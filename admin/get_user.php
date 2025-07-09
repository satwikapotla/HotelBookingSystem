<?php
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hotel_booking";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No user ID provided']);
    exit();
}

$id = $conn->real_escape_string($_GET['id']);
$sql = "SELECT id, first_name, last_name, email, phone, role, created_at 
        FROM users WHERE id = '$id'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo json_encode(['error' => 'User not found']);
}

$conn->close();
?>