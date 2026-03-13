<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Verify it's a hospital
if (!isLoggedIn() || getUserRole() !== 'hospital') {
    redirect('../public/login.php');
}

$page_title = "My Blood Requests";
$message = '';
$message_type = '';

// Actions on requests
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $request_id = (int)$_GET['id'];
    
    // Verify that the request belongs to this hospital
    $stmt = $pdo->prepare("SELECT id FROM donation_requests WHERE id = ? AND hospital_id = ?");
    $stmt->execute([$request_id, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        try {
            if ($action === 'cancel') {
                $stmt = $pdo->prepare("UPDATE donation_requests SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$request_id]);
                $message = "✅ Request cancelled.";
                $message_type = 'success';
            } 
            elseif ($action === 'delete') {
                // Check if there are associated donations
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE request_id = ?");
                $stmt->execute([$request_id]);
                $has_donations = $stmt->fetchColumn();
                
                if ($has_donations == 0) {
                    $stmt = $pdo->prepare("DELETE FROM donation_requests WHERE id = ?");
                    $stmt->execute([$request_id]);
                    $message = "✅ Request deleted.";
                    $message_type = 'success';
                } else {
                    $message = "⚠️ Cannot delete: donations are associated with this request.";
                    $message_type = 'warning';
                }
            }
            elseif ($action === 'fulfill') {
                $stmt = $pdo->prepare("UPDATE donation_requests SET status = 'fulfilled', fulfilled_at = NOW() WHERE id = ?");
                $stmt->execute([$request_id]);
                $message = "✅ Request marked as fulfilled.";
                $message_type = 'success';
            }
            elseif ($action === 'reopen') {
                $stmt = $pdo->prepare("UPDATE donation_requests SET status = 'pending', fulfilled_at = NULL WHERE id = ?");
                $stmt->execute([$request_id]);
                $message = "✅ Request reopened.";
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = "❌ You don't have permission to modify this request.";
        $message_type = 'danger';
    }
}

// Get hospital requests
$stmt = $pdo->prepare("
    SELECT dr.*, bg.name as blood_group_name,
           (SELECT COUNT(*) FROM donations WHERE request_id = dr.id) as donor_count
    FROM donation_requests dr
    JOIN blood_groups bg ON dr.blood_group_id = bg.id
    WHERE dr.hospital_id = ?
    ORDER BY 
        CASE dr.urgency 
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'low' THEN 3 
        END,
        dr.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$requests = $stmt->fetchAll();

// Statistics
$stats = [
    'total' => count($requests),
    'pending' => 0,
    'fulfilled' => 0,
    'cancelled' => 0,
    'total_donors' => 0
];

foreach ($requests as $request) {
    $stats[$request['status']]++;
    $stats['total_donors'] += $request['donor_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        /* Base styles similar to create-request.php */
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
            background: #28a745; 
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
            color: #28a745; 
            margin-bottom: 0.5rem; 
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
            border-top: 4px solid #28a745; 
        }
        
        .stat-number { 
            font-size: 2rem; 
            color: #28a745; 
            font-weight: bold; 
            margin-bottom: 0.5rem; 
        }
        
        .stat-label { 
            color: #666; 
            font-size: 0.9rem; 
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
            border-bottom-color: #28a745; 
            color: #28a745; 
            font-weight: bold; 
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
        }
        
        .request-card:hover { 
            transform: translateY(-5px); 
        }
        
        .request-card.pending { 
            border-left-color: #ffc107; 
        }
        
        .request-card.fulfilled { 
            border-left-color: #28a745; 
        }
        
        .request-card.cancelled { 
            border-left-color: #6c757d; 
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
        }
        
        .request-badge { 
            display: inline-block; 
            padding: 0.25rem 0.75rem; 
            border-radius: 20px; 
            font-size: 0.85rem; 
            font-weight: 500; 
            margin-right: 0.5rem; 
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
        
        .badge-status { 
            background: #e2e3e5; 
            color: #383d41; 
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
        }
        
        .info-label { 
            font-size: 0.9rem; 
            color: #6c757d; 
            margin-bottom: 0.25rem; 
        }
        
        .info-value { 
            font-weight: 500; 
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
        
        .btn-sm { 
            padding: 0.25rem 0.5rem; 
            font-size: 0.875rem; 
        }
        
        .btn-primary { 
            background: #28a745; 
            color: white; 
        }
        
        .btn-primary:hover { 
            background: #218838; 
        }
        
        .btn-danger { 
            background: #dc3545; 
            color: white; 
        }
        
        .btn-danger:hover { 
            background: #c82333; 
        }
        
        .btn-warning { 
            background: #ffc107; 
            color: #212529; 
        }
        
        .btn-warning:hover { 
            background: #e0a800; 
        }
        
        .btn-secondary { 
            background: #6c757d; 
            color: white; 
        }
        
        .btn-secondary:hover { 
            background: #5a6268; 
        }
        
        .empty-state { 
            text-align: center; 
            padding: 3rem; 
            color: #6c757d; 
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
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        .search-filter { 
            display: flex; 
            gap: 1rem; 
            margin-bottom: 1.5rem; 
            align-items: center; 
            flex-wrap: wrap; 
        }
        
        .search-box { 
            padding: 0.5rem 1rem; 
            border: 1px solid #ced4da; 
            border-radius: 5px; 
            width: 300px; 
        }
        
        .search-box:focus {
            outline: none;
            border-color: #28a745;
        }
        
        .filter-select { 
            padding: 0.5rem; 
            border: 1px solid #ced4da; 
            border-radius: 5px; 
            background: white; 
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #28a745;
        }
        
        .pagination { 
            display: flex; 
            justify-content: center; 
            gap: 0.5rem; 
            margin-top: 2rem; 
        }
        
        .page-btn { 
            padding: 0.5rem 1rem; 
            border: 1px solid #dee2e6; 
            background: white; 
            border-radius: 5px; 
            cursor: pointer; 
        }
        
        .page-btn.active { 
            background: #28a745; 
            color: white; 
            border-color: #28a745; 
        }
        
        .page-btn:hover:not(.active) { 
            background: #f8f9fa; 
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">🏥 BloodLink Hospital</div>
            <div class="user-info">
                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php">Dashboard</a>
            <a href="create-request.php">New Request</a>
            <a href="my-requests.php" style="background: rgba(255,255,255,0.2);">My Requests</a>
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
        
        <div class="card">
            <h1>📋 My Blood Requests</h1>
            <p>Manage all your published blood requests.</p>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['fulfilled']; ?></div>
                    <div class="stat-label">Fulfilled</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_donors']; ?></div>
                    <div class="stat-label">Interested Donors</div>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="search-filter">
                <input type="text" class="search-box" placeholder="Search by ID, blood group..." 
                       onkeyup="filterRequests(this.value)">
                <select class="filter-select" onchange="filterByStatus(this.value)">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="fulfilled">Fulfilled</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select class="filter-select" onchange="filterByUrgency(this.value)">
                    <option value="">All Urgencies</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
                <a href="create-request.php" class="btn btn-primary">+ New Request</a>
            </div>
            
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="switchTab('all')">All (<?php echo $stats['total']; ?>)</div>
                <div class="tab" onclick="switchTab('pending')">Pending (<?php echo $stats['pending']; ?>)</div>
                <div class="tab" onclick="switchTab('urgent')">Urgent (<?php 
                    $urgent_count = 0;
                    foreach ($requests as $r) {
                        if ($r['urgency'] === 'high' && $r['status'] === 'pending') $urgent_count++;
                    }
                    echo $urgent_count;
                ?>)</div>
                <div class="tab" onclick="switchTab('fulfilled')">Fulfilled (<?php echo $stats['fulfilled']; ?>)</div>
            </div>
            
            <!-- Requests List -->
            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📝</div>
                    <h3>No requests yet</h3>
                    <p>Create your first blood request to get started.</p>
                    <a href="create-request.php" class="btn btn-primary" style="margin-top: 1rem;">
                        Create a Request
                    </a>
                </div>
            <?php else: ?>
                <div class="requests-grid" id="requestsContainer">
                    <?php foreach ($requests as $request): 
                        $urgency_badge = [
                            'high' => ['class' => 'badge-urgent', 'text' => '🔴 High'],
                            'medium' => ['class' => 'badge-medium', 'text' => '🟡 Medium'],
                            'low' => ['class' => 'badge-low', 'text' => '🟢 Low']
                        ][$request['urgency']];
                        
                        $status_text = [
                            'pending' => '⏳ Pending',
                            'fulfilled' => '✅ Fulfilled',
                            'cancelled' => '❌ Cancelled'
                        ][$request['status']];
                    ?>
                        <div class="request-card <?php echo $request['status']; ?>" 
                             data-status="<?php echo $request['status']; ?>"
                             data-urgency="<?php echo $request['urgency']; ?>"
                             data-group="<?php echo $request['blood_group_name']; ?>">
                            <div class="request-header">
                                <div class="request-id">Request #<?php echo $request['id']; ?></div>
                                <div class="request-title">Blood Group <?php echo htmlspecialchars($request['blood_group_name']); ?></div>
                                <div>
                                    <span class="request-badge <?php echo $urgency_badge['class']; ?>">
                                        <?php echo $urgency_badge['text']; ?>
                                    </span>
                                    <span class="request-badge badge-status">
                                        <?php echo $status_text; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="request-body">
                                <div class="request-info">
                                    <div class="info-item">
                                        <div class="info-label">Quantity</div>
                                        <div class="info-value"><?php echo $request['quantity']; ?> bag(s)</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Interested Donors</div>
                                        <div class="info-value"><?php echo $request['donor_count']; ?> person(s)</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Created Date</div>
                                        <div class="info-value"><?php echo date('m/d/Y', strtotime($request['created_at'])); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Last Updated</div>
                                        <div class="info-value">
                                            <?php 
                                            echo $request['fulfilled_at'] 
                                                ? date('m/d/Y', strtotime($request['fulfilled_at'])) . ' (fulfilled)'
                                                : date('m/d/Y', strtotime($request['created_at']));
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($request['notes']): ?>
                                    <div style="margin-top: 1rem; padding: 0.75rem; background: #f8f9fa; border-radius: 5px; font-size: 0.9rem;">
                                        <strong>Notes:</strong> <?php echo htmlspecialchars($request['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="request-actions">
                                <?php if ($request['status'] === 'pending'): ?>
                                    <a href="view-request.php?id=<?php echo $request['id']; ?>" class="btn btn-primary">
                                        👁️ View Responses
                                    </a>
                                    <a href="?action=fulfill&id=<?php echo $request['id']; ?>" class="btn btn-primary"
                                       onclick="return confirm('Mark this request as fulfilled?')">
                                        ✅ Fulfilled
                                    </a>
                                    <a href="?action=cancel&id=<?php echo $request['id']; ?>" class="btn btn-warning"
                                       onclick="return confirm('Cancel this request?')">
                                        ⏹️ Cancel
                                    </a>
                                <?php elseif ($request['status'] === 'fulfilled'): ?>
                                    <a href="view-request.php?id=<?php echo $request['id']; ?>" class="btn btn-secondary">
                                        📋 View Details
                                    </a>
                                    <a href="?action=reopen&id=<?php echo $request['id']; ?>" class="btn btn-warning"
                                       onclick="return confirm('Reopen this request?')">
                                        🔄 Reopen
                                    </a>
                                <?php elseif ($request['status'] === 'cancelled'): ?>
                                    <a href="?action=delete&id=<?php echo $request['id']; ?>" class="btn btn-danger"
                                       onclick="return confirm('Permanently delete this request?')">
                                        🗑️ Delete
                                    </a>
                                <?php endif; ?>
                                
                                <a href="edit-request.php?id=<?php echo $request['id']; ?>" class="btn btn-secondary">
                                    ✏️ Edit
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Simple pagination info -->
                <div style="text-align: center; margin-top: 1rem; color: #6c757d;">
                    Showing <?php echo count($requests); ?> request(s)
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabName) {
            const cards = document.querySelectorAll('.request-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                let shouldShow = false;
                
                switch(tabName) {
                    case 'all':
                        shouldShow = true;
                        break;
                    case 'pending':
                        if (card.dataset.status === 'pending') shouldShow = true;
                        break;
                    case 'urgent':
                        if (card.dataset.status === 'pending' && card.dataset.urgency === 'high') 
                            shouldShow = true;
                        break;
                    case 'fulfilled':
                        if (card.dataset.status === 'fulfilled') shouldShow = true;
                        break;
                }
                
                card.style.display = shouldShow ? 'block' : 'none';
                if (shouldShow) visibleCount++;
            });
            
            // Update active tab
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Show message if no results
            const container = document.getElementById('requestsContainer');
            let noResultsMsg = document.getElementById('noResultsMsg');
            
            if (visibleCount === 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMsg';
                    noResultsMsg.className = 'empty-state';
                    noResultsMsg.innerHTML = '<div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div><h3>No requests found</h3><p>Try changing your filters.</p>';
                    container.parentNode.insertBefore(noResultsMsg, container.nextSibling);
                }
            } else {
                if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        }
        
        // Filter by search
        function filterRequests(searchTerm) {
            searchTerm = searchTerm.toLowerCase().trim();
            const cards = document.querySelectorAll('.request-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                const group = card.dataset.group.toLowerCase();
                const id = card.querySelector('.request-id').textContent.toLowerCase();
                
                if (text.includes(searchTerm) || group.includes(searchTerm) || id.includes(searchTerm)) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            const container = document.getElementById('requestsContainer');
            let noResultsMsg = document.getElementById('noResultsMsg');
            
            if (visibleCount === 0 && searchTerm !== '') {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMsg';
                    noResultsMsg.className = 'empty-state';
                    noResultsMsg.innerHTML = '<div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div><h3>No requests found</h3><p>No results match "' + searchTerm + '"</p>';
                    container.parentNode.insertBefore(noResultsMsg, container.nextSibling);
                }
            } else {
                if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        }
        
        // Filter by status
        function filterByStatus(status) {
            const cards = document.querySelectorAll('.request-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                if (!status || card.dataset.status === status) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Update active tab visual
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelector('.tab:first-child').classList.add('active');
        }
        
        // Filter by urgency
        function filterByUrgency(urgency) {
            const cards = document.querySelectorAll('.request-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                if (!urgency || card.dataset.urgency === urgency) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Update active tab visual
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelector('.tab:first-child').classList.add('active');
        }
        
        // Auto-dismiss alerts after 5 seconds
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