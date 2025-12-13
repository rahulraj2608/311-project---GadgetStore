<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// ------------------------------
// CHECK CUSTOMER AUTHENTICATION
// ------------------------------
if (!isLoggedIn() || isAdmin()) {
    redirect('../login.php');
}

$customer_id = $_SESSION['user_id'];

// ------------------------------
// FETCH ORDERS FOR THIS CUSTOMER
// ------------------------------
$stmt_orders = $pdo->prepare("
    SELECT * FROM orders 
    WHERE customer_id = ? 
    ORDER BY order_date DESC
");
$stmt_orders->execute([$customer_id]);
$orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Orders - Gadget Store</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php
require("navbar.php");
?>

<div class="container mt-4">
    <h1 class="mb-4">My Orders</h1>

    <?php if (empty($orders)): ?>
        <div class="alert alert-info">
            You have not placed any orders yet. <a href="../index.php">Shop Now</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between">
                    <span>Order #<?php echo $order['order_id']; ?> - <?php echo date('d M Y', strtotime($order['order_date'])); ?></span>
                    <span class="badge bg-<?php echo $order['order_status'] === 'Pending' ? 'warning' : 'success'; ?>">
                        <?php echo $order['order_status']; ?>
                    </span>
                </div>
                <div class="card-body">
                    <p><strong>Shipping Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                    <p><strong>Payment Method:</strong> <?php echo $order['payment_method']; ?></p>

                    <!-- FETCH PAYMENT INFO -->
                    <?php
                    $stmt_payment = $pdo->prepare("SELECT * FROM payment WHERE order_id = ?");
                    $stmt_payment->execute([$order['order_id']]);
                    $payment = $stmt_payment->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <p><strong>Payment Status:</strong> <?php echo $payment['status'] ?? 'N/A'; ?>
                        <?php if (!empty($payment['transaction_id'])): ?>
                            | Transaction ID: <?php echo $payment['transaction_id']; ?>
                        <?php endif; ?>
                    </p>

                    <!-- FETCH ORDER ITEMS -->
                    <table class="table table-sm table-bordered mt-3">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $stmt_items = $pdo->prepare("
                            SELECT oi.*, p.product_name 
                            FROM order_item oi 
                            JOIN product p ON oi.product_id = p.product_id
                            WHERE oi.order_id = ?
                        ");
                        $stmt_items->execute([$order['order_id']]);
                        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($items as $item):
                            $subtotal = $item['quantity'] * $item['per_unit_price'];
                        ?>
                            <tr>
                                <td><?php echo $item['product_name']; ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>$<?php echo number_format($item['per_unit_price'], 2); ?></td>
                                <td>$<?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="text-end">
                        <strong>Total Amount: $<?php echo number_format($order['total_amount'], 2); ?></strong>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
