<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = "Hospital Registration - BloodLink";
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clean and validate data
    $hospital_name = trim($_POST['hospital_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    
    // Validation
    if (empty($hospital_name)) $errors[] = "Hospital name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid institutional email is required.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (empty($phone)) $errors[] = "Phone number is required.";
    if (empty($city)) $errors[] = "City is required.";
    if (empty($address)) $errors[] = "Address is required.";
    if (empty($contact_person)) $errors[] = "Contact person name is required.";
    
    // Email domain validation (institutional email)
    if (!empty($email)) {
        $domain = substr(strrchr($email, "@"), 1);
        if (in_array($domain, ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'])) {
            $errors[] = "Please use your institutional email address, not a personal one.";
        }
    }
    
    // Check if email already exists in hospitals table
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM hospitals WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "This email is already registered as a hospital.";
            }
        } catch (PDOException $e) {
            $errors[] = "Verification error. Please try again.";
            error_log("Hospital registration check error: " . $e->getMessage());
        }
    }
    
    // Also check if email exists in users table (donors/admins)
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "This email is already registered as a donor or admin.";
            }
        } catch (PDOException $e) {
            $errors[] = "Verification error. Please try again.";
            error_log("User registration check error: " . $e->getMessage());
        }
    }
    
    // If no errors, create the account
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO hospitals 
                (email, password, hospital_name, phone, city, address, contact_person, is_verified, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            
            $result = $stmt->execute([
                $email,
                $hashed_password,
                $hospital_name,
                $phone,
                $city,
                $address,
                $contact_person
            ]);
            
            if ($result) {
                $hospital_id = $pdo->lastInsertId();
                
                // Initialize hospital statistics if the table exists
                try {
                    // Check if hospital_stats table exists
                    $tableCheck = $pdo->query("SHOW TABLES LIKE 'hospital_stats'");
                    if ($tableCheck->rowCount() > 0) {
                        $stmt = $pdo->prepare("
                            INSERT INTO hospital_stats (hospital_id, total_requests, fulfilled_requests, pending_requests, created_at) 
                            VALUES (?, 0, 0, 0, NOW())
                        ");
                        $stmt->execute([$hospital_id]);
                    }
                } catch (PDOException $e) {
                    // Table doesn't exist, just log the error
                    error_log("Hospital stats table error: " . $e->getMessage());
                }
                
                $success = true;
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
            
        } catch (PDOException $e) {
            $errors[] = "Registration error. Please try again.";
            error_log("Hospital registration error: " . $e->getMessage());
        }
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

        :root {
            --hospital: #28a745;
            --hospital-dark: #218838;
            --hospital-light: #5cb85c;
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
            max-width: 700px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        /* Header */
        .header {
            background: var(--hospital);
            color: white;
            padding: 25px;
            text-align: center;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.1"><path d="M20 20 L80 20 L80 80 L20 80 Z" fill="none" stroke="white" stroke-width="2"/></svg>') repeat;
            opacity: 0.1;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--hospital);
            font-size: 1.2rem;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }

        .header p {
            font-size: 0.9rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        /* Progress Bar */
        .progress {
            display: flex;
            justify-content: space-between;
            padding: 20px 30px;
            background: var(--light);
            border-bottom: 1px solid var(--border);
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: var(--border);
            z-index: 1;
        }

        .step-number {
            width: 30px;
            height: 30px;
            background: white;
            border: 2px solid var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 5px;
            position: relative;
            z-index: 2;
            transition: all 0.3s;
        }

        .step.active .step-number {
            background: var(--hospital);
            border-color: var(--hospital);
            color: white;
        }

        .step.completed .step-number {
            background: var(--hospital);
            border-color: var(--hospital);
            color: white;
        }

        .step-label {
            font-size: 0.7rem;
            color: var(--gray);
            font-weight: 500;
        }

        .step.active .step-label {
            color: var(--hospital);
            font-weight: 600;
        }

        /* Main Content */
        .content {
            padding: 25px;
            max-height: 500px;
            overflow-y: auto;
        }

        /* Info Box */
        .info-box {
            background: #e8f5e9;
            border-left: 4px solid var(--hospital);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
        }

        .info-box i {
            color: var(--hospital);
            font-size: 1.2rem;
        }

        .info-box p {
            color: var(--dark);
            line-height: 1.4;
        }

        /* Messages */
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px 15px;
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
            color: var(--hospital-dark);
            padding: 30px;
            border-radius: 12px;
            text-align: center;
        }

        .success-message i {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .success-message h3 {
            margin-bottom: 10px;
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
        .form-section {
            display: block;
        }

        .form-section.hidden {
            display: none;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .section-title i {
            width: 30px;
            height: 30px;
            background: var(--hospital);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        .section-title h3 {
            font-size: 1.2rem;
            color: var(--dark);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .full-width {
            grid-column: span 2;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--dark);
        }

        .required {
            color: var(--hospital);
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
            font-size: 0.9rem;
        }

        input, textarea {
            width: 100%;
            padding: 10px 10px 10px 35px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }

        textarea {
            padding-left: 12px;
            min-height: 60px;
            resize: vertical;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--hospital);
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
        }

        /* Buttons */
        .nav-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--hospital);
            color: white;
        }

        .btn-primary:hover {
            background: var(--hospital-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--border);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: #d0d7de;
        }

        /* Footer */
        .footer {
            padding: 20px 25px;
            background: var(--light);
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .login-link {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .login-link a {
            color: var(--hospital);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--gray);
            text-decoration: none;
            font-size: 0.85rem;
            margin-top: 10px;
        }

        .back-link:hover {
            color: var(--hospital);
        }

        /* Scrollbar */
        .content::-webkit-scrollbar {
            width: 6px;
        }

        .content::-webkit-scrollbar-track {
            background: var(--border);
        }

        .content::-webkit-scrollbar-thumb {
            background: var(--hospital);
            border-radius: 3px;
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .full-width {
                grid-column: span 1;
            }
            
            .progress {
                padding: 15px;
            }
            
            .step-label {
                font-size: 0.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-hospital"></i>
                </div>
                <span class="logo-text">BloodLink</span>
            </div>
            <h1>Hospital Registration</h1>
            <p>Join our network of healthcare partners</p>
        </div>

        <?php if ($success): ?>
            <!-- Success Message -->
            <div class="content">
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h3>Registration Successful!</h3>
                    <p>
                        Your hospital account has been created and is pending verification.<br>
                        An administrator will review your application within 24-48 hours.
                    </p>
                    <div class="success-actions">
                        <a href="login.php" class="btn btn-primary" style="text-decoration: none;">
                            <i class="fas fa-sign-in-alt"></i> Go to Login
                        </a>
                        <a href="index.php" class="btn btn-secondary" style="text-decoration: none; color: var(--dark);">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Progress Bar -->
            <div class="progress">
                <div class="step active" id="step1">
                    <div class="step-number">1</div>
                    <div class="step-label">Hospital</div>
                </div>
                <div class="step" id="step2">
                    <div class="step-number">2</div>
                    <div class="step-label">Contact</div>
                </div>
                <div class="step" id="step3">
                    <div class="step-number">3</div>
                    <div class="step-label">Security</div>
                </div>
            </div>

            <!-- Content -->
            <div class="content">
                <!-- Info Box -->
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <p>Your registration will be verified by our team. Please ensure all information is accurate.</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <strong><i class="fas fa-exclamation-triangle"></i> Please fix the following errors:</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="hospitalForm">
                    <!-- Section 1: Hospital Information -->
                    <div class="form-section" id="section1">
                        <div class="section-title">
                            <i class="fas fa-hospital"></i>
                            <h3>Hospital Information</h3>
                        </div>

                        <div class="form-grid">
                            <div class="full-width">
                                <label>Hospital Name <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-building"></i>
                                    <input type="text" name="hospital_name" 
                                           value="<?php echo htmlspecialchars($_POST['hospital_name'] ?? ''); ?>"
                                           placeholder="Saint Louis Hospital" required>
                                </div>
                            </div>

                            <div class="full-width">
                                <label>Complete Address <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <textarea name="address" rows="2" required 
                                              placeholder="123 Avenue of the Republic, 75011 Paris"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div>
                                <label>City <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-city"></i>
                                    <input type="text" name="city" 
                                           value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>"
                                           placeholder="Paris" required>
                                </div>
                            </div>

                            <div>
                                <label>Phone <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-phone"></i>
                                    <input type="tel" name="phone" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                           placeholder="+33 1 23 45 67 89" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Contact Person -->
                    <div class="form-section hidden" id="section2">
                        <div class="section-title">
                            <i class="fas fa-user-md"></i>
                            <h3>Contact Person</h3>
                        </div>

                        <div class="form-grid">
                            <div class="full-width">
                                <label>Contact Person Name <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="contact_person" 
                                           value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>"
                                           placeholder="Dr. John Smith" required>
                                </div>
                            </div>

                            <div class="full-width">
                                <label>Institutional Email <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                           placeholder="contact@hospital.com" required>
                                </div>
                                <small style="color: var(--gray); font-size: 0.75rem;">Use your institutional email</small>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Credentials -->
                    <div class="form-section hidden" id="section3">
                        <div class="section-title">
                            <i class="fas fa-lock"></i>
                            <h3>Account Credentials</h3>
                        </div>

                        <div class="form-grid">
                            <div>
                                <label>Password <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="password" name="password" required 
                                           placeholder="Min. 6 characters">
                                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label>Confirm Password <span class="required">*</span></label>
                                <div class="input-group">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" required 
                                           placeholder="Repeat password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="nav-buttons">
                        <button type="button" id="prevBtn" class="btn btn-secondary" style="display: none;" onclick="changeStep(-1)">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" id="nextBtn" class="btn btn-primary" onclick="changeStep(1)">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="submit" id="submitBtn" class="btn btn-primary" style="display: none;">
                            <i class="fas fa-hospital"></i> Register Hospital
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="footer">
                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to homepage
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 3;

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

        // Show/hide sections
        function showStep(step) {
            for (let i = 1; i <= totalSteps; i++) {
                const section = document.getElementById(`section${i}`);
                const stepEl = document.getElementById(`step${i}`);
                
                if (section) {
                    section.classList.add('hidden');
                }
                if (stepEl) {
                    stepEl.classList.remove('active', 'completed');
                }
            }

            const currentSection = document.getElementById(`section${step}`);
            if (currentSection) {
                currentSection.classList.remove('hidden');
            }

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

            document.getElementById('prevBtn').style.display = step === 1 ? 'none' : 'block';
            document.getElementById('nextBtn').style.display = step === totalSteps ? 'none' : 'block';
            document.getElementById('submitBtn').style.display = step === totalSteps ? 'block' : 'none';
        }

        // Change step
        function changeStep(direction) {
            const newStep = currentStep + direction;
            if (newStep >= 1 && newStep <= totalSteps) {
                if (direction > 0 && !validateStep(currentStep)) {
                    return;
                }
                currentStep = newStep;
                showStep(currentStep);
            }
        }

        // Validate step
        function validateStep(step) {
            const section = document.getElementById(`section${step}`);
            const inputs = section.querySelectorAll('input[required]');

            for (let input of inputs) {
                if (!input.value.trim()) {
                    alert('Please fill in all required fields.');
                    input.focus();
                    return false;
                }
            }

            if (step === 3) {
                const password = document.getElementById('password').value;
                const confirm = document.getElementById('confirm_password').value;

                if (password.length < 6) {
                    alert('Password must be at least 6 characters.');
                    return false;
                }

                if (password !== confirm) {
                    alert('Passwords do not match.');
                    return false;
                }
            }

            return true;
        }

        // Form validation
        document.getElementById('hospitalForm').addEventListener('submit', function(e) {
            // Validate all steps
            for (let i = 1; i <= totalSteps; i++) {
                if (!validateStep(i)) {
                    e.preventDefault();
                    currentStep = i;
                    showStep(i);
                    return false;
                }
            }

            // Check for personal email domains
            const email = document.querySelector('input[name="email"]').value;
            const domain = email.split('@')[1];
            const personalDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];

            if (personalDomains.includes(domain)) {
                e.preventDefault();
                alert('Please use your institutional email address.');
                return false;
            }

            return true;
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            showStep(1);
        });
    </script>
</body>
</html>