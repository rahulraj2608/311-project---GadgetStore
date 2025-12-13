<?php
// Note: We assume $categories, isLoggedIn(), isAdmin(), and getSubcategories() 
// are already defined by the includes in cart.php (which they are, via '../includes/...')

// ----------------------------------------------------
// DYNAMIC VARIABLES FOR THE NAVBAR
// ----------------------------------------------------
// We need to fetch the category data again if it wasn't fetched in the parent script,
// but since the parent script (cart.php) uses '../includes/functions.php' and 
// '../includes/db_connect.php', we can assume the necessary variables are available.

// To ensure category data is available if the navbar is used independently, 
// you might want to move category fetching logic to a centralized header file.
// For now, we'll assume $categories and $pdo are available due to cart.php's includes.

// Define $search here, in case the parent file didn't.
// This is not strictly necessary for cart.php, but good for general use.
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; 
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="../index.php">Gadget Store</a> 
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../index.php">Home</a> 
                </li>
                <?php 
                // Ensure $categories is defined before looping (check is essential in shared files)
                if (isset($categories) && is_array($categories)): 
                    foreach ($categories as $cat): 
                ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <?php echo $cat['category_name']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <?php 
                        // Assuming getSubcategories($pdo, $category_id) is defined via includes
                        $subcategories = getSubcategories($pdo, $cat['category_id']);
                        foreach ($subcategories as $sub): ?>
                        <li>
                            <a class="dropdown-item" href="../index.php?category=<?php echo $cat['category_id']; ?>&subcategory=<?php echo $sub['sub_category_id']; ?>">
                                <?php echo $sub['sub_category_name']; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php 
                    endforeach; 
                endif; 
                ?>
            </ul>
            
            <form class="d-flex me-3" method="GET" action="../index.php">
                <input class="form-control me-2" type="search" name="search" placeholder="Search products..." value="<?php echo $search; ?>">
                <button class="btn btn-outline-light" type="submit">Search</button>
            </form>
            
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/dashboard.php">
                                <i class="bi bi-speedometer2"></i> Admin Dashboard
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="cart.php"> 
                            <i class="bi bi-cart"></i> Cart
                        </a>
                    </li>
                    <li class="nav-item">
                         <a class="nav-link" href="myorders.php">
                            <i class="bi bi-box"></i> My Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>