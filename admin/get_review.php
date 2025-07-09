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
    echo json_encode(['error' => 'No review ID provided']);
    exit();
}

$id = $conn->real_escape_string($_GET['id']);
$sql = "SELECT u.email, rt.name as room_type, r.rating, r.comment, r.created_at 
        FROM reviews r 
        JOIN bookings b ON r.booking_id = b.id 
        JOIN users u ON b.user_id = u.id 
        JOIN rooms rm ON b.room_id = rm.id 
        JOIN room_types rt ON rm.room_type_id = rt.id 
        WHERE r.id = '$id'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo json_encode(['error' => 'Review not found']);
}

$conn->close();
?>