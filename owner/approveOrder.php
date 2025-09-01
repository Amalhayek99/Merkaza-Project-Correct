<?php
require_once '../sessions.php';
require_role(['owner', 'admin']);
require_once '../db.php';
require_once '../auth/phpmailer/PHPMailer.php';
require_once '../auth/phpmailer/SMTP.php';
require_once '../auth/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Get the order
    $stmt = $conn->prepare("
        SELECT po.*, p.Name 
        FROM pending_orders po
        JOIN products p ON po.product_id = p.ID
        WHERE po.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        $productId = $row['product_id'];
        $orderedQty = $row['suggested_order'];
        $beforeQty = $row['current_qty'];
        $afterQty = $beforeQty + $orderedQty;
        $today = date('Y-m-d');
        $name = $row['Name'];

        // Insert into ordered table
        $insert = $conn->prepare("INSERT INTO ordered (product_id, order_date, ordered_quantity, before_quantity, after_quantity)
                                  VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("isiii", $productId, $today, $orderedQty, $beforeQty, $afterQty);
        $insert->execute();
        $insert->close();

        // Update product quantity
        $update = $conn->prepare("UPDATE products SET quantity = ? WHERE ID = ?");
        $update->bind_param("ii", $afterQty, $productId);
        $update->execute();
        $update->close();

        // Remove from pending
        $delete = $conn->prepare("DELETE FROM pending_orders WHERE id = ?");
        $delete->bind_param("i", $id);
        $delete->execute();
        $delete->close();

        // Send email to admins/owners
        $emailStmt = $conn->query("SELECT email FROM users WHERE usertype IN ('admin', 'owner')");
        $emails = [];
        while ($rowEmail = $emailStmt->fetch_assoc()) {
            $emails[] = $rowEmail['email'];
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.yourhost.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'amalhayek10@gmail.com';
            $mail->Password   = 'buet ybii ogil domj';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('noreply@merkaza.com', 'Merkaza System');
            foreach ($emails as $email) {
                $mail->addAddress($email);
            }

            $mail->Subject = "✅ Approved Manual Restock - $name";
            $mail->isHTML(true);
            $mail->Body = "
                <h2>Restock Order Approved</h2>
                <p><strong>Product:</strong> $name</p>
                <p><strong>Previous Stock:</strong> $beforeQty</p>
                <p><strong>Added Quantity:</strong> $orderedQty</p>
                <p><strong>New Stock:</strong> $afterQty</p>
            ";
            $mail->send();
        } catch (Exception $e) {
            error_log("Mail error: " . $mail->ErrorInfo);
        }
    }
}

header("Location: orders.php");
exit();
?>
