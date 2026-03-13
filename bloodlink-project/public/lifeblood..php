<?php
// lifeblood.php - Tableau de bord principal
session_start();

// Vérifier la connexion
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Données de l'utilisateur
$user_email = $_SESSION['user_email'];
$user_name = $_SESSION['user_name'];

// Données de démonstration
$donors = [
    [
        'id' => 1,
        'name' => "Jean Dupont",
        'phone' => "237 123 456 789",
        'email' => "jean.dupont@email.com",
        'age' => 32,
        'bloodType' => "O+",
        'city' => "Yaoundé",
        'lastDonation' => "2023-10-15",
        'status' => "active"
    ],
    [
        'id' => 2,
        'name' => "Marie Kamga",
        'phone' => "237 987 654 321",
        'email' => "marie.kamga@email.com",
        'age' => 28,
        'bloodType' => "A-",
        'city' => "Douala",
        'lastDonation' => "2023-09-20",
        'status' => "active"
    ],
    [
        'id' => 3,
        'name' => "Paul Mbappe",
        'phone' => "237 555 123 456",
        'email' => "paul.mbappe@email.com",
        'age' => 45,
        'bloodType' => "B+",
        'city' => "Bafoussam",
        'lastDonation' => "2023-08-10",
        'status' => "active"
    ]
];

$hospitals = [
    [
        'id' => 1,
        'name' => "Hôpital Central de Yaoundé",
        'city' => "Yaoundé",
        'phone' => "237 111 111 111",
        'email' => "contact@hcy.cm",
        'status' => "active"
    ],
    [
        'id' => 2,
        'name' => "Hôpital Général de Douala",
        'city' => "Douala",
        'phone' => "237 222 222 222",
        'email' => "contact@hgd.cm",
        'status' => "active"
    ]
];

// Statistiques
$totalDonors = count($donors);
$today = date('Y-m-d');
$todayDonations = 2; // Valeur fixe pour l'exemple
$livesSaved = $totalDonors * 3;
?>




















<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeBlood - Plateforme Intelligente de Don de Sang</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
 /* Ajoutez juste ces styles pour la déconnexion */
        .logout-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: 10px;
            text-decoration: underline;
        }
        
        .logout-btn:hover {
            color: white;
        }




        :root {
            --primary-color: #ff4757;
            --primary-dark: #ff3838;
            --primary-light: #ff6b81;
            --secondary-color: #3742fa;
            --accent-color: #00d2d3;
            --dark-color: #2f3542;
            --light-color: #f1f2f6;
            --success-color: #2ed573;
            --warning-color: #ff9f43;
            --danger-color: #ff3838;
            --info-color: #1e90ff;
            --gradient-primary: linear-gradient(135deg, #ff4757, #ff3838);
            --gradient-secondary: linear-gradient(135deg, #3742fa, #5352ed);
            --gradient-success: linear-gradient(135deg, #2ed573, #26de81);
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
            --radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            color: #fff;
            line-height: 1.5;
            overflow-x: hidden;
            min-height: 100vh;
            position: relative;
            font-size: 14px;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(255, 71, 87, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(55, 66, 250, 0.1) 0%, transparent 50%);
            z-index: -1;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }

        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Navigation Styling */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(47, 53, 66, 0.98);
            backdrop-filter: blur(20px);
            border-bottom: 2px solid var(--primary-color);
            z-index: 1000;
            padding: 10px 0;
            transition: var(--transition);
        }

        .navbar.scrolled {
            padding: 8px 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            font-size: 1.4rem;
            font-weight: 700;
            color: white;
        }

        .logo-icon {
            margin-right: 8px;
            color: var(--primary-color);
            animation: heartbeat 2s infinite;
            font-size: 1.5rem;
        }

        @keyframes heartbeat {
            0% { transform: scale(1); }
            5% { transform: scale(1.1); }
            10% { transform: scale(1); }
            15% { transform: scale(1.1); }
            20% { transform: scale(1); }
            100% { transform: scale(1); }
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 5px;
        }

        .nav-link a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            font-size: 0.9rem;
        }

        .nav-link a i {
            margin-right: 6px;
            font-size: 1rem;
        }

        .nav-link a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--gradient-primary);
            transition: var(--transition);
            z-index: -1;
        }

        .nav-link a:hover,
        .nav-link a.active {
            color: white;
            transform: translateY(-2px);
        }

        .nav-link a:hover::before,
        .nav-link a.active::before {
            left: 0;
        }

        .user-profile {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            transition: var(--transition);
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-size: 1rem;
        }

        .user-name {
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Hero Section */
        .hero-section {
            min-height: 90vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding-top: 70px;
            background: linear-gradient(135deg, rgba(15, 12, 41, 0.9), rgba(36, 36, 62, 0.9));
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                url('https://images.unsplash.com/photo-1582750433449-648ed127bb54?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80') center/cover no-repeat;
            opacity: 0.2;
            z-index: -1;
        }

        .hero-content {
            width: 100%;
            text-align: center;
            padding: 2rem 0;
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #ff6b81);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            line-height: 1.2;
            text-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
        }

        .hero-subtitle {
            font-size: 1rem;
            max-width: 700px;
            margin: 0 auto 2rem;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            max-width: 800px;
            margin: 2rem auto;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: var(--radius);
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: var(--shadow-hover);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            color: white;
        }

        .stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Emergency Banner */
        .emergency-banner {
            background: linear-gradient(135deg, #ff3838, #ff6b81);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin: 2rem auto;
            max-width: 1100px;
            animation: pulse 2s infinite;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 5px 20px rgba(255, 56, 56, 0.3); }
            50% { transform: scale(1.01); box-shadow: 0 8px 25px rgba(255, 56, 56, 0.4); }
            100% { transform: scale(1); box-shadow: 0 5px 20px rgba(255, 56, 56, 0.3); }
        }

        .emergency-content {
            flex: 1;
            padding-right: 1.5rem;
        }

        .emergency-title {
            font-size: 1.4rem;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
        }

        .emergency-title i {
            margin-right: 10px;
            font-size: 1.5rem;
        }

        /* Main Dashboard */
        .dashboard-section {
            padding: 3rem 0;
            background: rgba(15, 12, 41, 0.7);
        }

        .section-title {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 0.8rem;
            background: linear-gradient(135deg, #fff, var(--primary-light));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .section-subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 3rem;
            font-size: 1rem;
        }

        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border-radius: var(--radius);
            padding: 2rem;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.12);
            box-shadow: var(--shadow-hover);
        }

        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 1.2rem;
            color: var(--primary-color);
        }

        .card-title {
            font-size: 1.3rem;
            margin-bottom: 0.8rem;
            color: white;
        }

        .card-description {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 1.5rem;
            line-height: 1.6;
            font-size: 0.9rem;
        }

        /* Tabs Section */
        .tabs-section {
            background: rgba(47, 53, 66, 0.8);
            border-radius: var(--radius);
            overflow: hidden;
            margin-top: 3rem;
            box-shadow: var(--shadow);
        }

        .tabs-header {
            display: flex;
            background: linear-gradient(135deg, var(--dark-color), #1a1a2e);
            overflow-x: auto;
            padding: 0 1.5rem;
        }

        .tab-btn {
            padding: 1.2rem 1.5rem;
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            display: flex;
            align-items: center;
            position: relative;
        }

        .tab-btn i {
            margin-right: 8px;
            font-size: 1.1rem;
        }

        .tab-btn:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-btn.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .tab-content {
            display: none;
            padding: 2rem;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tab-content.active {
            display: block;
        }

        /* Enhanced Forms */
        .form-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
            display: flex;
            align-items: center;
        }

        .form-title i {
            margin-right: 12px;
            color: var(--primary-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.6rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-label i {
            margin-right: 8px;
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1.2rem;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.2);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
        }

        /* Amélioration de la sélection dans les formulaires */
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.3);
            transform: translateY(-2px);
        }

        /* Style amélioré pour les selects */
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23ff4757' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 14px;
            padding-right: 2.5rem;
            cursor: pointer;
        }

        select.form-control option {
            background-color: var(--dark-color);
            color: white;
            padding: 8px;
        }

        select.form-control option:checked {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }

        select.form-control option:hover {
            background: var(--primary-light);
        }

        /* Enhanced Cards */
        .data-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .data-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 71, 87, 0.05), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .data-card:hover::before {
            transform: translateX(100%);
        }

        .data-card:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: var(--shadow);
        }

        .data-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
        }

        .data-title {
            font-size: 1.2rem;
            color: white;
            display: flex;
            align-items: center;
        }

        .data-title i {
            margin-right: 10px;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .data-badge {
            background: var(--gradient-primary);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Liste des donneurs et hôpitaux - FIXED */
        .donors-list-container,
        .hospitals-list-container {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 10px;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius);
            padding: 1rem;
        }

        .donors-list-container::-webkit-scrollbar,
        .hospitals-list-container::-webkit-scrollbar {
            width: 6px;
        }

        .donors-list-container::-webkit-scrollbar-track,
        .hospitals-list-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 3px;
        }

        .donors-list-container::-webkit-scrollbar-thumb,
        .hospitals-list-container::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 3px;
        }

        /* Buttons */
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn i {
            margin-right: 8px;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 5px 20px rgba(255, 71, 87, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 8px 25px rgba(255, 71, 87, 0.4);
        }

        .btn-secondary {
            background: var(--gradient-secondary);
            color: white;
            box-shadow: 0 5px 20px rgba(55, 66, 250, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 8px 25px rgba(55, 66, 250, 0.4);
        }

        .btn-success {
            background: var(--gradient-success);
            color: white;
            box-shadow: 0 5px 20px rgba(46, 213, 115, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 8px 25px rgba(46, 213, 115, 0.4);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1.1rem;
        }

        /* Blood Badge */
        .blood-badge {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: 0 5px 15px rgba(255, 71, 87, 0.3);
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        .blood-badge::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 60%);
            transform: rotate(45deg);
        }

        /* Footer - Réduit */
        .footer {
            background: linear-gradient(135deg, #0f0c29, #1a1a2e);
            padding: 3rem 0 1.5rem;
            margin-top: 3rem;
            position: relative;
            overflow: hidden;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-column {
            margin-bottom: 1.5rem;
        }

        .footer-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: white;
            position: relative;
            padding-bottom: 0.8rem;
        }

        .footer-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background: var(--gradient-primary);
            border-radius: 2px;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.8rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }

        .footer-links a i {
            margin-right: 8px;
            width: 16px;
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: white;
            transform: translateX(5px);
        }

        .social-links {
            display: flex;
            gap: 0.8rem;
            margin-top: 1.5rem;
        }

        .social-link {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1rem;
        }

        .social-link:hover {
            background: var(--gradient-primary);
            transform: translateY(-3px) rotate(5deg);
        }

        .copyright {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
        }

        /* Modal Styling */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            animation: fadeIn 0.3s ease;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(135deg, #2f3542, #1a1a2e);
            border-radius: var(--radius);
            width: 100%;
            max-width: 700px;
            max-height: 85vh;
            overflow-y: auto;
            position: relative;
            animation: slideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px) scale(0.9); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-light));
            padding: 1.5rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }

        .modal-title i {
            margin-right: 12px;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: flex-end;
            gap: 0.8rem;
        }

        /* Charts and Graphs */
        .chart-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.2rem;
            color: white;
            display: flex;
            align-items: center;
        }

        .chart-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .chart {
            height: 250px;
            position: relative;
        }

        .chart-bar {
            position: absolute;
            bottom: 0;
            width: 35px;
            background: var(--gradient-primary);
            border-radius: 8px 8px 0 0;
            transition: var(--transition);
            cursor: pointer;
        }

        .chart-bar:hover {
            filter: brightness(1.2);
            transform: scale(1.05);
        }

        /* Loading Spinner */
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 1.5rem auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 3000;
            max-width: 350px;
        }

        .toast {
            background: rgba(47, 53, 66, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius);
            padding: 1.2rem;
            margin-bottom: 0.8rem;
            border-left: 4px solid var(--primary-color);
            box-shadow: var(--shadow);
            animation: slideInRight 0.3s ease;
            display: flex;
            align-items: center;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .toast-icon {
            font-size: 1.8rem;
            margin-right: 1.2rem;
            color: var(--primary-color);
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-size: 1.1rem;
            margin-bottom: 0.4rem;
            color: white;
        }

        .toast-message {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        /* Grid System */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -0.8rem;
        }

        .col {
            padding: 0.8rem;
        }

        .col-1 { flex: 0 0 8.33%; }
        .col-2 { flex: 0 0 16.66%; }
        .col-3 { flex: 0 0 25%; }
        .col-4 { flex: 0 0 33.33%; }
        .col-5 { flex: 0 0 41.66%; }
        .col-6 { flex: 0 0 50%; }
        .col-7 { flex: 0 0 58.33%; }
        .col-8 { flex: 0 0 66.66%; }
        .col-9 { flex: 0 0 75%; }
        .col-10 { flex: 0 0 83.33%; }
        .col-11 { flex: 0 0 91.66%; }
        .col-12 { flex: 0 0 100%; }

        /* Additional Utility Classes */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .mt-1 { margin-top: 1rem; }
        .mt-2 { margin-top: 1.5rem; }
        .mt-3 { margin-top: 2rem; }
        .mb-1 { margin-bottom: 1rem; }
        .mb-2 { margin-bottom: 1.5rem; }
        .mb-3 { margin-bottom: 2rem; }
        .p-1 { padding: 1rem; }
        .p-2 { padding: 1.5rem; }
        .p-3 { padding: 2rem; }
        .d-flex { display: flex; }
        .d-block { display: block; }
        .d-none { display: none; }
        .align-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .justify-center { justify-content: center; }
        .w-100 { width: 100%; }
        .h-100 { height: 100%; }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gradient-primary);
            border-radius: 8px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Selection Color */
        ::selection {
            background: var(--primary-color);
            color: white;
        }

        /* Focus Styles */
        :focus {
            outline: none;
        }

        :focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 2.2rem;
            }
            
            .hero-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .nav-links {
                display: none;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .hero-title {
                font-size: 1.8rem;
            }
            
            .hero-subtitle {
                font-size: 0.9rem;
            }
            
            .dashboard-cards {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 1.6rem;
            }
            
            .hero-stats {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 1.2rem;
            }
            
            .tab-content {
                padding: 1.5rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .modal-body {
                padding: 1.5rem;
            }
            
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .hero-title {
                font-size: 1.4rem;
            }
            
            .hero-subtitle {
                font-size: 0.85rem;
            }
            
            .btn {
                padding: 0.7rem 1.2rem;
                font-size: 0.9rem;
            }
            
            .modal-content {
                margin: 0.5rem;
            }
            
            .tab-btn {
                padding: 1rem;
                font-size: 0.9rem;
            }
            
            .tab-btn i {
                margin-right: 6px;
                font-size: 1rem;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 1.3rem;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
            
            .dashboard-card {
                padding: 1.5rem;
            }
            
            .data-card {
                padding: 1.2rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Pagination améliorée */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .pagination-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .pagination-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }

        .pagination-btn.active {
            background: var(--gradient-primary);
            color: white;
            transform: scale(1.1);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Classes utilitaires */
        .focused {
            border-color: var(--primary-color) !important;
        }

        .has-selection {
            border-color: var(--success-color) !important;
        }

        /* Listes fixes */
        .fixed-list-container {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 10px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .fixed-list-container::-webkit-scrollbar {
            width: 6px;
        }

        .fixed-list-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 3px;
        }

        .fixed-list-container::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 3px;
        }

        .hospital-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        /* Fix pour les listes */
        #donorsList,
        #hospitalsList {
            display: grid;
            gap: 1rem;
            grid-template-columns: 1fr;
        }

        @media (min-width: 768px) {
            #hospitalsList {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 992px) {
            #hospitalsList {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container nav-container">
            <a href="#" class="logo" onclick="openTab('donors'); return false;">
                <i class="fas fa-heartbeat logo-icon"></i>
                LifeBlood
            </a>
            
            <ul class="nav-links">
                <li class="nav-link"><a href="#home" class="active" onclick="openTab('donors'); return false;"><i class="fas fa-home"></i> Accueil</a></li>
                <li class="nav-link"><a href="#dashboard" onclick="openTab('donors'); return false;"><i class="fas fa-tachometer-alt"></i> Tableau de Bord</a></li>
                <li class="nav-link"><a href="#donors" onclick="openTab('donors'); return false;"><i class="fas fa-users"></i> Donneurs</a></li>
                <li class="nav-link"><a href="#hospitals" onclick="openTab('hospitals'); return false;"><i class="fas fa-hospital"></i> Hôpitaux</a></li>
                <li class="nav-link"><a href="#reports" onclick="openTab('reports'); return false;"><i class="fas fa-chart-bar"></i> Rapports</a></li>
                <li class="nav-link"><a href="#contact" onclick="scrollToContact(); return false;"><i class="fas fa-envelope"></i> Contact</a></li>
            </ul>
            
            <div class="user-profile">
                <div class="user-avatar">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="user-name"><a href="page connrxion.html">Hôpital Central</a></div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="hero-background"></div>
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">Système Intelligent de Gestion des Donneurs de Sang</h1>
                <p class="hero-subtitle">
                    Plateforme avancée pour connecter les donneurs de sang avec les patients dans le besoin. 
                    Utilisez notre système pour sauver des vies efficacement et intelligemment.
                </p>
                
                <div class="hero-actions">
                    <button class="btn btn-primary btn-lg" onclick="openModal('addDonorModal', true)">
                        <i class="fas fa-user-plus"></i> Nouveau Donneur
                    </button>
                    <button class="btn btn-secondary btn-lg" onclick="openModal('requestBloodModal', true)">
                        <i class="fas fa-hand-holding-medical"></i> Demande de Sang
                    </button>
                    <button class="btn btn-success btn-lg" onclick="openModal('addHospitalModal', true)">
                        <i class="fas fa-hospital"></i> Ajouter Hôpital
                    </button>
                </div>
                
                <div class="hero-stats">
                    <div class="stat-card">
                        <i class="fas fa-users stat-icon"></i>
                        <div class="stat-number" id="totalDonorsCount">0</div>
                        <div class="stat-label">Donneurs Actifs</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-tint stat-icon"></i>
                        <div class="stat-number" id="todayDonations">0</div>
                        <div class="stat-label">Dons Aujourd'hui</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-heartbeat stat-icon"></i>
                        <div class="stat-number" id="livesSaved">0</div>
                        <div class="stat-label">Vies Sauvées</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Emergency Banner -->
    <div class="container">
        <div class="emergency-banner">
            <div class="emergency-content">
                <h3 class="emergency-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    URGENCE : BESOIN CRITIQUE DE SANG O-
                </h3>
                <p>Hôpital Central - Chirurgie cardiaque urgente nécessite 4 poches de sang O- dans les 2 heures.</p>
                <small><i class="fas fa-clock"></i> Délai critique : 2 heures restantes</small>
            </div>
            <button class="btn btn-primary" onclick="openEmergencyDetails()">
                <i class="fas fa-info-circle"></i> Voir les détails
            </button>
        </div>
    </div>

    <!-- Dashboard Section -->
    <section class="dashboard-section" id="dashboard">
        <div class="container">
            <h2 class="section-title">Tableau de Bord Intelligent</h2>
            <p class="section-subtitle">Gérez toutes vos opérations de don de sang depuis une interface unique</p>
            
            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <i class="fas fa-users card-icon"></i>
                    <h3 class="card-title">Gestion des Donneurs</h3>
                    <p class="card-description">
                        Gérez votre base de donneurs, suivez les dons, vérifiez l'éligibilité et trouvez des donneurs compatibles.
                    </p>
                    <button class="btn btn-primary" onclick="openTab('donors')">
                        <i class="fas fa-arrow-right"></i> Accéder
                    </button>
                </div>
                
                <div class="dashboard-card">
                    <i class="fas fa-hospital card-icon"></i>
                    <h3 class="card-title">Gestion des Hôpitaux</h3>
                    <p class="card-description">
                        Gérez vos partenaires hospitaliers, suivez les stocks de sang et coordonnez les livraisons.
                    </p>
                    <button class="btn btn-primary" onclick="openTab('hospitals')">
                        <i class="fas fa-arrow-right"></i> Accéder
                    </button>
                </div>
                
                <div class="dashboard-card">
                    <i class="fas fa-chart-line card-icon"></i>
                    <h3 class="card-title">Analyses & Rapports</h3>
                    <p class="card-description">
                        Analysez vos données, générez des rapports détaillés et suivez vos performances.
                    </p>
                    <button class="btn btn-primary" onclick="openTab('reports')">
                        <i class="fas fa-arrow-right"></i> Accéder
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Tabs Section -->
    <section class="container" id="mainTabs">
        <div class="tabs-section">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="donors">
                    <i class="fas fa-users"></i> Donneurs
                </button>
                <button class="tab-btn" data-tab="hospitals">
                    <i class="fas fa-hospital"></i> Hôpitaux
                </button>
                <button class="tab-btn" data-tab="requests">
                    <i class="fas fa-hand-holding-medical"></i> Demandes
                </button>
                <button class="tab-btn" data-tab="donations">
                    <i class="fas fa-tint"></i> Historique des Dons
                </button>
                <button class="tab-btn" data-tab="analytics">
                    <i class="fas fa-chart-pie"></i> Analytics
                </button>
                <button class="tab-btn" data-tab="reports">
                    <i class="fas fa-file-alt"></i> Rapports
                </button>
            </div>

            <!-- Donors Tab -->
            <div class="tab-content active" id="donorsTab">
                <div class="data-header mb-2">
                    <h3 class="data-title"><i class="fas fa-users"></i> Gestion des Donneurs</h3>
                    <div class="d-flex">
                        <button class="btn btn-primary" onclick="openModal('addDonorModal', true)">
                            <i class="fas fa-plus"></i> Ajouter un Donneur
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col col-4">
                        <div class="form-container">
                            <h4 class="form-title"><i class="fas fa-filter"></i> Filtres Avancés</h4>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-search"></i> Recherche</label>
                                <input type="text" class="form-control" id="searchDonor" placeholder="Nom, téléphone, email...">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-tint"></i> Groupe Sanguin</label>
                                <select class="form-control" id="bloodTypeFilter">
                                    <option value="">Tous les groupes</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-city"></i> Ville</label>
                                <select class="form-control" id="cityFilter">
                                    <option value="">Toutes les villes</option>
                                </select>
                            </div>
                            <button class="btn btn-primary w-100" onclick="filterDonors()">
                                <i class="fas fa-filter"></i> Appliquer les Filtres
                            </button>
                            <button class="btn btn-outline w-100 mt-1" onclick="resetFilters()">
                                <i class="fas fa-redo"></i> Réinitialiser
                            </button>
                        </div>
                    </div>

                    <div class="col col-8">
                        <div class="data-card">
                            <div class="data-header">
                                <h4 class="data-title"><i class="fas fa-list"></i> Liste des Donneurs</h4>
                                <span class="data-badge" id="donorsCount">0 Donneurs</span>
                            </div>
                            <div class="fixed-list-container" id="donorsListContainer">
                                <div id="donorsList">
                                    <!-- Donors will be loaded here -->
                                </div>
                            </div>
                            <div class="text-center mt-2">
                                <div id="donorsPagination"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hospitals Tab -->
            <div class="tab-content" id="hospitalsTab">
                <div class="data-header mb-2">
                    <h3 class="data-title"><i class="fas fa-hospital"></i> Gestion des Hôpitaux</h3>
                    <button class="btn btn-primary" onclick="openModal('addHospitalModal', true)">
                        <i class="fas fa-plus"></i> Nouvel Hôpital
                    </button>
                </div>

                <div class="data-card">
                    <div class="data-header">
                        <h4 class="data-title"><i class="fas fa-hospital-alt"></i> Liste des Hôpitaux</h4>
                        <span class="data-badge" id="hospitalsCount">0 Hôpitaux</span>
                    </div>
                    <div class="fixed-list-container" id="hospitalsListContainer">
                        <div class="hospital-grid" id="hospitalsList">
                            <!-- Hospitals will be loaded here -->
                        </div>
                    </div>
                    <div class="text-center mt-2">
                        <div id="hospitalsPagination"></div>
                    </div>
                </div>
            </div>

            <!-- Blood Requests Tab -->
            <div class="tab-content" id="requestsTab">
                <div class="data-header mb-2">
                    <h3 class="data-title"><i class="fas fa-hand-holding-medical"></i> Demandes de Sang</h3>
                    <button class="btn btn-primary" onclick="openModal('requestBloodModal', true)">
                        <i class="fas fa-plus"></i> Nouvelle Demande
                    </button>
                </div>

                <div class="fixed-list-container" id="requestsListContainer">
                    <div id="requestsList">
                        <!-- Blood requests will be loaded here -->
                    </div>
                </div>
                <div class="text-center mt-2">
                    <div id="requestsPagination"></div>
                </div>
            </div>

            <!-- Donations History Tab -->
            <div class="tab-content" id="donationsTab">
                <div class="data-header mb-2">
                    <h3 class="data-title"><i class="fas fa-history"></i> Historique des Dons</h3>
                </div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h4 class="chart-title"><i class="fas fa-chart-bar"></i> Évolution des Dons</h4>
                        <select class="form-control" style="width: auto;" id="chartPeriod">
                            <option value="7">7 derniers jours</option>
                            <option value="30">30 derniers jours</option>
                            <option value="90">3 derniers mois</option>
                        </select>
                    </div>
                    <div class="chart" id="donationsChart"></div>
                </div>

                <div class="fixed-list-container" id="donationsListContainer">
                    <div id="donationsList">
                        <!-- Donations history will be loaded here -->
                    </div>
                </div>
                <div class="text-center mt-2">
                    <div id="donationsPagination"></div>
                </div>
            </div>

            <!-- Analytics Tab -->
            <div class="tab-content" id="analyticsTab">
                <div class="data-header mb-2">
                    <h3 class="data-title"><i class="fas fa-chart-pie"></i> Analytics Avancés</h3>
                </div>

                <div class="row">
                    <div class="col col-6">
                        <div class="chart-container">
                            <h4 class="chart-title"><i class="fas fa-tint"></i> Répartition par Groupe Sanguin</h4>
                            <div class="chart" id="bloodTypeChart"></div>
                        </div>
                    </div>
                    <div class="col col-6">
                        <div class="chart-container">
                            <h4 class="chart-title"><i class="fas fa-map-marker-alt"></i> Répartition Géographique</h4>
                            <div class="chart" id="geographicChart"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports Tab -->
            <div class="tab-content" id="reportsTab">
                <div class="data-header mb-2">
                    <h3 class="data-title"><i class="fas fa-file-alt"></i> Génération de Rapports</h3>
                </div>

                <div class="row">
                    <div class="col col-6">
                        <div class="form-container">
                            <h4 class="form-title"><i class="fas fa-cog"></i> Paramètres du Rapport</h4>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-file"></i> Type de Rapport</label>
                                <select class="form-control" id="reportType">
                                    <option value="donors">Liste des Donneurs</option>
                                    <option value="donations">Historique des Dons</option>
                                    <option value="hospitals">Hôpitaux Partenaires</option>
                                    <option value="statistics">Statistiques Générales</option>
                                    <option value="comprehensive">Rapport Complet</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-calendar"></i> Période</label>
                                <select class="form-control" id="reportPeriod">
                                    <option value="today">Aujourd'hui</option>
                                    <option value="week">Cette semaine</option>
                                    <option value="month">Ce mois</option>
                                    <option value="quarter">Ce trimestre</option>
                                    <option value="year">Cette année</option>
                                    <option value="custom">Personnalisée</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-download"></i> Format</label>
                                <select class="form-control" id="reportFormat">
                                    <option value="pdf">PDF</option>
                                    <option value="excel">Excel</option>
                                    <option value="csv">CSV</option>
                                    <option value="print">Impression</option>
                                </select>
                            </div>
                            <button class="btn btn-primary w-100" onclick="generateReport()">
                                <i class="fas fa-file-download"></i> Générer le Rapport
                            </button>
                        </div>
                    </div>

                    <div class="col col-6">
                        <div class="form-container">
                            <h4 class="form-title"><i class="fas fa-chart-line"></i> Statistiques Rapides</h4>
                            <div id="quickStats">
                                <!-- Quick stats will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h4 class="footer-title">LifeBlood</h4>
                    <p style="font-size: 0.9rem; color: rgba(255,255,255,0.7);">Système intelligent de gestion des donneurs de sang. Ensemble, sauvons des vies.</p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <div class="footer-column">
                    <h4 class="footer-title">Navigation</h4>
                    <ul class="footer-links">
                        <li><a href="#home" onclick="openTab('donors'); return false;"><i class="fas fa-chevron-right"></i> Accueil</a></li>
                        <li><a href="#dashboard" onclick="openTab('donors'); return false;"><i class="fas fa-chevron-right"></i> Tableau de Bord</a></li>
                        <li><a href="#donors" onclick="openTab('donors'); return false;"><i class="fas fa-chevron-right"></i> Donneurs</a></li>
                        <li><a href="#hospitals" onclick="openTab('hospitals'); return false;"><i class="fas fa-chevron-right"></i> Hôpitaux</a></li>
                        <li><a href="#reports" onclick="openTab('reports'); return false;"><i class="fas fa-chevron-right"></i> Rapports</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h4 class="footer-title">Ressources</h4>
                    <ul class="footer-links">
                        <li><a href="#" onclick="openModal('addDonorModal', true)"><i class="fas fa-chevron-right"></i> Devenir Donneur</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Guide du Don</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Blog</a></li>
                        <li><a href="#" onclick="openModal('emergencyModal')"><i class="fas fa-chevron-right"></i> Contact d'Urgence</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h4 class="footer-title">Contact</h4>
                    <ul class="footer-links">
                        <li><a href="tel:+237123456789"><i class="fas fa-phone"></i> +237 123 456 789</a></li>
                        <li><a href="mailto:contact@lifeblood.cm"><i class="fas fa-envelope"></i> contact@lifeblood.cm</a></li>
                        <li><a href="#"><i class="fas fa-map-marker-alt"></i> Yaoundé, Cameroun</a></li>
                        <li><a href="#"><i class="fas fa-clock"></i> Lun - Ven: 8h - 18h</a></li>
                        <li><a href="#" onclick="openModal('requestBloodModal', true)"><i class="fas fa-ambulance"></i> Urgence: 24h/24</a></li>
                    </ul>
                </div>
            </div>

            <div class="copyright">
                <p style="font-size: 0.85rem;">&copy; 2023 LifeBlood - Système Intelligent de Gestion des Donneurs de Sang. Tous droits réservés.</p>
                <p class="mt-1" style="font-size: 0.8rem;">
                    <a href="#" style="color: rgba(255,255,255,0.5); margin-right: 10px;">Politique de Confidentialité</a>
                    <a href="#" style="color: rgba(255,255,255,0.5);">Conditions d'Utilisation</a>
                </p>
            </div>
        </div>
    </footer>

    <!-- Modals -->
    <!-- Add Donor Modal -->
    <div class="modal" id="addDonorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-plus"></i> <span id="donorModalTitle">Nouveau Donneur</span></h3>
                <button class="modal-close" onclick="closeModal('addDonorModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-container">
                    <form id="donorForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-user"></i> Nom Complet *</label>
                                <input type="text" class="form-control" id="donorName" required placeholder="Jean Dupont">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-phone"></i> Téléphone *</label>
                                <input type="tel" class="form-control" id="donorPhone" required placeholder="237 123 456 789">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-envelope"></i> Email</label>
                                <input type="email" class="form-control" id="donorEmail" placeholder="jean.dupont@email.com">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-birthday-cake"></i> Âge *</label>
                                <input type="number" class="form-control" id="donorAge" required min="18" max="65" placeholder="30">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-tint"></i> Groupe Sanguin *</label>
                                <select class="form-control" id="donorBloodType" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-city"></i> Ville *</label>
                                <select class="form-control" id="donorCity" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="Yaoundé">Yaoundé</option>
                                    <option value="Douala">Douala</option>
                                    <option value="Bafoussam">Bafoussam</option>
                                    <option value="Garoua">Garoua</option>
                                    <option value="Maroua">Maroua</option>
                                    <option value="Ngaoundéré">Ngaoundéré</option>
                                    <option value="Bamenda">Bamenda</option>
                                    <option value="Bertoua">Bertoua</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-notes-medical"></i> Informations Médicales</label>
                            <textarea class="form-control" id="donorMedicalInfo" rows="2" placeholder="Allergies, conditions médicales, médicaments..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-sticky-note"></i> Notes Additionnelles</label>
                            <textarea class="form-control" id="donorNotes" rows="2" placeholder="Informations supplémentaires..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <div class="d-flex align-center">
                                <input type="checkbox" id="donorConsent" required style="margin-right: 8px;">
                                <label for="donorConsent" style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                                    J'accepte les conditions d'utilisation et autorise le traitement de mes données
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addDonorModal')">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="saveDonor()" id="donorSaveBtn">
                    <i class="fas fa-save"></i> Enregistrer le Donneur
                </button>
            </div>
        </div>
    </div>

    <!-- Add Hospital Modal -->
    <div class="modal" id="addHospitalModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-hospital"></i> <span id="hospitalModalTitle">Nouvel Hôpital Partenaire</span></h3>
                <button class="modal-close" onclick="closeModal('addHospitalModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-container">
                    <form id="hospitalForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-hospital-alt"></i> Nom de l'Hôpital *</label>
                                <input type="text" class="form-control" id="hospitalName" required placeholder="Hôpital Central de Yaoundé">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-city"></i> Ville *</label>
                                <select class="form-control" id="hospitalCity" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="Yaoundé">Yaoundé</option>
                                    <option value="Douala">Douala</option>
                                    <option value="Bafoussam">Bafoussam</option>
                                    <option value="Garoua">Garoua</option>
                                    <option value="Maroua">Maroua</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-phone"></i> Téléphone *</label>
                                <input type="tel" class="form-control" id="hospitalPhone" required placeholder="237 111 111 111">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-envelope"></i> Email</label>
                                <input type="email" class="form-control" id="hospitalEmail" placeholder="contact@hopital.cm">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-map-marker-alt"></i> Adresse Complète</label>
                            <textarea class="form-control" id="hospitalAddress" rows="2" placeholder="Adresse complète de l'hôpital..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-tint"></i> Stocks de Sang Requis</label>
                            <div class="form-grid" style="grid-template-columns: repeat(2, 1fr);">
                                <div>
                                    <label style="color: rgba(255,255,255,0.7); font-size: 0.85rem; display: block; margin-bottom: 0.3rem;">O+</label>
                                    <input type="number" class="form-control" id="hospitalOPlus" min="0" placeholder="0">
                                </div>
                                <div>
                                    <label style="color: rgba(255,255,255,0.7); font-size: 0.85rem; display: block; margin-bottom: 0.3rem;">O-</label>
                                    <input type="number" class="form-control" id="hospitalONeg" min="0" placeholder="0">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addHospitalModal')">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="saveHospital()">
                    <i class="fas fa-save"></i> Enregistrer l'Hôpital
                </button>
            </div>
        </div>
    </div>

    <!-- Request Blood Modal -->
    <div class="modal" id="requestBloodModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-hand-holding-medical"></i> Demande de Sang Urgente</h3>
                <button class="modal-close" onclick="closeModal('requestBloodModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-container">
                    <form id="bloodRequestForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-tint"></i> Groupe Sanguin Requis *</label>
                                <select class="form-control" id="requestBloodType" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-syringe"></i> Quantité (Poches) *</label>
                                <input type="number" class="form-control" id="requestQuantity" required min="1" max="10" placeholder="2">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-hospital"></i> Hôpital Requérant *</label>
                                <select class="form-control" id="requestHospital" required>
                                    <option value="">Sélectionner...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-clock"></i> Urgence *</label>
                                <select class="form-control" id="requestUrgency" required>
                                    <option value="normal">Normale (48h)</option>
                                    <option value="urgent">Urgente (24h)</option>
                                    <option value="critical">Critique (4h)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-user-injured"></i> Patient / Raison</label>
                            <textarea class="form-control" id="requestReason" rows="2" placeholder="Nom du patient, raison médicale, chirurgie..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-notes-medical"></i> Notes Médicales</label>
                            <textarea class="form-control" id="requestMedicalNotes" rows="2" placeholder="Informations médicales importantes..."></textarea>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('requestBloodModal')">Annuler</button>
                <button type="button" class="btn btn-danger" onclick="submitBloodRequest()">
                    <i class="fas fa-bell"></i> Envoyer la Demande d'Urgence
                </button>
            </div>
        </div>
    </div>

    <!-- Emergency Details Modal -->
    <div class="modal" id="emergencyModal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ff3838, #ff6b81);">
                <h3 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Détails de l'Urgence</h3>
                <button class="modal-close" onclick="closeModal('emergencyModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="data-card" style="background: rgba(255, 56, 56, 0.1); border-color: rgba(255, 56, 56, 0.3);">
                    <div class="data-header">
                        <h4 class="data-title"><i class="fas fa-heartbeat"></i> Chirurgie Cardiaque Urgente</h4>
                        <span class="data-badge" style="background: linear-gradient(135deg, #ff3838, #ff6b81);">CRITIQUE</span>
                    </div>
                    <p style="color: rgba(255,255,255,0.9); margin-bottom: 1rem;">
                        <strong>Hôpital Central</strong> a besoin de <strong>4 poches de sang O-</strong> pour une chirurgie cardiaque d'urgence.
                    </p>
                    <div class="row">
                        <div class="col col-6">
                            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">
                                <i class="fas fa-clock"></i> Délai: <strong>2 heures</strong><br>
                                <i class="fas fa-map-marker-alt"></i> Localisation: <strong>Bloc Opératoire 3</strong><br>
                                <i class="fas fa-user-md"></i> Chirurgien: <strong>Dr. Kamga</strong>
                            </p>
                        </div>
                        <div class="col col-6">
                            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">
                                <i class="fas fa-phone"></i> Contact: <strong>+237 111 111 111</strong><br>
                                <i class="fas fa-user-injured"></i> Patient: <strong>M. Ndongo, 54 ans</strong><br>
                                <i class="fas fa-stethoscope"></i> Diagnostic: <strong>Pontage coronarien</strong>
                            </p>
                        </div>
                    </div>
                </div>
                
                <h4 style="color: white; margin: 1.5rem 0 1rem; display: flex; align-items: center;">
                    <i class="fas fa-users" style="margin-right: 10px; color: var(--primary-color);"></i>
                    Donneurs Compatibles Disponibles
                </h4>
                
                <div class="fixed-list-container" style="max-height: 300px;">
                    <div id="emergencyDonorsList">
                        <!-- Emergency donors will be loaded here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('emergencyModal')">Fermer</button>
                <button type="button" class="btn btn-danger" onclick="notifyEmergencyDonors()">
                    <i class="fas fa-bell"></i> Notifier les Donneurs
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- JavaScript -->
    <script>
        // Données initiales
        let donors = [];
        let hospitals = [];
        let bloodRequests = [];
        let donations = [];
        let currentDonorId = null;
        let currentHospitalId = null;

        // Configuration
        const config = {
            itemsPerPage: 8,
            currentDonorPage: 1,
            currentHospitalPage: 1,
            currentRequestPage: 1,
            currentDonationPage: 1
        };

        // Données initiales de démonstration
        const initialDonors = [
            {
                id: 1,
                name: "Jean Dupont",
                phone: "237 123 456 789",
                email: "jean.dupont@email.com",
                age: 32,
                bloodType: "O+",
                city: "Yaoundé",
                lastDonation: "2023-10-15",
                status: "active",
                medicalInfo: "Aucune condition médicale connue"
            },
            {
                id: 2,
                name: "Marie Kamga",
                phone: "237 987 654 321",
                email: "marie.kamga@email.com",
                age: 28,
                bloodType: "A-",
                city: "Douala",
                lastDonation: "2023-09-20",
                status: "active",
                medicalInfo: "Allergie aux pénicillines"
            },
            {
                id: 3,
                name: "Paul Mbappe",
                phone: "237 555 123 456",
                email: "paul.mbappe@email.com",
                age: 45,
                bloodType: "B+",
                city: "Bafoussam",
                lastDonation: "2023-08-10",
                status: "active",
                medicalInfo: "Hypertension contrôlée"
            },
            {
                id: 4,
                name: "Sophie Ngo",
                phone: "237 777 888 999",
                email: "sophie.ngo@email.com",
                age: 29,
                bloodType: "O-",
                city: "Yaoundé",
                lastDonation: "2023-11-05",
                status: "active",
                medicalInfo: "Aucune"
            },
            {
                id: 5,
                name: "David Tchoupo",
                phone: "237 222 333 444",
                email: "david.t@email.com",
                age: 38,
                bloodType: "AB+",
                city: "Douala",
                lastDonation: "2023-07-15",
                status: "active",
                medicalInfo: "Diabétique type 2"
            },
            {
                id: 6,
                name: "Alice Mbarga",
                phone: "237 666 777 888",
                email: "alice.m@email.com",
                age: 26,
                bloodType: "A+",
                city: "Garoua",
                lastDonation: "2023-10-28",
                status: "active",
                medicalInfo: "Anémie légère"
            },
            {
                id: 7,
                name: "Marc Eto'o",
                phone: "237 111 222 333",
                email: "marc.e@email.com",
                age: 42,
                bloodType: "O-",
                city: "Yaoundé",
                lastDonation: "2023-09-12",
                status: "active",
                medicalInfo: "Aucune condition médicale"
            },
            {
                id: 8,
                name: "Julie Nkono",
                phone: "237 444 555 666",
                email: "julie.n@email.com",
                age: 31,
                bloodType: "B-",
                city: "Douala",
                lastDonation: "2023-11-10",
                status: "active",
                medicalInfo: "Grossesse en cours"
            }
        ];

        const initialHospitals = [
            {
                id: 1,
                name: "Hôpital Central de Yaoundé",
                city: "Yaoundé",
                phone: "237 111 111 111",
                email: "contact@hcy.cm",
                address: "Boulevard du 20 Mai, Yaoundé",
                bloodStock: {
                    "O+": 15,
                    "O-": 8,
                    "A+": 12,
                    "A-": 6,
                    "B+": 10,
                    "B-": 5,
                    "AB+": 4,
                    "AB-": 2
                },
                status: "active"
            },
            {
                id: 2,
                name: "Hôpital Général de Douala",
                city: "Douala",
                phone: "237 222 222 222",
                email: "contact@hgd.cm",
                address: "Avenue des Hôpitaux, Douala",
                bloodStock: {
                    "O+": 20,
                    "O-": 10,
                    "A+": 15,
                    "A-": 8,
                    "B+": 12,
                    "B-": 6,
                    "AB+": 5,
                    "AB-": 3
                },
                status: "active"
            },
            {
                id: 3,
                name: "Hôpital Régional de Bafoussam",
                city: "Bafoussam",
                phone: "237 333 333 333",
                email: "contact@hrb.cm",
                address: "Quartier Administratif, Bafoussam",
                bloodStock: {
                    "O+": 8,
                    "O-": 4,
                    "A+": 6,
                    "A-": 3,
                    "B+": 5,
                    "B-": 2,
                    "AB+": 2,
                    "AB-": 1
                },
                status: "active"
            }
        ];

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Charger les données
            loadInitialData();
            
            // Initialiser les écouteurs d'événements
            initializeEventListeners();
            
            // Mettre à jour les statistiques
            updateStats();
            
            // Initialiser les graphiques
            initializeCharts();
            
            // Charger les listes
            loadDonorsList();
            loadHospitalsList();
            loadBloodRequests();
            loadDonationsHistory();
            
            // Remplir les selects
            populateHospitalSelect();
            populateCityFilters();
            
            // Effet de navbar au scroll
            window.addEventListener('scroll', handleScroll);
        });

        // Fonctions principales
        function loadInitialData() {
            // Charger depuis localStorage ou utiliser les données initiales
            const storedDonors = localStorage.getItem('bloodDonors');
            const storedHospitals = localStorage.getItem('bloodHospitals');
            const storedRequests = localStorage.getItem('bloodRequests');
            const storedDonations = localStorage.getItem('bloodDonations');
            
            donors = storedDonors ? JSON.parse(storedDonors) : initialDonors;
            hospitals = storedHospitals ? JSON.parse(storedHospitals) : initialHospitals;
            bloodRequests = storedRequests ? JSON.parse(storedRequests) : [];
            donations = storedDonations ? JSON.parse(storedDonations) : generateSampleDonations();
            
            // Sauvegarder les données initiales si localStorage était vide
            if (!storedDonors) localStorage.setItem('bloodDonors', JSON.stringify(donors));
            if (!storedHospitals) localStorage.setItem('bloodHospitals', JSON.stringify(hospitals));
            if (!storedDonations) localStorage.setItem('bloodDonations', JSON.stringify(donations));
        }

        function initializeEventListeners() {
            // Navigation tabs
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    openTab(tabId);
                });
            });

            // Recherche en temps réel
            document.getElementById('searchDonor')?.addEventListener('input', function() {
                filterDonors();
            });

            // Fermeture modale avec ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeAllModals();
                }
            });

            // Clic en dehors des modales pour fermer
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('modal')) {
                    closeModal(e.target.id);
                }
            });
        }

        // Fonctions de navigation
        function openTab(tabId) {
            // Désactiver tous les onglets
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Activer l'onglet sélectionné
            const tabBtn = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
            const tabContent = document.getElementById(`${tabId}Tab`);
            
            if (tabBtn && tabContent) {
                tabBtn.classList.add('active');
                tabContent.classList.add('active');
                
                // Charger les données spécifiques à l'onglet
                switch(tabId) {
                    case 'donors':
                        loadDonorsList();
                        break;
                    case 'hospitals':
                        loadHospitalsList();
                        break;
                    case 'requests':
                        loadBloodRequests();
                        break;
                    case 'donations':
                        loadDonationsHistory();
                        updateChart();
                        break;
                    case 'analytics':
                        updateAnalytics();
                        break;
                    case 'reports':
                        updateQuickStats();
                        break;
                }
            }
        }

        // Fonctions pour les modales
        function openModal(modalId, clearForm = false) {
            const modal = document.getElementById(modalId);
            if (modal) {
                document.body.style.overflow = 'hidden';
                modal.classList.add('active');
                
                if (clearForm) {
                    clearModalForm(modalId);
                }
                
                // Pour la modal de demande de sang, s'assurer que le select des hôpitaux est rempli
                if (modalId === 'requestBloodModal') {
                    populateHospitalSelect();
                }
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                document.body.style.overflow = '';
                modal.classList.remove('active');
                currentDonorId = null;
                currentHospitalId = null;
            }
        }

        function closeAllModals() {
            document.querySelectorAll('.modal.active').forEach(modal => {
                modal.classList.remove('active');
            });
            document.body.style.overflow = '';
            currentDonorId = null;
            currentHospitalId = null;
        }

        function clearModalForm(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                const form = modal.querySelector('form');
                if (form) form.reset();
                
                // Réinitialiser les titres des modales
                if (modalId === 'addDonorModal') {
                    document.getElementById('donorModalTitle').textContent = 'Nouveau Donneur';
                    document.getElementById('donorSaveBtn').innerHTML = '<i class="fas fa-save"></i> Enregistrer le Donneur';
                } else if (modalId === 'addHospitalModal') {
                    document.getElementById('hospitalModalTitle').textContent = 'Nouvel Hôpital Partenaire';
                }
            }
        }

        // Fonctions pour les donneurs
        function loadDonorsList(page = 1) {
            config.currentDonorPage = page;
            const filteredDonors = getFilteredDonors();
            const startIndex = (page - 1) * config.itemsPerPage;
            const endIndex = startIndex + config.itemsPerPage;
            const paginatedDonors = filteredDonors.slice(startIndex, endIndex);
            
            const donorsList = document.getElementById('donorsList');
            const donorsCount = document.getElementById('donorsCount');
            
            donorsCount.textContent = `${filteredDonors.length} Donneurs`;
            
            if (paginatedDonors.length === 0) {
                donorsList.innerHTML = `
                    <div class="data-card" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-users" style="font-size: 3rem; color: rgba(255,255,255,0.3); margin-bottom: 1rem;"></i>
                        <h4 style="color: rgba(255,255,255,0.7);">Aucun donneur trouvé</h4>
                        <p style="color: rgba(255,255,255,0.5); margin-top: 0.5rem;">Essayez de modifier vos filtres de recherche</p>
                    </div>
                `;
            } else {
                donorsList.innerHTML = paginatedDonors.map(donor => `
                    <div class="data-card">
                        <div class="data-header">
                            <h4 class="data-title">
                                <i class="fas fa-user"></i> ${donor.name}
                                <span style="font-size: 0.85rem; color: rgba(255,255,255,0.7); margin-left: 8px;">(${donor.age} ans)</span>
                            </h4>
                            <div class="d-flex">
                                <div class="blood-badge" style="width: 45px; height: 45px; font-size: 1rem; margin-right: 10px;">
                                    ${donor.bloodType}
                                </div>
                                <div class="d-flex" style="gap: 5px;">
                                    <button class="btn btn-sm btn-outline" onclick="editDonor(${donor.id})" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline" onclick="recordDonation(${donor.id})" title="Enregistrer un don">
                                        <i class="fas fa-tint"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline" onclick="deleteDonor(${donor.id})" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row" style="margin-top: 1rem;">
                            <div class="col col-6">
                                <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-bottom: 0.3rem;">
                                    <i class="fas fa-phone"></i> ${donor.phone}
                                </p>
                                <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">
                                    <i class="fas fa-city"></i> ${donor.city}
                                </p>
                            </div>
                            <div class="col col-6">
                                <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-bottom: 0.3rem;">
                                    <i class="fas fa-calendar"></i> Dernier don: ${donor.lastDonation || 'Jamais'}
                                </p>
                                <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">
                                    <i class="fas fa-heart"></i> <span style="color: var(--success-color);">Actif</span>
                                </p>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            
            renderPagination('donorsPagination', filteredDonors.length, config.currentDonorPage, 'loadDonorsList');
        }

        function getFilteredDonors() {
            const searchTerm = document.getElementById('searchDonor')?.value.toLowerCase() || '';
            const bloodTypeFilter = document.getElementById('bloodTypeFilter')?.value || '';
            const cityFilter = document.getElementById('cityFilter')?.value || '';
            
            return donors.filter(donor => {
                const matchesSearch = !searchTerm || 
                    donor.name.toLowerCase().includes(searchTerm) ||
                    donor.phone.toLowerCase().includes(searchTerm) ||
                    donor.email.toLowerCase().includes(searchTerm);
                
                const matchesBloodType = !bloodTypeFilter || donor.bloodType === bloodTypeFilter;
                const matchesCity = !cityFilter || donor.city === cityFilter;
                
                return matchesSearch && matchesBloodType && matchesCity;
            });
        }

        function filterDonors() {
            loadDonorsList(1);
        }

        function resetFilters() {
            document.getElementById('searchDonor').value = '';
            document.getElementById('bloodTypeFilter').value = '';
            document.getElementById('cityFilter').value = '';
            loadDonorsList(1);
        }

        function saveDonor() {
            const form = document.getElementById('donorForm');
            if (!form.checkValidity()) {
                showToast('Veuillez remplir tous les champs obligatoires', 'error');
                return;
            }

            const donorData = {
                id: currentDonorId || donors.length > 0 ? Math.max(...donors.map(d => d.id)) + 1 : 1,
                name: document.getElementById('donorName').value,
                phone: document.getElementById('donorPhone').value,
                email: document.getElementById('donorEmail').value,
                age: parseInt(document.getElementById('donorAge').value),
                bloodType: document.getElementById('donorBloodType').value,
                city: document.getElementById('donorCity').value,
                medicalInfo: document.getElementById('donorMedicalInfo').value,
                notes: document.getElementById('donorNotes').value,
                lastDonation: null,
                status: 'active',
                createdAt: new Date().toISOString()
            };

            if (currentDonorId) {
                // Modification
                const index = donors.findIndex(d => d.id === currentDonorId);
                if (index !== -1) {
                    donors[index] = { ...donors[index], ...donorData };
                    showToast('Donneur modifié avec succès', 'success');
                }
            } else {
                // Nouveau donneur
                donors.push(donorData);
                showToast('Donneur ajouté avec succès', 'success');
            }

            // Sauvegarder dans localStorage
            localStorage.setItem('bloodDonors', JSON.stringify(donors));
            
            // Fermer la modal et rafraîchir la liste
            closeModal('addDonorModal');
            loadDonorsList(config.currentDonorPage);
            updateStats();
            populateCityFilters();
        }

        function editDonor(id) {
            const donor = donors.find(d => d.id === id);
            if (!donor) return;

            currentDonorId = id;
            
            // Remplir le formulaire
            document.getElementById('donorName').value = donor.name;
            document.getElementById('donorPhone').value = donor.phone;
            document.getElementById('donorEmail').value = donor.email || '';
            document.getElementById('donorAge').value = donor.age;
            document.getElementById('donorBloodType').value = donor.bloodType;
            document.getElementById('donorCity').value = donor.city;
            document.getElementById('donorMedicalInfo').value = donor.medicalInfo || '';
            document.getElementById('donorNotes').value = donor.notes || '';
            
            // Mettre à jour le titre de la modal
            document.getElementById('donorModalTitle').textContent = 'Modifier le Donneur';
            document.getElementById('donorSaveBtn').innerHTML = '<i class="fas fa-save"></i> Mettre à jour';
            
            // Ouvrir la modal
            openModal('addDonorModal');
        }

        function deleteDonor(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce donneur ?')) {
                donors = donors.filter(d => d.id !== id);
                localStorage.setItem('bloodDonors', JSON.stringify(donors));
                showToast('Donneur supprimé avec succès', 'success');
                loadDonorsList(config.currentDonorPage);
                updateStats();
            }
        }

        function recordDonation(donorId) {
            const donor = donors.find(d => d.id === donorId);
            if (!donor) return;

            const today = new Date().toISOString().split('T')[0];
            donor.lastDonation = today;
            
            // Ajouter à l'historique des dons
            const donation = {
                id: donations.length > 0 ? Math.max(...donations.map(d => d.id)) + 1 : 1,
                donorId: donorId,
                donorName: donor.name,
                bloodType: donor.bloodType,
                date: today,
                quantity: 1,
                status: 'completed'
            };
            
            donations.push(donation);
            
            // Sauvegarder
            localStorage.setItem('bloodDonors', JSON.stringify(donors));
            localStorage.setItem('bloodDonations', JSON.stringify(donations));
            
            showToast(`Don enregistré pour ${donor.name}`, 'success');
            loadDonorsList(config.currentDonorPage);
            updateStats();
            
            // Mettre à jour le graphique si on est sur l'onglet des dons
            if (document.getElementById('donationsTab').classList.contains('active')) {
                updateChart();
                loadDonationsHistory();
            }
        }

        // Fonctions pour les hôpitaux
        function loadHospitalsList(page = 1) {
            config.currentHospitalPage = page;
            const startIndex = (page - 1) * config.itemsPerPage;
            const endIndex = startIndex + config.itemsPerPage;
            const paginatedHospitals = hospitals.slice(startIndex, endIndex);
            
            const hospitalsList = document.getElementById('hospitalsList');
            const hospitalsCount = document.getElementById('hospitalsCount');
            
            hospitalsCount.textContent = `${hospitals.length} Hôpitaux`;
            
            if (paginatedHospitals.length === 0) {
                hospitalsList.innerHTML = `
                    <div class="data-card" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                        <i class="fas fa-hospital" style="font-size: 3rem; color: rgba(255,255,255,0.3); margin-bottom: 1rem;"></i>
                        <h4 style="color: rgba(255,255,255,0.7);">Aucun hôpital trouvé</h4>
                        <p style="color: rgba(255,255,255,0.5); margin-top: 0.5rem;">Ajoutez votre premier hôpital partenaire</p>
                    </div>
                `;
            } else {
                hospitalsList.innerHTML = paginatedHospitals.map(hospital => `
                    <div class="data-card">
                        <div class="data-header">
                            <h4 class="data-title">
                                <i class="fas fa-hospital-alt"></i> ${hospital.name}
                            </h4>
                            <div class="d-flex" style="gap: 5px;">
                                <button class="btn btn-sm btn-outline" onclick="editHospital(${hospital.id})" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline" onclick="viewHospitalStock(${hospital.id})" title="Voir les stocks">
                                    <i class="fas fa-tint"></i>
                                </button>
                                <button class="btn btn-sm btn-outline" onclick="deleteHospital(${hospital.id})" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div style="margin-top: 1rem;">
                            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-city"></i> ${hospital.city}
                            </p>
                            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-phone"></i> ${hospital.phone}
                            </p>
                            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-envelope"></i> ${hospital.email || 'Non spécifié'}
                            </p>
                        </div>
                        <div class="mt-1" style="padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                            <div class="d-flex justify-between">
                                <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Stocks totaux:</span>
                                <span style="color: white; font-weight: 600;">
                                    ${Object.values(hospital.bloodStock || {}).reduce((a, b) => a + b, 0)} poches
                                </span>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            
            renderPagination('hospitalsPagination', hospitals.length, config.currentHospitalPage, 'loadHospitalsList');
        }

        function saveHospital() {
            const form = document.getElementById('hospitalForm');
            if (!form.checkValidity()) {
                showToast('Veuillez remplir tous les champs obligatoires', 'error');
                return;
            }

            const hospitalData = {
                id: currentHospitalId || hospitals.length > 0 ? Math.max(...hospitals.map(h => h.id)) + 1 : 1,
                name: document.getElementById('hospitalName').value,
                city: document.getElementById('hospitalCity').value,
                phone: document.getElementById('hospitalPhone').value,
                email: document.getElementById('hospitalEmail').value,
                address: document.getElementById('hospitalAddress').value,
                bloodStock: {
                    "O+": parseInt(document.getElementById('hospitalOPlus').value) || 0,
                    "O-": parseInt(document.getElementById('hospitalONeg').value) || 0,
                    "A+": 0,
                    "A-": 0,
                    "B+": 0,
                    "B-": 0,
                    "AB+": 0,
                    "AB-": 0
                },
                status: 'active',
                createdAt: new Date().toISOString()
            };

            if (currentHospitalId) {
                // Modification
                const index = hospitals.findIndex(h => h.id === currentHospitalId);
                if (index !== -1) {
                    hospitals[index] = { ...hospitals[index], ...hospitalData };
                    showToast('Hôpital modifié avec succès', 'success');
                }
            } else {
                // Nouvel hôpital
                hospitals.push(hospitalData);
                showToast('Hôpital ajouté avec succès', 'success');
            }

            // Sauvegarder
            localStorage.setItem('bloodHospitals', JSON.stringify(hospitals));
            
            // Fermer et rafraîchir
            closeModal('addHospitalModal');
            loadHospitalsList(config.currentHospitalPage);
            populateHospitalSelect();
            updateStats();
        }

        function editHospital(id) {
            const hospital = hospitals.find(h => h.id === id);
            if (!hospital) return;

            currentHospitalId = id;
            
            // Remplir le formulaire
            document.getElementById('hospitalName').value = hospital.name;
            document.getElementById('hospitalCity').value = hospital.city;
            document.getElementById('hospitalPhone').value = hospital.phone;
            document.getElementById('hospitalEmail').value = hospital.email || '';
            document.getElementById('hospitalAddress').value = hospital.address || '';
            document.getElementById('hospitalOPlus').value = hospital.bloodStock?.["O+"] || 0;
            document.getElementById('hospitalONeg').value = hospital.bloodStock?.["O-"] || 0;
            
            // Mettre à jour le titre
            document.getElementById('hospitalModalTitle').textContent = 'Modifier l\'Hôpital';
            
            // Ouvrir la modal
            openModal('addHospitalModal');
        }

        function viewHospitalStock(id) {
            const hospital = hospitals.find(h => h.id === id);
            if (!hospital) return;

            const stockHTML = Object.entries(hospital.bloodStock || {})
                .filter(([_, quantity]) => quantity > 0)
                .map(([type, quantity]) => `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <span style="color: rgba(255,255,255,0.9);">${type}</span>
                        <span style="color: ${quantity < 5 ? 'var(--danger-color)' : 'var(--success-color)'}; font-weight: 600;">
                            ${quantity} poches
                        </span>
                    </div>
                `).join('');

            const totalStock = Object.values(hospital.bloodStock || {}).reduce((a, b) => a + b, 0);
            
            showToast(`
                <div>
                    <h4 style="margin-bottom: 10px; color: white;">${hospital.name}</h4>
                    <p style="color: rgba(255,255,255,0.7); margin-bottom: 15px;">Stocks de sang disponibles:</p>
                    ${stockHTML}
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; margin-top: 10px; border-top: 2px solid var(--primary-color);">
                        <span style="color: white; font-weight: 600;">TOTAL</span>
                        <span style="color: white; font-weight: 600;">${totalStock} poches</span>
                    </div>
                </div>
            `, 'info');
        }

        function deleteHospital(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cet hôpital ?')) {
                hospitals = hospitals.filter(h => h.id !== id);
                localStorage.setItem('bloodHospitals', JSON.stringify(hospitals));
                showToast('Hôpital supprimé avec succès', 'success');
                loadHospitalsList(config.currentHospitalPage);
                populateHospitalSelect();
                updateStats();
            }
        }

        // Fonctions pour les demandes de sang
        function loadBloodRequests(page = 1) {
            config.currentRequestPage = page;
            const startIndex = (page - 1) * config.itemsPerPage;
            const endIndex = startIndex + config.itemsPerPage;
            const paginatedRequests = bloodRequests.slice(startIndex, endIndex);
            
            const requestsList = document.getElementById('requestsList');
            
            if (paginatedRequests.length === 0) {
                requestsList.innerHTML = `
                    <div class="data-card" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-hand-holding-medical" style="font-size: 3rem; color: rgba(255,255,255,0.3); margin-bottom: 1rem;"></i>
                        <h4 style="color: rgba(255,255,255,0.7);">Aucune demande de sang</h4>
                        <p style="color: rgba(255,255,255,0.5); margin-top: 0.5rem;">Créez votre première demande de sang urgent</p>
                    </div>
                `;
            } else {
                requestsList.innerHTML = paginatedRequests.map(request => `
                    <div class="data-card">
                        <div class="data-header">
                            <h4 class="data-title">
                                <i class="fas fa-tint"></i> ${request.bloodType} - ${request.quantity} poches
                            </h4>
                            <span class="data-badge" style="background: ${
                                request.urgency === 'critical' ? 'linear-gradient(135deg, #ff3838, #ff6b81)' :
                                request.urgency === 'urgent' ? 'linear-gradient(135deg, #ff9f43, #ffaf60)' :
                                'linear-gradient(135deg, #1e90ff, #4da6ff)'
                            };">
                                ${request.urgency === 'critical' ? 'CRITIQUE' : 
                                  request.urgency === 'urgent' ? 'URGENT' : 'NORMALE'}
                            </span>
                        </div>
                        <div style="margin-top: 1rem;">
                            <p style="color: rgba(255,255,255,0.9); margin-bottom: 0.5rem;">
                                <i class="fas fa-hospital"></i> ${request.hospitalName}
                            </p>
                            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-clock"></i> ${request.date} - ${request.time}
                            </p>
                            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-user-injured"></i> ${request.reason || 'Non spécifié'}
                            </p>
                        </div>
                        <div class="mt-1" style="padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                            <div class="d-flex justify-between">
                                <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Statut:</span>
                                <span style="color: ${
                                    request.status === 'completed' ? 'var(--success-color)' :
                                    request.status === 'pending' ? 'var(--warning-color)' : 'var(--danger-color)'
                                }; font-weight: 600;">
                                    ${request.status === 'completed' ? 'COMPLÉTÉE' :
                                      request.status === 'pending' ? 'EN ATTENTE' : 'URGENTE'}
                                </span>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            
            renderPagination('requestsPagination', bloodRequests.length, config.currentRequestPage, 'loadBloodRequests');
        }

        function submitBloodRequest() {
            const form = document.getElementById('bloodRequestForm');
            if (!form.checkValidity()) {
                showToast('Veuillez remplir tous les champs obligatoires', 'error');
                return;
            }

            const hospitalId = parseInt(document.getElementById('requestHospital').value);
            const hospital = hospitals.find(h => h.id === hospitalId);
            
            if (!hospital) {
                showToast('Hôpital non trouvé', 'error');
                return;
            }

            const now = new Date();
            const requestData = {
                id: bloodRequests.length > 0 ? Math.max(...bloodRequests.map(r => r.id)) + 1 : 1,
                bloodType: document.getElementById('requestBloodType').value,
                quantity: parseInt(document.getElementById('requestQuantity').value),
                hospitalId: hospitalId,
                hospitalName: hospital.name,
                urgency: document.getElementById('requestUrgency').value,
                reason: document.getElementById('requestReason').value,
                medicalNotes: document.getElementById('requestMedicalNotes').value,
                date: now.toISOString().split('T')[0],
                time: now.toTimeString().split(' ')[0].substring(0, 5),
                status: 'pending',
                createdAt: now.toISOString()
            };

            bloodRequests.unshift(requestData);
            localStorage.setItem('bloodRequests', JSON.stringify(bloodRequests));
            
            showToast('Demande de sang envoyée avec succès', 'success');
            closeModal('requestBloodModal');
            
            // Si c'est une urgence, afficher une notification spéciale
            if (requestData.urgency === 'critical') {
                setTimeout(() => {
                    showToast('🚨 Demande CRITIQUE envoyée! Notifications aux donneurs en cours...', 'warning', 5000);
                }, 1000);
            }
            
            // Recharger la liste
            if (document.getElementById('requestsTab').classList.contains('active')) {
                loadBloodRequests();
            } else {
                openTab('requests');
            }
            
            updateStats();
        }

        // Fonctions pour l'historique des dons
        function loadDonationsHistory(page = 1) {
            config.currentDonationPage = page;
            const startIndex = (page - 1) * config.itemsPerPage;
            const endIndex = startIndex + config.itemsPerPage;
            const paginatedDonations = donations.slice(startIndex, endIndex);
            
            const donationsList = document.getElementById('donationsList');
            
            if (paginatedDonations.length === 0) {
                donationsList.innerHTML = `
                    <div class="data-card" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-history" style="font-size: 3rem; color: rgba(255,255,255,0.3); margin-bottom: 1rem;"></i>
                        <h4 style="color: rgba(255,255,255,0.7);">Aucun don enregistré</h4>
                        <p style="color: rgba(255,255,255,0.5); margin-top: 0.5rem;">Commencez par enregistrer votre premier don</p>
                    </div>
                `;
            } else {
                donationsList.innerHTML = paginatedDonations.map(donation => `
                    <div class="data-card">
                        <div class="data-header">
                            <h4 class="data-title">
                                <i class="fas fa-tint"></i> ${donation.donorName}
                            </h4>
                            <div class="d-flex align-center">
                                <div class="blood-badge" style="width: 40px; height: 40px; font-size: 0.9rem; margin-right: 10px;">
                                    ${donation.bloodType}
                                </div>
                                <span class="data-badge" style="background: ${
                                    donation.status === 'completed' ? 'var(--success-color)' :
                                    donation.status === 'scheduled' ? 'var(--info-color)' : 'var(--warning-color)'
                                };">
                                    ${donation.status === 'completed' ? 'COMPLÉTÉ' : 'PLANIFIÉ'}
                                </span>
                            </div>
                        </div>
                        <div style="margin-top: 1rem;">
                            <p style="color: rgba(255,255,255,0.9); margin-bottom: 0.5rem;">
                                <i class="fas fa-calendar"></i> ${donation.date}
                            </p>
                            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">
                                <i class="fas fa-info-circle"></i> ${donation.quantity} poche(s) de sang
                            </p>
                        </div>
                    </div>
                `).join('');
            }
            
            renderPagination('donationsPagination', donations.length, config.currentDonationPage, 'loadDonationsHistory');
        }

        // Fonctions pour les statistiques et graphiques
        function updateStats() {
            // Total des donneurs
            document.getElementById('totalDonorsCount').textContent = donors.length;
            
            // Dons aujourd'hui
            const today = new Date().toISOString().split('T')[0];
            const todayDonations = donations.filter(d => d.date === today).length;
            document.getElementById('todayDonations').textContent = todayDonations;
            
            // Vies sauvées (estimation: chaque don peut sauver jusqu'à 3 vies)
            document.getElementById('livesSaved').textContent = donations.length * 3;
            
            // Mettre à jour les statistiques rapides
            updateQuickStats();
        }

        function initializeCharts() {
            updateChart();
            updateAnalytics();
        }

        function updateChart() {
            const chartContainer = document.getElementById('donationsChart');
            const period = parseInt(document.getElementById('chartPeriod')?.value || '7');
            
            // Générer des données de démonstration pour le graphique
            const data = generateChartData(period);
            
            // Créer le graphique en barres simple
            const maxValue = Math.max(...data.values);
            const barWidth = 35;
            const spacing = 20;
            const totalWidth = (barWidth + spacing) * data.labels.length;
            
            chartContainer.innerHTML = `
                <div style="position: relative; height: 100%; width: ${totalWidth}px; min-width: 100%;">
                    ${data.labels.map((label, index) => {
                        const height = (data.values[index] / maxValue) * 180;
                        const left = index * (barWidth + spacing);
                        return `
                            <div class="chart-bar" 
                                 style="left: ${left}px; height: ${height}px;"
                                 title="${label}: ${data.values[index]} dons">
                                <div style="position: absolute; bottom: -25px; left: 0; right: 0; text-align: center; color: rgba(255,255,255,0.7); font-size: 0.8rem;">
                                    ${label}
                                </div>
                                <div style="position: absolute; top: -25px; left: 0; right: 0; text-align: center; color: white; font-weight: 600;">
                                    ${data.values[index]}
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }

        function updateAnalytics() {
            // Graphique des groupes sanguins
            const bloodTypeChart = document.getElementById('bloodTypeChart');
            const bloodTypeData = calculateBloodTypeDistribution();
            
            bloodTypeChart.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; padding: 10px;">
                    ${Object.entries(bloodTypeData).map(([type, count]) => `
                        <div style="text-align: center;">
                            <div class="blood-badge" style="margin: 0 auto 10px; width: 50px; height: 50px; font-size: 1rem;">
                                ${type}
                            </div>
                            <div style="color: white; font-weight: 600; font-size: 1.2rem;">${count}</div>
                            <div style="color: rgba(255,255,255,0.7); font-size: 0.8rem;">donneurs</div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            // Graphique géographique
            const geographicChart = document.getElementById('geographicChart');
            const geographicData = calculateGeographicDistribution();
            
            geographicChart.innerHTML = `
                <div style="display: flex; flex-direction: column; gap: 10px; padding: 10px;">
                    ${Object.entries(geographicData).map(([city, count]) => {
                        const percentage = (count / donors.length) * 100;
                        return `
                            <div style="margin-bottom: 10px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span style="color: rgba(255,255,255,0.9);">${city}</span>
                                    <span style="color: white; font-weight: 600;">${count}</span>
                                </div>
                                <div style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;">
                                    <div style="height: 100%; width: ${percentage}%; background: var(--gradient-primary); border-radius: 3px;"></div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }

        function calculateBloodTypeDistribution() {
            const distribution = {};
            donors.forEach(donor => {
                distribution[donor.bloodType] = (distribution[donor.bloodType] || 0) + 1;
            });
            return distribution;
        }

        function calculateGeographicDistribution() {
            const distribution = {};
            donors.forEach(donor => {
                distribution[donor.city] = (distribution[donor.city] || 0) + 1;
            });
            return distribution;
        }

        function updateQuickStats() {
            const quickStats = document.getElementById('quickStats');
            
            const stats = {
                'Donneurs Actifs': donors.length,
                'Dons du Mois': donations.filter(d => {
                    const donationDate = new Date(d.date);
                    const now = new Date();
                    return donationDate.getMonth() === now.getMonth() && 
                           donationDate.getFullYear() === now.getFullYear();
                }).length,
                'Hôpitaux Actifs': hospitals.length,
                'Demandes En Cours': bloodRequests.filter(r => r.status === 'pending').length,
                'Taux de Compatibilité': '95%',
                'Satisfaction': '98%'
            };
            
            quickStats.innerHTML = Object.entries(stats).map(([label, value]) => `
                <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px; margin-bottom: 0.8rem;">
                    <div style="color: rgba(255,255,255,0.7); font-size: 0.85rem; margin-bottom: 0.3rem;">${label}</div>
                    <div style="color: white; font-size: 1.5rem; font-weight: 700;">${value}</div>
                </div>
            `).join('');
        }

        // Fonctions utilitaires
        function generateSampleDonations() {
            const sampleDonations = [];
            const donorsCopy = [...initialDonors];
            
            // Générer des dons aléatoires pour les 30 derniers jours
            const today = new Date();
            for (let i = 0; i < 30; i++) {
                const date = new Date();
                date.setDate(today.getDate() - Math.floor(Math.random() * 30));
                const dateStr = date.toISOString().split('T')[0];
                
                const randomDonor = donorsCopy[Math.floor(Math.random() * donorsCopy.length)];
                
                sampleDonations.push({
                    id: i + 1,
                    donorId: randomDonor.id,
                    donorName: randomDonor.name,
                    bloodType: randomDonor.bloodType,
                    date: dateStr,
                    quantity: 1,
                    status: 'completed'
                });
            }
            
            return sampleDonations;
        }

        function generateChartData(days) {
            const labels = [];
            const values = [];
            
            const today = new Date();
            for (let i = days - 1; i >= 0; i--) {
                const date = new Date();
                date.setDate(today.getDate() - i);
                const dateStr = date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
                labels.push(dateStr);
                
                // Compter les dons pour cette date
                const dateISO = date.toISOString().split('T')[0];
                const count = donations.filter(d => d.date === dateISO).length;
                values.push(count);
            }
            
            return { labels, values };
        }

        function populateHospitalSelect() {
            const select = document.getElementById('requestHospital');
            if (!select) return;
            
            select.innerHTML = '<option value="">Sélectionner...</option>' +
                hospitals.map(h => `<option value="${h.id}">${h.name} (${h.city})</option>`).join('');
        }

        function populateCityFilters() {
            const select = document.getElementById('cityFilter');
            if (!select) return;
            
            const cities = [...new Set(donors.map(d => d.city))].sort();
            select.innerHTML = '<option value="">Toutes les villes</option>' +
                cities.map(city => `<option value="${city}">${city}</option>`).join('');
        }

        function generateReport() {
            const reportType = document.getElementById('reportType').value;
            const reportFormat = document.getElementById('reportFormat').value;
            
            showToast(`Rapport ${reportType} généré en format ${reportFormat.toUpperCase()}`, 'success');
            
            // Simulation de téléchargement
            setTimeout(() => {
                showToast('Rapport téléchargé avec succès', 'success');
            }, 1000);
        }

        function renderPagination(elementId, totalItems, currentPage, callbackFunction) {
            const totalPages = Math.ceil(totalItems / config.itemsPerPage);
            if (totalPages <= 1) {
                document.getElementById(elementId).innerHTML = '';
                return;
            }

            let paginationHTML = '';
            
            // Bouton précédent
            paginationHTML += `
                <button class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''} 
                        onclick="${callbackFunction}(${currentPage - 1})">
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;

            // Pages
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    paginationHTML += `
                        <button class="pagination-btn ${i === currentPage ? 'active' : ''}" 
                                onclick="${callbackFunction}(${i})">
                            ${i}
                        </button>
                    `;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    paginationHTML += `<span class="pagination-btn" style="border: none; background: transparent;">...</span>`;
                }
            }

            // Bouton suivant
            paginationHTML += `
                <button class="pagination-btn" ${currentPage === totalPages ? 'disabled' : ''} 
                        onclick="${callbackFunction}(${currentPage + 1})">
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;

            document.getElementById(elementId).innerHTML = paginationHTML;
        }

        function showToast(message, type = 'info', duration = 3000) {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };
            
            const colors = {
                success: 'var(--success-color)',
                error: 'var(--danger-color)',
                warning: 'var(--warning-color)',
                info: 'var(--info-color)'
            };
            
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.id = toastId;
            toast.style.borderLeftColor = colors[type];
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="${icons[type]}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title" style="color: ${colors[type]};">${type.toUpperCase()}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="modal-close" onclick="document.getElementById('${toastId}').remove()" 
                        style="width: 24px; height: 24px; font-size: 1rem; margin-left: 10px;">&times;</button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto-remove après la durée spécifiée
            setTimeout(() => {
                const toastEl = document.getElementById(toastId);
                if (toastEl) {
                    toastEl.style.opacity = '0';
                    toastEl.style.transform = 'translateX(100%)';
                    setTimeout(() => toastEl.remove(), 300);
                }
            }, duration);
        }

        function handleScroll() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }

        function notifyEmergencyDonors() {
            const emergencyDonors = donors.filter(d => d.bloodType === 'O-');
            
            if (emergencyDonors.length === 0) {
                showToast('Aucun donneur O- disponible pour cette urgence', 'error');
                return;
            }
            
            showToast(`Notifications envoyées à ${emergencyDonors.length} donneurs O-`, 'success');
            
            // Simuler l'envoi de notifications
            setTimeout(() => {
                showToast('📱 SMS envoyés aux donneurs compatibles', 'info', 4000);
            }, 1000);
            
            setTimeout(() => {
                showToast('📧 Emails de notification délivrés', 'info', 4000);
            }, 2000);
            
            setTimeout(() => {
                showToast('✅ 2 donneurs ont déjà confirmé leur disponibilité', 'success', 5000);
            }, 3000);
        }

        function openEmergencyDetails() {
            // Remplir la liste des donneurs O- pour l'urgence
            const emergencyDonorsList = document.getElementById('emergencyDonorsList');
            const emergencyDonors = donors.filter(d => d.bloodType === 'O-');
            
            if (emergencyDonors.length === 0) {
                emergencyDonorsList.innerHTML = `
                    <div class="data-card" style="text-align: center; padding: 1.5rem;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: var(--warning-color); margin-bottom: 1rem;"></i>
                        <h4 style="color: rgba(255,255,255,0.9);">Aucun donneur O- disponible</h4>
                        <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-top: 0.5rem;">
                            Envoyez une notification générale pour trouver des donneurs compatibles.
                        </p>
                    </div>
                `;
            } else {
                emergencyDonorsList.innerHTML = emergencyDonors.map(donor => `
                    <div class="data-card" style="margin-bottom: 0.5rem; padding: 1rem;">
                        <div class="d-flex justify-between align-center">
                            <div>
                                <h5 style="color: white; margin-bottom: 0.3rem;">${donor.name}</h5>
                                <p style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">
                                    <i class="fas fa-phone"></i> ${donor.phone} | 
                                    <i class="fas fa-city"></i> ${donor.city}
                                </p>
                            </div>
                            <button class="btn btn-sm btn-primary" onclick="contactDonor(${donor.id})">
                                <i class="fas fa-phone"></i> Contacter
                            </button>
                        </div>
                    </div>
                `).join('');
            }
            
            // Ouvrir la modal
            openModal('emergencyModal');
        }

        function contactDonor(donorId) {
            const donor = donors.find(d => d.id === donorId);
            if (!donor) return;
            
            showToast(`Appel en cours vers ${donor.phone}...`, 'info');
            
            // Simulation d'appel
            setTimeout(() => {
                showToast(`✅ ${donor.name} a accepté de venir donner du sang!`, 'success', 4000);
            }, 2000);
        }

        function scrollToContact() {
            const footer = document.getElementById('contact');
            if (footer) {
                footer.scrollIntoView({ behavior: 'smooth' });
            }
        }

        // Initialiser le graphique quand la période change
        document.getElementById('chartPeriod')?.addEventListener('change', updateChart);
    </script>
</body>
</html>