<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Handle search
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filter_admin = isset($_GET['filter_admin']) ? (int)$_GET['filter_admin'] : -1;

// Build query for customers with order statistics
$sql = "SELECT c.*, 
               COUNT(o.order_id) as total_orders,
               COALESCE(SUM(o.total_amount), 0) as total_spent,
               MAX(o.order_date) as last_order_date
        FROM customer c
        LEFT JOIN orders o ON c.customer_id = o.customer_id
        WHERE 1=1";

$params = [];
$types = '';

// Add search conditions
if ($search) {
    $sql .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone_number LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}

// Filter by admin status
if ($filter_admin >= 0) {
    $sql .= " AND c.is_admin = ?";
    $params[] = $filter_admin;
    $types .= 'i';
}

$sql .= " GROUP BY c.customer_id ORDER BY c.created_at DESC";

// Prepare and execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Handle customer deletion
if (isset($_GET['delete'])) {
    $customer_id = (int)$_GET['delete'];
    
    // Check if customer has orders
    $check_stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders WHERE customer_id = ?");
    $check_stmt->execute([$customer_id]);
    $result = $check_stmt->fetch();
    
    if ($result['order_count'] == 0) {
        $pdo->prepare("DELETE FROM customer WHERE customer_id = ?")->execute([$customer_id]);
        $_SESSION['success'] = "Customer deleted successfully!";
    } else {
        $_SESSION['error'] = "Cannot delete customer. There are orders associated with this customer.";
    }
    redirect('customers.php');
}

// Handle toggle admin status
if (isset($_GET['toggle_admin'])) {
    $customer_id = (int)$_GET['toggle_admin'];
    
    $stmt = $pdo->prepare("UPDATE customer SET is_admin = NOT is_admin WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    
    $_SESSION['success'] = "Customer admin status updated!";
    redirect('customers.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - Admin</title>
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
        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .stats-card {
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .search-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .action-buttons {
            white-space: nowrap;
        }
        .dataTables_wrapper {
            padding: 0;
        }
        .badge-admin {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .badge-customer {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .last-order {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-nav.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Customer Management</h1>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                            <i class="bi bi-person-plus"></i> Add Customer
                        </button>
                    </div>
                </div>
                
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <?php
                    // Get statistics
                    $total_customers = $pdo->query("SELECT COUNT(*) as count FROM customer")->fetch()['count'];
                    $total_admins = $pdo->query("SELECT COUNT(*) as count FROM customer WHERE is_admin = 1")->fetch()['count'];
                    $active_customers = $pdo->query("SELECT COUNT(DISTINCT customer_id) as count FROM orders WHERE order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['count'];
                    $total_revenue = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders")->fetch()['total'];
                    ?>
                    
                    <div class="col-md-3">
                        <div class="card stats-card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Customers</h6>
                                        <h2 class="mb-0"><?php echo $total_customers; ?></h2>
                                    </div>
                                    <i class="bi bi-people display-6 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stats-card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Active Customers</h6>
                                        <h2 class="mb-0"><?php echo $active_customers; ?></h2>
                                        <small>Last 30 days</small>
                                    </div>
                                    <i class="bi bi-person-check display-6 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stats-card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Admin Users</h6>
                                        <h2 class="mb-0"><?php echo $total_admins; ?></h2>
                                    </div>
                                    <i class="bi bi-shield-check display-6 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stats-card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Revenue</h6>
                                        <h2 class="mb-0">$<?php echo number_format($total_revenue, 2); ?></h2>
                                    </div>
                                    <i class="bi bi-currency-dollar display-6 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter Section -->
                <div class="search-container">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <select class="form-select" name="filter_admin">
                                <option value="-1" <?php echo $filter_admin == -1 ? 'selected' : ''; ?>>All Users</option>
                                <option value="1" <?php echo $filter_admin == 1 ? 'selected' : ''; ?>>Admins Only</option>
                                <option value="0" <?php echo $filter_admin == 0 ? 'selected' : ''; ?>>Customers Only</option>
                            </select>
                        </div>
                        
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                    
                    <?php if ($search || $filter_admin >= 0): ?>
                    <div class="mt-3">
                        <span class="badge bg-light text-dark">
                            Found <?php echo count($customers); ?> customer(s)
                            <?php if ($search): ?> matching "<?php echo htmlspecialchars($search); ?>"<?php endif; ?>
                        </span>
                        <a href="customers.php" class="btn btn-sm btn-outline-secondary ms-2">Clear Filters</a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Customers Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="customersTable">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Contact</th>
                                        <th>Role</th>
                                        <th>Orders</th>
                                        <th>Total Spent</th>
                                        <th>Last Order</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="customer-avatar me-3">
                                                    <?php echo strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong>
                                                    <br><small class="text-muted">ID: <?php echo $customer['customer_id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($customer['email']); ?></div>
                                            <?php if ($customer['phone_number']): ?>
                                            <div><i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($customer['phone_number']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($customer['is_admin']): ?>
                                            <span class="badge badge-admin">
                                                <i class="bi bi-shield-check"></i> Admin
                                            </span>
                                            <?php else: ?>
                                            <span class="badge badge-customer">Customer</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo $customer['total_orders']; ?> orders
                                            </span>
                                        </td>
                                        <td>
                                            <strong>$<?php echo number_format($customer['total_spent'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($customer['last_order_date']): ?>
                                            <span class="last-order">
                                                <?php echo date('M d, Y', strtotime($customer['last_order_date'])); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">No orders</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($customer['created_at'])); ?>
                                        </td>
                                        <td class="action-buttons">
                                            <a href="view-customer.php?id=<?php echo $customer['customer_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="?toggle_admin=<?php echo $customer['customer_id']; ?>" 
                                               class="btn btn-sm btn-outline-warning" 
                                               onclick="return confirm('Toggle admin status for <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>?')"
                                               title="Toggle Admin">
                                                <i class="bi bi-shield"></i>
                                            </a>
                                            <?php if ($customer['customer_id'] != $_SESSION['user_id']): ?>
                                            <a href="?delete=<?php echo $customer['customer_id']; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Are you sure? This will permanently delete the customer and all their data.')"
                                               title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (empty($customers)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-people display-1 text-muted"></i>
                            <h5 class="mt-3">No Customers Found</h5>
                            <p class="text-muted">Try adjusting your search or add a new customer.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="add-customer.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Customer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2"></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_admin" id="is_admin">
                                    <label class="form-check-label" for="is_admin">
                                        Make this user an administrator
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Customer</button>
                    </div>
                </form>
            </div>
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
            $('#customersTable').DataTable({
                "pageLength": 10,
                "order": [[6, 'desc']], // Sort by joined date descending
                "language": {
                    "search": "Filter:",
                    "lengthMenu": "Show _MENU_ customers per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ customers"
                }
            });
        });
    </script>
</body>
</html>