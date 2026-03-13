<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || getUserRole() !== 'hospital') {
    redirect('../public/login.php');
}

$request_id = (int)($_GET['id'] ?? 0);

// Récupérer la demande avec vérification de propriété
$stmt = $pdo->prepare("
    SELECT dr.*, bg.name as blood_group_name, h.hospital_name 
    FROM donation_requests dr
    JOIN blood_groups bg ON dr.blood_group_id = bg.id
    JOIN hospitals h ON dr.hospital_id = h.id
    WHERE dr.id = ? AND dr.hospital_id = ?
");
$stmt->execute([$request_id, $_SESSION['user_id']]);
$request = $stmt->fetch();

if (!$request) {
    die("<div style='padding: 2rem; text-align: center;'>
        <h2>Request Not Found</h2>
        <p>This request does not exist or you don't have access to it.</p>
        <a href='my-requests.php'>Back to My Requests</a>
    </div>");
}

// Récupérer les dons associés
$stmt = $pdo->prepare("
    SELECT d.*, u.full_name, u.phone, u.email, u.city 
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE d.request_id = ?
    ORDER BY d.created_at DESC
");
$stmt->execute([$request_id]);
$donations = $stmt->fetchAll();

$page_title = "Request #" . $request['id'] . " - BloodLink";
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
            color: var(--hospital);
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
            color: var(--hospital);
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
            color: var(--hospital);
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
            color: var(--hospital);
            border-color: var(--hospital);
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
            animation: fadeInUp 0.5s ease;
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
            color: var(--hospital);
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

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--hospital-soft);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--hospital);
            font-size: 1.1rem;
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
            font-size: 1rem;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-fulfilled {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-low {
            background: #d4edda;
            color: #155724;
        }

        .status-medium {
            background: #fff3cd;
            color: #856404;
        }

        .status-high {
            background: #f8d7da;
            color: #721c24;
        }

        .status-scheduled {
            background: #e3f2fd;
            color: #0d47a1;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Table */
        .table-responsive {
            overflow-x: auto;
            border-radius: var(--radius-sm);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 1rem 0.8rem;
            color: var(--gray);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--gray-light);
            background: var(--light);
        }

        td {
            padding: 1rem 0.8rem;
            border-bottom: 1px solid var(--gray-light);
            color: var(--dark);
            font-size: 0.95rem;
        }

        tr:hover td {
            background: var(--light);
        }

        /* Donor Card */
        .donor-card {
            background: var(--light);
            border-radius: var(--radius-sm);
            padding: 1rem;
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .donor-card:hover {
            border-color: var(--hospital);
            box-shadow: var(--shadow);
        }

        .donor-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .donor-avatar {
            width: 50px;
            height: 50px;
            background: var(--hospital-soft);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--hospital);
            font-size: 1.2rem;
            font-weight: 600;
        }

        .donor-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.2rem;
        }

        .donor-info p {
            color: var(--gray);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .donor-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 0.8rem 0;
            border-top: 1px solid var(--gray-light);
            border-bottom: 1px solid var(--gray-light);
        }

        .donor-contact {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-dark);
            font-size: 0.85rem;
        }

        .donor-contact i {
            width: 16px;
            color: var(--hospital);
        }

        .donor-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
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
            border: 1px solid var(--gray-light);
            color: var(--gray);
        }

        .btn-outline:hover {
            background: var(--light);
            color: var(--hospital);
            border-color: var(--hospital);
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

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--white);
            border: 1px solid var(--gray-light);
            color: var(--gray);
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-icon:hover {
            background: var(--hospital);
            color: white;
            border-color: var(--hospital);
        }

        /* Stats mini */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-mini {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--radius-sm);
            text-align: center;
        }

        .stat-mini .number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--hospital);
        }

        .stat-mini .label {
            font-size: 0.8rem;
            color: var(--gray);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-light);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
        }

        .empty-state p {
            color: var(--gray);
            margin-bottom: 1.5rem;
        }

        /* Notes section */
        .notes-section {
            margin-top: 1.5rem;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--radius-sm);
        }

        .notes-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: var(--hospital);
            font-weight: 600;
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

            .stats-mini {
                grid-template-columns: 1fr;
            }

            .donor-actions {
                flex-wrap: wrap;
            }

            .btn {
                width: 100%;
            }

            .container {
                padding: 0 1rem;
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
                <i class="fas fa-user-md"></i>
                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Hospital'); ?>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="my-requests.php"><i class="fas fa-list"></i> My Requests</a>
            <a href="create-request.php"><i class="fas fa-plus-circle"></i> New Request</a>
            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-clipboard-list"></i>
                Request Details #<?php echo $request['id']; ?>
            </h1>
            <a href="my-requests.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to My Requests
            </a>
        </div>

        <!-- Request Information Card -->
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
                        <div class="info-value"><?php echo htmlspecialchars($request['blood_group_name']); ?></div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-cube"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Quantity</div>
                        <div class="info-value"><?php echo $request['quantity']; ?> unit(s)</div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-tag"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Urgency</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo $request['urgency']; ?>">
                                <?php 
                                $urgency_labels = [
                                    'low' => '🟢 Low',
                                    'medium' => '🟡 Medium',
                                    'high' => '🔴 High'
                                ];
                                echo $urgency_labels[$request['urgency']] ?? $request['urgency'];
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Created At</div>
                        <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></div>
                    </div>
                </div>

                <?php if (!empty($request['needed_by'])): ?>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Needed By</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($request['needed_by'])); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                <?php 
                                $status_labels = [
                                    'pending' => '⏳ Pending',
                                    'approved' => '✅ Approved',
                                    'fulfilled' => '✅ Fulfilled',
                                    'cancelled' => '❌ Cancelled'
                                ];
                                echo $status_labels[$request['status']] ?? $request['status'];
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($request['notes'])): ?>
            <div class="notes-section">
                <div class="notes-header">
                    <i class="fas fa-sticky-note"></i>
                    <strong>Notes / Additional Information:</strong>
                </div>
                <p style="color: var(--gray-dark);"><?php echo nl2br(htmlspecialchars($request['notes'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Mini stats -->
            <div class="stats-mini">
                <div class="stat-mini">
                    <div class="number"><?php echo count($donations); ?></div>
                    <div class="label">Total Responses</div>
                </div>
                <div class="stat-mini">
                    <div class="number">
                        <?php 
                        $confirmed = 0;
                        foreach ($donations as $d) {
                            if ($d['status'] === 'completed') $confirmed++;
                        }
                        echo $confirmed;
                        ?>
                    </div>
                    <div class="label">Completed</div>
                </div>
                <div class="stat-mini">
                    <div class="number">
                        <?php 
                        $pending = 0;
                        foreach ($donations as $d) {
                            if ($d['status'] === 'scheduled') $pending++;
                        }
                        echo $pending;
                        ?>
                    </div>
                    <div class="label">Scheduled</div>
                </div>
            </div>
        </div>

        <!-- Donors Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-users"></i>
                <h2>Donor Responses (<?php echo count($donations); ?>)</h2>
            </div>

            <?php if (empty($donations)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-friends"></i>
                    <h3>No Responses Yet</h3>
                    <p>No donors have responded to this request yet.</p>
                    <a href="my-requests.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Requests
                    </a>
                </div>
            <?php else: ?>
                <div class="donors-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1rem;">
                    <?php foreach ($donations as $donation): ?>
                    <div class="donor-card">
                        <div class="donor-header">
                            <div class="donor-avatar">
                                <?php echo strtoupper(substr($donation['full_name'], 0, 1)); ?>
                            </div>
                            <div class="donor-info">
                                <h4><?php echo htmlspecialchars($donation['full_name']); ?></h4>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($donation['city'] ?? 'City not specified'); ?></p>
                            </div>
                        </div>

                        <div class="donor-details">
                            <div class="donor-contact">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($donation['phone'] ?? 'No phone'); ?></span>
                            </div>
                            <div class="donor-contact">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($donation['email']); ?></span>
                            </div>
                            <div class="donor-contact">
                                <i class="fas fa-clock"></i>
                                <span>Responded: <?php echo date('d/m/Y', strtotime($donation['created_at'])); ?></span>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                            <span class="status-badge status-<?php echo $donation['status']; ?>">
                                <?php 
                                $donation_status = [
                                    'scheduled' => '📅 Scheduled',
                                    'completed' => '✅ Completed',
                                    'cancelled' => '❌ Cancelled',
                                    'pending' => '⏳ Pending'
                                ];
                                echo $donation_status[$donation['status']] ?? $donation['status'];
                                ?>
                            </span>
                            <?php if (!empty($donation['donation_date'])): ?>
                            <small style="color: var(--gray);">
                                <i class="fas fa-calendar-check"></i>
                                <?php echo date('d/m/Y', strtotime($donation['donation_date'])); ?>
                            </small>
                            <?php endif; ?>
                        </div>

                        <div class="donor-actions">
                            <?php if ($donation['status'] === 'scheduled'): ?>
                            <a href="confirm-donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to confirm this donation?')">
                                <i class="fas fa-check"></i> Confirm
                            </a>
                            <?php endif; ?>
                            <a href="tel:<?php echo $donation['phone']; ?>" class="btn btn-sm btn-outline">
                                <i class="fas fa-phone"></i> Call
                            </a>
                            <a href="mailto:<?php echo $donation['email']; ?>" class="btn btn-sm btn-outline">
                                <i class="fas fa-envelope"></i> Email
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1rem; flex-wrap: wrap;">
            <?php if ($request['status'] === 'pending'): ?>
            <a href="approve-request.php?id=<?php echo $request['id']; ?>" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this request?')">
                <i class="fas fa-check-circle"></i> Approve Request
            </a>
            <?php endif; ?>
            
            <?php if ($request['status'] !== 'cancelled' && $request['status'] !== 'fulfilled'): ?>
            <a href="cancel-request.php?id=<?php echo $request['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this request?')">
                <i class="fas fa-times-circle"></i> Cancel Request
            </a>
            <?php endif; ?>
            
            <a href="edit-request.php?id=<?php echo $request['id']; ?>" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit Request
            </a>
            
            <a href="my-requests.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <script>
        // Animation on scroll
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card, .donor-card');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>