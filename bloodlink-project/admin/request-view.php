<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier que c'est un admin
if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../public/login.php');
}

$page_title = "Gestion des demandes de sang";
$message = $_GET['msg'] ?? '';
$message_type = $_GET['type'] ?? 'success';
$active_filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Construire la requête SQL avec filtres
$sql = "
    SELECT dr.*, 
           bg.name as blood_group_name,
           h.hospital_name,
           h.city as hospital_city,
           h.phone as hospital_phone,
           h.email as hospital_email,
           (SELECT COUNT(*) FROM donations WHERE request_id = dr.id) as donor_count,
           (SELECT COUNT(*) FROM donations WHERE request_id = dr.id AND status = 'completed') as completed_count,
           (SELECT COUNT(*) FROM donations WHERE request_id = dr.id AND status = 'scheduled') as scheduled_count
    FROM donation_requests dr
    JOIN blood_groups bg ON dr.blood_group_id = bg.id
    JOIN hospitals h ON dr.hospital_id = h.id
    WHERE 1=1
";

$params = [];

// Filtre par statut
if ($active_filter !== 'all') {
    $sql .= " AND dr.status = ?";
    $params[] = $active_filter;
}

// Recherche
if (!empty($search)) {
    $sql .= " AND (h.hospital_name LIKE ? OR bg.name LIKE ? OR dr.notes LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY 
    CASE dr.urgency 
        WHEN 'high' THEN 1 
        WHEN 'medium' THEN 2 
        WHEN 'low' THEN 3 
    END,
    dr.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Statistiques globales
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) as fulfilled,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN urgency = 'high' AND status = 'pending' THEN 1 ELSE 0 END) as urgent
    FROM donation_requests
";
$stats = $pdo->query($stats_sql)->fetch();

// Statistiques par groupe sanguin
$blood_stats_sql = "
    SELECT 
        bg.name as blood_group,
        COUNT(dr.id) as request_count,
        SUM(dr.quantity) as total_units
    FROM donation_requests dr
    JOIN blood_groups bg ON dr.blood_group_id = bg.id
    WHERE dr.status = 'pending'
    GROUP BY bg.id, bg.name
    ORDER BY request_count DESC
";
$blood_stats = $pdo->query($blood_stats_sql)->fetchAll();

// Dernières activités
$activities_sql = "
    (SELECT 
        'request' as type,
        dr.id as item_id,
        CONCAT('New request: ', bg.name, ' from ', h.hospital_name) as description,
        dr.created_at as date
    FROM donation_requests dr
    JOIN blood_groups bg ON dr.blood_group_id = bg.id
    JOIN hospitals h ON dr.hospital_id = h.id
    ORDER BY dr.created_at DESC
    LIMIT 5)
    UNION ALL
    (SELECT 
        'donation' as type,
        d.id as item_id,
        CONCAT('Donation: ', u.full_name, ' for ', bg.name) as description,
        d.created_at as date
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    JOIN donation_requests dr ON d.request_id = dr.id
    JOIN blood_groups bg ON dr.blood_group_id = bg.id
    ORDER BY d.created_at DESC
    LIMIT 5)
    ORDER BY date DESC
    LIMIT 10
";
$activities = $pdo->query($activities_sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
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
            --primary: #28a745;
            --primary-dark: #218838;
            --primary-light: #5cb85c;
            --primary-soft: #e8f5e9;
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
            max-width: 1400px;
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-header i {
            font-size: 2rem;
            color: var(--primary);
            opacity: 0.8;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .stat-trend {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            background: var(--primary-soft);
            border-radius: 20px;
            color: var(--primary-dark);
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Blood Stats */
        .blood-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .blood-card {
            background: var(--white);
            padding: 1.2rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-light);
            text-align: center;
            transition: var(--transition);
        }

        .blood-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .blood-type {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .blood-count {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .blood-label {
            font-size: 0.8rem;
            color: var(--gray);
        }

        /* Filters */
        .filters-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius);
            border: 1px solid var(--gray-light);
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-dark);
        }

        .filter-input, .filter-select {
            padding: 0.8rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            transition: var(--transition);
            width: 100%;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .tab {
            padding: 0.8rem 1.5rem;
            border-radius: 30px;
            background: var(--white);
            border: 1px solid var(--gray-light);
            color: var(--gray);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .tab i {
            font-size: 0.9rem;
        }

        /* Table */
        .table-container {
            background: var(--white);
            border-radius: var(--radius);
            border: 1px solid var(--gray-light);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--light);
            padding: 1rem 1.2rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-dark);
            font-size: 0.9rem;
            border-bottom: 2px solid var(--gray-light);
            white-space: nowrap;
        }

        td {
            padding: 1rem 1.2rem;
            border-bottom: 1px solid var(--gray-light);
            color: var(--dark);
            font-size: 0.95rem;
        }

        tr:hover td {
            background: var(--light);
        }

        tr:last-child td {
            border-bottom: none;
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

        .status-fulfilled {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-cancelled {
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

        /* Hospital badge */
        .hospital-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .hospital-icon {
            width: 32px;
            height: 32px;
            background: var(--primary-soft);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
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
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
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
            padding: 0;
            border-radius: 8px;
        }

        /* Activities */
        .activities-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1px solid var(--gray-light);
            padding: 1.5rem;
        }

        .activities-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--gray-dark);
            font-weight: 600;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.8rem 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-soft);
            color: var(--primary);
        }

        .activity-content {
            flex: 1;
        }

        .activity-description {
            font-size: 0.95rem;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.8rem;
            color: var(--gray);
        }

        .activity-type {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            background: var(--light);
            color: var(--gray);
        }

        /* Empty state */
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

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 0 1rem;
            }

            table {
                font-size: 0.85rem;
            }

            td, th {
                padding: 0.8rem;
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
                BloodLink Admin
            </div>
            <div class="user-info">
                <i class="fas fa-user-shield"></i>
                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="validations.php"><i class="fas fa-check-circle"></i> Validations</a>
            <a href="request-view.php" class="active"><i class="fas fa-clipboard-list"></i> Demandes</a>
            <a href="users.php"><i class="fas fa-users"></i> Utilisateurs</a>
            <a href="blood-stock.php"><i class="fas fa-tint"></i> Stock</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Rapports</a>
            <a href="profile.php"><i class="fas fa-user"></i> Profil</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </nav>

    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-clipboard-list"></i>
                Gestion des demandes de sang
            </h1>
            <div class="filter-actions">
                <a href="?export=1&filter=<?php echo $active_filter; ?>" class="btn btn-outline">
                    <i class="fas fa-download"></i> Exporter
                </a>
                <a href="request-view.php?t=<?php echo time(); ?>" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Actualiser
                </a>
            </div>
        </div>

        <!-- Statistiques globales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <i class="fas fa-clipboard-list"></i>
                    <span class="stat-trend">Total</span>
                </div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Demandes totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <i class="fas fa-clock"></i>
                    <span class="stat-trend">En attente</span>
                </div>
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Demandes en attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span class="stat-trend">Urgent</span>
                </div>
                <div class="stat-value"><?php echo $stats['urgent']; ?></div>
                <div class="stat-label">Demandes urgentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <i class="fas fa-check-circle"></i>
                    <span class="stat-trend">Satisfaites</span>
                </div>
                <div class="stat-value"><?php echo $stats['fulfilled']; ?></div>
                <div class="stat-label">Demandes satisfaites</div>
            </div>
        </div>

        <!-- Statistiques par groupe sanguin -->
        <div class="blood-stats">
            <?php foreach ($blood_stats as $stat): ?>
            <div class="blood-card">
                <div class="blood-type"><?php echo $stat['blood_group']; ?></div>
                <div class="blood-count"><?php echo $stat['request_count']; ?> demandes</div>
                <div class="blood-label"><?php echo $stat['total_units']; ?> unités</div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filtres -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Rechercher</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Hôpital, groupe sanguin, notes..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Statut</label>
                        <select name="filter" class="filter-select">
                            <option value="all" <?php echo $active_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="pending" <?php echo $active_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                            <option value="fulfilled" <?php echo $active_filter === 'fulfilled' ? 'selected' : ''; ?>>Satisfaites</option>
                            <option value="cancelled" <?php echo $active_filter === 'cancelled' ? 'selected' : ''; ?>>Annulées</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Période</label>
                        <select name="period" class="filter-select">
                            <option value="all">Toutes les périodes</option>
                            <option value="today">Aujourd'hui</option>
                            <option value="week">Cette semaine</option>
                            <option value="month">Ce mois</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                            <a href="request-view.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Réinitialiser
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabs de navigation rapide -->
        <div class="tabs">
            <a href="?filter=all" class="tab <?php echo $active_filter === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> Toutes (<?php echo $stats['total']; ?>)
            </a>
            <a href="?filter=pending" class="tab <?php echo $active_filter === 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> En attente (<?php echo $stats['pending']; ?>)
            </a>
            <a href="?filter=fulfilled" class="tab <?php echo $active_filter === 'fulfilled' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Satisfaites (<?php echo $stats['fulfilled']; ?>)
            </a>
            <a href="?filter=cancelled" class="tab <?php echo $active_filter === 'cancelled' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i> Annulées (<?php echo $stats['cancelled']; ?>)
            </a>
        </div>

        <!-- Tableau des demandes -->
        <div class="table-container">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Hôpital</th>
                            <th>Groupe</th>
                            <th>Quantité</th>
                            <th>Urgence</th>
                            <th>Statut</th>
                            <th>Réponses</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="9" class="empty-state">
                                <i class="fas fa-clipboard-list"></i>
                                <h3>Aucune demande trouvée</h3>
                                <p>Aucune demande ne correspond à vos critères de recherche.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><strong>#<?php echo $request['id']; ?></strong></td>
                                <td>
                                    <div class="hospital-badge">
                                        <div class="hospital-icon">
                                            <i class="fas fa-hospital"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($request['hospital_name']); ?></div>
                                            <div style="font-size: 0.8rem; color: var(--gray);">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($request['hospital_city']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background: #dc3545; color: white; padding: 0.4rem 0.8rem; border-radius: 20px;">
                                        <?php echo $request['blood_group_name']; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $request['quantity']; ?></strong> unité(s)</td>
                                <td>
                                    <span class="status-badge urgency-<?php echo $request['urgency']; ?>">
                                        <?php if ($request['urgency'] === 'high'): ?>
                                            <i class="fas fa-exclamation-triangle"></i> Urgent
                                        <?php elseif ($request['urgency'] === 'medium'): ?>
                                            <i class="fas fa-clock"></i> Moyen
                                        <?php else: ?>
                                            <i class="fas fa-check-circle"></i> Bas
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $request['status']; ?>">
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <i class="fas fa-clock"></i> En attente
                                        <?php elseif ($request['status'] === 'fulfilled'): ?>
                                            <i class="fas fa-check-circle"></i> Satisfaite
                                        <?php elseif ($request['status'] === 'cancelled'): ?>
                                            <i class="fas fa-times-circle"></i> Annulée
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <span class="badge" style="background: var(--primary-soft); color: var(--primary-dark); padding: 0.3rem 0.6rem; border-radius: 20px;">
                                            <i class="fas fa-users"></i> <?php echo $request['donor_count']; ?>
                                        </span>
                                        <?php if ($request['scheduled_count'] > 0): ?>
                                            <span class="badge" style="background: #fff3cd; color: #856404;">
                                                <i class="fas fa-calendar"></i> <?php echo $request['scheduled_count']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.9rem;"><?php echo date('d/m/Y', strtotime($request['created_at'])); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--gray);"><?php echo date('H:i', strtotime($request['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="request-detail.php?id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <a href="?action=fulfill&id=<?php echo $request['id']; ?>&t=<?php echo time(); ?>" 
                                               class="btn btn-sm btn-primary" 
                                               title="Marquer comme satisfaite"
                                               onclick="return confirm('Marquer cette demande comme satisfaite ?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($request['status'] !== 'cancelled' && $request['status'] !== 'fulfilled'): ?>
                                            <a href="?action=cancel&id=<?php echo $request['id']; ?>&t=<?php echo time(); ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Annuler"
                                               onclick="return confirm('Annuler cette demande ?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Dernières activités -->
        <div class="activities-card">
            <div class="activities-header">
                <i class="fas fa-history"></i>
                <span>Dernières activités</span>
            </div>
            <?php foreach ($activities as $activity): ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-<?php echo $activity['type'] === 'request' ? 'clipboard' : 'heart'; ?>"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-description"><?php echo htmlspecialchars($activity['description']); ?></div>
                    <div class="activity-time">
                        <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($activity['date'])); ?>
                        <span class="activity-type"><?php echo $activity['type'] === 'request' ? 'Demande' : 'Don'; ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // Auto-dismiss des alertes
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 3000);

        // Animation au survol
        document.querySelectorAll('.stat-card, .blood-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>