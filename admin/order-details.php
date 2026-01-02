<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/mail_helper.php'; // 1. Added mail helper

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Fixed Charges
$shipping_fixed = 10.00;
$tax_rate = 0.10; // 10%
$statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

// 1. GET ORDER ID AND FETCH DATA
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id === 0) { redirect('orders.php'); }

$order_sql = "SELECT o.*, c.first_name, c.last_name, c.email 
              FROM `orders` o 
              JOIN customer c ON o.customer_id = c.customer_id 
              WHERE o.order_id = ?";
$order_stmt = $pdo->prepare($order_sql);
$order_stmt->execute([$order_id]);
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) { redirect('orders.php'); } // Ensure order exists

$order_items = [];
$items_sql = "SELECT oi.*, p.product_name, pi.image_url
              FROM order_item oi
              JOIN product p ON oi.product_id = p.product_id
              LEFT JOIN product_image pi ON p.product_id = pi.product_id
              WHERE oi.order_id = ?
              GROUP BY oi.order_item_id"; 
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute([$order_id]); 
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. HANDLE STATUS UPDATE AND SEND MAIL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'];
    
    if (in_array($new_status, $statuses)) {
        // A. Update Database
        $update_sql = "UPDATE `orders` SET order_status = ? WHERE order_id = ?";
        $pdo->prepare($update_sql)->execute([$new_status, $order_id]);
        
        // B. Prepare Email Content
        $to = $order['email'];
        $first_name = $order['first_name'];
        $status_display = ucfirst($new_status);
        $subject = "Your Order #$order_id has been $status_display";
        
        $body = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <h2 style='color: #0d6efd;'>Order Status Update</h2>
                <p>Hello $first_name,</p>
                <p>We are writing to let you know that your order <strong>#$order_id</strong> has been updated to:</p>
                <div style='background: #f8f9fa; padding: 15px; border-left: 5px solid #0d6efd; margin: 20px 0;'>
                    <span style='font-size: 1.2em; font-weight: bold;'>$status_display</span>
                </div>
                <p>Order Total: <strong>$" . number_format($order['total_amount'], 2) . "</strong></p>
                <p>You can view your order history in your account dashboard for more details.</p>
                <br>
                <p>Thank you for shopping with us!</p>
                <hr style='border: 0; border-top: 1px solid #eee;'>
                <p style='font-size: 0.8em; color: #777;'>Gadget Store Admin Team</p>
            </div>
        ";

        // C. Send Mail
        $mail_sent = sendCustomMail($to, $subject, $body);

        // D. Update UI message
        $order['order_status'] = $new_status;
        $success_message = "Order status updated to: " . $status_display;
        if ($mail_sent === true) {
            $success_message .= " and notification email sent to " . $to;
        } else {
            $error_message = "Status updated, but email failed: " . $mail_sent;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Details #<?php echo $order_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
        <div class="container-fluid">

        <div class="row">

       <?php include '../includes/admin-nav.php'; ?>

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


    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Order #<?php echo $order_id; ?></h1>
            <a href="orders.php" class="btn btn-secondary">Back to List</a>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header fw-bold">Items Purchased</div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $calculated_subtotal = 0;
                                foreach ($order_items as $item): 
                                    $item_total = $item['per_unit_price'] * $item['quantity'];
                                    $calculated_subtotal += $item_total;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td>$<?php echo number_format($item['per_unit_price'], 2); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td class="text-end">$<?php echo number_format($item_total, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white fw-bold">Order Summary</div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($calculated_subtotal, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span>$<?php echo number_format($shipping_fixed, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-muted">
                            <span>Tax (10%):</span>
                            <?php $tax_amount = $calculated_subtotal * $tax_rate; ?>
                            <span>$<?php echo number_format($tax_amount, 2); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <strong class="h5">Grand Total:</strong>
                            <?php $grand_total = $calculated_subtotal + $shipping_fixed + $tax_amount; ?>
                            <strong class="h5 text-primary">$<?php echo number_format($grand_total, 2); ?></strong>
                        </div>

                        <form method="POST">
                            <label class="form-label small fw-bold">UPDATE STATUS</label>
                            <div class="input-group">
                                <select name="new_status" class="form-select form-select-sm">
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo ($order['order_status'] == $status) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($status); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button name="update_status" class="btn btn-sm btn-success">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold">Customer Info</h6>
                        <p class="mb-0 small"><?php echo $order['first_name'] . ' ' . $order['last_name']; ?></p>
                        <p class="small text-muted"><?php echo $order['email']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>