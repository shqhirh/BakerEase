<?php
// inventory.php - Admin Stock Management
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify admin login
check_auth();

$error = '';
$success = '';

// Handle stock level quick updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $new_stock = (int)($_POST['stock_quantity'] ?? 0);

    if ($product_id <= 0 || $new_stock < 0) {
        $error = "Invalid product ID or stock level. Stock cannot be negative.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
            $stmt->execute([$new_stock, $product_id]);
            $success = "Stock quantity updated successfully!";
        } catch (PDOException $e) {
            $error = "Failed to update stock: " . $e->getMessage();
        }
    }
}

// Fetch all products, sorted by stock quantity ascending (critical low stock first)
try {
    // 1. Fetch all stock levels for the global metrics cards
    $all_stmt = $pdo->query("SELECT stock_quantity FROM products");
    $all_inventory_for_counts = $all_stmt->fetchAll();

    // 2. Fetch filtered products if search is active
    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT product_id, product_name, category, stock_quantity, price FROM products WHERE product_name LIKE ? OR category LIKE ? ORDER BY stock_quantity ASC, product_name ASC");
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT product_id, product_name, category, stock_quantity, price FROM products ORDER BY stock_quantity ASC, product_name ASC");
    }
    $inventory = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Inventory Query Failed: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container my-5">
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h2 class="display-6 font-heading text-bakery-brown mb-1">Stock & Inventory Management</h2>
            <p class="text-muted mb-0">Monitor stock levels, set low-stock triggers, and update quantities instantly.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <!-- PDF Export for Inventory -->
            <a href="export_pdf.php?type=inventory" target="_blank" class="btn btn-bakery-dark py-2 px-4 shadow-sm">
                <i class="fa-solid fa-file-pdf me-1"></i> Export Inventory Report (PDF)
            </a>
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

    <!-- Summary Stock Alerts Header -->
    <div class="row g-4 mb-5">
        <?php
            $out_of_stock_count = 0;
            $low_stock_count = 0;
            foreach ($all_inventory_for_counts as $item) {
                $q = (int)$item['stock_quantity'];
                if ($q === 0) $out_of_stock_count++;
                elseif ($q < 5) $low_stock_count++;
            }
        ?>
        <div class="col-md-6">
            <div class="card border-0 rounded-4 shadow-sm p-3 <?php echo ($out_of_stock_count > 0) ? 'out-of-stock-alert' : 'bg-white'; ?>">
                <div class="d-flex align-items-center">
                    <div class="px-3 py-2 bg-danger bg-opacity-10 text-danger rounded-3 fs-3 me-3">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-dark fw-bold">Critical Out of Stock</h6>
                        <span class="fs-5 fw-bold text-danger"><?php echo $out_of_stock_count; ?> products</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 rounded-4 shadow-sm p-3 <?php echo ($low_stock_count > 0) ? 'low-stock-alert' : 'bg-white'; ?>">
                <div class="d-flex align-items-center">
                    <div class="px-3 py-2 bg-warning bg-opacity-10 text-warning rounded-3 fs-3 me-3">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-dark fw-bold">Warning Low Stock (Under 5)</h6>
                        <span class="fs-5 fw-bold text-warning-emphasis"><?php echo $low_stock_count; ?> products</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="card border-0 rounded-4 shadow-sm bg-white p-3 mb-4">
        <form action="inventory.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-8 col-sm-7">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control bg-light border-0" placeholder="Search by product name or category" value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4 col-sm-5 d-flex gap-2">
                <button type="submit" class="btn btn-bakery-dark w-100"><i class="fa-solid fa-magnifying-glass me-1"></i> Search</button>
                <?php if ($search !== ''): ?>
                    <a href="inventory.php" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Inventory Stock Table -->
    <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden mb-5">
        <div class="table-responsive p-0 border-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="ps-4">Product ID</th>
                        <th scope="col">Product Name</th>
                        <th scope="col">Category</th>
                        <th scope="col">Unit Price</th>
                        <th scope="col">Current Stock Status</th>
                        <th scope="col" class="text-end pe-4" style="min-width: 250px;">Quick Stock Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($inventory)): ?>
                        <?php foreach ($inventory as $prod): ?>
                            <?php 
                                $qty = (int)$prod['stock_quantity']; 
                                $row_class = '';
                                if ($qty === 0) {
                                    $row_class = 'table-danger-subtle';
                                } elseif ($qty < 5) {
                                    $row_class = 'table-warning-subtle';
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td class="ps-4 fw-mono text-muted">#<?php echo $prod['product_id']; ?></td>
                                <td>
                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($prod['product_name']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill px-2.5 py-1"><?php echo htmlspecialchars($prod['category']); ?></span>
                                </td>
                                <td>RM <?php echo number_format($prod['price'], 2); ?></td>
                                <td>
                                    <?php 
                                        if ($qty === 0) {
                                            echo '<span class="badge bg-danger rounded-pill px-3 py-1.5"><i class="fa-solid fa-circle-xmark me-1"></i>Out of Stock</span>';
                                        } elseif ($qty < 5) {
                                            echo '<span class="badge bg-warning text-dark rounded-pill px-3 py-1.5"><i class="fa-solid fa-triangle-exclamation me-1"></i>Low Stock ('.$qty.')</span>';
                                        } else {
                                            echo '<span class="badge bg-success rounded-pill px-3 py-1.5"><i class="fa-solid fa-circle-check me-1"></i>Healthy ('.$qty.')</span>';
                                        }
                                    ?>
                                </td>
                                <td class="text-end pe-4">
                                    <form action="inventory.php" method="POST" class="d-inline-flex justify-content-end align-items-center gap-2">
                                        <input type="hidden" name="action" value="update_stock">
                                        <input type="hidden" name="product_id" value="<?php echo $prod['product_id']; ?>">
                                        <div class="input-group input-group-sm" style="width: 130px;">
                                            <input type="number" name="stock_quantity" class="form-control text-center py-1 fw-bold" min="0" value="<?php echo $qty; ?>" required>
                                            <span class="input-group-text bg-light">qty</span>
                                        </div>
                                        <button type="submit" class="btn btn-bakery-dark btn-sm px-3 py-1.5 rounded-3">
                                            <i class="fa-solid fa-rotate me-1"></i> Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-boxes-stacked fa-3x mb-3"></i>
                                <p class="mb-0"><?php echo ($search !== '') ? 'No products match your search query.' : 'No products to track. Please add products first.'; ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
