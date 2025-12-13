<?php
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="col-md-3 col-lg-2 d-md-block sidebar bg-dark">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <div class="text-white">
                <h5 class="mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?></h5>
                <small class="text-muted"><?php echo htmlspecialchars($_SESSION['email'] ?? 'admin'); ?></small>
            </div>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            
            <!-- Products Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['products.php', 'add-product.php', 'edit-product.php']) ? 'active' : ''; ?>" 
                   href="products.php">
                    <i class="bi bi-box me-2"></i> Products
                </a>
            </li>
            
            <!-- Categories Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'categories.php' ? 'active' : ''; ?>" 
                   href="categories.php">
                    <i class="bi bi-tags me-2"></i> Categories
                </a>
            </li>
            
            <!-- Orders Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'orders.php' ? 'active' : ''; ?>" 
                   href="orders.php">
                    <i class="bi bi-cart me-2"></i> Orders
                </a>
            </li>
            
            <!-- Customers Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'customers.php' ? 'active' : ''; ?>" 
                   href="customers.php">
                    <i class="bi bi-people me-2"></i> Customers
                </a>
            </li>
            
            <!-- Brands Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'brands.php' ? 'active' : ''; ?>" 
                   href="brands.php">
                    <i class="bi bi-shop me-2"></i> Brands
                </a>
            </li>
            
            <!-- Suppliers Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'suppliers.php' ? 'active' : ''; ?>" 
                   href="suppliers.php">
                    <i class="bi bi-truck me-2"></i> Suppliers
                </a>
            </li>
            
            <!-- Discounts Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'discounts.php' ? 'active' : ''; ?>" 
                   href="discounts.php">
                    <i class="bi bi-percent me-2"></i> Discounts
                </a>
            </li>
            
            <hr class="border-secondary my-3">
            
            <!-- Store Front -->
            <li class="nav-item">
                <a class="nav-link" href="../index.php" target="_blank">
                    <i class="bi bi-shop me-2"></i> View Store
                </a>
            </li>
            
            <!-- Logout -->
            <li class="nav-item mt-4">
                <a class="nav-link text-danger" href="../logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</nav>