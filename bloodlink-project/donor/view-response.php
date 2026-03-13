<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || getUserRole() !== 'donor') {
    redirect('../public/login.php');
}

$response_id = (int)($_GET['id'] ?? 0);

// Get response with verification
$stmt = $pdo->prepare("
    SELECT d.*, dr.*, bg.name as blood_group_name, h.hospital_name, 
           h.phone as hospital_phone, h.contact_person, h.address as hospital_address,
           h.city as hospital_city, h.email as hospital_email,
           u.full_name as donor_name, u.phone as donor_phone, u.email as donor_email
    FROM donations d
    JOIN blood_requests dr ON d.request_id = dr.id
    JOIN blood_groups bg ON dr.blood_group_id = bg.id
    JOIN hospitals h ON dr.hospital_id = h.id
    JOIN users u ON d.donor_id = u.id
    WHERE d.id = ? AND d.donor_id = ?
");
$stmt->execute([$response_id, $_SESSION['user_id']]);
$response = $stmt->fetch();

if (!$response) {
    die("
    <div style='display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f3f4f6;'>
        <div style='text-align: center; padding: 3rem; background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-width: 500px;'>
            <i class='fas fa-search' style='font-size: 4rem; color: #e63946; margin-bottom: 1rem;'></i>
            <h2 style='color: #1f2937; margin-bottom: 1rem;'>Response Not Found</h2>
            <p style='color: #6b7280; margin-bottom: 2rem;'>This response doesn't exist or you don't have access to it.</p>
            <a href='requests.php' style='display: inline-block; padding: 0.8rem 1.5rem; background: #e63946; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s;'>Back to Requests</a>
        </div>
    </div>
    ");
}

$page_title = "Response Details #" . $response['id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - BloodLink</title>
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

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
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

        .card-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
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

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-scheduled {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .urgency-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .urgency-high, .urgency-emergency {
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

        /* Notes */
        .notes-box {
            background: var(--light);
            padding: 1.2rem;
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--primary);
            margin-top: 1rem;
        }

        .notes-box p {
            color: var(--gray-dark);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Info boxes */
        .info-box {
            padding: 1.2rem;
            border-radius: var(--radius-sm);
            margin-top: 1rem;
        }

        .info-box.scheduled {
            background: #fff3e0;
        }

        .info-box.completed {
            background: #e8f5e9;
        }

        .info-box h4 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.8rem;
        }

        .info-box ul {
            list-style: none;
            margin-top: 0.8rem;
        }

        .info-box li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: var(--gray-dark);
            font-size: 0.9rem;
        }

        .info-box li i {
            color: var(--primary);
            font-size: 0.8rem;
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

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-block {
            width: 100%;
        }

        /* Next steps */
        .next-steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .step {
            text-align: center;
            padding: 1rem;
            background: var(--white);
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-light);
        }

        .step-number {
            width: 30px;
            height: 30px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
        }

        .step p {
            font-size: 0.85rem;
            color: var(--gray);
        }

        /* Timeline */
        .timeline {
            margin: 1.5rem 0;
        }

        .timeline-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .timeline-item:last-child {
            border-bottom: none;
        }

        .timeline-icon {
            width: 36px;
            height: 36px;
            background: var(--primary-soft);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.2rem;
        }

        .timeline-date {
            font-size: 0.8rem;
            color: var(--gray);
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

            .info-grid {
                grid-template-columns: 1fr;
            }

            .next-steps {
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

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
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
            <a href="requests.php"><i class="fas fa-tint"></i> Requests</a>
            <a href="history.php"><i class="fas fa-history"></i> History</a>
            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-clipboard-list"></i>
                Response Details #<?php echo $response['id']; ?>
            </h1>
            <a href="requests.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Requests
            </a>
        </div>

        <!-- Status Banner -->
        <div class="card" style="padding: 1rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <span class="status-badge status-<?php echo $response['status']; ?>">
                        <i class="fas fa-<?php 
                            echo $response['status'] === 'completed' ? 'check-circle' : 
                                ($response['status'] === 'scheduled' ? 'clock' : 'times-circle'); 
                        ?>"></i>
                        <?php 
                            echo $response['status'] === 'completed' ? 'Completed' : 
                                ($response['status'] === 'scheduled' ? 'Scheduled' : 'Cancelled'); 
                        ?>
                    </span>
                    <span class="urgency-badge urgency-<?php echo $response['urgency']; ?>">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php 
                            echo $response['urgency'] === 'emergency' ? 'Emergency' : 
                                ($response['urgency'] === 'high' ? 'High Urgency' : 
                                ($response['urgency'] === 'medium' ? 'Medium Urgency' : 'Low Urgency')); 
                        ?>
                    </span>
                    <span style="color: var(--gray);">
                        <i class="far fa-calendar"></i>
                        <?php echo date('F d, Y', strtotime($response['created_at'])); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
            <!-- Left Column -->
            <div>
                <!-- Request Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-file-medical"></i>
                        <h2>Request Information</h2>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-tint"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Blood Group</div>
                                <div class="info-value">
                                    <span style="color: var(--primary); font-weight: 700;">
                                        <?php echo htmlspecialchars($response['blood_group_name']); ?>
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
                                    <?php echo $response['quantity']; ?> unit(s)
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($response['notes'])): ?>
                    <div class="notes-box">
                        <i class="fas fa-sticky-note" style="color: var(--primary); margin-right: 0.5rem;"></i>
                        <strong>Hospital Notes:</strong>
                        <p style="margin-top: 0.5rem;"><?php echo nl2br(htmlspecialchars($response['notes'])); ?></p>
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
                            <?php echo htmlspecialchars($response['hospital_name']); ?>
                        </div>

                        <?php if (!empty($response['hospital_address'])): ?>
                        <div class="hospital-detail">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($response['hospital_address']); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($response['hospital_city'])): ?>
                        <div class="hospital-detail">
                            <i class="fas fa-city"></i>
                            <span><?php echo htmlspecialchars($response['hospital_city']); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($response['hospital_phone'])): ?>
                        <div class="hospital-detail">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($response['hospital_phone']); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($response['hospital_email'])): ?>
                        <div class="hospital-detail">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($response['hospital_email']); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($response['contact_person'])): ?>
                        <div class="hospital-detail">
                            <i class="fas fa-user"></i>
                            <span>Contact: <?php echo htmlspecialchars($response['contact_person']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clock"></i>
                        <h2>Timeline</h2>
                    </div>

                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">Request Created</div>
                                <div class="timeline-date">
                                    <?php echo date('F d, Y H:i', strtotime($response['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-hand-holding-heart"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">You Responded</div>
                                <div class="timeline-date">
                                    <?php echo date('F d, Y H:i', strtotime($response['responded_at'] ?? $response['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($response['status'] === 'completed' || $response['status'] === 'cancelled'): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon" style="background: <?php echo $response['status'] === 'completed' ? '#e8f5e9' : '#fee2e2'; ?>;">
                                <i class="fas fa-<?php echo $response['status'] === 'completed' ? 'check-circle' : 'times-circle'; ?>" 
                                   style="color: <?php echo $response['status'] === 'completed' ? '#2e7d32' : '#991b1b'; ?>;"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">
                                    <?php echo $response['status'] === 'completed' ? 'Donation Completed' : 'Donation Cancelled'; ?>
                                </div>
                                <div class="timeline-date">
                                    <?php echo date('F d, Y H:i', strtotime($response['updated_at'] ?? $response['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Your Response -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user"></i>
                        <h2>Your Response</h2>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Donor Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($response['donor_name']); ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($response['donor_phone'] ?? 'Not provided'); ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($response['donor_email']); ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Response Date</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($response['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($response['donation_date'])): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--light); border-radius: var(--radius-sm);">
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--primary);">
                            <i class="fas fa-calendar-check"></i>
                            <strong>Scheduled Donation Date:</strong>
                        </div>
                        <p style="margin-top: 0.5rem; font-size: 1.1rem; font-weight: 600;">
                            <?php echo date('F d, Y', strtotime($response['donation_date'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Status Specific Information -->
                <?php if ($response['status'] === 'scheduled'): ?>
                <div class="card" style="background: #fff3e0;">
                    <div class="card-header">
                        <i class="fas fa-clock" style="color: #ef6c00;"></i>
                        <h2 style="color: #ef6c00;">Next Steps</h2>
                    </div>

                    <div class="next-steps">
                        <div class="step">
                            <div class="step-number">1</div>
                            <p>Hospital will contact you to confirm the appointment</p>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <p>Prepare for donation (eat well, sleep well)</p>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <p>Bring ID on donation day</p>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button class="btn btn-warning" onclick="alert('Reschedule feature coming soon!')">
                            <i class="fas fa-calendar-alt"></i>
                            Reschedule
                        </button>
                        <button class="btn btn-danger" 
                                onclick="if(confirm('Are you sure you want to cancel your response?')) { window.location.href='cancel-response.php?id=<?php echo $response_id; ?>'; }">
                            <i class="fas fa-times"></i>
                            Cancel Response
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($response['status'] === 'completed'): ?>
                <div class="card" style="background: #e8f5e9;">
                    <div class="card-header">
                        <i class="fas fa-check-circle" style="color: #2e7d32;"></i>
                        <h2 style="color: #2e7d32;">Donation Completed</h2>
                    </div>

                    <div style="text-align: center; padding: 1rem;">
                        <i class="fas fa-heart" style="font-size: 4rem; color: #2e7d32; margin-bottom: 1rem;"></i>
                        <p style="font-size: 1.1rem; margin-bottom: 1rem;">Thank you for your donation!</p>
                        <p style="color: var(--gray-dark);">You have potentially saved up to <strong>3 lives</strong>.</p>
                        
                        <?php if (!empty($response['donation_date'])): ?>
                        <div style="margin-top: 1rem; padding: 1rem; background: rgba(255,255,255,0.5); border-radius: var(--radius-sm);">
                            <p><strong>Next donation eligible:</strong></p>
                            <p><?php 
                                $next_date = date('F d, Y', strtotime($response['donation_date'] . ' + 56 days'));
                                echo $next_date;
                            ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="action-buttons">
                        <button class="btn btn-success" onclick="window.print()">
                            <i class="fas fa-download"></i>
                            Download Certificate
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($response['status'] === 'cancelled'): ?>
                <div class="card" style="background: #fee2e2;">
                    <div class="card-header">
                        <i class="fas fa-times-circle" style="color: #991b1b;"></i>
                        <h2 style="color: #991b1b;">Donation Cancelled</h2>
                    </div>

                    <div style="text-align: center; padding: 1rem;">
                        <i class="fas fa-frown" style="font-size: 4rem; color: #991b1b; margin-bottom: 1rem;"></i>
                        <p>This donation has been cancelled.</p>
                        <p style="color: var(--gray-dark); margin-top: 1rem;">You can respond to other blood requests.</p>
                    </div>

                    <div class="action-buttons">
                        <a href="requests.php" class="btn btn-primary btn-block">
                            <i class="fas fa-tint"></i>
                            View Other Requests
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Contact Hospital -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-headset"></i>
                        <h2>Need Help?</h2>
                    </div>

                    <p style="margin-bottom: 1rem; color: var(--gray);">
                        If you have any questions, don't hesitate to contact the hospital directly.
                    </p>

                    <?php if (!empty($response['hospital_phone'])): ?>
                    <a href="tel:<?php echo $response['hospital_phone']; ?>" class="btn btn-outline btn-block" style="margin-bottom: 0.5rem;">
                        <i class="fas fa-phone"></i>
                        Call Hospital
                    </a>
                    <?php endif; ?>

                    <?php if (!empty($response['hospital_email'])): ?>
                    <a href="mailto:<?php echo $response['hospital_email']; ?>" class="btn btn-outline btn-block">
                        <i class="fas fa-envelope"></i>
                        Email Hospital
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .navbar, .nav-right, .back-btn, .action-buttons, .btn, .logout-btn {
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
            
            .hospital-card {
                background: #f8f9fa;
            }
        }
    </style>

    <script>
        // Auto-dismiss alerts if any
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