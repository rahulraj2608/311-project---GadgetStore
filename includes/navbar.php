<?php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__ . '/../'); // Adjust: includes/.. = project root
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '/ge/'); // your project URL path
}
require_once ROOT_PATH . 'config.php';
require_once ROOT_PATH . 'db_connect.php';
require_once ROOT_PATH . 'functions.php';

if (!isset($categories)) {
    $categories = getCategories($pdo);
}
if (!isset($search)) {
    $search = '';
}

function url($path) {
    return BASE_URL . ltrim($path, '/');
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?php echo url('index.php'); ?>">Gadget Store</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo url('index.php'); ?>">Home</a>
                </li>
                <?php foreach ($categories as $cat): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach (getSubcategories($pdo, $cat['category_id']) as $sub): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo url("index.php?category={$cat['category_id']}&subcategory={$sub['sub_category_id']}"); ?>">
                                        <?php echo htmlspecialchars($sub['sub_category_name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form class="d-flex me-3" method="GET" action="<?php echo url('index.php'); ?>">
                <input class="form-control me-2" type="search" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-light" type="submit">Search</button>
            </form>

            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo url('admin/dashboard.php'); ?>">Admin Dashboard</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo url('customer/cart.php'); ?>">Cart</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo url('customer/myorders.php'); ?>">My Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo url('logout.php'); ?>">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo url('login.php'); ?>">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo url('register.php'); ?>">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
