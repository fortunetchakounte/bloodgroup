<?php
require_once __DIR__ . '/../includes/init.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../public/login.php');
}

$page_title = "Hospitals Management - BloodLink";
$message = '';
$message_type = '';

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    try {
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM hospitals WHERE id = ?")->execute([$id]);
            $message = "Hospital deleted successfully!";
            $message_type = 'success';
        } elseif ($action === 'verify') {
            $pdo->prepare("UPDATE hospitals SET is_verified = 1 WHERE id = ?")->execute([$id]);
            $message = "Hospital verified successfully!";
            $message_type = 'success';
        } elseif ($action === 'unverify') {
            $pdo->prepare("UPDATE hospitals SET is_verified = 0 WHERE id = ?")->execute([$id]);
            $message = "Hospital unverified!";
            $message_type = 'warning';
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get hospitals with filtering
$search = $_GET['search'] ?? '';
$city = $_GET['city'] ?? '';
$status = $_GET['status'] ?? '';

$query = "SELECT * FROM hospitals WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (hospital_name LIKE ? OR email LIKE ? OR contact_person LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($city) {
    $query .= " AND city LIKE ?";
    $params[] = "%$city%";
}

if ($status === 'verified') {
    $query .= " AND is_verified = 1";
} elseif ($status === 'pending') {
    $query .= " AND is_verified = 0";
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$hospitals = $stmt->fetchAll();

// Get unique cities for filter
$cities = $pdo->query("SELECT DISTINCT city FROM hospitals WHERE city IS NOT NULL ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
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

        /* Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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

        /* Hospitals grid */
        .hospitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .hospital-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .hospital-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .hospital-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .hospital-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--info));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .hospital-info {
            min-width: 0;
        }

        .hospital-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .hospital-info p {
            color: var(--gray);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .hospital-info i {
            width: 14px;
            color: var(--primary);
            font-size: 0.8rem;
        }

        .hospital-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin: 1rem 0;
            padding: 1rem 0;
            border-top: 1px solid var(--gray-light);
            border-bottom: 1px solid var(--gray-light);
        }

        .detail-item {
            font-size: 0.85rem;
        }

        .detail-label {
            color: var(--gray);
            margin-bottom: 0.15rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .detail-value {
            font-weight: 500;
            color: var(--dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Badges */
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

        /* Action buttons */
        .hospital-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .action-btn {
            width: 34px;
            height: 34px;
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

        .action-btn i {
            font-size: 0.9rem;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .hospitals-grid {
                grid-template-columns: 1fr;
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
            <a href="donors.php"><i class="fas fa-users"></i> Donors</a>
            <a href="hospitals.php" class="active"><i class="fas fa-hospital"></i> Hospitals</a>
            <a href="requests.php"><i class="fas fa-tint"></i> Requests</a>
            <a href="validations.php"><i class="fas fa-check-circle"></i> Validations</a>
            <a href="reports.php"><i class="fas fa-file-pdf"></i> Reports</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>Hospitals Management</h1>
            <p>Complete list of partner hospitals</p>
        </div>

        <!-- Alert Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <?php
        $total_hospitals = count($hospitals);
        $verified = count(array_filter($hospitals, fn($h) => $h['is_verified']));
        $pending = $total_hospitals - $verified;
        ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-hospital"></i></div>
                <div class="stat-number"><?php echo $total_hospitals; ?></div>
                <div class="stat-label">Total Hospitals</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo $verified; ?></div>
                <div class="stat-label">Verified</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $pending; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search hospital..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <select class="filter-select" id="cityFilter">
                <option value="">All Cities</option>
                <?php foreach ($cities as $city_name): ?>
                <option value="<?php echo $city_name; ?>" <?php echo $city == $city_name ? 'selected' : ''; ?>>
                    <?php echo $city_name; ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select class="filter-select" id="statusFilter">
                <option value="">All Statuses</option>
                <option value="verified" <?php echo $status === 'verified' ? 'selected' : ''; ?>>Verified</option>
                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
            </select>
            
            <a href="hospitals.php" class="btn-clear">
                <i class="fas fa-times"></i> Clear
            </a>
        </div>

        <!-- Hospitals Grid -->
        <div class="hospitals-grid">
            <?php if (empty($hospitals)): ?>
                <div class="empty-state">
                    <i class="fas fa-hospital"></i>
                    <h3>No hospitals found</h3>
                    <p>Adjust your filters to see more results.</p>
                </div>
            <?php else: ?>
                <?php foreach ($hospitals as $hospital): ?>
                <div class="hospital-card">
                    <div class="hospital-header">
                        <div class="hospital-avatar">
                            <i class="fas fa-hospital"></i>
                        </div>
                        <div class="hospital-info">
                            <h3><?php echo htmlspecialchars($hospital['hospital_name']); ?></h3>
                            <p>
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($hospital['city'] ?? 'City not specified'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="hospital-details">
                        <div class="detail-item">
                            <div class="detail-label">Contact Person</div>
                            <div class="detail-value"><?php echo htmlspecialchars($hospital['contact_person']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Phone</div>
                            <div class="detail-value"><?php echo htmlspecialchars($hospital['phone']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Email</div>
                            <div class="detail-value"><?php echo htmlspecialchars($hospital['email']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <?php if ($hospital['is_verified']): ?>
                                    <span class="badge badge-success">Verified</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="hospital-actions">
                        <a href="hospitals.php?action=view&id=<?php echo $hospital['id']; ?>" 
                           class="action-btn" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="hospitals.php?action=edit&id=<?php echo $hospital['id']; ?>" 
                           class="action-btn" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php if (!$hospital['is_verified']): ?>
                        <a href="?action=verify&id=<?php echo $hospital['id']; ?>" 
                           class="action-btn" title="Verify"
                           onclick="return confirm('Verify this hospital?')">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i>
                        </a>
                        <?php else: ?>
                        <a href="?action=unverify&id=<?php echo $hospital['id']; ?>" 
                           class="action-btn" title="Unverify"
                           onclick="return confirm('Unverify this hospital?')">
                            <i class="fas fa-times-circle" style="color: var(--warning);"></i>
                        </a>
                        <?php endif; ?>
                        <a href="?action=delete&id=<?php echo $hospital['id']; ?>" 
                           class="action-btn" title="Delete"
                           onclick="return confirm('Permanently delete this hospital?')">
                            <i class="fas fa-trash" style="color: var(--danger);"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
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
        document.getElementById('cityFilter').addEventListener('change', applyFilters);
        document.getElementById('statusFilter').addEventListener('change', applyFilters);

        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const city = document.getElementById('cityFilter').value;
            const status = document.getElementById('statusFilter').value;
            
            window.location.href = `hospitals.php?search=${encodeURIComponent(search)}&city=${encodeURIComponent(city)}&status=${status}`;
        }
    </script>
</body>
</html>