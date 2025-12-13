<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Define possible statuses for the dropdown
$statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

// ------------------------------
// 1. GET ORDER ID AND FETCH DATA
// ------------------------------
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id === 0) {
    // Redirect if no valid ID is provided
    redirect('orders.php'); 
}

// Fetch main order details and customer info
$order_sql = "SELECT o.*, c.first_name, c.last_name, c.email 
              FROM `orders` o 
              JOIN customer c ON o.customer_id = c.customer_id 
              WHERE o.order_id = ?";
$order_stmt = $pdo->prepare($order_sql);
$order_stmt->execute([$order_id]);
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    // Handle case where order does not exist
    $error_message = "Order #{$order_id} not found.";
    // Optionally set $order to null to skip the rest of the display
    $order = null; 
}

// Fetch order items (products, price, quantity) if the order was found
$order_items = [];
if ($order) {
    // FIX APPLIED: Removed 'p.sku' from the SELECT list.
    $items_sql = "SELECT oi.*, p.product_name, pi.image_url
                  FROM order_item oi
                  JOIN product p ON oi.product_id = p.product_id
                  LEFT JOIN product_image pi ON p.product_id = pi.product_id
                  WHERE oi.order_id = ?
                  GROUP BY oi.order_item_id"; 
    $items_stmt = $pdo->prepare($items_sql);
    // Line 50 in previous version was where execute was called. This should now run without error.
    $items_stmt->execute([$order_id]); 
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ------------------------------
// 2. HANDLE STATUS UPDATE (POST)
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'];

    if (in_array($new_status, $statuses)) {
        $update_sql = "UPDATE `orders` SET order_status = ? WHERE order_id = ?";
        $pdo->prepare($update_sql)->execute([$new_status, $order_id]);
        
        // Update the $order array for display without a redirect
        $order['order_status'] = $new_status;
        $success_message = "Order #{$order_id} status successfully updated to: " . ucfirst($new_status);
    } else {
        $error_message = "Invalid status selected.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details #<?php echo $order_id; ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar { min-height: 100vh; background-color: #343a40; }
        .sidebar .nav-link { color: white; }
        .sidebar .nav-link:hover { background-color: #495057; }
        .main-content { padding: 20px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar bg-dark">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php"><i class="bi bi-box"></i> Products</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php"><i class="bi bi-tags"></i> Categories</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="orders.php"><i class="bi bi-cart"></i> Orders</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customers.php"><i class="bi bi-people"></i> Customers</a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Order #<?php echo $order_id; ?> Details</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="orders.php" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="bi bi-arrow-left"></i> Back to Orders
                        </a>
                        <a href="invoice.php?id=<?php echo $order_id; ?>" target="_blank" class="btn btn-sm btn-info text-white">
                            <i class="bi bi-printer"></i> Print Invoice
                        </a>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if ($order): ?>
                <div class="row">
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">Order Summary</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Order Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></p>
                                <p><strong>Customer:</strong> <?php echo $order['first_name'] . ' ' . $order['last_name']; ?></p>
                                <p><strong>Email:</strong> <?php echo $order['email']; ?></p>
                                <p><strong>Payment Method:</strong> <?php echo $order['payment_method']; ?></p>
                                <?php if (!empty($order['transaction_id'])): ?>
                                    <p><strong>Transaction ID:</strong> <span class="badge bg-secondary"><?php echo $order['transaction_id']; ?></span></p>
                                <?php endif; ?>
                                <hr>
                                <p class="h4"><strong>Total Amount:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                            </div>
                        </div>

                        <div class="card mb-4 border-info">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Update Status</h5>
                            </div>
                            <div class="card-body">
                                <p>Current Status: 
                                    <span class="badge bg-<?php 
                                        switch($order['order_status']) {
                                            case 'delivered': echo 'success'; break;
                                            case 'shipped': echo 'primary'; break;
                                            case 'processing': echo 'info'; break;
                                            case 'pending': echo 'warning'; break;
                                            default: echo 'danger';
                                        }
                                    ?>">
                                        <?php echo ucfirst($order['order_status']); ?>
                                    </span>
                                </p>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="new_status" class="form-label">New Status</label>
                                        <select name="new_status" id="new_status" class="form-select" required>
                                            <?php foreach ($statuses as $status): ?>
                                                <option value="<?php echo $status; ?>" 
                                                    <?php echo ($order['order_status'] === $status) ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($status); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="update_status" class="btn btn-success w-100">
                                        <i class="bi bi-arrow-repeat"></i> Update Order
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8">
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Shipping Address</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><?php echo nl2br($order['shipping_address']); ?></p>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Ordered Products (<?php echo count($order_items); ?> items)</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th class="text-end">Unit Price</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $subtotal = 0;
                                        foreach ($order_items as $item): 
                                            $item_subtotal = $item['per_unit_price'] * $item['quantity'];
                                            $subtotal += $item_subtotal;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo $item['image_url'] ?? 'https://via.placeholder.com/50'; ?>" 
                                                         alt="<?php echo $item['product_name']; ?>" class="rounded me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                                    <div>
                                                        <strong><?php echo $item['product_name']; ?></strong><br>
                                                        </div>
                                                </div>
                                            </td>
                                            <td class="text-end">$<?php echo number_format($item['per_unit_price'], 2); ?></td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td class="text-end">$<?php echo number_format($item_subtotal, 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <?php 
                                        // Recalculate tax and shipping based on stored total amount and item subtotal
                                        $shipping = $order['shipping'] ?? 0.00; // Use stored shipping if available
                                        $tax_paid = $order['total_amount'] - $shipping - $subtotal;
                                        ?>
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold">Subtotal:</td>
                                            <td class="text-end fw-bold">$<?php echo number_format($subtotal, 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="text-end">Shipping:</td>
                                            <td class="text-end">$<?php echo number_format($shipping, 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="text-end">Tax:</td>
                                            <td class="text-end">$<?php echo number_format($tax_paid, 2); ?></td>
                                        </tr>
                                        <tr class="table-dark">
                                            <td colspan="3" class="text-end fw-bold">Grand Total:</td>
                                            <td class="text-end fw-bold">$<?php echo number_format($order['total_amount'], 2); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-danger mt-4">The requested order details could not be found.</div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>