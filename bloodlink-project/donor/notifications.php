<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

if (!isLoggedIn() || getUserRole() !== 'donor') {
    redirect('../public/login.php');
}

$page_title = "My Notifications - BloodLink";
$message = '';
$message_type = '';

// Actions
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'mark_all_read') {
        markAllNotificationsAsRead($pdo, $_SESSION['user_id'], $_SESSION['user_role']);
        $message = "✅ All notifications marked as read.";
        $message_type = 'success';
    } elseif ($_GET['action'] === 'clear_all') {
        // Marquer toutes comme lues (ne pas supprimer pour l'historique)
        markAllNotificationsAsRead($pdo, $_SESSION['user_id'], $_SESSION['user_role']);
        $message = "✅ All notifications cleared.";
        $message_type = 'success';
    }
}

// Récupérer toutes les notifications
$notifications = getUserNotifications($pdo, $_SESSION['user_id'], $_SESSION['user_role'], 100);

// Statistiques
$stats = [
    'total' => count($notifications),
    'unread' => 0,
    'urgent' => 0,
    'today' => 0
];

$today = date('Y-m-d');
foreach ($notifications as $notif) {
    if (!$notif['is_read']) $stats['unread']++;
    if ($notif['type'] === 'urgent' || $notif['type'] === 'danger') $stats['urgent']++;
    if (date('Y-m-d', strtotime($notif['created_at'])) === $today) $stats['today']++;
}
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
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Header */
        .page-header {
            margin-bottom: 2rem;
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

        /* Alert */
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
            background: #d4edda;
            border-color: var(--success);
            color: #155724;
        }

        .alert-danger {
            background: #fee2e2;
            border-color: var(--danger);
            color: #991b1b;
        }

        .alert-warning {
            background: #fff3cd;
            border-color: var(--warning);
            color: #856404;
        }

        .alert-info {
            background: #d1ecf1;
            border-color: var(--info);
            color: #0c5460;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.3rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Actions */
        .actions-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

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
            transform: translateY(-2px);
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

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }

        /* Filters */
        .filters-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: 30px;
            background: white;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            color: var(--gray);
        }

        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Notifications List */
        .notifications-list {
            background: var(--white);
            border-radius: var(--radius);
            border: 1px solid var(--gray-light);
            overflow: hidden;
        }

        .notification-item {
            padding: 1.2rem;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            transition: var(--transition);
            position: relative;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: var(--light);
        }

        .notification-item.unread {
            background: #f0f7ff;
            border-left: 3px solid var(--primary);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.3rem;
            flex-wrap: wrap;
        }

        .notification-title {
            font-weight: 600;
            color: var(--dark);
        }

        .notification-type {
            padding: 0.2rem 0.6rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .type-urgent, .type-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .type-warning {
            background: #fff3cd;
            color: #856404;
        }

        .type-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .type-info {
            background: #e3f2fd;
            color: #0d47a1;
        }

        .notification-message {
            color: var(--gray-dark);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .notification-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--gray);
        }

        .notification-time {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .notification-actions {
            margin-left: auto;
            display: flex;
            gap: 0.5rem;
        }

        .notification-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .notification-link:hover {
            text-decoration: underline;
        }

        .mark-read {
            color: var(--gray);
            text-decoration: none;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .mark-read:hover {
            color: var(--primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-light);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            margin-bottom: 1.5rem;
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .actions-bar {
                flex-direction: column;
            }

            .notification-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.3rem;
            }

            .notification-actions {
                margin-left: 0;
                margin-top: 0.5rem;
            }

            .container {
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body>
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
            <a href="notifications.php" class="active"><i class="fas fa-bell"></i> Notifications</a>
            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-bell"></i>
                My Notifications
                <?php if ($stats['unread'] > 0): ?>
                    <span style="background: var(--primary); color: white; padding: 0.3rem 1rem; border-radius: 30px; font-size: 1rem;">
                        <?php echo $stats['unread']; ?> new
                    </span>
                <?php endif; ?>
            </h1>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['unread']; ?></div>
                <div class="stat-label">Unread</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['urgent']; ?></div>
                <div class="stat-label">Urgent</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['today']; ?></div>
                <div class="stat-label">Today</div>
            </div>
        </div>

        <!-- Actions -->
        <div class="actions-bar">
            <a href="?action=mark_all_read" class="btn btn-primary">
                <i class="fas fa-check-double"></i> Mark all as read
            </a>
            <a href="?action=clear_all" class="btn btn-outline" onclick="return confirm('Clear all notifications?')">
                <i class="fas fa-trash"></i> Clear all
            </a>
            <a href="notification-settings.php" class="btn btn-outline">
                <i class="fas fa-cog"></i> Settings
            </a>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <button class="filter-btn active" onclick="filterNotifications('all')">All</button>
            <button class="filter-btn" onclick="filterNotifications('unread')">Unread</button>
            <button class="filter-btn" onclick="filterNotifications('urgent')">Urgent</button>
            <button class="filter-btn" onclick="filterNotifications('today')">Today</button>
        </div>

        <!-- Notifications List -->
        <div class="notifications-list" id="notificationsList">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No notifications</h3>
                    <p>You don't have any notifications yet.</p>
                    <a href="requests.php" class="btn btn-primary">
                        <i class="fas fa-tint"></i> Browse Requests
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): 
                    $type_class = 'type-' . ($notif['type'] ?? 'info');
                    $is_unread = !($notif['is_read'] ?? true);
                ?>
                    <div class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>" 
                         data-type="<?php echo $notif['type'] ?? 'info'; ?>"
                         data-read="<?php echo $is_unread ? '0' : '1'; ?>"
                         data-date="<?php echo date('Y-m-d', strtotime($notif['created_at'])); ?>">
                        
                        <div class="notification-icon">
                            <?php echo $notif['icon'] ?? '<i class="fas fa-bell"></i>'; ?>
                        </div>
                        
                        <div class="notification-content">
                            <div class="notification-header">
                                <span class="notification-title"><?php echo htmlspecialchars($notif['title'] ?? 'Notification'); ?></span>
                                <span class="notification-type <?php echo $type_class; ?>">
                                    <?php 
                                    $type_labels = [
                                        'urgent' => 'URGENT',
                                        'danger' => 'IMPORTANT',
                                        'warning' => 'ALERT',
                                        'success' => 'SUCCESS',
                                        'info' => 'INFO'
                                    ];
                                    echo $type_labels[$notif['type']] ?? 'INFO';
                                    ?>
                                </span>
                            </div>
                            
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notif['message'] ?? ''); ?>
                            </div>
                            
                            <div class="notification-meta">
                                <span class="notification-time">
                                    <i class="far fa-clock"></i>
                                    <?php 
                                    $time = strtotime($notif['created_at']);
                                    $diff = time() - $time;
                                    
                                    if ($diff < 60) {
                                        echo 'Just now';
                                    } elseif ($diff < 3600) {
                                        echo floor($diff / 60) . ' minutes ago';
                                    } elseif ($diff < 86400) {
                                        echo floor($diff / 3600) . ' hours ago';
                                    } else {
                                        echo date('d/m/Y H:i', $time);
                                    }
                                    ?>
                                </span>
                                
                                <?php if (!empty($notif['link'])): ?>
                                    <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="notification-link">
                                        View details <i class="fas fa-arrow-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($is_unread): ?>
                            <div class="notification-actions">
                                <span class="mark-read" onclick="markAsRead(<?php echo $notif['id']; ?>, this)">
                                    <i class="fas fa-check"></i> Mark read
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Filter notifications
        function filterNotifications(filter) {
            const items = document.querySelectorAll('.notification-item');
            const today = '<?php echo date('Y-m-d'); ?>';
            
            items.forEach(item => {
                let show = true;
                
                switch(filter) {
                    case 'unread':
                        show = item.dataset.read === '0';
                        break;
                    case 'urgent':
                        show = item.dataset.type === 'urgent' || item.dataset.type === 'danger';
                        break;
                    case 'today':
                        show = item.dataset.date === today;
                        break;
                    // 'all' par défaut
                }
                
                item.style.display = show ? 'flex' : 'none';
            });
            
            // Update active filter button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        // Mark notification as read
        function markAsRead(notificationId, element) {
            fetch('../ajax/mark-notification-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = element.closest('.notification-item');
                    item.classList.remove('unread');
                    item.dataset.read = '1';
                    element.parentElement.remove(); // Remove mark read button
                    
                    // Update unread count in header
                    const unreadBadge = document.querySelector('.page-header span');
                    const statsUnread = document.querySelector('.stat-card:nth-child(2) .stat-number');
                    
                    if (unreadBadge) {
                        const currentUnread = parseInt(unreadBadge.textContent);
                        if (currentUnread > 1) {
                            unreadBadge.textContent = (currentUnread - 1) + ' new';
                        } else {
                            unreadBadge.remove();
                        }
                    }
                    
                    if (statsUnread) {
                        statsUnread.textContent = parseInt(statsUnread.textContent) - 1;
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Auto-refresh notifications every minute
        setInterval(() => {
            fetch('../ajax/get-notifications.php?unread_only=1')
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        // Update notification badge if exists
                        const notificationLink = document.querySelector('a[href="notifications.php"]');
                        if (notificationLink) {
                            let badge = notificationLink.querySelector('.notification-badge');
                            if (!badge) {
                                badge = document.createElement('span');
                                badge.className = 'notification-badge';
                                notificationLink.appendChild(badge);
                            }
                            badge.textContent = data.count;
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
        }, 60000); // Every minute
    </script>
</body>
</html>