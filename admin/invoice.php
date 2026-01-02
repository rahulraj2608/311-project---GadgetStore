<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../login.php');
}

// ------------------------------
// 1. CONFIGURATION & FETCH DATA
// ------------------------------
$shipping_fixed = 10.00; // Fixed shipping charge
$tax_rate = 0.10;       // 10% tax rate
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id === 0) {
    die("Error: Invalid Order ID."); 
}

// Fetch main order details
$order_sql = "SELECT o.*, c.first_name, c.last_name, c.email 
              FROM `orders` o 
              JOIN customer c ON o.customer_id = c.customer_id 
              WHERE o.order_id = ?";
$order_stmt = $pdo->prepare($order_sql);
$order_stmt->execute([$order_id]);
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Error: Order #{$order_id} not found.");
}

// Fetch order items
$items_sql = "SELECT oi.*, p.product_name
              FROM order_item oi
              JOIN product p ON oi.product_id = p.product_id
              WHERE oi.order_id = ?
              GROUP BY oi.order_item_id"; 
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        @media print {
            body { background-color: white !important; font-size: 10pt; }
            .no-print { display: none !important; }
            .invoice-box { box-shadow: none !important; border: none !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; }
        }
        body { background-color: #f8f9fa; }
        .invoice-box { background-color: white; border: 1px solid #dee2e6; padding: 40px; margin: 30px auto; max-width: 850px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .table thead th { background-color: #343a40 !important; color: white !important; border: none; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="row mb-4">
            <div class="col-6">
                <h1 class="h2 text-dark mb-0">GADGET STORE</h1>
                <p class="text-muted">E-Commerce Receipt</p>
            </div>
            <div class="col-6 text-end">
                <h2 class="h4 text-primary mb-1">INVOICE #ORD-<?php echo $order_id; ?></h2>
                <p class="mb-0"><strong>Date:</strong> <?php echo date('M d, Y', strtotime($order['order_date'])); ?></p>
                <span class="badge bg-secondary"><?php echo strtoupper($order['order_status']); ?></span>
            </div>
        </div>

        <hr>

        <div class="row my-4">
            <div class="col-6">
                <h6 class="text-uppercase text-muted small fw-bold">Billed To:</h6>
                <p class="mb-0"><strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong></p>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($order['email']); ?></p>
            </div>
            <div class="col-6 text-end">
                <h6 class="text-uppercase text-muted small fw-bold">Shipping Address:</h6>
                <address class="mb-0"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></address>
            </div>
        </div>

        <table class="table table-striped table-bordered mt-4">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $subtotal_sum = 0;
                foreach ($order_items as $item): 
                    $line_total = $item['per_unit_price'] * $item['quantity'];
                    $subtotal_sum += $line_total;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="text-end">$<?php echo number_format($item['per_unit_price'], 2); ?></td>
                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                    <td class="text-end">$<?php echo number_format($line_total, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php 
                    $tax_amount = $subtotal_sum * $tax_rate;
                    $grand_total = $subtotal_sum + $shipping_fixed + $tax_amount;
                ?>
                <tr>
                    <td colspan="3" class="text-end fw-bold">Items Subtotal:</td>
                    <td class="text-end">$<?php echo number_format($subtotal_sum, 2); ?></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-end">Shipping Charge:</td>
                    <td class="text-end">$<?php echo number_format($shipping_fixed, 2); ?></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-end">Tax (10%):</td>
                    <td class="text-end">$<?php echo number_format($tax_amount, 2); ?></td>
                </tr>
                <tr class="table-dark">
                    <td colspan="3" class="text-end fw-bold">GRAND TOTAL:</td>
                    <td class="text-end fw-bold">$<?php echo number_format($grand_total, 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="row mt-5">
            <div class="col-6 small text-muted">
                <p><strong>Payment Info:</strong><br>
                Method: <?php echo $order['payment_method']; ?><br>
                <?php if(!empty($order['transaction_id'])) echo "ID: " . $order['transaction_id']; ?>
                </p>
            </div>
            <div class="col-6 text-end">
                <p class="fst-italic text-muted">Thank you for shopping with us!</p>
            </div>
        </div>

        <div class="text-center mt-4 no-print border-top pt-3">
            <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print</button>
            <a href="order-details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary">Back to Details</a>
        </div>
    </div>

    <script>
        // Optional: Auto-trigger print when page loads
        window.onload = function() {
            // window.print(); 
        };
    </script>
</body>
</html>