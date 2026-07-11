<?php
// reports.php - Admin Profit & Loss Reports
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify admin login
check_auth();

// Resolve metrics date filters
$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all', 'daily', 'weekly', 'monthly'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'all';
}

$orders_date_clause = '';
$items_date_clause = '';
$date_on_clause = '';

if ($filter === 'daily') {
    $orders_date_clause = " WHERE order_date >= CURDATE()";
    $items_date_clause = " WHERE o.order_date >= CURDATE()";
    $date_on_clause = " AND o.order_date >= CURDATE()";
} elseif ($filter === 'weekly') {
    $orders_date_clause = " WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $items_date_clause = " WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $date_on_clause = " AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'monthly') {
    $orders_date_clause = " WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $items_date_clause = " WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $date_on_clause = " AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

try {
    // 1. Fetch Grand P&L Metrics
    // Total Revenue (Sales inclusive of tax)
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS total_revenue FROM orders $orders_date_clause");
    $total_revenue = (float)$stmt->fetch()['total_revenue'];

    // Total Pre-tax Revenue (sum of items)
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(oi.subtotal), 0) AS pretax_revenue 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        $items_date_clause
    ");
    $pretax_revenue = (float)$stmt->fetch()['pretax_revenue'];

    // Total Cost (cost_price * quantity sold)
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(oi.quantity * p.cost_price), 0) AS total_cost 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.product_id
        JOIN orders o ON oi.order_id = o.order_id
        $items_date_clause
    ");
    $total_cost = (float)$stmt->fetch()['total_cost'];

    // Total Profit (Pre-tax Revenue - Cost)
    $total_profit = $pretax_revenue - $total_cost;

    // Fetch unique categories for filtering
    $categories_stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

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

    // 2. Fetch Product-by-Product Sales Report
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
    $sales_report = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Report Aggregation Failed: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container my-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="display-6 font-heading text-bakery-brown mb-1">Financial & Sales Reports</h2>
            <p class="text-muted mb-0">Track store revenues, product costs, and automatic profit calculations.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="btn-group shadow-sm rounded-pill overflow-hidden bg-white p-1" role="group" aria-label="Reports Date Filter">
                <a href="reports.php?filter=all" class="btn btn-sm px-3 py-2 rounded-pill fw-semibold border-0 <?php echo $filter === 'all' ? 'btn-bakery text-white' : 'btn-light text-secondary'; ?>">All-Time</a>
                <a href="reports.php?filter=daily" class="btn btn-sm px-3 py-2 rounded-pill fw-semibold border-0 <?php echo $filter === 'daily' ? 'btn-bakery text-white' : 'btn-light text-secondary'; ?>">Daily</a>
                <a href="reports.php?filter=weekly" class="btn btn-sm px-3 py-2 rounded-pill fw-semibold border-0 <?php echo $filter === 'weekly' ? 'btn-bakery text-white' : 'btn-light text-secondary'; ?>">Weekly</a>
                <a href="reports.php?filter=monthly" class="btn btn-sm px-3 py-2 rounded-pill fw-semibold border-0 <?php echo $filter === 'monthly' ? 'btn-bakery text-white' : 'btn-light text-secondary'; ?>">Monthly</a>
            </div>
        </div>
    </div>

    <!-- P&L Dashboard Summary Widgets -->
    <div class="row g-4 mb-4">
        <!-- Revenue Card -->
        <div class="col-md-4">
            <div class="card border-0 rounded-4 shadow-sm bg-white p-4 h-100">
                <span class="text-muted d-block small text-uppercase fw-bold mb-2">Total Gross Sales</span>
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="fw-bold fs-2 text-primary mb-0">RM <?php echo number_format($total_revenue, 2); ?></h3>
                    <div class="fs-1 text-primary opacity-20"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                </div>
                <div class="mt-2 text-muted small">Sum of all customers' invoice totals.</div>
            </div>
        </div>

        <!-- Cost Card -->
        <div class="col-md-4">
            <div class="card border-0 rounded-4 shadow-sm bg-white p-4 h-100">
                <span class="text-muted d-block small text-uppercase fw-bold mb-2">Total Product Costs</span>
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="fw-bold fs-2 text-danger mb-0">RM <?php echo number_format($total_cost, 2); ?></h3>
                    <div class="fs-1 text-danger opacity-20"><i class="fa-solid fa-file-invoice"></i></div>
                </div>
                <div class="mt-2 text-muted small">Based on: cost_price &times; quantity sold.</div>
            </div>
        </div>

        <!-- Profit Card -->
        <div class="col-md-4">
            <div class="card border-0 rounded-4 shadow-sm p-4 h-100 <?php echo ($total_profit >= 0) ? 'bg-success bg-opacity-10 text-success-emphasis border border-success border-opacity-25' : 'bg-danger bg-opacity-10 text-danger-emphasis border border-danger border-opacity-25'; ?>" style="border-radius: 16px;">
                <span class="text-muted d-block small text-uppercase fw-bold mb-2">Net Profit</span>
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="fw-bold fs-2 mb-0">RM <?php echo number_format($total_profit, 2); ?></h3>
                    <div class="fs-1 opacity-20"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                </div>
                <div class="mt-2 small">Calculated as: gross sales - product costs.</div>
            </div>
        </div>
    </div>

    <!-- PDF Export Actions Box -->
    <div class="card border-0 rounded-4 shadow-sm bg-white p-4 mb-5">
        <h3 class="h5 font-heading text-bakery-brown mb-3 d-flex align-items-center">
            <i class="fa-solid fa-file-pdf text-danger me-2"></i>
            Export Administrative Reports
        </h3>
        <p class="text-muted small mb-4">Select a report format below to download a styled PDF copy of the store financials or inventory spreadsheets.</p>
        <div class="row g-3">
            <div class="col-md-6">
                <a href="export_pdf.php?type=sales&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($selected_category); ?>" target="_blank" class="btn btn-bakery py-3 w-100 shadow-sm text-center">
                    <i class="fa-solid fa-file-invoice-dollar me-2"></i> Export Sales P&L Report (PDF)
                </a>
            </div>
            <div class="col-md-6">
                <a href="export_pdf.php?type=inventory" target="_blank" class="btn btn-bakery-dark py-3 w-100 shadow-sm text-center">
                    <i class="fa-solid fa-boxes-stacked me-2"></i> Export Inventory Report (PDF)
                </a>
            </div>
        </div>
    </div>

    <!-- Product Breakdown Table -->
    <div class="row" id="breakdown-table">
        <div class="col-12">
            <div class="card border-0 rounded-4 shadow-sm bg-white p-4">
                <h3 class="h5 font-heading text-bakery-brown mb-4 d-flex align-items-center">
                    <i class="fa-solid fa-chart-pie text-warning me-2"></i>
                    Product Sales & Profitability Breakdown
                </h3>

                <!-- Search & Filter Form -->
                <form action="reports.php#breakdown-table" method="GET" class="row g-2 mb-4 align-items-center">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    
                    <div class="col-md-5 col-sm-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" name="search" class="form-control bg-light border-0" placeholder="Search by product name..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6">
                        <select name="category" class="form-select bg-light border-0">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($selected_category === $cat) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 col-sm-12 d-flex gap-2">
                        <button type="submit" class="btn btn-bakery flex-grow-1"><i class="fa-solid fa-magnifying-glass me-1"></i> Search & Filter</button>
                        <?php if ($search !== '' || $selected_category !== ''): ?>
                            <a href="reports.php?filter=<?php echo urlencode($filter); ?>#breakdown-table" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark"></i></a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="table-responsive p-0 border-0 shadow-none">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="ps-3">ID</th>
                                <th scope="col">Product</th>
                                <th scope="col" class="text-center">Units Sold</th>
                                <th scope="col">Cost Price</th>
                                <th scope="col">Selling Price</th>
                                <th scope="col">Gross Revenue</th>
                                <th scope="col">Total Cost</th>
                                <th scope="col" class="text-end pe-3">Net Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sales_report)): ?>
                                <?php foreach ($sales_report as $row): ?>
                                    <?php 
                                        $profit = (float)$row['product_profit'];
                                        $profit_class = ($profit >= 0) ? 'text-success fw-semibold' : 'text-danger fw-semibold';
                                    ?>
                                    <tr>
                                        <td class="ps-3 fw-mono text-muted">#<?php echo $row['product_id']; ?></td>
                                        <td>
                                            <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                            <small class="text-muted">Category: <?php echo htmlspecialchars($row['category']); ?></small>
                                        </td>
                                        <td class="text-center fw-bold"><?php echo htmlspecialchars($row['units_sold']); ?></td>
                                        <td>RM <?php echo number_format($row['cost_price'], 2); ?></td>
                                        <td>RM <?php echo number_format($row['price'], 2); ?></td>
                                        <td class="fw-semibold text-primary">RM <?php echo number_format($row['product_revenue'], 2); ?></td>
                                        <td class="text-secondary">RM <?php echo number_format($row['product_cost'], 2); ?></td>
                                        <td class="text-end pe-3 <?php echo $profit_class; ?>">
                                            RM <?php echo number_format($profit, 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-chart-line fa-3x mb-3"></i>
                                        <p class="mb-0"><?php echo ($search !== '' || $selected_category !== '') ? 'No products match your search or filter criteria.' : 'No sales transactions have occurred yet.'; ?></p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
