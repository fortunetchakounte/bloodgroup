<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || getUserRole() !== 'donor') {
    redirect('../public/login.php');
}

$page_title = "My Donation History";

// Debug - Vérifions d'abord si l'utilisateur a des donations
$debug_stmt = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE donor_id = ?");
$debug_stmt->execute([$_SESSION['user_id']]);
$donation_count = $debug_stmt->fetchColumn();
error_log("Donor ID: " . $_SESSION['user_id'] . " has " . $donation_count . " donations");

// Get complete history - CORRIGÉ: donation_requests au lieu de blood_requests
$stmt = $pdo->prepare("
    SELECT d.*, dr.quantity, dr.urgency, h.hospital_name, h.city, bg.name as blood_group_name
    FROM donations d
    JOIN donation_requests dr ON d.request_id = dr.id
    JOIN hospitals h ON dr.hospital_id = h.id
    JOIN blood_groups bg ON dr.blood_group_id = bg.id
    WHERE d.donor_id = ?
    ORDER BY d.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$history = $stmt->fetchAll();

// Debug - Afficher le nombre de résultats
error_log("History query returned " . count($history) . " results");

// Statistics
$stats = [
    'total' => count($history),
    'completed' => 0,
    'scheduled' => 0,
    'cancelled' => 0,
    'pending' => 0,
    'total_lives' => 0,
    'unique_hospitals' => 0
];

$hospitals = [];
foreach ($history as $item) {
    if (isset($item['status'])) {
        $stats[$item['status']]++;
    }
    if ($item['status'] === 'completed') {
        $stats['total_lives'] += 3; // Estimation: 3 lives per donation
    }
    if (!empty($item['hospital_name'])) {
        $hospitals[$item['hospital_name']] = true;
    }
}
$stats['unique_hospitals'] = count($hospitals);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - BloodLink</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #e63946;
            --primary-dark: #c1121f;
            --primary-light: #ff6b6b;
            --primary-soft: #ffe5e5;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1f2937;
            --gray-dark: #374151;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --light: #f9fafb;
            --white: #ffffff;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --radius: 12px;
            --radius-sm: 8px;
            --transition: all 0.2s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            color: var(--dark);
            line-height: 1.5;
        }

        /* Navigation */
        .navbar {
            background: var(--white);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--gray-light);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--dark);
        }

        .logo i {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--light);
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            border: 1px solid var(--gray-light);
            color: var(--gray-dark);
        }

        .user-info i {
            color: var(--primary);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-right a {
            color: var(--gray);
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            border-radius: 30px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .nav-right a:hover {
            background: var(--light);
            color: var(--primary);
        }

        .nav-right a.active {
            background: var(--primary);
            color: white;
        }

        .logout-btn {
            background: #fee2e2 !important;
            color: var(--danger) !important;
        }

        .logout-btn:hover {
            background: #fecaca !important;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Header */
        .page-header {
            margin-bottom: 2rem;
            animation: fadeInUp 0.5s ease;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .page-header h1 i {
            color: var(--primary);
        }

        .page-header p {
            color: var(--gray);
            font-size: 1rem;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.8rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-soft);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: var(--primary);
            font-size: 1.3rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.3rem;
            line-height: 1;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-trend {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: var(--gray);
        }

        .stat-trend i {
            color: var(--primary);
        }

        /* Filters */
        .filters-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
            background: var(--white);
            padding: 1.2rem;
            border-radius: var(--radius);
            border: 1px solid var(--gray-light);
            animation: fadeInUp 0.7s ease;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.8rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 0.9rem;
        }

        .filter-select {
            padding: 0.8rem 2rem 0.8rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            min-width: 150px;
            cursor: pointer;
            background: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.8rem center;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn-clear {
            padding: 0.8rem 1.2rem;
            background: var(--light);
            color: var(--gray);
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-clear:hover {
            background: var(--gray-light);
            color: var(--dark);
        }

        /* Table Card */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            margin-bottom: 2rem;
            position: relative;
            animation: fadeInUp 0.8s ease;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .card-header h2 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header h2 i {
            color: var(--primary);
        }

        .badge-count {
            background: var(--primary-soft);
            color: var(--primary);
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Table */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 1rem 0.8rem;
            color: var(--gray);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--gray-light);
            background: var(--light);
        }

        td {
            padding: 1.2rem 0.8rem;
            border-bottom: 1px solid var(--gray-light);
            color: var(--dark);
            font-size: 0.95rem;
            transition: var(--transition);
        }

        tr:hover td {
            background: var(--light);
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-warning {
            background: #fff3e0;
            color: #ef6c00;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #e3f2fd;
            color: #0d47a1;
        }

        .badge-primary {
            background: var(--primary-soft);
            color: var(--primary);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(230, 57, 70, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-light);
            color: var(--gray);
        }

        .btn-outline:hover {
            background: var(--light);
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--light);
            border: 1px solid var(--gray-light);
            color: var(--gray);
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-icon:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--white);
            border-radius: var(--radius);
            border: 1px solid var(--gray-light);
        }

        .empty-state i {
            font-size: 5rem;
            color: var(--gray-light);
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--gray);
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        /* Hospital info */
        .hospital-info {
            display: flex;
            flex-direction: column;
        }

        .hospital-name {
            font-weight: 600;
            color: var(--dark);
        }

        .hospital-city {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 0.2rem;
        }

        .hospital-city i {
            color: var(--primary);
            margin-right: 0.2rem;
        }

        /* Urgency indicators */
        .urgency-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 0.3rem;
        }

        .dot-emergency, .dot-high {
            background: var(--danger);
        }

        .dot-medium {
            background: var(--warning);
        }

        .dot-low {
            background: var(--success);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.3rem;
            margin-top: 2rem;
        }

        .page-link {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--white);
            color: var(--gray);
            text-decoration: none;
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .page-link:hover,
        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .nav-right {
                flex-wrap: wrap;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                width: 100%;
            }

            .filter-select {
                width: 100%;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            th {
                font-size: 0.75rem;
            }

            td {
                font-size: 0.85rem;
                padding: 1rem 0.5rem;
            }

            .container {
                padding: 0 1rem;
            }
        }

        @media (max-width: 480px) {
            .stat-number {
                font-size: 2rem;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }

            .btn-sm {
                padding: 0.3rem 0.6rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">
                <i class="fas fa-heartbeat"></i>
                BloodLink
            </div>
            <div class="user-info">
                <i class="fas fa-user"></i>
                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="requests.php"><i class="fas fa-tint"></i> Requests</a>
            <a href="history.php" class="active"><i class="fas fa-history"></i> History</a>
            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-history"></i>
                My Donation History
            </h1>
            <p>Track all your blood donations and their impact</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Responses</div>
                <div class="stat-trend">
                    <i class="fas fa-calendar"></i> All time
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
                <div class="stat-trend">
                    <i class="fas fa-heart"></i> <?php echo $stats['completed'] * 3; ?> lives saved
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['scheduled']; ?></div>
                <div class="stat-label">Scheduled</div>
                <div class="stat-trend">
                    <i class="fas fa-calendar-alt"></i> Upcoming
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_lives']; ?></div>
                <div class="stat-label">Lives Potentially Saved</div>
                <div class="stat-trend">
                    <i class="fas fa-users"></i> <?php echo $stats['unique_hospitals']; ?> hospitals helped
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by hospital or blood group...">
            </div>
            
            <select class="filter-select" id="statusFilter">
                <option value="">All Status</option>
                <option value="completed">Completed</option>
                <option value="scheduled">Scheduled</option>
                <option value="cancelled">Cancelled</option>
                <option value="pending">Pending</option>
            </select>
            
            <select class="filter-select" id="urgencyFilter">
                <option value="">All Urgencies</option>
                <option value="emergency">Emergency</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            
            <a href="history.php" class="btn-clear">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        </div>

        <!-- History Table -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-list"></i>
                    Donation History
                </h2>
                <span class="badge-count">
                    <i class="fas fa-clipboard-list"></i>
                    <?php echo $stats['total']; ?> entries
                </span>
            </div>

            <?php if (empty($history)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>No History Yet</h3>
                    <p>You haven't participated in any blood donations yet.</p>
                    <a href="requests.php" class="btn btn-primary">
                        <i class="fas fa-tint"></i> View Available Requests
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="historyTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Hospital</th>
                                <th>Blood Group</th>
                                <th>Urgency</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $item): ?>
                            <tr class="history-row" 
                                data-status="<?php echo $item['status']; ?>"
                                data-urgency="<?php echo $item['urgency']; ?>"
                                data-hospital="<?php echo strtolower($item['hospital_name']); ?>"
                                data-blood="<?php echo strtolower($item['blood_group_name']); ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="far fa-calendar-alt" style="color: var(--gray);"></i>
                                        <div>
                                            <div><?php echo date('d/m/Y', strtotime($item['created_at'])); ?></div>
                                            <small style="color: var(--gray);">
                                                <?php echo date('H:i', strtotime($item['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="hospital-info">
                                        <span class="hospital-name">
                                            <i class="fas fa-hospital" style="color: var(--primary); margin-right: 0.3rem;"></i>
                                            <?php echo htmlspecialchars($item['hospital_name']); ?>
                                        </span>
                                        <?php if (!empty($item['city'])): ?>
                                        <span class="hospital-city">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($item['city']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: var(--primary);">
                                        <?php echo htmlspecialchars($item['blood_group_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $urgency_colors = [
                                        'emergency' => 'badge-danger',
                                        'high' => 'badge-danger',
                                        'medium' => 'badge-warning',
                                        'low' => 'badge-success'
                                    ];
                                    $urgency_labels = [
                                        'emergency' => 'Emergency',
                                        'high' => 'High',
                                        'medium' => 'Medium',
                                        'low' => 'Low'
                                    ];
                                    $urgency_class = isset($urgency_colors[$item['urgency']]) ? $urgency_colors[$item['urgency']] : 'badge-info';
                                    ?>
                                    <span class="badge <?php echo $urgency_class; ?>">
                                        <span class="urgency-dot dot-<?php echo $item['urgency']; ?>"></span>
                                        <?php echo $urgency_labels[$item['urgency']] ?? $item['urgency']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $status_colors = [
                                        'scheduled' => 'badge-warning',
                                        'completed' => 'badge-success',
                                        'cancelled' => 'badge-danger',
                                        'pending' => 'badge-info'
                                    ];
                                    $status_labels = [
                                        'scheduled' => 'Scheduled',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                        'pending' => 'Pending'
                                    ];
                                    $status_class = isset($status_colors[$item['status']]) ? $status_colors[$item['status']] : 'badge-info';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <i class="fas fa-<?php 
                                            echo $item['status'] === 'completed' ? 'check-circle' : 
                                                ($item['status'] === 'scheduled' ? 'clock' : 
                                                ($item['status'] === 'cancelled' ? 'times-circle' : 'info-circle')); 
                                        ?>"></i>
                                        <?php echo $status_labels[$item['status']] ?? $item['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.3rem;">
                                        <a href="view-response.php?id=<?php echo $item['id']; ?>" class="btn-icon" title="View details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($item['status'] === 'completed'): ?>
                                        <a href="certificate.php?id=<?php echo $item['id']; ?>" class="btn-icon" title="Download certificate">
                                            <i class="fas fa-award"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Simple pagination (static for now) -->
                <div class="pagination">
                    <a href="#" class="page-link"><i class="fas fa-chevron-left"></i></a>
                    <a href="#" class="page-link active">1</a>
                    <a href="#" class="page-link">2</a>
                    <a href="#" class="page-link">3</a>
                    <a href="#" class="page-link">4</a>
                    <a href="#" class="page-link">5</a>
                    <a href="#" class="page-link"><i class="fas fa-chevron-right"></i></a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Impact Summary -->
        <?php if (!empty($history)): ?>
        <div class="card" style="background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;">
                <div>
                    <h3 style="margin-bottom: 0.5rem;">Your Impact</h3>
                    <p style="opacity: 0.9;">Every donation makes a difference</p>
                </div>
                <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: 700;"><?php echo $stats['total']; ?></div>
                        <div style="opacity: 0.9;">Total Responses</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: 700;"><?php echo $stats['total_lives']; ?></div>
                        <div style="opacity: 0.9;">Lives Saved</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: 700;"><?php echo $stats['unique_hospitals']; ?></div>
                        <div style="opacity: 0.9;">Hospitals</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const urgencyFilter = document.getElementById('urgencyFilter');
            const rows = document.querySelectorAll('.history-row');

            function filterTable() {
                const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
                const statusValue = statusFilter ? statusFilter.value : '';
                const urgencyValue = urgencyFilter ? urgencyFilter.value : '';

                rows.forEach(row => {
                    const hospital = row.dataset.hospital || '';
                    const blood = row.dataset.blood || '';
                    const status = row.dataset.status || '';
                    const urgency = row.dataset.urgency || '';

                    const matchesSearch = searchTerm === '' || 
                        hospital.includes(searchTerm) || 
                        blood.includes(searchTerm);
                    const matchesStatus = statusValue === '' || status === statusValue;
                    const matchesUrgency = urgencyValue === '' || urgency === urgencyValue;

                    row.style.display = (matchesSearch && matchesStatus && matchesUrgency) ? '' : 'none';
                });
            }

            if (searchInput) searchInput.addEventListener('input', filterTable);
            if (statusFilter) statusFilter.addEventListener('change', filterTable);
            if (urgencyFilter) urgencyFilter.addEventListener('change', filterTable);
        });

        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.stat-card, .card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>