<?php
// product.php - Product Detail and Checkout Form
require_once __DIR__ . '/includes/db.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch product details
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
} catch (PDOException $e) {
    $product = null;
    $error_msg = "Database error: " . $e->getMessage();
}

if (!$product) {
    header("Location: index.php");
    exit;
}

// No POST ordering logic here; form submits directly to cart.php

// Function to resolve product image
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

require_once __DIR__ . '/includes/header.php';
?>

<main class="container my-5">
    <!-- Breadcrumb back link -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-bakery-brown text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i> Back to Menu</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['product_name']); ?></li>
        </ol>
    </nav>

    <!-- Error Alert -->
    <?php if (!empty($error_order)): ?>
        <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><strong>Error: </strong> <?php echo htmlspecialchars($error_order); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-5">
        <!-- Product Details Panel -->
        <div class="col-lg-6">
            <div class="card border-0 rounded-4 overflow-hidden shadow-sm h-100 bg-white">
                <div style="height: 380px; overflow: hidden; background-color: var(--bakery-sand);">
                    <img src="<?php echo get_product_image_url($product['image']); ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                </div>
                <div class="card-body p-4">
                    <span class="badge bg-secondary mb-3 rounded-pill px-3 py-1.5"><?php echo htmlspecialchars($product['category']); ?></span>
                    <h2 class="display-6 font-heading text-bakery-brown mb-3"><?php echo htmlspecialchars($product['product_name']); ?></h2>
                    <p class="fs-5 text-bakery-brown fw-bold mb-4">Price: <span class="fs-3 text-danger">RM <?php echo number_format($product['price'], 2); ?></span></p>
                    
                    <h4 class="h5 text-muted mb-2 font-heading">Description</h4>
                    <p class="text-secondary mb-4 fs-6 leading-relaxed"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    
                    <!-- Stock Indicators -->
                    <div class="d-flex align-items-center bg-light p-3 rounded-3 border-0">
                        <i class="fa-solid fa-warehouse text-muted fa-lg me-3"></i>
                        <div>
                            <span class="text-muted d-block small">Available Stock</span>
                            <span class="fw-bold fs-5 <?php echo ((int)$product['stock_quantity'] < 5) ? 'text-warning' : 'text-success'; ?>">
                                <span id="stockQuantityVal"><?php echo htmlspecialchars($product['stock_quantity']); ?></span> units
                            </span>
                            <?php if ((int)$product['stock_quantity'] < 5): ?>
                                <span class="d-block small text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Running low!</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Placement Form Panel / Admin Panel Info -->
        <div class="col-lg-6">
            <?php if ($is_admin): ?>
                <div class="card border-0 rounded-4 shadow-sm bg-white h-100 p-4 d-flex flex-column justify-content-between">
                    <div>
                        <h3 class="font-heading mb-4 text-bakery-brown d-flex align-items-center">
                            <i class="fa-solid fa-user-shield me-2 text-warning"></i>
                            Admin Product Management
                        </h3>
                        <p class="text-muted small mb-4">You are currently logged in as a shop administrator. Customer ordering forms are disabled in staff mode.</p>
                        
                        <div class="mb-3">
                            <span class="text-secondary small d-block">Cost Price (Material Cost):</span>
                            <span class="fw-bold fs-5 text-danger">RM <?php echo number_format($product['cost_price'], 2); ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="text-secondary small d-block">Selling Price (Retail):</span>
                            <span class="fw-bold fs-5 text-primary">RM <?php echo number_format($product['price'], 2); ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="text-secondary small d-block">Estimated Unit Profit Margin:</span>
                            <span class="fw-bold fs-5 text-success">RM <?php echo number_format($product['price'] - $product['cost_price'], 2); ?></span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="admin/products.php?edit_id=<?php echo $product['product_id']; ?>" class="btn btn-bakery w-100 py-3 fs-5 shadow-sm">
                            <i class="fa-solid fa-pen-to-square me-2"></i> Edit Product Details
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 rounded-4 shadow-sm bg-white h-100 p-4">
                    <h3 class="font-heading mb-4 text-bakery-brown d-flex align-items-center">
                        <i class="fa-solid fa-cart-plus me-2 text-warning"></i>
                        Configure Purchase
                    </h3>
                    <p class="text-muted small mb-4">Specify the quantity you want to buy. You can review your items and perform checkout inside your shopping cart page.</p>

                    <form action="cart.php" method="POST" id="orderForm">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

                        <!-- Quantity Input -->
                        <div class="mb-4">
                            <label for="orderQuantity" class="form-label fw-semibold text-secondary">Quantity to Order <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-calculator text-muted"></i></span>
                                <input type="number" name="quantity" id="orderQuantity" class="form-control bg-light border-start-0 py-2.5" min="1" max="<?php echo htmlspecialchars($product['stock_quantity']); ?>" value="1" required>
                            </div>
                            <div class="form-text">Choose how many items you'd like to buy. Cannot exceed available stock.</div>
                        </div>

                        <!-- Submit Add to Cart Button -->
                        <div class="mt-4">
                            <button type="submit" class="btn btn-bakery w-100 py-3 fs-5 shadow-sm">
                                <i class="fa-solid fa-cart-shopping me-2"></i> Add to Shopping Cart
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
