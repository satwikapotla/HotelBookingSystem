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
    echo json_encode(['error' => 'No room ID provided']);
    exit();
}

$id = $conn->real_escape_string($_GET['id']);
$sql = "SELECT r.room_number, rt.name as room_type, r.status, r.floor, r.max_occupancy 
        FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id 
        WHERE r.id = '$id'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo json_encode(['error' => 'Room not found']);
}

$conn->close();
?>