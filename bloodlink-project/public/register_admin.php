<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = "Admin Registration - BloodLink";
$errors = [];
$success = false;
$max_admins = 2; // Maximum of 2 administrators

// Check how many admins already exist
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'admin'");
$admin_count = $stmt->fetch()['count'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // First check the limit
    if ($admin_count >= $max_admins) {
        $errors[] = "Maximum number of administrators ($max_admins) reached. Cannot create new admin account.";
    } else {
        // Clean data
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($full_name)) $errors[] = "Full name is required.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long.";
        if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
        
        // Check if email already exists
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "This email is already registered.";
            }
        }
        
        // If no errors, create admin account
        if (empty($errors)) {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users 
                    (email, password, full_name, user_type, is_verified, created_at) 
                    VALUES (?, ?, ?, 'admin', TRUE, NOW())
                ");
                
                $stmt->execute([
                    $email,
                    $hashed_password,
                    $full_name
                ]);
                
                $admin_id = $pdo->lastInsertId();
                
                $success = true;
                
            } catch (PDOException $e) {
                $errors[] = "Registration error. Please try again.";
                error_log("Admin registration error: " . $e->getMessage());
            }
        }
    }
}

// Recount admins after possible creation
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'admin'");
$admin_count = $stmt->fetch()['count'];
$remaining_slots = $max_admins - $admin_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --admin: #6c757d;
            --admin-dark: #5a6268;
            --admin-light: #868e96;
            --dark: #264653;
            --gray: #6c757d;
            --light: #f8f9fa;
            --border: #e9ecef;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 500px;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(135deg, var(--admin) 0%, var(--admin-dark) 100%);
        }

        .header {
            background: linear-gradient(135deg, var(--admin) 0%, var(--admin-dark) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--admin);
            font-size: 1.5rem;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .content {
            padding: 30px;
        }

        /* Limit info */
        .limit-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .limit-info i {
            font-size: 1.8rem;
            color: #2196f3;
        }

        .limit-info-text h4 {
            color: #2196f3;
            margin-bottom: 5px;
        }

        .limit-info-text p {
            color: var(--dark);
            font-size: 0.9rem;
        }

        .slots {
            display: inline-block;
            background: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 5px;
        }

        /* Messages */
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .error-message ul {
            margin-left: 20px;
            margin-top: 5px;
        }

        .success-message {
            background: #e8f5e9;
            color: #28a745;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
        }

        .success-message i {
            font-size: 4rem;
            margin-bottom: 15px;
            color: #28a745;
        }

        .success-message h3 {
            margin-bottom: 10px;
            color: #28a745;
        }

        .success-message p {
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .success-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        /* Form */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark);
        }

        .required {
            color: var(--admin);
            margin-left: 2px;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 1rem;
        }

        input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: var(--admin);
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--admin) 0%, var(--admin-dark) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(108, 117, 125, 0.3);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: white;
            color: var(--admin);
            border: 2px solid var(--admin);
        }

        .btn-secondary:hover {
            background: var(--admin);
            color: white;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .footer a {
            color: var(--gray);
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .footer a:hover {
            color: var(--admin);
        }

        .info-text {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 20px;
        }

        .info-text i {
            color: var(--admin);
            margin-right: 5px;
        }

        @media (max-width: 600px) {
            .content {
                padding: 20px;
            }
            
            .success-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <span class="logo-text">BloodLink</span>
                </div>
                <h1>Admin Registration</h1>
                <p>Create an administrator account</p>
            </div>

            <div class="content">
                <!-- Limit info -->
                <div class="limit-info">
                    <i class="fas fa-info-circle"></i>
                    <div class="limit-info-text">
                        <h4>Administrator Limit</h4>
                        <p>Maximum number of administrators: <strong><?php echo $max_admins; ?></strong></p>
                        <div class="slots">
                            <?php if ($remaining_slots > 0): ?>
                                <span style="color: #28a745;">✓ <?php echo $remaining_slots; ?> slot(s) available</span>
                            <?php else: ?>
                                <span style="color: #dc3545;">✗ No slots available</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($success): ?>
                    <!-- Success message -->
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <h3>Admin Account Created!</h3>
                        <p>
                            Your administrator account has been successfully created.<br>
                            You can now log in to the admin area.
                        </p>
                        <div class="success-actions">
                            <a href="admin_login.php" class="btn btn-secondary" style="text-decoration: none;">
                                <i class="fas fa-sign-in-alt"></i> Go to Admin Login
                            </a>
                            <a href="index.php" class="btn" style="text-decoration: none; background: var(--admin);">
                                <i class="fas fa-home"></i> Home
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <div class="error-message">
                            <strong><i class="fas fa-exclamation-triangle"></i> Please correct:</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="adminForm">
                        <div class="form-group">
                            <label>Full name <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="full_name" 
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                                       placeholder="Admin Name" required <?php echo $remaining_slots <= 0 ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="admin@bloodlink.com" required <?php echo $remaining_slots <= 0 ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Password (min. 8 characters) <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" 
                                       placeholder="Strong password" required <?php echo $remaining_slots <= 0 ? 'disabled' : ''; ?>>
                                <button type="button" class="password-toggle" onclick="toggleField('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Confirm password <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm password" required <?php echo $remaining_slots <= 0 ? 'disabled' : ''; ?>>
                                <button type="button" class="password-toggle" onclick="toggleField('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn" <?php echo $remaining_slots <= 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-user-shield"></i> Create Admin Account
                        </button>
                    </form>

                    <div class="info-text">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Administrator accounts have full access to manage donors, hospitals, and blood requests. Only <?php echo $max_admins; ?> admin accounts are allowed.
                    </div>

                    <div class="footer">
                        <a href="admin_login.php">
                            <i class="fas fa-arrow-left"></i> Back to Admin Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleField(fieldId) {
            const field = document.getElementById(fieldId);
            const toggle = field.nextElementSibling;
            const icon = toggle.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Form validation
        document.getElementById('adminForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;

            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return false;
            }

            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match.');
                return false;
            }

            return true;
        });
    </script>
</body>
</html>