<?php
// receipt.php - Customer Receipt Invoice View
require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$viewable_orders = $_SESSION['viewable_orders'] ?? [];

// Access control check: Check if customer is authorized to view this specific order receipt
if ($order_id <= 0 || !in_array($order_id, $viewable_orders)) {
    die("Access Denied. You are not authorized to view this receipt.");
}

// Fetch Order and Customer details
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
        die("Receipt not found.");
    }

    // Fetch order items purchased
    $stmt = $pdo->prepare("
        SELECT oi.*, p.product_name, p.category, p.price AS current_price 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.product_id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error loading receipt: " . $e->getMessage());
}



require_once __DIR__ . '/includes/header.php';
?>

<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">
            
            <!-- Receipt Card Container -->
            <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden p-4 mb-4">
                
                <!-- Receipt Header -->
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center border-bottom pb-4 mb-4">
                    <div>
                        <h2 class="display-6 font-heading text-bakery-brown mb-1">BakerEase Receipt</h2>
                        <span class="text-secondary small">Thank you for supporting BakerEase!</span>
                    </div>
                    <div class="text-sm-end mt-3 mt-sm-0">
                        <span class="d-block text-muted small text-uppercase">Invoice ID</span>
                        <strong class="fs-4 text-dark">#BE-<?php echo $order['order_id']; ?></strong>
                    </div>
                </div>

                <!-- Customer & Date Meta Block -->
                <div class="row g-4 mb-4 bg-light p-3 rounded-3 mx-0">
                    <div class="col-sm-6">
                        <span class="text-muted d-block small text-uppercase fw-bold mb-1">Customer Profile</span>
                        <strong class="text-dark d-block"><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <span class="text-muted d-block small text-uppercase fw-bold mb-1">Details</span>
                        <span class="d-block fw-semibold text-dark"><?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></span>
                        <div class="mt-1">
                            <span class="small text-muted">Dining:</span>
                            <span class="fw-semibold text-dark">
                                <?php 
                                    if ($order['dining_type'] === 'Dine-In') {
                                        echo '<i class="fa-solid fa-chair text-primary me-1"></i> Dine-In (' . htmlspecialchars($order['table_number'] ?? 'N/A') . ')';
                                    } else {
                                        echo '<i class="fa-solid fa-bag-shopping text-warning me-1"></i> Takeaway';
                                    }
                                ?>
                            </span>
                        </div>
                        <div class="mt-2">
                            <span class="small text-muted me-2">Order Status:</span>
                            <?php 
                                $s = $order['status'];
                                if ($s === 'Completed') {
                                    echo '<span class="badge bg-success rounded-pill px-2.5 py-1">Completed</span>';
                                } elseif ($s === 'Paid') {
                                    echo '<span class="badge bg-primary rounded-pill px-2.5 py-1">Paid</span>';
                                } elseif ($s === 'Pending Payment') {
                                    echo '<span class="badge bg-warning text-dark rounded-pill px-2.5 py-1">Pending Payment</span>';
                                } else {
                                    echo '<span class="badge bg-secondary rounded-pill px-2.5 py-1">Pending</span>';
                                }
                            ?>
                        </div>
                        <div class="mt-2">
                            <span class="small text-muted me-2">Payment Method:</span>
                            <span class="fw-semibold text-dark">
                                <?php 
                                    $pm = $order['payment_method'] ?? 'Cash';
                                    if ($pm === 'ToyyibPay') {
                                        echo '<i class="fa-solid fa-globe text-primary me-1"></i> ToyyibPay';
                                    } elseif ($pm === 'Card') {
                                        echo '<i class="fa-solid fa-credit-card text-success me-1"></i> Credit/Debit Card';
                                    } else {
                                        echo '<i class="fa-solid fa-wallet text-warning me-1"></i> Cash';
                                    }
                                ?>
                            </span>
                        </div>
                        <div class="mt-2">
                            <span class="small text-muted me-2">Payment Status:</span>
                            <?php 
                                $ps = $order['payment_status'] ?? 'Pending';
                                if ($ps === 'Paid') {
                                    echo '<span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2 py-0.5">Paid</span>';
                                } elseif ($ps === 'Failed') {
                                    echo '<span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 rounded-pill px-2 py-0.5">Failed</span>';
                                } else {
                                    echo '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25 rounded-pill px-2 py-0.5">Pending</span>';
                                }
                            ?>
                        </div>
                    </div>
                </div>

                <?php if ($order['payment_method'] === 'Cash' && $order['status'] === 'Pending Payment'): ?>
                    <div class="alert alert-warning border-0 shadow-sm mb-4 d-flex align-items-center rounded-3" role="alert">
                        <i class="fa-solid fa-wallet fs-4 me-3 text-warning"></i>
                        <div>
                            <strong class="text-warning-emphasis">Cash Payment Required:</strong> Please proceed to the bakery cashier counter to complete your payment of <strong>RM <?php echo number_format($order['total_amount'], 2); ?></strong> for this order.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Table items breakdown -->
                <h4 class="h6 font-heading text-secondary border-bottom pb-2 mb-3">Purchased Items</h4>
                <div class="table-responsive p-0 border-0 shadow-none mb-4">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="ps-0">Product Name</th>
                                <th scope="col">Category</th>
                                <th scope="col" class="text-center">Price</th>
                                <th scope="col" class="text-center">Quantity</th>
                                <th scope="col" class="text-end pe-0">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="ps-0"><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                                    <td><span class="badge bg-secondary-subtle text-secondary rounded-pill"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                    <td class="text-center">RM <?php echo number_format($item['current_price'], 2); ?></td>
                                    <td class="text-center fw-bold"><?php echo $item['quantity']; ?></td>
                                    <td class="text-end pe-0 fw-semibold text-dark">RM <?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php 
                                $subtotal = 0.0;
                                foreach ($items as $item) {
                                    $subtotal += (float)$item['subtotal'];
                                }
                            ?>
                            <tr class="table-light">
                                <td colspan="4" class="text-end fw-bold py-2.5 ps-0">Subtotal:</td>
                                <td class="text-end pe-0 fw-semibold text-dark py-2.5">RM <?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="4" class="text-end fw-bold py-2.5 ps-0">Grand Total Paid:</td>
                                <td class="text-end pe-0 fw-bold text-danger py-2.5 fs-5">RM <?php echo number_format($order['total_amount'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Notice Block -->
                <div class="text-center text-muted small border-top pt-4">
                    <p class="mb-1"><i class="fa-solid fa-heart text-danger me-1"></i> Fresh desserts baked with love daily.</p>
                    <p class="mb-0">Please present this screen or downloaded PDF at checkout counter to pick up your order.</p>
                </div>
            </div>

            <!-- Receipt Actions Row -->
            <div class="d-flex flex-column flex-sm-row justify-content-between gap-3 mb-5">
                <a href="index.php" class="btn btn-bakery-outline py-2.5 px-4 shadow-sm order-2 order-sm-1">
                    <i class="fa-solid fa-arrow-left me-1"></i> Return to Dessert Menu
                </a>
                <div class="d-flex gap-2 order-1 order-sm-2">
                    <button onclick="window.print()" class="btn btn-secondary py-2.5 px-4 shadow-sm">
                        <i class="fa-solid fa-print me-1"></i> Print Receipt
                    </button>
                    <!-- PDF Download link with order_id -->
                    <a href="admin/export_pdf.php?type=receipt&order_id=<?php echo $order['order_id']; ?>" target="_blank" class="btn btn-bakery py-2.5 px-4 shadow-sm text-decoration-none text-white">
                        <i class="fa-solid fa-file-pdf me-1"></i> Download PDF Receipt
                    </a>
                </div>
            </div>
            
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
