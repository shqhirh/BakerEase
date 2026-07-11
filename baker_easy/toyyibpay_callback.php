<?php
// toyyibpay_callback.php - Background Callback Webhook from ToyyibPay

require_once __DIR__ . '/includes/db.php';

// Accept only POST requests from ToyyibPay
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status_id = trim($_POST['status_id'] ?? '');
    $billcode = trim($_POST['billcode'] ?? '');
    $refno = trim($_POST['refno'] ?? ''); // Order ID we sent
    $transaction_id = trim($_POST['transaction_id'] ?? '');

    if (!empty($billcode)) {
        try {
            // Find order matching the billcode
            $stmt = $pdo->prepare("SELECT order_id FROM orders WHERE toyyibpay_billcode = ? LIMIT 1");
            $stmt->execute([$billcode]);
            $order = $stmt->fetch();

            if ($order) {
                $order_id = (int)$order['order_id'];
                if ($status_id === '1') {
                    // Update status to Paid
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'Paid', payment_status = 'Paid' WHERE order_id = ?");
                    $stmt->execute([$order_id]);
                } elseif ($status_id === '2') {
                    // Update payment status to Pending
                    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'Pending' WHERE order_id = ?");
                    $stmt->execute([$order_id]);
                } else {
                    // Update payment status to Failed
                    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'Failed' WHERE order_id = ?");
                    $stmt->execute([$order_id]);
                }
                echo "OK"; // ToyyibPay expects acknowledgment response
                exit;
            }
        } catch (PDOException $e) {
            // Log database failure
            error_log("ToyyibPay Callback DB Error: " . $e->getMessage());
            http_response_code(500);
            echo "Database error";
            exit;
        }
    }
    http_response_code(400);
    echo "Invalid Parameters";
    exit;
}

http_response_code(405);
echo "Method Not Allowed";
exit;
?>
