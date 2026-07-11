<?php
// cart.php - Customer Shopping Cart & Checkout
require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect admins out of the customer shopping cart page
$is_admin = isset($_SESSION['admin_id']);
if ($is_admin) {
    header("Location: admin/dashboard.php");
    exit;
}

// Initialize cart if empty
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$error = '';
$success = '';

// Helper to resolve product image
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
        return 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?auto=format&fit=crop&q=80&w=150';
    }
    if (strpos($image, 'tart') !== false) {
        return 'https://images.unsplash.com/photo-1519869325930-281384150729?auto=format&fit=crop&q=80&w=150';
    }
    if (strpos($image, 'cookie') !== false) {
        return 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?auto=format&fit=crop&q=80&w=150';
    }
    if (strpos($image, 'sourdough') !== false) {
        return 'https://images.unsplash.com/photo-1549931319-a545dcf3bc73?auto=format&fit=crop&q=80&w=150';
    }
    if (strpos($image, 'cheesecake') !== false) {
        return 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?auto=format&fit=crop&q=80&w=150';
    }
    return $placeholder;
}

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. ADD ITEM TO CART
    if ($action === 'add') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);

        if ($product_id > 0 && $quantity > 0) {
            // Check if product exists and check stock limits
            $stmt = $pdo->prepare("SELECT product_name, stock_quantity FROM products WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $prod = $stmt->fetch();

            if ($prod) {
                $current_in_cart = $_SESSION['cart'][$product_id] ?? 0;
                $new_qty = $current_in_cart + $quantity;
                $stock = (int)$prod['stock_quantity'];

                if ($new_qty > $stock) {
                    $_SESSION['cart'][$product_id] = $stock; // Cap it at maximum stock
                    $error = "Capped {$prod['product_name']} quantity to maximum available stock ({$stock} units).";
                } else {
                    $_SESSION['cart'][$product_id] = $new_qty;
                    $success = "Added {$prod['product_name']} to cart!";
                }
            }
        }
        header("Location: cart.php");
        exit;
    }

    // 2. UPDATE ITEM QUANTITY
    if ($action === 'update') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);

        if ($product_id > 0) {
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$product_id]);
            } else {
                // Check stock limits
                $stmt = $pdo->prepare("SELECT product_name, stock_quantity FROM products WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $prod = $stmt->fetch();

                if ($prod) {
                    $stock = (int)$prod['stock_quantity'];
                    if ($quantity > $stock) {
                        $_SESSION['cart'][$product_id] = $stock;
                        $error = "Capped {$prod['product_name']} quantity to maximum available stock ({$stock} units).";
                    } else {
                        $_SESSION['cart'][$product_id] = $quantity;
                    }
                }
            }
        }
        header("Location: cart.php");
        exit;
    }

    // 3. REMOVE ITEM FROM CART
    if ($action === 'remove') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        if ($product_id > 0) {
            unset($_SESSION['cart'][$product_id]);
        }
        header("Location: cart.php");
        exit;
    }

    // 4. CHECKOUT SUBMISSION
    if ($action === 'checkout') {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $email = trim($_POST['email'] ?? NULL);
        $dining_type = trim($_POST['dining_type'] ?? 'Takeaway');
        $table_id = isset($_POST['table_id']) && $_POST['table_id'] !== '' ? (int)$_POST['table_id'] : null;

        $payment_method = trim($_POST['payment_method'] ?? 'ToyyibPay');
        $allowed_methods = ['ToyyibPay', 'Card', 'Cash'];
        if (!in_array($payment_method, $allowed_methods)) {
            $payment_method = 'ToyyibPay';
        }

        $card_number = '';
        $card_expiry = '';
        $card_cvv = '';
        if ($payment_method === 'Card') {
            $card_number = str_replace(' ', '', trim($_POST['card_number'] ?? ''));
            $card_expiry = trim($_POST['card_expiry'] ?? '');
            $card_cvv = trim($_POST['card_cvv'] ?? '');
        }

        if (empty($customer_name) || empty($phone_number)) {
            $error = "Please enter your name and phone number to complete checkout.";
        } elseif ($dining_type === 'Dine-In' && empty($table_id)) {
            $error = "Please select a table number for Dine-In orders.";
        } elseif ($payment_method === 'Card' && (empty($card_number) || empty($card_expiry) || empty($card_cvv))) {
            $error = "Please enter all card details (Card Number, Expiry, CVV) for payment.";
        } elseif ($payment_method === 'Card' && !preg_match('/^\d{16}$/', $card_number)) {
            $error = "Invalid card number format. Must be 16 digits.";
        } elseif ($payment_method === 'Card' && !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $card_expiry)) {
            $error = "Invalid expiry date. Use MM/YY format.";
        } elseif ($payment_method === 'Card' && !preg_match('/^\d{3,4}$/', $card_cvv)) {
            $error = "Invalid CVV format. Must be 3 or 4 digits.";
        } elseif (empty($_SESSION['cart'])) {
            $error = "Your shopping cart is empty!";
        } else {
            try {
                // Start Transaction for concurrency stock reduction
                $pdo->beginTransaction();

                $cart_items_detail = [];
                $grand_total = 0.0;

                // Step A: Lock and check stock for all cart items
                foreach ($_SESSION['cart'] as $prod_id => $cart_qty) {
                    $stmt = $pdo->prepare("SELECT product_name, price, cost_price, stock_quantity FROM products WHERE product_id = ? FOR UPDATE");
                    $stmt->execute([$prod_id]);
                    $prod_db = $stmt->fetch();

                    if (!$prod_db) {
                        throw new Exception("One of the products in your cart no longer exists.");
                    }

                    $stock_qty = (int)$prod_db['stock_quantity'];
                    if ($stock_qty < $cart_qty) {
                        // Throw exact error message required
                        throw new Exception("Insufficient stock available for " . $prod_db['product_name']);
                    }

                    $subtotal = (float)$prod_db['price'] * $cart_qty;
                    $item_tax = 0.00;
                    $grand_total += $subtotal;

                    $cart_items_detail[$prod_id] = [
                        'price' => (float)$prod_db['price'],
                        'cost_price' => (float)$prod_db['cost_price'],
                        'quantity' => $cart_qty,
                        'subtotal' => $subtotal,
                        'tax_amount' => $item_tax
                    ];
                }

                $final_total = $grand_total;

                // Step A.2: If Dine-In, check table availability
                if ($dining_type === 'Dine-In') {
                    // Check if table exists
                    $stmt = $pdo->prepare("SELECT table_number FROM tables WHERE table_id = ?");
                    $stmt->execute([$table_id]);
                    $table_db = $stmt->fetch();
                    if (!$table_db) {
                        throw new Exception("The selected table does not exist.");
                    }

                    // Check if there is an active order for this table
                    $stmt = $pdo->prepare("
                        SELECT order_id 
                        FROM orders 
                        WHERE table_id = ? 
                          AND dining_type = 'Dine-In' 
                          AND status != 'Completed' 
                          AND payment_status != 'Failed'
                        FOR UPDATE
                    ");
                    $stmt->execute([$table_id]);
                    if ($stmt->fetch()) {
                        throw new Exception("Sorry, " . htmlspecialchars($table_db['table_number']) . " is currently occupied. Please choose another table.");
                    }
                }

                // Step B: Find or Create Customer
                $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE phone_number = ? LIMIT 1");
                $stmt->execute([$phone_number]);
                $existing_cust = $stmt->fetch();

                if ($existing_cust) {
                    $customer_id = $existing_cust['customer_id'];
                    $stmt = $pdo->prepare("UPDATE customers SET name = ?, email = ? WHERE customer_id = ?");
                    $stmt->execute([$customer_name, $email, $customer_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO customers (name, phone_number, email) VALUES (?, ?, ?)");
                    $stmt->execute([$customer_name, $phone_number, $email]);
                    $customer_id = $pdo->lastInsertId();
                }

                // Resolve Order & Payment status
                $status = 'Pending';
                $payment_status = 'Pending';

                if ($payment_method === 'Cash') {
                    $status = 'Pending Payment';
                    $payment_status = 'Pending';
                } elseif ($payment_method === 'Card') {
                    $status = 'Paid';
                    $payment_status = 'Paid';
                }

                // Step C: Insert Order
                $stmt = $pdo->prepare("INSERT INTO orders (customer_id, total_amount, status, dining_type, table_id, payment_method, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$customer_id, $final_total, $status, $dining_type, $table_id, $payment_method, $payment_status]);
                $order_id = $pdo->lastInsertId();

                // Step D: Insert Order Items and Reduce Stock
                foreach ($cart_items_detail as $prod_id => $item) {
                    // Insert Order Item (including tax_amount)
                    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, subtotal, tax_amount) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$order_id, $prod_id, $item['quantity'], $item['subtotal'], $item['tax_amount']]);

                    // Deduct Stock
                    $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
                    $stmt->execute([$item['quantity'], $prod_id]);
                }

                // Commit Transaction
                $pdo->commit();

                // Handle payment processing redirects
                if ($payment_method === 'ToyyibPay') {
                    try {
                        $url = TOYYIBPAY_API_URL . 'createBill';
                        $bill_desc = 'Bakery Order for ' . $customer_name;
                        $bill_amount = (int)($final_total * 100);

                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                        $host = $_SERVER['HTTP_HOST'];
                        $uri_parts = explode('/', $_SERVER['REQUEST_URI']);
                        array_pop($uri_parts); // Remove cart.php
                        $base_url = $protocol . $host . implode('/', $uri_parts) . '/';

                        $payment_data = [
                            'userSecretKey' => TOYYIBPAY_SECRET_KEY,
                            'categoryCode' => TOYYIBPAY_CATEGORY_CODE,
                            'billName' => 'Order #' . $order_id,
                            'billDescription' => $bill_desc,
                            'billPriceSetting' => 1,
                            'billPayorInfo' => 1,
                            'billAmount' => $bill_amount,
                            'billReturnUrl' => $base_url . 'toyyibpay_return.php',
                            'billCallbackUrl' => $base_url . 'toyyibpay_callback.php',
                            'billExternalReferenceNo' => (string)$order_id,
                            'billTo' => $customer_name,
                            'billEmail' => !empty($email) ? $email : 'no-reply@bakerease.com',
                            'billPhone' => $phone_number,
                            'billSplitPayment' => 0,
                            'billSplitPaymentArgs' => '',
                            'billPaymentChannel' => '0',
                            'billContentEmail' => 'Thank you for your order! Your payment for Order #' . $order_id . ' is successful.',
                            'billChargeToCustomer' => 1
                        ];

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payment_data));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                        $response = curl_exec($ch);
                        if (curl_errno($ch)) {
                            throw new Exception("ToyyibPay Connection Error: " . curl_error($ch));
                        }
                        curl_close($ch);

                        $res_arr = json_decode($response, true);
                        if (isset($res_arr[0]['BillCode'])) {
                            $bill_code = $res_arr[0]['BillCode'];

                            // Save BillCode
                            $stmt = $pdo->prepare("UPDATE orders SET toyyibpay_billcode = ? WHERE order_id = ?");
                            $stmt->execute([$bill_code, $order_id]);

                            // Whitelist and clear cart
                            if (!isset($_SESSION['viewable_orders'])) {
                                $_SESSION['viewable_orders'] = [];
                            }
                            $_SESSION['viewable_orders'][] = (int)$order_id;
                            unset($_SESSION['cart']);

                            // Redirect to ToyyibPay
                            header("Location: " . TOYYIBPAY_PAY_URL . $bill_code);
                            exit;
                        } else {
                            throw new Exception("ToyyibPay API Error: " . ($response ?: 'No response from payment gateway'));
                        }
                    } catch (Exception $payEx) {
                        // Display error but let them know the order was recorded
                        $error = "Payment Initialization Failed: " . $payEx->getMessage();
                    }
                } else {
                    // Store order ID in session viewable array for receipt access control
                    if (!isset($_SESSION['viewable_orders'])) {
                        $_SESSION['viewable_orders'] = [];
                    }
                    $_SESSION['viewable_orders'][] = (int)$order_id;

                    // Clear the cart
                    unset($_SESSION['cart']);

                    // Redirect to customer receipt view
                    header("Location: receipt.php?order_id=" . $order_id);
                    exit;
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (strpos($e->getMessage(), 'Insufficient stock available') !== false) {
                    $error = "Insufficient stock available";
                } else {
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// Fetch details for display
$cart_products = [];
$grand_total = 0.0;
$tables_list = [];

try {
    $tables_list = $pdo->query("
        SELECT t.table_id, t.table_number,
               (CASE WHEN COUNT(o.order_id) > 0 THEN 1 ELSE 0 END) AS is_occupied
        FROM tables t
        LEFT JOIN orders o ON t.table_id = o.table_id 
           AND o.dining_type = 'Dine-In' 
           AND o.status != 'Completed'
           AND o.payment_status != 'Failed'
        GROUP BY t.table_id, t.table_number
        ORDER BY CAST(SUBSTRING(t.table_number, 7) AS UNSIGNED) ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to load tables: " . $e->getMessage();
}
if (!empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id IN ($placeholders)");
        $stmt->execute($product_ids);
        $db_prods = $stmt->fetchAll();

        foreach ($db_prods as $prod) {
            $qty = $_SESSION['cart'][$prod['product_id']];
            $sub = (float)$prod['price'] * $qty;
            $grand_total += $sub;

            $cart_products[] = array_merge($prod, [
                'cart_qty' => $qty,
                'subtotal' => $sub
            ]);
        }
    } catch (PDOException $e) {
        $error = "Failed to load cart items: " . $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<main class="container my-5">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="display-6 font-heading text-bakery-brown mb-1"><i class="fa-solid fa-cart-shopping me-2"></i>Your Shopping Cart</h2>
            <p class="text-muted mb-0">Review items in your cart and fill in checkout details to place your order.</p>
        </div>
    </div>

    <!-- Notifications -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><strong>Checkout Error: </strong> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($cart_products)): ?>
        <div class="row g-4">
            <!-- Left Pane: Cart Items list -->
            <div class="col-lg-8">
                <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden p-3">
                    <div class="table-responsive p-0 border-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col" class="ps-3">Item</th>
                                    <th scope="col" class="text-center" style="width: 150px;">Quantity</th>
                                    <th scope="col">Price</th>
                                    <th scope="col">Subtotal</th>
                                    <th scope="col" class="text-end pe-3">Remove</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_products as $prod): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo get_product_image_url($prod['image']); ?>" class="rounded-3 object-fit-cover me-3 border border-light" alt="<?php echo htmlspecialchars($prod['product_name']); ?>" style="width: 50px; height: 50px;">
                                                <div>
                                                    <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($prod['product_name']); ?></span>
                                                    <small class="text-secondary">Category: <?php echo htmlspecialchars($prod['category']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <!-- Update Quantity Form -->
                                            <form action="cart.php" method="POST" class="d-flex align-items-center gap-1">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="product_id" value="<?php echo $prod['product_id']; ?>">
                                                <input type="number" name="quantity" class="form-control form-control-sm text-center py-1 fw-bold" min="1" max="<?php echo htmlspecialchars($prod['stock_quantity']); ?>" value="<?php echo $prod['cart_qty']; ?>" style="width: 65px;" required>
                                                <button type="submit" class="btn btn-outline-secondary btn-sm rounded-3 px-2 py-1" title="Update Quantity">
                                                    <i class="fa-solid fa-rotate"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="text-secondary small">RM <?php echo number_format($prod['price'], 2); ?></td>
                                        <td class="fw-semibold text-dark">RM <?php echo number_format($prod['subtotal'], 2); ?></td>
                                        <td class="text-end pe-3">
                                            <!-- Remove Form -->
                                            <form action="cart.php" method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="product_id" value="<?php echo $prod['product_id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-circle px-2 py-1.5" title="Remove item">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Pane: Checkout Summary -->
            <div class="col-lg-4">
                <div class="card border-0 rounded-4 shadow-sm bg-white p-4">
                    <h3 class="h5 font-heading text-bakery-brown mb-4 d-flex align-items-center border-bottom pb-2">
                        <i class="fa-solid fa-file-invoice-dollar me-2 text-warning"></i>
                        Checkout Summary
                    </h3>

                    <!-- Totals Block -->
                    <div class="d-flex justify-content-between mb-2 text-secondary">
                        <span>Distinct Items:</span>
                        <span class="fw-semibold text-dark"><?php echo count($cart_products); ?> items</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 text-secondary pb-3 border-bottom">
                        <span>Subtotal:</span>
                        <span class="fw-semibold text-dark">RM <?php echo number_format($grand_total, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-4 border-bottom pb-3">
                        <span class="fw-bold fs-5 text-dark">Grand Total:</span>
                        <span class="fw-bold fs-4 text-danger">RM <?php echo number_format($grand_total, 2); ?></span>
                    </div>

                    <!-- Checkout form -->
                    <form action="cart.php" method="POST" id="checkoutForm">
                        <input type="hidden" name="action" value="checkout">
                        
                        <div class="mb-3">
                            <label for="custName" class="form-label fw-semibold text-secondary">Your Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-muted"></i></span>
                                <input type="text" name="customer_name" id="custName" class="form-control bg-light border-start-0 py-2.5" placeholder="Enter full name" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="custPhone" class="form-label fw-semibold text-secondary">Phone Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-phone text-muted"></i></span>
                                <input type="tel" name="phone_number" id="custPhone" class="form-control bg-light border-start-0 py-2.5" placeholder="e.g. 012-3456789" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="custEmail" class="form-label fw-semibold text-secondary">Email Address <span class="text-muted">(Optional)</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                                <input type="email" name="email" id="custEmail" class="form-control bg-light border-start-0 py-2.5" placeholder="customer@example.com">
                            </div>
                        </div>

                        <!-- Dining Preference Selection -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary">Dining Option <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="flex-fill p-2.5 border rounded-3 bg-light d-flex align-items-center">
                                    <input class="form-check-input mt-0 me-2" type="radio" name="dining_type" id="diningTakeaway" value="Takeaway" checked onclick="toggleTableSelect(false)">
                                    <label class="form-check-label fw-semibold text-dark w-100 mb-0" for="diningTakeaway" style="cursor: pointer;">
                                        <i class="fa-solid fa-bag-shopping me-1 text-warning"></i> Takeaway
                                    </label>
                                </div>
                                <div class="flex-fill p-2.5 border rounded-3 bg-light d-flex align-items-center">
                                    <input class="form-check-input mt-0 me-2" type="radio" name="dining_type" id="diningDineIn" value="Dine-In" onclick="toggleTableSelect(true)">
                                    <label class="form-check-label fw-semibold text-dark w-100 mb-0" for="diningDineIn" style="cursor: pointer;">
                                        <i class="fa-solid fa-chair me-1 text-primary"></i> Dine-In
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Table Selector (Toggled dynamically by JS) -->
                        <div class="mb-4 d-none" id="tableSelectGroup">
                            <label for="tableId" class="form-label fw-semibold text-secondary">Select Table <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-hashtag text-muted"></i></span>
                                <select name="table_id" id="tableId" class="form-select bg-light border-start-0 py-2.5">
                                    <option value="" disabled selected>-- Choose Table --</option>
                                    <?php foreach ($tables_list as $tbl): ?>
                                        <?php 
                                            $disabled = $tbl['is_occupied'] ? 'disabled' : '';
                                            $label_suffix = $tbl['is_occupied'] ? ' (Occupied)' : ' (Vacant)';
                                        ?>
                                        <option value="<?php echo $tbl['table_id']; ?>" <?php echo $disabled; ?>>
                                            <?php echo htmlspecialchars($tbl['table_number'] . $label_suffix); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Payment Method Preference Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-secondary">Payment Method <span class="text-danger">*</span></label>
                            <div class="d-flex flex-column gap-2">
                                <div class="p-2.5 border rounded-3 bg-light d-flex align-items-center">
                                    <input class="form-check-input mt-0 me-2" type="radio" name="payment_method" id="payToyyibPay" value="ToyyibPay" checked onclick="toggleCardForm(false)">
                                    <label class="form-check-label fw-semibold text-dark mb-0" for="payToyyibPay" style="cursor: pointer;">
                                        <i class="fa-solid fa-globe me-1 text-primary"></i> ToyyibPay (Online Banking)
                                    </label>
                                </div>
                                <div class="p-2.5 border rounded-3 bg-light d-flex align-items-center">
                                    <input class="form-check-input mt-0 me-2" type="radio" name="payment_method" id="payCard" value="Card" onclick="toggleCardForm(true)">
                                    <label class="form-check-label fw-semibold text-dark mb-0" for="payCard" style="cursor: pointer;">
                                        <i class="fa-solid fa-credit-card me-1 text-success"></i> Credit / Debit Card
                                    </label>
                                </div>
                                <div class="p-2.5 border rounded-3 bg-light d-flex align-items-center">
                                    <input class="form-check-input mt-0 me-2" type="radio" name="payment_method" id="payCash" value="Cash" onclick="toggleCardForm(false)">
                                    <label class="form-check-label fw-semibold text-dark mb-0" for="payCash" style="cursor: pointer;">
                                        <i class="fa-solid fa-wallet me-1 text-warning"></i> Cash
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Card Payment Fields (Toggled dynamically by JS) -->
                        <div class="mb-4 d-none p-3 border rounded-3 bg-light shadow-sm" id="cardDetailsGroup">
                            <h6 class="font-heading text-bakery-brown mb-3 border-bottom pb-2"><i class="fa-solid fa-credit-card me-1 text-success"></i> Card Payment Details</h6>
                            <div class="mb-3">
                                <label for="cardNo" class="form-label fw-semibold text-secondary mb-1">Card Number</label>
                                <input type="text" name="card_number" id="cardNo" class="form-control bg-white py-2" placeholder="1234 5678 1234 5678" maxlength="19" oninput="formatCardNumber(this)">
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label for="cardExp" class="form-label fw-semibold text-secondary mb-1">Expiry Date</label>
                                    <input type="text" name="card_expiry" id="cardExp" class="form-control bg-white py-2" placeholder="MM/YY" maxlength="5" oninput="formatCardExpiry(this)">
                                </div>
                                <div class="col-6">
                                    <label for="cardCvv" class="form-label fw-semibold text-secondary mb-1">CVV</label>
                                    <input type="text" name="card_cvv" id="cardCvv" class="form-control bg-white py-2" placeholder="123" maxlength="4">
                                </div>
                            </div>
                        </div>

                        <script>
                            function toggleTableSelect(show) {
                                const group = document.getElementById('tableSelectGroup');
                                const select = document.getElementById('tableId');
                                if (show) {
                                    group.classList.remove('d-none');
                                    select.required = true;
                                } else {
                                    group.classList.add('d-none');
                                    select.required = false;
                                    select.value = '';
                                }
                            }

                            function toggleCardForm(show) {
                                const group = document.getElementById('cardDetailsGroup');
                                const cardNo = document.getElementById('cardNo');
                                const cardExp = document.getElementById('cardExp');
                                const cardCvv = document.getElementById('cardCvv');
                                if (show) {
                                    group.classList.remove('d-none');
                                    cardNo.required = true;
                                    cardExp.required = true;
                                    cardCvv.required = true;
                                } else {
                                    group.classList.add('d-none');
                                    cardNo.required = false;
                                    cardExp.required = false;
                                    cardCvv.required = false;
                                    cardNo.value = '';
                                    cardExp.value = '';
                                    cardCvv.value = '';
                                }
                            }

                            function formatCardNumber(input) {
                                let val = input.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                                let matches = val.match(/\d{4,16}/g);
                                let match = matches && matches[0] || '';
                                let parts = [];

                                for (let i = 0, len = match.length; i < len; i += 4) {
                                    parts.push(match.substring(i, i + 4));
                                }

                                if (parts.length > 0) {
                                    input.value = parts.join(' ');
                                } else {
                                    input.value = val;
                                }
                            }

                            function formatCardExpiry(input) {
                                let val = input.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                                if (val.length >= 2) {
                                    input.value = val.substring(0, 2) + '/' + val.substring(2, 4);
                                } else {
                                    input.value = val;
                                }
                            }
                        </script>

                        <button type="submit" class="btn btn-bakery w-100 py-3 fs-5 shadow-sm">
                            <i class="fa-solid fa-circle-check me-1"></i> Place Order & Pay
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Empty Cart State -->
        <div class="row justify-content-center">
            <div class="col-md-6 text-center py-5 bg-white rounded-4 shadow-sm">
                <i class="fa-solid fa-cart-arrow-down fa-5x text-muted mb-3"></i>
                <h3 class="text-muted">Your Shopping Cart is Empty</h3>
                <p class="text-secondary mb-4">Browse our menu of artisanal desserts and add some sweets to your cart!</p>
                <a href="index.php" class="btn btn-bakery px-4 py-2">
                    <i class="fa-solid fa-cookie-bite me-2"></i> Browse Desserts
                </a>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
