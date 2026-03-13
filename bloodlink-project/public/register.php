<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = "Join BloodLink - Become a Donor";
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clean data - no pre-filled values
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $blood_group_id = (int)($_POST['blood_group_id'] ?? 0);
    
    // Validation
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (empty($city)) $errors[] = "City is required.";
    if ($blood_group_id <= 0) $errors[] = "Please select your blood group.";
    
    // Check if email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "This email is already registered.";
        }
    }
    
    // If no errors, create the account
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // MODIFICATION: is_verified mis à FALSE (0) au lieu de TRUE
            $stmt = $pdo->prepare("
                INSERT INTO users 
                (email, password, full_name, phone, city, address, blood_group_id, user_type, is_verified, can_donate, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'donor', FALSE, TRUE, NOW())
            ");
            
            $stmt->execute([
                $email,
                $hashed_password,
                $full_name,
                $phone,
                $city,
                $address,
                $blood_group_id
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Initialize XP statistics
            $stmt = $pdo->prepare("
                INSERT INTO user_xp (user_id, level, level_title, current_xp, next_level_xp, total_xp) 
                VALUES (?, 1, 'New Donor', 0, 100, 0)
            ");
            $stmt->execute([$user_id]);
            
            // Initialize donation statistics
            $stmt = $pdo->prepare("
                INSERT INTO donation_stats (user_id, total_donations, consecutive_donations, helped_hospitals) 
                VALUES (?, 0, 0, 0)
            ");
            $stmt->execute([$user_id]);
            
            // Give welcome badge
            $stmt = $pdo->prepare("
                INSERT INTO user_badges (user_id, badge_id, earned_at) 
                VALUES (?, 1, NOW())
            ");
            $stmt->execute([$user_id]);
            
            $success = true;
            
        } catch (PDOException $e) {
            $errors[] = "Registration error. Please try again.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

// Get blood groups for select dropdown
$stmt = $pdo->query("SELECT id, name FROM blood_groups ORDER BY name");
$blood_groups = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #e63946;
            --primary-dark: #c1121f;
            --primary-light: #ff6b6b;
            --secondary-color: #2a9d8f;
            --dark-color: #264653;
            --light-color: #f8f9fa;
            --gray-color: #6c757d;
            --gray-light: #e9ecef;
            --gradient-primary: linear-gradient(135deg, #e63946 0%, #c1121f 100%);
            --gradient-secondary: linear-gradient(135deg, #2a9d8f 0%, #1d7873 100%);
            --shadow-sm: 0 2px 10px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 5px 20px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem;
            color: var(--dark-color);
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        /* Left Side - Info */
        .info-side {
            padding-right: 2rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 2rem;
        }

        .logo-icon {
            background: var(--gradient-primary);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .logo-text {
            font-family: 'Montserrat', sans-serif;
            font-weight: 900;
            font-size: 2rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 3rem;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--dark-color) 0%, var(--primary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            color: var(--gray-color);
            line-height: 1.8;
            margin-bottom: 2.5rem;
        }

        .benefits-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .benefit-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, rgba(230, 57, 70, 0.1) 0%, rgba(42, 157, 143, 0.1) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--primary-color);
            flex-shrink: 0;
        }

        .benefit-content h4 {
            font-size: 1.1rem;
            margin-bottom: 0.3rem;
            color: var(--dark-color);
        }

        .benefit-content p {
            color: var(--gray-color);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .statistics {
            display: flex;
            gap: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-light);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.3rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-color);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Right Side - Form */
        .form-side {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .form-side::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--gradient-primary);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .form-header h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }

        .form-header p {
            color: var(--gray-color);
        }

        .success-message {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 2rem;
            animation: fadeIn 0.6s ease;
        }

        .success-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        /* MODIFICATION: Message d'attente de validation */
        .pending-message {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 2rem;
            animation: fadeIn 0.6s ease;
        }

        .pending-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        .error-message {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            animation: fadeIn 0.6s ease;
        }

        .error-message ul {
            margin-left: 1.5rem;
            margin-top: 0.5rem;
        }

        .error-message li {
            margin-bottom: 0.3rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
            font-size: 0.95rem;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-color);
            font-size: 1rem;
        }

        input, select, textarea {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 3rem;
            border: 2px solid var(--gray-light);
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: var(--transition);
            background: white;
        }

        select {
            padding-left: 1rem;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        textarea {
            padding-left: 1rem;
            min-height: 100px;
            resize: vertical;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-color);
            cursor: pointer;
            font-size: 1rem;
        }

        .form-footer {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-light);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(230, 57, 70, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(230, 57, 70, 0.4);
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--gray-color);
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .back-home {
            text-align: center;
            margin-top: 1rem;
        }

        .back-home a {
            color: var(--gray-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .back-home a:hover {
            color: var(--primary-color);
        }

        .progress-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .progress-bar::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50px;
            right: 50px;
            height: 2px;
            background: var(--gray-light);
            z-index: 1;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: var(--gray-light);
            color: var(--gray-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
            transition: var(--transition);
        }

        .progress-step.active .step-number {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 10px rgba(230, 57, 70, 0.3);
        }

        .progress-step.completed .step-number {
            background: var(--secondary-color);
            color: white;
        }

        .step-label {
            font-size: 0.85rem;
            color: var(--gray-color);
            text-align: center;
        }

        .progress-step.active .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Section navigation */
        .form-section {
            transition: opacity 0.3s ease;
        }

        /* Animations */
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

        /* Responsive */
        @media (max-width: 992px) {
            .container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .info-side {
                padding-right: 0;
                text-align: center;
            }
            
            .welcome-title {
                font-size: 2.5rem;
            }
            
            .benefit-item {
                justify-content: center;
            }
            
            .statistics {
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .form-side {
                padding: 2rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .form-header h2 {
                font-size: 1.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .progress-bar::before {
                left: 30px;
                right: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Left Side - Information -->
        <div class="info-side">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <span class="logo-text">BloodLink</span>
            </div>
            
            <h1 class="welcome-title">Join the Community of Lifesavers</h1>
            <p class="welcome-subtitle">
                Register in 2 minutes and start saving lives. Your donation can make 
                a difference for multiple people. Together, let's build a supportive network.
            </p>
            
            <div class="benefits-list">
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="benefit-content">
                        <h4>Quick Registration</h4>
                        <p>Create your profile in less than 2 minutes</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="benefit-content">
                        <h4>Personalized Alerts</h4>
                        <p>Receive notifications for urgent needs near you</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="benefit-content">
                        <h4>100% Secure</h4>
                        <p>Your medical data is encrypted and protected</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="benefit-content">
                        <h4>Reward System</h4>
                        <p>Earn badges and level up with each donation</p>
                    </div>
                </div>
            </div>
            
            <div class="statistics">
                <div class="stat-item">
                    <div class="stat-number">10,000+</div>
                    <div class="stat-label">Lives Saved</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">5,000+</div>
                    <div class="stat-label">Active Donors</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">98%</div>
                    <div class="stat-label">Satisfaction</div>
                </div>
            </div>
        </div>
        
        <!-- Right Side - Form -->
        <div class="form-side">
            <!-- Progress Bar -->
            <div class="progress-bar">
                <div class="progress-step active" id="step1">
                    <div class="step-number">1</div>
                    <div class="step-label">Personal</div>
                </div>
                <div class="progress-step" id="step2">
                    <div class="step-number">2</div>
                    <div class="step-label">Medical</div>
                </div>
                <div class="progress-step" id="step3">
                    <div class="step-number">3</div>
                    <div class="step-label">Contact</div>
                </div>
                <div class="progress-step" id="step4">
                    <div class="step-number">4</div>
                    <div class="step-label">Security</div>
                </div>
            </div>
            
            <div class="form-header">
                <h2>Create Your Donor Account</h2>
                <p>All fields marked with * are required</p>
            </div>
            
            <?php if ($success): ?>
                <!-- MODIFICATION: Message d'attente de validation au lieu de succès immédiat -->
                <div class="pending-message">
                    <i class="fas fa-clock"></i>
                    <h3 style="margin-bottom: 1rem;">⏳ Registration Received!</h3>
                    <p style="margin-bottom: 1.5rem; font-size: 1.1rem;">
                        Thank you for registering as a donor. Your account is pending verification by an administrator.
                    </p>
                    <p style="margin-bottom: 2rem; opacity: 0.9;">
                        You will receive an email confirmation once your account is verified. This process usually takes 24-48 hours.
                    </p>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <a href="index.php" class="btn" style="background: white; color: var(--primary-color); max-width: 200px;">
                            <i class="fas fa-home"></i> Return Home
                        </a>
                    </div>
                </div>
            <?php elseif (!empty($errors)): ?>
                <div class="error-message">
                    <strong><i class="fas fa-exclamation-triangle"></i> Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST" action="" id="registerForm">
                <!-- Section 1: Personal Information -->
                <div class="form-section" id="section1">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="full_name">Full Name *</label>
                            <div class="input-with-icon">
                                <i class="fas fa-user"></i>
                                <input type="text" id="full_name" name="full_name" 
                                       placeholder="John Doe" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Medical Information -->
                <div class="form-section" id="section2" style="display: none;">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="blood_group_id">Blood Group *</label>
                            <div class="input-with-icon">
                                <i class="fas fa-tint"></i>
                                <select id="blood_group_id" name="blood_group_id" required>
                                    <option value="">Select your blood group</option>
                                    <?php foreach ($blood_groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>">
                                            Blood Type <?php echo htmlspecialchars($group['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Contact Information -->
                <div class="form-section" id="section3" style="display: none;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <div class="input-with-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" 
                                       placeholder="john.doe@email.com" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <div class="input-with-icon">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="phone" name="phone" 
                                       placeholder="+33 1 23 45 67 89">
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="city">City *</label>
                            <div class="input-with-icon">
                                <i class="fas fa-city"></i>
                                <input type="text" id="city" name="city" 
                                       required placeholder="Paris">
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="address">Full Address</label>
                            <div class="input-with-icon">
                                <i class="fas fa-map-marker-alt"></i>
                                <textarea id="address" name="address" rows="2" 
                                          placeholder="123 Avenue of the Republic, 75011 Paris"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Security -->
                <div class="form-section" id="section4" style="display: none;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="password">Password (min. 6 characters) *</label>
                            <div class="input-with-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" required 
                                       placeholder="Minimum 6 characters">
                                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <div class="input-with-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" required 
                                       placeholder="Repeat your password">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="button" id="prevBtn" class="btn" style="background: var(--gray-light); color: var(--dark-color); box-shadow: none; flex: 1; display: none;" onclick="changeStep(-1)">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="button" id="nextBtn" class="btn" style="flex: 1;" onclick="changeStep(1)">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" id="submitBtn" class="btn" style="flex: 1; display: none;">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </div>
                
                <div class="form-footer">
                    <div class="login-link">
                        Already have an account? <a href="login.php">Log in</a>
                    </div>
                    
                    <div class="back-home">
                        <a href="index.php">
                            <i class="fas fa-arrow-left"></i> Back to homepage
                        </a>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 4;

        // Toggle password visibility
        function togglePassword(fieldId) {
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

        // Show/hide sections based on step
        function showStep(step) {
            // Hide all sections
            for (let i = 1; i <= totalSteps; i++) {
                const section = document.getElementById(`section${i}`);
                const stepEl = document.getElementById(`step${i}`);
                if (section) section.style.display = 'none';
                if (stepEl) {
                    stepEl.classList.remove('active', 'completed');
                }
            }
            
            // Show current section
            const currentSection = document.getElementById(`section${step}`);
            if (currentSection) currentSection.style.display = 'block';
            
            // Update step classes
            for (let i = 1; i <= step; i++) {
                const stepEl = document.getElementById(`step${i}`);
                if (stepEl) {
                    if (i < step) {
                        stepEl.classList.add('completed');
                    } else {
                        stepEl.classList.add('active');
                    }
                }
            }
            
            // Update navigation buttons
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');
            
            if (prevBtn) prevBtn.style.display = step === 1 ? 'none' : 'block';
            
            if (step === totalSteps) {
                if (nextBtn) nextBtn.style.display = 'none';
                if (submitBtn) submitBtn.style.display = 'block';
            } else {
                if (nextBtn) nextBtn.style.display = 'block';
                if (submitBtn) submitBtn.style.display = 'none';
            }
        }

        // Change step
        function changeStep(direction) {
            const newStep = currentStep + direction;
            if (newStep >= 1 && newStep <= totalSteps) {
                // Validate current step before moving
                if (direction > 0 && !validateStep(currentStep)) {
                    return;
                }
                currentStep = newStep;
                showStep(currentStep);
            }
        }

        // Validate current step
        function validateStep(step) {
            const section = document.getElementById(`section${step}`);
            if (!section) return true;
            
            const inputs = section.querySelectorAll('input[required], select[required]');
            
            for (let input of inputs) {
                if (!input.value.trim()) {
                    alert('Please fill in all required fields in this section.');
                    input.focus();
                    return false;
                }
            }
            
            // Special validation for step 4 (password)
            if (step === 4) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password.length < 6) {
                    alert('Password must be at least 6 characters long.');
                    return false;
                }
                
                if (password !== confirmPassword) {
                    alert('Passwords do not match.');
                    return false;
                }
            }
            
            return true;
        }

        // Form submission validation
        document.getElementById('registerForm')?.addEventListener('submit', function(e) {
            // Validate all steps
            for (let i = 1; i <= totalSteps; i++) {
                if (!validateStep(i)) {
                    e.preventDefault();
                    currentStep = i;
                    showStep(i);
                    return false;
                }
            }
            return true;
        });

        // Initialize first step
        document.addEventListener('DOMContentLoaded', function() {
            showStep(1);
            
            // Animate form elements
            const formElements = document.querySelectorAll('.form-group');
            formElements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>