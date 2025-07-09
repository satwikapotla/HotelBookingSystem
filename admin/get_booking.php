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
    echo json_encode(['error' => 'No booking ID provided']);
    exit();
}

$id = $conn->real_escape_string($_GET['id']);
$sql = "SELECT b.booking_reference, u.email, r.room_number, b.check_in_date, b.check_out_date, 
               b.total_price, b.status, b.created_at 
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN rooms r ON b.room_id = r.id 
        WHERE b.id = '$id'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo json_encode(['error' => 'Booking not found']);
}

$conn->close();
?>
