<?php
// Database configuration
$host = '127.0.0.1';
$username = 'root';
$password = '';

try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL successfully.<br>";
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS gadget_store CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("USE gadget_store");
    
    echo "Database created/selected successfully.<br>";
    
    // Drop tables if they exist (for fresh setup)
    $tables = [
        'cart', 'review', 'product_image', 'order_item', 'payment', 'shipment',
        'order', 'discount', 'product', 'sub_category', 'category', 
        'brand', 'supplier', 'customer'
    ];
    
    foreach ($tables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        } catch (Exception $e) {
            // Table might not exist, continue
        }
    }
    echo "Cleaned up existing tables.<br>";
    
    // Create tables in correct order (to satisfy foreign key constraints)
    
    // 1. Brand table
    $pdo->exec("CREATE TABLE `brand` (
        `brand_id` int(11) NOT NULL AUTO_INCREMENT,
        `brand_name` varchar(100) NOT NULL,
        PRIMARY KEY (`brand_id`),
        UNIQUE KEY `brand_name` (`brand_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'brand' table.<br>";
    
    // 2. Category table
    $pdo->exec("CREATE TABLE `category` (
        `category_id` int(11) NOT NULL AUTO_INCREMENT,
        `category_name` varchar(100) NOT NULL,
        PRIMARY KEY (`category_id`),
        UNIQUE KEY `category_name` (`category_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'category' table.<br>";
    
    // 3. Supplier table
    $pdo->exec("CREATE TABLE `supplier` (
        `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
        `supplier_name` varchar(100) NOT NULL,
        `phone_number` varchar(11) DEFAULT NULL,
        `email` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`supplier_id`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'supplier' table.<br>";
    
    // 4. Customer table (with is_admin column)
    $pdo->exec("CREATE TABLE `customer` (
        `customer_id` int(11) NOT NULL AUTO_INCREMENT,
        `first_name` varchar(100) NOT NULL,
        `last_name` varchar(100) NOT NULL,
        `email` varchar(255) NOT NULL,
        `password` varchar(255) NOT NULL,
        `phone_number` varchar(11) DEFAULT NULL,
        `address` varchar(255) DEFAULT NULL,
        `is_admin` tinyint(1) DEFAULT 0,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`customer_id`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'customer' table.<br>";
    
    // 5. Product table
    $pdo->exec("CREATE TABLE `product` (
        `product_id` int(11) NOT NULL AUTO_INCREMENT,
        `product_name` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `price` decimal(10,2) NOT NULL,
        `stock_quantity` int(11) NOT NULL DEFAULT 0,
        `brand_id` int(11) NOT NULL,
        `category_id` int(11) NOT NULL,
        `supplier_id` int(11) NOT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`product_id`),
        UNIQUE KEY `uc_product_name` (`product_name`),
        KEY `brand_id` (`brand_id`),
        KEY `category_id` (`category_id`),
        KEY `supplier_id` (`supplier_id`),
        CONSTRAINT `product_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brand` (`brand_id`),
        CONSTRAINT `product_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`),
        CONSTRAINT `product_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'product' table.<br>";
    
    // 6. Sub_category table
    $pdo->exec("CREATE TABLE `sub_category` (
        `sub_category_id` int(11) NOT NULL AUTO_INCREMENT,
        `sub_category_name` varchar(100) NOT NULL,
        `category_id` int(11) NOT NULL,
        PRIMARY KEY (`sub_category_id`),
        UNIQUE KEY `sub_category_name` (`sub_category_name`),
        UNIQUE KEY `uc_category_sub_category` (`sub_category_name`,`category_id`),
        KEY `category_id` (`category_id`),
        CONSTRAINT `sub_category_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'sub_category' table.<br>";
    
    // 7. Product_image table
    $pdo->exec("CREATE TABLE `product_image` (
        `image_id` int(11) NOT NULL AUTO_INCREMENT,
        `product_id` int(11) NOT NULL,
        `image_url` varchar(500) NOT NULL,
        `is_primary` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`image_id`),
        KEY `product_id` (`product_id`),
        CONSTRAINT `product_image_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'product_image' table.<br>";
    
    // 8. Discount table
    $pdo->exec("CREATE TABLE `discount` (
        `discount_id` int(11) NOT NULL AUTO_INCREMENT,
        `discount_code` varchar(50) DEFAULT NULL,
        `type` enum('PERCENTAGE','FIXED_AMOUNT') NOT NULL,
        `value` decimal(5,2) NOT NULL,
        `applicable_to` varchar(50) NOT NULL,
        `category_id` int(11) DEFAULT NULL,
        `start_date` date NOT NULL,
        `expiry_date` date DEFAULT NULL,
        PRIMARY KEY (`discount_id`),
        UNIQUE KEY `discount_code` (`discount_code`),
        KEY `category_id` (`category_id`),
        CONSTRAINT `discount_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'discount' table.<br>";
    
    $pdo->exec("CREATE TABLE orders (
        `order_id` int(11) NOT NULL AUTO_INCREMENT,
        `customer_id` int(11) NOT NULL,
        `order_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `total_amount` decimal(10,2) NOT NULL,
        `order_status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
        `shipping_address` varchar(255) DEFAULT NULL,
        `payment_method` varchar(50) DEFAULT NULL,
        PRIMARY KEY (`order_id`),
        KEY `customer_id` (`customer_id`),
        CONSTRAINT `order_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'order' table.<br>";
    
    // 10. Order_item table
    $pdo->exec("CREATE TABLE `order_item` (
        `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
        `order_id` int(11) NOT NULL,
        `product_id` int(11) NOT NULL,
        `quantity` int(11) NOT NULL,
        `per_unit_price` decimal(10,2) NOT NULL,
        PRIMARY KEY (`order_item_id`),
        UNIQUE KEY `unique_order_product` (`order_id`,`product_id`),
        KEY `product_id` (`product_id`),
        CONSTRAINT `order_item_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES orders (`order_id`),
        CONSTRAINT `order_item_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'order_item' table.<br>";
    
    // 11. Payment table
    $pdo->exec("CREATE TABLE `payment` (
        `payment_id` int(11) NOT NULL AUTO_INCREMENT,
        `order_id` int(11) NOT NULL,
        `payment_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `payment_method` varchar(50) NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
        `transaction_id` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`payment_id`),
        KEY `order_id` (`order_id`),
        CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES orders (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'payment' table.<br>";
    
    // 12. Shipment table
    $pdo->exec("CREATE TABLE `shipment` (
        `shipment_id` int(11) NOT NULL AUTO_INCREMENT,
        `order_id` int(11) NOT NULL,
        `carrier` varchar(100) NOT NULL,
        `tracking_number` varchar(100) DEFAULT NULL,
        `shipping_date` date DEFAULT NULL,
        `delivery_date` date DEFAULT NULL,
        `shipping_cost` decimal(6,2) NOT NULL DEFAULT 0.00,
        `status` varchar(50) NOT NULL,
        PRIMARY KEY (`shipment_id`),
        KEY `order_id` (`order_id`),
        CONSTRAINT `shipment_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES orders (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'shipment' table.<br>";
    
    // 13. Review table
    $pdo->exec("CREATE TABLE `review` (
        `review_id` int(11) NOT NULL AUTO_INCREMENT,
        `customer_id` int(11) NOT NULL,
        `product_id` int(11) NOT NULL,
        `rating` tinyint(4) NOT NULL CHECK (rating >= 1 AND rating <= 5),
        `comment` text DEFAULT NULL,
        `review_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`review_id`),
        KEY `customer_id` (`customer_id`),
        KEY `product_id` (`product_id`),
        CONSTRAINT `review_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
        CONSTRAINT `review_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'review' table.<br>";
    
    // 14. Cart table
    $pdo->exec("CREATE TABLE `cart` (
        `cart_id` int(11) NOT NULL AUTO_INCREMENT,
        `customer_id` int(11) NOT NULL,
        `product_id` int(11) NOT NULL,
        `quantity` int(11) NOT NULL DEFAULT 1,
        `added_date` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`cart_id`),
        UNIQUE KEY `unique_cart_item` (`customer_id`, `product_id`),
        KEY `customer_id` (`customer_id`),
        KEY `product_id` (`product_id`),
        CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
        CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created 'cart' table.<br>";
    
    // Insert sample data
    
    // Insert brands
    $brands = ['Apple', 'Samsung', 'Sony', 'Dell', 'HP', 'Logitech', 'Microsoft', 'Lenovo'];
    foreach ($brands as $brand) {
        $pdo->prepare("INSERT IGNORE INTO brand (brand_name) VALUES (?)")->execute([$brand]);
    }
    echo "Inserted sample brands.<br>";
    
    // Insert categories
    $categories = ['Laptops', 'Smartphones', 'Tablets', 'Accessories', 'Gaming', 'Audio'];
    foreach ($categories as $category) {
        $pdo->prepare("INSERT IGNORE INTO category (category_name) VALUES (?)")->execute([$category]);
    }
    echo "Inserted sample categories.<br>";
    
    // Insert suppliers
    $suppliers = [
        ['Tech Distributors Inc.', '5551234567', 'sales@techdist.com'],
        ['Gadget Wholesale', '5559876543', 'orders@gadgetwholesale.com'],
        ['Global Electronics', '5554567890', 'contact@globalelec.com']
    ];
    foreach ($suppliers as $supplier) {
        $pdo->prepare("INSERT IGNORE INTO supplier (supplier_name, phone_number, email) VALUES (?, ?, ?)")
            ->execute($supplier);
    }
    echo "Inserted sample suppliers.<br>";
    
    // Insert sub-categories
    $subcategories = [
        ['Wireless', 4], ['Wired', 4], ['Mechanical', 4],
        ['Bluetooth', 6], ['Wired', 6], ['Noise Cancelling', 6],
        ['Gaming Laptops', 1], ['Business Laptops', 1], ['Ultrabooks', 1]
    ];
    foreach ($subcategories as $subcat) {
        $pdo->prepare("INSERT IGNORE INTO sub_category (sub_category_name, category_id) VALUES (?, ?)")
            ->execute([$subcat[0], $subcat[1]]);
    }
    echo "Inserted sample sub-categories.<br>";
    
    // Create admin user with hashed password
    $admin_password = 'admin123';
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO customer (first_name, last_name, email, password, is_admin) 
                           VALUES ('Admin', 'User', 'admin@gadgetstore.com', ?, 1)
                           ON DUPLICATE KEY UPDATE password = VALUES(password), is_admin = VALUES(is_admin)");
    $stmt->execute([$hashed_password]);
    echo "Created admin user.<br>";
    
    // Create a test customer
    $customer_password = password_hash('customer123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT IGNORE INTO customer (first_name, last_name, email, password) 
                   VALUES ('John', 'Doe', 'customer@example.com', ?)")
        ->execute([$customer_password]);
    echo "Created test customer.<br>";
    
    // Insert sample products
    $products = [
        ['MacBook Pro 14"', 'Apple laptop with M2 Pro chip', 1999.99, 10, 1, 1, 1],
        ['iPhone 15 Pro', 'Latest Apple smartphone', 999.99, 25, 1, 2, 1],
        ['Galaxy S23 Ultra', 'Samsung flagship phone', 1199.99, 15, 2, 2, 2],
        ['Sony WH-1000XM5', 'Noise cancelling headphones', 349.99, 30, 3, 6, 3],
        ['Logitech MX Master 3', 'Wireless mouse', 99.99, 50, 6, 4, 1],
        ['Dell XPS 13', 'Ultrabook laptop', 1299.99, 8, 4, 1, 2]
    ];
    
    foreach ($products as $product) {
        $pdo->prepare("INSERT INTO product (product_name, description, price, stock_quantity, brand_id, category_id, supplier_id) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute($product);
    }
    echo "Inserted sample products.<br>";
    
    // Insert product images
    $image_urls = [
        'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/mbp14-spacegray-select-202301?wid=904&hei=840&fmt=jpeg&qlt=90&.v=1671304673202',
        'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/iphone-15-pro-finish-select-202309-6-7inch?wid=5120&hei=2880&fmt=webp&qlt=70&.v=1693009279096',
        'https://images.samsung.com/is/image/samsung/p6pim/uk/2302/gallery/uk-galaxy-s23-s918-445072-sm-s918bzkdeub-534881013?$650_519_PNG$',
        'https://www.sony.com/image/abbcea6d19e3f21257a91eb6842cda15?fmt=pjpeg&wid=660&bgcolor=FFFFFF&bgc=FFFFFF',
        'https://resource.logitech.com/w_386,ar_1.0,c_limit,f_auto,q_auto,dpr_2.0/d_transparent.gif/content/dam/logitech/en/products/mice/mx-master-3s/gallery/mx-master-3s-mouse-top-view-grey.png?v=1',
        'https://i.dell.com/is/image/DellContent/content/dam/ss2/product-images/dell-client-products/notebooks/xps-notebooks/xps-13-9315/media-gallery/notebook-xps-9315-nt-blue-gallery-4.psd?fmt=png-alpha&pscan=auto&scl=1&hei=402&wid=548&qlt=100,1&resMode=sharp2&size=548,402&chrss=full'
    ];
    
    $product_count = count($products);
    for ($i = 1; $i <= $product_count; $i++) {
        $pdo->prepare("INSERT INTO product_image (product_id, image_url, is_primary) VALUES (?, ?, 1)")
            ->execute([$i, $image_urls[$i-1]]);
    }
    echo "Inserted product images.<br>";
    
    echo "<br><strong>Setup completed successfully!</strong><br><br>";
    echo "<h3>Login Credentials:</h3>";
    echo "<p><strong>Admin:</strong> admin@gadgetstore.com / admin123</p>";
    echo "<p><strong>Customer:</strong> customer@example.com / customer123</p>";
    echo "<br><a href='../index.php' class='btn btn-primary'>Go to Website</a>";
    
} catch(PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup Complete</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="alert alert-success">
            <h4>Database setup completed!</h4>
            <p>You can now access the website.</p>
        </div>
    </div>
</body>

</html>
