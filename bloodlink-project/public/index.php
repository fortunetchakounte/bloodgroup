<?php
session_start();
$page_title = "BloodLink - Blood Donation Management System";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --blood: #8B0000;
            --blood-dark: #5A0000;
            --blood-darker: #2F0000;
            --blood-light: #B22222;
            --blood-mist: #FFF0F0;
            --dark: #1A1A1A;
            --dark-gray: #2D2D2D;
            --gray: #4A4A4A;
            --light-gray: #F8F8F8;
            --white: #FFFFFF;
            --border: #E0E0E0;
            --shadow: 0 4px 20px rgba(139, 0, 0, 0.1);
            --shadow-hover: 0 8px 30px rgba(139, 0, 0, 0.2);
        }

        body {
            font-family: 'Rajdhani', sans-serif;
            color: var(--dark);
            line-height: 1.5;
            font-weight: 500;
            background: var(--white);
        }

        /* Blood Drip Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 100vh;
            background: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"%3E%3Cpath d="M0,0 L100,0 L100,100 L0,100 Z" fill="%238B0000" opacity="0.02"/%3E%3C/svg%3E');
            pointer-events: none;
            z-index: -1;
        }

        /* Blood Drop Pattern */
        .blood-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"%3E%3Cpath d="M50 20 Q60 35 50 50 Q40 35 50 20" fill="%238B0000" opacity="0.03"/%3E%3C/svg%3E') repeat;
            pointer-events: none;
            z-index: -1;
        }

        /* Navigation */
        .navbar {
            background: var(--white);
            border-bottom: 3px solid var(--blood);
            padding: 0.8rem 5%;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: var(--shadow);
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            background: var(--blood);
            width: 45px;
            height: 45px;
            border-radius: 50% 50% 0 50%;
            transform: rotate(45deg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 10px rgba(139, 0, 0, 0.3);
        }

        .logo-icon i {
            transform: rotate(-45deg);
        }

        .logo-text {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--dark);
            letter-spacing: -0.5px;
        }

        .logo-text span {
            color: var(--blood);
            font-weight: 700;
        }

        .nav-links {
            display: flex;
            gap: 2.5rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--dark-gray);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: color 0.2s;
            padding: 0.5rem 0;
            border-bottom: 2px solid transparent;
        }

        .nav-links a:hover {
            color: var(--blood);
            border-bottom-color: var(--blood);
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.7rem 1.8rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-login {
            background: transparent;
            color: var(--blood);
            border: 2px solid var(--blood);
        }

        .btn-login:hover {
            background: var(--blood);
            color: white;
        }

        .btn-primary {
            background: var(--blood);
            color: white;
            border: 2px solid var(--blood);
        }

        .btn-primary:hover {
            background: var(--blood-dark);
            border-color: var(--blood-dark);
        }

        /* Hero Section */
        .hero {
            padding: 10rem 5% 5rem;
            background: linear-gradient(135deg, var(--white) 0%, var(--blood-mist) 100%);
            border-bottom: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '🩸';
            position: absolute;
            top: 10%;
            right: 5%;
            font-size: 15rem;
            opacity: 0.03;
            transform: rotate(15deg);
            pointer-events: none;
        }

        .hero::after {
            content: '🩸';
            position: absolute;
            bottom: 5%;
            left: 5%;
            font-size: 12rem;
            opacity: 0.03;
            transform: rotate(-10deg);
            pointer-events: none;
        }

        .hero-container {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
        }

        .hero-tag {
            display: inline-block;
            background: var(--blood);
            color: white;
            padding: 0.4rem 1rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1.5rem;
            border-radius: 3px;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }

        .hero-title span {
            color: var(--blood);
            position: relative;
            display: inline-block;
        }

        .hero-title span::before {
            content: '🩸';
            font-size: 2rem;
            position: absolute;
            left: -30px;
            top: -10px;
            opacity: 0.5;
        }

        .hero-subtitle {
            font-size: 1.1rem;
            color: var(--gray);
            margin-bottom: 2.5rem;
            line-height: 1.8;
            max-width: 500px;
            font-weight: 500;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 3rem;
        }

        .btn-outline {
            background: transparent;
            color: var(--dark);
            border: 2px solid var(--border);
        }

        .btn-outline:hover {
            border-color: var(--blood);
            color: var(--blood);
        }

        .hero-stats {
            display: flex;
            gap: 3rem;
            border-top: 1px solid var(--border);
            padding-top: 2rem;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .stat-item::before {
            content: '🩸';
            position: absolute;
            left: -20px;
            top: 0;
            font-size: 0.8rem;
            color: var(--blood);
            opacity: 0.5;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--blood);
            line-height: 1.2;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .hero-image {
            background: var(--white);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--blood), transparent);
        }

        .hero-image img {
            width: 100%;
            max-width: 400px;
            display: block;
            margin: 0 auto;
            filter: drop-shadow(0 10px 20px rgba(139, 0, 0, 0.1));
        }

        .blood-drop-decoration {
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 3rem;
            opacity: 0.1;
            transform: rotate(10deg);
        }

        .stats-mini {
            display: flex;
            justify-content: space-around;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .stats-mini-item {
            text-align: center;
            position: relative;
        }

        .stats-mini-item::after {
            content: '🩸';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.7rem;
            opacity: 0.3;
        }

        .stats-mini-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--blood);
        }

        .stats-mini-label {
            font-size: 0.7rem;
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Blood Type Bar */
        .blood-type-bar {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
            justify-content: center;
        }

        .blood-type {
            width: 40px;
            height: 40px;
            background: var(--blood-mist);
            border: 2px solid var(--blood);
            color: var(--blood);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            border-radius: 50%;
        }

        /* Features Section */
        .features {
            padding: 5rem 5%;
            background: var(--white);
            position: relative;
        }

        .features::before {
            content: '🩸🩸🩸';
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 3rem;
            opacity: 0.03;
            transform: rotate(5deg);
        }

        .section-header {
            text-align: center;
            margin-bottom: 3.5rem;
        }

        .section-tag {
            display: inline-block;
            background: var(--blood);
            color: white;
            padding: 0.3rem 1rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
            border-radius: 3px;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark);
        }

        .section-title i {
            color: var(--blood);
            margin: 0 0.5rem;
        }

        .section-subtitle {
            color: var(--gray);
            font-size: 1rem;
            max-width: 600px;
            margin: 0 auto;
            font-weight: 500;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            background: var(--white);
            padding: 2rem;
            border: 1px solid var(--border);
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '🩸';
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 2rem;
            opacity: 0.1;
            transform: rotate(15deg);
        }

        .feature-card:hover {
            border-color: var(--blood);
            box-shadow: var(--shadow-hover);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: rgba(139, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: var(--blood);
            font-size: 1.8rem;
            border-radius: 50% 50% 0 50%;
            transform: rotate(45deg);
        }

        .feature-icon i {
            transform: rotate(-45deg);
        }

        .feature-card h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
            position: relative;
        }

        .feature-card h3::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 30px;
            height: 2px;
            background: var(--blood);
        }

        .feature-card p {
            color: var(--gray);
            font-size: 0.9rem;
            line-height: 1.6;
            font-weight: 500;
        }

        /* Process Section */
        .process {
            padding: 5rem 5%;
            background: var(--light-gray);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            position: relative;
        }

        .process::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--blood), transparent, var(--blood));
        }

        .process-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        .process-card {
            background: var(--white);
            padding: 2rem;
            border: 1px solid var(--border);
            position: relative;
            text-align: center;
        }

        .process-number {
            font-size: 3rem;
            font-weight: 700;
            color: rgba(139, 0, 0, 0.1);
            position: absolute;
            top: 1rem;
            right: 1rem;
            line-height: 1;
        }

        .process-icon {
            font-size: 2.5rem;
            color: var(--blood);
            margin-bottom: 1rem;
            opacity: 0.8;
        }

        .process-card h3 {
            font-size: 1.2rem;
            margin-bottom: 0.8rem;
            font-weight: 700;
            position: relative;
        }

        .process-card p {
            color: var(--gray);
            font-size: 0.9rem;
            line-height: 1.6;
            font-weight: 500;
            position: relative;
        }

        /* Emergency Section */
        .emergency {
            padding: 5rem 5%;
            background: var(--blood-darker);
            position: relative;
            overflow: hidden;
        }

        .emergency::before {
            content: '🩸🩸🩸🩸🩸';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            font-size: 5rem;
            opacity: 0.05;
            display: flex;
            align-items: center;
            justify-content: space-around;
            pointer-events: none;
        }

        .emergency-container {
            max-width: 1000px;
            margin: 0 auto;
            text-align: center;
            color: white;
            position: relative;
        }

        .emergency-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }

        .emergency h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .emergency p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            font-weight: 500;
        }

        .emergency .btn {
            background: white;
            color: var(--blood-darker);
            border: none;
            font-size: 1rem;
            padding: 1rem 3rem;
        }

        .emergency .btn:hover {
            background: var(--light-gray);
            transform: translateY(-2px);
        }

        /* Blood Inventory Preview */
        .inventory-preview {
            padding: 3rem 5%;
            background: var(--white);
        }

        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 1rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .inventory-item {
            text-align: center;
            padding: 1rem 0.5rem;
            background: var(--blood-mist);
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .inventory-item:hover {
            border-color: var(--blood);
            transform: translateY(-3px);
        }

        .inventory-blood {
            font-size: 2rem;
            margin-bottom: 0.3rem;
        }

        .inventory-type {
            font-weight: 700;
            color: var(--blood);
            font-size: 1.2rem;
        }

        .inventory-level {
            font-size: 0.8rem;
            color: var(--gray);
            font-weight: 600;
        }

        .critical {
            color: var(--blood);
            font-weight: 700;
        }

        /* Footer */
        footer {
            background: var(--dark);
            color: white;
            padding: 4rem 5% 2rem;
            position: relative;
        }

        footer::before {
            content: '🩸';
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 3rem;
            opacity: 0.1;
        }

        footer::after {
            content: '🩸';
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 3rem;
            opacity: 0.1;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1.5fr;
            gap: 3rem;
            margin-bottom: 3rem;
            position: relative;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
        }

        .footer-logo-icon {
            background: var(--blood);
            width: 45px;
            height: 45px;
            border-radius: 50% 50% 0 50%;
            transform: rotate(45deg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .footer-logo-icon i {
            transform: rotate(-45deg);
        }

        .footer-logo-text {
            font-weight: 700;
            font-size: 1.3rem;
            color: white;
        }

        .footer-about p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            line-height: 1.7;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .social-links a:hover {
            background: var(--blood);
        }

        .footer-links h4 {
            font-size: 1rem;
            margin-bottom: 1.5rem;
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
        }

        .footer-links h4::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 30px;
            height: 2px;
            background: var(--blood);
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.8rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }

        .footer-contact p {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .footer-contact p i {
            color: var(--blood);
            width: 20px;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .footer-bottom p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Blood drop divider */
        .blood-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }

        .blood-divider i {
            color: var(--blood);
            font-size: 1rem;
            opacity: 0.5;
        }

        .blood-divider span {
            width: 50px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--blood), transparent);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-subtitle {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-buttons {
                justify-content: center;
            }

            .hero-stats {
                justify-content: center;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .process-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .inventory-grid {
                grid-template-columns: repeat(4, 1fr);
            }

            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .process-grid {
                grid-template-columns: 1fr;
            }

            .inventory-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer-logo {
                justify-content: center;
            }

            .footer-links h4::after {
                left: 50%;
                transform: translateX(-50%);
            }

            .social-links {
                justify-content: center;
            }

            .footer-contact p {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 2rem;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .hero-stats {
                flex-direction: column;
                gap: 1.5rem;
            }

            .stat-item {
                align-items: center;
            }

            .stat-item::before {
                left: -10px;
            }
        }
    </style>
</head>
<body>
    <div class="blood-pattern"></div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-tint"></i>
                </div>
                <span class="logo-text">BLOOD<span>LINK</span></span>
            </a>
            
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="about.php">About</a>
                <a href="contact.php">Contact</a>
            </div>
            
            <div class="nav-buttons">
                <a href="login.php" class="btn btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
                <a href="register.php" class="btn btn-primary">
                    <i class="fas fa-tint"></i> Register
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-tag">
                    <i class="fas fa-tint"></i> BLOOD DONATION SYSTEM
                </div>
                <h1 class="hero-title">
                    Every Drop <span>Saves</span> a Life
                </h1>
                <p class="hero-subtitle">
                    A secure, centralized platform connecting blood donors with healthcare facilities. 
                    Real-time blood inventory tracking and emergency response coordination.
                </p>
                
                <div class="hero-buttons">
                    <a href="register.php" class="btn btn-primary">
                        <i class="fas fa-tint"></i> Become a Donor
                    </a>
                    <a href="hospital-register.php" class="btn btn-outline">
                        <i class="fas fa-hospital"></i> Register Hospital
                    </a>
                </div>
                
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-number">10,000+</span>
                        <span class="stat-label">Active Donors</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">200+</span>
                        <span class="stat-label">Partner Hospitals</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">15,000+</span>
                        <span class="stat-label">Units Collected</span>
                    </div>
                </div>

                <div class="blood-type-bar">
                    <div class="blood-type">A+</div>
                    <div class="blood-type">A-</div>
                    <div class="blood-type">B+</div>
                    <div class="blood-type">B-</div>
                    <div class="blood-type">AB+</div>
                    <div class="blood-type">AB-</div>
                    <div class="blood-type">O+</div>
                    <div class="blood-type">O-</div>
                </div>
            </div>
            
            <div class="hero-image">
                <div class="blood-drop-decoration">🩸🩸🩸</div>
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 350'%3E%3Ccircle cx='200' cy='150' r='100' fill='%238B0000' opacity='0.1'/%3E%3Cpath d='M200 50 L250 130 L320 130 L270 190 L290 270 L200 220 L110 270 L130 190 L80 130 L150 130 Z' fill='%238B0000' opacity='0.2'/%3E%3Cpath d='M200 80 L230 140 L290 140 L250 180 L270 250 L200 200 L130 250 L150 180 L110 140 L170 140 Z' fill='%238B0000' opacity='0.3'/%3E%3Ccircle cx='200' cy='150' r='40' fill='%238B0000' opacity='0.2'/%3E%3Ctext x='200' y='170' font-size='60' text-anchor='middle' fill='%238B0000' font-family='Arial'%3E🩸%3C/text%3E%3C/svg%3E" alt="Blood Donation System">
                
                <div class="stats-mini">
                    <div class="stats-mini-item">
                        <div class="stats-mini-value">A+</div>
                        <div class="stats-mini-label">Critical</div>
                    </div>
                    <div class="stats-mini-item">
                        <div class="stats-mini-value">O-</div>
                        <div class="stats-mini-label">Low</div>
                    </div>
                    <div class="stats-mini-item">
                        <div class="stats-mini-value">B+</div>
                        <div class="stats-mini-label">Normal</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Blood Inventory Preview -->
    <section class="inventory-preview">
        <div class="section-header">
            <div class="section-tag"><i class="fas fa-tint"></i> CURRENT STATUS</div>
            <h2 class="section-title">Blood Inventory <i>🩸</i> Levels</h2>
            <p class="section-subtitle">Real-time stock availability by blood type</p>
        </div>
        
        <div class="inventory-grid">
            <div class="inventory-item">
                <div class="inventory-blood">🩸</div>
                <div class="inventory-type">A+</div>
                <div class="inventory-level critical">CRITICAL</div>
            </div>
            <div class="inventory-item">
                <div class="inventory-blood">🩸</div>
                <div class="inventory-type">A-</div>
                <div class="inventory-level">LOW</div>
            </div>
            <div class="inventory-item">
                <div class="inventory-blood">🩸</div>
                <div class="inventory-type">B+</div>
                <div class="inventory-level">NORMAL</div>
            </div>
            <div class="inventory-item">
                <div class="inventory-blood">🩸</div>
                <div class="inventory-type">B-</div>
                <div class="inventory-level">LOW</div>
            </div>
            <div class="inventory-item">
                <div class="inventory-blood">🩸</div>
                <div class="inventory-type">AB+</div>
                <div class="inventory-level">NORMAL</div>
            </div>
            <div class="inventory-item">
                <div class="inventory-blood">🩸</div>
                <div class="inventory-type">AB-</div>
                <div class="inventory-level critical">CRITICAL</div>
            </div>
            <div class="inventory-item">
                <div class="inventory-blood">🩸</div>
                <div class="inventory-type">O+</div>
                <div class="inventory-level">LOW</div>
            </div>
            <div class="inventory-item">
                <div class="inventory-blood">🩸</div>
                <div class="inventory-type">O-</div>
                <div class="inventory-level critical">CRITICAL</div>
            </div>
        </div>
        
        <div class="blood-divider">
            <span></span>
            <i class="fas fa-tint"></i>
            <i class="fas fa-tint"></i>
            <i class="fas fa-tint"></i>
            <span></span>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="section-header">
            <div class="section-tag"><i class="fas fa-tint"></i> FEATURES</div>
            <h2 class="section-title">Comprehensive Blood <i>🩸</i> Management</h2>
            <p class="section-subtitle">Enterprise-grade solution for healthcare facilities</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-users"></i></div>
                <h3>Donor Management</h3>
                <p>Centralized database with donor history, eligibility tracking, and communication</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-boxes"></i></div>
                <h3>Inventory Control</h3>
                <p>Real-time blood stock monitoring with expiry alerts and usage tracking</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-hospital"></i></div>
                <h3>Hospital Integration</h3>
                <p>Seamless coordination between blood banks and medical facilities</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-chart-bar"></i></div>
                <h3>Analytics</h3>
                <p>Comprehensive reporting on donations and campaign effectiveness</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-bell"></i></div>
                <h3>Emergency Alerts</h3>
                <p>Automated notification system for urgent blood requirements</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-calendar-alt"></i></div>
                <h3>Campaign Management</h3>
                <p>Plan and track blood donation drives with targeted donor outreach</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Security</h3>
                <p>HIPAA-compliant data protection with role-based access control</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-sync-alt"></i></div>
                <h3>Real-time Updates</h3>
                <p>Synchronized data across all facilities with instant availability</p>
            </div>
        </div>
    </section>

    <!-- Process Section -->
    <section class="process">
        <div class="section-header">
            <div class="section-tag"><i class="fas fa-tint"></i> PROCESS</div>
            <h2 class="section-title">How It <i>🩸</i> Works</h2>
            <p class="section-subtitle">Streamlined workflow for donors and hospitals</p>
        </div>
        
        <div class="process-grid">
            <div class="process-card">
                <div class="process-number">01</div>
                <div class="process-icon">🩸</div>
                <h3>Registration</h3>
                <p>Donors and hospitals create verified accounts with necessary credentials</p>
            </div>
            <div class="process-card">
                <div class="process-number">02</div>
                <div class="process-icon">🩸</div>
                <h3>Verification</h3>
                <p>Medical eligibility and hospital credentials are validated</p>
            </div>
            <div class="process-card">
                <div class="process-number">03</div>
                <div class="process-icon">🩸</div>
                <h3>Matching</h3>
                <p>Algorithm matches donors with hospitals based on blood type and location</p>
            </div>
            <div class="process-card">
                <div class="process-number">04</div>
                <div class="process-icon">🩸</div>
                <h3>Fulfillment</h3>
                <p>Donations are scheduled, completed, and tracked in the system</p>
            </div>
        </div>
    </section>

    <!-- Emergency Section -->
    <section class="emergency">
        <div class="emergency-container">
            <div class="emergency-icon">🩸🩸🩸</div>
            <h2>Emergency Blood Requests</h2>
            <p>Critical shortages reported. Hospitals need immediate response.</p>
            <a href="emergency.php" class="btn btn-primary">
                <i class="fas fa-exclamation-triangle"></i> View Emergency Needs
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-about">
                <div class="footer-logo">
                    <div class="footer-logo-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <span class="footer-logo-text">BLOODLINK</span>
                </div>
                <p>Enterprise blood donation management system connecting donors with healthcare facilities. Founded 2024.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-x-twitter"></i></a>
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                </div>
            </div>
            
            <div class="footer-links">
                <h4>Company</h4>
                <ul>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="privacy.php">Privacy</a></li>
                    <li><a href="terms.php">Terms</a></li>
                </ul>
            </div>
            
            <div class="footer-links">
                <h4>Resources</h4>
                <ul>
                    <li><a href="eligibility.php">Eligibility</a></li>
                    <li><a href="faq.php">FAQ</a></li>
                </ul>
            </div>
            
            <div class="footer-contact">
                <h4>Contact</h4>
                <p><i class="fas fa-map-marker-alt"></i> Douala, Cameroon</p>
                <p><i class="fas fa-phone"></i> +237 233 434 837</p>
                <p><i class="fas fa-envelope"></i> admin@bloodlink.cm</p>
                <p><i class="fas fa-clock"></i> 24/7 Emergency Support</p>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>© 2024 BloodLink. All rights reserved. | Made with 🩸 to save lives</p>
        </div>
    </footer>

    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.padding = '0.5rem 5%';
            } else {
                navbar.style.padding = '0.8rem 5%';
            }
        });
    </script>
</body>
</html>