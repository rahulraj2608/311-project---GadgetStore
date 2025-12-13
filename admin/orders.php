<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Define possible order statuses
$statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

// ------------------------------
// 1. HANDLE STATUS QUICK-UPDATE (POST)
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    if ($order_id > 0 && in_array($new_status, $statuses)) {
        // Prepare the status message
        $action_message = ($new_status === 'cancelled') ? 'Cancelled' : 'updated to ' . ucfirst($new_status);
        
        $update_sql = "UPDATE `orders` SET order_status = ? WHERE order_id = ?";
        $pdo->prepare($update_sql)->execute([$new_status, $order_id]);
        $success_message = "Order #{$order_id} {$action_message}.";
        
        // Redirect to prevent form resubmission on refresh
        redirect('orders.php?status=success&msg=' . urlencode($success_message));
    } else {
        $error_message = "Invalid request for status update.";
    }
}

// Check for status messages after redirect
if (isset($_GET['status']) && $_GET['status'] === 'success' && isset($_GET['msg'])) {
    $success_message = urldecode($_GET['msg']);
}


// ------------------------------
// 2. FETCH ALL ORDERS (with optional filtering/sorting)
// ------------------------------

// Base SQL query
$sql = "SELECT o.*, c.first_name, c.last_name 
        FROM `orders` o 
        JOIN customer c ON o.customer_id = c.customer_id";

$where_clauses = [];
$params = [];

// Filtering by Status
$filter_status = isset($_GET['filter_status']) && in_array($_GET['filter_status'], $statuses) ? $_GET['filter_status'] : 'all';
if ($filter_status !== 'all') {
    $where_clauses[] = "o.order_status = ?";
    $params[] = $filter_status;
}

// Sorting
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'order_date';
$sort_dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC' ? 'ASC' : 'DESC';

// Apply WHERE clauses
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

// Apply ORDER BY
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
                            <a class="nav-link active" aria-current="page" href="orders.php"><i class="bi bi-cart"></i> Orders</a>
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
                    <h1 class="h2">Manage Orders</h1>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
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
                                                Order ID 
                                                <?php if ($sort_by === 'order_id'): ?><i class="bi bi-arrow-<?php echo strtolower($sort_dir); ?> sort-icon"></i><?php endif; ?>
                                            </a>
                                        </th>
                                        <th>Customer</th>
                                        <th>
                                            <a href="?sort=total_amount&dir=<?php echo ($sort_by === 'total_amount' && $sort_dir === 'ASC') ? 'DESC' : 'ASC'; ?>&filter_status=<?php echo $filter_status; ?>">
                                                Amount 
                                                <?php if ($sort_by === 'total_amount'): ?><i class="bi bi-arrow-<?php echo strtolower($sort_dir); ?> sort-icon"></i><?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort=order_date&dir=<?php echo ($sort_by === 'order_date' && $sort_dir === 'ASC') ? 'DESC' : 'ASC'; ?>&filter_status=<?php echo $filter_status; ?>">
                                                Date 
                                                <?php if ($sort_by === 'order_date'): ?><i class="bi bi-arrow-<?php echo strtolower($sort_dir); ?> sort-icon"></i><?php endif; ?>
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
                                                <td><?php echo $order['first_name'] . ' ' . $order['last_name']; ?></td>
                                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                                <td>
                                                    <form method="POST" class="d-flex align-items-center">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                        <select name="new_status" class="form-select form-select-sm me-2">
                                                            <?php foreach ($statuses as $status): ?>
                                                                <option value="<?php echo $status; ?>" 
                                                                    <?php echo ($order['order_status'] === $status) ? 'selected' : ''; ?>>
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
                                                    <a href="order-details.php?id=<?php echo $order['order_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary me-1" title="View Order Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    
                                                    <a href="invoice.php?id=<?php echo $order['order_id']; ?>" 
                                                       class="btn btn-sm btn-outline-secondary me-1" target="_blank" title="Print Invoice">
                                                        <i class="bi bi-printer"></i>
                                                    </a>

                                                    <?php 
                                                    // Action 3: Cancel Order - Only visible if the order hasn't been completed or cancelled yet
                                                    if ($order['order_status'] !== 'delivered' && $order['order_status'] !== 'cancelled'): 
                                                    ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel Order #<?php echo $order['order_id']; ?>? This action cannot be undone.');">
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