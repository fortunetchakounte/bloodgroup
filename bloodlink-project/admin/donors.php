<?php
require_once __DIR__ . '/../includes/init.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../public/login.php');
}

$page_title = "Donors Management - BloodLink";
$message = '';
$message_type = '';

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    try {
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $message = "Donor deleted successfully!";
            $message_type = 'success';
        } elseif ($action === 'verify') {
            $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$id]);
            $message = "Donor verified successfully!";
            $message_type = 'success';
        } elseif ($action === 'unverify') {
            $pdo->prepare("UPDATE users SET is_verified = 0 WHERE id = ?")->execute([$id]);
            $message = "Donor unverified!";
            $message_type = 'warning';
        } elseif ($action === 'toggle_donate') {
            $pdo->prepare("UPDATE users SET can_donate = NOT can_donate WHERE id = ?")->execute([$id]);
            $message = "Donor donation status updated!";
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get donors with filtering
$search = $_GET['search'] ?? '';
$blood_group = $_GET['blood_group'] ?? '';
$status = $_GET['status'] ?? '';

$query = "SELECT u.*, bg.name as blood_group_name 
          FROM users u 
          LEFT JOIN blood_groups bg ON u.blood_group_id = bg.id 
          WHERE u.user_type = 'donor'";
$params = [];

if ($search) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($blood_group) {
    $query .= " AND u.blood_group_id = ?";
    $params[] = $blood_group;
}

if ($status === 'verified') {
    $query .= " AND u.is_verified = 1";
} elseif ($status === 'pending') {
    $query .= " AND u.is_verified = 0";
} elseif ($status === 'blocked') {
    $query .= " AND u.can_donate = 0";
}

$query .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$donors = $stmt->fetchAll();

// Get blood groups for filter
$blood_groups = $pdo->query("SELECT id, name FROM blood_groups ORDER BY name")->fetchAll();
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
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .page-header p {
            color: var(--gray);
            font-size: 0.95rem;
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

        .alert-info {
            background: #dbeafe;
            border-color: var(--info);
            color: #1e40af;
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
            flex: 1;
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
            padding: 0.6rem 2rem 0.6rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            min-width: 150px;
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
        }

        .btn-clear:hover {
            background: var(--gray-light);
            color: var(--dark);
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

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        /* Table */
        .table-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            border: 1px solid var(--gray-light);
            box-shadow: var(--shadow);
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
        }

        .table-title i {
            color: var(--primary);
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th {
            text-align: left;
            padding: 1rem 0.5rem;
            color: var(--gray);
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 1px solid var(--gray-light);
        }

        td {
            padding: 1rem 0.5rem;
            border-bottom: 1px solid var(--gray-light);
            font-size: 0.9rem;
        }

        tr:hover td {
            background: var(--light);
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
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

        .donor-status {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-verified {
            background: var(--success);
        }

        .status-pending {
            background: var(--warning);
        }

        .status-blocked {
            background: var(--danger);
        }

        /* Action buttons */
        .action-btns {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
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

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
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

        /* Responsive */
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
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
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
            <a href="donors.php" class="active"><i class="fas fa-users"></i> Donors</a>
            <a href="hospitals.php"><i class="fas fa-hospital"></i> Hospitals</a>
            <a href="requests.php"><i class="fas fa-tint"></i> Requests</a>
            <a href="validation.php"><i class="fas fa-check-circle"></i> Validations</a>
            <a href="reports.php"><i class="fas fa-file-pdf"></i> Reports</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>Donors Management</h1>
            <p>Complete list of donors registered on the platform</p>
        </div>

        <!-- Alert Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search donor..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <select class="filter-select" id="bloodGroupFilter">
                <option value="">All blood groups</option>
                <?php foreach ($blood_groups as $bg): ?>
                <option value="<?php echo $bg['id']; ?>" <?php echo $blood_group == $bg['id'] ? 'selected' : ''; ?>>
                    Group <?php echo $bg['name']; ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select class="filter-select" id="statusFilter">
                <option value="">All statuses</option>
                <option value="verified" <?php echo $status === 'verified' ? 'selected' : ''; ?>>Verified</option>
                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="blocked" <?php echo $status === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
            </select>
            
            <a href="donors.php" class="btn-clear">
                <i class="fas fa-times"></i> Clear
            </a>
        </div>

        <!-- Donors Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-users"></i>
                    Donors List (<?php echo count($donors); ?>)
                </h3>
                <div class="table-actions">
                    <a href="donors.php?action=add" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add
                    </a>
                    <a href="?action=export" class="btn btn-primary btn-sm">
                        <i class="fas fa-download"></i> Export
                    </a>
                </div>
            </div>

            <?php if (empty($donors)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No donors found</h3>
                    <p>Adjust your filters or add a new donor.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Blood Group</th>
                                <th>City</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donors as $donor): ?>
                            <tr>
                                <td>#<?php echo $donor['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($donor['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($donor['email']); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo $donor['blood_group_name'] ?? '?'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($donor['city'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($donor['phone'] ?? '-'); ?></td>
                                <td>
                                    <?php if (!$donor['is_verified']): ?>
                                        <span class="badge badge-warning">
                                            <span class="donor-status status-pending"></span>
                                            Pending
                                        </span>
                                    <?php elseif (!$donor['can_donate']): ?>
                                        <span class="badge badge-danger">
                                            <span class="donor-status status-blocked"></span>
                                            Blocked
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-success">
                                            <span class="donor-status status-verified"></span>
                                            Verified
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($donor['created_at'])); ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="donors.php?action=view&id=<?php echo $donor['id']; ?>" 
                                           class="action-btn" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="donors.php?action=edit&id=<?php echo $donor['id']; ?>" 
                                           class="action-btn" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (!$donor['is_verified']): ?>
                                        <a href="?action=verify&id=<?php echo $donor['id']; ?>" 
                                           class="action-btn" title="Verify"
                                           onclick="return confirm('Verify this donor?')">
                                            <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($donor['can_donate']): ?>
                                        <a href="?action=toggle_donate&id=<?php echo $donor['id']; ?>" 
                                           class="action-btn" title="Block"
                                           onclick="return confirm('Block this donor?')">
                                            <i class="fas fa-ban" style="color: var(--warning);"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="?action=delete&id=<?php echo $donor['id']; ?>" 
                                           class="action-btn" title="Delete"
                                           onclick="return confirm('Permanently delete this donor?')">
                                            <i class="fas fa-trash" style="color: var(--danger);"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
            <?php endif; ?>
        </div>
    </div>

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
        document.getElementById('bloodGroupFilter').addEventListener('change', applyFilters);
        document.getElementById('statusFilter').addEventListener('change', applyFilters);

        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const blood_group = document.getElementById('bloodGroupFilter').value;
            const status = document.getElementById('statusFilter').value;
            
            window.location.href = `donors.php?search=${encodeURIComponent(search)}&blood_group=${blood_group}&status=${status}`;
        }
    </script>
</body>
</html>