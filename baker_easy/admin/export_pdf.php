<?php
// export_pdf.php - PDF Exporter (Admin Reports & Customer Receipts)

date_default_timezone_set('Asia/Kuala_Lumpur');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$type = $_GET['type'] ?? 'sales'; // Default report type is sales

// Security & Access Control Check
if ($type === 'receipt') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    $viewable_orders = $_SESSION['viewable_orders'] ?? [];
    
    // Admins can download any receipt; customers can only download their current session receipts
    $is_admin = isset($_SESSION['admin_id']);
    
    if ($order_id <= 0 || (!$is_admin && !in_array($order_id, $viewable_orders))) {
        die("Access Denied. You are not authorized to view this receipt.");
    }

    // Load helper and generate PDF
    require_once __DIR__ . '/../includes/pdf_helper.php';
    generate_receipt_pdf($order_id, $pdo, 'I');
    exit;
} else {
    // Admin reports (sales P&L / inventory) require admin check
    check_auth();
}

// Load PDF Helper for reports (which includes FPDF and BakerEasePDF class)
require_once __DIR__ . '/../includes/pdf_helper.php';

// Resolve metrics date filters
$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all', 'daily', 'weekly', 'monthly'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'all';
}

$orders_date_clause = '';
$items_date_clause = '';
$date_on_clause = '';
$title_period = 'All-Time';

if ($filter === 'daily') {
    $orders_date_clause = " WHERE order_date >= CURDATE()";
    $items_date_clause = " WHERE o.order_date >= CURDATE()";
    $date_on_clause = " AND o.order_date >= CURDATE()";
    $title_period = 'Daily (Today)';
} elseif ($filter === 'weekly') {
    $orders_date_clause = " WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $items_date_clause = " WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $date_on_clause = " AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $title_period = 'Weekly (Last 7 Days)';
} elseif ($filter === 'monthly') {
    $orders_date_clause = " WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $items_date_clause = " WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $date_on_clause = " AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $title_period = 'Monthly (Last 30 Days)';
}

// Instantiate PDF
$pdf = new BakerEasePDF('P', 'mm', 'A4');
$pdf->AliasNbPages();

// --- 1. SALES REPORT EXPORTER ---
if ($type === 'sales') {
    // Resolve search and category filters
    $search = trim($_GET['search'] ?? '');
    $selected_category = trim($_GET['category'] ?? '');

    $where_clauses = [];
    $params = [];

    if ($search !== '') {
        $where_clauses[] = "p.product_name LIKE ?";
        $params[] = "%$search%";
    }

    if ($selected_category !== '') {
        $where_clauses[] = "p.category = ?";
        $params[] = $selected_category;
    }

    $where_section = '';
    if (!empty($where_clauses)) {
        $where_section = "WHERE " . implode(" AND ", $where_clauses);
    }

    $pdf_title = "Profit & Loss Report - $title_period";
    if ($search !== '' || $selected_category !== '') {
        $pdf_title .= " (Filtered)";
    }
    $pdf->setReportTitle($pdf_title);
    $pdf->AddPage();

    // Query Sales Data
    try {
        $query = "
            SELECT p.product_id, p.product_name, p.category, p.price, p.cost_price, 
                   COALESCE(SUM(sales.quantity), 0) AS units_sold, 
                   COALESCE(SUM(sales.subtotal), 0) AS product_revenue, 
                   COALESCE(SUM(sales.quantity * p.cost_price), 0) AS product_cost, 
                   COALESCE(SUM(sales.subtotal - (sales.quantity * p.cost_price)), 0) AS product_profit 
            FROM products p 
            LEFT JOIN (
                SELECT oi.product_id, oi.quantity, oi.subtotal
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                $orders_date_clause
            ) sales ON p.product_id = sales.product_id
            $where_section
            GROUP BY p.product_id 
            ORDER BY units_sold DESC, p.product_name ASC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $sales_data = $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Sales query error: " . $e->getMessage());
    }

    // Render Table Header
    // Column widths: ID(15), Name(55), Category(25), Sold(20), Cost(25), Price(25), Profit(25) = 190
    $pdf->SetFillColor(78, 54, 41); // Bakery Brown Fill
    $pdf->SetTextColor(255, 255, 255); // White Text
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->SetLineWidth(0.3);
    $pdf->SetFont('Helvetica', 'B', 9);

    $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
    $pdf->Cell(55, 8, 'Product Name', 1, 0, 'L', true);
    $pdf->Cell(25, 8, 'Category', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Sold', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Unit Cost', 1, 0, 'R', true);
    $pdf->Cell(25, 8, 'Unit Price', 1, 0, 'R', true);
    $pdf->Cell(25, 8, 'Net Profit', 1, 1, 'R', true);

    // Render Table Body
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetFont('Helvetica', '', 9);
    
    $total_revenue = 0.0;
    $total_cost = 0.0;
    $total_profit = 0.0;
    $fill = false;

    foreach ($sales_data as $row) {
        $units = (int)$row['units_sold'];
        $cost = (float)$row['product_cost'];
        $rev = (float)$row['product_revenue'];
        $prof = (float)$row['product_profit'];

        $total_revenue += $rev;
        $total_cost += $cost;
        $total_profit += $prof;

        // Alternating row background color
        $pdf->SetFillColor(250, 246, 242); // Light Warm cream
        $pdf->Cell(15, 8, '#' . $row['product_id'], 1, 0, 'C', $fill);
        $pdf->Cell(55, 8, $row['product_name'], 1, 0, 'L', $fill);
        $pdf->Cell(25, 8, $row['category'], 1, 0, 'C', $fill);
        $pdf->Cell(20, 8, $units, 1, 0, 'C', $fill);
        $pdf->Cell(25, 8, 'RM ' . number_format($row['cost_price'], 2), 1, 0, 'R', $fill);
        $pdf->Cell(25, 8, 'RM ' . number_format($row['price'], 2), 1, 0, 'R', $fill);
        
        // Highlight profit negative or positive
        if ($prof < 0) {
            $pdf->SetTextColor(150, 0, 0);
        } else {
            $pdf->SetTextColor(0, 100, 0);
        }
        $pdf->Cell(25, 8, 'RM ' . number_format($prof, 2), 1, 1, 'R', $fill);
        $pdf->SetTextColor(50, 50, 50); // reset text color
        
        $fill = !$fill;
    }

    $pdf->Ln(5);

    // Summary Section
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetFillColor(245, 245, 245);

    // Total Gross Sales (Inclusive of Tax)
    $pdf->Cell(140, 8, 'Total Gross Revenue (Sales)', 1, 0, 'R', true);
    $pdf->Cell(50, 8, 'RM ' . number_format($total_revenue, 2), 1, 1, 'R');

    // Total Product Material Costs
    $pdf->Cell(140, 8, 'Total Product Material Costs', 1, 0, 'R', true);
    $pdf->Cell(50, 8, 'RM ' . number_format($total_cost, 2), 1, 1, 'R');

    $pdf->SetFillColor(197, 168, 128); // Gold highlighting for profit
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(140, 9, 'Net Profit', 1, 0, 'R', true);
    $pdf->SetTextColor(78, 54, 41);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(50, 9, 'RM ' . number_format($total_profit, 2), 1, 1, 'R');

    // Clean outputs
    $pdf->Output('I', 'BakerEase_Sales_Report_' . date('Y-m-d') . '.pdf');
}

// --- 2. INVENTORY STATUS REPORT EXPORTER ---
elseif ($type === 'inventory') {
    $pdf->setReportTitle('Inventory Levels & Valuation Report');
    $pdf->AddPage();

    // Query Inventory data
    try {
        $stmt = $pdo->query("
            SELECT product_id, product_name, category, price, cost_price, stock_quantity 
            FROM products 
            ORDER BY stock_quantity ASC, product_name ASC
        ");
        $inventory_data = $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Inventory query error: " . $e->getMessage());
    }

    // Render Table Header
    // Column widths: ID(15), Name(65), Category(30), Price(25), Stock(25), Valuation(30) = 190
    $pdf->SetFillColor(78, 54, 41);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->SetLineWidth(0.3);
    $pdf->SetFont('Helvetica', 'B', 9);

    $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
    $pdf->Cell(65, 8, 'Product Name', 1, 0, 'L', true);
    $pdf->Cell(30, 8, 'Category', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Unit Price', 1, 0, 'R', true);
    $pdf->Cell(25, 8, 'Stock Qty', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Stock Value', 1, 1, 'R', true);

    // Render Table Body
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetFont('Helvetica', '', 9);
    
    $total_items = 0;
    $total_stock_qty = 0;
    $total_valuation = 0.0;
    $low_stock_count = 0;
    $fill = false;

    foreach ($inventory_data as $row) {
        $qty = (int)$row['stock_quantity'];
        $val = $qty * (float)$row['price'];
        
        $total_items++;
        $total_stock_qty += $qty;
        $total_valuation += $val;

        if ($qty < 5) {
            $low_stock_count++;
        }

        $pdf->SetFillColor(250, 246, 242);
        $pdf->Cell(15, 8, '#' . $row['product_id'], 1, 0, 'C', $fill);
        $pdf->Cell(65, 8, $row['product_name'], 1, 0, 'L', $fill);
        $pdf->Cell(30, 8, $row['category'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 8, 'RM ' . number_format($row['price'], 2), 1, 0, 'R', $fill);
        
        // Highlight low stock quantities
        if ($qty === 0) {
            $pdf->SetTextColor(200, 0, 0);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(25, 8, 'OUT OF STOCK', 1, 0, 'C', $fill);
        } elseif ($qty < 5) {
            $pdf->SetTextColor(180, 100, 0);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(25, 8, $qty . ' (LOW)', 1, 0, 'C', $fill);
        } else {
            $pdf->Cell(25, 8, $qty, 1, 0, 'C', $fill);
        }
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont('Helvetica', '', 9);
        
        $pdf->Cell(30, 8, 'RM ' . number_format($val, 2), 1, 1, 'R', $fill);
        
        $fill = !$fill;
    }

    $pdf->Ln(5);

    // Summary Section
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(135, 8, 'Total Distinct Dessert Types', 1, 0, 'R', true);
    $pdf->Cell(55, 8, $total_items . ' items', 1, 1, 'R');

    $pdf->Cell(135, 8, 'Total Cumulative Stock Units', 1, 0, 'R', true);
    $pdf->Cell(55, 8, $total_stock_qty . ' units', 1, 1, 'R');

    $pdf->Cell(135, 8, 'Total Warning Low Stock Items (< 5 units)', 1, 0, 'R', true);
    $pdf->SetTextColor(180, 100, 0);
    $pdf->Cell(55, 8, $low_stock_count . ' items', 1, 1, 'R');
    $pdf->SetTextColor(50, 50, 50);

    $pdf->SetFillColor(78, 54, 41); // Highlight valuation summary in brown
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(135, 9, 'Total Retail Inventory Asset Valuation', 1, 0, 'R', true);
    $pdf->SetTextColor(197, 168, 128); // Gold text
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(55, 9, 'RM ' . number_format($total_valuation, 2), 1, 1, 'R');

    $pdf->Output('I', 'BakerEase_Inventory_Report_' . date('Y-m-d') . '.pdf');
}
?>
