<?php
// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin and if method is POST
if (!isLoggedIn() || !isAdmin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../login.php');
}

// Ensure an action is specified
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Function to validate and sanitize common fields
function validateDiscountData() {
    $errors = [];
    $data = [];

    // 1. Validate Discount Code
    $data['discount_code'] = isset($_POST['discount_code']) ? strtoupper(trim(sanitize($_POST['discount_code']))) : '';
    if (empty($data['discount_code'])) {
        $errors[] = "Discount Code is required.";
    }

    // 2. Validate Type and Value
    $data['type'] = isset($_POST['type']) ? sanitize($_POST['type']) : '';
    $data['value'] = isset($_POST['value']) ? floatval($_POST['value']) : 0;
    
    if ($data['type'] !== 'percentage' && $data['type'] !== 'fixed_amount') {
        $errors[] = "Invalid discount type selected.";
    }
    if ($data['value'] <= 0) {
        $errors[] = "Discount value must be greater than zero.";
    }
    if ($data['type'] === 'percentage' && $data['value'] > 100) {
        $errors[] = "Percentage discount cannot exceed 100%.";
    }

    // 3. Validate Applicable To and Category ID
    $data['applicable_to'] = isset($_POST['applicable_to']) ? sanitize($_POST['applicable_to']) : 'all';
    $data['category_id'] = null; // Default to null for database
    
    if ($data['applicable_to'] === 'category') {
        $cat_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        if ($cat_id <= 0) {
            $errors[] = "A specific category must be selected for category discounts.";
        } else {
            $data['category_id'] = $cat_id;
        }
    } else {
         // If not 'category', ensure category_id is null/ignored
         $data['category_id'] = null;
    }
    
    // 4. Validate Dates
    $data['start_date'] = isset($_POST['start_date']) ? sanitize($_POST['start_date']) : '';
    $data['expiry_date'] = isset($_POST['expiry_date']) ? sanitize($_POST['expiry_date']) : '';

    if (empty($data['start_date']) || empty($data['expiry_date'])) {
        $errors[] = "Both Start Date and Expiry Date are required.";
    } elseif (strtotime($data['start_date']) > strtotime($data['expiry_date'])) {
        $errors[] = "Start Date cannot be after the Expiry Date.";
    }

    return ['data' => $data, 'errors' => $errors];
}


// ------------------------------
// HANDLE ADD/EDIT ACTIONS
// ------------------------------

if ($action === 'add') {
    $validation = validateDiscountData();
    
    if (!empty($validation['errors'])) {
        $_SESSION['error'] = "Validation Failed: " . implode('; ', $validation['errors']);
        redirect('discounts.php');
    }

    $data = $validation['data'];
    
    $sql = "INSERT INTO discount (discount_code, type, value, applicable_to, category_id, start_date, expiry_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['discount_code'],
            $data['type'],
            $data['value'],
            $data['applicable_to'],
            $data['category_id'],
            $data['start_date'],
            $data['expiry_date']
        ]);
        
        $_SESSION['success'] = "Discount code **{$data['discount_code']}** added successfully.";
        
    } catch (PDOException $e) {
        // Check for common errors like duplicate code constraint
        if ($e->getCode() === '23000') { // Integrity constraint violation (e.g., unique key)
            $_SESSION['error'] = "Error: Discount code **{$data['discount_code']}** already exists.";
        } else {
            // Log the error in production
            $_SESSION['error'] = "Failed to add discount. Database error.";
        }
    }
    
    redirect('discounts.php');

} elseif ($action === 'edit') {
    
    $discount_id = isset($_POST['discount_id']) ? (int)$_POST['discount_id'] : 0;
    
    if ($discount_id <= 0) {
        $_SESSION['error'] = "Invalid discount ID for editing.";
        redirect('discounts.php');
    }
    
    $validation = validateDiscountData();
    
    if (!empty($validation['errors'])) {
        $_SESSION['error'] = "Validation Failed: " . implode('; ', $validation['errors']);
        redirect('discounts.php');
    }

    $data = $validation['data'];
    
    $sql = "UPDATE discount SET 
            discount_code = ?, 
            type = ?, 
            value = ?, 
            applicable_to = ?, 
            category_id = ?, 
            start_date = ?, 
            expiry_date = ?
            WHERE discount_id = ?";
            
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['discount_code'],
            $data['type'],
            $data['value'],
            $data['applicable_to'],
            $data['category_id'],
            $data['start_date'],
            $data['expiry_date'],
            $discount_id // WHERE clause parameter
        ]);
        
        // Check if any rows were affected
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Discount code **{$data['discount_code']}** updated successfully.";
        } else {
            $_SESSION['error'] = "No changes were made to the discount or discount not found.";
        }
        
    } catch (PDOException $e) {
        // Log the error in production
        $_SESSION['error'] = "Failed to update discount. Database error.";
    }
    
    redirect('discounts.php');

} else {
    // Default action if no valid action is provided
    $_SESSION['error'] = "Invalid action specified.";
    redirect('discounts.php');
}
?>