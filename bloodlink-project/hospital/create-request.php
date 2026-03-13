<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Verify it's a verified hospital
if (!isLoggedIn() || getUserRole() !== 'hospital') {
    redirect('../public/login.php');
}

// Check if hospital is verified
$stmt = $pdo->prepare("SELECT is_verified FROM hospitals WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$hospital = $stmt->fetch();

if (!$hospital || !$hospital['is_verified']) {
    die("<div style='padding: 2rem; text-align: center;'>
        <h2>⏳ Account Pending Verification</h2>
        <p>Your hospital account is not yet verified.</p>
        <p>You will be able to create requests once an administrator verifies your account.</p>
        <a href='dashboard.php'>Back to Dashboard</a>
    </div>");
}

$page_title = "New Blood Request";
$success_message = '';
$error_message = '';

// Get blood groups
$blood_groups_stmt = $pdo->query("SELECT id, name FROM blood_groups ORDER BY name");
$blood_groups = $blood_groups_stmt->fetchAll();

// Function to notify compatible donors
function notifyCompatibleDonors($pdo, $request_id, $blood_group_id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.full_name 
        FROM users u 
        WHERE u.user_type = 'donor' 
        AND u.is_verified = TRUE 
        AND u.can_donate = TRUE 
        AND u.blood_group_id = ?
        AND u.city IN (
            SELECT city FROM hospitals WHERE id = (
                SELECT hospital_id FROM donation_requests WHERE id = ?
            )
        )
    ");
    
    $stmt->execute([$blood_group_id, $request_id]);
    $compatible_donors = $stmt->fetchAll();
    
    error_log("Request #$request_id: " . count($compatible_donors) . " compatible donors notified");
    
    return count($compatible_donors);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $blood_group_id = (int)($_POST['blood_group_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $urgency = $_POST['urgency'] ?? 'medium';
    $notes = trim($_POST['notes'] ?? '');
    $needed_by = $_POST['needed_by'] ?? '';
    
    $errors = [];
    
    if ($blood_group_id <= 0) {
        $errors[] = "Please select a blood group.";
    }
    
    if ($quantity < 1 || $quantity > 20) {
        $errors[] = "Quantity must be between 1 and 20 bags.";
    }
    
    if (!in_array($urgency, ['low', 'medium', 'high'])) {
        $errors[] = "Invalid urgency level.";
    }
    
    if (!empty($needed_by)) {
        $needed_date = DateTime::createFromFormat('Y-m-d', $needed_by);
        $today = new DateTime();
        if (!$needed_date || $needed_date < $today) {
            $errors[] = "The needed date must be in the future.";
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO donation_requests 
                (hospital_id, blood_group_id, quantity, urgency, notes, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $blood_group_id,
                $quantity,
                $urgency,
                $notes
            ]);
            
            $request_id = $pdo->lastInsertId();
            $notified_count = notifyCompatibleDonors($pdo, $request_id, $blood_group_id);
            
            $success_message = "✅ Request created successfully! ID: #$request_id<br>";
            $success_message .= "<small>$notified_count compatible donor(s) notified.</small>";
            
            $_POST = [];
            
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        html, body {
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            position: fixed;
        }
        
        body {
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
        }
        
        /* Navbar ultra compact */
        .navbar {
            background: #28a745;
            color: white;
            padding: 0.5rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            height: 60px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .nav-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            font-size: 1.3rem;
            font-weight: bold;
        }
        
        .user-info {
            background: rgba(255,255,255,0.2);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .nav-right a {
            color: white;
            text-decoration: none;
            margin-left: 0.8rem;
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            transition: background 0.3s;
            font-size: 0.85rem;
        }
        
        .nav-right a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        /* Conteneur principal sans scroll */
        .main-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0.5rem;
            height: calc(100vh - 60px);
            overflow: hidden;
        }
        
        .container {
            width: 100%;
            max-width: 1000px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        /* Alertes compactes */
        .alert {
            padding: 0.5rem 0.8rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            border-left: 4px solid;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        
        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        /* Cartes ultra compactes */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            padding: 1rem;
            margin-bottom: 0.5rem;
            flex-shrink: 0;
        }
        
        .card:last-child {
            margin-bottom: 0;
        }
        
        .card h1 {
            color: #28a745;
            font-size: 1.5rem;
            margin-bottom: 0.2rem;
        }
        
        .card p {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        /* Formulaire super compact */
        .form-group {
            margin-bottom: 0.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.2rem;
            color: #495057;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 0.5rem 0.8rem;
            border: 1.5px solid #e9ecef;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #28a745;
            background: white;
        }
        
        textarea {
            resize: none;
            height: 50px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
        }
        
        /* Blood group selector compact */
        .blood-group-select {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.4rem;
        }
        
        .blood-group-option {
            padding: 0.5rem;
            text-align: center;
            border: 1.5px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
            background: #f8f9fa;
            font-size: 0.8rem;
        }
        
        .blood-group-option:hover {
            border-color: #28a745;
            transform: translateY(-1px);
        }
        
        .blood-group-option.selected {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }
        
        /* Quantity selector compact */
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quantity-btn {
            width: 32px;
            height: 32px;
            border: 1.5px solid #e9ecef;
            border-radius: 50%;
            background: white;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .quantity-btn:hover {
            border-color: #28a745;
            background: #28a745;
            color: white;
        }
        
        .quantity-display {
            font-size: 1.2rem;
            font-weight: bold;
            min-width: 40px;
            text-align: center;
            color: #28a745;
        }
        
        /* Urgency selector compact */
        .urgency-selector {
            display: flex;
            gap: 0.4rem;
        }
        
        .urgency-btn {
            flex: 1;
            padding: 0.5rem;
            text-align: center;
            border: 1.5px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
            background: #f8f9fa;
            font-size: 0.8rem;
        }
        
        .urgency-btn:hover {
            transform: translateY(-1px);
        }
        
        .urgency-btn.low.active {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .urgency-btn.medium.active {
            background: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        
        .urgency-btn.high.active {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        /* Info box compact */
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #1976d2;
            padding: 0.5rem 0.8rem;
            margin-bottom: 0.8rem;
            border-radius: 0 6px 6px 0;
            font-size: 0.8rem;
        }
        
        .info-box h4 {
            color: #1976d2;
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        
        .info-box p {
            margin-bottom: 0;
            font-size: 0.8rem;
        }
        
        /* Stats preview compact */
        .stats-preview {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
            margin: 0.5rem 0;
            padding: 0.5rem 0;
            border-top: 1px dashed #dee2e6;
            border-bottom: 1px dashed #dee2e6;
        }
        
        .stat-preview {
            text-align: center;
            padding: 0.3rem;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .stat-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: #6c757d;
        }
        
        /* Buttons compact */
        .button-group {
            display: flex;
            gap: 0.8rem;
            margin-top: 0.5rem;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .btn-primary {
            background: #28a745;
            color: white;
            flex: 2;
        }
        
        .btn-primary:hover {
            background: #218838;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            flex: 1;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
        }
        
        /* How it works ultra compact */
        .how-it-works-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
        }
        
        .how-it-works-grid div {
            text-align: center;
            padding: 0.4rem;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .how-it-works-grid h4 {
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
            color: #28a745;
        }
        
        .how-it-works-grid p {
            font-size: 0.7rem;
            margin-bottom: 0;
            line-height: 1.2;
        }
        
        /* Utilitaires */
        small {
            font-size: 0.7rem !important;
            color: #6c757d !important;
        }
        
        /* Pas de scroll nulle part */
        .no-scroll {
            overflow: hidden;
        }
    </style>
</head>
<body>
    <!-- Navigation ultra compacte -->
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">🏥 BloodLink</div>
            <div class="user-info">
                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php">Dashboard</a>
            <a href="create-request.php" style="background: rgba(255,255,255,0.2);">New</a>
            <a href="my-requests.php">My Req</a>
            <a href="profile.php">Profile</a>
            <a href="../public/logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <!-- Contenu principal SANS SCROLL -->
    <div class="main-wrapper">
        <div class="container">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                    <div style="margin-top: 0.3rem;">
                        <a href="my-requests.php" class="btn btn-primary btn-sm">View</a>
                        <a href="create-request.php" class="btn btn-secondary btn-sm">New</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Formulaire principal -->
            <div class="card">
                <h1>🩸 New Request</h1>
                <p>Fill form to publish a blood request.</p>
                
                <div class="info-box">
                    <h4>💡 Info</h4>
                    <p>• 450ml/bag • Urgent prioritized • Can modify anytime</p>
                </div>
                
                <form method="POST" action="" id="requestForm">
                    <!-- Blood Group -->
                    <div class="form-group">
                        <label class="required">Blood Group</label>
                        <div class="blood-group-select" id="bloodGroupSelector">
                            <?php foreach ($blood_groups as $group): 
                                $group_letter = substr($group['name'], 0, 1);
                            ?>
                                <div class="blood-group-option <?php echo $group_letter; ?>" 
                                     data-id="<?php echo $group['id']; ?>"
                                     onclick="selectBloodGroup(this, <?php echo $group['id']; ?>)">
                                    <?php echo htmlspecialchars($group['name']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="blood_group_id" id="blood_group_id" 
                               value="<?php echo htmlspecialchars($_POST['blood_group_id'] ?? ''); ?>" required>
                    </div>
                    
                    <!-- Quantity and Urgency -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Bags</label>
                            <div class="quantity-selector">
                                <button type="button" class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                                <div class="quantity-display" id="quantityDisplay">1</div>
                                <button type="button" class="quantity-btn" onclick="changeQuantity(1)">+</button>
                            </div>
                            <input type="hidden" name="quantity" id="quantityInput" value="1" required>
                            <small>Max 20 bags</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Urgency</label>
                            <div class="urgency-selector">
                                <div class="urgency-btn low <?php echo ($_POST['urgency'] ?? 'medium') === 'low' ? 'active' : ''; ?>" 
                                     onclick="selectUrgency('low', this)">Low</div>
                                <div class="urgency-btn medium <?php echo ($_POST['urgency'] ?? 'medium') === 'medium' ? 'active' : ''; ?>" 
                                     onclick="selectUrgency('medium', this)">Medium</div>
                                <div class="urgency-btn high <?php echo ($_POST['urgency'] ?? 'medium') === 'high' ? 'active' : ''; ?>" 
                                     onclick="selectUrgency('high', this)">High</div>
                            </div>
                            <input type="hidden" name="urgency" id="urgencyInput" 
                                   value="<?php echo htmlspecialchars($_POST['urgency'] ?? 'medium'); ?>" required>
                        </div>
                    </div>
                    
                    <!-- Needed By Date -->
                    <div class="form-group">
                        <label>Needed By (opt)</label>
                        <input type="date" name="needed_by" class="compact-input"
                               value="<?php echo htmlspecialchars($_POST['needed_by'] ?? ''); ?>"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <!-- Notes -->
                    <div class="form-group">
                        <label>Notes (opt)</label>
                        <textarea name="notes" rows="1" 
                                  placeholder="Additional info..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Stats Preview -->
                    <div class="stats-preview">
                        <div class="stat-preview">
                            <div class="stat-number" id="previewQuantity">1</div>
                            <div class="stat-label">Bags</div>
                        </div>
                        <div class="stat-preview">
                            <div class="stat-number" id="previewMl">450</div>
                            <div class="stat-label">ml</div>
                        </div>
                        <div class="stat-preview">
                            <div class="stat-number" id="previewDonors">0</div>
                            <div class="stat-label">Donors</div>
                        </div>
                        <div class="stat-preview">
                            <div class="stat-number" id="previewLives">3</div>
                            <div class="stat-label">Lives</div>
                        </div>
                    </div>
                    
                    <!-- Buttons -->
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Publish</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
            
            <!-- How It Works compact -->
            <div class="card">
                <div class="how-it-works-grid">
                    <div><h4>1. Publish</h4><p>Request visible</p></div>
                    <div><h4>2. Notify</h4><p>Donors alerted</p></div>
                    <div><h4>3. Respond</h4><p>Donors reply</p></div>
                    <div><h4>4. Confirm</h4><p>Arrange donation</p></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectBloodGroup(element, groupId) {
            document.querySelectorAll('.blood-group-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('blood_group_id').value = groupId;
            updateDonorEstimate();
        }
        
        function selectUrgency(level, element) {
            document.querySelectorAll('.urgency-btn').forEach(btn => btn.classList.remove('active'));
            element.classList.add('active');
            document.getElementById('urgencyInput').value = level;
        }
        
        let currentQuantity = 1;
        
        function changeQuantity(change) {
            let newQuantity = currentQuantity + change;
            if (newQuantity >= 1 && newQuantity <= 20) {
                currentQuantity = newQuantity;
                updateQuantityDisplay();
            }
        }
        
        function updateQuantityDisplay() {
            document.getElementById('quantityDisplay').textContent = currentQuantity;
            document.getElementById('quantityInput').value = currentQuantity;
            document.getElementById('previewQuantity').textContent = currentQuantity;
            document.getElementById('previewMl').textContent = currentQuantity * 450;
            document.getElementById('previewLives').textContent = currentQuantity * 3;
        }
        
        function updateDonorEstimate() {
            const groupId = document.getElementById('blood_group_id').value;
            if (groupId) {
                const estimates = {1:15, 2:8, 3:12, 4:5, 5:6, 6:2, 7:20, 8:10};
                document.getElementById('previewDonors').textContent = estimates[groupId] || 0;
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            updateQuantityDisplay();
            
            const selectedGroupId = document.getElementById('blood_group_id').value;
            if (selectedGroupId) {
                const option = document.querySelector(`.blood-group-option[data-id="${selectedGroupId}"]`);
                if (option) option.classList.add('selected');
                updateDonorEstimate();
            }
            
            document.getElementById('requestForm').addEventListener('submit', function(e) {
                if (!document.getElementById('blood_group_id').value) {
                    e.preventDefault();
                    alert('Please select a blood group.');
                }
            });
        });
    </script>
</body>
</html>