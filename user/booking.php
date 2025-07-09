<?php
// Start session
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
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

// INR conversion rate
$exchange_rate = 85;

// Get room type ID from query parameter
$room_type_id = isset($_GET['room_type_id']) ? intval($_GET['room_type_id']) : 0;

// Fetch room type details
$room_sql = "SELECT rt.id, rt.name, rt.description, rt.base_price, ri.image_path 
             FROM room_types rt 
             LEFT JOIN room_images ri ON rt.id = ri.room_type_id 
             WHERE rt.id = $room_type_id AND ri.is_primary = 1";
$room_result = $conn->query($room_sql);

if ($room_result->num_rows == 0) {
    header("Location: dashboard.php");
    exit();
}
$room = $room_result->fetch_assoc();

// Fetch amenities for the room type
$amenities_sql = "SELECT a.name, a.icon 
                  FROM amenities a 
                  JOIN room_amenities ra ON a.id = ra.amenity_id 
                  WHERE ra.room_type_id = $room_type_id";
$amenities_result = $conn->query($amenities_sql);
$room_amenities = [];
while ($amenity = $amenities_result->fetch_assoc()) {
    $room_amenities[] = $amenity;
}

// Handle booking form submission
$booking_error = '';
$booking_success = '';
$total_price = 0;
$nights = 0;

if (isset($_POST['submit_booking'])) {
    ob_start();
    $check_in_date = $conn->real_escape_string($_POST['check_in_date']);
    $check_out_date = $conn->real_escape_string($_POST['check_out_date']);
    $guests = intval($_POST['guests']);

    // Validate dates
    $today = date('Y-m-d');
    $check_in = strtotime($check_in_date);
    $check_out = strtotime($check_out_date);

    if ($check_in < strtotime($today)) {
        $booking_error = "Check-in date cannot be in the past.";
    } elseif ($check_out <= $check_in) {
        $booking_error = "Check-out date must be after check-in date.";
    } elseif ($guests < 1) {
        $booking_error = "Number of guests must be at least 1.";
    } else {
        // Calculate nights and total price
        $nights = ceil(($check_out - $check_in) / (60 * 60 * 24));
        $total_price = $room['base_price'] * $nights;

        // Check room availability
        $availability_sql = "SELECT r.id 
                            FROM rooms r 
                            WHERE r.room_type_id = $room_type_id 
                            AND r.id NOT IN (
                                SELECT b.room_id 
                                FROM bookings b 
                                WHERE b.status != 'cancelled' 
                                AND (
                                    (b.check_in_date <= '$check_out_date' AND b.check_out_date >= '$check_in_date')
                                )
                            ) 
                            LIMIT 1";
        $availability_result = $conn->query($availability_sql);

        if ($availability_result->num_rows == 0) {
            $booking_error = "No rooms of this type are available for the selected dates.";
        } else {
            $room_id = $availability_result->fetch_assoc()['id'];

            // Generate unique booking reference
            $booking_reference = 'LUXE' . strtoupper(substr(md5(uniqid()), 0, 8));

            // Insert booking
            $sql = "INSERT INTO bookings (user_id, room_id, booking_reference, check_in_date, check_out_date, total_price, status, created_at) 
                    VALUES ({$_SESSION['user_id']}, $room_id, '$booking_reference', '$check_in_date', '$check_out_date', $total_price, 'confirmed', NOW())";
            
            if ($conn->query($sql) === TRUE) {
                $booking_success = "Booking successful! Your booking reference is $booking_reference.";
                header("Location: dashboard.php#dashboard");
                exit();
            } else {
                $booking_error = "Error: " . $conn->error;
            }
        }
    }
    ob_end_clean();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LuxeStay Hotel - Book a Room</title>
    
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
            --primary: #c8a97e;
            --secondary: #1a1a1a;
            --light: #f8f9fa;
            --dark: #343a40;
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
        
        /* Section Styles */
        section {
            padding: 80px 0;
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
        
        /* Booking Section */
        .booking-content {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }
        
        .room-details {
            flex: 1;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .room-img {
            position: relative;
            height: 200px;
            overflow: hidden;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .room-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .room-price {
            font-size: 1.25rem;
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .room-details h3 {
            font-size: 1.75rem;
            margin-bottom: 15px;
        }
        
        .room-details p {
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .amenities-list {
            list-style: none;
            margin-bottom: 20px;
        }
        
        .amenities-list li {
            font-size: 0.85rem;
            color: #555;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }
        
        .amenities-list li i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .booking-form {
            flex: 1;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
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
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(200, 169, 126, 0.3);
            outline: none;
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
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-submit:hover {
            background-color: var(--secondary);
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
        
        .total-price {
            font-size: 1.25rem;
            color: var(--secondary);
            margin-top: 20px;
            font-weight: 500;
        }
        
        /* Footer */
        footer {
            background-color: var(--secondary);
            color: #fff;
            padding: 50px 0 0;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .footer-logo {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #fff;
            display: flex;
            align-items: center;
        }
        
        .footer-logo i {
            color: var(--primary);
            margin-right: 10px;
        }
        
        .footer-about p {
            line-height: 1.8;
            margin-bottom: 20px;
        }
        
        .social-links a {
            display: inline-block;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-radius: 50%;
            margin-right: 10px;
            text-align: center;
            line-height: 40px;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background-color: var(--primary);
        }
        
        .footer-title {
            font-size: 1.25rem;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 2px;
            background-color: var(--primary);
        }
        
        .footer-links ul {
            list-style: none;
        }
        
        .footer-links ul li {
            margin-bottom: 10px;
        }
        
        .footer-links ul li a {
            color: #ddd;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .footer-links ul li a:hover {
            color: var(--primary);
            padding-left: 5px;
        }
        
        .contact-info p {
            display: flex;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .contact-info i {
            color: var(--primary);
            margin-right: 10px;
            width: 20px;
        }
        
        .footer-bottom {
            background-color: #111;
            padding: 20px 0;
            text-align: center;
            font-size: 0.9rem;
        }
        
        /* Media Queries */
        @media (max-width: 991px) {
            .booking-content {
                flex-direction: column;
            }
        }
        
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
        }
        
        @media (max-width: 576px) {
            .section-title h2 {
                font-size: 1.75rem;
            }
            
            .room-details h3 {
                font-size: 1.5rem;
            }
            
            .room-price {
                font-size: 1.125rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <a href="dashboard.php#home" class="logo">
                    <i class="fas fa-hotel"></i>
                    LuxeStay
                </a>
                
                <nav>
                    <ul id="nav-menu">
                        <li><a href="dashboard.php#home">Home</a></li>
                        <li><a href="dashboard.php#about">About</a></li>
                        <li><a href="dashboard.php#amenities">Amenities</a></li>
                        <li><a href="dashboard.php#rooms">Rooms</a></li>
                        <li><a href="dashboard.php#testimonials">Testimonials</a></li>
                        <li><a href="dashboard.php#contact">Contact</a></li>
                        <li><a href="dashboard.php#dashboard">My Account</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
                
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>
    
    <!-- Booking Section -->
    <section class="booking" id="booking">
        <div class="container">
            <div class="section-title">
                <h2>Book Your Stay</h2>
                <p>Reserve your luxurious room at LuxeStay</p>
            </div>
            
            <?php if($booking_error): ?>
                <div class="error-message"><?php echo htmlspecialchars($booking_error); ?></div>
            <?php endif; ?>
            <?php if($booking_success): ?>
                <div class="success-message"><?php echo htmlspecialchars($booking_success); ?></div>
            <?php endif; ?>
            
            <div class="booking-content">
                <!-- Room Details -->
                <div class="room-details">
                    <div class="room-img">
                        <img src="<?php echo htmlspecialchars($room['image_path']); ?>" alt="<?php echo htmlspecialchars($room['name']); ?>">
                    </div>
                    <h3><?php echo htmlspecialchars($room['name']); ?></h3>
                    <div class="room-price">₹<?php echo number_format($room['base_price'] * $exchange_rate, 2); ?>/night</div>
                    <p><?php echo htmlspecialchars($room['description']); ?></p>
                    <ul class="amenities-list">
                        <?php foreach ($room_amenities as $amenity): ?>
                            <li><i class="fas fa-<?php echo htmlspecialchars($amenity['icon']); ?>"></i> <?php echo htmlspecialchars($amenity['name']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Booking Form -->
                <div class="booking-form">
                    <form method="POST">
                        <div class="form-group">
                            <label for="check_in_date">Check-In Date</label>
                            <input type="date" id="check_in_date" name="check_in_date" required>
                        </div>
                        <div class="form-group">
                            <label for="check_out_date">Check-Out Date</label>
                            <input type="date" id="check_out_date" name="check_out_date" required>
                        </div>
                        <div class="form-group">
                            <label for="guests">Number of Guests</label>
                            <input type="number" id="guests" name="guests" min="1" value="1" required>
                        </div>
                        <button type="submit" name="submit_booking" class="btn-submit">Confirm Booking</button>
                    </form>
                    <?php if ($total_price > 0): ?>
                        <div class="total-price">
                            Total for <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>: ₹<?php echo number_format($total_price * $exchange_rate, 2); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-about">
                    <a href="dashboard.php#home" class="footer-logo">
                        <i class="fas fa-hotel"></i>
                        LuxeStay
                    </a>
                    <p>Experience luxury and comfort at its finest. Book your stay with us and create unforgettable memories.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-links">
                    <h3 class="footer-title">Quick Links</h3>
                    <ul>
                        <li><a href="dashboard.php#home">Home</a></li>
                        <li><a href="dashboard.php#about">About</a></li>
                        <li><a href="dashboard.php#amenities">Amenities</a></li>
                        <li><a href="dashboard.php#rooms">Rooms</a></li>
                        <li><a href="dashboard.php#testimonials">Testimonials</a></li>
                        <li><a href="dashboard.php#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="contact-info">
                    <h3 class="footer-title">Contact Info</h3>
                    <p><i class="fas fa-map-marker-alt"></i> 123 Luxury Avenue, Mumbai, India</p>
                    <p><i class="fas fa-phone"></i> +91 123 456 7890</p>
                    <p><i class="fas fa-envelope"></i> info@luxestay.com</p>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© <?php echo date('Y'); ?> LuxeStay Hotel. All rights reserved.</p>
        </div>
    </footer>
    
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
    </script>
</body>
</html>