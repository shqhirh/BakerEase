<?php
// products.php - Admin Product CRUD
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify admin authentication
check_auth();

$error = '';
$success = '';

// Helper to sanitize filenames and upload image
function upload_image($file) {
    if (empty($file['name'])) {
        return null;
    }
    
    $target_dir = __DIR__ . '/../assets/images/';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($file_ext, $allowed_exts)) {
        throw new Exception("Invalid image format. Allowed formats: JPG, JPEG, PNG, GIF, WEBP.");
    }
    
    $new_filename = uniqid('prod_', true) . '.' . $file_ext;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $new_filename;
    } else {
        throw new Exception("Failed to upload image.");
    }
}

// 1. Handle POST Actions (Add/Edit/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD PRODUCT
    if ($action === 'add') {
        $product_name = trim($_POST['product_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = (float)($_POST['price'] ?? 0.0);
        $cost_price = (float)($_POST['cost_price'] ?? 0.0);
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (empty($product_name) || empty($category) || $price <= 0 || $cost_price <= 0) {
            $error = "Please fill in all required fields. Prices must be positive.";
        } else {
            try {
                $image_name = null;
                if (!empty($_FILES['image']['name'])) {
                    $image_name = upload_image($_FILES['image']);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO products (product_name, category, price, cost_price, stock_quantity, description, image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$product_name, $category, $price, $cost_price, $stock_quantity, $description, $image_name]);
                
                $success = "Product added successfully!";
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }

    // EDIT PRODUCT
    if ($action === 'edit') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $product_name = trim($_POST['product_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = (float)($_POST['price'] ?? 0.0);
        $cost_price = (float)($_POST['cost_price'] ?? 0.0);
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if ($product_id <= 0 || empty($product_name) || empty($category) || $price <= 0 || $cost_price <= 0) {
            $error = "Please fill in all required fields. Prices must be positive.";
        } else {
            try {
                // Fetch existing product to see if image needs replacement
                $stmt = $pdo->prepare("SELECT image FROM products WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $existing = $stmt->fetch();
                $image_name = $existing['image'];

                if (!empty($_FILES['image']['name'])) {
                    // Upload new image
                    $new_image = upload_image($_FILES['image']);
                    
                    // Delete old image if exists
                    if (!empty($image_name) && file_exists(__DIR__ . '/../assets/images/' . $image_name)) {
                        @unlink(__DIR__ . '/../assets/images/' . $image_name);
                    }
                    $image_name = $new_image;
                }

                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET product_name = ?, category = ?, price = ?, cost_price = ?, stock_quantity = ?, description = ?, image = ? 
                    WHERE product_id = ?
                ");
                $stmt->execute([$product_name, $category, $price, $cost_price, $stock_quantity, $description, $image_name, $product_id]);
                
                $success = "Product updated successfully!";
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }

    // DELETE PRODUCT
    if ($action === 'delete') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        if ($product_id > 0) {
            try {
                // Delete image file first
                $stmt = $pdo->prepare("SELECT image FROM products WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $existing = $stmt->fetch();
                if ($existing && !empty($existing['image'])) {
                    $file_path = __DIR__ . '/../assets/images/' . $existing['image'];
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }

                // Delete db row
                $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $success = "Product deleted successfully!";
            } catch (PDOException $e) {
                $error = "Cannot delete product. It is linked to existing order records.";
            }
        }
    }
}

// 2. Fetch products and single product for Edit Modal trigger
$edit_product = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$edit_id]);
    $edit_product = $stmt->fetch();
}

// Fetch all products
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_name LIKE ? ORDER BY product_id DESC");
    $stmt->execute(["%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY product_id DESC");
}
$products = $stmt->fetchAll();

// Product images resolver helper
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
    if (strpos($image, 'lava') !== false) {
        return 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?auto=format&fit=crop&q=80&w=300';
    }
    if (strpos($image, 'tart') !== false) {
        return 'https://images.unsplash.com/photo-1519869325930-281384150729?auto=format&fit=crop&q=80&w=300';
    }
    if (strpos($image, 'cookie') !== false) {
        return 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?auto=format&fit=crop&q=80&w=300';
    }
    if (strpos($image, 'sourdough') !== false) {
        return 'https://images.unsplash.com/photo-1549931319-a545dcf3bc73?auto=format&fit=crop&q=80&w=300';
    }
    if (strpos($image, 'cheesecake') !== false) {
        return 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?auto=format&fit=crop&q=80&w=300';
    }
    return $placeholder;
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container my-5">
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h2 class="display-6 font-heading text-bakery-brown mb-1">Product Inventory</h2>
            <p class="text-muted mb-0">Create, Read, Update, and Delete dessert items in your catalogue.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <button class="btn btn-bakery py-2 px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fa-solid fa-plus me-1"></i> Add New Product
            </button>
        </div>
    </div>

    <!-- Search Form -->
    <div class="card border-0 rounded-4 shadow-sm bg-white p-3 mb-4">
        <form action="products.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-8 col-sm-7">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control bg-light border-0" placeholder="Search by product name" value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4 col-sm-5 d-flex gap-2">
                <button type="submit" class="btn btn-bakery w-100"><i class="fa-solid fa-magnifying-glass me-1"></i> Search</button>
                <?php if ($search !== ''): ?>
                    <a href="products.php" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Alert Notifications -->
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

    <!-- Products Table -->
    <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden mb-5">
        <div class="table-responsive p-0 border-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="ps-4">Image</th>
                        <th scope="col">Product Name</th>
                        <th scope="col">Category</th>
                        <th scope="col">Cost Price</th>
                        <th scope="col">Selling Price</th>
                        <th scope="col">Stock</th>
                        <th scope="col" class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $prod): ?>
                            <tr>
                                <td class="ps-4">
                                    <img src="<?php echo get_product_image_url($prod['image']); ?>" class="rounded-3 object-fit-cover border border-light" alt="<?php echo htmlspecialchars($prod['product_name']); ?>" style="width: 55px; height: 55px;">
                                </td>
                                <td>
                                    <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($prod['product_name']); ?></span>
                                    <small class="text-secondary text-truncate d-inline-block" style="max-width: 200px;"><?php echo htmlspecialchars($prod['description']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill px-2.5 py-1"><?php echo htmlspecialchars($prod['category']); ?></span>
                                </td>
                                <td class="text-secondary small">RM <?php echo number_format($prod['cost_price'], 2); ?></td>
                                <td class="fw-semibold text-dark">RM <?php echo number_format($prod['price'], 2); ?></td>
                                <td>
                                    <?php 
                                        $qty = (int)$prod['stock_quantity'];
                                        if ($qty === 0) {
                                            echo '<span class="badge bg-danger rounded-pill px-2 py-1">Out of Stock</span>';
                                        } elseif ($qty < 5) {
                                            echo '<span class="badge bg-warning text-dark rounded-pill px-2 py-1">Low ('.$qty.')</span>';
                                        } else {
                                            echo '<span class="badge bg-success rounded-pill px-2 py-1">'.$qty.' units</span>';
                                        }
                                    ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="products.php?edit_id=<?php echo $prod['product_id']; ?>" class="btn btn-outline-primary btn-sm rounded-3 me-1">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </a>
                                    <button class="btn btn-outline-danger btn-sm rounded-3" data-bs-toggle="modal" data-bs-target="#deleteProductModal<?php echo $prod['product_id']; ?>">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>

                            <!-- DELETE CONFIRMATION MODAL -->
                            <div class="modal fade" id="deleteProductModal<?php echo $prod['product_id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content rounded-4 border-0 shadow">
                                        <div class="modal-body p-4 text-center">
                                            <i class="fa-solid fa-circle-exclamation text-danger fa-4x mb-3"></i>
                                            <h3 class="h4 font-heading text-dark mb-2">Delete Product</h3>
                                            <p class="text-muted">Are you sure you want to permanently delete <strong><?php echo htmlspecialchars($prod['product_name']); ?></strong>? This action cannot be undone.</p>
                                            
                                            <form action="products.php" method="POST" class="mt-4 d-flex justify-content-center gap-2">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="product_id" value="<?php echo $prod['product_id']; ?>">
                                                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger px-4">Delete Item</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-cookie-bite fa-3x mb-3"></i>
                                <p class="mb-0"><?php echo ($search !== '') ? 'No products match your search query.' : 'No products available in inventory.'; ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- ADD PRODUCT MODAL -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-light px-4">
                <h5 class="modal-title font-heading text-bakery-brown" id="addProductModalLabel"><i class="fa-solid fa-plus me-1 text-warning"></i> Add New Bakery Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="products.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Product Name -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="product_name" class="form-control" placeholder="e.g. Red Velvet Slice" required>
                        </div>
                        
                        <!-- Category -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="" disabled selected>Select Category</option>
                                <option value="Cakes">Cakes</option>
                                <option value="Pastries">Pastries</option>
                                <option value="Cookies">Cookies</option>
                                <option value="Breads">Breads</option>
                                <option value="Drinks">Drinks</option>
                            </select>
                        </div>

                        <!-- Cost Price (RM) -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary">Cost Price (RM) <span class="text-danger">*</span></label>
                            <input type="number" name="cost_price" class="form-control" step="0.01" min="0.01" placeholder="e.g. 5.50" required>
                        </div>

                        <!-- Selling Price (RM) -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary">Selling Price (RM) <span class="text-danger">*</span></label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0.01" placeholder="e.g. 12.00" required>
                        </div>

                        <!-- Stock Level -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary">Initial Stock Quantity</label>
                            <input type="number" name="stock_quantity" class="form-control" min="0" value="0">
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label fw-semibold text-secondary">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Enter ingredients, allergy details, or highlights..."></textarea>
                        </div>

                        <!-- Image File -->
                        <div class="col-12">
                            <label class="form-label fw-semibold text-secondary">Product Image</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                            <div class="form-text">Allowed files: JPG, PNG, WEBP (Max 2MB).</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-light px-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-bakery"><i class="fa-solid fa-save me-1"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT PRODUCT MODAL (AUTOMATICALLY LOADS IF edit_product EXISTS) -->
<?php if ($edit_product): ?>
    <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-light px-4">
                    <h5 class="modal-title font-heading text-bakery-brown" id="editProductModalLabel"><i class="fa-solid fa-pen-to-square me-1 text-warning"></i> Edit Bakery Product</h5>
                    <button type="button" class="btn-close" onclick="window.location.href='products.php'" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="products.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="product_id" value="<?php echo $edit_product['product_id']; ?>">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Product Name -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary">Product Name <span class="text-danger">*</span></label>
                                <input type="text" name="product_name" class="form-control" value="<?php echo htmlspecialchars($edit_product['product_name']); ?>" required>
                            </div>
                            
                            <!-- Category -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary">Category <span class="text-danger">*</span></label>
                                <select name="category" class="form-select" required>
                                    <option value="Cakes" <?php echo ($edit_product['category'] === 'Cakes') ? 'selected' : ''; ?>>Cakes</option>
                                    <option value="Pastries" <?php echo ($edit_product['category'] === 'Pastries') ? 'selected' : ''; ?>>Pastries</option>
                                    <option value="Cookies" <?php echo ($edit_product['category'] === 'Cookies') ? 'selected' : ''; ?>>Cookies</option>
                                    <option value="Breads" <?php echo ($edit_product['category'] === 'Breads') ? 'selected' : ''; ?>>Breads</option>
                                    <option value="Drinks" <?php echo ($edit_product['category'] === 'Drinks') ? 'selected' : ''; ?>>Drinks</option>
                                </select>
                            </div>

                            <!-- Cost Price (RM) -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary">Cost Price (RM) <span class="text-danger">*</span></label>
                                <input type="number" name="cost_price" class="form-control" step="0.01" min="0.01" value="<?php echo $edit_product['cost_price']; ?>" required>
                            </div>

                            <!-- Selling Price (RM) -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary">Selling Price (RM) <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control" step="0.01" min="0.01" value="<?php echo $edit_product['price']; ?>" required>
                            </div>

                            <!-- Stock Level -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary">Stock Quantity</label>
                                <input type="number" name="stock_quantity" class="form-control" min="0" value="<?php echo $edit_product['stock_quantity']; ?>">
                            </div>

                            <!-- Description -->
                            <div class="col-12">
                                <label class="form-label fw-semibold text-secondary">Description</label>
                                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($edit_product['description']); ?></textarea>
                            </div>

                            <!-- Image File -->
                            <div class="col-12">
                                <label class="form-label fw-semibold text-secondary">Product Image <small class="text-muted">(Leave empty to keep existing)</small></label>
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <img src="<?php echo get_product_image_url($edit_product['image']); ?>" class="rounded-3 border border-light" alt="Current image" style="width: 50px; height: 50px;">
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                </div>
                                <div class="form-text">Allowed files: JPG, PNG, WEBP.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-light px-4">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='products.php'" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-bakery"><i class="fa-solid fa-save me-1"></i> Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Script to trigger edit modal immediately -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
            editModal.show();
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
