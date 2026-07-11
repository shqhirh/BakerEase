<?php
// toyyibpay_return.php - Handling redirect from ToyyibPay

require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$status_id = trim($_GET['status_id'] ?? '');
$billcode = trim($_GET['billcode'] ?? '');
$transaction_id = trim($_GET['transaction_id'] ?? '');

$error = '';
$success_msg = '';
$order_id = 0;

if (empty($billcode)) {
    $error = "Missing payment reference code (billcode).";
} else {
    try {
        // Find order matching the billcode
        $stmt = $pdo->prepare("
            SELECT o.*, c.name AS customer_name, c.email, c.phone_number 
            FROM orders o
            JOIN customers c ON o.customer_id = c.customer_id
            WHERE o.toyyibpay_billcode = ? 
            LIMIT 1
        ");
        $stmt->execute([$billcode]);
        $order = $stmt->fetch();

        if (!$order) {
            $error = "Order matching the payment reference was not found.";
        } else {
            $order_id = (int)$order['order_id'];
            
            // ToyyibPay status_id: 1 = Success, 2 = Pending, 3 = Failed
            if ($status_id === '1') {
                // Update order status to Paid
                $stmt = $pdo->prepare("UPDATE orders SET status = 'Paid', payment_status = 'Paid' WHERE order_id = ?");
                $stmt->execute([$order_id]);

                // Whitelist order ID in session viewable list
                if (!isset($_SESSION['viewable_orders'])) {
                    $_SESSION['viewable_orders'] = [];
                }
                if (!in_array($order_id, $_SESSION['viewable_orders'])) {
                    $_SESSION['viewable_orders'][] = $order_id;
                }

                $success_msg = "Your payment was processed successfully! Thank you for your purchase.";
            } elseif ($status_id === '2') {
                // Update payment status to Pending
                $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'Pending' WHERE order_id = ?");
                $stmt->execute([$order_id]);
                $error = "Your payment is currently pending confirmation from your bank.";
            } else {
                // Update payment status to Failed
                $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'Failed' WHERE order_id = ?");
                $stmt->execute([$order_id]);
                $error = "Payment failed or was cancelled by the user. Please try checking out again.";
            }
        }
    } catch (PDOException $e) {
        $error = "Database error processing payment return: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status</title>
    <!-- Bootstrap 5.3 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom Bakery Theme CSS -->
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        body {
            background-color: var(--bakery-cream);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
            font-family: var(--font-body);
        }
        .receipt-container {
            width: 100%;
            max-width: 520px;
        }
        .card {
            border: none;
            border-radius: 20px !important;
            box-shadow: 0 15px 35px rgba(78, 54, 41, 0.08) !important;
            overflow: hidden;
        }
        .success-icon-wrapper, .error-icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .success-icon-bg {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.8rem;
            box-shadow: 0 8px 24px rgba(46, 204, 113, 0.25);
            animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .error-icon-bg {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.8rem;
            box-shadow: 0 8px 24px rgba(231, 76, 60, 0.25);
            animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .receipt-details {
            background-color: #fdfdfd;
            border: 1px solid rgba(0, 0, 0, 0.04);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }
        .receipt-row:last-child {
            margin-bottom: 0;
        }
        .receipt-divider {
            border-top: 2px dashed rgba(0, 0, 0, 0.06);
            margin: 16px 0;
        }
        .countdown-bar-container {
            width: 100%;
            height: 5px;
            background-color: #f1f0ee;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 10px;
        }
        .countdown-bar {
            width: 100%;
            height: 100%;
            background-color: var(--bakery-gold);
            transition: width 1s linear;
        }
        .btn-premium {
            padding: 12px 24px;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-premium-primary {
            background: linear-gradient(135deg, var(--bakery-gold), var(--bakery-gold-dark));
            color: white !important;
            box-shadow: 0 4px 15px rgba(197, 168, 128, 0.2);
            border: none;
        }
        .btn-premium-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(197, 168, 128, 0.3);
        }
        .btn-premium-secondary {
            background-color: transparent;
            color: var(--bakery-brown-light) !important;
            border: 1.5px solid var(--bakery-sand);
        }
        .btn-premium-secondary:hover {
            background-color: var(--bakery-cream);
            color: var(--bakery-brown) !important;
            border-color: var(--bakery-brown-light);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <?php if (!empty($success_msg)): ?>
        <!-- SUCCESS STATE CARD -->
        <div class="card border-0 p-5 bg-white text-center">
            <div class="success-icon-wrapper mb-4">
                <div class="success-icon-bg">
                    <i class="fa-solid fa-check"></i>
                </div>
            </div>
            <h2 class="display-6 font-heading text-bakery-brown mb-2">Payment Successful!</h2>
            <p class="text-secondary mb-4"><?php echo htmlspecialchars($success_msg); ?></p>
            
            <div class="receipt-details text-start">
                <div class="receipt-row">
                    <span class="text-secondary">Order ID:</span>
                    <strong class="text-dark">#<?php echo $order_id; ?></strong>
                </div>
                <div class="receipt-row">
                    <span class="text-secondary">Bill Code:</span>
                    <strong class="text-dark"><?php echo htmlspecialchars($billcode); ?></strong>
                </div>
                <?php if (!empty($transaction_id)): ?>
                    <div class="receipt-row">
                        <span class="text-secondary">Transaction ID:</span>
                        <strong class="text-dark"><?php echo htmlspecialchars($transaction_id); ?></strong>
                    </div>
                <?php endif; ?>
                <div class="receipt-divider"></div>
                <div class="receipt-row align-items-center">
                    <span class="fw-bold text-dark">Amount Paid:</span>
                    <strong class="text-danger fs-5">RM <?php echo number_format($order['total_amount'], 2); ?></strong>
                </div>
            </div>

            <div class="mb-4">
                <p class="text-muted small mb-0">
                    <i class="fa-solid fa-circle-notch fa-spin me-2"></i> Redirecting you to your receipt page in <span id="countdown" class="fw-bold">5</span> seconds...
                </p>
                <div class="countdown-bar-container">
                    <div class="countdown-bar" id="countdownBar"></div>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                <a href="receipt.php?order_id=<?php echo $order_id; ?>" class="btn-premium btn-premium-primary">
                    <i class="fa-solid fa-receipt"></i> View Receipt
                </a>
                <a href="index.php" class="btn-premium btn-premium-secondary">
                    <i class="fa-solid fa-house"></i> Return Home
                </a>
            </div>
        </div>

        <script>
            let count = 5;
            const countdownEl = document.getElementById('countdown');
            const countdownBar = document.getElementById('countdownBar');
            const timer = setInterval(function() {
                count--;
                if (countdownEl) countdownEl.textContent = count;
                if (countdownBar) {
                    countdownBar.style.width = (count / 5 * 100) + '%';
                }
                if (count <= 0) {
                    clearInterval(timer);
                    window.location.href = "receipt.php?order_id=<?php echo $order_id; ?>";
                }
            }, 1000);
        </script>

    <?php else: ?>
        <!-- ERROR / FAILED STATE CARD -->
        <div class="card border-0 p-5 bg-white text-center">
            <div class="error-icon-wrapper mb-4">
                <div class="error-icon-bg">
                    <i class="fa-solid fa-xmark"></i>
                </div>
            </div>
            <h2 class="display-6 font-heading text-danger mb-2">Payment Failed</h2>
            <p class="text-secondary mb-4"><?php echo htmlspecialchars($error); ?></p>

            <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center mt-2">
                <a href="cart.php" class="btn-premium btn-premium-primary">
                    <i class="fa-solid fa-cart-shopping"></i> Return to Cart
                </a>
                <a href="index.php" class="btn-premium btn-premium-secondary">
                    <i class="fa-solid fa-house"></i> Back to Catalog
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Bootstrap 5 Bundle JS CDN (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
