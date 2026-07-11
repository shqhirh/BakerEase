<?php
// pdf_helper.php - Customer Receipt PDF Generator

date_default_timezone_set('Asia/Kuala_Lumpur');

require_once __DIR__ . '/../libs/fpdf/fpdf.php';

if (!class_exists('BakerEasePDF')) {
    class BakerEasePDF extends FPDF {
        protected $reportTitle;

        public function setReportTitle($title) {
            $this->reportTitle = $title;
        }

        // Header override
        function Header() {
            // Shop Banner
            $this->SetFont('Helvetica', 'B', 15);
            $this->SetTextColor(78, 54, 41); // Bakery Brown
            $this->Cell(0, 10, 'BakerEase Management System', 0, 1, 'C');
            
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 4, 'Operations Management & Financial Tracking', 0, 1, 'C');
            $this->Cell(0, 4, 'Currency: RM', 0, 1, 'C');
            
            $this->Ln(5);
            $this->SetDrawColor(197, 168, 128); // Bakery Gold border
            $this->SetLineWidth(0.8);
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            $this->Ln(6);

            // Report Title
            $this->SetFont('Helvetica', 'B', 13);
            $this->SetTextColor(50, 50, 50);
            $this->Cell(0, 8, $this->reportTitle, 0, 1, 'L');
            $this->SetFont('Helvetica', 'I', 9);
            $this->SetTextColor(120, 120, 120);
            $this->Cell(0, 5, 'Date Generated: ' . date('d M Y, h:i A'), 0, 1, 'L');
            $this->Ln(6);
        }

        // Footer override
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Helvetica', 'I', 8);
            $this->SetTextColor(150, 150, 150);
            
            // Page number
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
            
            // Confidential tag on right
            $this->SetY(-15);
            $this->Cell(0, 10, 'INTERNAL ADMINISTRATIVE REPORT', 0, 0, 'R');
        }
    }
}

/**
 * Generates the PDF Receipt for a specific order.
 *
 * @param int $order_id
 * @param PDO $pdo
 * @param string $output_type 'I' to stream to browser, 'S' to return as a string
 * @return string|void
 */
function generate_receipt_pdf($order_id, $pdo, $output_type = 'I') {
    // Query order and customer details
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, c.name AS customer_name, c.phone_number, c.email, t.table_number 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.customer_id 
            LEFT JOIN tables t ON o.table_id = t.table_id
            WHERE o.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if (!$order) {
            die("Receipt record not found.");
        }

        // Query items in this order
        $stmt = $pdo->prepare("
            SELECT oi.*, p.product_name, p.category, p.price AS current_price 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.product_id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Database error during PDF generation: " . $e->getMessage());
    }

    $pdf = new BakerEasePDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->setReportTitle('Customer Invoice Receipt');
    $pdf->AddPage();

    // Render Invoice Customer Details block
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(78, 54, 41); // Bakery Brown
    $pdf->Cell(95, 6, 'CUSTOMER BILL TO:', 0, 0, 'L');
    $pdf->Cell(95, 6, 'INVOICE DETAILS:', 0, 1, 'R');

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(50, 50, 50);

    // Left Details: Customer
    $pdf->Cell(95, 5, 'Name: ' . $order['customer_name'], 0, 0, 'L');
    // Right Details: Invoice metadata
    $pdf->Cell(95, 5, 'Invoice ID: #BE-' . $order['order_id'], 0, 1, 'R');

    $pdf->Cell(95, 5, '', 0, 0, 'L');
    $pdf->Cell(95, 5, 'Order Date: ' . date('d M Y, h:i A', strtotime($order['order_date'])), 0, 1, 'R');

    $pdf->Cell(95, 5, '', 0, 0, 'L');
    $dining_str = ($order['dining_type'] === 'Dine-In') ? 'Dine-In (' . ($order['table_number'] ?? 'N/A') . ')' : 'Takeaway';
    $pdf->Cell(95, 5, 'Dining Option: ' . $dining_str, 0, 1, 'R');

    $pdf->Cell(95, 5, '', 0, 0, 'L');
    $pdf->Cell(95, 5, 'Order Status: ' . $order['status'], 0, 1, 'R');

    $pdf->Ln(8);

    // Items table header
    // Widths: Name(90), Category(30), Unit Price(25), Qty(20), Subtotal(25) = 190
    $pdf->SetFillColor(78, 54, 41);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(90, 8, 'Product Name', 1, 0, 'L', true);
    $pdf->Cell(30, 8, 'Category', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Unit Price', 1, 0, 'R', true);
    $pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Subtotal', 1, 1, 'R', true);

    // Items table body
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetFont('Helvetica', '', 9);
    $fill = false;
    $subtotal = 0.0;
    foreach ($items as $item) {
        $pdf->SetFillColor(250, 246, 242);
        $pdf->Cell(90, 8, $item['product_name'], 1, 0, 'L', $fill);
        $pdf->Cell(30, 8, $item['category'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 8, 'RM ' . number_format($item['current_price'], 2), 1, 0, 'R', $fill);
        $pdf->Cell(20, 8, $item['quantity'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 8, 'RM ' . number_format($item['subtotal'], 2), 1, 1, 'R', $fill);
        $fill = !$fill;
        $subtotal += (float)$item['subtotal'];
    }

    $pdf->Ln(4);

    // Subtotal row
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(165, 7, 'Subtotal (RM):', 1, 0, 'R', true);
    $pdf->Cell(25, 7, 'RM ' . number_format($subtotal, 2), 1, 1, 'R');

    // Grand total row
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(165, 8, 'Grand Total Paid (RM):', 1, 0, 'R', true);
    $pdf->SetTextColor(150, 0, 0); // Highlight totals in red
    $pdf->Cell(25, 8, 'RM ' . number_format($order['total_amount'], 2), 1, 1, 'R');

    $pdf->Ln(10);

    // Customer collection terms
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetFont('Helvetica', 'I', 8.5);
    $pdf->MultiCell(190, 4.5, "Important Collection Details: Please present this printed receipt or download the PDF copy on your mobile device at our bakery pickup counter to claim your sweets. If you have any inquiries regarding this order, feel free to call our operations desk.\n\nThank you for sweetening your day with BakerEase!", 0, 'C');

    return $pdf->Output($output_type, 'BakerEase_Receipt_' . $order['order_id'] . '.pdf');
}
