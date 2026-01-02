<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// 1. GET PRODUCT ID AND FETCH EXISTING DATA
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id === 0) { redirect('products.php'); }

$stmt = $pdo->prepare("SELECT * FROM product WHERE product_id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) { redirect('products.php'); }

// Fetch existing images
$img_stmt = $pdo->prepare("SELECT * FROM product_image WHERE product_id = ?");
$img_stmt->execute([$product_id]);
$existing_images = $img_stmt->fetchAll();

// Get data for dropdowns
$categories = getCategories($pdo);
$brands = getBrands($pdo);
$suppliers = $pdo->query("SELECT * FROM supplier ORDER BY supplier_name")->fetchAll();

// 2. HANDLE FORM SUBMISSION (UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = sanitize($_POST['product_name']);
    $price = (float)$_POST['price'];
    $stock_quantity = (int)$_POST['stock_quantity'];
    $brand_id = (int)$_POST['brand_id'];
    $category_id = (int)$_POST['category_id'];
    $supplier_id = (int)$_POST['supplier_id'];
    $description = sanitize($_POST['description']);

    // Update product details
    $update_stmt = $pdo->prepare("UPDATE product SET product_name = ?, price = ?, stock_quantity = ?, brand_id = ?, category_id = ?, supplier_id = ?, description = ? WHERE product_id = ?");
    $update_stmt->execute([$product_name, $price, $stock_quantity, $brand_id, $category_id, $supplier_id, $description, $product_id]);

    // Handle New Image Uploads
    if (isset($_FILES['upload_images']) && !empty($_FILES['upload_images']['name'][0])) {
        $upload_dir = '../uploads/products/';
        foreach ($_FILES['upload_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['upload_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = uniqid() . '_' . basename($_FILES['upload_images']['name'][$key]);
                if (move_uploaded_file($tmp_name, $upload_dir . $file_name)) {
                    $image_url = 'localhost/ge/uploads/products/' . $file_name;
                    $pdo->prepare("INSERT INTO product_image (product_id, image_url, is_primary) VALUES (?, ?, 0)")
                        ->execute([$product_id, $image_url]);
                }
            }
        }
    }

    // Handle New Image URLs
    if (!empty($_POST['image_urls'])) {
        $urls = explode("\n", trim($_POST['image_urls']));
        foreach ($urls as $url) {
            $url = trim($url);
            if (!empty($url) && (filter_var($url, FILTER_VALIDATE_URL) || strpos($url, 'http') === 0)) {
                $pdo->prepare("INSERT INTO product_image (product_id, image_url, is_primary) VALUES (?, ?, 0)")
                    ->execute([$product_id, $url]);
            }
        }
    }

    $_SESSION['success'] = "Product updated successfully!";
    header("Location: edit-product.php?id=" . $product_id);
    exit();
}

// 3. HANDLE IMAGE DELETION (AJAX or GET)
if (isset($_GET['delete_image'])) {
    $img_id = (int)$_GET['delete_image'];
    $pdo->prepare("DELETE FROM product_image WHERE image_id = ? AND product_id = ?")->execute([$img_id, $product_id]);
    header("Location: edit-product.php?id=" . $product_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .main-content { padding: 20px; }
        .current-images img { width: 100px; height: 100px; object-fit: cover; border-radius: 5px; }
        .image-card { position: relative; display: inline-block; margin-right: 10px; }
        .delete-img-btn { position: absolute; top: -5px; right: -5px; padding: 2px 6px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-nav.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="card mx-auto" style="max-width: 800px;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Edit Product: <?php echo htmlspecialchars($product['product_name']); ?></h4>
                        <a href="products.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Product Name *</label>
                                <input type="text" class="form-control" name="product_name" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Price ($) *</label>
                                    <input type="number" step="0.01" class="form-control" name="price" value="<?php echo $product['price']; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Stock Quantity *</label>
                                    <input type="number" class="form-control" name="stock_quantity" value="<?php echo $product['stock_quantity']; ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Brand</label>
                                    <select class="form-select" name="brand_id">
                                        <?php foreach ($brands as $brand): ?>
                                            <option value="<?php echo $brand['brand_id']; ?>" <?php echo ($brand['brand_id'] == $product['brand_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($brand['brand_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" name="category_id">
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['category_id']; ?>" <?php echo ($cat['category_id'] == $product['category_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Supplier</label>
                                    <select class="form-select" name="supplier_id">
                                        <?php foreach ($suppliers as $sup): ?>
                                            <option value="<?php echo $sup['supplier_id']; ?>" <?php echo ($sup['supplier_id'] == $product['supplier_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($sup['supplier_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($product['description']); ?></textarea>
                            </div>

                            <hr>
                            <h5>Current Images</h5>
                            <div class="current-images mb-3">
                                <?php foreach ($existing_images as $img): ?>
                                    <div class="image-card">
                                        <img src="<?php echo (strpos($img['image_url'], 'http') === 0) ? $img['image_url'] : '../' . $img['image_url']; ?>" alt="Product">
                                        <a href="?id=<?php echo $product_id; ?>&delete_image=<?php echo $img['image_id']; ?>" 
                                           class="btn btn-danger btn-sm delete-img-btn" 
                                           onclick="return confirm('Delete this image?')">
                                            <i class="bi bi-x"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Add New Images (Upload)</label>
                                <input type="file" class="form-control" name="upload_images[]" multiple accept="image/*">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Add New Images (URLs - one per line)</label>
                                <textarea class="form-control" name="image_urls" rows="2"></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">Update Product</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>