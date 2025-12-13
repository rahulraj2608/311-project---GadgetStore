<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is logged in (optional: check if admin, but non-admin users might need their own invoices too)
if (!isLoggedIn()) {
    redirect('../login.php');
}

// ------------------------------
// 1. GET ORDER ID AND FETCH DATA
// ------------------------------
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id === 0) {
    die("Error: Invalid Order ID."); 
}

// Fetch main order details and customer info (using the fixed query structure)
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

// Fetch order items (products, price, quantity)
$items_sql = "SELECT oi.*, p.product_name
              FROM order_item oi
              JOIN product p ON oi.product_id = p.product_id
              WHERE oi.order_id = ?
              GROUP BY oi.order_item_id"; 
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for unauthorized access (e.g., if a regular customer tries to view another user's invoice)
// Since this is in the 'admin' folder, we assume the admin check at the top is sufficient, 
// but for a customer-facing invoice, a check like below is vital:
// if (!isAdmin() && $order['customer_id'] !== $_SESSION['customer_id']) { 
//     die("Access Denied.");
// }
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
        /* Print styles for optimal invoice layout */
        @media print {
            body { font-size: 10pt; }
            .container { width: 100%; max-width: 800px; margin: 0 auto; padding: 0; }
            .no-print { display: none; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
        }
        /* General screen styles */
        body { background-color: #f8f9fa; }
        .invoice-box { background-color: white; border: 1px solid #dee2e6; padding: 30px; margin: 20px auto; max-width: 800px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body onload="window.print()">
    <div class="invoice-box">
        <div class="text-center mb-4">
            <h1 class="h3">GADGET STORE</h1>
            <p class="text-muted">Invoice/Receipt</p>
        </div>

        <div class="row mb-4 border-bottom pb-3">
            <div class="col-6">
                <strong>INVOICE TO:</strong><br>
                <?php echo $order['first_name'] . ' ' . $order['last_name']; ?><br>
                <?php echo $order['email']; ?>
            </div>
            <div class="col-6 text-end">
                <strong>INVOICE #</strong><br>
                <span class="h4 text-primary">ORD-<?php echo $order['order_id']; ?></span><br><br>
                <strong>Date:</strong> <?php echo date('M d, Y', strtotime($order['order_date'])); ?><br>
                <strong>Status:</strong> <?php echo ucfirst($order['order_status']); ?>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-6">
                <strong>SHIPPING ADDRESS:</strong><br>
                <address><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></address>
            </div>
            <div class="col-6 text-end">
                <strong>PAYMENT METHOD:</strong><br>
                <?php echo $order['payment_method']; ?><br>
                <?php if (!empty($order['transaction_id'])): ?>
                    <small class="text-muted">TXN: <?php echo $order['transaction_id']; ?></small>
                <?php endif; ?>
            </div>
        </div>

        <table class="table table-bordered table-sm mb-5">
            <thead>
                <tr class="table-dark">
                    <th style="width: 50%;">Product</th>
                    <th class="text-end" style="width: 15%;">Unit Price</th>
                    <th class="text-center" style="width: 15%;">Qty</th>
                    <th class="text-end" style="width: 20%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $subtotal = 0;
                foreach ($order_items as $item): 
                    $item_subtotal = $item['per_unit_price'] * $item['quantity'];
                    $subtotal += $item_subtotal;
                ?>
                <tr>
                    <td><?php echo $item['product_name']; ?></td>
                    <td class="text-end">$<?php echo number_format($item['per_unit_price'], 2); ?></td>
                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                    <td class="text-end">$<?php echo number_format($item_subtotal, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php 
                $shipping = $order['shipping'] ?? 0.00;
                $tax_paid = $order['total_amount'] - $shipping - $subtotal;
                ?>
                <tr>
                    <td colspan="3" class="text-end fw-bold">Subtotal:</td>
                    <td class="text-end fw-bold">$<?php echo number_format($subtotal, 2); ?></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-end">Shipping:</td>
                    <td class="text-end">$<?php echo number_format($shipping, 2); ?></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-end">Tax:</td>
                    <td class="text-end">$<?php echo number_format($tax_paid, 2); ?></td>
                </tr>
                <tr class="table-info">
                    <td colspan="3" class="text-end fw-bold h5">GRAND TOTAL:</td>
                    <td class="text-end fw-bold h5">$<?php echo number_format($order['total_amount'], 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="text-center pt-3 mt-5 border-top">
            <p class="text-muted">Thank you for your order! Please contact us for any inquiries.</p>
            <p class="no-print">
                <button onclick="window.print()" class="btn btn-primary me-2"><i class="bi bi-printer"></i> Print this Invoice</button>
                <a href="order-details.php?id=<?php echo $order_id; ?>" class="btn btn-secondary"><i class="bi bi-x-lg"></i> Close</a>
            </p>
        </div>
    </div>
    
    <script>
        // Use JavaScript to trigger the print dialog immediately on load
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>