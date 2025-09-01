<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once 'sessions.php';

}

// Database connection settings
$servername = "localhost";
$username = "root"; // Your MySQL username
$password = ""; // Your MySQL password
$dbname = "merkaza_hostinger"; // Updated database name

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to get the user's current balance
function getUserBalance($conn, $user_id) {
    $query = "SELECT amount FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($balance);
    $stmt->fetch();
    $stmt->close();
    return $balance;
}
?>
