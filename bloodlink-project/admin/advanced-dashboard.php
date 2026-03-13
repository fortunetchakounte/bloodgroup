<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier que c'est un admin
if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../public/login.php');
}

$page_title = "Tableau de bord Avancé";
$timeframe = $_GET['timeframe'] ?? 'month'; // day, week, month, year

// Calculer les dates pour le timeframe
$date_ranges = [
    'day' => ['start' => date('Y-m-d'), 'end' => date('Y-m-d')],
    'week' => ['start' => date('Y-m-d', strtotime('-7 days')), 'end' => date('Y-m-d')],
    'month' => ['start' => date('Y-m-d', strtotime('-30 days')), 'end' => date('Y-m-d')],
    'year' => ['start' => date('Y-m-d', strtotime('-365 days')), 'end' => date('Y-m-d')]
];

$start_date = $date_ranges[$timeframe]['start'];
$end_date = $date_ranges[$timeframe]['end'];

// Statistiques générales
$stats = [
    'total_donors' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'donor'")->fetchColumn(),
    'active_donors' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'donor' AND is_verified = TRUE AND can_donate = TRUE")->fetchColumn(),
    'total_hospitals' => $pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn(),
    'active_hospitals' => $pdo->query("SELECT COUNT(*) FROM hospitals WHERE is_verified = TRUE")->fetchColumn(),
    'total_requests' => $pdo->query("SELECT COUNT(*) FROM donation_requests")->fetchColumn(),
    'active_requests' => $pdo->query("SELECT COUNT(*) FROM donation_requests WHERE status = 'pending'")->fetchColumn(),
    'total_donations' => $pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn(),
    'completed_donations' => $pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'completed'")->fetchColumn(),
    'avg_response_time' => $pdo->query("SELECT AVG(TIMESTAMPDIFF(HOUR, dr.created_at, d.created_at)) FROM donations d JOIN donation_requests dr ON d.request_id = dr.id")->fetchColumn(),
    'conversion_rate' => 0
];

if ($stats['total_requests'] > 0) {
    $stats['conversion_rate'] = round(($stats['completed_donations'] / $stats['total_requests']) * 100, 1);
}

// Données pour les graphiques
$chart_data = [
    'daily_stats' => [],
    'blood_group_distribution' => [],
    'city_distribution' => [],
    'request_status' => [],
    'donation_status' => []
];

// Récupérer les stats quotidiennes
$stmt = $pdo->prepare("
    SELECT stat_date, new_donors, new_requests, new_donations, completed_donations
    FROM daily_stats 
    WHERE stat_date BETWEEN ? AND ?
    ORDER BY stat_date
");
$stmt->execute([$start_date, $end_date]);
$chart_data['daily_stats'] = $stmt->fetchAll();

// Distribution des groupes sanguins
$stmt = $pdo->query("
    SELECT bg.name, COUNT(u.id) as count
    FROM users u
    JOIN blood_groups bg ON u.blood_group_id = bg.id
    WHERE u.user_type = 'donor'
    GROUP BY bg.id
    ORDER BY count DESC
");
$chart_data['blood_group_distribution'] = $stmt->fetchAll();

// Distribution par ville
$stmt = $pdo->query("
    SELECT city, COUNT(*) as count
    FROM (
        SELECT city FROM users WHERE user_type = 'donor'
        UNION ALL
        SELECT city FROM hospitals
    ) as all_cities
    WHERE city IS NOT NULL AND city != ''
    GROUP BY city
    ORDER BY count DESC
    LIMIT 10
");
$chart_data['city_distribution'] = $stmt->fetchAll();

// Statut des demandes
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM donation_requests
    GROUP BY status
");
$chart_data['request_status'] = $stmt->fetchAll();

// Statut des dons
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM donations
    GROUP BY status
");
$chart_data['donation_status'] = $stmt->fetchAll();

// Alertes système actives
$alerts_stmt = $pdo->query("
    SELECT * FROM system_alerts 
    WHERE is_active = TRUE
    ORDER BY priority DESC, created_at DESC
    LIMIT 5
");
$system_alerts = $alerts_stmt->fetchAll();

// Activité récente
$activity_stmt = $pdo->query("
    SELECT * FROM admin_logs 
    ORDER BY created_at DESC 
    LIMIT 10
");
$recent_activity = $activity_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            color: #333;
        }
        
        .navbar {
            background: #343a40;
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
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .dashboard-header h1 {
            color: #343a40;
            margin: 0;
        }
        
        .timeframe-selector {
            display: flex;
            gap: 0.5rem;
            background: white;
            padding: 0.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .timeframe-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .timeframe-btn.active {
            background: #343a40;
            color: white;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .action-btn.primary {
            background: #343a40;
            color: white;
        }
        
        .action-btn.primary:hover {
            background: #23272b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 58, 64, 0.3);
        }
        
        .action-btn.secondary {
            background: #6c757d;
            color: white;
        }
        
        .action-btn.secondary:hover {
            background: #5a6268;
        }
        
        .action-btn.success {
            background: #28a745;
            color: white;
        }
        
        .action-btn.success:hover {
            background: #218838;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 4px solid #343a40;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-title {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-icon {
            font-size: 1.5rem;
            color: #343a40;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #343a40;
            margin-bottom: 0.5rem;
        }
        
        .stat-change {
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .stat-change.positive {
            color: #28a745;
        }
        
        .stat-change.negative {
            color: #dc3545;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 1200px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .chart-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }
        
        .chart-card h3 {
            color: #343a40;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .alerts-section {
            margin-bottom: 2rem;
        }
        
        .alert-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .alert-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #343a40;
        }
        
        .alert-priority {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }
        
        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .priority-low {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .activity-timeline {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }
        
        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .activity-time {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .export-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">👑 BloodLink Admin</div>
            <div class="user-info">Administrateur : <?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php">Dashboard Simple</a>
            <a href="advanced-dashboard.php" style="background: rgba(255,255,255,0.2);">Dashboard Avancé</a>
            <a href="reports.php">Rapports</a>
            <a href="system.php">Système</a>
            <?php include_once __DIR__ . '/../includes/notification-bell.php'; ?>
            <a href="../public/logout.php" class="logout-btn">Déconnexion</a>
        </div>
    </nav>

    <div class="container">
        <!-- En-tête -->
        <div class="dashboard-header">
            <h1>📊 Tableau de bord Administratif Avancé</h1>
            
            <div class="timeframe-selector">
                <a href="?timeframe=day" class="timeframe-btn <?php echo $timeframe === 'day' ? 'active' : ''; ?>">
                    Aujourd'hui
                </a>
                <a href="?timeframe=week" class="timeframe-btn <?php echo $timeframe === 'week' ? 'active' : ''; ?>">
                    7 jours
                </a>
                <a href="?timeframe=month" class="timeframe-btn <?php echo $timeframe === 'month' ? 'active' : ''; ?>">
                    30 jours
                </a>
                <a href="?timeframe=year" class="timeframe-btn <?php echo $timeframe === 'year' ? 'active' : ''; ?>">
                    1 an
                </a>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="quick-actions">
            <a href="reports.php?generate=donors" class="action-btn primary">
                📊 Générer rapport
            </a>
            <a href="export.php?type=csv" class="action-btn secondary">
                📥 Exporter données
            </a>
            <a href="system-alerts.php" class="action-btn secondary">
            ⚠️ Alertes système
            </a>
            <a href="backup.php" class="action-btn success">
                💾 Sauvegarde
            </a>
            <a href="settings.php" class="action-btn secondary">
                ⚙️ Paramètres
            </a>
        </div>
        
        <!-- Statistiques principales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Donneurs actifs</div>
                    <div class="stat-icon">👤</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['active_donors']); ?></div>
                <div class="stat-change positive">
                    <span>▲</span>
                    <span>Total : <?php echo number_format($stats['total_donors']); ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Hôpitaux actifs</div>
                    <div class="stat-icon">🏥</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['active_hospitals']); ?></div>
                <div class="stat-change positive">
                    <span>▲</span>
                    <span>Total : <?php echo number_format($stats['total_hospitals']); ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Demandes actives</div>
                    <div class="stat-icon">🩸</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['active_requests']); ?></div>
                <div class="stat-change">
                    <span>Total : <?php echo number_format($stats['total_requests']); ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Dons complétés</div>
                    <div class="stat-icon">❤️</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['completed_donations']); ?></div>
                <div class="stat-change positive">
                    <span>▲</span>
                    <span>Total : <?php echo number_format($stats['total_donations']); ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Temps réponse moyen</div>
                    <div class="stat-icon">⏱️</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['avg_response_time'], 1); ?>h</div>
                <div class="stat-change">
                    <span>Demande → Réponse</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Taux de conversion</div>
                    <div class="stat-icon">📈</div>
                </div>
                <div class="stat-value"><?php echo $stats['conversion_rate']; ?>%</div>
                <div class="stat-change">
                    <span>Demande → Don</span>
                </div>
            </div>
        </div>
        
        <!-- Graphiques -->
        <div class="charts-grid">
            <!-- Graphique 1 : Activité quotidienne -->
            <div class="chart-card">
                <h3>📈 Activité quotidienne</h3>
                <div class="chart-container">
                    <canvas id="dailyActivityChart"></canvas>
                </div>
            </div>
            
            <!-- Graphique 2 : Distribution groupes sanguins -->
            <div class="chart-card">
                <h3>🩸 Distribution des groupes sanguins</h3>
                <div class="chart-container">
                    <canvas id="bloodGroupChart"></canvas>
                </div>
            </div>
            
            <!-- Graphique 3 : Statut des demandes -->
            <div class="chart-card">
                <h3>📋 Statut des demandes</h3>
                <div class="chart-container">
                    <canvas id="requestsStatusChart"></canvas>
                </div>
            </div>
            
            <!-- Graphique 4 : Top villes -->
            <div class="chart-card">
                <h3>📍 Top 10 villes actives</h3>
                <div class="chart-container">
                    <canvas id="citiesChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Alertes système -->
        <div class="alerts-section">
            <h2 style="color: #343a40; margin-bottom: 1rem;">⚠️ Alertes système</h2>
            
            <?php if (empty($system_alerts)): ?>
                <div class="alert-card">
                    <div style="text-align: center; padding: 2rem; color: #28a745;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                        <h3>Système stable</h3>
                        <p>Aucune alerte système active.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($system_alerts as $alert): 
                    $priority_class = [
                        1 => 'priority-low',
                        2 => 'priority-low',
                        3 => 'priority-medium',
                        4 => 'priority-high',
                        5 => 'priority-high'
                    ][$alert['priority']];
                    
                    $priority_text = [
                        1 => 'Faible',
                        2 => 'Faible',
                        3 => 'Moyenne',
                        4 => 'Haute',
                        5 => 'Critique'
                    ][$alert['priority']];
                ?>
                    <div class="alert-card">
                        <div class="alert-header">
                            <div class="alert-title"><?php echo htmlspecialchars($alert['title']); ?></div>
                            <div class="alert-priority <?php echo $priority_class; ?>">
                                Priorité : <?php echo $priority_text; ?>
                            </div>
                        </div>
                        <p><?php echo htmlspecialchars($alert['message']); ?></p>
                        <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                            <?php if ($alert['action_url']): ?>
                                <a href="<?php echo htmlspecialchars($alert['action_url']); ?>" class="action-btn primary">
                                    🔧 Résoudre
                                </a>
                            <?php endif; ?>
                            <button class="action-btn secondary" 
                                    onclick="markAlertResolved(<?php echo $alert['id']; ?>)">
                                ✅ Marquer comme résolu
                            </button>
                        </div>
                        <small style="color: #6c757d; display: block; margin-top: 0.5rem;">
                            Créée le <?php echo date('d/m/Y H:i', strtotime($alert['created_at'])); ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Activité récente et rapports -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <!-- Activité récente -->
            <div class="activity-timeline">
                <h3 style="color: #343a40; margin-bottom: 1rem;">🕐 Activité récente</h3>
                
                <?php if (empty($recent_activity)): ?>
                    <div class="empty-state" style="padding: 1rem;">
                        Aucune activité récente
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php 
                                $icons = [
                                    'create' => '➕',
                                    'update' => '✏️',
                                    'delete' => '🗑️',
                                    'validate' => '✅',
                                    'reject' => '❌',
                                    'block' => '⛔'
                                ];
                                echo $icons[$activity['action']] ?? '📝';
                                ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php echo htmlspecialchars($activity['action']); ?> - 
                                    <?php echo htmlspecialchars($activity['target_type']); ?> #<?php echo $activity['target_id']; ?>
                                </div>
                                <p style="font-size: 0.9rem; color: #666; margin-bottom: 0.25rem;">
                                    <?php echo htmlspecialchars($activity['details']); ?>
                                </p>
                                <div class="activity-time">
                                    <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="activity-logs.php" class="action-btn secondary">Voir tous les logs</a>
                </div>
            </div>
            
            <!-- Rapports rapides -->
            <div class="chart-card">
                <h3>📄 Rapports rapides</h3>
                <div style="margin-top: 1rem;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Rapport</th>
                                <th>Dernière génération</th>
                                <th>Téléchargements</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $reports_stmt = $pdo->query("
                                SELECT r.*, u.full_name as generated_by_name
                                FROM admin_reports r
                                JOIN users u ON r.generated_by = u.id
                                ORDER BY r.generated_at DESC
                                LIMIT 5
                            ");
                            $recent_reports = $reports_stmt->fetchAll();
                            
                            if (empty($recent_reports)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #6c757d;">
                                        Aucun rapport généré
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_reports as $report): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($report['title']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($report['generated_at'])); ?></td>
                                        <td><?php echo $report['download_count']; ?></td>
                                        <td>
                                            <?php if ($report['file_path']): ?>
                                                <a href="<?php echo htmlspecialchars($report['file_path']); ?>" 
                                                   class="export-btn" 
                                                   style="padding: 0.25rem 0.5rem; font-size: 0.9rem;">
                                                    📥
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 1rem;">
                    <a href="reports.php" class="action-btn primary">Générer un nouveau rapport</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Graphique 1 : Activité quotidienne
        const dailyCtx = document.getElementById('dailyActivityChart').getContext('2d');
        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($chart_data['daily_stats'], 'stat_date')); ?>,
                datasets: [
                    {
                        label: 'Nouveaux donneurs',
                        data: <?php echo json_encode(array_column($chart_data['daily_stats'], 'new_donors')); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Nouvelles demandes',
                        data: <?php echo json_encode(array_column($chart_data['daily_stats'], 'new_requests')); ?>,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Dons complétés',
                        data: <?php echo json_encode(array_column($chart_data['daily_stats'], 'completed_donations')); ?>,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // Graphique 2 : Groupes sanguins
        const bloodCtx = document.getElementById('bloodGroupChart').getContext('2d');
        const bloodChart = new Chart(bloodCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($chart_data['blood_group_distribution'], 'name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($chart_data['blood_group_distribution'], 'count')); ?>,
                    backgroundColor: [
                        '#dc3545', '#ff6b6b', '#ffc107', '#28a745',
                        '#007bff', '#6f42c1', '#20c997', '#fd7e14'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((context.raw / total) * 100);
                                return `${context.label}: ${context.raw} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Graphique 3 : Statut des demandes
        const requestsCtx = document.getElementById('requestsStatusChart').getContext('2d');
        const requestsChart = new Chart(requestsCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($chart_data['request_status'], 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($chart_data['request_status'], 'count')); ?>,
                    backgroundColor: [
                        '#ffc107', // pending
                        '#28a745', // fulfilled
                        '#6c757d'  // cancelled
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
        
        // Graphique 4 : Top villes
        const citiesCtx = document.getElementById('citiesChart').getContext('2d');
        const citiesChart = new Chart(citiesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($chart_data['city_distribution'], 'city')); ?>,
                datasets: [{
                    label: 'Activité',
                    data: <?php echo json_encode(array_column($chart_data['city_distribution'], 'count')); ?>,
                    backgroundColor: '#343a40',
                    borderColor: '#23272b',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Fonction pour marquer une alerte comme résolue
        function markAlertResolved(alertId) {
            if (confirm('Marquer cette alerte comme résolue ?')) {
                fetch('ajax/mark-alert-resolved.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + alertId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur : ' + data.error);
                    }
                })
                .catch(error => {
                    alert('Erreur réseau');
                    console.error('Error:', error);
                });
            }
        }
        
        // Auto-refresh du dashboard toutes les 5 minutes
        setInterval(() => {
            // Rafraîchir les graphiques
            fetch('ajax/get-dashboard-stats.php?timeframe=<?php echo $timeframe; ?>')
                .then(response => response.json())
                .then(data => {
                    // Mettre à jour les statistiques
                    // (Implémentation avancée)
                })
                .catch(error => console.error('Error:', error));
        }, 300000); // 5 minutes
    </script>
</body>
</html>