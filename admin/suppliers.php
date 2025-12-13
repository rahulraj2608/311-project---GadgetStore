<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Handle supplier deletion
if (isset($_GET['delete'])) {
    $supplier_id = (int)$_GET['delete'];
    
    // Check if supplier has products
    $check_stmt = $pdo->prepare("SELECT COUNT(*) as product_count FROM product WHERE supplier_id = ?");
    $check_stmt->execute([$supplier_id]);
    $result = $check_stmt->fetch();
    
    if ($result['product_count'] == 0) {
        $pdo->prepare("DELETE FROM supplier WHERE supplier_id = ?")->execute([$supplier_id]);
        $_SESSION['success'] = "Supplier deleted successfully!";
    } else {
        $_SESSION['error'] = "Cannot delete supplier. There are products associated with this supplier.";
    }
    redirect('suppliers.php');
}

// Handle form submission for adding/editing supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_name = sanitize($_POST['supplier_name']);
    $phone_number = sanitize($_POST['phone_number']);
    $email = sanitize($_POST['email']);
    
    if (isset($_POST['supplier_id']) && !empty($_POST['supplier_id'])) {
        // Edit existing supplier
        $supplier_id = (int)$_POST['supplier_id'];
        $stmt = $pdo->prepare("UPDATE supplier SET supplier_name = ?, phone_number = ?, email = ? WHERE supplier_id = ?");
        $stmt->execute([$supplier_name, $phone_number, $email, $supplier_id]);
        $_SESSION['success'] = "Supplier updated successfully!";
    } else {
        // Add new supplier
        $stmt = $pdo->prepare("INSERT INTO supplier (supplier_name, phone_number, email) VALUES (?, ?, ?)");
        $stmt->execute([$supplier_name, $phone_number, $email]);
        $_SESSION['success'] = "Supplier added successfully!";
    }
    redirect('suppliers.php');
}

// Get all suppliers with product count
$sql = "SELECT s.*, COUNT(p.product_id) as product_count 
        FROM supplier s 
        LEFT JOIN product p ON s.supplier_id = p.supplier_id 
        GROUP BY s.supplier_id 
        ORDER BY s.supplier_name";
$suppliers = $pdo->query($sql)->fetchAll();

// Get supplier details for editing
$edit_supplier = null;
if (isset($_GET['edit'])) {
    $supplier_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM supplier WHERE supplier_id = ?");
    $stmt->execute([$supplier_id]);
    $edit_supplier = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Suppliers - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
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
        .product-count-badge {
            font-size: 0.75em;
        }
        .action-buttons {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-nav.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <h1 class="mb-4">Manage Suppliers</h1>
                
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
                
                <div class="row">
                    <!-- Add/Edit Supplier Form -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <?php echo $edit_supplier ? 'Edit Supplier' : 'Add New Supplier'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <?php if ($edit_supplier): ?>
                                        <input type="hidden" name="supplier_id" value="<?php echo $edit_supplier['supplier_id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="supplier_name" class="form-label">Supplier Name *</label>
                                        <input type="text" class="form-control" id="supplier_name" name="supplier_name" 
                                               value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['supplier_name']) : ''; ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['email']) : ''; ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone_number" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                               value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['phone_number']) : ''; ?>">
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <?php echo $edit_supplier ? 'Update Supplier' : 'Add Supplier'; ?>
                                        </button>
                                        <?php if ($edit_supplier): ?>
                                            <a href="suppliers.php" class="btn btn-outline-secondary">Cancel Edit</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Suppliers List -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">All Suppliers</h5>
                                <span class="badge bg-secondary"><?php echo count($suppliers); ?> suppliers</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Supplier Name</th>
                                                <th>Contact Information</th>
                                                <th>Products</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($suppliers as $supplier): ?>
                                            <tr>
                                                <td><?php echo $supplier['supplier_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <div><i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($supplier['email']); ?></div>
                                                    <?php if ($supplier['phone_number']): ?>
                                                    <div><i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars($supplier['phone_number']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info product-count-badge">
                                                        <?php echo $supplier['product_count']; ?> products
                                                    </span>
                                                </td>
                                                <td class="action-buttons">
                                                    <a href="view-supplier.php?id=<?php echo $supplier['supplier_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="suppliers.php?edit=<?php echo $supplier['supplier_id']; ?>" 
                                                       class="btn btn-sm btn-outline-warning" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $supplier['supplier_id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Are you sure? This action cannot be undone if there are products associated.')" 
                                                       title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if (empty($suppliers)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-truck display-1 text-muted"></i>
                                    <h5 class="mt-3">No Suppliers Found</h5>
                                    <p class="text-muted">Start by adding your first supplier</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const email = document.getElementById('email').value;
                const phone = document.getElementById('phone_number').value;
                
                // Validate email format
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('Please enter a valid email address.');
                    return;
                }
                
                // Validate phone format (optional)
                if (phone && !/^[\d\s\-\+\(\)]{10,}$/.test(phone)) {
                    e.preventDefault();
                    alert('Please enter a valid phone number.');
                    return;
                }
            });
        });
    </script>
</body>
</html>