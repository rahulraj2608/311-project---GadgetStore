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
// VALIDATE POST DATA
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('cart.php');
}

$shipping_address = trim($_POST['shipping_address'] ?? '');
$payment_method   = $_POST['payment_method'] ?? '';
$transaction_id   = trim($_POST['transaction_id'] ?? '');
$total_amount     = (float)($_POST['total_amount'] ?? 0);

if (empty($shipping_address)) {
    redirect('cart.php?error=Shipping address is required');
}
if ($payment_method === 'Bkash' && empty($transaction_id)) {
    redirect('cart.php?error=Bkash transaction ID is required');
}

// ------------------------------
// GET CART ITEMS
// ------------------------------
$stmt = $pdo->prepare("
    SELECT c.cart_id, c.quantity, p.product_id, p.product_name, p.price, p.stock_quantity
    FROM cart c
    JOIN product p ON c.product_id = p.product_id
    WHERE c.customer_id = ?
");
$stmt->execute([$customer_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart_items)) {
    redirect('cart.php?error=Your cart is empty');
}

// ------------------------------
// START TRANSACTION
// ------------------------------
try {
    $pdo->beginTransaction();

    // ------------------------------
    // INSERT INTO orders TABLE
    // ------------------------------
    $stmt = $pdo->prepare("
        INSERT INTO orders (customer_id, order_date, total_amount, order_status, shipping_address, payment_method)
        VALUES (?, NOW(), ?, 'Pending', ?, ?)
    ");
    $stmt->execute([$customer_id, $total_amount, $shipping_address, $payment_method]);
    $order_id = $pdo->lastInsertId();

    // ------------------------------
    // INSERT INTO order_item TABLE & REDUCE STOCK
    // ------------------------------
    $stmt_item = $pdo->prepare("
        INSERT INTO order_item (order_id, product_id, quantity, per_unit_price)
        VALUES (?, ?, ?, ?)
    ");
    $stmt_stock = $pdo->prepare("UPDATE product SET stock_quantity = stock_quantity - ? WHERE product_id = ?");

    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['stock_quantity']) {
            throw new Exception("Product '{$item['product_name']}' is out of stock.");
        }

        $stmt_item->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
        $stmt_stock->execute([$item['quantity'], $item['product_id']]);
    }

    // ------------------------------
    // INSERT INTO payment TABLE
    // ------------------------------
    $status = ($payment_method === 'Cash on Delivery') ? 'Pending' : 'Completed';
    $stmt_payment = $pdo->prepare("
        INSERT INTO payment (order_id, payment_date, payment_method, amount, status, transaction_id)
        VALUES (?, NOW(), ?, ?, ?, ?)
    ");
    $stmt_payment->execute([$order_id, $payment_method, $total_amount, $status, $transaction_id]);

    // ------------------------------
    // CLEAR CART
    // ------------------------------
    $stmt_clear = $pdo->prepare("DELETE FROM cart WHERE customer_id = ?");
    $stmt_clear->execute([$customer_id]);

    $pdo->commit();

    // ------------------------------
    // REDIRECT TO MY ORDERS
    // ------------------------------
    redirect('myorders.php');

} catch (Exception $e) {
    $pdo->rollBack();
    die("Checkout failed: " . $e->getMessage());
}
?>
