<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Check if customer ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('customers.php');
}

$customer_id = (int)$_GET['id'];

// Fetch customer details
$stmt = $pdo->prepare("SELECT * FROM customer WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    $_SESSION['error'] = "Customer not found!";
    redirect('customers.php');
}

// Get customer statistics
$stats_sql = "SELECT 
    COUNT(o.order_id) as total_orders,
    COALESCE(SUM(o.total_amount), 0) as total_spent,
    AVG(o.total_amount) as avg_order_value,
    MAX(o.order_date) as last_order_date,
    MIN(o.order_date) as first_order_date
    FROM orders o 
    WHERE o.customer_id = ?";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute([$customer_id]);
$stats = $stats_stmt->fetch();

// Get customer's recent orders
$orders_sql = "SELECT o.*, 
    COUNT(oi.order_item_id) as item_count,
    (SELECT status FROM payment WHERE order_id = o.order_id ORDER BY payment_date DESC LIMIT 1) as payment_status
    FROM orders o
    LEFT JOIN order_item oi ON o.order_id = oi.order_id
    WHERE o.customer_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
    LIMIT 20";
$orders_stmt = $pdo->prepare($orders_sql);
$orders_stmt->execute([$customer_id]);
$orders = $orders_stmt->fetchAll();

// Get order items for each order
foreach ($orders as &$order) {
    $items_sql = "SELECT oi.*, p.product_name, b.brand_name, pi.image_url
                  FROM order_item oi
                  JOIN product p ON oi.product_id = p.product_id
                  LEFT JOIN brand b ON p.brand_id = b.brand_id
                  LEFT JOIN (SELECT product_id, MIN(image_url) as image_url FROM product_image GROUP BY product_id) pi 
                  ON p.product_id = pi.product_id
                  WHERE oi.order_id = ?";
    $items_stmt = $pdo->prepare($items_sql);
    $items_stmt->execute([$order['order_id']]);
    $order['items'] = $items_stmt->fetchAll();
}

// Calculate lifetime value metrics
$revenue_by_month = [];
if ($stats['total_orders'] > 0) {
    $revenue_sql = "SELECT 
        DATE_FORMAT(order_date, '%Y-%m') as month,
        SUM(total_amount) as monthly_revenue,
        COUNT(order_id) as monthly_orders
        FROM orders 
        WHERE customer_id = ? 
        GROUP BY DATE_FORMAT(order_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6";
    $revenue_stmt = $pdo->prepare($revenue_sql);
    $revenue_stmt->execute([$customer_id]);
    $revenue_by_month = $revenue_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?> - Customer Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: white;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .sidebar .nav-link.active {
            background-color: #007bff;
        }
        .main-content {
            padding: 20px;
        }
        .customer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .customer-avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            margin-right: 20px;
        }
        .stat-card {
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .order-item-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
            border: 2px solid white;
        }
        .badge-order-status {
            font-size: 0.75em;
        }
        .revenue-chart {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .chart-bar {
            height: 20px;
            background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 10px;
            margin-bottom: 5px;
        }
        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-nav.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="customers.php">Customers</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                        </li>
                    </ol>
                </nav>
                
                <!-- Customer Header -->
                <div class="customer-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <div class="customer-avatar-large">
                                    <?php echo strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h1 class="h2 mb-2"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h1>
                                    <div class="d-flex flex-wrap gap-3">
                                        <?php if ($customer['email']): ?>
                                        <span class="badge bg-light text-dark">
                                            <i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($customer['email']); ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($customer['phone_number']): ?>
                                        <span class="badge bg-light text-dark">
                                            <i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($customer['phone_number']); ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($customer['is_admin']): ?>
                                        <span class="badge bg-warning">
                                            <i class="bi bi-shield-check"></i> Administrator
                                        </span>
                                        <?php endif; ?>
                                        <span class="badge bg-info">
                                            Customer ID: <?php echo $customer['customer_id']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="customers.php?edit=<?php echo $customer_id; ?>" class="btn btn-light me-2">
                                <i class="bi bi-pencil"></i> Edit Customer
                            </a>
                            <a href="customers.php" class="btn btn-outline-light">
                                <i class="bi bi-arrow-left"></i> Back to Customers
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Orders</h6>
                                        <h2 class="mb-0"><?php echo $stats['total_orders'] ?? 0; ?></h2>
                                    </div>
                                    <i class="bi bi-cart display-6 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Spent</h6>
                                        <h2 class="mb-0">$<?php echo number_format($stats['total_spent'] ?? 0, 2); ?></h2>
                                    </div>
                                    <i class="bi bi-currency-dollar display-6 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Avg. Order Value</h6>
                                        <h2 class="mb-0">$<?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?></h2>
                                    </div>
                                    <i class="bi bi-graph-up display-6 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Customer Since</h6>
                                        <h2 class="mb-0"><?php echo date('Y', strtotime($customer['created_at'])); ?></h2>
                                        <small><?php echo date('F j, Y', strtotime($customer['created_at'])); ?></small>
                                    </div>
                                    <i class="bi bi-calendar display-6 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Customer Information & Activity -->
                    <div class="col-md-4">
                        <!-- Customer Details Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Customer Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="info-card">
                                    <h6><i class="bi bi-person me-2"></i>Personal Details</h6>
                                    <div class="mb-2">
                                        <small class="text-muted">Full Name</small>
                                        <p class="mb-1"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Email Address</small>
                                        <p class="mb-1"><?php echo htmlspecialchars($customer['email']); ?></p>
                                    </div>
                                    <?php if ($customer['phone_number']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Phone Number</small>
                                        <p class="mb-1"><?php echo htmlspecialchars($customer['phone_number']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($customer['address']): ?>
                                    <div>
                                        <small class="text-muted">Shipping Address</small>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($customer['address'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Order Timeline -->
                                <?php if ($stats['total_orders'] > 0): ?>
                                <div class="info-card">
                                    <h6><i class="bi bi-clock-history me-2"></i>Order History</h6>
                                    <div class="timeline">
                                        <?php if ($stats['first_order_date']): ?>
                                        <div class="timeline-item">
                                            <small class="text-muted">First Order</small>
                                            <p class="mb-1"><?php echo date('F j, Y', strtotime($stats['first_order_date'])); ?></p>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($stats['last_order_date']): ?>
                                        <div class="timeline-item">
                                            <small class="text-muted">Last Order</small>
                                            <p class="mb-0"><?php echo date('F j, Y', strtotime($stats['last_order_date'])); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Monthly Revenue Chart -->
                                <?php if (!empty($revenue_by_month)): ?>
                                <div class="revenue-chart">
                                    <h6><i class="bi bi-bar-chart me-2"></i>Recent Spending</h6>
                                    <?php foreach ($revenue_by_month as $month_data): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small><?php echo date('M Y', strtotime($month_data['month'] . '-01')); ?></small>
                                            <small><strong>$<?php echo number_format($month_data['monthly_revenue'], 2); ?></strong></small>
                                        </div>
                                        <div class="chart-bar" style="width: <?php echo min(100, ($month_data['monthly_revenue'] / max(array_column($revenue_by_month, 'monthly_revenue')) * 100)); ?>%;"></div>
                                        <small class="text-muted"><?php echo $month_data['monthly_orders']; ?> orders</small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Orders History -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Order History</h5>
                                <span class="badge bg-secondary"><?php echo $stats['total_orders'] ?? 0; ?> orders total</span>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($orders)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="ordersTable">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Date</th>
                                                <th>Items</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Payment</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong>#<?php echo $order['order_id']; ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                                                    <br><small class="text-muted"><?php echo date('h:i A', strtotime($order['order_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($order['items'])): ?>
                                                        <div class="me-3">
                                                            <?php foreach (array_slice($order['items'], 0, 3) as $item): ?>
                                                            <img src="<?php echo $item['image_url'] ? htmlspecialchars($item['image_url']) : 'https://via.placeholder.com/50'; ?>" 
                                                                 class="order-item-img me-1" 
                                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                                 title="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                            <?php endforeach; ?>
                                                            <?php if (count($order['items']) > 3): ?>
                                                            <span class="badge bg-secondary">+<?php echo count($order['items']) - 3; ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <small><?php echo $order['item_count']; ?> item(s)</small>
                                                            <br><small class="text-muted">
                                                                <?php echo htmlspecialchars($order['items'][0]['product_name'] ?? ''); ?>
                                                                <?php if (count($order['items']) > 1): ?> and <?php echo count($order['items']) - 1; ?> more<?php endif; ?>
                                                            </small>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_colors = [
                                                        'pending' => 'warning',
                                                        'processing' => 'info',
                                                        'shipped' => 'primary',
                                                        'delivered' => 'success',
                                                        'cancelled' => 'danger'
                                                    ];
                                                    $status_color = $status_colors[$order['order_status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_color; ?> badge-order-status">
                                                        <?php echo ucfirst($order['order_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $payment_colors = [
                                                        'pending' => 'warning',
                                                        'completed' => 'success',
                                                        'failed' => 'danger',
                                                        'refunded' => 'secondary'
                                                    ];
                                                    $payment_color = $payment_colors[$order['payment_status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $payment_color; ?> badge-order-status">
                                                        <?php echo ucfirst($order['payment_status'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="order-details.php?id=<?php echo $order['order_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                            
                                            <!-- Order Items Detail (Collapsible) -->
                                            <tr class="order-detail-row" style="display: none;">
                                                <td colspan="7" class="bg-light">
                                                    <div class="p-3">
                                                        <h6>Order Items</h6>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Product</th>
                                                                        <th>Brand</th>
                                                                        <th>Quantity</th>
                                                                        <th>Price</th>
                                                                        <th>Subtotal</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($order['items'] as $item): ?>
                                                                    <tr>
                                                                        <td>
                                                                            <div class="d-flex align-items-center">
                                                                                <img src="<?php echo $item['image_url'] ? htmlspecialchars($item['image_url']) : 'https://via.placeholder.com/40'; ?>" 
                                                                                     class="order-item-img me-2">
                                                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                                                            </div>
                                                                        </td>
                                                                        <td><?php echo htmlspecialchars($item['brand_name'] ?? 'N/A'); ?></td>
                                                                        <td><?php echo $item['quantity']; ?></td>
                                                                        <td>$<?php echo number_format($item['per_unit_price'], 2); ?></td>
                                                                        <td><strong>$<?php echo number_format($item['quantity'] * $item['per_unit_price'], 2); ?></strong></td>
                                                                    </tr>
                                                                    <?php endforeach; ?>
                                                                    <tr>
                                                                        <td colspan="4" class="text-end"><strong>Order Total:</strong></td>
                                                                        <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if (count($orders) > 10): ?>
                                <div class="text-center mt-3">
                                    <a href="customer-orders.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-list"></i> View All Orders
                                    </a>
                                </div>
                                <?php endif; ?>
                                
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-cart display-1 text-muted"></i>
                                    <h5 class="mt-3">No Orders Yet</h5>
                                    <p class="text-muted">This customer hasn't placed any orders yet.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#ordersTable').DataTable({
                "pageLength": 5,
                "order": [[1, 'desc']], // Sort by date descending
                "language": {
                    "search": "Search orders:",
                    "lengthMenu": "Show _MENU_ orders per page"
                }
            });
            
            // Toggle order details
            $('.order-detail-row').prev().click(function() {
                $(this).next('.order-detail-row').toggle();
            });
        });
    </script>
</body>
</html>