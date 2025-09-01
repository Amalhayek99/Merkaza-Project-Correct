<?php
require_once '../sessions.php';
require_role(['owner', 'admin']);
include('../db.php');

// Refresh every 10 seconds
echo '<meta http-equiv="refresh" content="10">';
echo '<h3>Auto-processing orders... Page refreshes every 10 seconds.</h3>';

date_default_timezone_set('Asia/Jerusalem');

// Get pending orders where time has passed and not processed
$query = $conn->query("
    SELECT * FROM pending_orders
    WHERE auto_order_time <= NOW() AND processed = 0
");

while ($row = $query->fetch_assoc()) {
    $product_id = $row['product_id'];
    $order_date = $row['order_date'];
    $suggested_order = $row['suggested_order'];
    $current_qty = $row['current_qty'];

    // Calculate new stock
    $after_quantity = $current_qty + $suggested_order;

    // Insert into ordered table
    $insert = $conn->prepare("
        INSERT INTO ordered (product_id, order_date, ordered_quantity, before_quantity, after_quantity)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert->bind_param("isiii", $product_id, $order_date, $suggested_order, $current_qty, $after_quantity);
    $insert->execute();
    $insert->close();

    // Update products table (increase stock)
    $updateProduct = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE ID = ?");
    $updateProduct->bind_param("ii", $suggested_order, $product_id);
    $updateProduct->execute();
    $updateProduct->close();

    // Delete from pending_orders
    $deletePending = $conn->prepare("DELETE FROM pending_orders WHERE id = ?");
    $deletePending->bind_param("i", $row['id']);
    $deletePending->execute();
    $deletePending->close();

    echo "✅ Processed and removed pending order for product ID: $product_id<br>";
}

if ($query->num_rows == 0) {
    echo "⏳ No orders to process yet.<br>";
}
?>
