<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../public/login.php');
}

$page_title = "Dashboard - Admin";

// Retrieve real-time statistics
try {
    // General statistics
    $total_donors = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'donor'")->fetchColumn();
    $verified_donors = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'donor' AND is_verified = 1")->fetchColumn();
    $pending_donors = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'donor' AND is_verified = 0")->fetchColumn();
    
    $total_hospitals = $pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();
    $verified_hospitals = $pdo->query("SELECT COUNT(*) FROM hospitals WHERE is_verified = 1")->fetchColumn();
    $pending_hospitals = $pdo->query("SELECT COUNT(*) FROM hospitals WHERE is_verified = 0")->fetchColumn();
    
    // Blood requests
    $total_requests = $pdo->query("SELECT COUNT(*) FROM blood_requests")->fetchColumn();
    $pending_requests = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'pending'")->fetchColumn();
    $approved_requests = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'approved'")->fetchColumn();
    $fulfilled_requests = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'fulfilled'")->fetchColumn();
    
    // Urgent requests
    $emergency_requests = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE urgency = 'emergency' AND status = 'pending'")->fetchColumn();
    $urgent_requests = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE urgency = 'urgent' AND status = 'pending'")->fetchColumn();
    $normal_requests = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE urgency = 'normal' AND status = 'pending'")->fetchColumn();
    
    // Donations
    $total_donations = $pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn();
    $completed_donations = $pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'completed'")->fetchColumn();
    
    // Monthly donations
    $current_month = date('Y-m');
    $monthly_donations = $pdo->query("SELECT COUNT(*) FROM donations WHERE DATE_FORMAT(created_at, '%Y-%m') = '$current_month'")->fetchColumn();
    
    // Recent activities
    $recent_donors = $pdo->query("SELECT full_name, created_at FROM users WHERE user_type = 'donor' ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $recent_hospitals = $pdo->query("SELECT hospital_name, created_at FROM hospitals ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $recent_requests = $pdo->query("SELECT r.*, h.hospital_name FROM blood_requests r JOIN hospitals h ON r.hospital_id = h.id ORDER BY r.created_at DESC LIMIT 5")->fetchAll();
    
} catch (Exception $e) {
    // In case of error, initialize to 0
    $total_donors = $verified_donors = $pending_donors = 0;
    $total_hospitals = $verified_hospitals = $pending_hospitals = 0;
    $total_requests = $pending_requests = $approved_requests = $fulfilled_requests = 0;
    $emergency_requests = $urgent_requests = $normal_requests = 0;
    $total_donations = $completed_donations = $monthly_donations = 0;
    $recent_donors = $recent_hospitals = $recent_requests = [];
}

// Get blood groups for chart
$blood_groups_stats = $pdo->query("
    SELECT bg.name, COUNT(u.id) as count 
    FROM users u 
    RIGHT JOIN blood_groups bg ON u.blood_group_id = bg.id AND u.user_type = 'donor'
    GROUP BY bg.id, bg.name
    ORDER BY bg.name
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --light: #f9fafb;
            --white: #ffffff;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            color: var(--dark);
            line-height: 1.5;
        }

        /* Navigation */
        .navbar {
            background: var(--dark);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo i {
            color: var(--primary);
        }

        .user-info {
            background: rgba(255,255,255,0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .nav-right {
            display: flex;
            gap: 0.5rem;
        }

        .nav-right a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }

        .nav-right a:hover,
        .nav-right a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .logout-btn {
            background: var(--danger) !important;
            color: white !important;
            margin-left: 1rem;
        }

        .logout-btn:hover {
            background: #dc2626 !important;
        }

        /* Main container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: var(--dark);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--gray);
            font-size: 1rem;
        }

        /* Statistics cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--info));
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-title {
            color: var(--gray);
            font-size: 0.9rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .stat-detail {
            color: var(--gray);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-detail span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-detail i {
            font-size: 0.8rem;
        }

        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }
        .text-warning { color: var(--warning); }
        .text-info { color: var(--info); }

        /* Main grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Admin cards */
        .admin-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border-top: 4px solid var(--primary);
        }

        .admin-card h3 {
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
        }

        .admin-card h3 i {
            color: var(--primary);
        }

        .stats-list {
            list-style: none;
        }

        .stats-list li {
            padding: 0.8rem 0;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stats-list li:last-child {
            border-bottom: none;
        }

        .stats-list .label {
            color: var(--gray);
            font-size: 0.95rem;
        }

        .stats-list .value {
            font-weight: 600;
            color: var(--dark);
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--gray-light);
            border-radius: 3px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--info));
            border-radius: 3px;
            transition: width 0.3s;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-primary {
            background: #e0e7ff;
            color: #3730a3;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.6rem 1.2rem;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .btn-block {
            width: 100%;
            justify-content: center;
            margin-top: 1rem;
        }

        /* Recent table */
        .recent-table {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-top: 2rem;
        }

        .recent-table h3 {
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
        }

        .recent-table h3 i {
            color: var(--primary);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 1rem 0.5rem;
            color: var(--gray);
            font-weight: 600;
            font-size: 0.9rem;
            border-bottom: 2px solid var(--gray-light);
        }

        td {
            padding: 1rem 0.5rem;
            border-bottom: 1px solid var(--gray-light);
            color: var(--dark);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: var(--light);
        }

        /* Quick actions */
        .quick-actions {
            margin-top: 2rem;
        }

        .quick-actions h3 {
            color: var(--dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--white);
            border-radius: 10px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
            border: 1px solid var(--gray-light);
        }

        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .action-btn i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .action-btn span {
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            .nav-left {
                width: 100%;
                justify-content: space-between;
            }
            
            .nav-right {
                width: 100%;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 0 1rem;
            }
        }

        @media (max-width: 480px) {
            .stat-number {
                font-size: 1.8rem;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">
                <i class="fas fa-tint"></i>
                BloodLink Admin
            </div>
            <div class="user-info">
                <i class="fas fa-user-shield"></i>
                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="donors.php"><i class="fas fa-users"></i> Donors</a>
            <a href="hospitals.php"><i class="fas fa-hospital"></i> Hospitals</a>
            <a href="requests.php"><i class="fas fa-tint"></i> Requests</a>
            <a href="validation.php"><i class="fas fa-check-circle"></i> Validations</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <p>Welcome to your administration space. Here's a summary of activity.</p>
        </div>

        <!-- Main Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Donors</span>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($total_donors); ?></div>
                <div class="stat-detail">
                    <span class="text-success"><i class="fas fa-check-circle"></i> <?php echo $verified_donors; ?> verified</span>
                    <span class="text-warning"><i class="fas fa-clock"></i> <?php echo $pending_donors; ?> pending</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Hospitals</span>
                    <div class="stat-icon">
                        <i class="fas fa-hospital"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($total_hospitals); ?></div>
                <div class="stat-detail">
                    <span class="text-success"><i class="fas fa-check-circle"></i> <?php echo $verified_hospitals; ?> verified</span>
                    <span class="text-warning"><i class="fas fa-clock"></i> <?php echo $pending_hospitals; ?> pending</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Requests</span>
                    <div class="stat-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($total_requests); ?></div>
                <div class="stat-detail">
                    <span class="text-warning"><i class="fas fa-clock"></i> <?php echo $pending_requests; ?> ongoing</span>
                    <span class="text-success"><i class="fas fa-check"></i> <?php echo $fulfilled_requests; ?> fulfilled</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Donations</span>
                    <div class="stat-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo number_format($total_donations); ?></div>
                <div class="stat-detail">
                    <span class="text-success"><i class="fas fa-calendar"></i> <?php echo $monthly_donations; ?> this month</span>
                    <span class="text-info"><i class="fas fa-check"></i> <?php echo $completed_donations; ?> completed</span>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="dashboard-grid">
            <!-- Quick Stats Card -->
            <div class="admin-card">
                <h3><i class="fas fa-chart-pie"></i> Blood Groups Distribution</h3>
                <?php foreach ($blood_groups_stats as $bg): ?>
                <div style="margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                        <span style="color: var(--gray);">Group <?php echo $bg['name']; ?></span>
                        <span style="font-weight: 600;"><?php echo $bg['count']; ?> donors</span>
                    </div>
                    <?php 
                    $percentage = $total_donors > 0 ? round(($bg['count'] / $total_donors) * 100) : 0;
                    ?>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <a href="donors.php" class="btn btn-block btn-outline">View all donors</a>
            </div>

            <!-- Pending Validations Card -->
            <div class="admin-card">
                <h3><i class="fas fa-clock"></i> Pending Validations</h3>
                <ul class="stats-list">
                    <li>
                        <span class="label"><i class="fas fa-user"></i> Donors to validate</span>
                        <span class="value badge badge-warning"><?php echo $pending_donors; ?></span>
                    </li>
                    <li>
                        <span class="label"><i class="fas fa-hospital"></i> Hospitals to validate</span>
                        <span class="value badge badge-warning"><?php echo $pending_hospitals; ?></span>
                    </li>
                </ul>
                
                <div style="margin-top: 1.5rem;">
                    <h4 style="color: var(--dark); font-size: 1rem; margin-bottom: 0.5rem;">Recent registrations</h4>
                    <ul class="stats-list">
                        <?php 
                        $recent_pending = array_slice(array_merge($recent_donors, $recent_hospitals), 0, 3);
                        foreach ($recent_pending as $item): 
                        ?>
                        <li>
                            <span class="label">
                                <?php if (isset($item['full_name'])): ?>
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['full_name']); ?>
                                <?php else: ?>
                                    <i class="fas fa-hospital"></i> <?php echo htmlspecialchars($item['hospital_name']); ?>
                                <?php endif; ?>
                            </span>
                            <span class="value"><?php echo date('d/m', strtotime($item['created_at'])); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <a href="validation.php" class="btn btn-block">Manage validations</a>
            </div>

            <!-- Urgent Requests Card -->
            <div class="admin-card">
                <h3><i class="fas fa-exclamation-triangle"></i> Urgent Requests</h3>
                
                <div style="margin-bottom: 1rem;">
                    <div class="stats-list">
                        <li>
                            <span class="label"><i class="fas fa-skull-crosswalk"></i> High urgency</span>
                            <span class="value badge badge-danger"><?php echo $emergency_requests; ?></span>
                        </li>
                        <li>
                            <span class="label"><i class="fas fa-exclamation"></i> Medium urgency</span>
                            <span class="value badge badge-warning"><?php echo $urgent_requests; ?></span>
                        </li>
                        <li>
                            <span class="label"><i class="fas fa-clock"></i> Normal</span>
                            <span class="value badge badge-info"><?php echo $normal_requests; ?></span>
                        </li>
                    </div>
                </div>

                <?php if (!empty($recent_requests)): ?>
                <h4 style="color: var(--dark); font-size: 1rem; margin: 1rem 0 0.5rem;">Recent requests</h4>
                <ul class="stats-list">
                    <?php foreach (array_slice($recent_requests, 0, 3) as $req): ?>
                    <li>
                        <span class="label">
                            <i class="fas fa-hospital"></i> <?php echo htmlspecialchars($req['hospital_name']); ?>
                        </span>
                        <span>
                            <?php if ($req['urgency'] == 'emergency'): ?>
                                <span class="badge badge-danger">Urgent</span>
                            <?php elseif ($req['urgency'] == 'urgent'): ?>
                                <span class="badge badge-warning">High</span>
                            <?php else: ?>
                                <span class="badge badge-info">Normal</span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <a href="requests.php?urgency=emergency" class="btn btn-block">View emergencies</a>
            </div>
        </div>

        <!-- Recent Activities Table -->
        <div class="recent-table">
            <h3><i class="fas fa-history"></i> Recent Activities</h3>
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Action</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $activities = [];
                    foreach ($recent_donors as $donor) {
                        $activities[] = [
                            'type' => 'donor',
                            'name' => $donor['full_name'],
                            'action' => 'New donor',
                            'date' => $donor['created_at'],
                            'status' => 'success'
                        ];
                    }
                    foreach ($recent_hospitals as $hospital) {
                        $activities[] = [
                            'type' => 'hospital',
                            'name' => $hospital['hospital_name'],
                            'action' => 'New hospital',
                            'date' => $hospital['created_at'],
                            'status' => 'info'
                        ];
                    }
                    foreach ($recent_requests as $request) {
                        $activities[] = [
                            'type' => 'request',
                            'name' => $request['hospital_name'],
                            'action' => 'New request',
                            'date' => $request['created_at'],
                            'status' => $request['urgency'] == 'emergency' ? 'danger' : 'warning'
                        ];
                    }
                    
                    // Sort by date
                    usort($activities, function($a, $b) {
                        return strtotime($b['date']) - strtotime($a['date']);
                    });
                    
                    // Display the 10 most recent
                    foreach (array_slice($activities, 0, 10) as $activity):
                    ?>
                    <tr>
                        <td>
                            <?php if ($activity['type'] == 'donor'): ?>
                                <span class="badge badge-success">Donor</span>
                            <?php elseif ($activity['type'] == 'hospital'): ?>
                                <span class="badge badge-info">Hospital</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Request</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($activity['name']); ?></td>
                        <td><?php echo $activity['action']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($activity['date'])); ?></td>
                        <td>
                            <?php if ($activity['status'] == 'success'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php elseif ($activity['status'] == 'danger'): ?>
                                <span class="badge badge-danger">Urgent</span>
                            <?php elseif ($activity['status'] == 'warning'): ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php else: ?>
                                <span class="badge badge-info">New</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($activities)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem;">
                            <i class="fas fa-inbox" style="font-size: 2rem; color: var(--gray-light); margin-bottom: 0.5rem;"></i>
                            <p>No recent activity</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="actions-grid">
                <a href="donors.php?action=add" class="action-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>New donor</span>
                </a>
                <a href="hospitals.php?action=add" class="action-btn">
                    <i class="fas fa-hospital"></i>
                    <span>New hospital</span>
                </a>
                <a href="requests.php?action=add" class="action-btn">
                    <i class="fas fa-tint"></i>
                    <span>Blood request</span>
                </a>
                <a href="validation.php" class="action-btn">
                    <i class="fas fa-check-circle"></i>
                    <span>Validations</span>
                </a>
                <a href="reports.php" class="action-btn">
                    <i class="fas fa-file-pdf"></i>
                    <span>Reports</span>
                </a>
                <a href="settings.php" class="action-btn">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>