<?php
// orders.php - Admin Order Management
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify admin login
check_auth();

$error = '';
$success = '';

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');

    $allowed_statuses = ['Pending', 'Paid', 'Completed', 'Pending Payment'];
    if ($order_id <= 0 || !in_array($new_status, $allowed_statuses)) {
        $error = "Invalid order ID or status selection.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->execute([$new_status, $order_id]);
            $success = "Order status updated to '{$new_status}' successfully!";
        } catch (PDOException $e) {
            $error = "Failed to update order status: " . $e->getMessage();
        }
    }
}

// Handle Order Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_order') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    if ($order_id <= 0) {
        $error = "Invalid order ID.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM orders WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $success = "Order #{$order_id} deleted successfully!";
        } catch (PDOException $e) {
            $error = "Failed to delete order: " . $e->getMessage();
        }
    }
}

// Fetch details for Modal display if requested
$order_details = null;
$order_items = [];
if (isset($_GET['order_id'])) {
    $detail_order_id = (int)$_GET['order_id'];
    try {
        // Fetch order + customer details
        $stmt = $pdo->prepare("
            SELECT o.*, c.name AS customer_name, c.phone_number, c.email, t.table_number 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.customer_id 
            LEFT JOIN tables t ON o.table_id = t.table_id
            WHERE o.order_id = ?
        ");
        $stmt->execute([$detail_order_id]);
        $order_details = $stmt->fetch();

        if ($order_details) {
            // Fetch order items details
            $stmt = $pdo->prepare("
                SELECT oi.*, p.product_name, p.price AS current_price, p.category 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.product_id 
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$detail_order_id]);
            $order_items = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error = "Error loading order details: " . $e->getMessage();
    }
}

// Fetch all orders
$search = trim($_GET['search'] ?? '');
try {
    if ($search !== '') {
        $stmt = $pdo->prepare("
            SELECT o.*, c.name AS customer_name, c.phone_number, t.table_number 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.customer_id 
            LEFT JOIN tables t ON o.table_id = t.table_id
            WHERE c.name LIKE ? 
               OR c.phone_number LIKE ? 
               OR o.order_id = ? 
            ORDER BY o.order_date DESC
        ");
        $stmt->execute(["%$search%", "%$search%", (int)$search]);
    } else {
        $stmt = $pdo->query("
            SELECT o.*, c.name AS customer_name, c.phone_number, t.table_number 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.customer_id 
            LEFT JOIN tables t ON o.table_id = t.table_id
            ORDER BY o.order_date DESC
        ");
    }
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Orders query failed: " . $e->getMessage());
}

// Highlight ID from dashboard
$highlight_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container my-5">
    <div class="row align-items-center mb-4">
        <div class="col-12">
            <h2 class="display-6 font-heading text-bakery-brown mb-1">Customer Order Management</h2>
            <p class="text-muted mb-0">View recent orders, check customer contact details, and update processing status.</p>
        </div>
    </div>

    <!-- Notifications -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Search Form -->
    <div class="card border-0 rounded-4 shadow-sm bg-white p-3 mb-4">
        <form action="orders.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-8 col-sm-7">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control bg-light border-0" placeholder="Search by Order ID, Customer Name and Phone Number" value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4 col-sm-5 d-flex gap-2">
                <button type="submit" class="btn btn-bakery-dark w-100"><i class="fa-solid fa-magnifying-glass me-1"></i> Search</button>
                <?php if ($search !== ''): ?>
                    <a href="orders.php" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Orders Table List -->
    <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden mb-5">
        <div class="table-responsive p-0 border-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="ps-4">Order ID</th>
                        <th scope="col">Customer Name</th>
                        <th scope="col">Phone Number</th>
                        <th scope="col">Order Date</th>
                        <th scope="col">Dining</th>
                        <th scope="col">Total Amount</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                            <?php 
                                $is_highlighted = ($order['order_id'] === $highlight_id || $order['order_id'] === (int)($_GET['order_id'] ?? 0));
                                $row_style = $is_highlighted ? 'background-color: rgba(197, 168, 128, 0.15); font-weight: 500;' : '';
                            ?>
                            <tr style="<?php echo $row_style; ?>">
                                <td class="ps-4 fw-bold">#<?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['phone_number']); ?></td>
                                <td class="small text-secondary"><?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <?php 
                                        if ($order['dining_type'] === 'Dine-In') {
                                            echo '<span class="badge bg-primary-subtle text-primary rounded-pill"><i class="fa-solid fa-chair me-1"></i> Dine-In (' . htmlspecialchars($order['table_number'] ?? 'N/A') . ')</span>';
                                        } else {
                                            echo '<span class="badge bg-warning-subtle text-warning-emphasis rounded-pill"><i class="fa-solid fa-bag-shopping me-1"></i> Takeaway</span>';
                                        }
                                    ?>
                                </td>
                                <td class="fw-semibold text-dark">RM <?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <form action="orders.php" method="POST" class="d-inline-block">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <select name="status" class="form-select form-select-sm rounded-pill px-2.5 py-1 fw-semibold text-center border-0 shadow-sm"
                                                onchange="this.form.submit()"
                                                style="width: 130px; 
                                                <?php 
                                                    $s = $order['status'];
                                                    if ($s === 'Completed') {
                                                        echo 'background-color: #d1e7dd; color: #0f5132;';
                                                    } elseif ($s === 'Paid') {
                                                        echo 'background-color: #cfe2ff; color: #084298;';
                                                    } elseif ($s === 'Pending Payment') {
                                                        echo 'background-color: #ffe8cc; color: #a65100;';
                                                    } else {
                                                        echo 'background-color: #fff3cd; color: #664d03;';
                                                    }
                                                ?>">
                                            <option value="Pending" <?php echo ($s === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Pending Payment" <?php echo ($s === 'Pending Payment') ? 'selected' : ''; ?>>Pending Payment</option>
                                            <option value="Paid" <?php echo ($s === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                                            <option value="Completed" <?php echo ($s === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-2">
                                        <a href="orders.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-bakery-outline btn-sm py-1.5 px-3 rounded-3">
                                            <i class="fa-solid fa-list-check me-1"></i> View Items
                                        </a>
                                        <form action="orders.php" method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to delete Order #<?php echo $order['order_id']; ?>? This action will permanently remove the order and all its items.');">
                                            <input type="hidden" name="action" value="delete_order">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-1.5 px-3 rounded-3">
                                                <i class="fa-solid fa-trash-can me-1"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-receipt fa-3x mb-3"></i>
                                <p class="mb-0"><?php echo ($search !== '') ? 'No orders match your search query.' : 'No customer orders recorded yet.'; ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- ORDER DETAILS MODAL (AUTOMATICALLY LOADS IF order_details EXISTS) -->
<?php if ($order_details): ?>
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-light px-4">
                    <h5 class="modal-title font-heading text-bakery-brown" id="orderDetailsModalLabel">
                        <i class="fa-solid fa-receipt me-1 text-warning"></i> 
                        Invoice & Order Details #<?php echo $order_details['order_id']; ?>
                    </h5>
                    <button type="button" class="btn-close" onclick="window.location.href='orders.php'" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <!-- Customer Information Block -->
                    <div class="row g-3 bg-light p-3 rounded-3 mb-4">
                        <div class="col-md-6">
                            <span class="text-muted d-block small text-uppercase fw-bold">Customer Contact</span>
                            <strong class="fs-5 text-dark"><?php echo htmlspecialchars($order_details['customer_name']); ?></strong>
                            <div class="text-secondary small mt-1">
                                <i class="fa-solid fa-phone me-1"></i> <?php echo htmlspecialchars($order_details['phone_number']); ?>
                            </div>
                            <?php if (!empty($order_details['email'])): ?>
                                <div class="text-secondary small">
                                    <i class="fa-solid fa-envelope me-1"></i> <?php echo htmlspecialchars($order_details['email']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="text-muted d-block small text-uppercase fw-bold">Order Placement</span>
                            <span class="text-dark d-block fw-semibold"><?php echo date('d M Y, h:i A', strtotime($order_details['order_date'])); ?></span>
                            <div class="mt-2">
                                <span class="small text-muted me-2">Dining Type:</span>
                                <?php 
                                    if ($order_details['dining_type'] === 'Dine-In') {
                                        echo '<span class="fw-semibold text-dark"><i class="fa-solid fa-chair text-primary me-1"></i> Dine-In (' . htmlspecialchars($order_details['table_number'] ?? 'N/A') . ')</span>';
                                    } else {
                                        echo '<span class="fw-semibold text-dark"><i class="fa-solid fa-bag-shopping text-warning me-1"></i> Takeaway</span>';
                                    }
                                ?>
                            </div>
                            <div class="mt-2">
                                <span class="small text-muted me-2">Order Status:</span>
                                <?php 
                                    $s = $order_details['status'];
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
                                        $pm = $order_details['payment_method'] ?? 'Cash';
                                        if ($pm === 'ToyyibPay') {
                                            echo '<i class="fa-solid fa-globe text-primary me-1"></i> ToyyibPay';
                                        } elseif ($pm === 'Card') {
                                            echo '<i class="fa-solid fa-credit-card text-success me-1"></i> Card';
                                        } else {
                                            echo '<i class="fa-solid fa-wallet text-warning me-1"></i> Cash';
                                        }
                                    ?>
                                </span>
                            </div>
                            <div class="mt-2">
                                <span class="small text-muted me-2">Payment Status:</span>
                                <?php 
                                    $ps = $order_details['payment_status'] ?? 'Pending';
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

                    <!-- Items Purchased Table -->
                    <h6 class="font-heading mb-3 text-secondary border-bottom pb-2">Purchased Items</h6>
                    <div class="table-responsive p-0 shadow-none border-0 mb-3">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th class="text-center">Unit Price</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary rounded-pill"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                        <td class="text-center">RM <?php echo number_format($item['current_price'], 2); ?></td>
                                        <td class="text-center fw-bold"><?php echo $item['quantity']; ?></td>
                                        <td class="text-end fw-semibold text-dark">RM <?php echo number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php 
                                    $subtotal = 0.0;
                                    foreach ($order_items as $item) {
                                        $subtotal += (float)$item['subtotal'];
                                    }
                                ?>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold py-2">Subtotal:</td>
                                    <td class="text-end fw-semibold text-dark py-2">RM <?php echo number_format($subtotal, 2); ?></td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="4" class="text-end fw-bold py-2">Grand Total:</td>
                                    <td class="text-end fw-bold text-danger py-2 fs-5">RM <?php echo number_format($order_details['total_amount'], 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="modal-footer border-light px-4">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='orders.php'">Close</button>
                    

                    <!-- Quick Print Button using standard window print or link to PDF -->
                    <button type="button" class="btn btn-bakery-dark" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Print Invoice</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Script to trigger details modal immediately -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const detailModal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            detailModal.show();
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
