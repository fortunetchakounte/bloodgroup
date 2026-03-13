<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Verify it's an admin
if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../public/login.php');
}

$page_title = "Admin Profile";
$success_message = '';
$error_message = '';

// Get admin info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    redirect('../public/login.php');
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validate name
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email already exists
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->execute([$email, $_SESSION['user_id']]);
        if ($check_stmt->fetch()) {
            $errors[] = "This email is already used.";
        }
    }
    
    // Validate password if provided
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        // Verify current password
        if (!password_verify($current_password, $admin['password'])) {
            $errors[] = "Current password is incorrect.";
        }
        
        // Validate new password
        if (empty($new_password)) {
            $errors[] = "New password is required.";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters.";
        }
        
        // Verify confirmation
        if ($new_password !== $confirm_password) {
            $errors[] = "Password confirmation does not match.";
        }
    }
    
    if (empty($errors)) {
        try {
            // Update basic info
            $update_sql = "UPDATE users SET full_name = ?, email = ?";
            $params = [$full_name, $email];
            
            // Update password if provided
            if (!empty($new_password)) {
                $update_sql .= ", password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            $update_sql .= " WHERE id = ?";
            $params[] = $_SESSION['user_id'];
            
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute($params);
            
            // Update session
            $_SESSION['user_name'] = $full_name;
            
            // Log action
            $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, ?, NOW())");
            $log_stmt->execute([$_SESSION['user_id'], "Profile updated"]);
            
            $success_message = "✅ Profile updated successfully!";
            
            // Refresh data
            $stmt->execute([$_SESSION['user_id']]);
            $admin = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error_message = "❌ Error: " . $e->getMessage();
        }
    } else {
        $error_message = "⚠️ " . implode("<br>", $errors);
    }
}

// Admin statistics
$stats = [
    'total_donors' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'donor'")->fetchColumn(),
    'verified_donors' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'donor' AND is_verified = 1")->fetchColumn(),
    'total_hospitals' => $pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn(),
    'verified_hospitals' => $pdo->query("SELECT COUNT(*) FROM hospitals WHERE is_verified = 1")->fetchColumn(),
    'pending_validations' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'donor' AND is_verified = 0")->fetchColumn() +
                           $pdo->query("SELECT COUNT(*) FROM hospitals WHERE is_verified = 0")->fetchColumn(),
    'active_requests' => $pdo->query("SELECT COUNT(*) FROM donation_requests WHERE status = 'pending'")->fetchColumn(),
];

// Get recent logs
$logs_stmt = $pdo->prepare("
    SELECT action, created_at 
    FROM admin_logs 
    WHERE admin_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$logs_stmt->execute([$_SESSION['user_id']]);
$recent_logs = $logs_stmt->fetchAll();
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
            --admin: #343a40;
            --admin-dark: #23272b;
            --admin-light: #6c757d;
            --admin-soft: #e9ecef;
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

        /* Container */
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid;
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

        /* Profile Card */
        .profile-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--admin), var(--admin-light));
            padding: 2rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            border: 4px solid rgba(255,255,255,0.3);
        }

        .profile-title h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .profile-title p {
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .profile-body {
            padding: 2rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--light);
            border-radius: var(--radius-sm);
            padding: 1.2rem;
            text-align: center;
            border: 1px solid var(--gray-light);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--admin);
            line-height: 1.2;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.85rem;
            margin-top: 0.3rem;
        }

        /* Form */
        .form-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1px solid var(--gray-light);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-light);
            color: var(--dark);
        }

        .form-title i {
            color: var(--admin);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-dark);
            font-size: 0.9rem;
        }

        label i {
            color: var(--admin);
            margin-right: 0.5rem;
        }

        input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            transition: var(--transition);
        }

        input:focus {
            outline: none;
            border-color: var(--admin);
        }

        .input-hint {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 0.3rem;
        }

        /* Button */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: var(--admin);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            background: var(--admin-dark);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-light);
            color: var(--gray);
            width: 100%;
        }

        .btn-outline:hover {
            background: var(--light);
            color: var(--admin);
            border-color: var(--admin);
        }

        /* Logs */
        .logs-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1px solid var(--gray-light);
            padding: 1.5rem;
        }

        .logs-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--dark);
            font-weight: 600;
        }

        .logs-title i {
            color: var(--admin);
        }

        .log-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-icon {
            width: 32px;
            height: 32px;
            background: var(--admin-soft);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--admin);
        }

        .log-content {
            flex: 1;
        }

        .log-action {
            font-size: 0.95rem;
            color: var(--dark);
            margin-bottom: 0.2rem;
        }

        .log-time {
            font-size: 0.8rem;
            color: var(--gray);
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
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

            .profile-header {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
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
                <?php echo htmlspecialchars($admin['full_name']); ?>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="validation.php"><i class="fas fa-check-circle"></i> Validations</a>
            
            <a href="requests.php"><i class="fas fa-clipboard-list"></i> Requests</a>
            <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="profile-title">
                    <h1><?php echo htmlspecialchars($admin['full_name']); ?></h1>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($admin['email']); ?></p>
                    <p><i class="fas fa-calendar-alt"></i> Member since <?php echo date('d/m/Y', strtotime($admin['created_at'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_donors']; ?></div>
                <div class="stat-label">Total Donors</div>
                <div style="font-size: 0.8rem; color: var(--gray);"><?php echo $stats['verified_donors']; ?> verified</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_hospitals']; ?></div>
                <div class="stat-label">Total Hospitals</div>
                <div style="font-size: 0.8rem; color: var(--gray);"><?php echo $stats['verified_hospitals']; ?> verified</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending_validations']; ?></div>
                <div class="stat-label">Pending Validations</div>
            </div>
        </div>

        <!-- Edit Profile Form -->
        <div class="form-card">
            <div class="form-title">
                <i class="fas fa-user-edit"></i>
                <h3>Edit Profile Information</h3>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" name="full_name" 
                           value="<?php echo htmlspecialchars($admin['full_name']); ?>" 
                           placeholder="Your full name" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" 
                           value="<?php echo htmlspecialchars($admin['email']); ?>" 
                           placeholder="your@email.com" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Current Password</label>
                    <input type="password" name="current_password" 
                           placeholder="Enter current password">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-key"></i> New Password</label>
                    <input type="password" name="new_password" 
                           placeholder="Minimum 8 characters">
                    <div class="input-hint">Leave empty to keep current password</div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> Confirm New Password</label>
                    <input type="password" name="confirm_password" 
                           placeholder="Confirm new password">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Save Changes
                </button>
            </form>
        </div>

        <!-- Recent Activity -->
        <div class="logs-card">
            <div class="logs-title">
                <i class="fas fa-history"></i>
                <h3>Recent Activity</h3>
            </div>
            
            <?php if (empty($recent_logs)): ?>
                <p style="color: var(--gray); text-align: center; padding: 1rem;">
                    No recent activity
                </p>
            <?php else: ?>
                <?php foreach ($recent_logs as $log): ?>
                <div class="log-item">
                    <div class="log-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="log-content">
                        <div class="log-action"><?php echo htmlspecialchars($log['action']); ?></div>
                        <div class="log-time">
                            <i class="fas fa-clock"></i>
                            <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="action-buttons">
            <a href="validations.php" class="btn btn-primary">
                <i class="fas fa-check-circle"></i>
                Manage Validations
            </a>
            <a href="users.php" class="btn btn-outline">
                <i class="fas fa-users"></i>
                Manage Users
            </a>
        </div>
    </div>

    <script>
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