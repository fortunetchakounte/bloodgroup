<?php
require_once __DIR__ . '/../includes/init.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../public/login.php');
}

$page_title = "Requests Management - BloodLink";
$message = '';
$message_type = '';

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    try {
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM blood_requests WHERE id = ?")->execute([$id]);
            $message = "Request deleted successfully!";
            $message_type = 'success';
        } elseif ($action === 'approve') {
            $pdo->prepare("UPDATE blood_requests SET status = 'approved' WHERE id = ?")->execute([$id]);
            $message = "Request approved!";
            $message_type = 'success';
        } elseif ($action === 'fulfill') {
            $pdo->prepare("UPDATE blood_requests SET status = 'fulfilled', fulfilled_at = NOW() WHERE id = ?")->execute([$id]);
            $message = "Request marked as fulfilled!";
            $message_type = 'success';
        } elseif ($action === 'cancel') {
            $pdo->prepare("UPDATE blood_requests SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            $message = "Request cancelled!";
            $message_type = 'warning';
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$hospital_id = $_GET['hospital_id'] ?? '';
$blood_group = $_GET['blood_group'] ?? '';
$urgency = $_GET['urgency'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$query = "SELECT r.*, h.hospital_name, h.city as hospital_city, bg.name as blood_group_name 
          FROM blood_requests r 
          JOIN hospitals h ON r.hospital_id = h.id 
          LEFT JOIN blood_groups bg ON r.blood_group_id = bg.id 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (h.hospital_name LIKE ? OR r.patient_name LIKE ? OR r.id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($hospital_id) {
    $query .= " AND r.hospital_id = ?";
    $params[] = $hospital_id;
}

if ($blood_group) {
    $query .= " AND r.blood_group_id = ?";
    $params[] = $blood_group;
}

if ($urgency) {
    $query .= " AND r.urgency = ?";
    $params[] = $urgency;
}

if ($status) {
    $query .= " AND r.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY 
    CASE r.urgency 
        WHEN 'emergency' THEN 1 
        WHEN 'urgent' THEN 2 
        ELSE 3 
    END, 
    r.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Get hospitals for filter
$hospitals = $pdo->query("SELECT id, hospital_name FROM hospitals ORDER BY hospital_name")->fetchAll();

// Get blood groups for filter
$blood_groups = $pdo->query("SELECT id, name FROM blood_groups ORDER BY name")->fetchAll();

// Statistics
$total_requests = count($requests);
$pending_requests = count(array_filter($requests, fn($r) => $r['status'] == 'pending'));
$emergency_requests = count(array_filter($requests, fn($r) => $r['urgency'] == 'emergency' && $r['status'] == 'pending'));
$urgent_requests = count(array_filter($requests, fn($r) => $r['urgency'] == 'urgent' && $r['status'] == 'pending'));
$fulfilled_requests = count(array_filter($requests, fn($r) => $r['status'] == 'fulfilled'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #0f172a;
            --gray-dark: #334155;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --light: #f8fafc;
            --white: #ffffff;
            --shadow: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.05);
            --radius: 10px;
            --radius-sm: 6px;
            --transition: all 0.2s;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: var(--dark);
            line-height: 1.5;
        }

        /* Navigation */
        .navbar {
            background: var(--white);
            padding: 0.75rem 2rem;
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
            gap: 1.5rem;
        }

        .logo {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--dark);
        }

        .logo i {
            color: var(--primary);
        }

        .user-info {
            background: var(--light);
            padding: 0.35rem 1rem;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--gray);
            border: 1px solid var(--gray-light);
        }

        .nav-right {
            display: flex;
            gap: 0.25rem;
        }

        .nav-right a {
            color: var(--gray);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
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
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Header */
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark);
        }

        .add-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            background: var(--primary);
            color: white;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .add-btn:hover {
            background: var(--primary-dark);
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #dcfce7;
            border-color: var(--success);
            color: #166534;
        }

        .alert-danger {
            background: #fee2e2;
            border-color: var(--danger);
            color: #991b1b;
        }

        .alert-warning {
            background: #fef3c7;
            border-color: var(--warning);
            color: #92400e;
        }

        /* Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.25rem;
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1rem;
            margin-bottom: 0.75rem;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Filters bar */
        .filters-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
            background: var(--white);
            padding: 1.25rem;
            border-radius: var(--radius);
            border: 1px solid var(--gray-light);
        }

        .search-box {
            flex: 2;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .search-box i {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 0.9rem;
        }

        .filter-select {
            flex: 1;
            min-width: 150px;
            padding: 0.6rem 2rem 0.6rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            cursor: pointer;
            background: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn-clear {
            padding: 0.6rem 1.2rem;
            background: var(--light);
            color: var(--gray);
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            white-space: nowrap;
        }

        .btn-clear:hover {
            background: var(--gray-light);
            color: var(--dark);
        }

        /* Requests grid */
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
        }

        .request-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            border: 1px solid var(--gray-light);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .request-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .request-card.emergency {
            border-left: 4px solid var(--danger);
        }

        .request-card.urgent {
            border-left: 4px solid var(--warning);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .request-hospital {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .request-hospital i {
            color: var(--primary);
            font-size: 1rem;
        }

        .request-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .badge-emergency {
            background: #fee2e2;
            color: var(--danger);
        }

        .badge-urgent {
            background: #fef3c7;
            color: var(--warning);
        }

        .badge-normal {
            background: #dbeafe;
            color: var(--info);
        }

        .request-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin: 1rem 0;
            padding: 1rem 0;
            border-top: 1px solid var(--gray-light);
            border-bottom: 1px solid var(--gray-light);
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .detail-label {
            color: var(--gray);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .detail-value {
            font-weight: 500;
            color: var(--dark);
            font-size: 0.95rem;
        }

        .detail-value i {
            margin-right: 0.3rem;
            color: var(--primary);
        }

        .request-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }

        .request-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Status badges */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-purple {
            background: #f3e8ff;
            color: #6b21a8;
        }

        /* Action buttons */
        .action-btns {
            display: flex;
            gap: 0.3rem;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            text-decoration: none;
            transition: var(--transition);
            background: var(--light);
            border: 1px solid var(--gray-light);
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .action-btn.approve:hover {
            background: var(--success);
        }

        .action-btn.fulfill:hover {
            background: var(--info);
        }

        .action-btn.cancel:hover {
            background: var(--warning);
        }

        .action-btn.delete:hover {
            background: var(--danger);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
            grid-column: 1/-1;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--gray-light);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .empty-state p {
            font-size: 0.9rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.3rem;
            margin-top: 2rem;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            background: var(--white);
            color: var(--gray);
            text-decoration: none;
            transition: var(--transition);
            border: 1px solid var(--gray-light);
        }

        .page-link:hover {
            background: var(--light);
            color: var(--primary);
            border-color: var(--primary);
        }

        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .nav-right {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .requests-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .container {
                padding: 0 1rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .request-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">
                <i class="fas fa-tint"></i>
                BloodLink
            </div>
            <div class="user-info">
                <i class="fas fa-user-shield"></i>
                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="donors.php"><i class="fas fa-users"></i> Donors</a>
            <a href="hospitals.php"><i class="fas fa-hospital"></i> Hospitals</a>
            <a href="requests.php" class="active"><i class="fas fa-tint"></i> Requests</a>
            <a href="validation.php"><i class="fas fa-check-circle"></i> Validations</a>
            <a href="reports.php"><i class="fas fa-file-pdf"></i> Reports</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-tint" style="color: var(--primary); margin-right: 0.5rem;"></i> Blood Requests Management</h1>
            <a href="request-add.php" class="add-btn">
                <i class="fas fa-plus"></i> New Request
            </a>
        </div>

        <!-- Alert Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-tint"></i></div>
                <div class="stat-number"><?php echo $total_requests; ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $pending_requests; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i></div>
                <div class="stat-number" style="color: var(--danger);"><?php echo $emergency_requests; ?></div>
                <div class="stat-label">Emergency</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-exclamation" style="color: var(--warning);"></i></div>
                <div class="stat-number" style="color: var(--warning);"><?php echo $urgent_requests; ?></div>
                <div class="stat-label">Urgent</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle" style="color: var(--success);"></i></div>
                <div class="stat-number" style="color: var(--success);"><?php echo $fulfilled_requests; ?></div>
                <div class="stat-label">Fulfilled</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search (hospital, patient, ID)..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <select class="filter-select" id="hospitalFilter">
                <option value="">All Hospitals</option>
                <?php foreach ($hospitals as $h): ?>
                <option value="<?php echo $h['id']; ?>" <?php echo $hospital_id == $h['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($h['hospital_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select class="filter-select" id="bloodGroupFilter">
                <option value="">All Blood Groups</option>
                <?php foreach ($blood_groups as $bg): ?>
                <option value="<?php echo $bg['id']; ?>" <?php echo $blood_group == $bg['id'] ? 'selected' : ''; ?>>
                    Group <?php echo $bg['name']; ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select class="filter-select" id="urgencyFilter">
                <option value="">All Urgencies</option>
                <option value="emergency" <?php echo $urgency === 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                <option value="urgent" <?php echo $urgency === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                <option value="normal" <?php echo $urgency === 'normal' ? 'selected' : ''; ?>>Normal</option>
            </select>
            
            <select class="filter-select" id="statusFilter">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="fulfilled" <?php echo $status === 'fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            
            <a href="requests.php" class="btn-clear">
                <i class="fas fa-times"></i> Clear
            </a>
        </div>

        <!-- Requests Grid -->
        <div class="requests-grid">
            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <i class="fas fa-tint"></i>
                    <h3>No requests found</h3>
                    <p>Adjust your filters or create a new request.</p>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                <div class="request-card <?php echo $request['urgency']; ?>">
                    <div class="request-header">
                        <div class="request-hospital">
                            <i class="fas fa-hospital"></i>
                            <?php echo htmlspecialchars($request['hospital_name']); ?>
                        </div>
                        <span class="request-badge <?php 
                            echo $request['urgency'] == 'emergency' ? 'badge-emergency' : 
                                ($request['urgency'] == 'urgent' ? 'badge-urgent' : 'badge-normal'); 
                        ?>">
                            <?php 
                            echo $request['urgency'] == 'emergency' ? 'EMERGENCY' : 
                                ($request['urgency'] == 'urgent' ? 'URGENT' : 'NORMAL'); 
                            ?>
                        </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.8rem; color: var(--gray);">
                            <i class="fas fa-hashtag"></i> #<?php echo $request['id']; ?>
                        </span>
                        <span style="font-size: 0.8rem; color: var(--gray);">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($request['hospital_city']); ?>
                        </span>
                    </div>

                    <div class="request-details">
                        <div class="detail-item">
                            <span class="detail-label">Blood Group</span>
                            <span class="detail-value">
                                <i class="fas fa-tint"></i>
                                <?php echo $request['blood_group_name'] ?? 'Not specified'; ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Quantity</span>
                            <span class="detail-value">
                                <i class="fas fa-cube"></i>
                                <?php echo $request['quantity']; ?> unit(s)
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Patient</span>
                            <span class="detail-value">
                                <i class="fas fa-user-injured"></i>
                                <?php echo htmlspecialchars($request['patient_name'] ?? 'Not specified'); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Needed By</span>
                            <span class="detail-value">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d/m/Y', strtotime($request['needed_by'])); ?>
                            </span>
                        </div>
                    </div>

                    <div class="request-footer">
                        <div class="request-status">
                            <?php if ($request['status'] == 'pending'): ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php elseif ($request['status'] == 'approved'): ?>
                                <span class="badge badge-info">Approved</span>
                            <?php elseif ($request['status'] == 'fulfilled'): ?>
                                <span class="badge badge-success">Fulfilled</span>
                            <?php elseif ($request['status'] == 'cancelled'): ?>
                                <span class="badge badge-danger">Cancelled</span>
                            <?php endif; ?>
                            
                            <span style="color: var(--gray); font-size: 0.75rem;">
                                <i class="fas fa-clock"></i>
                                <?php echo date('d/m/Y', strtotime($request['created_at'])); ?>
                            </span>
                        </div>

                        <div class="action-btns">
                            <a href="request-view.php?id=<?php echo $request['id']; ?>" 
                               class="action-btn" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            
                            <?php if ($request['status'] == 'pending'): ?>
                                <a href="?action=approve&id=<?php echo $request['id']; ?>" 
                                   class="action-btn approve" title="Approve"
                                   onclick="return confirm('Approve this request?')">
                                    <i class="fas fa-check"></i>
                                </a>
                                <a href="?action=fulfill&id=<?php echo $request['id']; ?>" 
                                   class="action-btn fulfill" title="Mark as fulfilled"
                                   onclick="return confirm('Mark this request as fulfilled?')">
                                    <i class="fas fa-check-double"></i>
                                </a>
                                <a href="?action=cancel&id=<?php echo $request['id']; ?>" 
                                   class="action-btn cancel" title="Cancel"
                                   onclick="return confirm('Cancel this request?')">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                            
                            <a href="?action=delete&id=<?php echo $request['id']; ?>" 
                               class="action-btn delete" title="Delete"
                               onclick="return confirm('Permanently delete this request?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>

                    <?php if ($request['status'] == 'pending' && $request['urgency'] == 'emergency'): ?>
                    <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed var(--danger); display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-bell" style="color: var(--danger); animation: pulse 2s infinite;"></i>
                        <span style="color: var(--danger); font-size: 0.8rem; font-weight: 500;">URGENT ACTION REQUIRED</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <a href="#" class="page-link"><i class="fas fa-chevron-left"></i></a>
            <a href="#" class="page-link active">1</a>
            <a href="#" class="page-link">2</a>
            <a href="#" class="page-link">3</a>
            <a href="#" class="page-link">4</a>
            <a href="#" class="page-link">5</a>
            <a href="#" class="page-link"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <style>
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>

    <script>
        // Live search with debounce
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                applyFilters();
            }, 500);
        });

        // Filter on select change
        document.getElementById('hospitalFilter').addEventListener('change', applyFilters);
        document.getElementById('bloodGroupFilter').addEventListener('change', applyFilters);
        document.getElementById('urgencyFilter').addEventListener('change', applyFilters);
        document.getElementById('statusFilter').addEventListener('change', applyFilters);

        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const hospital_id = document.getElementById('hospitalFilter').value;
            const blood_group = document.getElementById('bloodGroupFilter').value;
            const urgency = document.getElementById('urgencyFilter').value;
            const status = document.getElementById('statusFilter').value;
            
            let url = 'requests.php?';
            if (search) url += `search=${encodeURIComponent(search)}&`;
            if (hospital_id) url += `hospital_id=${hospital_id}&`;
            if (blood_group) url += `blood_group=${blood_group}&`;
            if (urgency) url += `urgency=${urgency}&`;
            if (status) url += `status=${status}&`;
            
            window.location.href = url.slice(0, -1);
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
    </script>
</body>
</html>