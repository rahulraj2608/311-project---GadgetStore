<?php
// Note: Assumes functions.php includes: 
// 1. isLoggedIn(), isAdmin(), redirect(), sanitize()
// 2. The displaySessionMessages() function we defined earlier.
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// ------------------------------
// 1. DATA FETCHING AND PROCESSING
// ------------------------------

// Fetch categories for the Add/Edit modals
try {
    $cat_stmt = $pdo->query("SELECT category_id, category_name FROM category ORDER BY category_name");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error if categories cannot be fetched
    $categories = [];
    error_log("Error fetching categories: " . $e->getMessage());
}

// Handle search and filtering
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sql = "SELECT d.*, c.category_name 
        FROM discount d
        LEFT JOIN category c ON d.category_id = c.category_id
        WHERE 1=1";
$params = [];

if ($search) {
    // Search by discount code, type, or category name
    $sql .= " AND (d.discount_code LIKE ? OR d.type LIKE ? OR c.category_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY d.expiry_date ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: Could not fetch discounts. " . $e->getMessage());
}

// ------------------------------
// 2. HANDLE DELETION (GET Request)
// ------------------------------
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    try {
        $delete_stmt = $pdo->prepare("DELETE FROM discount WHERE discount_id = ?");
        $delete_stmt->execute([$delete_id]);
        
        // Check if deletion was successful
        if ($delete_stmt->rowCount() > 0) {
            $_SESSION['success'] = "Discount code deleted successfully.";
        } else {
            $_SESSION['error'] = "Discount not found or already deleted.";
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to delete discount. Database error: " . $e->getMessage();
    }
    
    // Redirect to clear the GET parameter
    redirect('discounts.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Discounts - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar { min-height: 100vh; background-color: #343a40; }
        .main-content { padding: 20px; }
        .expired { background-color: #f8d7da; } 
        .upcoming { background-color: #fff3cd; } 
        .active-discount { background-color: #d4edda; } 
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-nav.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1><i class="bi bi-percent me-2"></i> Discount Management</h1>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDiscountModal">
                            <i class="bi bi-plus-lg"></i> Add New Discount
                        </button>
                    </div>
                </div>

                <?php 
                // Line 99 - Now requires displaySessionMessages() to be defined in functions.php
                displaySessionMessages(); 
                ?>

                <div class="card p-3 mb-4">
                    <form method="GET" action="discounts.php" class="row g-3 align-items-center">
                        <div class="col-md-10">
                            <input type="text" class="form-control" name="search" placeholder="Search by code, type, or category..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-secondary">Search</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Type</th>
                                        <th>Value</th>
                                        <th>Applies To</th>
                                        <th>Start Date</th>
                                        <th>Expiry Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($discounts)): ?>
                                        <?php 
                                        $today = new DateTime('now');
                                        foreach ($discounts as $discount): 
                                            $startDate = new DateTime($discount['start_date']);
                                            $expiryDate = new DateTime($discount['expiry_date']);
                                            $status = '';
                                            $rowClass = '';

                                            if ($expiryDate < $today) {
                                                $status = '<span class="badge bg-danger">Expired</span>';
                                                $rowClass = 'expired';
                                            } elseif ($startDate > $today) {
                                                $status = '<span class="badge bg-warning text-dark">Upcoming</span>';
                                                $rowClass = 'upcoming';
                                            } else {
                                                $status = '<span class="badge bg-success">Active</span>';
                                                $rowClass = 'active-discount';
                                            }
                                        ?>
                                            <tr class="<?php echo $rowClass; ?>">
                                                <td><strong><?php echo htmlspecialchars($discount['discount_code']); ?></strong></td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($discount['type']))); ?></td>
                                                <td>
                                                    <?php 
                                                    echo $discount['type'] === 'percentage' 
                                                        ? htmlspecialchars($discount['value']) . '%' 
                                                        : '$' . number_format(htmlspecialchars($discount['value']), 2);
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($discount['applicable_to'] === 'category' && $discount['category_name']) {
                                                        echo "Category: " . htmlspecialchars($discount['category_name']);
                                                    } else {
                                                        echo ucfirst(str_replace('_', ' ', htmlspecialchars($discount['applicable_to'])));
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo date('Y-m-d', strtotime($discount['start_date'])); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($discount['expiry_date'])); ?></td>
                                                <td><?php echo $status; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-warning edit-discount-btn" 
                                                            data-id="<?php echo $discount['discount_id']; ?>" 
                                                            data-code="<?php echo htmlspecialchars($discount['discount_code']); ?>"
                                                            data-type="<?php echo htmlspecialchars($discount['type']); ?>"
                                                            data-value="<?php echo htmlspecialchars($discount['value']); ?>"
                                                            data-applies-to="<?php echo htmlspecialchars($discount['applicable_to']); ?>"
                                                            data-category-id="<?php echo htmlspecialchars($discount['category_id'] ?? ''); ?>"
                                                            data-start-date="<?php echo date('Y-m-d', strtotime($discount['start_date'])); ?>"
                                                            data-expiry-date="<?php echo date('Y-m-d', strtotime($discount['expiry_date'])); ?>"
                                                            data-bs-toggle="modal" data-bs-target="#editDiscountModal" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <a href="?delete_id=<?php echo $discount['discount_id']; ?>" 
                                                        class="btn btn-sm btn-outline-danger" 
                                                        onclick="return confirm('Are you sure you want to delete the discount: <?php echo htmlspecialchars($discount['discount_code']); ?>?')"
                                                        title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">No discounts found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="addDiscountModal" tabindex="-1" aria-labelledby="addDiscountModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="process-discount.php?action=add">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addDiscountModalLabel">Add New Discount</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="add_discount_code" class="form-label">Discount Code *</label>
                            <input type="text" class="form-control" id="add_discount_code" name="discount_code" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_type" class="form-label">Discount Type *</label>
                                <select class="form-select" id="add_type" name="type" required>
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed_amount">Fixed Amount ($)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_value" class="form-label">Value *</label>
                                <input type="number" step="0.01" class="form-control" id="add_value" name="value" required min="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_applicable_to" class="form-label">Applies To *</label>
                                <select class="form-select" id="add_applicable_to" name="applicable_to" required>
                                    <option value="all">All Products</option>
                                    <option value="category">Specific Category</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" id="add_category_field" style="display:none;">
                                <label for="add_category_id" class="form-label">Category</label>
                                <select class="form-select" id="add_category_id" name="category_id">
                                    <option value="">Select Category (Optional)</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="add_start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_expiry_date" class="form-label">Expiry Date *</label>
                                <input type="date" class="form-control" id="add_expiry_date" name="expiry_date" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Discount</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editDiscountModal" tabindex="-1" aria-labelledby="editDiscountModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="process-discount.php?action=edit">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="editDiscountModalLabel">Edit Discount</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="discount_id" id="edit_discount_id">
                        
                        <div class="mb-3">
                            <label for="edit_discount_code" class="form-label">Discount Code *</label>
                            <input type="text" class="form-control" id="edit_discount_code" name="discount_code" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_type" class="form-label">Discount Type *</label>
                                <select class="form-select" id="edit_type" name="type" required>
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed_amount">Fixed Amount ($)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_value" class="form-label">Value *</label>
                                <input type="number" step="0.01" class="form-control" id="edit_value" name="value" required min="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_applicable_to" class="form-label">Applies To *</label>
                                <select class="form-select" id="edit_applicable_to" name="applicable_to" required>
                                    <option value="all">All Products</option>
                                    <option value="category">Specific Category</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" id="edit_category_field" style="display:none;">
                                <label for="edit_category_id" class="form-label">Category</label>
                                <select class="form-select" id="edit_category_id" name="category_id">
                                    <option value="">Select Category (Optional)</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_expiry_date" class="form-label">Expiry Date *</label>
                                <input type="date" class="form-control" id="edit_expiry_date" name="expiry_date" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning"><i class="bi bi-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
    $(document).ready(function() {
        // Function to toggle Category field visibility
        function toggleCategoryField(selector, categoryFieldId) {
            if ($(selector).val() === 'category') {
                $(categoryFieldId).show();
            } else {
                $(categoryFieldId).hide();
                // Optionally clear the selection if it's hidden, to prevent submission errors
                // $(categoryFieldId).find('select').val(''); 
            }
        }

        // Add Discount Modal - Event Handlers
        $('#add_applicable_to').on('change', function() {
            toggleCategoryField(this, '#add_category_field');
        });
        // Initial check for Add Modal
        toggleCategoryField('#add_applicable_to', '#add_category_field');


        // Edit Discount Modal - Event Handlers
        $('#edit_applicable_to').on('change', function() {
            toggleCategoryField(this, '#edit_category_field');
        });

        // Script to pass data to the Edit Discount Modal
        $('.edit-discount-btn').on('click', function() {
            var data = $(this).data(); // Get all data-* attributes

            // Populate the hidden ID field
            $('#edit_discount_id').val(data.id);
            
            // Populate all other fields
            $('#edit_discount_code').val(data.code);
            $('#edit_type').val(data.type);
            $('#edit_value').val(data.value);
            $('#edit_applicable_to').val(data.appliesTo);
            $('#edit_start_date').val(data.startDate);
            $('#edit_expiry_date').val(data.expiryDate);

            // Special handling for category ID and visibility
            $('#edit_category_id').val(data.categoryId);
            toggleCategoryField('#edit_applicable_to', '#edit_category_field');
        });
    });
    </script>
</body>
</html>