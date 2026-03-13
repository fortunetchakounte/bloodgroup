<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Verify admin access
if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../public/login.php');
}

$page_title = "Account Validations";
$message = $_GET['msg'] ?? '';
$message_type = $_GET['type'] ?? 'success';
$active_tab = $_GET['tab'] ?? 'hospitals';

// Force cache busting
$cache_buster = time();

// Handle validation actions
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['type'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    $type = $_GET['type']; // 'hospital' or 'donor'
    
    try {
        if ($action === 'validate') {
            if ($type === 'hospital') {
                // Remove verified_at if column doesn't exist
                $stmt = $pdo->prepare("UPDATE hospitals SET is_verified = 1 WHERE id = ?");
            } else {
                // Remove verified_at if column doesn't exist
                $stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ? AND user_type = 'donor'");
            }
            $stmt->execute([$id]);
            
            $message = $type === 'hospital' ? "✅ Hospital validated successfully!" : "✅ Donor validated successfully!";
            $message_type = 'success';
        }
        elseif ($action === 'reject') {
            if ($type === 'hospital') {
                $stmt = $pdo->prepare("UPDATE hospitals SET is_verified = 0 WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE users SET is_verified = 0 WHERE id = ? AND user_type = 'donor'");
            }
            $stmt->execute([$id]);
            
            $message = $type === 'hospital' ? "⚠️ Hospital rejected." : "⚠️ Donor rejected.";
            $message_type = 'warning';
        }
        elseif ($action === 'delete') {
            if ($type === 'hospital') {
                // Check for associated requests
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM donation_requests WHERE hospital_id = ?");
                $stmt->execute([$id]);
                $has_requests = $stmt->fetchColumn();
                
                if ($has_requests == 0) {
                    $stmt = $pdo->prepare("DELETE FROM hospitals WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = "🗑️ Hospital deleted.";
                    $message_type = 'success';
                } else {
                    $message = "⚠️ Cannot delete: this hospital has associated requests.";
                    $message_type = 'danger';
                }
            } else {
                // Check for associated donations
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE donor_id = ?");
                $stmt->execute([$id]);
                $has_donations = $stmt->fetchColumn();
                
                if ($has_donations == 0) {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'donor'");
                    $stmt->execute([$id]);
                    $message = "🗑️ Donor deleted.";
                    $message_type = 'success';
                } else {
                    $message = "⚠️ Cannot delete: this donor has associated donations.";
                    $message_type = 'danger';
                }
            }
        }
        
        // Redirect with message and cache buster
        $redirect_url = "validation.php?msg=" . urlencode($message) . "&type=" . $message_type . "&tab=" . $active_tab . "&t=" . time();
        header("Location: " . $redirect_url);
        exit();
        
    } catch (PDOException $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get pending hospitals
$stmt = $pdo->query("
    SELECT h.*, 
           (SELECT COUNT(*) FROM donation_requests WHERE hospital_id = h.id) as requests_count
    FROM hospitals h 
    WHERE h.is_verified = 0 
    ORDER BY h.created_at DESC
");
$pending_hospitals = $stmt->fetchAll();

// Get all hospitals
$stmt = $pdo->query("
    SELECT h.*, 
           (SELECT COUNT(*) FROM donation_requests WHERE hospital_id = h.id) as requests_count
    FROM hospitals h 
    ORDER BY h.is_verified DESC, h.created_at DESC
");
$all_hospitals = $stmt->fetchAll();

// Get pending donors
$stmt = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM donations WHERE donor_id = u.id) as donations_count,
           bg.name as blood_group_name
    FROM users u 
    LEFT JOIN blood_groups bg ON u.blood_group_id = bg.id
    WHERE u.user_type = 'donor' AND u.is_verified = 0 
    ORDER BY u.created_at DESC
");
$pending_donors = $stmt->fetchAll();

// Get all donors
$stmt = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM donations WHERE donor_id = u.id) as donations_count,
           bg.name as blood_group_name
    FROM users u 
    LEFT JOIN blood_groups bg ON u.blood_group_id = bg.id
    WHERE u.user_type = 'donor'
    ORDER BY u.is_verified DESC, u.created_at DESC
");
$all_donors = $stmt->fetchAll();

// Calculate statistics
$stats = [
    'pending_hospitals' => count($pending_hospitals),
    'pending_donors' => count($pending_donors),
    'verified_hospitals' => count($all_hospitals) - count($pending_hospitals),
    'verified_donors' => count($all_donors) - count($pending_donors),
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - BloodLink Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --admin: #343a40;
            --admin-dark: #23272b;
            --admin-light: #6c757d;
            --admin-soft: #e9ecef;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #1f2937;
            --gray-dark: #374151;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --light: #f9fafb;
            --white: #ffffff;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
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
            background: var(--admin);
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
            gap: 2rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo i {
            color: var(--admin-light);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.1);
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-right a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            border-radius: 30px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .nav-right a:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .nav-right a.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .logout-btn {
            background: var(--danger) !important;
            color: white !important;
        }

        .logout-btn:hover {
            background: #c82333 !important;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .alert-success {
            background: #d4edda;
            border-color: var(--success);
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border-color: var(--danger);
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            border-color: var(--warning);
            color: #856404;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--admin);
            margin-bottom: 0.3rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            border-bottom: 2px solid var(--gray-light);
            padding-bottom: 0.5rem;
        }

        .tab {
            padding: 0.8rem 1.5rem;
            border-radius: 30px;
            background: var(--white);
            border: 1px solid var(--gray-light);
            color: var(--gray);
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab:hover {
            border-color: var(--admin);
            color: var(--admin);
        }

        .tab.active {
            background: var(--admin);
            color: white;
            border-color: var(--admin);
        }

        .tab a {
            text-decoration: none;
            color: inherit;
        }

        /* Filters */
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
            background: var(--white);
            padding: 1rem;
            border-radius: var(--radius);
            border: 1px solid var(--gray-light);
        }

        .search-box {
            padding: 0.6rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            width: 300px;
            font-size: 0.95rem;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--admin);
        }

        .filter-select {
            padding: 0.6rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            background: white;
            font-size: 0.95rem;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--admin);
        }

        .refresh-btn {
            background: var(--admin);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: auto;
            font-size: 0.95rem;
        }

        .refresh-btn:hover {
            background: var(--admin-dark);
        }

        /* Table */
        .table-responsive {
            overflow-x: auto;
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--light);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-dark);
            border-bottom: 2px solid var(--gray-light);
            white-space: nowrap;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-light);
            color: var(--dark);
        }

        tr:hover td {
            background: var(--light);
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .blood-badge {
            background: #dc3545;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            padding: 0.4rem 0.8rem;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: var(--warning);
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .action-group {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
        }

        /* Animation */
        @keyframes highlight {
            0% { background-color: #fff3cd; }
            100% { background-color: transparent; }
        }

        .highlight {
            animation: highlight 2s ease;
        }

        /* Responsive */
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

            .filters {
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .refresh-btn {
                margin-left: 0;
                width: 100%;
            }

            .container {
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                BloodLink Admin
            </div>
            <div class="user-info">
                <i class="fas fa-user-cog"></i>
                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="validation.php" class="active"><i class="fas fa-check-circle"></i> Validations</a>
            <a href="hospitals.php"><i class="fas fa-hospital"></i> Hospitals</a>
            <a href="requests.php"><i class="fas fa-clipboard-list"></i> Requests</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" id="messageAlert">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_hospitals']; ?></div>
                <div class="stat-label">Pending Hospitals</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_donors']; ?></div>
                <div class="stat-label">Pending Donors</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['verified_hospitals']; ?></div>
                <div class="stat-label">Verified Hospitals</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['verified_donors']; ?></div>
                <div class="stat-label">Verified Donors</div>
            </div>
        </div>

        <!-- Tabs with cache buster -->
        <div class="tabs">
            <a href="?tab=hospitals&t=<?php echo time(); ?>" class="tab <?php echo $active_tab === 'hospitals' ? 'active' : ''; ?>">
                <i class="fas fa-hospital"></i> Pending Hospitals (<?php echo count($pending_hospitals); ?>)
            </a>
            <a href="?tab=donors&t=<?php echo time(); ?>" class="tab <?php echo $active_tab === 'donors' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Pending Donors (<?php echo count($pending_donors); ?>)
            </a>
            <a href="?tab=all_hospitals&t=<?php echo time(); ?>" class="tab <?php echo $active_tab === 'all_hospitals' ? 'active' : ''; ?>">
                <i class="fas fa-building"></i> All Hospitals
            </a>
            <a href="?tab=all_donors&t=<?php echo time(); ?>" class="tab <?php echo $active_tab === 'all_donors' ? 'active' : ''; ?>">
                <i class="fas fa-user-friends"></i> All Donors
            </a>
        </div>

        <!-- Filters -->
        <div class="filters">
            <input type="text" class="search-box" placeholder="Search by name, email..." id="searchInput" onkeyup="filterTable()">
            <select class="filter-select" id="statusFilter" onchange="filterTable()">
                <option value="all">All Status</option>
                <option value="verified">Verified</option>
                <option value="pending">Pending</option>
            </select>
            <button class="refresh-btn" onclick="refreshData()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>

        <!-- Pending Hospitals Table -->
        <?php if ($active_tab === 'hospitals'): ?>
        <div class="table-responsive">
            <table id="hospitalsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Hospital Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Registration Date</th>
                        <th>Requests</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_hospitals as $hospital): ?>
                    <tr data-status="pending" data-name="<?php echo strtolower($hospital['hospital_name']); ?>" data-email="<?php echo strtolower($hospital['email']); ?>">
                        <td>#<?php echo $hospital['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($hospital['hospital_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($hospital['email']); ?></td>
                        <td><?php echo htmlspecialchars($hospital['phone'] ?? 'Not provided'); ?></td>
                        <td><?php echo htmlspecialchars($hospital['city']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($hospital['created_at'])); ?></td>
                        <td>
                            <span class="badge badge-info"><?php echo $hospital['requests_count']; ?></span>
                        </td>
                        <td class="action-group">
                            <a href="?action=validate&type=hospital&id=<?php echo $hospital['id']; ?>&tab=<?php echo $active_tab; ?>&t=<?php echo time(); ?>" 
                               class="btn btn-success btn-sm" 
                               onclick="return confirm('Validate this hospital?')">
                                <i class="fas fa-check"></i> Validate
                            </a>
                            <a href="?action=reject&type=hospital&id=<?php echo $hospital['id']; ?>&tab=<?php echo $active_tab; ?>&t=<?php echo time(); ?>" 
                               class="btn btn-warning btn-sm" 
                               onclick="return confirm('Reject this hospital?')">
                                <i class="fas fa-times"></i> Reject
                            </a>
                            <a href="?action=delete&type=hospital&id=<?php echo $hospital['id']; ?>&tab=<?php echo $active_tab; ?>&t=<?php echo time(); ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('Permanently delete this hospital? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pending_hospitals)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2rem; color: var(--gray);">
                            <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                            <p>No pending hospitals</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Pending Donors Table -->
        <?php if ($active_tab === 'donors'): ?>
        <div class="table-responsive">
            <table id="donorsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Blood Group</th>
                        <th>City</th>
                        <th>Registration Date</th>
                        <th>Donations</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_donors as $donor): ?>
                    <tr data-status="pending" data-name="<?php echo strtolower($donor['full_name']); ?>" data-email="<?php echo strtolower($donor['email']); ?>">
                        <td>#<?php echo $donor['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($donor['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($donor['email']); ?></td>
                        <td><?php echo htmlspecialchars($donor['phone'] ?? 'Not provided'); ?></td>
                        <td><span class="blood-badge"><?php echo $donor['blood_group_name'] ?: 'Not specified'; ?></span></td>
                        <td><?php echo htmlspecialchars($donor['city'] ?? 'Not provided'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($donor['created_at'])); ?></td>
                        <td>
                            <span class="badge badge-info"><?php echo $donor['donations_count']; ?></span>
                        </td>
                        <td class="action-group">
                            <a href="?action=validate&type=donor&id=<?php echo $donor['id']; ?>&tab=<?php echo $active_tab; ?>&t=<?php echo time(); ?>" 
                               class="btn btn-success btn-sm" 
                               onclick="return confirm('Validate this donor?')">
                                <i class="fas fa-check"></i> Validate
                            </a>
                            <a href="?action=reject&type=donor&id=<?php echo $donor['id']; ?>&tab=<?php echo $active_tab; ?>&t=<?php echo time(); ?>" 
                               class="btn btn-warning btn-sm" 
                               onclick="return confirm('Reject this donor?')">
                                <i class="fas fa-times"></i> Reject
                            </a>
                            <a href="?action=delete&type=donor&id=<?php echo $donor['id']; ?>&tab=<?php echo $active_tab; ?>&t=<?php echo time(); ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('Permanently delete this donor? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pending_donors)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2rem; color: var(--gray);">
                            <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                            <p>No pending donors</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- All Hospitals Table -->
        <?php if ($active_tab === 'all_hospitals'): ?>
        <div class="table-responsive">
            <table id="allHospitalsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Hospital Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Status</th>
                        <th>Requests</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_hospitals as $hospital): ?>
                    <tr data-status="<?php echo $hospital['is_verified'] ? 'verified' : 'pending'; ?>" 
                        data-name="<?php echo strtolower($hospital['hospital_name']); ?>" 
                        data-email="<?php echo strtolower($hospital['email']); ?>">
                        <td>#<?php echo $hospital['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($hospital['hospital_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($hospital['email']); ?></td>
                        <td><?php echo htmlspecialchars($hospital['phone'] ?? 'Not provided'); ?></td>
                        <td><?php echo htmlspecialchars($hospital['city']); ?></td>
                        <td>
                            <?php if ($hospital['is_verified']): ?>
                                <span class="badge badge-success">Verified</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-info"><?php echo $hospital['requests_count']; ?></span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($hospital['created_at'])); ?></td>
                        <td class="action-group">
                            <?php if (!$hospital['is_verified']): ?>
                                <a href="?action=validate&type=hospital&id=<?php echo $hospital['id']; ?>&tab=<?php echo $active_tab; ?>&t=<?php echo time(); ?>" 
                                   class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Validate
                                </a>
                            <?php else: ?>
                                <a href="?action=reject&type=hospital&id=<?php echo $hospital['id']; ?>&tab=<?php echo $active_tab; ?>&t=<?php echo time(); ?>" 
                                   class="btn btn-warning btn-sm">
                                    <i class="fas fa-times"></i> Reject
                                </a>
                            <?php endif; ?>
                            <a href="?action=delete&type=hospital&id=<?php echo $hospital['id']; ?>&tab=<?php echo $active_tab; ?>&t=<?php echo time(); ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('Permanently delete this hospital?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- All Donors Table -->
        <?php if ($active_tab === 'all_donors'): ?>
        <div class="table-responsive">
            <table id="allDonorsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Blood Group</th>
                        <th>City</th>
                        <th>Status</th>
                        <th>Donations</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_donors as $donor): ?>
                    <tr data-status="<?php echo $donor['is_verified'] ? 'verified' : 'pending'; ?>" 
                        data-name="<?php echo strtolower($donor['full_name']); ?>" 
                        data-email="<?php echo strtolower($donor['email']); ?>">
                        <td>#<?php echo $donor['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($donor['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($donor['email']); ?></td>
                        <td><?php echo htmlspecialchars($donor['phone'] ?? 'Not provided'); ?></td>
                        <td><span class="blood-badge"><?php echo $donor['blood_group_name'] ?: 'Not specified'; ?></span></td>
                        <td><?php echo htmlspecialchars($donor['city'] ?? 'Not provided'); ?></td>
                        <td>
                            <?php if ($donor['is_verified']): ?>
                                <span class="badge badge-success">Verified</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-info"><?php echo $donor['donations_count']; ?></span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($donor['created_at'])); ?></td>
                        <td class="action-group">
                            <?php if (!$donor['is_verified']): ?>
                                <a href="?action=validate&type=donor&id=<?php echo $donor['id']; ?>&tab=<?php echo $active_tab; ?>&t=<?php echo time(); ?>" 
                                   class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Validate
                                </a>
                            <?php else: ?>
                                <a href="?action=reject&type=donor&id=<?php echo $donor['id']; ?>&tab=<?php echo $active_tab; ?>&t=<?php echo time(); ?>" 
                                   class="btn btn-warning btn-sm">
                                    <i class="fas fa-times"></i> Reject
                                </a>
                            <?php endif; ?>
                            <a href="?action=delete&type=donor&id=<?php echo $donor['id']; ?>&tab=<?php echo $active_tab; ?>&t=<?php echo time(); ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('Permanently delete this donor?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Refresh data
        function refreshData() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('t', Date.now());
            window.location.href = currentUrl.toString();
        }

        // Filter table
        function filterTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            
            // Determine active table
            let table;
            if (document.getElementById('hospitalsTable')) table = document.getElementById('hospitalsTable');
            else if (document.getElementById('donorsTable')) table = document.getElementById('donorsTable');
            else if (document.getElementById('allHospitalsTable')) table = document.getElementById('allHospitalsTable');
            else if (document.getElementById('allDonorsTable')) table = document.getElementById('allDonorsTable');
            
            if (!table) return;
            
            const tbody = table.getElementsByTagName('tbody')[0];
            if (!tbody) return;
            
            const rows = tbody.getElementsByTagName('tr');
            
            for (let row of rows) {
                // Skip empty state row
                if (row.cells.length === 1 && row.cells[0].colSpan > 1) continue;
                
                const text = row.textContent.toLowerCase();
                const status = row.dataset.status;
                
                const matchesSearch = text.includes(searchInput);
                const matchesStatus = statusFilter === 'all' || status === statusFilter;
                
                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            }
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            const alert = document.getElementById('messageAlert');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);

        // Highlight new data
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('t')) {
                const rows = document.querySelectorAll('tbody tr');
                rows.forEach((row, index) => {
                    setTimeout(() => {
                        row.classList.add('highlight');
                        setTimeout(() => row.classList.remove('highlight'), 2000);
                    }, index * 100);
                });
            }
        });
    </script>
</body>
</html>