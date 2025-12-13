<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin and if the request method is POST
if (!isLoggedIn() || !isAdmin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // If not admin or not a POST request, redirect with an error message
    $_SESSION['error'] = "Unauthorized access or invalid request method.";
    redirect('brands.php');
}

// Check if the brand name was submitted
if (isset($_POST['brand_name'])) {
    // Sanitize and trim the brand name input
    $brand_name = trim(sanitize($_POST['brand_name']));

    if (empty($brand_name)) {
        $_SESSION['error'] = "Brand name cannot be empty. Please enter a name.";
        redirect('brands.php');
    }

    try {
        // 1. Check if a brand with this name already exists (Case-insensitive check is often preferred)
        $check_stmt = $pdo->prepare("SELECT brand_id FROM brand WHERE brand_name = ?");
        $check_stmt->execute([$brand_name]);

        if ($check_stmt->fetch()) {
            $_SESSION['error'] = "Brand '{$brand_name}' already exists. Please choose a different name.";
        } else {
            // 2. Insert the new brand into the database
            $insert_stmt = $pdo->prepare("INSERT INTO brand (brand_name) VALUES (?)");
            $success = $insert_stmt->execute([$brand_name]);
            
            if ($success && $insert_stmt->rowCount() > 0) {
                $_SESSION['success'] = "Brand '{$brand_name}' successfully added!";
            } else {
                $_SESSION['error'] = "Failed to add brand: Database insertion failed.";
            }
        }
    } catch (PDOException $e) {
        // In a real application, you would log $e->getMessage() for debugging.
        $_SESSION['error'] = "A critical error occurred while accessing the database. Please try again.";
    }
} else {
    $_SESSION['error'] = "Invalid form submission: Brand name field is missing.";
}

// Redirect back to the brand management page regardless of outcome
redirect('brands.php');
?>