<?php
// Start session
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hotel_booking";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle add room
$add_room_error = '';
$add_room_success = '';
if (isset($_POST['add_room'])) {
    $room_number = $conn->real_escape_string(trim($_POST['room_number']));
    $room_type_id = intval($_POST['room_type_id']);
    $status = $conn->real_escape_string($_POST['status']);
    $floor = intval($_POST['floor']);
    $max_occupancy = intval($_POST['max_occupancy']);
    
    // Validation
    $errors = [];
    if (empty($room_number)) {
        $errors[] = "Room number is required.";
    } else {
        // Check if room_number is unique
        $check_sql = "SELECT id FROM rooms WHERE room_number = '$room_number'";
        if ($conn->query($check_sql)->num_rows > 0) {
            $errors[] = "Room number already exists.";
        }
    }
    if ($room_type_id <= 0) {
        $errors[] = "Please select a valid room type.";
    }
    if (!in_array($status, ['available', 'occupied', 'maintenance'])) {
        $errors[] = "Invalid status.";
    }
    if ($floor <= 0) {
        $errors[] = "Floor must be a positive number.";
    }
    if ($max_occupancy <= 0) {
        $errors[] = "Max occupancy must be a positive number.";
    }
    
    if (empty($errors)) {
        $sql = "INSERT INTO rooms (room_number, room_type_id, status, floor, max_occupancy) 
                VALUES ('$room_number', $room_type_id, '$status', $floor, $max_occupancy)";
        if ($conn->query($sql) === TRUE) {
            $add_room_success = "Room added successfully.";
            header("Location: dashboard.php");
            exit();
        } else {
            $add_room_error = "Error: " . $conn->error;
        }
    } else {
        $add_room_error = implode("<br>", $errors);
    }
}

// Handle delete actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $conn->real_escape_string($_GET['id']);
    switch ($_GET['action']) {
        case 'delete_user':
            $sql = "DELETE FROM users WHERE id = '$id' AND role != 'admin'";
            $conn->query($sql);
            break;
        case 'delete_room':
            $sql = "DELETE FROM rooms WHERE id = '$id'";
            $conn->query($sql);
            break;
        case 'delete_booking':
            $sql = "DELETE FROM bookings WHERE id = '$id'";
            $conn->query($sql);
            break;
        case 'delete_review':
            $sql = "DELETE FROM reviews WHERE id = '$id'";
            $conn->query($sql);
            break;
    }
    header("Location: dashboard.php");
    exit();
}

// Fetch summary data
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms")->fetch_assoc()['count'];
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];
$total_reviews = $conn->query("SELECT COUNT(*) as count FROM reviews")->fetch_assoc()['count'];

// Fetch data for tables
$users = $conn->query("SELECT id, first_name, last_name, email, role FROM users ORDER BY created_at DESC");
$rooms = $conn->query("SELECT r.id, r.room_number, rt.name as room_type, r.status, r.floor, r.max_occupancy 
                       FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id 
                       ORDER BY r.floor, r.room_number");
$bookings = $conn->query("SELECT b.id, b.booking_reference, u.email, r.room_number, b.check_in_date, b.check_out_date, b.total_price, b.status 
                          FROM bookings b 
                          JOIN users u ON b.user_id = u.id 
                          JOIN rooms r ON b.room_id = r.id 
                          ORDER BY b.created_at DESC");
$reviews = $conn->query("SELECT r.id, u.email, rt.name as room_type, r.rating, r.comment, r.created_at 
                         FROM reviews r 
                         JOIN bookings b ON r.booking_id = b.id 
                         JOIN users u ON b.user_id = u.id 
                         JOIN rooms rm ON b.room_id = rm.id 
                         JOIN room_types rt ON rm.room_type_id = rt.id 
                         ORDER BY r.created_at DESC");

// Fetch room types for add room form
$room_types = $conn->query("SELECT id, name FROM room_types ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LuxeStay Hotel - Admin Dashboard</title>
    
    <!-- Favicon -->
    <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2933/2933134.png" type="image/png">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #c8a97e; /* Gold */
            --secondary: #1a1a1a; /* Dark */
            --light: #f8f9fa; /* Light */
            --dark: #343a40; /* Navy */
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header Styles */
        header {
            background-color: rgba(255, 255, 255, 0.9);
            position: fixed;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
            transition: all 0.3s ease;
        }
        
        header.scrolled {
            background-color: #fff;
            padding: 10px 0;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            color: var(--primary);
            margin-right: 10px;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 30px;
        }
        
        nav ul li a {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        nav ul li a:hover {
            color: var(--primary);
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary);
        }
        
        /* Dashboard Styles */
        .dashboard {
            padding: 100px 0;
            background-color: var(--light);
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            position: relative;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .section-title h2::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: var(--primary);
        }
        
        .section-title p {
            font-size: 1rem;
            color: #777;
        }
        
        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }
        
        .summary-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        
        .summary-card i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .summary-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .summary-card p {
            font-size: 1.25rem;
            color: var(--secondary);
        }
        
        /* Table Styles */
        .table-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: var(--primary);
            color: #fff;
            font-weight: 600;
        }
        
        tr:hover {
            background-color: var(--light);
        }
        
        .action-buttons a {
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            margin-right: 5px;
        }
        
        .btn-view {
            background-color: #007bff;
            color: #fff;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: #fff;
        }
        
        .btn-add {
            display: inline-block;
            padding: 12px 20px;
            background-color: var(--primary);
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            overflow: auto;
        }
        
        .modal-content {
            background-color: #fff;
            max-width: 500px;
            margin: 80px auto;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 1.5rem;
            cursor: pointer;
            color: #777;
            transition: all 0.3s ease;
        }
        
        .close-btn:hover {
            color: var(--secondary);
        }
        
        .modal-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .btn-submit {
            background-color: var(--primary);
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .modal-details {
            font-size: 1rem;
            line-height: 1.6;
        }
        
        .modal-details p {
            margin-bottom: 10px;
        }
        
        /* Media Queries */
        @media (max-width: 768px) {
            header {
                padding: 10px 0;
            }
            
            nav ul {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                background-color: #fff;
                flex-direction: column;
                padding: 20px 0;
                box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
            }
            
            nav ul.active {
                display: flex;
            }
            
            nav ul li {
                margin: 10px 0;
                text-align: center;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            table {
                font-size: 0.9rem;
            }
            
            th, td {
                padding: 8px;
            }
        }
        
        @media (max-width: 576px) {
            .section-title h2 {
                font-size: 1.75rem;
            }
            
            .summary-card {
                padding: 15px;
            }
            
            .summary-card h3 {
                font-size: 1.25rem;
            }
            
            .summary-card p {
                font-size: 1rem;
            }
            
            .modal-content {
                padding: 20px;
                margin: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <a href="../index.php" class="logo">
                    <i class="fas fa-hotel"></i>
                    LuxeStay
                </a>
                
                <nav>
                    <ul id="nav-menu">
                        <li><a href="#users">Users</a></li>
                        <li><a href="#rooms">Rooms</a></li>
                        <li><a href="#bookings">Bookings</a></li>
                        <li><a href="#reviews">Reviews</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
                
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>
    
    <!-- Dashboard -->
    <section class="dashboard">
        <div class="container">
            <div class="section-title">
                <h2>Admin Dashboard</h2>
                <p>Manage users, rooms, bookings, and reviews</p>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <i class="fas fa-users"></i>
                    <h3>Total Users</h3>
                    <p><?php echo $total_users; ?></p>
                </div>
                <div class="summary-card">
                    <i class="fas fa-hotel"></i>
                    <h3>Total Rooms</h3>
                    <p><?php echo $total_rooms; ?></p>
                </div>
                <div class="summary-card">
                    <i class="fas fa-book"></i>
                    <h3>Total Bookings</h3>
                    <p><?php echo $total_bookings; ?></p>
                </div>
                <div class="summary-card">
                    <i class="fas fa-star"></i>
                    <h3>Total Reviews</h3>
                    <p><?php echo $total_reviews; ?></p>
                </div>
            </div>
            
            <!-- Users Section -->
            <div class="table-container" id="users">
                <h3>Users</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td class="action-buttons">
                                    <a href="#" class="btn-view" onclick="viewUser(<?php echo $user['id']; ?>)">View</a>
                                    <?php if ($user['role'] !== 'admin'): ?>
                                        <a href="?action=delete_user&id=<?php echo $user['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Rooms Section -->
            <div class="table-container" id="rooms">
                <h3>Rooms</h3>
                <a href="#" class="btn-add" onclick="openModal('addRoomModal')">Add Room</a>
                <table>
                    <thead>
                        <tr>
                            <th>Room Number</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Floor</th>
                            <th>Max Occupancy</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($room = $rooms->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($room['room_type']); ?></td>
                                <td><?php echo htmlspecialchars($room['status']); ?></td>
                                <td><?php echo htmlspecialchars($room['floor']); ?></td>
                                <td><?php echo htmlspecialchars($room['max_occupancy']); ?></td>
                                <td class="action-buttons">
                                    <a href="#" class="btn-view" onclick="viewRoom(<?php echo $room['id']; ?>)">View</a>
                                    <a href="?action=delete_room&id=<?php echo $room['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this room?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Bookings Section -->
            <div class="table-container" id="bookings">
                <h3>Bookings</h3>
                <a href="#" class="btn-add">Add Booking</a>
                <table>
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>User</th>
                            <th>Room</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['booking_reference']); ?></td>
                                <td><?php echo htmlspecialchars($booking['email']); ?></td>
                                <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($booking['check_in_date']); ?></td>
                                <td><?php echo htmlspecialchars($booking['check_out_date']); ?></td>
                                <td>₹<?php echo number_format($booking['total_price'] * 85, 2); ?></td>
                                <td><?php echo htmlspecialchars($booking['status']); ?></td>
                                <td class="action-buttons">
                                    <a href="#" class="btn-view" onclick="viewBooking(<?php echo $booking['id']; ?>)">View</a>
                                    <a href="?action=delete_booking&id=<?php echo $booking['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this booking?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Reviews Section -->
            <div class="table-container" id="reviews">
                <h3>Reviews</h3>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Room Type</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($review = $reviews->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($review['email']); ?></td>
                                <td><?php echo htmlspecialchars($review['room_type']); ?></td>
                                <td><?php echo htmlspecialchars($review['rating']); ?>/5</td>
                                <td><?php echo htmlspecialchars(substr($review['comment'], 0, 50)) . (strlen($review['comment']) > 50 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($review['created_at']))); ?></td>
                                <td class="action-buttons">
                                    <a href="#" class="btn-view" onclick="viewReview(<?php echo $review['id']; ?>)">View</a>
                                    <a href="?action=delete_review&id=<?php echo $review['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this review?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Add Room Modal -->
            <div class="modal" id="addRoomModal">
                <div class="modal-content">
                    <span class="close-btn" onclick="closeModal('addRoomModal')">×</span>
                    <h2 class="modal-title">Add New Room</h2>
                    <?php if($add_room_error): ?>
                        <div class="error-message"><?php echo htmlspecialchars($add_room_error); ?></div>
                    <?php endif; ?>
                    <?php if($add_room_success): ?>
                        <div class="success-message"><?php echo htmlspecialchars($add_room_success); ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="form-group">
                            <label for="room_number">Room Number</label>
                            <input type="text" id="room_number" name="room_number" required>
                        </div>
                        <div class="form-group">
                            <label for="room_type_id">Room Type</label>
                            <select id="room_type_id" name="room_type_id" required>
                                <option value="">Select Room Type</option>
                                <?php while ($rt = $room_types->fetch_assoc()): ?>
                                    <option value="<?php echo $rt['id']; ?>"><?php echo htmlspecialchars($rt['name']); ?></option>
                                <?php endwhile; ?>
                                <?php $room_types->data_seek(0); ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="floor">Floor</label>
                            <input type="number" id="floor" name="floor" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="max_occupancy">Max Occupancy</label>
                            <input type="number" id="max_occupancy" name="max_occupancy" min="1" required>
                        </div>
                        <button type="submit" name="add_room" class="btn-submit">Add Room</button>
                    </form>
                </div>
            </div>
            
            <!-- View User Modal -->
            <div class="modal" id="viewUserModal">
                <div class="modal-content">
                    <span class="close-btn" onclick="closeModal('viewUserModal')">×</span>
                    <h2 class="modal-title">User Details</h2>
                    <div class="modal-details" id="userDetails"></div>
                </div>
            </div>
            
            <!-- View Room Modal -->
            <div class="modal" id="viewRoomModal">
                <div class="modal-content">
                    <span class="close-btn" onclick="closeModal('viewRoomModal')">×</span>
                    <h2 class="modal-title">Room Details</h2>
                    <div class="modal-details" id="roomDetails"></div>
                </div>
            </div>
            
            <!-- View Booking Modal -->
            <div class="modal" id="viewBookingModal">
                <div class="modal-content">
                    <span class="close-btn" onclick="closeModal('viewBookingModal')">×</span>
                    <h2 class="modal-title">Booking Details</h2>
                    <div class="modal-details" id="bookingDetails"></div>
                </div>
            </div>
            
            <!-- View Review Modal -->
            <div class="modal" id="viewReviewModal">
                <div class="modal-content">
                    <span class="close-btn" onclick="closeModal('viewReviewModal')">×</span>
                    <h2 class="modal-title">Review Details</h2>
                    <div class="modal-details" id="reviewDetails"></div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- JavaScript -->
    <script>
        // Header scroll effect
        window.addEventListener('scroll', () => {
            const header = document.querySelector('header');
            header.classList.toggle('scrolled', window.scrollY > 50);
        });
        
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navMenu = document.getElementById('nav-menu');
        mobileMenuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            mobileMenuBtn.querySelector('i').classList.toggle('fa-bars');
            mobileMenuBtn.querySelector('i').classList.toggle('fa-times');
        });
        
        // Modal handling
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal on outside click
        window.addEventListener('click', (event) => {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        });
        
        // View functions
        function viewUser(id) {
            fetch(`get_user.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('userDetails').innerHTML = `<p>Error: ${data.error}</p>`;
                    } else {
                        document.getElementById('userDetails').innerHTML = `
                            <p><strong>ID:</strong> ${data.id}</p>
                            <p><strong>Name:</strong> ${data.first_name} ${data.last_name}</p>
                            <p><strong>Email:</strong> ${data.email}</p>
                            <p><strong>Phone:</strong> ${data.phone}</p>
                            <p><strong>Role:</strong> ${data.role}</p>
                            <p><strong>Created At:</strong> ${data.created_at}</p>
                        `;
                    }
                    openModal('viewUserModal');
                })
                .catch(error => {
                    document.getElementById('userDetails').innerHTML = `<p>Error fetching user details.</p>`;
                    openModal('viewUserModal');
                });
        }
        
        function viewRoom(id) {
            fetch(`get_room.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('roomDetails').innerHTML = `<p>Error: ${data.error}</p>`;
                    } else {
                        document.getElementById('roomDetails').innerHTML = `
                            <p><strong>Room Number:</strong> ${data.room_number}</p>
                            <p><strong>Type:</strong> ${data.room_type}</p>
                            <p><strong>Status:</strong> ${data.status}</p>
                            <p><strong>Floor:</strong> ${data.floor}</p>
                            <p><strong>Max Occupancy:</strong> ${data.max_occupancy}</p>
                        `;
                    }
                    openModal('viewRoomModal');
                })
                .catch(error => {
                    document.getElementById('roomDetails').innerHTML = `<p>Error fetching room details.</p>`;
                    openModal('viewRoomModal');
                });
        }
        
        function viewBooking(id) {
            fetch(`get_booking.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('bookingDetails').innerHTML = `<p>Error: ${data.error}</p>`;
                    } else {
                        document.getElementById('bookingDetails').innerHTML = `
                            <p><strong>Reference:</strong> ${data.booking_reference}</p>
                            <p><strong>User:</strong> ${data.email}</p>
                            <p><strong>Room:</strong> ${data.room_number}</p>
                            <p><strong>Check-in:</strong> ${data.check_in_date}</p>
                            <p><strong>Check-out:</strong> ${data.check_out_date}</p>
                            <p><strong>Total Price:</strong> ₹${(data.total_price * 85).toFixed(2)}</p>
                            <p><strong>Status:</strong> ${data.status}</p>
                            <p><strong>Created At:</strong> ${data.created_at}</p>
                        `;
                    }
                    openModal('viewBookingModal');
                })
                .catch(error => {
                    document.getElementById('bookingDetails').innerHTML = `<p>Error fetching booking details.</p>`;
                    openModal('viewBookingModal');
                });
        }
        
        function viewReview(id) {
            fetch(`get_review.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('reviewDetails').innerHTML = `<p>Error: ${data.error}</p>`;
                    } else {
                        document.getElementById('reviewDetails').innerHTML = `
                            <p><strong>User:</strong> ${data.email}</p>
                            <p><strong>Room Type:</strong> ${data.room_type}</p>
                            <p><strong>Rating:</strong> ${data.rating}/5</p>
                            <p><strong>Comment:</strong> ${data.comment}</p>
                            <p><strong>Created At:</strong> ${data.created_at}</p>
                        `;
                    }
                    openModal('viewReviewModal');
                })
                .catch(error => {
                    document.getElementById('reviewDetails').innerHTML = `<p>Error fetching review details.</p>`;
                    openModal('viewReviewModal');
                });
        }
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>