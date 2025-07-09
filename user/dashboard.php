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

// Fetch room types and images
$room_types_sql = "SELECT rt.id, rt.name, rt.description, rt.base_price, ri.image_path 
                  FROM room_types rt 
                  LEFT JOIN room_images ri ON rt.id = ri.room_type_id 
                  WHERE ri.is_primary = 1";
$room_types = $conn->query($room_types_sql);

// Fetch amenities for each room type
$room_amenities = [];
if ($room_types->num_rows > 0) {
    while ($room = $room_types->fetch_assoc()) {
        $room_id = $room['id'];
        $amenities_sql = "SELECT a.name, a.icon 
                         FROM amenities a 
                         JOIN room_amenities ra ON a.id = ra.amenity_id 
                         WHERE ra.room_type_id = $room_id";
        $amenities_result = $conn->query($amenities_sql);
        $room_amenities[$room_id] = [];
        while ($amenity = $amenities_result->fetch_assoc()) {
            $room_amenities[$room_id][] = $amenity;
        }
    }
    $room_types->data_seek(0);
}

// Handle review submission
$review_error = '';
$review_success = '';
if (isset($_POST['submit_review'])) {
    ob_start();
    $booking_id = $conn->real_escape_string($_POST['booking_id']);
    $rating = intval($_POST['rating']);
    $comment = $conn->real_escape_string(trim($_POST['comment']));

    // Validate
    if ($rating < 1 || $rating > 5) {
        $review_error = "Rating must be between 1 and 5.";
    } else {
        // Check if booking belongs to user and is completed
        $check_sql = "SELECT id FROM bookings WHERE id = $booking_id AND user_id = {$_SESSION['user_id']} AND status = 'completed'";
        $check_result = $conn->query($check_sql);
        if ($check_result->num_rows == 0) {
            $review_error = "Invalid booking or booking not completed.";
        } else {
            // Check if review already exists
            $review_check_sql = "SELECT id FROM reviews WHERE booking_id = $booking_id";
            if ($conn->query($review_check_sql)->num_rows > 0) {
                $review_error = "You have already submitted a review for this booking.";
            } else {
                $sql = "INSERT INTO reviews (booking_id, rating, comment, created_at) 
                        VALUES ($booking_id, $rating, '$comment', NOW())";
                if ($conn->query($sql) === TRUE) {
                    $review_success = "Review submitted successfully!";
                } else {
                    $review_error = "Error: " . $conn->error;
                }
            }
        }
    }
    ob_end_clean();
}

// Fetch user's bookings
$bookings_sql = "SELECT b.id, b.booking_reference, b.check_in_date, b.check_out_date, b.total_price, b.status, 
                        rt.name AS room_type, r.room_number 
                 FROM bookings b 
                 JOIN rooms r ON b.room_id = r.id 
                 JOIN room_types rt ON r.room_type_id = rt.id 
                 WHERE b.user_id = {$_SESSION['user_id']} 
                 ORDER BY b.created_at DESC";
$bookings = $conn->query($bookings_sql);

// Fetch bookings eligible for review (completed bookings without reviews)
$reviewable_bookings_sql = "SELECT b.id, b.booking_reference, rt.name AS room_type 
                           FROM bookings b 
                           JOIN rooms r ON b.room_id = r.id 
                           JOIN room_types rt ON r.room_type_id = rt.id 
                           LEFT JOIN reviews rv ON b.id = rv.booking_id 
                           WHERE user_id = {$_SESSION['user_id']} 
                           AND b.status = 'completed' 
                           AND rv.id IS NULL";
$reviewable_bookings = $conn->query($reviewable_bookings_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LuxeStay Hotel - User Dashboard</title>
    
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
        
        /* Welcome Section */
        .welcome {
            padding: 80px 0 40px;
            background-color: var(--light);
            text-align: center;
        }
        
        .welcome h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary);
            position: relative;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .welcome h2::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: var(--primary);
        }
        
        .welcome p {
            font-size: 1rem;
            color: #777;
        }
        
        /* Hero Carousel */
        .hero {
            height: 100vh;
            display: flex;
            align-items: center;
            text-align: center;
            color: #fff;
            padding-top: 80px;
            background-size: cover;
            background-position: center;
            transition: background-image 1s ease-in-out;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(0, 0, 0, 0.5);
            padding: 2rem;
            border-radius: 10px;
        }
        
        .hero h1 {
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .hero p {
            font-size: 1.25rem;
            margin-bottom: 30px;
        }
        
        .hero-buttons a {
            padding: 15px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            margin: 0 10px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: #fff;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
        }
        
        .btn-secondary {
            background-color: transparent;
            color: #fff;
            border: 1px solid #fff;
        }
        
        .btn-secondary:hover {
            background-color: #fff;
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
        
        /* About Section */
        .about-content {
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .about-img {
            flex: 1;
            min-width: 300px;
            max-width: 500px;
        }
        
        .about-img img {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .about-text {
            flex: 1;
            padding: 20px;
        }
        
        .about-text h3 {
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--secondary);
        }
        
        .about-text p {
            font-size: 1rem;
            color: #777;
            line-height: 1.8;
            margin-bottom: 15px;
        }
        
        /* Amenities Section */
        .amenities {
            background-color: var(--light);
        }
        
        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .amenity-item {
            text-align: center;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .amenity-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        
        .amenity-item i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .amenity-item h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--secondary);
        }
        
        .amenity-item p {
            font-size: 0.9rem;
            color: #777;
        }
        
        /* Rooms Section */
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .room-card {
            background-color: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .room-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .room-img {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .room-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.3s ease;
        }
        
        .room-card:hover .room-img img {
            transform: scale(1.1);
        }
        
        .room-price {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: var(--primary);
            color: #fff;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .room-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-height: 350px;
        }
        
        .room-content h3 {
            font-size: 1.375rem;
            margin-bottom: 10px;
        }
        
        .room-content p {
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
        
        .btn-book {
            display: block;
            width: 100%;
            padding: 12px;
            text-align: center;
            background-color: var(--primary);
            color: #fff;
            border: none;
            border-radius: 5px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-top: auto;
        }
        
        .btn-book:hover {
            background-color: var(--secondary);
        }
        
        /* Testimonials Section */
        .testimonials {
            background-color: var(--light);
        }
        
        .testimonial-slider {
            максимум-width: 800px;
            margin: 0 auto;
        }
        
        .testimonial-item {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin: 20px;
        }
        
        .testimonial-content {
            position: relative;
            padding: 20px 0;
        }
        
        .testimonial-content::before {
            content: '\201C';
            font-size: 50px;
            color: var(--primary);
            position: absolute;
            top: -20px;
            left: -10px;
            font-family: serif;
        }
        
        .testimonial-rating {
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            margin-top: 20px;
        }
        
        .testimonial-author img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }
        
        .author-info h4 {
            font-size: 1.125rem;
            margin-bottom: 5px;
        }
        
        .author-info p {
            color: #777;
            font-size: 0.875rem;
        }
        
        /* Contact Section */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
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
        
        /* Booking History Table */
        .booking-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .booking-table th,
        .booking-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .booking-table th {
            background-color: var(--primary);
            color: #fff;
        }
        
        .booking-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .booking-table tr:hover {
            background-color: #f1f1f1;
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
            .hero h1 {
                font-size: 3rem;
            }
            .about-content {
                flex-direction: column;
                gap: 20px;
            }
            .about-img {
                max-width: 100%;
            }
            .about-text {
                padding: 0;
            }
            .rooms-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
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
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .section-title h2, .welcome h2 {
                font-size: 2rem;
            }
            
            .amenities-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .room-content {
                min-height: 300px;
            }
            
            .booking-table {
                font-size: 0.9rem;
            }
            
            .booking-table th,
            .booking-table td {
                padding: 8px;
            }
        }
        
        @media (max-width: 576px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .section-title h2, .welcome h2 {
                font-size: 1.75rem;
            }
            
            .amenity-item {
                padding: 15px;
            }
            
            .room-content {
                min-height: 280px;
            }
            
            .booking-table {
                font-size: 0.8rem;
            }
            
            .booking-table th,
            .booking-table td {
                padding: 6px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <a href="#home" class="logo">
                    <i class="fas fa-hotel"></i>
                    LuxeStay
                </a>
                
                <nav>
                    <ul id="nav-menu">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#amenities">Amenities</a></li>
                        <li><a href="#rooms">Rooms</a></li>
                        <li><a href="#testimonials">Testimonials</a></li>
                        <li><a href="#contact">Contact</a></li>
                        <li><a href="#my-account">My Account</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
                
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>
    
    <!-- Welcome Section -->
    <section class="welcome">
        <div class="container">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
            <p>Explore our luxurious accommodations and manage your bookings</p>
        </div>
    </section>
    
    <!-- Hero Carousel -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <h1>Experience Luxury & Comfort</h1>
                <p>Discover the perfect blend of elegance, comfort, and exceptional service at LuxeStay Hotel.</p>
                <div class="hero-buttons">
                    <a href="#rooms" class="btn-primary">Explore Rooms</a>
                    <a href="#contact" class="btn-secondary">Contact Us</a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- About Section -->
    <section class="about" id="about">
        <div class="container">
            <div class="section-title">
                <h2>About LuxeStay</h2>
                <p>Discover our story and what makes us special</p>
            </div>
            <div class="about-content">
                <div class="about-img">
                    <img src="https://images.unsplash.com/photo-1596436889106-be35e843f974" alt="Luxurious Hotel Lobby">
                </div>
                <div class="about-text">
                    <h3>A Sanctuary of Elegance & Tranquility</h3>
                    <p>Welcome to LuxeStay, where luxury meets comfort in perfect harmony. Nestled in a prime location, our hotel stands as a testament to refined hospitality and exceptional service.</p>
                    <p>Since our establishment, we have been dedicated to providing our guests with memorable experiences, paying meticulous attention to every detail from stylish accommodations to world-class amenities.</p>
                    <p>Our team of hospitality professionals is committed to ensuring your stay is nothing short of perfect. Whether you're traveling for business or leisure, LuxeStay offers a sophisticated retreat that caters to your every need.</p>
                    <a href="#rooms" class="btn-primary" style="display: inline-block; margin-top: 20px; padding: 12px 30px; border-radius: 30px; text-decoration: none;">Explore Our Rooms</a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Amenities Section -->
    <section class="amenities" id="amenities">
        <div class="container">
            <div class="section-title">
                <h2>Our Amenities</h2>
                <p>Experience world-class facilities designed for your comfort</p>
            </div>
            <div class="amenities-grid">
                <div class="amenity-item">
                    <i class="fas fa-wifi"></i>
                    <h4>High-Speed Wi-Fi</h4>
                    <p>Stay connected with our complimentary high-speed internet.</p>
                </div>
                <div class="amenity-item">
                    <i class="fas fa-swimming-pool"></i>
                    <h4>Infinity Pool</h4>
                    <p>Relax and unwind in our stunning rooftop infinity pool.</p>
                </div>
                <div class="amenity-item">
                    <i class="fas fa-spa"></i>
                    <h4>Luxury Spa</h4>
                    <p>Indulge in rejuvenating treatments at our world-class spa.</p>
                </div>
                <div class="amenity-item">
                    <i class="fas fa-dumbbell"></i>
                    <h4>Fitness Center</h4>
                    <p>Stay active with our state-of-the-art gym facilities.</p>
                </div>
                <div class="amenity-item">
                    <i class="fas fa-utensils"></i>
                    <h4>Gourmet Dining</h4>
                    <p>Enjoy exquisite cuisine at our on-site restaurants.</p>
                </div>
                <div class="amenity-item">
                    <i class="fas fa-concierge-bell"></i>
                    <h4>24/7 Room Service</h4>
                    <p>Experience unparalleled service any time of day.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Rooms Section -->
    <section class="rooms" id="rooms">
        <div class="container">
            <div class="section-title">
                <h2>Our Accommodations</h2>
                <p>Discover our wide range of luxurious rooms and suites</p>
            </div>
            <div class="rooms-grid">
                <?php if($room_types->num_rows > 0): ?>
                    <?php while($room = $room_types->fetch_assoc()): ?>
                        <div class="room-card">
                            <div class="room-img">
                                <img src="<?php echo htmlspecialchars($room['image_path']); ?>" alt="<?php echo htmlspecialchars($room['name']); ?>">
                                <div class="room-price">₹<?php echo number_format($room['base_price'] * $exchange_rate, 2); ?>/night</div>
                            </div>
                            <div class="room-content">
                                <h3><?php echo htmlspecialchars($room['name']); ?></h3>
                                <p><?php echo htmlspecialchars($room['description']); ?></p>
                                <ul class="amenities-list">
                                    <?php foreach ($room_amenities[$room['id']] as $amenity): ?>
                                        <li><i class="fas fa-<?php echo htmlspecialchars($amenity['icon']); ?>"></i> <?php echo htmlspecialchars($amenity['name']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <a href="booking.php?room_type_id=<?php echo $room['id']; ?>" class="btn-book">Book Now</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No rooms available at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- Testimonials Section -->
    <section class="testimonials" id="testimonials">
        <div class="container">
            <div class="section-title">
                <h2>Guest Testimonials</h2>
                <p>Hear what our guests have to say about their stay</p>
            </div>
            <div class="testimonial-slider">
                <div class="testimonial-item">
                    <div class="testimonial-content">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p>An incredible experience! The staff was so welcoming, and the rooms were luxurious beyond expectation.</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://images.unsplash.com/photo-1488426862026-3ee34a7d66df" alt="Priya Sharma">
                        <div class="author-info">
                            <h4>Priya Sharma</h4>
                            <p>Delhi, India</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-item">
                    <div class="testimonial-content">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i>
                        </div>
                        <p>The Executive Suite was stunning, and the amenities made our stay unforgettable. Highly recommend!</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d" alt="Arjun Patel">
                        <div class="author-info">
                            <h4>Arjun Patel</h4>
                            <p>Mumbai, India</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-item">
                    <div class="testimonial-content">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p>Perfect for families! The kids loved the pool, and the service was top-notch. We'll be back!</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b" alt="Sneha Reddy">
                        <div class="author-info">
                            <h4>Sneha Reddy</h4>
                            <p>Hyderabad, India</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="container">
            <div class="section-title">
                <h2>Contact Us</h2>
                <p>Get in touch with us for any inquiries</p>
            </div>
            <div class="contact-form">
                <form action="../contact.php" method="POST">
                    <div class="form-row" style="display: flex; gap: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn-submit">Send Message</button>
                </form>
            </div>
        </div>
    </section>
    
    <!-- My Account Section -->
    <section class="dashboard" id="my-account">
        <div class="container">
            <div class="section-title">
                <h2>My Account</h2>
                <p>Manage your bookings and share your experience</p>
            </div>
            
            <!-- Submit a Review -->
            <div class="section-title">
                <h2>Submit a Review</h2>
                <p>Share your experience with us</p>
            </div>
            <?php if($review_error): ?>
                <div class="error-message"><?php echo htmlspecialchars($review_error); ?></div>
            <?php endif; ?>
            <?php if($review_success): ?>
                <div class="success-message"><?php echo htmlspecialchars($review_success); ?></div>
            <?php endif; ?>
            <?php if($reviewable_bookings->num_rows > 0): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="booking_id">Select Booking</label>
                        <select id="booking_id" name="booking_id" required>
                            <?php 
                            $reviewable_bookings->data_seek(0); // Reset pointer
                            while($booking = $reviewable_bookings->fetch_assoc()): ?>
                                <option value="<?php echo $booking['id']; ?>">
                                    <?php echo htmlspecialchars($booking['booking_reference'] . ' - ' . $booking['room_type']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="rating">Rating (1-5)</label>
                        <input type="number" id="rating" name="rating" min="1" max="5" required>
                    </div>
                    <div class="form-group">
                        <label for="comment">Comment</label>
                        <textarea id="comment" name="comment" rows="5" required></textarea>
                    </div>
                    <button type="submit" name="submit_review" class="btn-submit">Submit Review</button>
                </form>
            <?php else: ?>
                <p>No bookings available for review at the moment.</p>
            <?php endif; ?>
            
            <!-- Booking History -->
            <div class="section-title">
                <h2>Booking History</h2>
                <p>View your past and upcoming bookings</p>
            </div>
            <?php if($bookings->num_rows > 0): ?>
                <table class="booking-table">
                    <thead>
                        <tr>
                            <th>Booking Reference</th>
                            <th>Room Type</th>
                            <th>Room Number</th>
                            <th>Check-In Date</th>
                            <th>Check-Out Date</th>
                            <th>Total Price (₹)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($booking = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['booking_reference']); ?></td>
                                <td><?php echo htmlspecialchars($booking['room_type']); ?></td>
                                <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($booking['check_in_date']); ?></td>
                                <td><?php echo htmlspecialchars($booking['check_out_date']); ?></td>
                                <td><?php echo number_format($booking['total_price'] * $exchange_rate, 2); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($booking['status'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>You have no bookings yet.</p>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-about">
                    <a href="#home" class="footer-logo">
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
                        <li><a href="#home">Home</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#amenities">Amenities</a></li>
                        <li><a href="#rooms">Rooms</a></li>
                        <li><a href="#testimonials">Testimonials</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="contact-info">
                    <h3 class="footer-title">Contact Info</h3>
                    <p><i class="fas fa-map-marker-alt"></i> 123 Luxury Avenue, Vadlamudi, India</p>
                    <p><i class="fas fa-phone"></i> +91 6304194629</p>
                    <p><i class="fas fa-envelope"></i> satwikapotla@.com</p>
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
        
        // Hero carousel
        const heroImages = [
            'https://images.unsplash.com/photo-1566073771259-6a8506099945',
            'https://images.unsplash.com/photo-1591088398332-8a7791972843',
            'https://images.unsplash.com/photo-1600585154340-be6161a56a0c',
            'https://images.unsplash.com/photo-1618773928121-c32242e63f39'
        ];
        let currentHeroImage = 0;
        const heroSection = document.querySelector('.hero');
        
        function changeHeroImage() {
            heroSection.style.backgroundImage = `linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url(${heroImages[currentHeroImage]})`;
            currentHeroImage = (currentHeroImage + 1) % heroImages.length;
        }
        
        changeHeroImage();
        setInterval(changeHeroImage, 5000);
    </script>
</body>
</html>