<?php
require_once __DIR__ . '/../includes/init.php';

$page_title = "Login - BloodLink";
$error = '';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectBasedOnRole();
}

// Process form submission (ONLY for donors and hospitals)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'donor';
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $user = null;
            $role = '';
            $id_field = '';
            $name_field = '';
            
            // Process based on user type (ONLY donor and hospital)
            if ($user_type === 'hospital') {
                // Hospital: hospitals table
                $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $role = 'hospital';
                    $id_field = 'id';
                    $name_field = 'hospital_name';
                } else {
                    $error = "No hospital account found with this email.";
                }
            } else {
                // Donor: users table with user_type = 'donor'
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND user_type = 'donor'");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $role = 'donor';
                    $id_field = 'id';
                    $name_field = 'full_name';
                } else {
                    $error = "No donor account found with this email.";
                }
            }
            
            // If user found, verify password
            if ($user && empty($error)) {
                if (password_verify($password, $user['password'])) {
                    // Check if account is verified
                    if (isset($user['is_verified']) && !$user['is_verified']) {
                        $error = "Your account is not verified yet. Please contact administrator.";
                    } else {
                        // Login successful
                        $_SESSION['user_id'] = $user[$id_field];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_name'] = $user[$name_field];
                        $_SESSION['user_role'] = $role;
                        
                        // For donors, store blood group
                        if ($role === 'donor' && isset($user['blood_group_id'])) {
                            $_SESSION['blood_group_id'] = $user['blood_group_id'];
                        }
                        
                        // Redirect based on role
                        redirectBasedOnRole();
                    }
                } else {
                    $error = "Incorrect password.";
                }
            }
        } catch (PDOException $e) {
            $error = "Technical error. Please try again.";
            // For debugging: error_log($e->getMessage());
        }
    }
}

// Role-based redirect function
function redirectBasedOnRole() {
    $role = $_SESSION['user_role'] ?? 'donor';
    
    switch ($role) {
        case 'admin':
            header('Location: ../admin/dashboard.php');
            exit();
        case 'hospital':
            header('Location: ../hospital/dashboard.php');
            exit();
        case 'donor':
        default:
            header('Location: ../donor/dashboard.php');
            exit();
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #89789a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 950px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            max-height: 700px;
        }

        /* Left side - Welcome */
        .welcome-side {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 35px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
        }

        .welcome-side h1 {
            font-size: 2.2rem;
            margin-bottom: 15px;
            font-weight: 700;
            line-height: 1.2;
        }

        .welcome-side p {
            font-size: 0.95rem;
            line-height: 1.5;
            opacity: 0.9;
            margin-bottom: 25px;
        }

        .features {
            list-style: none;
        }

        .features li {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .features li i {
            width: 36px;
            height: 36px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .features li span {
            font-size: 0.9rem;
        }

        /* Right side - Login Form */
        .form-side {
            padding: 35px;
            overflow-y: auto;
            max-height: 700px;
        }

        /* Custom scrollbar */
        .form-side::-webkit-scrollbar {
            width: 5px;
        }

        .form-side::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .form-side::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo h2 {
            font-size: 1.8rem;
            color: #333;
            font-weight: 700;
        }

        .logo p {
            color: #666;
            margin-top: 4px;
            font-size: 0.9rem;
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-type-selector {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .type-btn {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .type-btn i {
            font-size: 1.1rem;
            color: #666;
        }

        .type-btn span {
            font-size: 0.8rem;
            font-weight: 500;
            color: #666;
        }

        .type-btn:hover {
            border-color: #667eea;
        }

        .type-btn.active {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .type-btn.active i,
        .type-btn.active span {
            color: #667eea;
        }

        .type-btn[data-type="donor"].active {
            border-color: #e63946;
            background: #fee;
        }
        .type-btn[data-type="donor"].active i,
        .type-btn[data-type="donor"].active span {
            color: #e63946;
        }

        .type-btn[data-type="hospital"].active {
            border-color: #28a745;
            background: #e8f5e9;
        }
        .type-btn[data-type="hospital"].active i,
        .type-btn[data-type="hospital"].active span {
            color: #28a745;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #333;
            font-size: 0.85rem;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 0.9rem;
        }

        .input-group input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        /* Admin Section */
        .admin-section {
            margin-top: 20px;
            text-align: center;
            padding-top: 16px;
            border-top: 1px solid #e0e0e0;
        }

        .admin-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 8px;
        }

        .admin-btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 12px;
            background: #343a40;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .admin-btn i {
            font-size: 0.9rem;
        }

        .admin-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .admin-btn.login-btn:hover {
            background: #23272b;
        }

        .admin-btn.register-btn {
            background: #28a745;
        }

        .admin-btn.register-btn:hover {
            background: #218838;
        }

        .admin-note {
            font-size: 0.7rem;
            color: #999;
        }

        .register-links {
            margin-top: 20px;
            text-align: center;
        }

        .register-buttons {
            display: flex;
            gap: 10px;
            margin: 12px 0;
        }

        .register-btn {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .register-btn.donor {
            background: #e63946;
            color: white;
        }

        .register-btn.donor:hover {
            background: #c1121f;
        }

        .register-btn.hospital {
            background: #28a745;
            color: white;
        }

        .register-btn.hospital:hover {
            background: #218838;
        }

        .other-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 12px 0;
            flex-wrap: wrap;
        }

        .other-links a {
            color: #666;
            text-decoration: none;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .other-links a:hover {
            color: #667eea;
        }

        .back-home {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e0e0e0;
        }

        .back-home a {
            color: #666;
            text-decoration: none;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .back-home a:hover {
            color: #667eea;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                max-height: none;
            }
            .welcome-side {
                display: none;
            }
            .register-buttons {
                flex-direction: column;
            }
            .admin-buttons {
                flex-direction: column;
            }
            .form-side {
                max-height: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Left side - Welcome -->
        <div class="welcome-side">
            <h1>Welcome to BloodLink</h1>
            <p>Log in to access your personal space and continue saving lives.</p>
            <ul class="features">
                <li><i class="fas fa-shield-alt"></i> <span>Secure connection</span></li>
                <li><i class="fas fa-tachometer-alt"></i> <span>Personalized dashboard</span></li>
                <li><i class="fas fa-bell"></i> <span>Real-time alerts</span></li>
                <li><i class="fas fa-chart-line"></i> <span>Track your impact</span></li>
            </ul>
        </div>
        
        <!-- Right side - Login Form -->
        <div class="form-side">
            <div class="logo">
                <h2>BloodLink</h2>
                <p>Log in to your account</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <!-- User Type Selector (ONLY donor and hospital) -->
                <div class="user-type-selector">
                    <button type="button" class="type-btn active" data-type="donor">
                        <i class="fas fa-user"></i> 
                        <span>Donor</span>
                    </button>
                    <button type="button" class="type-btn" data-type="hospital">
                        <i class="fas fa-hospital"></i> 
                        <span>Hospital</span>
                    </button>
                </div>

                <input type="hidden" name="user_type" id="userType" value="donor">

                <div class="form-group">
                    <label for="email">Email address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="your@email.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Log in
                </button>

                <div class="other-links">
                    <a href="forgot-password.php">
                        <i class="fas fa-key"></i> Forgot password?
                    </a>
                    <a href="help.php">
                        <i class="fas fa-question-circle"></i> Help
                    </a>
                </div>
            </form>

            <!-- Admin Section -->
            <div class="admin-section">
                <div class="admin-buttons">
                    <a href="admin_login.php" class="admin-btn login-btn">
                        <i class="fas fa-lock"></i>
                        Admin Login
                    </a>
                    <a href="register_admin.php" class="admin-btn register-btn">
                        <i class="fas fa-user-plus"></i>
                        Admin Register
                    </a>
                </div>
                <div class="admin-note">
                    Restricted to system administrators (max 2 accounts)
                </div>
            </div>

            <!-- Registration -->
            <div class="register-links">
                <p>Don't have an account yet?</p>
                <div class="register-buttons">
                    <a href="register.php" class="register-btn donor">
                        <i class="fas fa-user"></i> Donor
                    </a>
                    <a href="register_hospital.php" class="register-btn hospital">
                        <i class="fas fa-hospital"></i> Hospital
                    </a>
                </div>

                <div class="back-home">
                    <a href="index.php">
                        <i class="fas fa-arrow-left"></i> Back to home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle user type selector
        document.querySelectorAll('.type-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('userType').value = this.getAttribute('data-type');
            });
        });

        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.classList.remove('fa-eye');
                toggleBtn.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleBtn.classList.remove('fa-eye-slash');
                toggleBtn.classList.add('fa-eye');
            }
        }

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields.');
                return false;
            }
            return true;
        });

        // Focus on email field
        document.getElementById('email').focus();
    </script>
</body>
</html>