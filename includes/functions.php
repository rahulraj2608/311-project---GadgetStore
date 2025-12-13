<?php
/**
 * Check if user is logged in
 */
function displaySessionMessages() {
    // Start session if not already started (optional, but safer)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check for success message
    if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); 
    endif; 
    
    // Check for error message
    if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); 
    endif;
}
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Get all categories
 */
function getCategories($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM category ORDER BY category_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting categories: " . $e->getMessage());
        return [];
    }
}

/**
 * Get subcategories
 */
function getSubcategories($pdo, $category_id = null) {
    try {
        if ($category_id) {
            $stmt = $pdo->prepare("SELECT * FROM sub_category WHERE category_id = ? ORDER BY sub_category_name");
            $stmt->execute([$category_id]);
        } else {
            $stmt = $pdo->query("SELECT * FROM sub_category ORDER BY sub_category_name");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting subcategories: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all brands
 */
function getBrands($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM brand ORDER BY brand_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting brands: " . $e->getMessage());
        return [];
    }
}

/**
 * Display error message
 */
function displayError($message) {
    return '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

/**
 * Display success message
 */
function displaySuccess($message) {
    return '<div class="alert alert-success alert-dismissible fade show" role="alert">
                ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

/**
 * Get product images
 */
function getProductImages($pdo, $product_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM product_image WHERE product_id = ? ORDER BY image_id");
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting product images: " . $e->getMessage());
        return [];
    }
}

/**
 * Generate star rating HTML
 */
function generateStarRating($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<i class="bi bi-star-fill text-warning"></i>';
        } elseif ($i - 0.5 <= $rating) {
            $html .= '<i class="bi bi-star-half text-warning"></i>';
        } else {
            $html .= '<i class="bi bi-star text-warning"></i>';
        }
    }
    return $html;
}

/**
 * Format price
 */
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

/**
 * Check if product is in stock
 */
function isInStock($quantity) {
    return $quantity > 0;
}

/**
 * Get stock status badge
 */
function getStockBadge($quantity) {
    if ($quantity > 10) {
        return '<span class="badge bg-success">In Stock</span>';
    } elseif ($quantity > 0) {
        return '<span class="badge bg-warning">Low Stock</span>';
    } else {
        return '<span class="badge bg-danger">Out of Stock</span>';
    }
}

/**
 * Add item to cart
 */
function addToCart($pdo, $customer_id, $product_id, $quantity = 1) {
    try {
        // Check if already in cart
        $check_stmt = $pdo->prepare("SELECT * FROM cart WHERE customer_id = ? AND product_id = ?");
        $check_stmt->execute([$customer_id, $product_id]);
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            // Update quantity
            $update_stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE cart_id = ?");
            return $update_stmt->execute([$quantity, $existing['cart_id']]);
        } else {
            // Insert new
            $insert_stmt = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, ?)");
            return $insert_stmt->execute([$customer_id, $product_id, $quantity]);
        }
    } catch (PDOException $e) {
        error_log("Error adding to cart: " . $e->getMessage());
        return false;
    }
}
?>