<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier que c'est un donneur
if (!isLoggedIn() || getUserRole() !== 'donor') {
    redirect('../public/login.php');
}

// Vérifier qu'un ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('history.php');
}

$donation_id = (int)$_GET['id'];

// Récupérer les détails de la donation
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        dr.quantity,
        dr.urgency,
        dr.needed_by,
        dr.reason,
        dr.doctor_name,
        dr.doctor_phone,
        dr.notes as request_notes,
        h.hospital_name,
        h.city as hospital_city,
        h.address as hospital_address,
        h.phone as hospital_phone,
        h.email as hospital_email,
        h.contact_person,
        bg.name as blood_group_name,
        u.full_name as donor_name,
        u.email as donor_email,
        u.phone as donor_phone
    FROM donations d
    JOIN blood_requests dr ON d.request_id = dr.id
    JOIN hospitals h ON dr.hospital_id = h.id
    JOIN blood_groups bg ON dr.blood_group_id = bg.id
    JOIN users u ON d.donor_id = u.id
    WHERE d.id = ? AND d.donor_id = ?
");
$stmt->execute([$donation_id, $_SESSION['user_id']]);
$donation = $stmt->fetch();

if (!$donation) {
    redirect('history.php');
}

// Timeline pour suivre l'évolution
$timeline = [
    [
        'date' => $donation['created_at'],
        'title' => 'Demande créée',
        'description' => 'La demande de sang a été publiée par l\'hôpital',
        'icon' => 'fa-file-alt',
        'color' => '#3b82f6'
    ],
    [
        'date' => $donation['responded_at'] ?? $donation['created_at'],
        'title' => 'Vous avez répondu',
        'description' => 'Vous avez accepté de participer à ce don',
        'icon' => 'fa-hand-holding-heart',
        'color' => '#10b981'
    ]
];

if ($donation['status'] === 'completed' || $donation['status'] === 'cancelled') {
    $timeline[] = [
        'date' => $donation['updated_at'] ?? $donation['created_at'],
        'title' => $donation['status'] === 'completed' ? 'Don effectué' : 'Don annulé',
        'description' => $donation['status'] === 'completed' 
            ? 'Votre don a été effectué avec succès' 
            : 'Ce don a été annulé',
        'icon' => $donation['status'] === 'completed' ? 'fa-check-circle' : 'fa-times-circle',
        'color' => $donation['status'] === 'completed' ? '#10b981' : '#ef4444'
    ];
}

// Page title
$page_title = "Donation Details - BloodLink";
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

        /* Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .page-header h1 i {
            color: var(--primary);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            background: var(--white);
            color: var(--gray);
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .back-btn:hover {
            background: var(--light);
            color: var(--primary);
            border-color: var(--primary);
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-scheduled {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-pending {
            background: #e3f2fd;
            color: #0d47a1;
        }

        /* Grid Layout */
        .grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            position: relative;
            margin-bottom: 1.5rem;
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

        .card-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .card-header i {
            font-size: 1.2rem;
            color: var(--primary);
        }

        .card-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
        }

        .info-icon {
            width: 36px;
            height: 36px;
            background: var(--primary-soft);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 0.2rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
        }

        .info-value i {
            color: var(--primary);
            margin-right: 0.3rem;
        }

        /* Hospital Card */
        .hospital-card {
            background: linear-gradient(135deg, var(--primary-soft), var(--light));
            border-radius: var(--radius-sm);
            padding: 1.2rem;
            margin-top: 1rem;
        }

        .hospital-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .hospital-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-dark);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .hospital-detail i {
            width: 18px;
            color: var(--primary);
        }

        /* Timeline */
        .timeline {
            position: relative;
            margin-top: 1.5rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 18px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--gray-light);
        }

        .timeline-item {
            position: relative;
            padding-left: 3.5rem;
            margin-bottom: 1.5rem;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-icon {
            position: absolute;
            left: 0;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            z-index: 2;
        }

        .timeline-content {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--radius-sm);
        }

        .timeline-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.3rem;
        }

        .timeline-date {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .timeline-description {
            font-size: 0.85rem;
            color: var(--gray-dark);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

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
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(230, 57, 70, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-light);
            color: var(--gray);
        }

        .btn-outline:hover {
            background: var(--light);
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #0e9f6e;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Urgency badges */
        .urgency-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .urgency-emergency {
            background: #fee2e2;
            color: #991b1b;
        }

        .urgency-high {
            background: #fee2e2;
            color: #991b1b;
        }

        .urgency-medium {
            background: #fff3e0;
            color: #ef6c00;
        }

        .urgency-low {
            background: #e8f5e9;
            color: #2e7d32;
        }

        /* Certificate */
        .certificate {
            background: linear-gradient(135deg, #fef3c7, #fff3cd);
            border: 2px solid #fbbf24;
            border-radius: var(--radius);
            padding: 1.5rem;
            text-align: center;
            margin-top: 1.5rem;
        }

        .certificate i {
            font-size: 3rem;
            color: #f59e0b;
            margin-bottom: 1rem;
        }

        .certificate h3 {
            color: #92400e;
            margin-bottom: 0.5rem;
        }

        .certificate p {
            color: #92400e;
            margin-bottom: 1rem;
        }

        .btn-certificate {
            background: #f59e0b;
            color: white;
        }

        .btn-certificate:hover {
            background: #d97706;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
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
            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="requests.php"><i class="fas fa-tint"></i> Requests</a>
            <a href="history.php" class="active"><i class="fas fa-history"></i> History</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-clipboard-list"></i>
                Donation Details
            </h1>
            <a href="history.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to History
            </a>
        </div>

        <!-- Status Banner -->
        <div class="card" style="padding: 1rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span class="status-badge status-<?php echo $donation['status']; ?>">
                        <i class="fas fa-<?php 
                            echo $donation['status'] === 'completed' ? 'check-circle' : 
                                ($donation['status'] === 'scheduled' ? 'clock' : 'times-circle'); 
                        ?>"></i>
                        <?php 
                            echo $donation['status'] === 'completed' ? 'Completed' : 
                                ($donation['status'] === 'scheduled' ? 'Scheduled' : 'Cancelled'); 
                        ?>
                    </span>
                    <span class="urgency-badge urgency-<?php echo $donation['urgency']; ?>">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php 
                            echo $donation['urgency'] === 'emergency' ? 'Emergency' : 
                                ($donation['urgency'] === 'high' ? 'High Urgency' : 
                                ($donation['urgency'] === 'medium' ? 'Medium Urgency' : 'Low Urgency')); 
                        ?>
                    </span>
                </div>
                <div>
                    <strong>Donation ID:</strong> #<?php echo str_pad($donation['id'], 6, '0', STR_PAD_LEFT); ?>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid-2">
            <!-- Left Column -->
            <div>
                <!-- Donation Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-tint"></i>
                        <h2>Donation Information</h2>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Donation Date</div>
                                <div class="info-value">
                                    <?php echo date('F d, Y', strtotime($donation['donation_date'] ?? $donation['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-tint"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Blood Group</div>
                                <div class="info-value">
                                    <span style="color: var(--primary); font-weight: 700;">
                                        <?php echo htmlspecialchars($donation['blood_group_name']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-cube"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Quantity</div>
                                <div class="info-value">
                                    <?php echo $donation['quantity']; ?> unit(s) (approx. 450ml each)
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Lives Potentially Saved</div>
                                <div class="info-value">
                                    <?php echo $donation['quantity'] * 3; ?> lives
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($donation['reason'])): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-light);">
                        <div class="info-label" style="margin-bottom: 0.5rem;">Medical Reason</div>
                        <div style="background: var(--light); padding: 1rem; border-radius: var(--radius-sm);">
                            <?php echo nl2br(htmlspecialchars($donation['reason'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Hospital Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-hospital"></i>
                        <h2>Hospital Information</h2>
                    </div>
                    
                    <div class="hospital-card">
                        <div class="hospital-name">
                            <i class="fas fa-hospital"></i>
                            <?php echo htmlspecialchars($donation['hospital_name']); ?>
                        </div>
                        
                        <div class="hospital-detail">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($donation['hospital_address'] ?? $donation['hospital_city']); ?></span>
                        </div>
                        
                        <div class="hospital-detail">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($donation['hospital_phone']); ?></span>
                        </div>
                        
                        <div class="hospital-detail">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($donation['hospital_email']); ?></span>
                        </div>
                        
                        <div class="hospital-detail">
                            <i class="fas fa-user"></i>
                            <span>Contact: <?php echo htmlspecialchars($donation['contact_person']); ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($donation['doctor_name'])): ?>
                    <div style="margin-top: 1rem;">
                        <div class="info-label">Doctor in charge</div>
                        <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem;">
                            <i class="fas fa-user-md" style="color: var(--primary);"></i>
                            <div>
                                <div style="font-weight: 600;">Dr. <?php echo htmlspecialchars($donation['doctor_name']); ?></div>
                                <?php if (!empty($donation['doctor_phone'])): ?>
                                    <div style="font-size: 0.85rem; color: var(--gray);">
                                        <?php echo htmlspecialchars($donation['doctor_phone']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Timeline -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clock"></i>
                        <h2>Donation Timeline</h2>
                    </div>
                    
                    <div class="timeline">
                        <?php foreach ($timeline as $index => $event): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon" style="background: <?php echo $event['color']; ?>;">
                                <i class="fas <?php echo $event['icon']; ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title"><?php echo $event['title']; ?></div>
                                <div class="timeline-date">
                                    <i class="far fa-calendar"></i>
                                    <?php echo date('F d, Y H:i', strtotime($event['date'])); ?>
                                </div>
                                <div class="timeline-description">
                                    <?php echo $event['description']; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Patient Information -->
                <?php if (!empty($donation['patient_name'])): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-injured"></i>
                        <h2>Patient Information</h2>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div style="width: 50px; height: 50px; background: var(--primary-soft); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user" style="color: var(--primary);"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($donation['patient_name']); ?></div>
                            <div style="color: var(--gray); font-size: 0.85rem;">Patient</div>
                        </div>
                    </div>
                    
                    <?php if (!empty($donation['request_notes'])): ?>
                    <div style="background: var(--light); padding: 1rem; border-radius: var(--radius-sm);">
                        <div class="info-label" style="margin-bottom: 0.3rem;">Additional notes</div>
                        <p style="color: var(--gray-dark); font-size: 0.9rem;">
                            <?php echo nl2br(htmlspecialchars($donation['request_notes'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Certificate for completed donations -->
                <?php if ($donation['status'] === 'completed'): ?>
                <div class="certificate">
                    <i class="fas fa-award"></i>
                    <h3>Certificate of Donation</h3>
                    <p>Thank you for your heroic act!</p>
                    <button class="btn btn-certificate" onclick="window.print()">
                        <i class="fas fa-download"></i> Download Certificate
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <?php if ($donation['status'] === 'scheduled'): ?>
                <a href="reschedule-donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-outline">
                    <i class="fas fa-calendar-alt"></i>
                    Reschedule
                </a>
                <a href="cancel-donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-danger" 
                   onclick="return confirm('Are you sure you want to cancel this donation?');">
                    <i class="fas fa-times"></i>
                    Cancel Donation
                </a>
            <?php endif; ?>
            
            <a href="history.php" class="btn btn-outline">
                <i class="fas fa-history"></i>
                Back to History
            </a>
            
            <a href="print-donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-outline" target="_blank">
                <i class="fas fa-print"></i>
                Print Details
            </a>
        </div>
    </div>

    <style>
        @media print {
            .navbar, .nav-right, .back-btn, .action-buttons, .btn, .certificate button {
                display: none !important;
            }
            
            body {
                background: white;
                padding: 2rem;
            }
            
            .container {
                margin: 0;
                padding: 0;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
            }
            
            .certificate {
                border: 2px solid #000;
            }
        }
    </style>

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