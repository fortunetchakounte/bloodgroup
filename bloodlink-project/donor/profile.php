<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is a donor
if (!isLoggedIn() || getUserRole() !== 'donor') {
    redirect('../public/login.php');
}

$page_title = "My Profile - Donor";
$success_message = '';
$error_message = '';

// Get donor info
$stmt = $pdo->prepare("
    SELECT u.*, bg.name as blood_group_name 
    FROM users u 
    LEFT JOIN blood_groups bg ON u.blood_group_id = bg.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$donor = $stmt->fetch();

if (!$donor) {
    redirect('../public/login.php');
}

// Get blood groups for select
$blood_groups_stmt = $pdo->query("SELECT id, name FROM blood_groups ORDER BY name");
$blood_groups = $blood_groups_stmt->fetchAll();

// Donor statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_donations,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_donations,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_donations
    FROM donations 
    WHERE donor_id = ?
");
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $blood_group_id = (int)($_POST['blood_group_id'] ?? 0);
    
    // Validation
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($city)) $errors[] = "City is required.";
    if ($blood_group_id <= 0) $errors[] = "Please select your blood group.";
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, phone = ?, city = ?, address = ?, blood_group_id = ? 
                WHERE id = ?
            ");
            
            $stmt->execute([
                $full_name,
                $phone,
                $city,
                $address,
                $blood_group_id,
                $_SESSION['user_id']
            ]);
            
            // Update session
            $_SESSION['user_name'] = $full_name;
            
            $success_message = "✅ Profile updated successfully!";
            
            // Reload data
            $stmt = $pdo->prepare("
                SELECT u.*, bg.name as blood_group_name 
                FROM users u 
                LEFT JOIN blood_groups bg ON u.blood_group_id = bg.id 
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $donor = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    } else {
        $error_message = "⚠️ " . implode("<br>", $errors);
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (!password_verify($current_password, $donor['password'])) {
        $errors[] = "Current password is incorrect.";
    }
    
    if (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long.";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }
    
    if ($current_password === $new_password) {
        $errors[] = "New password must be different from current password.";
    }
    
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            $success_message = "✅ Password changed successfully!";
            
        } catch (PDOException $e) {
            $error_message = "Error changing password: " . $e->getMessage();
        }
    } else {
        $error_message = "⚠️ " . implode("<br>", $errors);
    }
}
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #e8f5e9;
            border-color: var(--success);
            color: #2e7d32;
        }

        .alert-danger {
            background: #fee2e2;
            border-color: var(--danger);
            color: #991b1b;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Profile Header */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 2rem;
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
        }

        .avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            box-shadow: 0 10px 20px rgba(230, 57, 70, 0.2);
            border: 4px solid white;
            flex-shrink: 0;
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-dark);
            font-size: 0.95rem;
        }

        .info-item i {
            width: 20px;
            color: var(--primary);
        }

        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
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
            background: #fee;
            color: var(--primary);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            transition: var(--transition);
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-soft);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--primary);
            font-size: 1.3rem;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.3rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: var(--white);
            padding: 0.5rem;
            border-radius: 50px;
            border: 1px solid var(--gray-light);
            overflow-x: auto;
        }

        .tab {
            padding: 0.8rem 1.5rem;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            color: var(--gray);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab:hover {
            background: var(--light);
            color: var(--primary);
        }

        .tab.active {
            background: var(--primary);
            color: white;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            margin-bottom: 2rem;
            position: relative;
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

        .card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card h3 i {
            color: var(--primary);
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.9rem;
        }

        label i {
            color: var(--primary);
            margin-right: 0.3rem;
        }

        input, select, textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
        }

        input:disabled {
            background: var(--light);
            cursor: not-allowed;
        }

        textarea {
            min-height: 80px;
            resize: vertical;
        }

        .small-text {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 0.3rem;
            display: block;
        }

        /* Info Items */
        .info-item-card {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--radius-sm);
            border-left: 3px solid var(--primary);
        }

        .info-label {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 0.3rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--dark);
        }

        /* Password Strength */
        .password-strength {
            height: 5px;
            background: var(--gray-light);
            border-radius: 3px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }

        .requirements {
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.2rem;
        }

        .requirement.valid {
            color: var(--success);
        }

        .requirement.invalid {
            color: var(--danger);
        }

        #passwordMatch {
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
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

        .btn-secondary {
            background: var(--gray-light);
            color: var(--gray-dark);
        }

        .btn-secondary:hover {
            background: var(--gray);
            color: white;
        }

        .btn-block {
            width: 100%;
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
            padding: 1rem 0.5rem;
            color: var(--gray);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--gray-light);
            background: var(--light);
        }

        td {
            padding: 1rem 0.5rem;
            border-bottom: 1px solid var(--gray-light);
            color: var(--dark);
            font-size: 0.9rem;
        }

        tr:hover td {
            background: var(--light);
        }

        /* Delete Account */
        .delete-account {
            background: #fee2e2;
            border: 2px solid #fecaca;
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            margin-top: 2rem;
        }

        .delete-account h3 {
            color: #991b1b;
            margin-bottom: 0.5rem;
        }

        .delete-account p {
            color: #991b1b;
            margin-bottom: 1rem;
        }

        .btn-delete {
            background: #991b1b;
            color: white;
        }

        .btn-delete:hover {
            background: #7f1d1d;
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

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-info h1 {
                justify-content: center;
            }

            .info-item {
                justify-content: center;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-wrap: wrap;
                border-radius: var(--radius);
            }

            .tab {
                flex: 1;
                text-align: center;
                justify-content: center;
            }

            .container {
                padding: 0 1rem;
            }
        }

        @media (max-width: 480px) {
            .profile-info h1 {
                font-size: 1.5rem;
                flex-direction: column;
                gap: 0.5rem;
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
            <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
            <a href="requests.php"><i class="fas fa-tint"></i> Requests</a>
            <a href="history.php"><i class="fas fa-history"></i> History</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Alerts -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="avatar">
                <?php echo strtoupper(substr($donor['full_name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1>
                    <?php echo htmlspecialchars($donor['full_name']); ?>
                    <?php if ($donor['is_verified']): ?>
                        <span class="badge badge-success"><i class="fas fa-check-circle"></i> Verified</span>
                    <?php else: ?>
                        <span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
                    <?php endif; ?>
                </h1>
                
                <div class="info-grid">
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($donor['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($donor['city'] ?? 'Not specified'); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-tint"></i>
                        <span>
                            Blood Type: 
                            <strong><?php echo $donor['blood_group_name'] ?? 'Not specified'; ?></strong>
                        </span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-heartbeat"></i>
                        <span>
                            Status: 
                            <?php if ($donor['can_donate']): ?>
                                <span class="badge badge-success">Eligible</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Not Eligible</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tint"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_donations'] ?? 0; ?></div>
                <div class="stat-label">Total Donations</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['completed_donations'] ?? 0; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-number"><?php echo $stats['scheduled_donations'] ?? 0; ?></div>
                <div class="stat-label">Scheduled</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="stat-number">
                    <?php echo ($stats['completed_donations'] ?? 0) * 3; ?>
                </div>
                <div class="stat-label">Lives Saved</div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="switchTab('info')">
                <i class="fas fa-user-edit"></i> Personal Info
            </div>
            <div class="tab" onclick="switchTab('security')">
                <i class="fas fa-lock"></i> Security
            </div>
            <div class="tab" onclick="switchTab('activity')">
                <i class="fas fa-chart-line"></i> Activity
            </div>
        </div>
        
        <!-- Personal Info Tab -->
        <div id="info-tab" class="tab-content active">
            <div class="card">
                <h3><i class="fas fa-edit"></i> Edit Personal Information</h3>
                
                <form method="POST" action="">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" name="full_name" 
                                   value="<?php echo htmlspecialchars($donor['full_name']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($donor['email']); ?>" 
                                   disabled>
                            <span class="small-text">
                                <i class="fas fa-info-circle"></i> Email cannot be changed for security reasons.
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-tint"></i> Blood Group *</label>
                            <select name="blood_group_id" required>
                                <option value="">Select blood group</option>
                                <?php foreach ($blood_groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"
                                        <?php echo $donor['blood_group_id'] == $group['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($group['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone</label>
                            <input type="tel" name="phone" 
                                   value="<?php echo htmlspecialchars($donor['phone'] ?? ''); ?>"
                                   placeholder="+33 1 23 45 67 89">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-city"></i> City *</label>
                            <input type="text" name="city" 
                                   value="<?php echo htmlspecialchars($donor['city'] ?? ''); ?>" 
                                   required placeholder="Paris">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea name="address" rows="2" 
                                      placeholder="Your full address"><?php echo htmlspecialchars($donor['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
            
            <!-- Account Information -->
            <div class="card">
                <h3><i class="fas fa-info-circle"></i> Account Information</h3>
                <div class="form-grid">
                    <div class="info-item-card">
                        <div class="info-label">Member since</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($donor['created_at'])); ?></div>
                    </div>
                    <div class="info-item-card">
                        <div class="info-label">Last login</div>
                        <div class="info-value">
                            <?php echo isset($_SESSION['last_login']) 
                                ? date('F d, Y H:i', $_SESSION['last_login'])
                                : 'Today'; ?>
                        </div>
                    </div>
                    <div class="info-item-card">
                        <div class="info-label">Account status</div>
                        <div class="info-value">
                            <?php if ($donor['is_verified']): ?>
                                <span class="badge badge-success">Verified</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Pending verification</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item-card">
                        <div class="info-label">Donation eligibility</div>
                        <div class="info-value">
                            <?php if ($donor['can_donate']): ?>
                                <span class="badge badge-success">Eligible</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Not eligible</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Security Tab -->
        <div id="security-tab" class="tab-content">
            <div class="card">
                <h3><i class="fas fa-key"></i> Change Password</h3>
                
                <form method="POST" action="">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Current Password *</label>
                        <input type="password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> New Password *</label>
                        <input type="password" id="new_password" name="new_password" required 
                               onkeyup="checkPasswordStrength(this.value)">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <div class="requirements">
                            <div class="requirement invalid" id="lengthReq">
                                <i class="fas fa-times"></i> At least 6 characters
                            </div>
                            <div class="requirement invalid" id="numberReq">
                                <i class="fas fa-times"></i> Contains a number
                            </div>
                            <div class="requirement invalid" id="specialReq">
                                <i class="fas fa-times"></i> Contains a special character
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm New Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               onkeyup="checkPasswordMatch()">
                        <div id="passwordMatch"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
            
            <!-- Account Security -->
            <div class="card">
                <h3><i class="fas fa-shield-alt"></i> Account Security</h3>
                <div class="form-grid">
                    <div class="info-item-card">
                        <div class="info-label">Active sessions</div>
                        <div class="info-value">1 device</div>
                    </div>
                    <div class="info-item-card">
                        <div class="info-label">2-Factor Authentication</div>
                        <div class="info-value">
                            <span class="badge badge-warning">Not enabled</span>
                            <span class="small-text">
                                <a href="#" style="color: var(--primary);">Enable 2FA</a>
                            </span>
                        </div>
                    </div>
                    <div class="info-item-card">
                        <div class="info-label">Email notifications</div>
                        <div class="info-value">
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" checked> Enabled
                            </label>
                        </div>
                    </div>
                    <div class="info-item-card">
                        <div class="info-label">Last security check</div>
                        <div class="info-value">Today</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Activity Tab -->
        <div id="activity-tab" class="tab-content">
            <div class="card">
                <h3><i class="fas fa-history"></i> Recent Donations</h3>
                
                <?php
                $history_stmt = $pdo->prepare("
                    SELECT d.*, dr.quantity, dr.urgency, h.hospital_name 
                    FROM donations d
                    JOIN donation_requests dr ON d.request_id = dr.id
                    JOIN hospitals h ON dr.hospital_id = h.id
                    WHERE d.donor_id = ?
                    ORDER BY d.created_at DESC
                    LIMIT 10
                ");
                $history_stmt->execute([$_SESSION['user_id']]);
                $donation_history = $history_stmt->fetchAll();
                
                if (empty($donation_history)): ?>
                    <div class="empty-state" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-tint" style="font-size: 4rem; color: var(--gray-light); margin-bottom: 1rem;"></i>
                        <h3 style="color: var(--gray); margin-bottom: 0.5rem;">No donations yet</h3>
                        <p style="color: var(--gray); margin-bottom: 1.5rem;">Start your donor journey and save lives!</p>
                        <a href="requests.php" class="btn btn-primary">
                            <i class="fas fa-tint"></i> View Available Requests
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Hospital</th>
                                    <th>Quantity</th>
                                    <th>Urgency</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donation_history as $donation): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($donation['donation_date'] ?? $donation['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($donation['hospital_name']); ?></td>
                                    <td><?php echo $donation['quantity']; ?> unit(s)</td>
                                    <td>
                                        <?php 
                                        $urgency_labels = [
                                            'low' => 'Low',
                                            'medium' => 'Medium', 
                                            'high' => 'High',
                                            'emergency' => 'Emergency'
                                        ];
                                        $urgency_colors = [
                                            'low' => 'badge-success',
                                            'medium' => 'badge-warning', 
                                            'high' => 'badge-danger',
                                            'emergency' => 'badge-danger'
                                        ];
                                        $urgency_class = $urgency_colors[$donation['urgency']] ?? 'badge-secondary';
                                        ?>
                                        <span class="badge <?php echo $urgency_class; ?>">
                                            <?php echo $urgency_labels[$donation['urgency']] ?? $donation['urgency']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_labels = [
                                            'scheduled' => 'Scheduled',
                                            'completed' => 'Completed',
                                            'cancelled' => 'Cancelled'
                                        ];
                                        $status_colors = [
                                            'scheduled' => 'badge-warning',
                                            'completed' => 'badge-success',
                                            'cancelled' => 'badge-danger'
                                        ];
                                        $status_class = $status_colors[$donation['status']] ?? 'badge-secondary';
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo $status_labels[$donation['status']] ?? $donation['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="text-align: center; margin-top: 1.5rem;">
                        <a href="history.php" class="btn btn-secondary">
                            <i class="fas fa-history"></i> View Full History
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Delete Account -->
        <div class="delete-account">
            <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
            <p>Account deletion is permanent and cannot be undone. All your data will be erased.</p>
            <button class="btn btn-delete" 
                    onclick="if(confirm('Are you sure you want to permanently delete your account? This action cannot be undone.')) { 
                        window.location.href='delete-account.php'; 
                    }">
                <i class="fas fa-trash-alt"></i> Delete My Account
            </button>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Update active tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        // Password strength checker
        function checkPasswordStrength(password) {
            const bar = document.getElementById('passwordStrengthBar');
            const lengthReq = document.getElementById('lengthReq');
            const numberReq = document.getElementById('numberReq');
            const specialReq = document.getElementById('specialReq');
            
            let strength = 0;
            
            // Length check
            if (password.length >= 6) {
                strength += 33;
                lengthReq.className = 'requirement valid';
                lengthReq.innerHTML = '<i class="fas fa-check"></i> At least 6 characters';
            } else {
                lengthReq.className = 'requirement invalid';
                lengthReq.innerHTML = '<i class="fas fa-times"></i> At least 6 characters';
            }
            
            // Number check
            if (/\d/.test(password)) {
                strength += 33;
                numberReq.className = 'requirement valid';
                numberReq.innerHTML = '<i class="fas fa-check"></i> Contains a number';
            } else {
                numberReq.className = 'requirement invalid';
                numberReq.innerHTML = '<i class="fas fa-times"></i> Contains a number';
            }
            
            // Special character check
            if (/[^A-Za-z0-9]/.test(password)) {
                strength += 34;
                specialReq.className = 'requirement valid';
                specialReq.innerHTML = '<i class="fas fa-check"></i> Contains a special character';
            } else {
                specialReq.className = 'requirement invalid';
                specialReq.innerHTML = '<i class="fas fa-times"></i> Contains a special character';
            }
            
            // Update progress bar
            bar.style.width = strength + '%';
            
            if (strength < 33) {
                bar.style.background = '#ef4444';
            } else if (strength < 66) {
                bar.style.background = '#f59e0b';
            } else {
                bar.style.background = '#10b981';
            }
        }
        
        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirm === '') {
                matchDiv.innerHTML = '';
            } else if (password === confirm) {
                matchDiv.innerHTML = '<i class="fas fa-check" style="color: #10b981;"></i> Passwords match';
                matchDiv.style.color = '#10b981';
            } else {
                matchDiv.innerHTML = '<i class="fas fa-times" style="color: #ef4444;"></i> Passwords do not match';
                matchDiv.style.color = '#ef4444';
            }
        }
        
        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>