<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier que c'est un admin
if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../public/login.php');
}

$page_title = "Gestion Système";
$message = '';
$message_type = '';

// Actions système
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'clear_cache':
            // Supprimer les caches
            $cache_dir = '../cache/';
            if (is_dir($cache_dir)) {
                array_map('unlink', glob($cache_dir . "*"));
                $message = "✅ Cache nettoyé";
                $message_type = 'success';
            }
            break;
            
        case 'optimize_tables':
            try {
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    $pdo->exec("OPTIMIZE TABLE $table");
                }
                $message = "✅ Tables optimisées";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "❌ Erreur : " . $e->getMessage();
                $message_type = 'danger';
            }
            break;
            
        case 'create_backup':
            $backup_file = createDatabaseBackup($pdo);
            if ($backup_file) {
                $message = "✅ Sauvegarde créée : " . basename($backup_file);
                $message_type = 'success';
            } else {
                $message = "❌ Erreur lors de la sauvegarde";
                $message_type = 'danger';
            }
            break;
            
        case 'cleanup_old_data':
            // Supprimer les données anciennes
            $deleted = $pdo->exec("
                DELETE FROM notifications 
                WHERE expires_at < NOW() - INTERVAL 30 DAY
            ");
            $message = "✅ $deleted notifications anciennes supprimées";
            $message_type = 'success';
            break;
    }
}

// Fonction de sauvegarde
function createDatabaseBackup($pdo) {
    $backup_dir = '../backups/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $backup_file = $backup_dir . 'backup_' . date('Ymd_His') . '.sql';
    
    // Récupérer toutes les tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $output = "-- BloodLink Database Backup\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n--\n\n";
    
    foreach ($tables as $table) {
        // Structure de la table
        $output .= "--\n-- Structure de la table `$table`\n--\n";
        $create_table = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $output .= $create_table['Create Table'] . ";\n\n";
        
        // Données de la table
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $output .= "--\n-- Données de la table `$table`\n--\n";
            
            foreach ($rows as $row) {
                $values = array_map(function($value) use ($pdo) {
                    if ($value === null) return 'NULL';
                    return $pdo->quote($value);
                }, $row);
                
                $output .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
            }
            $output .= "\n";
        }
    }
    
    if (file_put_contents($backup_file, $output)) {
        // Compresser le fichier
        if (function_exists('gzencode')) {
            $compressed = gzencode(file_get_contents($backup_file), 9);
            $compressed_file = $backup_file . '.gz';
            file_put_contents($compressed_file, $compressed);
            unlink($backup_file);
            return $compressed_file;
        }
        return $backup_file;
    }
    
    return false;
}

// Informations système
$system_info = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu',
    'database_size' => getDatabaseSize($pdo),
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_hospitals' => $pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn(),
    'disk_usage' => getDiskUsage(),
    'memory_usage' => getMemoryUsage()
];

function getDatabaseSize($pdo) {
    $stmt = $pdo->query("
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ");
    $result = $stmt->fetch();
    return $result['size_mb'] ?? '0.00';
}

function getDiskUsage() {
    $total = disk_total_space(__DIR__);
    $free = disk_free_space(__DIR__);
    $used = $total - $free;
    
    return [
        'total' => round($total / (1024 * 1024 * 1024), 2), // Go
        'used' => round($used / (1024 * 1024 * 1024), 2),
        'percent' => round(($used / $total) * 100, 1)
    ];
}

function getMemoryUsage() {
    $memory_limit = ini_get('memory_limit');
    $memory_usage = memory_get_usage(true);
    $memory_peak = memory_get_peak_usage(true);
    
    return [
        'limit' => $memory_limit,
        'usage' => round($memory_usage / 1024 / 1024, 2), // Mo
        'peak' => round($memory_peak / 1024 / 1024, 2),
        'percent' => round(($memory_usage / convertToBytes($memory_limit)) * 100, 1)
    ];
}

function convertToBytes($value) {
    $unit = strtoupper(substr($value, -1));
    $number = (int)substr($value, 0, -1);
    
    switch ($unit) {
        case 'G': return $number * 1024 * 1024 * 1024;
        case 'M': return $number * 1024 * 1024;
        case 'K': return $number * 1024;
        default: return $number;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        /* Styles similaires aux autres pages */
        /* ... */
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">⚙️ BloodLink System</div>
            <div class="user-info">Administrateur : <?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
        </div>
        <div class="nav-right">
            <a href="advanced-dashboard.php">Dashboard</a>
            <a href="reports.php">Rapports</a>
            <a href="system.php" style="background: rgba(255,255,255,0.2);">Système</a>
            <a href="../public/logout.php" class="logout-btn">Déconnexion</a>
        </div>
    </nav>

    <div class="container">
        <h1 style="color: #343a40; margin-bottom: 1rem;">⚙️ Gestion du système</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom: 1.5rem;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Actions système -->
        <div class="card" style="margin-bottom: 2rem;">
            <h3>🔧 Actions système</h3>
            <p style="color: #6c757d; margin-bottom: 1.5rem;">
                Outils de maintenance et de gestion du système.
            </p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <form method="POST" action="" style="margin: 0;">
                    <input type="hidden" name="action" value="clear_cache">
                    <button type="submit" class="action-btn primary" style="width: 100%;">
                        🗑️ Nettoyer le cache
                    </button>
                </form>
                
                <form method="POST" action="" style="margin: 0;">
                    <input type="hidden" name="action" value="optimize_tables">
                    <button type="submit" class="action-btn primary" style="width: 100%;"
                            onclick="return confirm('Optimiser toutes les tables de la base de données ?')">
                        ⚡ Optimiser les tables
                    </button>
                </form>
                
                <form method="POST" action="" style="margin: 0;">
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="action-btn success" style="width: 100%;">
                        💾 Créer une sauvegarde
                    </button>
                </form>
                
                <form method="POST" action="" style="margin: 0;">
                    <input type="hidden" name="action" value="cleanup_old_data">
                    <button type="submit" class="action-btn secondary" style="width: 100%;"
                            onclick="return confirm('Supprimer les données anciennes ?')">
                        🧹 Nettoyer données anciennes
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Informations système -->
        <div class="card" style="margin-bottom: 2rem;">
            <h3>📊 Informations système</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <!-- Serveur -->
                <div>
                    <h4 style="color: #343a40; margin-bottom: 1rem;">🖥️ Serveur</h4>
                    <table style="width: 100%;">
                        <tr>
                            <td style="padding: 0.5rem 0; color: #666;">PHP Version</td>
                            <td style="padding: 0.5rem 0; font-weight: 500;"><?php echo $system_info['php_version']; ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0; color: #666;">Serveur</td>
                            <td style="padding: 0.5rem 0; font-weight: 500;"><?php echo $system_info['server_software']; ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem 0; color: #666;">Base de données</td>
                            <td style="padding: 0.5rem 0; font-weight: 500;"><?php echo $system_info['database_size']; ?> MB</td>
                        </tr>
                    </table>
                </div>
                
                <!-- Utilisation disque -->
                <div>
                    <h4 style="color: #343a40; margin-bottom: 1rem;">💾 Disque</h4>
                    <div style="margin-bottom: 0.5rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span>Utilisation : <?php echo $system_info['disk_usage']['percent']; ?>%</span>
                            <span><?php echo $system_info['disk_usage']['used']; ?>Go / <?php echo $system_info['disk_usage']['total']; ?>Go</span>
                        </div>
                        <div style="height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo $system_info['disk_usage']['percent']; ?>%; background: #28a745;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Mémoire -->
                <div>
                    <h4 style="color: #343a40; margin-bottom: 1rem;">🧠 Mémoire</h4>
                    <div style="margin-bottom: 0.5rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span>Utilisation : <?php echo $system_info['memory_usage']['percent']; ?>%</span>
                            <span><?php echo $system_info['memory_usage']['usage']; ?>Mo / <?php echo $system_info['memory_usage']['limit']; ?></span>
                        </div>
                        <div style="height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo min($system_info['memory_usage']['percent'], 100); ?>%; background: #007bff;"></div>
                        </div>
                    </div>
                    <div style="font-size: 0.9rem; color: #666;">
                        Pic : <?php echo $system_info['memory_usage']['peak']; ?>Mo
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Logs système -->
        <div class="card">
            <h3>📝 Logs système récents</h3>
            
            <?php
            $logs_stmt = $pdo->query("
                SELECT * FROM admin_logs 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $system_logs = $logs_stmt->fetchAll();
            ?>
            
            <?php if (empty($system_logs)): ?>
                <div class="empty-state" style="padding: 1rem;">
                    Aucun log système
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>Cible</th>
                                <th>Détails</th>
                                <th>Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($system_logs as $log): ?>
                                <tr>
                                    <td style="white-space: nowrap;">
                                        <?php echo date('d/m H:i', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($log['target_type']); ?> 
                                        #<?php echo $log['target_id']; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                                    <td>
                                        <?php 
                                        $admin_stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                                        $admin_stmt->execute([$log['admin_id']]);
                                        $admin = $admin_stmt->fetch();
                                        echo htmlspecialchars($admin['full_name'] ?? 'Inconnu');
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="export-logs.php" class="action-btn secondary">
                        📥 Exporter les logs
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>