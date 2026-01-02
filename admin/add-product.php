<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Get data for dropdowns
$categories = getCategories($pdo);
$brands = getBrands($pdo);
$suppliers = $pdo->query("SELECT * FROM supplier ORDER BY supplier_name")->fetchAll();
$subcategories = getSubcategories($pdo);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = sanitize($_POST['product_name']);
    $price = (float)$_POST['price'];
    $stock_quantity = (int)$_POST['stock_quantity'];
    $brand_id = (int)$_POST['brand_id'];
    $category_id = (int)$_POST['category_id'];
    $supplier_id = (int)$_POST['supplier_id'];
    $description = sanitize($_POST['description']);
    $image_source = 'both'; // Always use both options
    
    // Insert product
    $stmt = $pdo->prepare("INSERT INTO product (product_name, price, stock_quantity, brand_id, category_id, supplier_id, description) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$product_name, $price, $stock_quantity, $brand_id, $category_id, $supplier_id, $description]);
    
    $product_id = $pdo->lastInsertId();
    
    // Initialize counter for setting primary image
    $image_counter = 0;
    
    // Handle images based on selected source
    if ($image_source === 'upload') {
        // Handle local file upload
        if (isset($_FILES['upload_images']) && !empty($_FILES['upload_images']['name'][0])) {
            $upload_dir = '../uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($_FILES['upload_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['upload_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = uniqid() . '_' . basename($_FILES['upload_images']['name'][$key]);
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $image_url = 'localhost/ge/uploads/products/' . $file_name;
                        $is_primary = ($image_counter === 0) ? 1 : 0;
                        
                        $img_stmt = $pdo->prepare("INSERT INTO product_image (product_id, image_url, is_primary) VALUES (?, ?, ?)");
                        $img_stmt->execute([$product_id, $image_url, $is_primary]);
                        $image_counter++;
                    }
                }
            }
        }
    } elseif ($image_source === 'url') {
        // Handle external URLs
        if (!empty($_POST['image_urls'])) {
            $urls = explode("\n", trim($_POST['image_urls']));
            foreach ($urls as $url) {
                $url = trim($url);
                if (!empty($url)) {
                    // Check if it's a valid URL or a relative path
                    if (filter_var($url, FILTER_VALIDATE_URL) || strpos($url, 'http') === 0) {
                        $is_primary = ($image_counter === 0) ? 1 : 0;
                        
                        $img_stmt = $pdo->prepare("INSERT INTO product_image (product_id, image_url, is_primary) VALUES (?, ?, ?)");
                        $img_stmt->execute([$product_id, $url, $is_primary]);
                        $image_counter++;
                    }
                }
            }
        }
    } elseif ($image_source === 'both') {
        // Handle both uploads and URLs
        
        // First process file uploads
        if (isset($_FILES['upload_images']) && !empty($_FILES['upload_images']['name'][0])) {
            $upload_dir = '../uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($_FILES['upload_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['upload_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = uniqid() . '_' . basename($_FILES['upload_images']['name'][$key]);
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $image_url = 'uploads/products/' . $file_name;
                        $is_primary = ($image_counter === 0) ? 1 : 0;
                        
                        $img_stmt = $pdo->prepare("INSERT INTO product_image (product_id, image_url, is_primary) VALUES (?, ?, ?)");
                        $img_stmt->execute([$product_id, $image_url, $is_primary]);
                        $image_counter++;
                    }
                }
            }
        }
        
        // Then process URLs
        if (!empty($_POST['image_urls'])) {
            $urls = explode("\n", trim($_POST['image_urls']));
            foreach ($urls as $url) {
                $url = trim($url);
                if (!empty($url)) {
                    if (filter_var($url, FILTER_VALIDATE_URL) || strpos($url, 'http') === 0) {
                        $is_primary = ($image_counter === 0) ? 1 : 0;
                        
                        $img_stmt = $pdo->prepare("INSERT INTO product_image (product_id, image_url, is_primary) VALUES (?, ?, ?)");
                        $img_stmt->execute([$product_id, $url, $is_primary]);
                        $image_counter++;
                    }
                }
            }
        }
    }
    
    $_SESSION['success'] = "Product added successfully!";
    redirect('products.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .image-source-section {

            margin-bottom: 15px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        .image-source-section.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include Admin Navigation -->
            <?php include '../includes/admin-nav.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="mb-0">Add New Product</h4>
                            </div>
                            <div class="card-body">
                                <?php if (isset($_SESSION['success'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="product_name" class="form-label">Product Name *</label>
                                        <input type="text" class="form-control" id="product_name" name="product_name" required>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="price" class="form-label">Price *</label>
                                            <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="stock_quantity" class="form-label">Stock Quantity *</label>
                                            <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="brand_id" class="form-label">Brand *</label>
                                            <select class="form-select" id="brand_id" name="brand_id" required>
                                                <option value="">Select Brand</option>
                                                <?php foreach ($brands as $brand): ?>
                                                <option value="<?php echo $brand['brand_id']; ?>"><?php echo htmlspecialchars($brand['brand_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="category_id" class="form-label">Category *</label>
                                            <select class="form-select" id="category_id" name="category_id" required>
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['category_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="supplier_id" class="form-label">Supplier *</label>
                                        <select class="form-select" id="supplier_id" name="supplier_id" required>
                                            <option value="">Select Supplier</option>
                                            <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo $supplier['supplier_id']; ?>"><?php echo htmlspecialchars($supplier['supplier_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>
                                    

                                    
                                    <!-- Both Section -->
                                    <div id="both_section" class="image-source-section">
                                        <div class="mb-3">
                                            <label for="upload_images_both" class="form-label">Upload Images</label>
                                            <input type="file" class="form-control" id="upload_images_both" name="upload_images[]" multiple accept="image/*">
                                            <small class="text-muted">Upload images from your computer</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="image_urls_both" class="form-label">Additional Image URLs</label>
                                            <textarea class="form-control" id="image_urls_both" name="image_urls" rows="3" placeholder="Enter additional image URLs (one per line)"></textarea>
                                            <small class="text-muted">You can add more images via URLs</small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">Add Product</button>
                                        <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html> 