<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/mail_helper.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Define possible order statuses
$statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
$success_message = "";
$error_message = "";

// ------------------------------
// 1. HANDLE STATUS QUICK-UPDATE (POST)
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    if ($order_id > 0 && in_array($new_status, $statuses)) {
        
        // A. Fetch customer info BEFORE updating to get email and name
        $cust_sql = "SELECT c.email, c.first_name, o.total_amount 
                     FROM `orders` o 
                     JOIN customer c ON o.customer_id = c.customer_id 
                     WHERE o.order_id = ?";
        $cust_stmt = $pdo->prepare($cust_sql);
        $cust_stmt->execute([$order_id]);
        $customer = $cust_stmt->fetch();

        if ($customer) {
            // B. Update the order status in Database
            $update_sql = "UPDATE `orders` SET order_status = ? WHERE order_id = ?";
            $pdo->prepare($update_sql)->execute([$new_status, $order_id]);

            // C. Prepare and Send Email Notification
            $to = $customer['email'];
            $subject = "Order #{$order_id} Update - Gadget Store";
            $status_display = ucfirst($new_status);
            
            $body = "
                <div style='font-family: Arial, sans-serif; border: 1px solid #ddd; padding: 20px; max-width: 600px;'>
                    <h2 style='color: #0d6efd;'>Order Status Update</h2>
                    <p>Hi {$customer['first_name']},</p>
                    <p>The status of your order <strong>#{$order_id}</strong> has been updated to: <strong>$status_display</strong></p>
                    <div style='background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; font-size: 18px; text-align: center; color: #333; font-weight: bold;'>
                        Status: $status_display
                    </div>
                    <p>Total Amount: <strong>$" . number_format($customer['total_amount'], 2) . "</strong></p>
                    <p>Thank you for shopping with us!</p>
                </div>
            ";

            $mailResult = sendCustomMail($to, $subject, $body);
            $action_message = ($new_status === 'cancelled') ? 'Cancelled' : 'updated to ' . $status_display;
            
            $msg = "Order #{$order_id} {$action_message}.";
            if ($mailResult !== true) $msg .= " (But email failed to send)";
            
            redirect('orders.php?status=success&msg=' . urlencode($msg));
        }
    } else {
        $error_message = "Invalid request for status update.";
    }
}

// Check for status messages from URL
if (isset($_GET['status']) && $_GET['status'] === 'success' && isset($_GET['msg'])) {
    $success_message = htmlspecialchars($_GET['msg']);
}

// ------------------------------
// 2. FETCH ALL ORDERS
// ------------------------------
$filter_status = (isset($_GET['filter_status']) && in_array($_GET['filter_status'], $statuses)) ? $_GET['filter_status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'order_date';
$sort_dir = (isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC') ? 'ASC' : 'DESC';

$sql = "SELECT o.*, c.first_name, c.last_name 
        FROM `orders` o 
        JOIN customer c ON o.customer_id = c.customer_id";

$params = [];
if ($filter_status !== 'all') {
    $sql .= " WHERE o.order_status = ?";
    $params[] = $filter_status;
}
$sql .= " ORDER BY {$sort_by} {$sort_dir}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar { min-height: 100vh; background-color: #343a40; }
        .sidebar .nav-link { color: white; }
        .sidebar .nav-link:hover { background-color: #495057; }
        .main-content { padding: 20px; }
        .sort-icon { font-size: 0.8em; margin-left: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-nav.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Orders</h1>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-center">
                            <div class="col-auto">
                                <label for="filter_status" class="col-form-label">Filter by Status:</label>
                            </div>
                            <div class="col-md-3">
                                <select name="filter_status" id="filter_status" class="form-select">
                                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Orders</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo $filter_status === $status ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($status); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Apply Filter</button>
                                <a href="orders.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Order List (<?php echo count($orders); ?> Orders)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>
                                            <a href="?sort=order_id&dir=<?php echo ($sort_by === 'order_id' && $sort_dir === 'ASC') ? 'DESC' : 'ASC'; ?>&filter_status=<?php echo $filter_status; ?>">
                                                Order ID <?php if ($sort_by === 'order_id'): ?><i class="bi bi-arrow-<?php echo strtolower($sort_dir); ?> sort-icon"></i><?php endif; ?>
                                            </a>
                                        </th>
                                        <th>Customer</th>
                                        <th>
                                            <a href="?sort=total_amount&dir=<?php echo ($sort_by === 'total_amount' && $sort_dir === 'ASC') ? 'DESC' : 'ASC'; ?>&filter_status=<?php echo $filter_status; ?>">
                                                Amount <?php if ($sort_by === 'total_amount'): ?><i class="bi bi-arrow-<?php echo strtolower($sort_dir); ?> sort-icon"></i><?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort=order_date&dir=<?php echo ($sort_by === 'order_date' && $sort_dir === 'ASC') ? 'DESC' : 'ASC'; ?>&filter_status=<?php echo $filter_status; ?>">
                                                Date <?php if ($sort_by === 'order_date'): ?><i class="bi bi-arrow-<?php echo strtolower($sort_dir); ?> sort-icon"></i><?php endif; ?>
                                            </a>
                                        </th>
                                        <th style="width: 200px;">Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No orders found matching the criteria.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>#<?php echo $order['order_id']; ?></td>
                                                <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                                <td>
                                                    <form method="POST" class="d-flex align-items-center">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                        <select name="new_status" class="form-select form-select-sm me-2">
                                                            <?php foreach ($statuses as $status): ?>
                                                                <option value="<?php echo $status; ?>" <?php echo ($order['order_status'] === $status) ? 'selected' : ''; ?>>
                                                                    <?php echo ucfirst($status); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" name="quick_update_status" class="btn btn-sm btn-info" title="Update Status">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <a href="order-details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="invoice.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-outline-secondary me-1" target="_blank" title="Invoice">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                    <?php if ($order['order_status'] !== 'delivered' && $order['order_status'] !== 'cancelled'): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Cancel Order #<?php echo $order['order_id']; ?>?');">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                            <input type="hidden" name="new_status" value="cancelled">
                                                            <button type="submit" name="quick_update_status" class="btn btn-sm btn-outline-danger" title="Cancel Order">
                                                                <i class="bi bi-x-circle"></i> 
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>