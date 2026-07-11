<?php
// index.php - Dessert Catalog Landing Page
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';

// Fetch all products
try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY product_id DESC");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
    $error_msg = "Failed to load products: " . $e->getMessage();
}

// Function to resolve product image (with curated Unsplash fallbacks)
function get_product_image_url($image) {
    global $base_path;
    $resolved_base = isset($base_path) ? $base_path : '';
    
    // SVG "No Image Available" base64 placeholder matching theme colors
    $placeholder = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><rect width="400" height="300" fill="#EFEBE9"/><g transform="translate(200,135)" text-anchor="middle" font-family="sans-serif"><text font-size="20" font-weight="bold" fill="#4E3629">No Image Available</text><text y="30" font-size="13" fill="#8D6E63">BakerEase Dessert</text></g></svg>');

    if (empty($image)) {
        return $placeholder;
    }

    $root_path = (basename(__DIR__) === 'admin') ? dirname(__DIR__) : __DIR__;
    if (file_exists($root_path . '/assets/images/' . $image)) {
        return $resolved_base . 'assets/images/' . $image;
    }
    
    // Curated high-res Unsplash links based on product filename or category keywords
    if (strpos($image, 'lava') !== false) {
        return 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?auto=format&fit=crop&q=80&w=500';
    }
    if (strpos($image, 'tart') !== false) {
        return 'https://images.unsplash.com/photo-1519869325930-281384150729?auto=format&fit=crop&q=80&w=500';
    }
    if (strpos($image, 'cookie') !== false) {
        return 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?auto=format&fit=crop&q=80&w=500';
    }
    if (strpos($image, 'sourdough') !== false) {
        return 'https://images.unsplash.com/photo-1549931319-a545dcf3bc73?auto=format&fit=crop&q=80&w=500';
    }
    if (strpos($image, 'cheesecake') !== false) {
        return 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?auto=format&fit=crop&q=80&w=500';
    }

    return $placeholder;
}
?>

<!-- Hero Banner -->
<header class="hero-section text-center">
    <div class="container">
        <h1 class="hero-title mb-3">BakerEase</h1>
        <p class="lead text-light mb-4 fs-4">Artisanal Desserts & Baked Delights Fresh Daily</p>
        <a href="#catalog" class="btn btn-bakery btn-lg px-4"><i class="fa-solid fa-cookie-bite me-2"></i>Explore Menu</a>
    </div>
</header>

<main class="container my-5" id="catalog">
    <!-- Search and Filter Bar -->
    <div class="row mb-5 align-items-center">
        <!-- Search Input -->
        <div class="col-md-5 mb-3 mb-md-0">
            <div class="input-group shadow-sm border-0 rounded-3 overflow-hidden">
                <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="productSearch" class="form-control border-0 py-2.5" placeholder="Search desserts by name...">
            </div>
        </div>
        <!-- Category Pill Filters -->
        <div class="col-md-7 text-md-end">
            <button class="btn filter-btn active" data-category="All">All Items</button>
            <button class="btn filter-btn" data-category="Cakes">Cakes</button>
            <button class="btn filter-btn" data-category="Pastries">Pastries</button>
            <button class="btn filter-btn" data-category="Cookies">Cookies</button>
            <button class="btn filter-btn" data-category="Breads">Breads</button>
            <button class="btn filter-btn" data-category="Drinks">Drinks</button>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><strong>Success!</strong> Your order has been placed. Thank you for your purchase!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <!-- Product Grid -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
                <?php 
                    $qty = (int)$product['stock_quantity'];
                    $in_stock = $qty > 0;
                    $low_stock = ($qty > 0 && $qty < 5);
                ?>
                <div class="col product-card-col" 
                     data-name="<?php echo htmlspecialchars($product['product_name']); ?>" 
                     data-category="<?php echo htmlspecialchars($product['category']); ?>">
                    
                    <div class="card-product">
                        <!-- Image & Category Tag -->
                        <div class="card-img-container">
                            <img src="<?php echo get_product_image_url($product['image']); ?>" class="product-img" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                            <span class="category-badge"><?php echo htmlspecialchars($product['category']); ?></span>
                        </div>
                        
                        <!-- Content -->
                        <div class="card-body">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                            <p class="product-description text-truncate"><?php echo htmlspecialchars($product['description']); ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <span class="price-tag">RM <?php echo number_format($product['price'], 2); ?></span>
                                
                                <?php if ($qty === 0): ?>
                                    <span class="badge bg-danger rounded-pill px-2.5 py-1.5"><i class="fa-solid fa-circle-xmark me-1"></i>Out of Stock</span>
                                <?php elseif ($low_stock): ?>
                                    <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1.5"><i class="fa-solid fa-triangle-exclamation me-1"></i>Only <?php echo $qty; ?> left!</span>
                                <?php else: ?>
                                    <span class="badge bg-success rounded-pill px-2.5 py-1.5"><i class="fa-solid fa-circle-check me-1"></i>In Stock</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Order / Detail Link -->
                            <div class="mt-3">
                                <?php if ($in_stock): ?>
                                <?php if ($is_admin): ?>
                                    <a href="admin/products.php?edit_id=<?php echo $product['product_id']; ?>" class="btn btn-bakery w-100 py-2 mb-2">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Product
                                    </a>
                                <?php else: ?>
                                    <form action="cart.php" method="POST" class="mb-2">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="btn btn-bakery w-100 py-2">
                                            <i class="fa-solid fa-cart-plus me-1"></i> Add to Cart
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-bakery-outline w-100 py-2">
                                    <i class="fa-solid fa-eye me-1"></i> View Details
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary w-100 py-2 mb-2" disabled>
                                    <i class="fa-solid fa-ban me-1"></i> Out of Stock
                                </button>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center my-5">
                <i class="fa-solid fa-cookie-bite fa-4x text-muted mb-3"></i>
                <h3 class="text-muted">No products found.</h3>
                <p>Check back later or contact admin to load desserts!</p>
            </div>
        <?php endif; ?>
        
        <!-- Search "No Products Found" Message -->
        <div class="col-12 text-center my-5 d-none" id="noProductsMessage">
            <i class="fa-solid fa-magnifying-glass fa-4x text-muted mb-3"></i>
            <h3 class="text-muted">No desserts match your search.</h3>
            <p>Try searching for cakes, pastries, cookies, breads, or drinks!</p>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
