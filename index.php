<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$subcategory_id = isset($_GET['subcategory']) ? (int)$_GET['subcategory'] : 0;
$brand_id = isset($_GET['brand']) ? (int)$_GET['brand'] : 0;
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 10000;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query
$sql = "SELECT p.*, b.brand_name, c.category_name, s.supplier_name 
        FROM product p 
        LEFT JOIN brand b ON p.brand_id = b.brand_id 
        LEFT JOIN category c ON p.category_id = c.category_id 
        LEFT JOIN supplier s ON p.supplier_id = s.supplier_id 
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_id > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}

if ($subcategory_id > 0) {
    $sql .= " AND p.subcategory_id = ?";
    $params[] = $subcategory_id;
}

if ($brand_id > 0) {
    $sql .= " AND p.brand_id = ?";
    $params[] = $brand_id;
}

if ($min_price > 0) {
    $sql .= " AND p.price >= ?";
    $params[] = $min_price;
}

if ($max_price < 10000) {
    $sql .= " AND p.price <= ?";
    $params[] = $max_price;
}

// Add sorting
switch ($sort) {
    case 'price_low':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'name':
        $sql .= " ORDER BY p.product_name ASC";
        break;
    default:
        $sql .= " ORDER BY p.product_id DESC";
}

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get filter data
$categories = getCategories($pdo);
$brands = getBrands($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gadget Store - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .product-card {
            transition: transform 0.3s;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .product-img {
            height: 200px;
            object-fit: contain;
            padding: 10px;
        }
        .cart-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
        }
        .filter-sidebar {
            position: sticky;
            top: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
 <?php
 require("navbar.php");
 ?>

    <div class="container mt-4">
        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="card filter-sidebar">
                    <div class="card-body">
                        <h5 class="card-title">Filters</h5>
                        <form method="GET" action="index.php">
                            <!-- Price Range -->
                            <div class="mb-3">
                                <label class="form-label">Price Range</label>
                                <div class="row">
                                    <div class="col">
                                        <input type="number" class="form-control" name="min_price" placeholder="Min" value="<?php echo $min_price; ?>">
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control" name="max_price" placeholder="Max" value="<?php echo $max_price; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Categories -->
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category">
                                    <option value="0">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" <?php echo $category_id == $cat['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo $cat['category_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Brands -->
                            <div class="mb-3">
                                <label class="form-label">Brand</label>
                                <select class="form-select" name="brand">
                                    <option value="0">All Brands</option>
                                    <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo $brand['brand_id']; ?>" <?php echo $brand_id == $brand['brand_id'] ? 'selected' : ''; ?>>
                                        <?php echo $brand['brand_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Sorting -->
                            <div class="mb-3">
                                <label class="form-label">Sort By</label>
                                <select class="form-select" name="sort">
                                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                            <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">Clear Filters</a>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="col-lg-9">
                <div class="row">
                    <?php if (empty($products)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">No products found. Try different filters.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card product-card h-100">
                                <?php
                                // Get product image
                                $img_stmt = $pdo->prepare("SELECT image_url FROM product_image WHERE product_id = ? LIMIT 1");
                                $img_stmt->execute([$product['product_id']]);
                                $image = $img_stmt->fetch();
                                ?>
                                <img src="<?php echo $image['image_url'] ?? 'https://via.placeholder.com/300x200?text=No+Image'; ?>" 
                                     class="card-img-top product-img" alt="<?php echo $product['product_name']; ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $product['product_name']; ?></h5>
                                    <p class="card-text text-muted"><?php echo $product['brand_name']; ?></p>
                                    <p class="card-text"><?php echo substr($product['description'] ?? 'No description available', 0, 100); ?>...</p>
                                    <p class="card-text"><strong>$<?php echo number_format($product['price'], 2); ?></strong></p>
                                    
                                    <?php if ($product['stock_quantity'] > 0): ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-white">
                                    <div class="d-flex justify-content-between">
                                        <a href="product-details.php?id=<?php echo $product['product_id']; ?>" 
                                           class="btn btn-outline-primary">View Details</a>
                                        <?php if (isLoggedIn() && !isAdmin() && $product['stock_quantity'] > 0): ?>
                                        <form action="customer/add-to-cart.php" method="POST" class="d-inline">
                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-cart-plus"></i> Add to Cart
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

   
</body>
</html>