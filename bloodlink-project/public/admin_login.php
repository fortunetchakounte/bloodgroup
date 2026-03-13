<?php
require_once __DIR__ . '/../includes/init.php';

// If already logged in as admin, redirect to admin dashboard
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: ../admin/dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            // Search for user by email in users table
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Check if it's an admin (user_type column)
                    if (isset($user['user_type']) && $user['user_type'] === 'admin') {
                        // Login successful
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_role'] = 'admin';

                        // Redirect to admin dashboard
                        header('Location: ../admin/dashboard.php');
                        exit();
                    } else {
                        $error = "This account is not an administrator. (Type found: " . ($user['user_type'] ?? 'undefined') . ")";
                    }
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "No account found with this email.";
            }
        } catch (PDOException $e) {
            error_log("Admin login error: " . $e->getMessage());
            $error = "Technical error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - BloodLink</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --primary-light: #8b9eff;
            --secondary: #764ba2;
            --success: #48bb78;
            --danger: #f56565;
            --dark: #2d3748;
            --gray-dark: #4a5568;
            --gray: #718096;
            --gray-light: #e2e8f0;
            --light: #f7fafc;
            --white: #ffffff;
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            top: -10%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 20s infinite;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: -10%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 25s infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(5%, 5%) rotate(5deg); }
            50% { transform: translate(-5%, 10%) rotate(-5deg); }
            75% { transform: translate(-10%, -5%) rotate(3deg); }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            max-width: 420px;
            width: 100%;
            padding: 40px;
            position: relative;
            z-index: 10;
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 2rem;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            transform: rotate(-5deg);
            transition: var(--transition);
        }

        .logo-icon:hover {
            transform: rotate(0deg) scale(1.05);
        }

        .logo h2 {
            font-size: 1.8rem;
            color: var(--dark);
            font-weight: 700;
            margin-bottom: 5px;
        }

        .logo p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .info-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-left: 4px solid var(--primary);
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            color: var(--dark);
            animation: fadeIn 0.8s ease;
        }

        .info-box i {
            color: var(--primary);
            font-size: 1.2rem;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .error-message {
            background: #fff5f5;
            color: var(--danger);
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid var(--danger);
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .error-message i {
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 20px;
            animation: fadeIn 0.8s ease;
            animation-fill-mode: both;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.9rem;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 1rem;
            transition: var(--transition);
            z-index: 1;
        }

        .input-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid var(--gray-light);
            border-radius: 14px;
            font-size: 0.95rem;
            transition: var(--transition);
            background: var(--white);
            color: var(--dark);
            font-family: 'Inter', sans-serif;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .input-group input:focus + i {
            color: var(--primary);
        }

        .input-group input::placeholder {
            color: var(--gray-light);
            font-weight: 300;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 1rem;
            transition: var(--transition);
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease 0.3s both;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
            animation: fadeIn 0.8s ease 0.4s both;
        }

        .back-link a {
            color: var(--gray);
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-link a:hover {
            color: var(--primary);
            transform: translateX(-3px);
        }

        .back-link a i {
            font-size: 0.8rem;
            transition: var(--transition);
        }

        .back-link a:hover i {
            transform: translateX(-3px);
        }

        /* Decorative elements */
        .decor-1 {
            position: absolute;
            top: 10%;
            right: 5%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            z-index: 1;
        }

        .decor-2 {
            position: absolute;
            bottom: 10%;
            left: 5%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 40px;
            transform: rotate(45deg);
            z-index: 1;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            .logo h2 {
                font-size: 1.6rem;
            }

            .logo-icon {
                width: 60px;
                height: 60px;
                font-size: 1.6rem;
            }

            .btn-login {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Decorative elements -->
    <div class="decor-1"></div>
    <div class="decor-2"></div>

    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>Admin Portal</h2>
            <p>BloodLink Administration</p>
        </div>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <span>Use your administrator credentials to access the dashboard.</span>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email address</label>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" 
                           placeholder="admin@bloodlink.com" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" 
                           placeholder="••••••••" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Sign in
            </button>
        </form>

        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to general login
            </a>
        </div>
    </div>

    <script>
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

        // Add floating effect to decorative elements
        document.addEventListener('mousemove', function(e) {
            const decor1 = document.querySelector('.decor-1');
            const decor2 = document.querySelector('.decor-2');
            
            if (decor1 && decor2) {
                const x = e.clientX / window.innerWidth;
                const y = e.clientY / window.innerHeight;
                
                decor1.style.transform = `translate(${x * 20}px, ${y * 20}px)`;
                decor2.style.transform = `translate(${-x * 20}px, ${-y * 20}px) rotate(45deg)`;
            }
        });

        // Focus on email input
        document.getElementById('email').focus();
    </script>
</body>
</html>