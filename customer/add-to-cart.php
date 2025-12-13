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
// ONLY ACCEPT POST REQUEST
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php');
}

if (!isset($_POST['product_id'])) {
    redirect('../index.php');
}

$product_id = (int)$_POST['product_id'];

// ------------------------------
// CHECK IF PRODUCT EXISTS
// ------------------------------
$stmt = $pdo->prepare("SELECT product_id, stock_quantity, price FROM product WHERE product_id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    redirect('../index.php?error=Product not found');
}

// ------------------------------
// CHECK STOCK
// ------------------------------
if ($product['stock_quantity'] < 1) {
    redirect('../index.php?error=Out+of+stock');
}

// ------------------------------
// CHECK IF ITEM ALREADY IN CART
// ------------------------------
$stmt = $pdo->prepare("SELECT cart_id, quantity FROM cart WHERE product_id = ? AND customer_id = ?");
$stmt->execute([$product_id, $customer_id]);
$existing = $stmt->fetch();

if ($existing) {

    // Already in cart → Increase quantity
    $newQty = $existing['quantity'] + 1;

    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
    $stmt->execute([$newQty, $existing['cart_id']]);

} else {

    // Add new row
    $stmt = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, 1)");
    $stmt->execute([$customer_id, $product_id]);
}

// ------------------------------
// REDIRECT BACK
// ------------------------------
redirect('cart.php?success=added');

?>
