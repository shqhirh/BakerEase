<?php
// header.php - Shared Sidebar Navigation & Layout Header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_admin = isset($_SESSION['admin_id']);
$current_page = basename($_SERVER['PHP_SELF']);
$base_path = ($current_page === 'index.php' || $current_page === 'product.php' || $current_page === 'cart.php' || $current_page === 'receipt.php') ? '' : '../';

// Calculate total cart items
$cart_count = 0;
if (!$is_admin && isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        $cart_count += $qty;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BakerEase - Simplifying Bakery Management</title>
    <!-- Bootstrap 5.3 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom Bakery Theme CSS (Formal Sidebar Layout) -->
    <link href="<?php echo $base_path; ?>assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <script>
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }
    </script>

    <!-- Main Layout Wrapper Container -->
    <div class="d-flex flex-column min-vh-100">
        
        <!-- Mobile Top Header Bar (Only visible on screens < 992px) -->
        <div class="mobile-header d-lg-none d-flex justify-content-between align-items-center">
            <a class="mobile-header-brand" href="<?php echo $base_path; ?>index.php">
                <i class="fa-solid fa-bread-slice me-2 text-warning"></i>
                BakerEase
            </a>
            <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
        
        <!-- Flex Container for Sidebar and Content Split -->
        <div class="d-flex flex-grow-1 flex-row">
            
            <!-- Sidebar Navigation Panel (Desktop Fixed / Mobile Offcanvas drawer) -->
            <div class="offcanvas-lg offcanvas-start offcanvas-sidebar d-flex flex-column" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
                
                <!-- Sidebar Branding Header -->
                <div class="sidebar-brand">
                    <a class="text-decoration-none d-flex align-items-center text-white" href="<?php echo $base_path; ?>index.php" style="color: var(--bakery-gold) !important;">
                        <i class="fa-solid fa-bread-slice me-2 text-warning"></i>
                        <span>BakerEase</span>
                    </a>
                    <button type="button" class="btn-close btn-close-white ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
                </div>
                
                <!-- Sidebar Navigation links list -->
                <div class="sidebar-nav-container flex-grow-1">
                    <a class="sidebar-nav-link <?php echo ($current_page === 'index.php' || $current_page === 'product.php') ? 'active' : ''; ?>" href="<?php echo $base_path; ?>index.php">
                        <i class="fa-solid fa-cookie"></i> Home / Catalog
                    </a>
                    
                    <?php if (!$is_admin): ?>
                    <a class="sidebar-nav-link <?php echo ($current_page === 'cart.php') ? 'active' : ''; ?>" href="<?php echo $base_path; ?>cart.php">
                        <i class="fa-solid fa-cart-shopping"></i> Shopping Cart
                        <?php if ($cart_count > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-auto"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Divider line -->
                    <div class="border-top border-white border-opacity-10 my-3"></div>
                    
                    <?php if ($is_admin): ?>
                        <div class="text-white text-opacity-40 px-4 py-2 small fw-bold text-uppercase">Admin Panel</div>
                        
                        <a class="sidebar-nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/dashboard.php">
                            <i class="fa-solid fa-chart-line"></i> Dashboard
                        </a>
                        
                        <a class="sidebar-nav-link <?php echo ($current_page === 'products.php') ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/products.php">
                            <i class="fa-solid fa-cake-candles"></i> Products
                        </a>
                        
                        <a class="sidebar-nav-link <?php echo ($current_page === 'inventory.php') ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/inventory.php">
                            <i class="fa-solid fa-boxes-stacked"></i> Stock Control
                        </a>
                        
                        <a class="sidebar-nav-link <?php echo ($current_page === 'reports.php') ? 'active' : ''; ?>" href="<?php echo $base_path; ?>admin/reports.php">
                            <i class="fa-solid fa-file-invoice-dollar"></i> P&L Report
                        </a>
                        
                        <a class="sidebar-nav-link text-danger mt-3" href="<?php echo $base_path; ?>admin/logout.php">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout (<?php echo htmlspecialchars($_SESSION['admin_username']); ?>)
                        </a>
                    <?php else: ?>
                        <a class="sidebar-nav-link mt-3" href="<?php echo $base_path; ?>admin/login.php">
                            <i class="fa-solid fa-user-lock"></i> Staff Secure Login
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Small branding footer in sidebar -->
                <div class="p-3 text-center text-white-50 border-top border-white border-opacity-10 small bg-black bg-opacity-10">
                    <small>BakerEase v1.2</small>
                </div>
            </div>
            
            <!-- Main Content Container Area -->
            <div class="main-wrapper flex-grow-1">
                
                <!-- Desktop top header with toggle button -->
                <header class="desktop-header d-none d-lg-flex">
                    <div class="d-flex align-items-center">
                        <button id="sidebarToggle" class="btn btn-light border-0 me-3" type="button" aria-label="Toggle Sidebar">
                            <i class="fa-solid fa-bars fs-5"></i>
                        </button>
                        <h4 class="mb-0 fw-bold text-dark d-flex align-items-center">
                            <i class="fa-solid fa-bread-slice me-2 text-warning fs-4"></i>
                            BakerEase
                        </h4>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <?php if (!$is_admin): ?>
                        <a href="<?php echo $base_path; ?>cart.php" class="btn btn-outline-dark border-0 position-relative py-1.5 px-2">
                            <i class="fa-solid fa-cart-shopping fs-5"></i>
                            <?php if ($cart_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                                    <?php echo $cart_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($is_admin): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill">
                                <i class="fa-solid fa-user-shield me-1"></i> Admin Portal
                            </span>
                        <?php endif; ?>
                    </div>
                </header>
