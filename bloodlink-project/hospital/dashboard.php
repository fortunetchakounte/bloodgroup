<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is a hospital
if (!isLoggedIn() || getUserRole() !== 'hospital') {
    redirect('../public/login.php');
}

$page_title = "Dashboard - Hospital";

// Get hospital info
$stmt = $pdo->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$hospital = $stmt->fetch();

// Statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM blood_requests WHERE hospital_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_requests = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM blood_requests WHERE hospital_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pending_requests = $stmt->fetch()['pending'];

$stmt = $pdo->prepare("SELECT COUNT(*) as fulfilled FROM blood_requests WHERE hospital_id = ? AND status = 'fulfilled'");
$stmt->execute([$_SESSION['user_id']]);
$fulfilled_requests = $stmt->fetch()['fulfilled'];

// Urgent requests
$stmt = $pdo->prepare("SELECT COUNT(*) as urgent FROM blood_requests WHERE hospital_id = ? AND urgency = 'emergency' AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$urgent_requests = $stmt->fetch()['urgent'];

// Recent requests
$stmt = $pdo->prepare("
    SELECT br.*, bg.name as blood_group_name 
    FROM blood_requests br
    LEFT JOIN blood_groups bg ON br.blood_group_id = bg.id
    WHERE br.hospital_id = ? 
    ORDER BY br.created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_requests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - BloodLink</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/../includes/notification-bell.php'; ?>
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
            --primary: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #1f2937;
            --gray-dark: #374151;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --light: #f9fafb;
            --white: #ffffff;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --radius: 10px;
            --radius-sm: 6px;
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
            gap: 1rem;
            background: var(--light);
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            border: 1px solid var(--gray-light);
        }

        .hospital-name {
            font-weight: 600;
            color: var(--dark);
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .verified {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .verified i {
            color: #2e7d32;
        }

        .pending {
            background: #fff3e0;
            color: #ef6c00;
        }

        .pending i {
            color: #ef6c00;
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
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Welcome Card */
        .welcome-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--hospital), var(--hospital-light));
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .welcome-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .welcome-header h1 i {
            color: var(--hospital);
            margin-right: 0.5rem;
        }

        .hospital-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            background: var(--light);
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            margin: 1.5rem 0;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .detail-item i {
            width: 24px;
            color: var(--hospital);
            font-size: 1.1rem;
        }

        .detail-item span {
            color: var(--gray-dark);
            font-size: 0.95rem;
        }

        .detail-item strong {
            color: var(--dark);
            font-weight: 600;
            margin-left: 0.3rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            transition: var(--transition);
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--hospital);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--hospital-soft);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--hospital);
            font-size: 1.3rem;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.3rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-trend {
            font-size: 0.8rem;
            margin-top: 0.5rem;
            color: var(--gray);
        }

        .stat-trend i {
            color: var(--hospital);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            border: 1px solid var(--gray-light);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .action-card:hover {
            transform: translateY(-3px);
            border-color: var(--hospital);
            box-shadow: var(--shadow-md);
        }

        .action-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--hospital), var(--hospital-light));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .action-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.3rem;
        }

        .action-card p {
            color: var(--gray);
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: 1rem;
            flex: 1;
        }

        .action-link {
            color: var(--hospital);
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .action-link i {
            font-size: 0.8rem;
            transition: transform 0.2s;
        }

        .action-card:hover .action-link i {
            transform: translateX(3px);
        }

        /* Recent Requests */
        .recent-requests {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            border: 1px solid var(--gray-light);
            margin-top: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-header h2 i {
            color: var(--hospital);
        }

        .view-all {
            color: var(--hospital);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .requests-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .request-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--radius-sm);
            border-left: 4px solid transparent;
        }

        .request-item.emergency {
            border-left-color: var(--danger);
        }

        .request-item.urgent {
            border-left-color: var(--warning);
        }

        .request-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.3rem;
        }

        .request-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--gray);
        }

        .request-meta i {
            margin-right: 0.2rem;
        }

        .request-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-fulfilled {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-emergency {
            background: #fee2e2;
            color: #991b1b;
        }

        .request-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
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

        /* Alert */
        .alert {
            background: #fff3cd;
            border-left: 4px solid var(--warning);
            padding: 1.2rem 1.5rem;
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
            margin-top: 2rem;
        }

        .alert h3 {
            color: #856404;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert p {
            color: #856404;
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

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

            .welcome-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .hospital-details {
                grid-template-columns: 1fr;
            }

            .request-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .request-actions {
                align-self: flex-end;
            }

            .container {
                padding: 0 1rem;
            }
        }

        @media (max-width: 480px) {
            .stat-number {
                font-size: 1.8rem;
            }

            .nav-right a {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
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
                <span class="hospital-name"><?php echo htmlspecialchars($hospital['hospital_name']); ?></span>
                <span class="verification-badge <?php echo $hospital['is_verified'] ? 'verified' : 'pending'; ?>">
                    <i class="fas fa-<?php echo $hospital['is_verified'] ? 'check-circle' : 'clock'; ?>"></i>
                    <?php echo $hospital['is_verified'] ? 'Verified' : 'Pending'; ?>
                </span>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="create-request.php"><i class="fas fa-plus-circle"></i> New Request</a>
            <a href="my-requests.php"><i class="fas fa-list"></i> My Requests</a>
            <a href="profile.php"><i class="fas fa-user-md"></i> Profile</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="welcome-header">
                <h1>
                    <i class="fas fa-hospital"></i>
                    Welcome, <?php echo htmlspecialchars($hospital['hospital_name']); ?>!
                </h1>
                <?php if ($hospital['is_verified']): ?>
                    <span class="verification-badge verified">
                        <i class="fas fa-check-circle"></i> Verified Account
                    </span>
                <?php endif; ?>
            </div>
            
            <p style="color: var(--gray); margin-bottom: 1rem;">
                Manage your blood requests and find compatible donors efficiently.
            </p>
            
            <div class="hospital-details">
                <div class="detail-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><strong>Address:</strong> <?php echo htmlspecialchars($hospital['address']); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-user"></i>
                    <span><strong>Contact:</strong> <?php echo htmlspecialchars($hospital['contact_person']); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-phone"></i>
                    <span><strong>Phone:</strong> <?php echo htmlspecialchars($hospital['phone']); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-envelope"></i>
                    <span><strong>Email:</strong> <?php echo htmlspecialchars($hospital['email']); ?></span>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_requests; ?></div>
                    <div class="stat-label">Total Requests</div>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i> All time
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $pending_requests; ?></div>
                    <div class="stat-label">Pending</div>
                    <div class="stat-trend">
                        <i class="fas fa-hourglass-half"></i> Awaiting response
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $fulfilled_requests; ?></div>
                    <div class="stat-label">Fulfilled</div>
                    <div class="stat-trend">
                        <i class="fas fa-heart"></i> Lives saved
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?php echo $urgent_requests; ?></div>
                    <div class="stat-label">Urgent</div>
                    <div class="stat-trend">
                        <i class="fas fa-bell"></i> Need attention
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="create-request.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-plus"></i>
                </div>
                <h3>Create Request</h3>
                <p>Post a new blood request for your patients</p>
                <span class="action-link">
                    Create now <i class="fas fa-arrow-right"></i>
                </span>
            </a>
            
            <a href="my-requests.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-list"></i>
                </div>
                <h3>My Requests</h3>
                <p>View and manage all your blood requests</p>
                <span class="action-link">
                    View all <i class="fas fa-arrow-right"></i>
                </span>
            </a>
            
            <a href="find-donors.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Find Donors</h3>
                <p>Search for compatible donors by blood type</p>
                <span class="action-link">
                    Search donors <i class="fas fa-arrow-right"></i>
                </span>
            </a>
            
            <a href="profile.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3>Hospital Profile</h3>
                <p>Update your hospital information</p>
                <span class="action-link">
                    Edit profile <i class="fas fa-arrow-right"></i>
                </span>
            </a>
        </div>

        <!-- Recent Requests -->
        <?php if (!empty($recent_requests)): ?>
        <div class="recent-requests">
            <div class="section-header">
                <h2>
                    <i class="fas fa-history"></i>
                    Recent Requests
                </h2>
                <a href="my-requests.php" class="view-all">
                    View all <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="requests-list">
                <?php foreach ($recent_requests as $request): ?>
                <div class="request-item <?php echo $request['urgency']; ?>">
                    <div class="request-info">
                        <h4>
                            <?php echo htmlspecialchars($request['blood_group_name'] ?? 'Blood request'); ?>
                            <span style="font-weight: normal; color: var(--gray); margin-left: 0.5rem;">
                                (<?php echo $request['quantity']; ?> units)
                            </span>
                        </h4>
                        <div class="request-meta">
                            <span><i class="far fa-calendar"></i> <?php echo date('d/m/Y', strtotime($request['created_at'])); ?></span>
                            <span><i class="far fa-clock"></i> <?php echo date('H:i', strtotime($request['created_at'])); ?></span>
                            <span><i class="fas fa-exclamation-circle"></i> <?php echo ucfirst($request['urgency']); ?></span>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span class="request-badge badge-<?php echo $request['status']; ?>">
                            <?php 
                            $status_text = [
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'fulfilled' => 'Fulfilled',
                                'cancelled' => 'Cancelled'
                            ];
                            echo $status_text[$request['status']] ?? $request['status'];
                            ?>
                        </span>
                        
                        <div class="request-actions">
                            <a href="view-request.php?id=<?php echo $request['id']; ?>" class="btn-icon" title="View details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($request['status'] == 'pending'): ?>
                            <a href="edit-request.php?id=<?php echo $request['id']; ?>" class="btn-icon" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Verification Alert -->
        <?php if (!$hospital['is_verified']): ?>
        <div class="alert">
            <h3>
                <i class="fas fa-clock"></i>
                Account Pending Verification
            </h3>
            <p>
                Your account is not yet verified. You will be able to create blood requests once an administrator validates your account.
                This process usually takes 24-48 hours.
            </p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Animation au scroll
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .action-card');
            
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