<?php
require_once '../sessions.php';
require_once '../db.php';

$cart_error_msg = '';
$cart_success_msg = '';
$cart_error_product_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === "customer") {
        $user_id    = (int) $_SESSION['user_id'];
        $product_id = (int) $_POST['product_id'];
        $quantity   = (int) ($_POST['quantity'] ?? 0);

        if ($quantity > 0) {
            // Get available stock
            $check_stock = $conn->prepare("SELECT quantity, name FROM products WHERE id = ?");
            $check_stock->bind_param("i", $product_id);
            $check_stock->execute();
            $result       = $check_stock->get_result();
            $product      = $result->fetch_assoc();
            $available    = (int) $product['quantity'];
            $product_name = $product['name'];
            $check_stock->close();

            if ($quantity > $available) {
                $cart_error_msg        = "Only {$available} of \"{$product_name}\" left in stock.";
                $cart_error_product_id = $product_id;
            } else {
                // Already in cart?
                $check = $conn->prepare("SELECT 1 FROM cart WHERE user_id = ? AND product_id = ?");
                $check->bind_param("ii", $user_id, $product_id);
                $check->execute();
                $res = $check->get_result();

                if ($res->num_rows > 0) {
                    $update = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
                    $update->bind_param("iii", $quantity, $user_id, $product_id);
                    $update->execute();
                    $update->close();
                } else {
                    // Fetch sale price if active
                    $price_query = $conn->prepare("
                        SELECT 
                            p.Price AS original_price,
                            s.price_after_discount AS sale_price
                        FROM products p
                        LEFT JOIN sales s 
                          ON p.id = s.product_id 
                         AND s.is_active = 1
                         AND NOW() BETWEEN s.date_start AND s.date_end
                        WHERE p.id = ?
                    ");
                    $price_query->bind_param("i", $product_id);
                    $price_query->execute();
                    $row = $price_query->get_result()->fetch_assoc();
                    $price_query->close();

                    $final_price = $row['sale_price'] !== null ? (float)$row['sale_price'] : (float)$row['original_price'];

                    $insert = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, selled_price, added_date) VALUES (?, ?, ?, ?, NOW())");
                    $insert->bind_param("iiid", $user_id, $product_id, $quantity, $final_price);
                    $insert->execute();
                    $insert->close();
                }

                $cart_success_msg = "Item added to cart!";
            }
        }
    }
}

// Handle optional redirect param safely (make relative paths go up one level)
if (!empty($_POST['redirect'])) {
    $redirect = trim($_POST['redirect']);
    if (strpos($redirect, 'http://') !== 0 && strpos($redirect, 'https://') !== 0 && strpos($redirect, '/') !== 0) {
        $redirect = '../' . ltrim($redirect, '/');
    }
    header("Location: $redirect");
    exit();
}

// Default fallback
header("Location: ../products.php");
exit();
