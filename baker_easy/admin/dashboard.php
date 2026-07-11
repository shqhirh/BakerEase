<?php
// dashboard.php - Admin Main Dashboard
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify admin login
check_auth();

$success = '';
$error = '';

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

// Handle Add Table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_table') {
    $table_number = trim($_POST['table_number'] ?? '');
    if (empty($table_number)) {
        $error = "Table number cannot be empty.";
    } elseif (strlen($table_number) > 10) {
        $error = "Table number cannot be longer than 10 characters.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM tables WHERE table_number = ?");
            $stmt->execute([$table_number]);
            if ($stmt->fetch()) {
                $error = "Table '{$table_number}' already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO tables (table_number) VALUES (?)");
                $stmt->execute([$table_number]);
                $success = "Table '{$table_number}' added successfully!";
            }
        } catch (PDOException $e) {
            $error = "Failed to add table: " . $e->getMessage();
        }
    }
}

// Handle Delete Table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_table') {
    $table_id = (int)($_POST['table_id'] ?? 0);
    if ($table_id <= 0) {
        $error = "Invalid table ID.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT 1 FROM orders 
                WHERE table_id = ? AND dining_type = 'Dine-In' AND status != 'Completed' AND payment_status != 'Failed'
            ");
            $stmt->execute([$table_id]);
            if ($stmt->fetch()) {
                $error = "Cannot delete table because it is currently occupied.";
            } else {
                $stmt = $pdo->prepare("SELECT table_number FROM tables WHERE table_id = ?");
                $stmt->execute([$table_id]);
                $tbl = $stmt->fetch();
                $tbl_num = $tbl ? $tbl['table_number'] : '';

                $stmt = $pdo->prepare("DELETE FROM tables WHERE table_id = ?");
                $stmt->execute([$table_id]);
                $success = "Table '{$tbl_num}' deleted successfully!";
            }
        } catch (PDOException $e) {
            $error = "Failed to delete table: " . $e->getMessage();
        }
    }
}

// Resolve metrics date filters
$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all', 'daily', 'weekly', 'monthly'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'all';
}

$orders_date_clause = '';
$items_date_clause = '';

if ($filter === 'daily') {
    $orders_date_clause = " WHERE order_date >= CURDATE()";
    $items_date_clause = " WHERE o.order_date >= CURDATE()";
} elseif ($filter === 'weekly') {
    $orders_date_clause = " WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $items_date_clause = " WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'monthly') {
    $orders_date_clause = " WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $items_date_clause = " WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

// Fetch summary metrics
try {
    // 1. Total Products (or unique products sold in the period)
    if ($filter === 'all') {
        $stmt = $pdo->query("SELECT COUNT(*) AS total_products FROM products");
        $total_products = $stmt->fetch()['total_products'];
    } else {
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT oi.product_id) AS total_products 
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            $items_date_clause
        ");
        $total_products = $stmt->fetch()['total_products'];
    }

    // 2. Total Orders
    $stmt = $pdo->query("SELECT COUNT(*) AS total_orders FROM orders $orders_date_clause");
    $total_orders = $stmt->fetch()['total_orders'];

    // 3. Total Revenue (Sales Sum)
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS total_revenue FROM orders $orders_date_clause");
    $total_revenue = (float)$stmt->fetch()['total_revenue'];

    // 4. Total Profit (Formula: SUM(subtotal - quantity * cost_price))
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(oi.subtotal - (oi.quantity * p.cost_price)), 0) AS total_profit 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.product_id
        JOIN orders o ON oi.order_id = o.order_id
        $items_date_clause
    ");
    $total_profit = (float)$stmt->fetch()['total_profit'];

    // Fetch Low Stock products (stock_quantity < 5)
    $stmt = $pdo->query("SELECT * FROM products WHERE stock_quantity < 5 ORDER BY stock_quantity ASC");
    $low_stock_items = $stmt->fetchAll();

    // Fetch Recent Orders (Limit 15 for scrolling list)
    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $stmt = $pdo->prepare("
            SELECT o.order_id, o.order_date, o.total_amount, o.status, o.dining_type, c.name AS customer_name, c.phone_number, t.table_number 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.customer_id 
            LEFT JOIN tables t ON o.table_id = t.table_id
            WHERE c.name LIKE ? 
               OR c.phone_number LIKE ? 
               OR o.order_id = ? 
               OR o.dining_type LIKE ? 
               OR o.status LIKE ?
            ORDER BY o.order_date DESC 
            LIMIT 15
        ");
        $stmt->execute(["%$search%", "%$search%", (int)$search, "%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("
            SELECT o.order_id, o.order_date, o.total_amount, o.status, o.dining_type, c.name AS customer_name, c.phone_number, t.table_number 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.customer_id 
            LEFT JOIN tables t ON o.table_id = t.table_id
            ORDER BY o.order_date DESC 
            LIMIT 15
        ");
    }
    $recent_orders = $stmt->fetchAll();

    // Fetch all tables for the monitor widget
    $stmt = $pdo->query("SELECT * FROM tables ORDER BY CAST(SUBSTRING(table_number, 7) AS UNSIGNED) ASC");
    $all_tables = $stmt->fetchAll();

    // Fetch active orders (Pending or Paid) grouped by table
    $stmt = $pdo->query("
        SELECT o.order_id, o.table_id, o.status, o.total_amount, c.name AS customer_name
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        WHERE o.dining_type = 'Dine-In' AND o.status != 'Completed' AND o.payment_status != 'Failed'
        ORDER BY o.order_date DESC
    ");
    $active_orders_raw = $stmt->fetchAll();

    $active_orders_by_table = [];
    foreach ($active_orders_raw as $ord) {
        $active_orders_by_table[$ord['table_id']][] = $ord;
    }

    // Fetch ordered items for these active orders
    $active_order_items = [];
    if (!empty($active_orders_raw)) {
        $active_order_ids = array_column($active_orders_raw, 'order_id');
        $in_clause = implode(',', array_map('intval', $active_order_ids));
        $stmt = $pdo->query("
            SELECT oi.order_id, oi.quantity, p.product_name, p.category
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id IN ($in_clause)
        ");
        $items_raw = $stmt->fetchAll();
        foreach ($items_raw as $item) {
            $active_order_items[$item['order_id']][] = $item;
        }
    }

    // Fetch trends based on active filter
    $sales_trend = [];
    $profit_trend = [];
    $trend_labels = [];
    $chart_title = 'Weekly Business Performance';
    $chart_subtitle = 'Revenue & Net Profit Trend (Last 7 Days)';
    
    if ($filter === 'daily') {
        $chart_title = 'Daily Business Performance';
        $chart_subtitle = 'Revenue & Net Profit Trend (Hourly - Today)';
        // Hourly trend for today
        for ($i = 0; $i < 24; $i++) {
            $label = sprintf("%02d:00", $i);
            $trend_labels[] = $label;
            $sales_trend[$i] = 0.0;
            $profit_trend[$i] = 0.0;
        }
        
        // Query revenue by hour
        $stmt = $pdo->query("
            SELECT HOUR(order_date) AS o_hour, SUM(total_amount) AS hourly_revenue 
            FROM orders 
            WHERE order_date >= CURDATE()
            GROUP BY HOUR(order_date)
        ");
        $sales_raw = $stmt->fetchAll();
        foreach ($sales_raw as $s) {
            $sales_trend[(int)$s['o_hour']] = (float)$s['hourly_revenue'];
        }
        
        // Query profit by hour
        $stmt = $pdo->query("
            SELECT HOUR(o.order_date) AS o_hour, SUM(oi.quantity * (p.price - p.cost_price)) AS hourly_profit 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            JOIN orders o ON oi.order_id = o.order_id
            WHERE o.order_date >= CURDATE()
            GROUP BY HOUR(o.order_date)
        ");
        $profit_raw = $stmt->fetchAll();
        foreach ($profit_raw as $p) {
            $profit_trend[(int)$p['o_hour']] = (float)$p['hourly_profit'];
        }
    } elseif ($filter === 'weekly') {
        $chart_title = 'Weekly Business Performance';
        $chart_subtitle = 'Revenue & Net Profit Trend (Last 7 Days)';
        // Daily trend for last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date_str = date('Y-m-d', strtotime("-$i days"));
            $display_str = date('d M', strtotime("-$i days"));
            $trend_labels[] = $display_str;
            $sales_trend[$date_str] = 0.0;
            $profit_trend[$date_str] = 0.0;
        }
        
        $stmt = $pdo->query("
            SELECT DATE(order_date) AS o_date, SUM(total_amount) AS daily_revenue 
            FROM orders 
            WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(order_date)
        ");
        $sales_raw = $stmt->fetchAll();
        foreach ($sales_raw as $s) {
            if (isset($sales_trend[$s['o_date']])) {
                $sales_trend[$s['o_date']] = (float)$s['daily_revenue'];
            }
        }
        
        $stmt = $pdo->query("
            SELECT DATE(o.order_date) AS o_date, SUM(oi.quantity * (p.price - p.cost_price)) AS daily_profit 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            JOIN orders o ON oi.order_id = o.order_id
            WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(o.order_date)
        ");
        $profit_raw = $stmt->fetchAll();
        foreach ($profit_raw as $p) {
            if (isset($profit_trend[$p['o_date']])) {
                $profit_trend[$p['o_date']] = (float)$p['daily_profit'];
            }
        }
    } elseif ($filter === 'monthly') {
        $chart_title = 'Monthly Business Performance';
        $chart_subtitle = 'Revenue & Net Profit Trend (Last 30 Days)';
        // Daily trend for last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date_str = date('Y-m-d', strtotime("-$i days"));
            $display_str = date('d M', strtotime("-$i days"));
            $trend_labels[] = $display_str;
            $sales_trend[$date_str] = 0.0;
            $profit_trend[$date_str] = 0.0;
        }
        
        $stmt = $pdo->query("
            SELECT DATE(order_date) AS o_date, SUM(total_amount) AS daily_revenue 
            FROM orders 
            WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            GROUP BY DATE(order_date)
        ");
        $sales_raw = $stmt->fetchAll();
        foreach ($sales_raw as $s) {
            if (isset($sales_trend[$s['o_date']])) {
                $sales_trend[$s['o_date']] = (float)$s['daily_revenue'];
            }
        }
        
        $stmt = $pdo->query("
            SELECT DATE(o.order_date) AS o_date, SUM(oi.quantity * (p.price - p.cost_price)) AS daily_profit 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            JOIN orders o ON oi.order_id = o.order_id
            WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            GROUP BY DATE(o.order_date)
        ");
        $profit_raw = $stmt->fetchAll();
        foreach ($profit_raw as $p) {
            if (isset($profit_trend[$p['o_date']])) {
                $profit_trend[$p['o_date']] = (float)$p['daily_profit'];
            }
        }
    } else {
        $chart_title = 'All-Time Business Performance';
        $chart_subtitle = 'Revenue & Net Profit Trend (Monthly)';
        // Monthly trend for all time (last 12 months)
        for ($i = 11; $i >= 0; $i--) {
            $month_str = date('Y-m', strtotime("-$i months"));
            $display_str = date('M Y', strtotime("-$i months"));
            $trend_labels[] = $display_str;
            $sales_trend[$month_str] = 0.0;
            $profit_trend[$month_str] = 0.0;
        }
        
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(order_date, '%Y-%m') AS o_month, SUM(total_amount) AS monthly_revenue 
            FROM orders 
            GROUP BY DATE_FORMAT(order_date, '%Y-%m')
        ");
        $sales_raw = $stmt->fetchAll();
        foreach ($sales_raw as $s) {
            if (isset($sales_trend[$s['o_month']])) {
                $sales_trend[$s['o_month']] = (float)$s['monthly_revenue'];
            }
        }
        
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(o.order_date, '%Y-%m') AS o_month, SUM(oi.quantity * (p.price - p.cost_price)) AS monthly_profit 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            JOIN orders o ON oi.order_id = o.order_id
            GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
        ");
        $profit_raw = $stmt->fetchAll();
        foreach ($profit_raw as $p) {
            if (isset($profit_trend[$p['o_month']])) {
                $profit_trend[$p['o_month']] = (float)$p['monthly_profit'];
            }
        }
    }

} catch (PDOException $e) {
    die("Dashboard Error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.active-orders-container > div:last-child {
    border-bottom: 0 !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}
.dashboard-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.08)!important;
}
</style>

<main class="container my-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="display-6 font-heading text-bakery-brown mb-1">Welcome Back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</h2>
            <p class="text-muted mb-0">Here is the snapshot of BakerEase operations.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="btn-group shadow-sm rounded-pill overflow-hidden bg-white p-1" role="group" aria-label="Dashboard Date Filter">
                <a href="dashboard.php?filter=all" class="btn btn-sm px-3 py-2 rounded-pill fw-semibold border-0 <?php echo $filter === 'all' ? 'btn-bakery text-white' : 'btn-light text-secondary'; ?>">All-Time</a>
                <a href="dashboard.php?filter=daily" class="btn btn-sm px-3 py-2 rounded-pill fw-semibold border-0 <?php echo $filter === 'daily' ? 'btn-bakery text-white' : 'btn-light text-secondary'; ?>">Daily</a>
                <a href="dashboard.php?filter=weekly" class="btn btn-sm px-3 py-2 rounded-pill fw-semibold border-0 <?php echo $filter === 'weekly' ? 'btn-bakery text-white' : 'btn-light text-secondary'; ?>">Weekly</a>
                <a href="dashboard.php?filter=monthly" class="btn btn-sm px-3 py-2 rounded-pill fw-semibold border-0 <?php echo $filter === 'monthly' ? 'btn-bakery text-white' : 'btn-light text-secondary'; ?>">Monthly</a>
            </div>
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

    <!-- Metric Cards -->
    <div class="row g-4 mb-5">
        <!-- Products Counter -->
        <div class="col-sm-6 col-lg-3">
            <div class="dashboard-card card border-0 p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small d-block mb-1 text-nowrap">
                            <?php echo $filter === 'all' ? 'Total Products' : 'Products Sold'; ?>
                        </span>
                        <h3 class="fw-bold fs-2 text-dark mb-0"><?php echo $total_products; ?></h3>
                    </div>
                    <div class="card-icon bg-info bg-opacity-10 text-info px-3 py-2 rounded-3">
                        <i class="fa-solid fa-cake-candles"></i>
                    </div>
                </div>
                <div class="mt-auto pt-3">
                    <a href="products.php" class="text-info small text-decoration-none"><i class="fa-solid fa-arrow-right me-1"></i> Manage Products</a>
                </div>
            </div>
        </div>

        <!-- Orders Counter -->
        <div class="col-sm-6 col-lg-3">
            <div class="dashboard-card card border-0 p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small d-block mb-1 text-nowrap">Total Orders</span>
                        <h3 class="fw-bold fs-2 text-dark mb-0"><?php echo $total_orders; ?></h3>
                    </div>
                    <div class="card-icon bg-success bg-opacity-10 text-success px-3 py-2 rounded-3">
                        <i class="fa-solid fa-cart-flatbed-suitcase"></i>
                    </div>
                </div>
                <div class="mt-auto pt-3">
                    <a href="orders.php" class="text-success small text-decoration-none"><i class="fa-solid fa-arrow-right me-1"></i> View All Orders</a>
                </div>
            </div>
        </div>

        <!-- Revenue Summary -->
        <div class="col-sm-6 col-lg-3">
            <div class="dashboard-card card border-0 p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small d-block mb-1 text-nowrap">Total Revenue</span>
                        <h3 class="fw-bold fs-2 text-dark mb-0">RM <?php echo number_format($total_revenue, 2); ?></h3>
                    </div>
                    <div class="card-icon bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-3">
                        <i class="fa-solid fa-coins"></i>
                    </div>
                </div>
                <div class="mt-auto pt-3">
                    <a href="reports.php" class="text-primary small text-decoration-none"><i class="fa-solid fa-arrow-right me-1"></i> View P&L Sheet</a>
                </div>
            </div>
        </div>

        <!-- Profit Summary -->
        <div class="col-sm-6 col-lg-3">
            <div class="dashboard-card card border-0 p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small d-block mb-1 text-nowrap">Total Profit</span>
                        <h3 class="fw-bold fs-2 text-dark mb-0">RM <?php echo number_format($total_profit, 2); ?></h3>
                    </div>
                    <div class="card-icon bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-3">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                </div>
                <div class="mt-auto pt-3">
                    <a href="reports.php" class="text-warning small text-decoration-none"><i class="fa-solid fa-arrow-right me-1"></i> Profit & Loss Details</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-time Tables Monitor Widget -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 rounded-4 shadow-sm bg-white p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <h3 class="h5 font-heading text-bakery-brown mb-0 d-flex align-items-center">
                        <i class="fa-solid fa-chair text-primary me-2"></i>
                        Real-time Tables Monitor
                    </h3>
                    <div class="d-flex gap-2 align-items-center">
                        <button type="button" class="btn btn-bakery btn-sm px-3 rounded-pill text-white fw-semibold" data-bs-toggle="modal" data-bs-target="#addTableModal">
                            <i class="fa-solid fa-plus me-1"></i> Add Table
                        </button>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-1.5 small">
                            <i class="fa-solid fa-arrows-rotate fa-spin me-1"></i> Live Update
                        </span>
                    </div>
                </div>

                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-4">
                    <?php foreach ($all_tables as $table): ?>
                        <?php 
                            $tid = $table['table_id'];
                            $has_orders = isset($active_orders_by_table[$tid]) && !empty($active_orders_by_table[$tid]);
                            $table_orders = $has_orders ? $active_orders_by_table[$tid] : [];
                            $card_border = $has_orders ? 'border: 1px solid rgba(220, 53, 69, 0.2);' : 'border: 1px solid rgba(13, 110, 253, 0.1);';
                            $card_bg = $has_orders ? 'background-color: #fdf8f8;' : 'background-color: #fafbfc;';
                        ?>
                        <div class="col">
                            <div class="card h-100 rounded-3 shadow-sm" style="<?php echo $card_border . ' ' . $card_bg; ?>">
                                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 pb-1 px-3">
                                    <span class="fw-bold text-dark font-heading fs-5"><?php echo htmlspecialchars($table['table_number']); ?></span>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <?php if ($has_orders): ?>
                                            <span class="badge bg-danger rounded-pill px-2.5 py-1 text-white small">Occupied</span>
                                        <?php else: ?>
                                            <form action="dashboard.php" method="POST" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($table['table_number']); ?>?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_table">
                                                <input type="hidden" name="table_id" value="<?php echo $table['table_id']; ?>">
                                                <?php if (isset($_GET['filter'])): ?>
                                                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($_GET['filter']); ?>">
                                                <?php endif; ?>
                                                <button type="submit" class="btn btn-link text-danger p-0 border-0 me-1" style="font-size: 0.85rem;" title="Delete Table">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5 py-1 small">Vacant</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body px-3 py-2 d-flex flex-column justify-content-between">
                                    <?php if ($has_orders): ?>
                                        <div class="active-orders-container">
                                            <?php foreach ($table_orders as $ord): ?>
                                                <?php $oid = $ord['order_id']; ?>
                                                <div class="border-bottom border-light pb-2 mb-2">
                                                    <div class="d-flex justify-content-between align-items-start small mb-1">
                                                        <a href="orders.php?order_id=<?php echo $oid; ?>" class="fw-bold text-decoration-none text-bakery-brown">#<?php echo $oid; ?></a>
                                                        <?php 
                                                            $status = $ord['status'];
                                                            if ($status === 'Paid') {
                                                                echo '<span class="badge bg-primary rounded-pill px-1.5 py-0.5" style="font-size: 0.65rem;">Paid</span>';
                                                            } elseif ($status === 'Pending Payment') {
                                                                echo '<span class="badge bg-warning text-dark rounded-pill px-1.5 py-0.5" style="font-size: 0.65rem;">Pending Payment</span>';
                                                            } else {
                                                                echo '<span class="badge bg-secondary rounded-pill px-1.5 py-0.5" style="font-size: 0.65rem;">Pending</span>';
                                                            }
                                                        ?>
                                                    </div>
                                                    <div class="small text-secondary fw-semibold mb-1"><?php echo htmlspecialchars($ord['customer_name']); ?></div>
                                                    
                                                    <!-- Items List -->
                                                    <ul class="list-unstyled mb-0 ps-0 text-muted" style="font-size: 0.8rem; line-height: 1.3;">
                                                        <?php if (isset($active_order_items[$oid]) && !empty($active_order_items[$oid])): ?>
                                                            <?php foreach ($active_order_items[$oid] as $it): ?>
                                                                <li class="d-flex justify-content-between">
                                                                    <span><?php echo htmlspecialchars($it['product_name']); ?></span>
                                                                    <span class="fw-bold ms-1">x<?php echo $it['quantity']; ?></span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </ul>
                                                    <div class="text-end fw-semibold text-danger mt-1" style="font-size: 0.8rem;">
                                                        RM <?php echo number_format($ord['total_amount'], 2); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4 my-auto">
                                            <i class="fa-solid fa-circle-check text-success-subtle fa-2x mb-2"></i>
                                            <p class="text-muted small mb-0">No active orders</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Table Modal -->
    <div class="modal fade" id="addTableModal" tabindex="-1" aria-labelledby="addTableModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title font-heading text-bakery-brown fw-bold" id="addTableModalLabel">Add New Table</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="dashboard.php" method="POST">
                    <input type="hidden" name="action" value="add_table">
                    <?php if (isset($_GET['filter'])): ?>
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($_GET['filter']); ?>">
                    <?php endif; ?>
                    <div class="modal-body py-3">
                        <div class="mb-3">
                            <label for="table_number" class="form-label fw-semibold text-secondary">Table Number / Label</label>
                            <input type="text" class="form-control rounded-3 py-2" id="table_number" name="table_number" placeholder="e.g., Table 13" required maxlength="10">
                            <div class="form-text small text-muted">Enter a unique name or number (maximum 10 characters).</div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-3 py-2 fw-semibold text-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-bakery rounded-pill px-4 py-2 fw-semibold text-white">Save Table</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sales & Net Profit Trends Chart -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 rounded-4 shadow-sm bg-white p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="h5 font-heading text-bakery-brown mb-0 d-flex align-items-center">
                        <i class="fa-solid fa-chart-line text-warning me-2"></i>
                        <?php echo htmlspecialchars($chart_title); ?>
                    </h3>
                    <span class="text-muted small"><?php echo htmlspecialchars($chart_subtitle); ?></span>
                </div>
                <div class="position-relative" style="height: 300px; width: 100%;">
                    <canvas id="revenueTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Low Stock Alerts Widget -->
        <div class="col-lg-4">
            <div class="card border-0 rounded-4 shadow-sm bg-white p-4 h-100">
                <h3 class="h5 font-heading text-bakery-brown mb-4 d-flex align-items-center">
                    <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>
                    Low Stock Alerts
                </h3>

                <?php if (!empty($low_stock_items)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($low_stock_items as $item): ?>
                            <?php $qty = (int)$item['stock_quantity']; ?>
                            <div class="list-group-item px-0 py-3 border-light d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 fw-semibold text-dark"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                    <small class="text-muted">Category: <?php echo htmlspecialchars($item['category']); ?></small>
                                </div>
                                <div>
                                    <?php if ($qty === 0): ?>
                                        <span class="badge bg-danger rounded-pill px-2.5 py-1.5">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1.5"><?php echo $qty; ?> units left</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="inventory.php" class="btn btn-bakery btn-sm w-100 py-2">
                            <i class="fa-solid fa-boxes-stacked me-1"></i> Replenish Stock
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-circle-check text-success fa-3x mb-3"></i>
                        <p class="text-muted mb-0">All items have sufficient stock levels.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Orders Widget -->
        <div class="col-lg-8">
            <div class="card border-0 rounded-4 shadow-sm bg-white p-4 h-100">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-4 gap-3">
                    <h3 class="h5 font-heading text-bakery-brown mb-0 d-flex align-items-center">
                        <i class="fa-solid fa-receipt text-primary me-2"></i>
                        Recent Orders
                    </h3>
                    <form action="dashboard.php" method="GET" class="d-flex gap-2 align-items-center" style="max-width: 320px; width: 100%;">
                        <?php if (isset($_GET['filter'])): ?>
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($_GET['filter']); ?>">
                        <?php endif; ?>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" name="search" class="form-control bg-light border-0" placeholder="Search orders..." value="<?php echo htmlspecialchars($search); ?>">
                            <?php if ($search !== ''): ?>
                                <a href="dashboard.php<?php echo isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''; ?>" class="btn btn-outline-secondary border-0 d-flex align-items-center justify-content-center"><i class="fa-solid fa-xmark"></i></a>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-bakery btn-sm px-3">Search</button>
                    </form>
                </div>

                <div class="table-responsive p-0 shadow-none border-0" style="max-height: 380px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Date</th>
                                <th scope="col">Dining</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_orders)): ?>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['order_id']; ?></td>
                                        <td>
                                            <span class="fw-semibold text-dark d-block"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['phone_number']); ?></small>
                                        </td>
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
                                        <td class="fw-semibold">RM <?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <?php 
                                                $status = $order['status'];
                                                if ($status === 'Completed') {
                                                    echo '<span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1">Completed</span>';
                                                } elseif ($status === 'Paid') {
                                                    echo '<span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 rounded-pill px-2.5 py-1">Paid</span>';
                                                } elseif ($status === 'Pending Payment') {
                                                    echo '<span class="badge bg-warning-subtle text-warning border border-warning border-opacity-25 rounded-pill px-2.5 py-1">Pending Payment</span>';
                                                } else {
                                                    echo '<span class="badge bg-secondary-subtle text-secondary border border-secondary border-opacity-25 rounded-pill px-2.5 py-1">Pending</span>';
                                                }
                                            ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2 justify-content-end">
                                                <a href="orders.php?highlight=<?php echo $order['order_id']; ?>" class="btn btn-outline-secondary btn-sm rounded-3">
                                                    <i class="fa-solid fa-eye me-1"></i> View
                                                </a>
                                                <form action="dashboard.php" method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to delete Order #<?php echo $order['order_id']; ?>? This action will permanently remove the order and all its items.');">
                                                    <input type="hidden" name="action" value="delete_order">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-3">
                                                        <i class="fa-solid fa-trash-can me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-cart-shopping fa-2x mb-3"></i>
                                        <p class="mb-0"><?php echo ($search !== '') ? 'No orders match your search query.' : 'No orders placed yet.'; ?></p>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueTrendChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_labels); ?>,
            datasets: [
                {
                    label: 'Revenue (RM)',
                    data: <?php echo json_encode(array_values($sales_trend)); ?>,
                    borderColor: '#4E3629',
                    backgroundColor: 'rgba(78, 54, 41, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#4E3629',
                    pointBorderColor: '#fff',
                    pointHoverRadius: 6
                },
                {
                    label: 'Net Profit (RM)',
                    data: <?php echo json_encode(array_values($profit_trend)); ?>,
                    borderColor: '#C5A880',
                    backgroundColor: 'rgba(197, 168, 128, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#C5A880',
                    pointBorderColor: '#fff',
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            family: 'Outfit, Inter, sans-serif',
                            weight: '600'
                        },
                        color: '#4E3629'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(78, 54, 41, 0.95)',
                    titleFont: { family: 'Outfit, Inter, sans-serif' },
                    bodyFont: { family: 'Outfit, Inter, sans-serif' },
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#8D6E63',
                        font: { family: 'Outfit, Inter, sans-serif' }
                    }
                },
                y: {
                    grid: { color: 'rgba(78, 54, 41, 0.05)' },
                    ticks: {
                        color: '#8D6E63',
                        font: { family: 'Outfit, Inter, sans-serif' },
                        callback: function(value) { return 'RM ' + value; }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
