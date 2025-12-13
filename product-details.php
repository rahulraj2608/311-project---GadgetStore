<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$product_id = (int)$_GET['id'];

// Fetch product details with related information
$sql = "SELECT p.*, 
               b.brand_name, 
               c.category_name,
               s.supplier_name,
               s.email as supplier_email,
               s.phone_number as supplier_phone
        FROM product p
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        LEFT JOIN category c ON p.category_id = c.category_id
        LEFT JOIN supplier s ON p.supplier_id = s.supplier_id
        WHERE p.product_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$product_id]);
$product = $stmt->fetch();

// If product not found, redirect to home
if (!$product) {
    header("Location: index.php");
    exit();
}

// Fetch product images
$img_stmt = $pdo->prepare("SELECT * FROM product_image WHERE product_id = ? ORDER BY image_id");
$img_stmt->execute([$product_id]);
$images = $img_stmt->fetchAll();

// If no images, add a placeholder
if (empty($images)) {
    $images = [['image_url' => 'https://via.placeholder.com/600x400?text=No+Image+Available', 'is_primary' => 1]];
}

// Fetch product reviews with customer info
$review_sql = "SELECT r.*, c.first_name, c.last_name 
               FROM review r 
               LEFT JOIN customer c ON r.customer_id = c.customer_id 
               WHERE r.product_id = ? 
               ORDER BY r.review_date DESC";
$review_stmt = $pdo->prepare($review_sql);
$review_stmt->execute([$product_id]);
$reviews = $review_stmt->fetchAll();

// Calculate average rating
$avg_rating = 0;
if (!empty($reviews)) {
    $total_rating = 0;
    foreach ($reviews as $review) {
        $total_rating += $review['rating'];
    }
    $avg_rating = round($total_rating / count($reviews), 1);
}

// Fetch related products (same category, different product)
$related_sql = "SELECT p.*, b.brand_name, pi.image_url 
                FROM product p 
                LEFT JOIN brand b ON p.brand_id = b.brand_id 
                LEFT JOIN (SELECT product_id, MIN(image_url) as image_url FROM product_image GROUP BY product_id) pi 
                ON p.product_id = pi.product_id 
                WHERE p.category_id = ? AND p.product_id != ? 
                LIMIT 4";
$related_stmt = $pdo->prepare($related_sql);
$related_stmt->execute([$product['category_id'], $product_id]);
$related_products = $related_stmt->fetchAll();

// Handle add to cart if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart']) && isLoggedIn() && !isAdmin()) {
    $quantity = (int)$_POST['quantity'];
    $customer_id = $_SESSION['user_id'];
    
    // Check if item already in cart
    $check_stmt = $pdo->prepare("SELECT * FROM cart WHERE customer_id = ? AND product_id = ?");
    $check_stmt->execute([$customer_id, $product_id]);
    $existing_item = $check_stmt->fetch();
    
    if ($existing_item) {
        // Update quantity
        $new_quantity = $existing_item['quantity'] + $quantity;
        $update_stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
        $update_stmt->execute([$new_quantity, $existing_item['cart_id']]);
    } else {
        // Add new item
        $insert_stmt = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, ?)");
        $insert_stmt->execute([$customer_id, $product_id, $quantity]);
    }
    
    // Redirect to cart with success message
    $_SESSION['success'] = "Product added to cart successfully!";
    header("Location: customer/cart.php");
    exit();
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && isLoggedIn() && !isAdmin()) {
    $rating = (int)$_POST['rating'];
    $comment = sanitize($_POST['comment']);
    $customer_id = $_SESSION['user_id'];
    
    // Check if customer has already reviewed this product
    $check_review = $pdo->prepare("SELECT * FROM review WHERE customer_id = ? AND product_id = ?");
    $check_review->execute([$customer_id, $product_id]);
    
    if (!$check_review->fetch()) {
        // Insert new review
        $review_insert = $pdo->prepare("INSERT INTO review (customer_id, product_id, rating, comment, review_date) 
                                       VALUES (?, ?, ?, ?, NOW())");
        $review_insert->execute([$customer_id, $product_id, $rating, $comment]);
        $_SESSION['success'] = "Review submitted successfully!";
        header("Location: product-details.php?id=" . $product_id);
        exit();
    } else {
        $_SESSION['error'] = "You have already reviewed this product.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - Gadget Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .product-gallery {
            position: relative;
        }
        .main-image {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        .main-image img {
            width: 100%;
            height: 400px;
            object-fit: contain;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .thumbnail-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .thumbnail {
            width: 80px;
            height: 80px;
            border: 2px solid transparent;
            border-radius: 5px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .thumbnail:hover, .thumbnail.active {
            border-color: #007bff;
        }
        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-price {
            font-size: 1.8rem;
            color: #dc3545;
            font-weight: 700;
        }
        .stock-badge {
            font-size: 0.9rem;
            padding: 0.5em 1em;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
        }
        .review-card {
            border-left: 4px solid #007bff;
            background-color: #f8f9fa;
        }
        .specs-table td {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .specs-table tr:last-child td {
            border-bottom: none;
        }
        .related-product-card {
            transition: transform 0.3s;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        .related-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .related-img {
            height: 150px;
            object-fit: contain;
            padding: 10px;
            background-color: #f8f9fa;
        }
        .quantity-input {
            width: 100px;
        }
        .breadcrumb {
            background-color: transparent;
            padding: 0;
        }
        .breadcrumb-item a {
            text-decoration: none;
            color: #6c757d;
        }
        .breadcrumb-item.active {
            color: #495057;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Gadget Store</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            Categories
                        </a>
                        <ul class="dropdown-menu">
                            <?php
                            $categories = getCategories($pdo);
                            foreach ($categories as $cat):
                            ?>
                            <li><a class="dropdown-item" href="index.php?category=<?php echo $cat['category_id']; ?>">
                                <?php echo $cat['category_name']; ?>
                            </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                </ul>
                
                <!-- User Menu -->
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin/dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Admin
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="customer/cart.php">
                                    <i class="bi bi-cart"></i> Cart
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="customer/orders.php">
                                    <i class="bi bi-box"></i> Orders
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="container mt-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php?category=<?php echo $product['category_id']; ?>">
                    <?php echo htmlspecialchars($product['category_name']); ?>
                </a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['product_name']); ?></li>
            </ol>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="container mt-4">
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
            <!-- Product Images Gallery -->
            <div class="col-lg-6 mb-4">
                <div class="product-gallery">
                    <div class="main-image">
                        <img id="mainProductImage" src="<?php echo $images[0]['image_url']; ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    </div>
                    
                    <?php if (count($images) > 1): ?>
                    <div class="thumbnail-container">
                        <?php foreach ($images as $index => $image): ?>
                        <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                             onclick="changeImage('<?php echo htmlspecialchars($image['image_url']); ?>', this)">
                            <img src="<?php echo htmlspecialchars($image['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Details -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <!-- Product Header -->
                        <div class="mb-3">
                            <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($product['brand_name']); ?></span>
                            <h1 class="h2 mb-2"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                            
                            <!-- Rating -->
                            <div class="d-flex align-items-center mb-3">
                                <div class="rating-stars me-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= $avg_rating ? '-fill' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-muted me-3"><?php echo $avg_rating; ?> (<?php echo count($reviews); ?> reviews)</span>
                                <?php if ($product['stock_quantity'] > 0): ?>
                                    <span class="badge bg-success stock-badge">In Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-danger stock-badge">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Price -->
                        <div class="mb-4">
                            <span class="product-price">$<?php echo number_format($product['price'], 2); ?></span>
                        </div>
                        
                        <!-- Product Description -->
                        <?php if (!empty($product['description'])): ?>
                        <div class="mb-4">
                            <h5 class="mb-2">Description</h5>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Specifications -->
                        <div class="mb-4">
                            <h5 class="mb-3">Specifications</h5>
                            <table class="table specs-table">
                                <tbody>
                                    <tr>
                                        <td><strong>Brand</strong></td>
                                        <td><?php echo htmlspecialchars($product['brand_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Category</strong></td>
                                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Supplier</strong></td>
                                        <td><?php echo htmlspecialchars($product['supplier_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Stock Available</strong></td>
                                        <td><?php echo $product['stock_quantity']; ?> units</td>
                                    </tr>
                                    <?php if ($product['supplier_email']): ?>
                                    <tr>
                                        <td><strong>Supplier Contact</strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($product['supplier_email']); ?>
                                            <?php if ($product['supplier_phone']): ?>
                                                <br><small class="text-muted">Phone: <?php echo htmlspecialchars($product['supplier_phone']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Add to Cart Form -->
                        <?php if (isLoggedIn() && !isAdmin() && $product['stock_quantity'] > 0): ?>
                        <form method="POST" action="" class="mb-4">
                            <div class="row g-3 align-items-center">
                                <div class="col-auto">
                                    <label for="quantity" class="form-label"><strong>Quantity:</strong></label>
                                </div>
                                <div class="col-auto">
                                    <input type="number" name="quantity" id="quantity" 
                                           class="form-control quantity-input" 
                                           value="1" min="1" max="<?php echo min($product['stock_quantity'], 10); ?>">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" name="add_to_cart" class="btn btn-primary btn-lg">
                                        <i class="bi bi-cart-plus"></i> Add to Cart
                                    </button>
                                </div>
                            </div>
                        </form>
                        <?php elseif (!isLoggedIn()): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Please <a href="login.php">login</a> to add items to cart.
                        </div>
                        <?php elseif ($product['stock_quantity'] <= 0): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> This product is currently out of stock.
                        </div>
                        <?php endif; ?>
                        
                        <!-- Share Buttons -->
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="mb-2">Share this product:</h6>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="shareProduct('facebook')">
                                    <i class="bi bi-facebook"></i> Facebook
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="shareProduct('twitter')">
                                    <i class="bi bi-twitter"></i> Twitter
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="shareProduct('whatsapp')">
                                    <i class="bi bi-whatsapp"></i> WhatsApp
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Reviews Section -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Customer Reviews</h5>
                    </div>
                    <div class="card-body">
                        <!-- Average Rating -->
                        <div class="row mb-4">
                            <div class="col-md-4 text-center">
                                <div class="display-4 fw-bold text-primary"><?php echo $avg_rating; ?></div>
                                <div class="rating-stars mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= $avg_rating ? '-fill' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-muted">Based on <?php echo count($reviews); ?> reviews</p>
                            </div>
                            
                            <!-- Add Review Form -->
                            <?php if (isLoggedIn() && !isAdmin()): ?>
                            <div class="col-md-8">
                                <h6>Write a Review</h6>
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label class="form-label">Rating</label>
                                        <div class="rating-input mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <button type="button" class="btn btn-outline-warning btn-sm rating-star" 
                                                    data-rating="<?php echo $i; ?>" 
                                                    onmouseover="hoverStars(<?php echo $i; ?>)" 
                                                    onmouseout="resetStars()" 
                                                    onclick="setRating(<?php echo $i; ?>)">
                                                <i class="bi bi-star"></i>
                                            </button>
                                            <?php endfor; ?>
                                            <input type="hidden" name="rating" id="selectedRating" value="5" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="comment" class="form-label">Your Review</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="3" 
                                                  placeholder="Share your experience with this product..." required></textarea>
                                    </div>
                                    <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Reviews List -->
                        <?php if (!empty($reviews)): ?>
                        <div class="reviews-list">
                            <h6 class="mb-3">Recent Reviews</h6>
                            <?php foreach ($reviews as $review): ?>
                            <div class="card review-card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong>
                                            <div class="rating-stars small">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('F j, Y', strtotime($review['review_date'])); ?>
                                        </small>
                                    </div>
                                    <?php if (!empty($review['comment'])): ?>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-chat-text display-1 text-muted"></i>
                            <h5 class="mt-3">No reviews yet</h5>
                            <p class="text-muted">Be the first to review this product!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="mb-4">Related Products</h3>
                <div class="row">
                    <?php foreach ($related_products as $related): ?>
                    <div class="col-md-3 col-sm-6 mb-4">
                        <div class="card related-product-card h-100">
                            <img src="<?php echo $related['image_url'] ?? 'https://via.placeholder.com/300x200?text=No+Image'; ?>" 
                                 class="card-img-top related-img" 
                                 alt="<?php echo htmlspecialchars($related['product_name']); ?>">
                            <div class="card-body">
                                <h6 class="card-title"><?php echo htmlspecialchars($related['product_name']); ?></h6>
                                <p class="card-text text-muted small"><?php echo htmlspecialchars($related['brand_name']); ?></p>
                                <p class="card-text fw-bold">$<?php echo number_format($related['price'], 2); ?></p>
                                <a href="product-details.php?id=<?php echo $related['product_id']; ?>" 
                                   class="btn btn-outline-primary btn-sm w-100">View Details</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Gadget Store</h5>
                    <p class="text-muted">Your one-stop shop for the latest gadgets and electronics.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Gadget Store. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image gallery functionality
        function changeImage(imageUrl, element) {
            // Update main image
            document.getElementById('mainProductImage').src = imageUrl;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            element.classList.add('active');
        }
        
        // Rating system
        let currentRating = 5;
        let stars = document.querySelectorAll('.rating-star');
        
        function hoverStars(rating) {
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.querySelector('i').className = 'bi bi-star-fill text-warning';
                } else {
                    star.querySelector('i').className = 'bi bi-star text-warning';
                }
            });
        }
        
        function resetStars() {
            stars.forEach((star, index) => {
                if (index < currentRating) {
                    star.querySelector('i').className = 'bi bi-star-fill text-warning';
                } else {
                    star.querySelector('i').className = 'bi bi-star text-warning';
                }
            });
        }
        
        function setRating(rating) {
            currentRating = rating;
            document.getElementById('selectedRating').value = rating;
            resetStars();
        }
        
        // Share product
        function shareProduct(platform) {
            const productName = "<?php echo addslashes($product['product_name']); ?>";
            const productUrl = window.location.href;
            const text = `Check out ${productName} at Gadget Store!`;
            
            let shareUrl = '';
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(productUrl)}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(productUrl)}`;
                    break;
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${encodeURIComponent(text + ' ' + productUrl)}`;
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }
        
        // Quantity validation
        document.getElementById('quantity')?.addEventListener('change', function() {
            const max = parseInt(this.max);
            const min = parseInt(this.min);
            let value = parseInt(this.value);
            
            if (isNaN(value)) value = min;
            if (value < min) value = min;
            if (value > max) value = max;
            
            this.value = value;
        });
    </script>
</body>
</html>