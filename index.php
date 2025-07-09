<?php
// Start session
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hotel_booking";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle login
$login_error = '';
if (isset($_POST['login'])) {
    // Start output buffering to prevent header issues
    ob_start();
    
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $room_type_id = isset($_POST['room_type_id']) ? intval($_POST['room_type_id']) : 0;
    
    // Hardcoded admin login check
    if ($email === 'satwikapotla@gmail.com' && $password === 'admin@123') {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set session variables for admin
        $_SESSION['user_id'] = 1; // Adjust if admin user ID is different
        $_SESSION['user_name'] = 'Admin User';
        $_SESSION['user_email'] = 'satwikapotla@gmail.com';
        $_SESSION['user_role'] = 'admin';
        
        // Clear output buffer and redirect
        ob_end_clean();
        header("Location: admin/dashboard.php");
        exit();
    }
    
    // Regular login process for other users
    $sql = "SELECT id, first_name, last_name, email, password, role FROM users WHERE email = '$email'";
    $result = $conn->query($sql);
    
    if ($result === false) {
        $login_error = "Database error: " . $conn->error;
    } elseif ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            // Clear output buffer
            ob_end_clean();
            
            // Redirect based on role and room_type_id
            if ($user['role'] == 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                if ($room_type_id > 0) {
                    header("Location: user/booking.php?room_type_id=$room_type_id");
                } else {
                    header("Location: user/dashboard.php");
                }
            }
            exit();
        } else {
            $login_error = "Invalid email or password";
        }
    } else {
        $login_error = "Invalid email or password";
    }
    
    // Clear output buffer if login fails
    ob_end_clean();
}

// Handle registration
$register_error = '';
$register_success = '';
if (isset($_POST['register'])) {
    // Start output buffering
    ob_start();
    
    $first_name = $conn->real_escape_string(trim($_POST['first_name']));
    $last_name = $conn->real_escape_string(trim($_POST['last_name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $country_code = $conn->real_escape_string(trim($_POST['country_code']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    $errors = [];
    
    // First name: letters and spaces, 2-50 characters
    if (!preg_match("/^[a-zA-Z\s]{2,50}$/", $first_name)) {
        $errors[] = "First name must be 2-50 characters, letters and spaces only.";
    }
    
    // Last name: letters and spaces, 2-50 characters
    if (!preg_match("/^[a-zA-Z\s]{2,50}$/", $last_name)) {
        $errors[] = "Last name must be 2-50 characters, letters and spaces only.";
    }
    
    // Email: valid format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Phone: exactly 10 digits
    if (!preg_match("/^\d{10}$/", $phone)) {
        $errors[] = "Phone number must be exactly 10 digits.";
    }
    
    // Country code: must be selected
    if (empty($country_code)) {
        $errors[] = "Please select a country code.";
    }
    
    // Password: 8+ characters, uppercase, lowercase, number, special character
    if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $password)) {
        $errors[] = "Password must be at least 8 characters, including uppercase, lowercase, number, and special character.";
    }
    
    // Confirm password: must match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // Check if email already exists
    $check_sql = "SELECT id FROM users WHERE email = '$email'";
    $result = $conn->query($check_sql);
    if ($result->num_rows > 0) {
        $errors[] = "Email already exists.";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $full_phone = $country_code . $phone; // e.g., +919876543210
        
        $sql = "INSERT INTO users (first_name, last_name, email, password, phone, role, created_at) 
                VALUES ('$first_name', '$last_name', '$email', '$hashed_password', '$full_phone', 'user', NOW())";
        
        if ($conn->query($sql) === TRUE) {
            $register_success = "Registration successful! Please login.";
        } else {
            $register_error = "Error: " . $conn->error;
        }
    } else {
        $register_error = implode("<br>", $errors);
    }
    
    // Clear output buffer
    ob_end_clean();
}

// Get room types and their amenities
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
    $room_types->data_seek(0); // Reset pointer for main loop
}

// INR conversion rate (1 USD ≈ 85 INR)
$exchange_rate = 85;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LuxeStay Hotel - Experience Luxury and Comfort</title>
    
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
        
        .auth-buttons a {
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-login {
            background-color: transparent;
            color: var(--secondary);
            border: 1px solid var(--primary);
            margin-right: 10px;
        }
        
        .btn-login:hover {
            background-color: var(--primary);
            color: #fff;
        }
        
        .btn-register {
            background-color: var(--primary);
            color: #fff;
        }
        
        .btn-register:hover {
            background-color: var(--secondary);
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary);
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
            min-height: 350px; /* Ensure consistent height */
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
            margin-top: auto; /* Push to bottom */
        }
        
        .btn-book:hover {
            background-color: var(--secondary);
        }
        
        /* Testimonials Section */
        .testimonials {
            background-color: var(--light);
        }
        
        .testimonial-slider {
            max-width: 800px;
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
            object-position: center; /* Center the image */
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
        
        .phone-group {
            display: flex;
            gap: 10px;
        }
        
        .phone-group select {
            width: 30%;
            max-width: 120px;
        }
        
        .phone-group input {
            flex-grow: 1;
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
            
            .auth-buttons {
                display: none;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .amenities-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .room-content {
                min-height: 300px; /* Smaller height for smaller screens */
            }
            
            .phone-group {
                flex-direction: column;
            }
            
            .phone-group select {
                width: 100%;
                max-width: none;
            }
        }
        
        @media (max-width: 576px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .section-title h2 {
                font-size: 1.75rem;
            }
            
            .modal-content {
                padding: 20px;
                margin: 20px;
            }
            
            .amenity-item {
                padding: 15px;
            }
            
            .room-content {
                min-height: 280px; /* Even smaller for mobile */
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
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
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <li>
                                <a href="<?php echo $_SESSION['user_role'] == 'admin' ? './admin/dashboard.php' : 'user/dashboard.php'; ?>">
                                    My Account
                                </a>
                            </li>
                            <li><a href="logout.php">Logout</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <?php if(!isset($_SESSION['user_id'])): ?>
                    <div class="auth-buttons">
                        <a href="#" class="btn-login" id="loginBtn">Login</a>
                        <a href="#" class="btn-register" id="registerBtn">Register</a>
                    </div>
                <?php endif; ?>
                
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>
    
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
    
    <!-- Login Modal -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('loginModal')">×</span>
            <h2 class="modal-title">Login</h2>
            <?php if($login_error): ?>
                <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            <form method="POST" id="loginForm">
                <input type="hidden" name="room_type_id" id="login-room-type-id">
                <div class="form-group">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required>
                </div>
                <button type="submit" name="login" class="btn-submit">Login</button>
            </form>
            <div class="form-footer">
                <p>Don't have an account? <a href="#" id="showRegister">Register</a></p>
            </div>
        </div>
    </div>
    
    <!-- Register Modal -->
    <div class="modal" id="registerModal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('registerModal')">×</span>
            <h2 class="modal-title">Register</h2>
            <?php if($register_error): ?>
                <div class="error-message"><?php echo htmlspecialchars($register_error); ?></div>
            <?php endif; ?>
            <?php if($register_success): ?>
                <div class="success-message"><?php echo htmlspecialchars($register_success); ?></div>
            <?php endif; ?>
            <form method="POST" id="registerForm" novalidate>
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" pattern="[A-Za-z\s]{2,50}" title="First name must be 2-50 characters, letters and spaces only" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" pattern="[A-Za-z\s]{2,50}" title="Last name must be 2-50 characters, letters and spaces only" required>
                </div>
                <div class="form-group">
                    <label for="register-email">Email</label>
                    <input type="email" id="register-email" name="email" required>
                </div>
                <div class="form-group phone-group">
                    <label for="phone">Phone</label>
                    <div class="phone-group">
                        <select id="country_code" name="country_code" required>
                            <option value="+91" selected>India (+91)</option>
                            <option value="+1">United States (+1)</option>
                            <option value="+44">United Kingdom (+44)</option>
                            <option value="+61">Australia (+61)</option>
                            <!-- Add more country codes as needed -->
                        </select>
                        <input type="tel" id="phone" name="phone" pattern="\d{10}" title="Phone number must be exactly 10 digits" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="register-password">Password</label>
                    <input type="password" id="register-password" name="password" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$" title="Password must be at least 8 characters, including uppercase, lowercase, number, and special character" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="register" class="btn-submit">Register</button>
            </form>
            <div class="form-footer">
                <p>Already have an account? <a href="#" id="showLogin">Login</a></p>
            </div>
        </div>
    </div>
    
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
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <a href="user/booking.php?room_type_id=<?php echo $room['id']; ?>" class="btn-book">Book Now</a>
                                <?php else: ?>
                                    <a href="#" class="btn-book" onclick="openBookModal(<?php echo $room['id']; ?>)">Book Now</a>
                                <?php endif; ?>
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
                <form action="contact.php" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
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
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-about">
                    <a href="index.php" class="footer-logo">
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
                    <p><i class="fas fa-envelope"></i> satwikapotla.com</p>
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
        
        // Modal handling
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Open book modal and store room_type_id
        function openBookModal(roomTypeId) {
            sessionStorage.setItem('booking_room_type_id', roomTypeId);
            document.getElementById('login-room-type-id').value = roomTypeId;
            openModal('loginModal');
        }
        
        document.getElementById('loginBtn').addEventListener('click', () => openModal('loginModal'));
        document.getElementById('registerBtn').addEventListener('click', () => openModal('registerModal'));
        document.getElementById('showRegister').addEventListener('click', () => {
            closeModal('loginModal');
            openModal('registerModal');
        });
        document.getElementById('showLogin').addEventListener('click', () => {
            closeModal('registerModal');
            openModal('loginModal');
        });
        
        // Close modal on outside click
        window.addEventListener('click', (event) => {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
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
        
        // Client-side validation for registration form
        document.getElementById('registerForm').addEventListener('submit', function(event) {
            const password = document.getElementById('register-password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const phone = document.getElementById('phone').value;
            
            // Check if passwords match
            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Passwords do not match.');
                return;
            }
            
            // Check phone number length
            if (!/^\d{10}$/.test(phone)) {
                event.preventDefault();
                alert('Phone number must be exactly 10 digits.');
                return;
            }
        });
    </script>
</body>
</html>