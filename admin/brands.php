<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// ------------------------------
// 1. DATA FETCHING AND PROCESSING
// ------------------------------

// Handle search and filtering
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sql = "SELECT b.*, COUNT(p.product_id) as product_count
        FROM brand b
        LEFT JOIN product p ON b.brand_id = p.brand_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND b.brand_name LIKE ?";
    $params[] = "%$search%";
}

$sql .= " GROUP BY b.brand_id ORDER BY b.brand_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ------------------------------
// 2. HANDLE DELETION (GET Request)
// ------------------------------
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // Check if any products are associated with this brand
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE brand_id = ?");
    $check_stmt->execute([$delete_id]);
    $product_count = $check_stmt->fetchColumn();

    if ($product_count == 0) {
        // Safe to delete
        $delete_stmt = $pdo->prepare("DELETE FROM brand WHERE brand_id = ?");
        $delete_stmt->execute([$delete_id]);
        $_SESSION['success'] = "Brand deleted successfully.";
    } else {
        $_SESSION['error'] = "Cannot delete brand. {$product_count} product(s) are currently linked to it.";
    }
    
    // Redirect to clear the GET parameter
    redirect('brands.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Brands - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar { min-height: 100vh; background-color: #343a40; }
        .sidebar .nav-link { color: white; }
        .sidebar .nav-link:hover { background-color: #495057; }
        .sidebar .nav-link.active { background-color: #007bff; }
        .main-content { padding: 20px; }
        .brand-icon { font-size: 1.5rem; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-nav.php'; // Assuming this file exists ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1><i class="bi bi-tag-fill me-2"></i> Brand Management</h1>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBrandModal">
                            <i class="bi bi-plus-lg"></i> Add New Brand
                        </button>
                    </div>
                </div>

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

                <div class="card p-3 mb-4">
                    <form method="GET" action="brands.php" class="row g-3 align-items-center">
                        <div class="col-md-10">
                            <input type="text" class="form-control" name="search" placeholder="Search by brand name..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-secondary">Search</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 10%;">ID</th>
                                        <th style="width: 50%;">Brand Name</th>
                                        <th style="width: 20%;">Products Count</th>
                                        <th style="width: 20%;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($brands)): ?>
                                        <?php foreach ($brands as $brand): ?>
                                            <tr>
                                                <td><?php echo $brand['brand_id']; ?></td>
                                                <td>
                                                    <i class="bi bi-diamond-fill me-2 brand-icon"></i>
                                                    <strong><?php echo htmlspecialchars($brand['brand_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info text-dark">
                                                        <?php echo $brand['product_count']; ?> Products
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-warning edit-brand-btn" 
                                                            data-id="<?php echo $brand['brand_id']; ?>" 
                                                            data-name="<?php echo htmlspecialchars($brand['brand_name']); ?>"
                                                            data-bs-toggle="modal" data-bs-target="#editBrandModal" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <a href="?delete_id=<?php echo $brand['brand_id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Are you sure you want to delete the brand: <?php echo htmlspecialchars($brand['brand_name']); ?>? This cannot be undone if no products are linked.')"
                                                       title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">No brands found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="addBrandModal" tabindex="-1" aria-labelledby="addBrandModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="add-brand.php">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addBrandModalLabel">Add New Brand</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="brand_name" class="form-label">Brand Name *</label>
                            <input type="text" class="form-control" id="brand_name" name="brand_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Brand</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editBrandModal" tabindex="-1" aria-labelledby="editBrandModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="edit-brand.php">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="editBrandModalLabel">Edit Brand</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="brand_id" id="edit_brand_id">
                        <div class="mb-3">
                            <label for="edit_brand_name" class="form-label">Brand Name *</label>
                            <input type="text" class="form-control" id="edit_brand_name" name="brand_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning"><i class="bi bi-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
    // Script to pass data to the Edit Brand Modal
    $(document).ready(function() {
        $('.edit-brand-btn').on('click', function() {
            var brandId = $(this).data('id');
            var brandName = $(this).data('name');
            
            // Populate the modal fields
            $('#edit_brand_id').val(brandId);
            $('#edit_brand_name').val(brandName);
        });
    });
    </script>
</body>
</html>