<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Start the session if not already started (essential for discount persistence)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------
// CHECK CUSTOMER AUTHENTICATION
// ------------------------------
if (!isLoggedIn() || isAdmin()) {
    redirect('../login.php');
}

$customer_id = $_SESSION['user_id'];
$message_type = '';
$message = '';

// ------------------------------
// HANDLE CART UPDATE/REMOVE
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update']) || isset($_POST['remove']))) {
    if (isset($_POST['update'])) {
        foreach ($_POST['quantities'] as $cart_id => $quantity) {
            $quantity = (int)$quantity;
            $cart_id = (int)$cart_id; // Ensure cart_id is also treated as integer
            if ($quantity > 0) {
                // Secure Update Query
                $pdo->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND customer_id = ?")
                    ->execute([$quantity, $cart_id, $customer_id]);
            } else {
                // Secure Delete Query (if quantity is zero or less)
                $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND customer_id = ?")
                    ->execute([$cart_id, $customer_id]);
            }
        }
        $_SESSION['success'] = "Shopping cart updated successfully.";
    } elseif (isset($_POST['remove'])) {
        $cart_id = (int)$_POST['cart_id'];
        // Secure Delete Query
        $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND customer_id = ?")
            ->execute([$cart_id, $customer_id]);
        $_SESSION['success'] = "Item removed from cart.";
    }

    // Unset discount session vars after cart change, forcing re-application
    unset($_SESSION['discount_code']);
    unset($_SESSION['discount_amount']);
    echo "<h4>DEBUG: Cart Item Categories</h4>";
foreach ($cart_items as $item) {
    echo "Product: " . htmlspecialchars($item['product_name']) . " (ID: " . $item['product_id'] . ") | Category ID: **" . ($item['category_id'] ?? 'NULL/MISSING') . "**<br>";
}

    // reload page after update/remove
    redirect('cart.php');
}

// ------------------------------
// GET CART ITEMS (with category_id)
// ------------------------------
$sql = "SELECT c.cart_id, c.quantity, p.*, b.brand_name, pi.image_url, cat.category_id 
        FROM cart c 
        JOIN product p ON c.product_id = p.product_id 
        LEFT JOIN brand b ON p.brand_id = b.brand_id 
        LEFT JOIN product_image pi ON p.product_id = pi.product_id 
        LEFT JOIN category cat ON p.category_id = cat.category_id 
        WHERE c.customer_id = ? 
        GROUP BY c.cart_id, p.product_id"; // Group by cart_id to ensure all distinct cart entries are fetched
$stmt = $pdo->prepare($sql);
$stmt->execute([$customer_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------
// CALCULATE SUBTOTAL (Pre-Discount)
// ------------------------------
$subtotal_pre_discount = 0;
foreach ($cart_items as $item) {
    $subtotal_pre_discount += $item['price'] * $item['quantity'];
}

$subtotal = $subtotal_pre_discount; // Initialize subtotal with pre-discount value

// ------------------------------
// HANDLE DISCOUNT APPLICATION (POST)
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_discount'])) {
    
    $code = isset($_POST['discount_code']) ? sanitize(strtoupper(trim($_POST['discount_code']))) : '';

    if (empty($code)) {
        $_SESSION['error'] = "Please enter a discount code.";
        redirect('cart.php');
    }

    // Fetch discount using PDO
    $dstmt = $pdo->prepare("SELECT * FROM discount WHERE discount_code = ? LIMIT 1");
    $dstmt->execute([$code]);
    $discount = $dstmt->fetch(PDO::FETCH_ASSOC);

    // Reset discount session on new attempt
    unset($_SESSION['discount_code']);
    unset($_SESSION['discount_amount']);

    if ($discount) {
        $today = new DateTime('today');
        $startDate = new DateTime($discount['start_date']);
        $expiryDate = new DateTime($discount['expiry_date']);
        
        // 1. Check Date Validity
        if ($today < $startDate || $today > $expiryDate) {
            $message = "This discount code is expired or not yet active.";
            $message_type = 'error';
        
        // 2. Calculate Discount Amount
        } else {
            $calculated_discount_amount = 0;
            $is_applicable = false;

            foreach ($cart_items as $item) {
                $product_subtotal = $item['price'] * $item['quantity'];
                
                // A. Discount applies to ALL products
                if ($discount['applicable_to'] === 'all') {
                    $is_applicable = true;
                    if ($discount['type'] == "percentage") {
                        $calculated_discount_amount += ($product_subtotal * $discount['value']) / 100;
                    } elseif ($discount['type'] == "fixed_amount") {
                        // For fixed amount, apply the full fixed amount once (or per item, depending on business logic).
                        // Applying it once to the subtotal is standard for 'fixed' total discount.
                        // We break here to avoid applying fixed_amount multiple times in the loop.
                        $calculated_discount_amount = $discount['value'];
                        break; 
                    }
                
                // B. Discount applies to a SPECIFIC CATEGORY
                } elseif ($discount['applicable_to'] === 'category' && (int)$item['category_id'] === (int)$discount['category_id']) {
                    $is_applicable = true;
                    if ($discount['type'] == "percentage") {
                        $calculated_discount_amount += ($product_subtotal * $discount['value']) / 100;
                    } elseif ($discount['type'] == "fixed_amount") {
                        // Fixed amount on a category is tricky. Here, we apply the fixed amount 
                        // only if there is at least one item from that category.
                        $calculated_discount_amount = $discount['value'];
                        break;
                    }
                }
            }
            
            if ($is_applicable && $calculated_discount_amount > 0) {
                
                // Ensure the fixed amount discount doesn't exceed the total pre-discount subtotal
                if ($discount['type'] == "fixed_amount") {
                    $calculated_discount_amount = min($calculated_discount_amount, $subtotal_pre_discount);
                }

                $_SESSION['discount_code'] = $code;
                $_SESSION['discount_amount'] = round($calculated_discount_amount, 2);
                
                $message = "Discount Code **{$code}** applied successfully! Saved $" . number_format($_SESSION['discount_amount'], 2) . ".";
                $message_type = 'success';
            } else {
                $message = "Discount code **{$code}** is not applicable to any items in your cart.";
                $message_type = 'error';
            }
        }

    } else {
        $message = "Invalid discount code.";
        $message_type = 'error';
    }
    
    // Store message in session to show after page reload (to clear POST data)
    $_SESSION[$message_type] = $message;
    redirect('cart.php');
}


// ------------------------------
// APPLY PERSISTENT DISCOUNT & CALCULATE FINAL TOTALS
// ------------------------------
$discount_applied_amount = 0;
if (isset($_SESSION['discount_amount'])) {
    $discount_applied_amount = $_SESSION['discount_amount'];
    // Apply discount to the subtotal
    $subtotal = max(0, $subtotal_pre_discount - $discount_applied_amount);
}

// Final Calculations
$tax = $subtotal * 0.10; // 10%
$shipping = count($cart_items) > 0 ? 5.00 : 0;
$total = $subtotal + $tax + $shipping;

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shopping Cart - Gadget Store</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php
// Assuming navbar.php exists and handles HTML structure
require("navbar.php"); 
// Assuming displaySessionMessages() handles both success and error keys
displaySessionMessages(); 
?>

<div class="container mt-4">
    <h1 class="mb-4">Shopping Cart</h1>

    <?php if (empty($cart_items)): ?>
        <div class="alert alert-info">
            Your cart is empty. <a href="../index.php">Continue shopping</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <form method="POST" action="cart.php">
                    <div class="card mb-4">
                        <div class="card-body">
                            <?php foreach ($cart_items as $item): ?>
                            <div class="row mb-3 pb-3 border-bottom align-items-center">
                                <div class="col-md-2">
                                    <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'https://via.placeholder.com/100'); ?>" 
                                            alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                            class="img-fluid rounded">
                                </div>
                                <div class="col-md-5">
                                    <h5><?php echo htmlspecialchars($item['product_name']); ?></h5>
                                    <p class="text-muted small">Brand: <?php echo htmlspecialchars($item['brand_name']); ?></p>
                                    <p class="text-muted small">Category ID: <?php echo htmlspecialchars($item['category_id']); ?></p>
                                    <p class="text-success">$<?php echo number_format($item['price'], 2); ?> per item</p>
                                </div>
                                <div class="col-md-2">
                                    <label for="qty-<?php echo $item['cart_id']; ?>" class="form-label visually-hidden">Quantity</label>
                                    <input type="number" name="quantities[<?php echo $item['cart_id']; ?>]" 
                                            value="<?php echo $item['quantity']; ?>" 
                                            min="1" max="10" id="qty-<?php echo $item['cart_id']; ?>" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-3 text-end">
                                    <p class="fw-bold mb-1">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                                    <button type="submit" name="remove" value="1" class="btn btn-sm btn-outline-danger">
                                        Remove
                                    </button>
                                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <div class="d-flex justify-content-between pt-3">
                                <a href="../index.php" class="btn btn-outline-secondary">
                                    Continue Shopping
                                </a>
                                <button type="submit" name="update" value="1" class="btn btn-primary">
                                    Update Cart
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="cart.php" class="mb-3">
                            <label for="discount_code" class="form-label fw-bold">Discount Code</label>
                            <div class="input-group">
                                <input type="text" name="discount_code" id="discount_code" class="form-control" placeholder="Enter code" 
                                       value="<?php echo htmlspecialchars($_SESSION['discount_code'] ?? ''); ?>" required>
                                <button type="submit" name="apply_discount" class="btn btn-outline-success">Apply</button>
                            </div>
                            <?php if(isset($_SESSION['discount_code'])): ?>
                                <p class="text-success mt-1 small">Code **<?php echo htmlspecialchars($_SESSION['discount_code']); ?>** Applied.</p>
                            <?php endif; ?>
                        </form>
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal (Items)</span>
                            <span>$<?php echo number_format($subtotal_pre_discount, 2); ?></span>
                        </div>
                        <?php if ($discount_applied_amount > 0): ?>
                            <div class="d-flex justify-content-between mb-2 text-danger fw-bold">
                                <span>Discount (<?php echo htmlspecialchars($_SESSION['discount_code']); ?>)</span>
                                <span>-$<?php echo number_format($discount_applied_amount, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 text-primary fw-bold">
                                <span>New Subtotal</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping</span>
                            <span>$<?php echo number_format($shipping, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (10%)</span>
                            <span>$<?php echo number_format($tax, 2); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total</strong>
                            <strong>$<?php echo number_format($total, 2); ?></strong>
                        </div>

                        <form method="POST" action="checkout.php">
                            <input type="hidden" name="final_total_amount" value="<?php echo number_format($total, 2, '.', ''); ?>">
                            <input type="hidden" name="applied_discount_code" value="<?php echo htmlspecialchars($_SESSION['discount_code'] ?? ''); ?>">

                            <div class="mb-3">
                                <label for="shipping_address" class="form-label fw-bold">Shipping Address</label>
                                <textarea name="shipping_address" id="shipping_address" class="form-control" rows="3" required></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Payment Method</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cod" value="Cash on Delivery" checked>
                                    <label class="form-check-label" for="cod">Cash on Delivery</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="bkash" value="Bkash">
                                    <label class="form-check-label" for="bkash">Bkash</label>
                                </div>
                            </div>

                            <div class="mb-3" id="transaction-field" style="display: none;">
                                <label for="transaction_id" class="form-label fw-bold">Bkash Transaction ID</label>
                                <input type="text" class="form-control" name="transaction_id" id="transaction_id" placeholder="Enter Bkash transaction ID">
                            </div>

                            <button type="submit" class="btn btn-success w-100">Proceed to Checkout</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Use jQuery for cleaner DOM manipulation
$(document).ready(function() {
    const codRadio = $('#cod');
    const bkashRadio = $('#bkash');
    const transactionField = $('#transaction-field');

    function toggleTransactionField() {
        if (bkashRadio.prop('checked')) {
            transactionField.slideDown();
            $('#transaction_id').prop('required', true);
        } else {
            transactionField.slideUp();
            $('#transaction_id').prop('required', false);
        }
    }

    codRadio.on('change', toggleTransactionField);
    bkashRadio.on('change', toggleTransactionField);
    toggleTransactionField(); // Run on page load
});
</script>

</body>
</html>