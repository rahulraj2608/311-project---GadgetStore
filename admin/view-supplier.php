<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Check if supplier ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('suppliers.php');
}

$supplier_id = (int)$_GET['id'];

// Fetch supplier details
$stmt = $pdo->prepare("SELECT * FROM supplier WHERE supplier_id = ?");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    $_SESSION['error'] = "Supplier not found!";
    redirect('suppliers.php');
}

// Fetch supplier's products
$sql = "SELECT p.*, b.brand_name, c.category_name, 
               (SELECT image_url FROM product_image WHERE product_id = p.product_id LIMIT 1) as image_url
        FROM product p
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        LEFT JOIN category c ON p.category_id = c.category_id
        WHERE p.supplier_id = ?
        ORDER BY p.product_name";
$stmt = $pdo->prepare($sql);
$stmt->execute([$supplier_id]);
$products = $stmt->fetchAll();

// Calculate statistics
$total_products = count($products);
$total_stock = 0;
$total_value = 0;
$out_of_stock = 0;

foreach ($products as $product) {
    $total_stock += $product['stock_quantity'];
    $total_value += $product['price'] * $product['stock_quantity'];
    if ($product['stock_quantity'] == 0) {
        $out_of_stock++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($supplier['supplier_name']); ?> - Supplier Details</title>
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
        .supplier-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        .breadcrumb {
            background-color: transparent;
            padding: 0;
        }
        .contact-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
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
                        <li class="breadcrumb-item"><a href="suppliers.php">Suppliers</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($supplier['supplier_name']); ?></li>
                    </ol>
                </nav>
                
                <!-- Supplier Header -->
                <div class="supplier-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h2 mb-2"><?php echo htmlspecialchars($supplier['supplier_name']); ?></h1>
                            <div class="d-flex flex-wrap gap-3">
                                <?php if ($supplier['email']): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($supplier['email']); ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($supplier['phone_number']): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($supplier['phone_number']); ?>
                                </span>
                                <?php endif; ?>
                                <span class="badge bg-info">
                                    <i class="bi bi-box me-1"></i> <?php echo $total_products; ?> Products
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="suppliers.php?edit=<?php echo $supplier_id; ?>" class="btn btn-light me-2">
                                <i class="bi bi-pencil"></i> Edit Supplier
                            </a>
                            <a href="suppliers.php" class="btn btn-outline-light">
                                <i class="bi bi-arrow-left"></i> Back to Suppliers
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Products</h6>
                                        <h2 class="mb-0"><?php echo $total_products; ?></h2>
                                    </div>
                                    <i class="bi bi-box display-6 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Stock</h6>
                                        <h2 class="mb-0"><?php echo $total_stock; ?></h2>
                                    </div>
                                    <i class="bi bi-archive display-6 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Out of Stock</h6>
                                        <h2 class="mb-0"><?php echo $out_of_stock; ?></h2>
                                    </div>
                                    <i class="bi bi-exclamation-triangle display-6 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Inventory Value</h6>
                                        <h2 class="mb-0">$<?php echo number_format($total_value, 2); ?></h2>
                                    </div>
                                    <i class="bi bi-currency-dollar display-6 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Contact Information -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Contact Information</h5>
                            </div>
                            <div class="card-body contact-info">
                                <?php if ($supplier['email']): ?>
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Email Address</h6>
                                    <p class="mb-0">
                                        <i class="bi bi-envelope me-2"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>">
                                            <?php echo htmlspecialchars($supplier['email']); ?>
                                        </a>
                                    </p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($supplier['phone_number']): ?>
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Phone Number</h6>
                                    <p class="mb-0">
                                        <i class="bi bi-telephone me-2"></i>
                                        <a href="tel:<?php echo htmlspecialchars($supplier['phone_number']); ?>">
                                            <?php echo htmlspecialchars($supplier['phone_number']); ?>
                                        </a>
                                    </p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mt-4">
                                    <h6 class="text-muted mb-2">Quick Actions</h6>
                                    <div class="d-grid gap-2">
                                        <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-envelope"></i> Send Email
                                        </a>
                                        <?php if ($supplier['phone_number']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($supplier['phone_number']); ?>" class="btn btn-outline-success">
                                            <i class="bi bi-telephone"></i> Call Supplier
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Products List -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Products Supplied</h5>
                                <span class="badge bg-secondary"><?php echo $total_products; ?> items</span>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($products)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Image</th>
                                                <th>Product Name</th>
                                                <th>Category</th>
                                                <th>Brand</th>
                                                <th>Price</th>
                                                <th>Stock</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <img src="<?php echo $product['image_url'] ? htmlspecialchars($product['image_url']) : 'https://via.placeholder.com/60'; ?>" 
                                                         class="product-img" 
                                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                                    <?php if (!empty($product['description'])): ?>
                                                    <br><small class="text-muted"><?php echo substr(htmlspecialchars($product['description']), 0, 50); ?>...</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($product['brand_name']); ?></td>
                                                <td><strong>$<?php echo number_format($product['price'], 2); ?></strong></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $product['stock_quantity'] > 0 ? 'success' : 'danger'; ?>">
                                                        <?php echo $product['stock_quantity']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="edit-product.php?id=<?php echo $product['product_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Edit Product">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="../product-details.php?id=<?php echo $product['product_id']; ?>" 
                                                       target="_blank" class="btn btn-sm btn-outline-info" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Summary -->
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Inventory Summary</h6>
                                                <ul class="list-unstyled mb-0">
                                                    <li><small class="text-muted">Total Products: <?php echo $total_products; ?></small></li>
                                                    <li><small class="text-muted">In Stock: <?php echo $total_products - $out_of_stock; ?></small></li>
                                                    <li><small class="text-muted">Out of Stock: <?php echo $out_of_stock; ?></small></li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Financial Summary</h6>
                                                <ul class="list-unstyled mb-0">
                                                    <li><small class="text-muted">Total Inventory Value: <strong>$<?php echo number_format($total_value, 2); ?></strong></small></li>
                                                    <li><small class="text-muted">Average Product Price: <strong>$<?php echo $total_products > 0 ? number_format($total_value / $total_stock, 2) : '0.00'; ?></strong></small></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-box-seam display-1 text-muted"></i>
                                    <h5 class="mt-3">No Products Found</h5>
                                    <p class="text-muted mb-4">This supplier doesn't have any products yet.</p>
                                    <a href="add-product.php?supplier=<?php echo $supplier_id; ?>" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Add Product for This Supplier
                                    </a>
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
        // Optional: Add JavaScript for interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Add confirmation for external links
            const externalLinks = document.querySelectorAll('a[href^="mailto:"], a[href^="tel:"]');
            externalLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const action = this.href.startsWith('mailto:') ? 'email' : 'call';
                    if (!confirm(`Are you sure you want to ${action} this supplier?`)) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>