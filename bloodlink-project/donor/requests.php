<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php'; // Inclure les fonctions de notification

// Vérifier que c'est un donneur vérifié
if (!isLoggedIn() || getUserRole() !== 'donor') {
    redirect('../public/login.php');
}

// Vérifier si le donneur peut donner
$stmt = $pdo->prepare("SELECT can_donate, blood_group_id, city, full_name, email, phone FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$donor = $stmt->fetch();

if (!$donor['can_donate']) {
    die("<div style='padding: 2rem; text-align: center;'>
        <h2>⛔ Account Restricted</h2>
        <p>Your account cannot respond to requests at this time.</p>
        <p>Contact the administrator for more information.</p>
        <a href='dashboard.php'>Back to Dashboard</a>
    </div>");
}

if (!$donor['blood_group_id']) {
    die("<div style='padding: 2rem; text-align: center;'>
        <h2>🩸 Missing Blood Group</h2>
        <p>Please complete your profile with your blood group to see requests.</p>
        <a href='profile.php'>Complete My Profile</a>
    </div>");
}

$page_title = "Available Blood Requests";
$message = '';
$message_type = '';

// Fonction pour notifier l'hôpital d'une nouvelle réponse (si pas déjà dans notifications.php)
if (!function_exists('notifyHospitalNewResponse')) {
    function notifyHospitalNewResponse($pdo, $donation_id) {
        try {
            // Récupérer les détails de la donation
            $stmt = $pdo->prepare("
                SELECT d.*, 
                       dr.hospital_id, 
                       h.email as hospital_email, 
                       h.hospital_name,
                       u.full_name as donor_name,
                       u.phone as donor_phone,
                       u.email as donor_email
                FROM donations d
                JOIN donation_requests dr ON d.request_id = dr.id
                JOIN hospitals h ON dr.hospital_id = h.id
                JOIN users u ON d.donor_id = u.id
                WHERE d.id = ?
            ");
            $stmt->execute([$donation_id]);
            $donation = $stmt->fetch();
            
            if ($donation) {
                // Vérifier si la table notifications existe
                try {
                    $check_table = $pdo->query("SHOW TABLES LIKE 'notifications'");
                    if ($check_table->rowCount() > 0) {
                        // Insérer une notification dans la base de données
                        $notif_stmt = $pdo->prepare("
                            INSERT INTO notifications 
                            (user_id, user_type, title, message, type, reference_id, created_at) 
                            VALUES (?, 'hospital', ?, ?, 'donation_response', ?, NOW())
                        ");
                        
                        $title = "New Donor Response";
                        $message = "Donor " . $donation['donor_name'] . " has responded to your blood request.";
                        
                        $notif_stmt->execute([
                            $donation['hospital_id'],
                            $title,
                            $message,
                            $donation['request_id']
                        ]);
                    }
                } catch (Exception $e) {
                    // La table notifications n'existe pas, on continue sans notification
                }
                
                // Envoyer un email à l'hôpital si la fonction existe
                if (function_exists('sendEmail')) {
                    $subject = "BloodLink - New Donor Response";
                    $body = "A donor has responded to your blood request.\n\n";
                    $body .= "Donor: " . $donation['donor_name'] . "\n";
                    $body .= "Phone: " . ($donation['donor_phone'] ?? 'Not provided') . "\n";
                    $body .= "Email: " . $donation['donor_email'] . "\n\n";
                    $body .= "Please contact them to schedule the donation.";
                    
                    sendEmail($donation['hospital_email'], $subject, $body);
                }
                
                // Log the notification
                error_log("Notification sent to hospital #{$donation['hospital_id']} for donation #{$donation_id}");
            }
        } catch (Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
        }
    }
}

// Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $request_id = (int)$_GET['id'];
    
    // Vérifier que le donneur n'a pas déjà répondu
    $stmt = $pdo->prepare("SELECT id FROM donations WHERE request_id = ? AND donor_id = ?");
    $stmt->execute([$request_id, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        $message = "⚠️ You have already responded to this request.";
        $message_type = 'warning';
    } else {
        try {
            if ($action === 'respond') {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    INSERT INTO donations (donor_id, request_id, status, created_at) 
                    VALUES (?, ?, 'pending', NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $request_id]);
                
                $donation_id = $pdo->lastInsertId();
                
                // Notifier l'hôpital
                notifyHospitalNewResponse($pdo, $donation_id);
                
                $pdo->commit();
                
                $message = "✅ You have successfully responded to this request!";
                $message_type = 'success';
                
                // Rediriger pour éviter la resoumission du formulaire
                header("Location: requests.php?success=1");
                exit();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Vérifier le message de succès après redirection
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "✅ You have successfully responded to this request!";
    $message_type = 'success';
}

// Récupérer les demandes compatibles avec le donneur
$stmt = $pdo->prepare("
    SELECT dr.*, bg.name as blood_group_name, h.hospital_name, h.city as hospital_city,
           h.phone as hospital_phone, h.contact_person,
           (SELECT COUNT(*) FROM donations WHERE request_id = dr.id) as donor_count,
           (SELECT COUNT(*) FROM donations WHERE request_id = dr.id AND donor_id = ?) as has_responded,
           DATEDIFF(NOW(), dr.created_at) as days_ago
    FROM donation_requests dr
    JOIN blood_groups bg ON dr.blood_group_id = bg.id
    JOIN hospitals h ON dr.hospital_id = h.id
    WHERE dr.status = 'pending'
    AND dr.blood_group_id = ?
    AND h.is_verified = 1
    AND (h.city = ? OR ? = 'all')
    ORDER BY 
        CASE dr.urgency 
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'low' THEN 3 
        END,
        dr.created_at DESC
    LIMIT 50
");

// Déterminer le filtre de ville
$city_filter = $_GET['city'] ?? $donor['city'];
$show_all = ($_GET['city'] ?? '') === 'all';

$stmt->execute([
    $_SESSION['user_id'],
    $donor['blood_group_id'],
    $show_all ? 'all' : $city_filter,
    $show_all ? 'all' : $city_filter
]);

$requests = $stmt->fetchAll();

// Statistiques
$stats = [
    'total' => count($requests),
    'urgent' => 0,
    'nearby' => 0,
    'responded' => 0
];

foreach ($requests as $request) {
    if ($request['urgency'] === 'high') $stats['urgent']++;
    if ($request['hospital_city'] === $donor['city']) $stats['nearby']++;
    if ($request['has_responded']) $stats['responded']++;
}

// Récupérer les hôpitaux où le donneur a déjà répondu
$responded_stmt = $pdo->prepare("
    SELECT dr.id, dr.blood_group_id, h.hospital_name, d.status, d.created_at as response_date,
           bg.name as blood_group_name
    FROM donations d
    JOIN donation_requests dr ON d.request_id = dr.id
    JOIN hospitals h ON dr.hospital_id = h.id
    LEFT JOIN blood_groups bg ON dr.blood_group_id = bg.id
    WHERE d.donor_id = ?
    ORDER BY d.created_at DESC
    LIMIT 10
");
$responded_stmt->execute([$_SESSION['user_id']]);
$responded_requests = $responded_stmt->fetchAll();

// Récupérer le nom du groupe sanguin
$bg_stmt = $pdo->prepare("SELECT name FROM blood_groups WHERE id = ?");
$bg_stmt->execute([$donor['blood_group_id']]);
$blood_group = $bg_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - BloodLink</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body {
            background: #f8f9fa;
        }
        
        .navbar {
            background: #dc3545;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .user-info {
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }
        
        .nav-right a {
            color: white;
            text-decoration: none;
            margin-left: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-right a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
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
        
        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .alert-info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .card h1 {
            color: #dc3545;
            margin-bottom: 0.5rem;
        }
        
        .donor-info {
            background: linear-gradient(135deg, #ff6b6b, #dc3545);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .donor-details {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .blood-group-badge {
            font-size: 2rem;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
            border-top: 4px solid #dc3545;
        }
        
        .stat-number {
            font-size: 2rem;
            color: #dc3545;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .filter-btn {
            padding: 0.5rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 20px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            text-decoration: none;
            color: #333;
        }
        
        .filter-btn:hover {
            border-color: #dc3545;
        }
        
        .filter-btn.active {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .request-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            border-left: 4px solid;
            transition: transform 0.3s;
            position: relative;
        }
        
        .request-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .request-card.urgent {
            border-left-color: #dc3545;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        
        .request-card.medium {
            border-left-color: #ffc107;
        }
        
        .request-card.low {
            border-left-color: #28a745;
        }
        
        .request-card.responded {
            opacity: 0.7;
        }
        
        .request-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .request-id {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .request-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .request-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-urgent {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-low {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-location {
            background: #d1ecf1;
            color: #0c5460;
            margin-left: 0.5rem;
        }
        
        .request-body {
            padding: 1.5rem;
        }
        
        .request-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-item {
            margin-bottom: 0.5rem;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .hospital-info {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 5px;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .request-actions {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: #dc3545;
            color: white;
        }
        
        .btn-primary:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            font-weight: 500;
            transition: all 0.3s;
            color: #6c757d;
            white-space: nowrap;
        }
        
        .tab:hover {
            color: #495057;
        }
        
        .tab.active {
            border-bottom-color: #dc3545;
            color: #dc3545;
            font-weight: bold;
        }
        
        .responded-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .map-link {
            color: #007bff;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .map-link:hover {
            text-decoration: underline;
        }
        
        .compatibility-info {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0 8px 8px 0;
        }
        
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">🩸 BloodLink</div>
            <div class="user-info">
                👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?> (Donor)
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php">Dashboard</a>
            <a href="requests.php" style="background: rgba(255,255,255,0.2);">Requests</a>
            <a href="history.php">History</a>
            <a href="profile.php">Profile</a>
            <a href="../public/logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Donor Info -->
        <div class="donor-info">
            <div class="donor-details">
                <div class="blood-group-badge">
                    <?php echo htmlspecialchars($blood_group['name'] ?? 'Unknown'); ?>
                </div>
                <div>
                    <h3 style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
                    <p>📍 <?php echo htmlspecialchars($donor['city']); ?></p>
                </div>
            </div>
            <div>
                <a href="profile.php" class="btn btn-primary">✏️ Edit Profile</a>
            </div>
        </div>
        
        <!-- Compatibility Info -->
        <div class="compatibility-info">
            <h4>💡 Your blood group is compatible with:</h4>
            <p>
                <?php
                $compatible_groups = [];
                switch ($blood_group['name'] ?? '') {
                    case 'O-': $compatible_groups = ['O-', 'O+', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']; break;
                    case 'O+': $compatible_groups = ['O+', 'A+', 'B+', 'AB+']; break;
                    case 'A-': $compatible_groups = ['A-', 'A+', 'AB-', 'AB+']; break;
                    case 'A+': $compatible_groups = ['A+', 'AB+']; break;
                    case 'B-': $compatible_groups = ['B-', 'B+', 'AB-', 'AB+']; break;
                    case 'B+': $compatible_groups = ['B+', 'AB+']; break;
                    case 'AB-': $compatible_groups = ['AB-', 'AB+']; break;
                    case 'AB+': $compatible_groups = ['AB+']; break;
                    default: $compatible_groups = ['All groups'];
                }
                echo implode(', ', $compatible_groups);
                ?>
            </p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Compatible Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['urgent']; ?></div>
                <div class="stat-label">Urgent Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['nearby']; ?></div>
                <div class="stat-label">Near You</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['responded']; ?></div>
                <div class="stat-label">Your Responses</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <strong>Filter by:</strong>
            <a href="?city=<?php echo urlencode($donor['city']); ?>" 
               class="filter-btn <?php echo !$show_all ? 'active' : ''; ?>">
                📍 My City (<?php echo htmlspecialchars($donor['city']); ?>)
            </a>
            <a href="?city=all" 
               class="filter-btn <?php echo $show_all ? 'active' : ''; ?>">
                🌍 All France
            </a>
            <a href="#" class="filter-btn" onclick="filterByUrgency('high'); return false;">
                🔴 Urgent
            </a>
            <a href="#" class="filter-btn" onclick="filterByUrgency('medium'); return false;">
                🟡 Medium
            </a>
            <a href="#" class="filter-btn" onclick="filterByUrgency('low'); return false;">
                🟢 Low
            </a>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="switchTab('available')">
                📋 Available Requests (<?php echo $stats['total']; ?>)
            </div>
            <div class="tab" onclick="switchTab('responded')">
                ✅ My Responses (<?php echo count($responded_requests); ?>)
            </div>
        </div>
        
        <!-- Available Requests Tab -->
        <div id="available-tab" class="tab-content active">
            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🎉</div>
                    <h3>No Requests Available</h3>
                    <p>There are currently no blood requests compatible with your group.</p>
                    <p>Come back later or expand your search to all of France.</p>
                    <a href="?city=all" class="btn btn-primary" style="margin-top: 1rem;">
                        View National Requests
                    </a>
                </div>
            <?php else: ?>
                <div class="requests-grid" id="requestsContainer">
                    <?php foreach ($requests as $request): 
                        $urgency_class = $request['urgency'];
                        $urgency_badge = [
                            'high' => ['class' => 'badge-urgent', 'text' => '🔴 Urgent'],
                            'medium' => ['class' => 'badge-medium', 'text' => '🟡 Medium'],
                            'low' => ['class' => 'badge-low', 'text' => '🟢 Low']
                        ][$request['urgency']];
                        
                        $is_nearby = $request['hospital_city'] === $donor['city'];
                        $has_responded = $request['has_responded'] > 0;
                    ?>
                        <div class="request-card <?php echo $urgency_class; ?> <?php echo $has_responded ? 'responded' : ''; ?>" 
                             data-urgency="<?php echo $request['urgency']; ?>"
                             data-city="<?php echo htmlspecialchars($request['hospital_city']); ?>"
                             data-distance="<?php echo $is_nearby ? 'near' : 'far'; ?>">
                            
                            <?php if ($has_responded): ?>
                                <div class="responded-badge">✅ Already Responded</div>
                            <?php endif; ?>
                            
                            <div class="request-header">
                                <div class="request-id">Request #<?php echo $request['id']; ?></div>
                                <div class="request-title">
                                    <span>Group <?php echo htmlspecialchars($request['blood_group_name']); ?></span>
                                    <span class="request-badge <?php echo $urgency_badge['class']; ?>">
                                        <?php echo $urgency_badge['text']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="request-body">
                                <div class="request-info">
                                    <div class="info-item">
                                        <div class="info-label">Quantity Needed</div>
                                        <div class="info-value"><?php echo $request['quantity']; ?> bag(s)</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Responses</div>
                                        <div class="info-value">
                                            <?php if ($request['donor_count'] == 0): ?>
                                                <span style="color: #dc3545;">No responses</span>
                                            <?php elseif ($request['donor_count'] < $request['quantity']): ?>
                                                <span style="color: #ffc107;">Still needed</span>
                                            <?php else: ?>
                                                <span style="color: #28a745;">Complete</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Hospital</div>
                                        <div class="info-value"><?php echo htmlspecialchars($request['hospital_name']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Location</div>
                                        <div class="info-value">
                                            <?php echo htmlspecialchars($request['hospital_city']); ?>
                                            <?php if ($is_nearby): ?>
                                                <span class="request-badge badge-location">📍 Near you</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($request['notes']): ?>
                                    <div style="margin-top: 1rem; padding: 0.75rem; background: #f8f9fa; border-radius: 5px; font-size: 0.9rem;">
                                        <strong>📝 Hospital Note:</strong><br>
                                        <?php echo htmlspecialchars($request['notes']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="hospital-info">
                                    <strong>🏥 Contact:</strong> <?php echo htmlspecialchars($request['contact_person']); ?><br>
                                    <strong>📞 Phone:</strong> <?php echo htmlspecialchars($request['hospital_phone']); ?><br>
                                    <strong>📅 Posted:</strong> <?php echo abs($request['days_ago']); ?> day(s) ago
                                </div>
                            </div>
                            
                            <div class="request-actions">
                                <?php if ($has_responded): ?>
                                    <button class="btn btn-success" disabled>
                                        ✅ You Responded
                                    </button>
                                    <a href="view-response.php?id=<?php echo $request['id']; ?>" class="btn btn-secondary">
                                        📋 View Response
                                    </a>
                                <?php else: ?>
                                    <a href="?action=respond&id=<?php echo $request['id']; ?>" 
                                       class="btn btn-primary"
                                       onclick="return confirm('Are you sure you want to respond to this request?\n\nThe hospital will contact you to arrange the donation.')">
                                        ❤️ I Can Donate
                                    </a>
                                    <a href="view-request.php?id=<?php echo $request['id']; ?>" class="btn btn-secondary btn-sm">
                                        👁️ More Details
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- My Responses Tab -->
        <div id="responded-tab" class="tab-content" style="display: none;">
            <?php if (empty($responded_requests)): ?>
                <div class="empty-state">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📝</div>
                    <h3>No Responses Yet</h3>
                    <p>You haven't responded to any blood requests yet.</p>
                    <p>Browse available requests and make a difference!</p>
                </div>
            <?php else: ?>
                <div class="card">
                    <h3>✅ My Responses</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                    <th style="padding: 1rem; text-align: left;">Request</th>
                                    <th style="padding: 1rem; text-align: left;">Hospital</th>
                                    <th style="padding: 1rem; text-align: left;">Response Date</th>
                                    <th style="padding: 1rem; text-align: left;">Status</th>
                                    <th style="padding: 1rem; text-align: left;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($responded_requests as $response): ?>
                                <tr style="border-bottom: 1px solid #e9ecef;">
                                    <td style="padding: 1rem;">
                                        #<?php echo $response['id']; ?> 
                                        (Group <?php echo htmlspecialchars($response['blood_group_name'] ?? 'Unknown'); ?>)
                                    </td>
                                    <td style="padding: 1rem;"><?php echo htmlspecialchars($response['hospital_name']); ?></td>
                                    <td style="padding: 1rem;"><?php echo date('d/m/Y', strtotime($response['response_date'])); ?></td>
                                    <td style="padding: 1rem;">
                                        <?php 
                                        $status_color = [
                                            'pending' => 'badge-warning',
                                            'scheduled' => 'badge-info',
                                            'completed' => 'badge-success',
                                            'cancelled' => 'badge-danger'
                                        ];
                                        $status_text = [
                                            'pending' => 'Pending',
                                            'scheduled' => 'Scheduled',
                                            'completed' => 'Completed',
                                            'cancelled' => 'Cancelled'
                                        ];
                                        ?>
                                        <span class="request-badge <?php echo $status_color[$response['status']] ?? 'badge-secondary'; ?>">
                                            <?php echo $status_text[$response['status']] ?? $response['status']; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <a href="view-response.php?id=<?php echo $response['id']; ?>" class="btn btn-primary btn-sm">
                                            📋 Details
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Donor Tips -->
        <div class="card">
            <h3>💡 Donor Tips</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
                <div>
                    <h4>Before Donating</h4>
                    <p>• Get good sleep<br>• Eat light meal<br>• Drink plenty of water</p>
                </div>
                <div>
                    <h4>After Donation</h4>
                    <p>• Rest for 15 minutes<br>• Avoid heavy exercise<br>• Stay hydrated</p>
                </div>
                <div>
                    <h4>Donation Frequency</h4>
                    <p>• Men: 6 times/year max<br>• Women: 4 times/year max<br>• 8 weeks between donations</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').style.display = 'block';
            
            // Update active tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        // Filter by urgency
        function filterByUrgency(urgency) {
            const cards = document.querySelectorAll('.request-card');
            cards.forEach(card => {
                if (card.dataset.urgency === urgency) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Update active filters
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            return false; // Prevent link behavior
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