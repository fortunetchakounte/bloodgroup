<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is a hospital
if (!isLoggedIn() || getUserRole() !== 'hospital') {
    redirect('../public/login.php');
}

$page_title = "Hospital Profile - BloodLink";
$success_message = '';
$error_message = '';

// Get hospital info
$stmt = $pdo->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$hospital = $stmt->fetch();

if (!$hospital) {
    redirect('../public/login.php');
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hospital_name = trim($_POST['hospital_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($hospital_name)) $errors[] = "Hospital name is required.";
    if (empty($contact_person)) $errors[] = "Contact person is required.";
    if (empty($phone)) $errors[] = "Phone number is required.";
    if (empty($city)) $errors[] = "City is required.";
    if (empty($address)) $errors[] = "Address is required.";
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE hospitals 
                SET hospital_name = ?, contact_person = ?, phone = ?, city = ?, address = ? 
                WHERE id = ?
            ");
            
            $stmt->execute([
                $hospital_name,
                $contact_person,
                $phone,
                $city,
                $address,
                $_SESSION['user_id']
            ]);
            
            // Update session
            $_SESSION['user_name'] = $hospital_name;
            
            $success_message = "✅ Profile updated successfully!";
            
            // Reload data
            $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $hospital = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
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
            --hospital: #28a745;
            --hospital-dark: #218838;
            --hospital-light: #5cb85c;
            --hospital-soft: #e8f5e9;
            --primary: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
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
            color: var(--hospital);
            font-size: 1.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: var(--light);
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            border: 1px solid var(--gray-light);
        }

        .hospital-name {
            font-weight: 600;
            color: var(--dark);
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .verified {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .pending {
            background: #fff3e0;
            color: #ef6c00;
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
            color: var(--hospital);
        }

        .nav-right a.active {
            background: var(--hospital);
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
            max-width: 1000px;
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
            background: var(--white);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            margin-bottom: 2rem;
            display: flex;
            gap: 2rem;
            align-items: center;
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
            background: linear-gradient(90deg, var(--hospital), var(--hospital-light));
        }

        .avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--hospital), var(--hospital-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.2);
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
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: var(--gray-dark);
            font-size: 0.95rem;
        }

        .info-item i {
            width: 20px;
            color: var(--hospital);
            font-size: 1rem;
        }

        .info-item strong {
            color: var(--dark);
            font-weight: 600;
            margin-right: 0.3rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-warning {
            background: #fff3e0;
            color: #ef6c00;
        }

        /* Form Card */
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
            background: linear-gradient(90deg, var(--hospital), var(--hospital-light));
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .card h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card h3 i {
            color: var(--hospital);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        label i {
            color: var(--hospital);
            font-size: 0.9rem;
        }

        .required {
            color: var(--danger);
            margin-left: 0.2rem;
        }

        input, textarea {
            padding: 0.8rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--hospital);
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        input:disabled {
            background: var(--light);
            cursor: not-allowed;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

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
            background: var(--hospital);
            color: white;
        }

        .btn-primary:hover {
            background: var(--hospital-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--gray-light);
            color: var(--gray);
        }

        .btn-outline:hover {
            border-color: var(--hospital);
            color: var(--hospital);
        }

        .btn-block {
            width: 100%;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        /* Stats mini */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-mini {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--radius-sm);
            text-align: center;
        }

        .stat-mini .number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--hospital);
        }

        .stat-mini .label {
            font-size: 0.8rem;
            color: var(--gray);
        }

        /* Info note */
        .info-note {
            background: #e8f5e9;
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: #2e7d32;
            font-size: 0.9rem;
        }

        .info-note i {
            font-size: 1.2rem;
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
            }

            .profile-info h1 {
                justify-content: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }

            .form-actions {
                flex-direction: column;
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

            .stats-mini {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">
                <i class="fas fa-hospital"></i>
                BloodLink
            </div>
            <div class="user-info">
                <span class="hospital-name"><?php echo htmlspecialchars($hospital['hospital_name']); ?></span>
                <span class="verification-badge <?php echo $hospital['is_verified'] ? 'verified' : 'pending'; ?>">
                    <i class="fas fa-<?php echo $hospital['is_verified'] ? 'check-circle' : 'clock'; ?>"></i>
                    <?php echo $hospital['is_verified'] ? 'Verified' : 'Pending'; ?>
                </span>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="create-request.php"><i class="fas fa-plus-circle"></i> New Request</a>
            <a href="my-requests.php"><i class="fas fa-list"></i> My Requests</a>
            <a href="profile.php" class="active"><i class="fas fa-user-md"></i> Profile</a>
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
                🏥
            </div>
            <div class="profile-info">
                <h1>
                    <?php echo htmlspecialchars($hospital['hospital_name']); ?>
                    <?php if ($hospital['is_verified']): ?>
                        <span class="status-badge badge-success">
                            <i class="fas fa-check-circle"></i> Verified
                        </span>
                    <?php else: ?>
                        <span class="status-badge badge-warning">
                            <i class="fas fa-clock"></i> Pending Verification
                        </span>
                    <?php endif; ?>
                </h1>
                
                <div class="info-grid">
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <span><strong>Email:</strong> <?php echo htmlspecialchars($hospital['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <span><strong>Phone:</strong> <?php echo htmlspecialchars($hospital['phone']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-city"></i>
                        <span><strong>City:</strong> <?php echo htmlspecialchars($hospital['city']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-user"></i>
                        <span><strong>Contact:</strong> <?php echo htmlspecialchars($hospital['contact_person']); ?></span>
                    </div>
                </div>
                
                <div class="info-item" style="margin-top: 0.5rem;">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><strong>Address:</strong> <?php echo htmlspecialchars($hospital['address']); ?></span>
                </div>
            </div>
        </div>

        <!-- Edit Profile Form -->
        <div class="card">
            <h3>
                <i class="fas fa-edit"></i>
                Edit Hospital Information
            </h3>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="hospital_name">
                            <i class="fas fa-hospital"></i>
                            Hospital Name <span class="required">*</span>
                        </label>
                        <input type="text" id="hospital_name" name="hospital_name" 
                               value="<?php echo htmlspecialchars($hospital['hospital_name']); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_person">
                            <i class="fas fa-user"></i>
                            Contact Person <span class="required">*</span>
                        </label>
                        <input type="text" id="contact_person" name="contact_person" 
                               value="<?php echo htmlspecialchars($hospital['contact_person']); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">
                            <i class="fas fa-phone"></i>
                            Phone Number <span class="required">*</span>
                        </label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($hospital['phone']); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="city">
                            <i class="fas fa-city"></i>
                            City <span class="required">*</span>
                        </label>
                        <input type="text" id="city" name="city" 
                               value="<?php echo htmlspecialchars($hospital['city']); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="address">
                            <i class="fas fa-map-marker-alt"></i>
                            Complete Address <span class="required">*</span>
                        </label>
                        <textarea id="address" name="address" rows="3" required><?php echo htmlspecialchars($hospital['address']); ?></textarea>
                    </div>
                </div>
                
                <div class="info-note">
                    <i class="fas fa-info-circle"></i>
                    <span>Your email address cannot be modified. Contact an administrator if you need to change it.</span>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                    <a href="dashboard.php" class="btn btn-outline btn-block">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Quick Stats Card -->
        <div class="card">
            <h3>
                <i class="fas fa-chart-bar"></i>
                Quick Statistics
            </h3>
            
            <?php
            // Get some quick stats
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM blood_requests WHERE hospital_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $total_requests = $stmt->fetch()['total'];

            $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM blood_requests WHERE hospital_id = ? AND status = 'pending'");
            $stmt->execute([$_SESSION['user_id']]);
            $pending_requests = $stmt->fetch()['pending'];

            $stmt = $pdo->prepare("SELECT COUNT(*) as fulfilled FROM blood_requests WHERE hospital_id = ? AND status = 'fulfilled'");
            $stmt->execute([$_SESSION['user_id']]);
            $fulfilled_requests = $stmt->fetch()['fulfilled'];
            ?>
            
            <div class="stats-mini">
                <div class="stat-mini">
                    <div class="number"><?php echo $total_requests; ?></div>
                    <div class="label">Total Requests</div>
                </div>
                <div class="stat-mini">
                    <div class="number"><?php echo $pending_requests; ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-mini">
                    <div class="number"><?php echo $fulfilled_requests; ?></div>
                    <div class="label">Fulfilled</div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 1rem;">
                <a href="my-requests.php" class="btn btn-outline">
                    <i class="fas fa-list"></i> View All Requests
                </a>
            </div>
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

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input[required], textarea[required]');
            let valid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.style.borderColor = 'var(--danger)';
                    valid = false;
                } else {
                    input.style.borderColor = 'var(--gray-light)';
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>