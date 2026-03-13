<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php'; // Ajouté pour les fonctions de notification

// Check if user is a donor
if (!isLoggedIn() || getUserRole() !== 'donor') {
    header('Location: ../public/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$pdo = $GLOBALS['pdo'];

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// ==============================================
// LOCAL UTILITY FUNCTIONS
// ==============================================

function calculateNextDonationDate($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT MAX(donation_date) as last_donation
        FROM donations
        WHERE donor_id = ? AND status = 'completed'
    ");
    
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && !empty($result['last_donation'])) {
        $next_date = date('Y-m-d', strtotime($result['last_donation'] . ' +56 days'));
        return $next_date;
    }
    
    return null;
}

function getLastMessagePreview($conversation_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT message 
        FROM messages 
        WHERE conversation_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$conversation_id]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($message && !empty($message['message'])) {
        $preview = $message['message'];
        if (strlen($preview) > 50) {
            $preview = substr($preview, 0, 47) . '...';
        }
        return $preview;
    }
    
    return 'No message';
}

function timeAgo($datetime) {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return 'Never';
    }
    
    $time = strtotime($datetime);
    if ($time === false) return 'Invalid date';
    
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return "$mins min ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "$hours h ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return "$days days ago";
    } else {
        return date('d/m/Y', $time);
    }
}

function getParticipantInfoSimple($id, $type) {
    global $pdo;
    
    if (!is_numeric($id)) {
        return [
            'id' => $id,
            'type' => $type,
            'name' => 'User',
            'avatar' => null
        ];
    }
    
    try {
        if ($type == 'donor') {
            $stmt = $pdo->prepare("
                SELECT full_name as name, profile_picture as avatar
                FROM users 
                WHERE id = ? AND user_type = 'donor'
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'id' => $id,
                'type' => $type,
                'name' => !empty($result['name']) ? $result['name'] : 'Donor',
                'avatar' => $result['avatar'] ?? null
            ];
            
        } elseif ($type == 'hospital') {
            $stmt = $pdo->prepare("
                SELECT hospital_name as name, logo as avatar
                FROM hospitals 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'id' => $id,
                'type' => $type,
                'name' => !empty($result['name']) ? $result['name'] : 'Hospital',
                'avatar' => $result['avatar'] ?? null
            ];
        }
    } catch (Exception $e) {
        error_log("Error in getParticipantInfoSimple: " . $e->getMessage());
    }
    
    return [
        'id' => $id,
        'type' => $type,
        'name' => 'User',
        'avatar' => null
    ];
}

// ==============================================
// DATA RETRIEVAL
// ==============================================

// User data
try {
    $stmt = $pdo->prepare("
        SELECT u.*, bg.name as blood_group_name,
               COALESCE(ux.level, 1) as level,
               COALESCE(ux.level_title, 'New donor') as level_title,
               COALESCE(ux.current_xp, 0) as current_xp,
               COALESCE(ux.next_level_xp, 100) as next_level_xp,
               COALESCE(ux.total_xp, 0) as total_xp,
               (SELECT COUNT(*) FROM donations WHERE donor_id = u.id AND status = 'completed') as total_donations,
               (SELECT COUNT(DISTINCT hospital_id) FROM donations WHERE donor_id = u.id AND status = 'completed') as helped_hospitals,
               (SELECT COUNT(*) FROM user_badges WHERE user_id = u.id) as badge_count
        FROM users u
        LEFT JOIN blood_groups bg ON u.blood_group_id = bg.id
        LEFT JOIN user_xp ux ON u.id = ux.user_id
        WHERE u.id = ? AND u.user_type = 'donor'
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header('Location: ../public/login.php');
        exit();
    }
    
    // Calculate consecutive donations
    $consecutive_stmt = $pdo->prepare("
        SELECT COUNT(*) as consecutive_donations
        FROM donations 
        WHERE donor_id = ? 
        AND status = 'completed'
        AND donation_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    ");
    $consecutive_stmt->execute([$user_id]);
    $consecutive_result = $consecutive_stmt->fetch(PDO::FETCH_ASSOC);
    $user['consecutive_donations'] = $consecutive_result['consecutive_donations'] ?? 0;
    
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    $user = [
        'full_name' => $_SESSION['user_name'] ?? 'User',
        'blood_group_name' => 'Not specified',
        'level' => 1,
        'level_title' => 'New donor',
        'current_xp' => 0,
        'next_level_xp' => 100,
        'total_xp' => 0,
        'total_donations' => 0,
        'consecutive_donations' => 0,
        'helped_hospitals' => 0,
        'badge_count' => 0
    ];
}

// Default coordinates
$user_latitude = !empty($user['latitude']) ? (float)$user['latitude'] : 48.8566;
$user_longitude = !empty($user['longitude']) ? (float)$user['longitude'] : 2.3522;

// Urgent requests - CORRIGÉ: Utilise donation_requests au lieu de blood_requests
$urgent_requests = [];
if (!empty($user['blood_group_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT dr.*, h.hospital_name, h.latitude, h.longitude, bg.name as blood_group_name
            FROM donation_requests dr
            JOIN hospitals h ON dr.hospital_id = h.id
            JOIN blood_groups bg ON dr.blood_group_id = bg.id
            WHERE dr.status = 'pending'
            AND dr.urgency IN ('high', 'emergency')
            AND dr.blood_group_id = ?
            ORDER BY dr.created_at DESC
            LIMIT 3
        ");
        
        $stmt->execute([$user['blood_group_id']]);
        $urgent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($urgent_requests as &$request) {
            if (!empty($request['latitude']) && !empty($request['longitude'])) {
                $lat1 = deg2rad($user_latitude);
                $lon1 = deg2rad($user_longitude);
                $lat2 = deg2rad((float)$request['latitude']);
                $lon2 = deg2rad((float)$request['longitude']);
                
                $dlat = $lat2 - $lat1;
                $dlon = $lon2 - $lon1;
                
                $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                $request['distance_km'] = 6371 * $c;
            } else {
                $request['distance_km'] = 0;
            }
        }
        
    } catch (PDOException $e) {
        error_log("Error fetching urgent requests: " . $e->getMessage());
    }
}

// Recent badges
$recent_badges = [];
try {
    $stmt = $pdo->prepare("
        SELECT b.*, ub.earned_at
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.user_id = ?
        ORDER BY ub.earned_at DESC
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $recent_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching badges: " . $e->getMessage());
}

// Unread messages
$unread_messages = 0;
try {
    // Check if messages table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM messages m
            WHERE m.conversation_id IN (
                SELECT conversation_id 
                FROM conversation_participants 
                WHERE user_id = ? AND user_type = 'donor'
            )
            AND m.is_read = FALSE
            AND m.sender_id != ?
        ");
        $stmt->execute([$user_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $unread_messages = $result['count'] ?? 0;
    }
} catch (PDOException $e) {
    error_log("Error fetching messages: " . $e->getMessage());
}

// Upcoming appointments
$upcoming_appointments = [];
try {
    // Check if donation_appointments table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'donation_appointments'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT da.*, h.hospital_name
            FROM donation_appointments da
            JOIN hospitals h ON da.hospital_id = h.id
            WHERE da.donor_id = ?
            AND da.appointment_date >= CURDATE()
            AND da.status IN ('scheduled', 'confirmed')
            ORDER BY da.appointment_date, da.appointment_time
            LIMIT 3
        ");
        $stmt->execute([$user_id]);
        $upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
}

// Recent conversations
$recent_conversations = [];
try {
    // Check if conversations table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'conversations'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT c.*
            FROM conversations c
            WHERE c.participant1_id = ? AND c.participant1_type = 'donor'
               OR c.participant2_id = ? AND c.participant2_type = 'donor'
            ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
            LIMIT 3
        ");
        $stmt->execute([$user_id, $user_id]);
        $recent_conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching conversations: " . $e->getMessage());
}

// For history
$donations = [];
$total_donations = 0;
$lives_saved = 0;
$hospitals_count = 0;
$last_donation = null;

if ($active_tab === 'history') {
    try {
        // Get donation history - CORRIGÉ: Utilise donation_requests au lieu de blood_requests
        $stmt = $pdo->prepare("
            SELECT d.*, 
                   h.hospital_name,
                   h.city as hospital_city,
                   bg.name as blood_group_name
            FROM donations d
            JOIN donation_requests dr ON d.request_id = dr.id
            JOIN hospitals h ON dr.hospital_id = h.id
            JOIN blood_groups bg ON dr.blood_group_id = bg.id
            WHERE d.donor_id = ?
            ORDER BY d.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$user_id]);
        $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Statistics
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM donations WHERE donor_id = ? AND status = 'completed'");
        $stmt->execute([$user_id]);
        $total_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_donations = $total_stats['total'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT MAX(donation_date) as last_donation FROM donations WHERE donor_id = ? AND status = 'completed'");
        $stmt->execute([$user_id]);
        $last_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $last_donation = $last_stats['last_donation'] ?? null;
        
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT hospital_id) as hospitals_count FROM donations WHERE donor_id = ? AND status = 'completed'");
        $stmt->execute([$user_id]);
        $hospitals_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $hospitals_count = $hospitals_stats['hospitals_count'] ?? 0;
        
        $lives_saved = $total_donations * 3;
        
    } catch (PDOException $e) {
        error_log("Error fetching history: " . $e->getMessage());
    }
}

// Statistics for dashboard
$stats = [
    'total_donations' => $user['total_donations'] ?? 0,
    'consecutive_donations' => $user['consecutive_donations'] ?? 0,
    'helped_hospitals' => $user['helped_hospitals'] ?? 0,
    'next_donation_date' => calculateNextDonationDate($user_id),
    'lives_saved' => ($user['total_donations'] ?? 0) * 3
];

// Calculate XP
$current_xp = $user['current_xp'] ?? 0;
$next_xp = $user['next_level_xp'] ?? 100;
$xp_percentage = $next_xp > 0 ? min(100, ($current_xp / $next_xp) * 100) : 0;

// Safe data for display
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$full_name = htmlspecialchars($user['full_name'] ?? $_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$blood_group = htmlspecialchars($user['blood_group_name'] ?? 'Not specified', ENT_QUOTES, 'UTF-8');
$level_title = htmlspecialchars($user['level_title'] ?? 'New donor', ENT_QUOTES, 'UTF-8');

$page_title = ($active_tab === 'history') ? "My Donation History" : "Dashboard - Donor";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - BloodLink</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* NAVIGATION */
        .navbar {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info {
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .nav-right a {
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            transition: all 0.3s;
            font-weight: 500;
            position: relative;
        }
        
        .nav-right a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        .nav-right a.active {
            background: rgba(255,255,255,0.2);
            font-weight: 600;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ffc107;
            color: #333;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* DASHBOARD GRID - DISPLAYED ONLY ON DASHBOARD TAB */
        <?php if ($active_tab === 'dashboard'): ?>
        .dashboard-container {
            display: grid;
            grid-template-columns: 300px 1fr 350px;
            gap: 1.5rem;
            padding: 2rem;
            max-width: 1800px;
            margin: 0 auto;
            min-height: calc(100vh - 80px);
        }
        <?php endif; ?>
        
        /* HISTORY PAGE STYLES */
        <?php if ($active_tab === 'history'): ?>
        .history-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .history-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .history-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .history-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            max-width: 800px;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .history-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
        }
        
        .section-header h2 {
            color: #333;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header h2 i {
            color: #667eea;
        }
        
        .timeline {
            position: relative;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, #667eea, #764ba2);
            border-radius: 2px;
        }
        
        .timeline-item {
            display: flex;
            justify-content: center;
            margin-bottom: 3rem;
            position: relative;
        }
        
        .timeline-content {
            background: white;
            border-radius: 15px;
            padding: 1.8rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            width: calc(50% - 40px);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }
        
        .timeline-item:nth-child(odd) .timeline-content {
            margin-right: 40px;
        }
        
        .timeline-item:nth-child(even) .timeline-content {
            margin-left: 40px;
        }
        
        .timeline-content:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .timeline-marker {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: 4px solid white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        }
        
        .timeline-item:nth-child(odd) .timeline-marker {
            right: -10px;
        }
        
        .timeline-item:nth-child(even) .timeline-marker {
            left: -10px;
        }
        
        .donation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .donation-date {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
        }
        
        .donation-status {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-scheduled {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .donation-body h3 {
            color: #333;
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        
        .donation-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        
        .detail-label {
            color: #666;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .detail-value {
            color: #333;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 992px) {
            .timeline::before {
                left: 30px;
            }
            
            .timeline-content {
                width: calc(100% - 80px);
                margin-left: 80px !important;
                margin-right: 0 !important;
            }
            
            .timeline-marker {
                left: 20px !important;
                right: auto !important;
            }
        }
        <?php endif; ?>
        
        /* LEFT SIDEBAR - PROFILE */
        .profile-sidebar {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .profile-header {
            text-align: center;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .profile-blood {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .xp-progress {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .xp-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #ffc107, #fd7e14);
            border-radius: 4px;
        }
        
        .level-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #666;
        }
        
        .badges-section {
            margin-top: 1rem;
        }
        
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .badge-item {
            text-align: center;
        }
        
        .badge-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 1.5rem;
            border: 2px solid #e9ecef;
        }
        
        /* CENTRAL CONTENT */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #4e54c8, #8f94fb);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .stat-card-dashboard {
            background: rgba(255,255,255,0.2);
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .urgent-requests {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .requests-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .request-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #dc3545;
        }
        
        .request-info h4 {
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .request-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: #666;
        }
        
        /* MAP */
        .map-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            height: 400px;
        }
        
        #dashboardMap {
            width: 100%;
            height: 100%;
            border-radius: 10px;
        }
        
        /* RIGHT SIDEBAR */
        .right-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .widget {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .widget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .widget-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .messages-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .message-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
        }
        
        .message-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .message-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #dc3545;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .message-content {
            flex: 1;
        }
        
        .message-sender {
            font-weight: 600;
            color: #333;
        }
        
        .message-preview {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: #999;
        }
        
        /* APPOINTMENTS */
        .appointments-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .appointment-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .appointment-date {
            text-align: center;
            background: #dc3545;
            color: white;
            padding: 0.5rem;
            border-radius: 8px;
            min-width: 60px;
        }
        
        .appointment-day {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .appointment-month {
            font-size: 0.8rem;
        }
        
        .appointment-details h4 {
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .appointment-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-scheduled {
            background: #fff3cd;
            color: #856404;
        }
        
        /* QUICK ACTIONS */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .action-card {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }
        
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        /* BUTTONS */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #dc3545;
            color: white;
        }
        
        .btn-primary:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #dc3545;
            color: #dc3545;
        }
        
        .btn-outline:hover {
            background: #dc3545;
            color: white;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1400px) {
            .dashboard-container {
                grid-template-columns: 280px 1fr 320px;
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 1200px) {
            .dashboard-container {
                grid-template-columns: 1fr;
                max-height: none;
            }
            
            .navbar {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            .nav-right {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid,
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-sidebar {
                padding: 1.5rem;
            }
            
            .map-section {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- NAVIGATION -->
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">
                <i class="fas fa-heartbeat"></i>
                BloodLink
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <span><?php echo $user_name; ?> (Donor)</span>
            </div>
        </div>
        <div class="nav-right">
            <a href="?tab=dashboard" class="<?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="profile.php">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="requests.php">
                <i class="fas fa-bell"></i> Requests
                <?php if(count($urgent_requests) > 0): ?>
                    <span class="notification-badge"><?php echo count($urgent_requests); ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=history" class="<?php echo $active_tab === 'history' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> History
            </a>
            <a href="appointments.php">
                <i class="fas fa-calendar-alt"></i> Appointments
            </a>
            <?php if (isset($recent_conversations) && !empty($recent_conversations)): ?>
            <a href="../messages/chat.php">
                <i class="fas fa-comments"></i> Messages
                <?php if($unread_messages > 0): ?>
                    <span class="notification-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <a href="../public/logout.php" style="border: 2px solid white; color: white;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <?php if ($active_tab === 'dashboard'): ?>
        <!-- DASHBOARD GRID -->
        <div class="dashboard-container">
            <!-- LEFT SIDEBAR - PROFILE -->
            <div class="profile-sidebar">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                    </div>
                    <h2 class="profile-name"><?php echo $full_name; ?></h2>
                    <div class="profile-blood">
                        Group <?php echo $blood_group; ?>
                    </div>
                </div>
                
                <!-- LEVEL & XP -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600; color: #333;">Level <?php echo $user['level'] ?? 1; ?></span>
                        <span style="font-size: 0.9rem; color: #666;"><?php echo $level_title; ?></span>
                    </div>
                    <div class="xp-progress">
                        <div class="xp-progress-bar" style="width: <?php echo $xp_percentage; ?>%"></div>
                    </div>
                    <div class="level-info">
                        <span><?php echo $current_xp; ?> XP</span>
                        <span><?php echo $next_xp; ?> XP</span>
                    </div>
                </div>
                
                <!-- QUICK STATS -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; text-align: center;">
                    <div>
                        <div style="font-size: 1.5rem; font-weight: bold; color: #dc3545;"><?php echo $stats['total_donations']; ?></div>
                        <div style="font-size: 0.85rem; color: #666;">Donations</div>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: bold; color: #dc3545;"><?php echo $stats['helped_hospitals']; ?></div>
                        <div style="font-size: 0.85rem; color: #666;">Hospitals</div>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: bold; color: #dc3545;"><?php echo $user['badge_count'] ?? 0; ?></div>
                        <div style="font-size: 0.85rem; color: #666;">Badges</div>
                    </div>
                </div>
                
                <!-- BADGES -->
                <?php if(!empty($recent_badges)): ?>
                <div class="badges-section">
                    <h3 style="font-size: 1rem; color: #333; margin-bottom: 1rem;">Recent badges</h3>
                    <div class="badges-grid">
                        <?php foreach($recent_badges as $badge): ?>
                        <div class="badge-item">
                            <div class="badge-icon" style="background: <?php echo !empty($badge['color']) ? htmlspecialchars($badge['color']) : '#dc3545'; ?>; color: white;">
                                <?php echo !empty($badge['icon']) ? htmlspecialchars($badge['icon']) : '🏆'; ?>
                            </div>
                            <div style="font-size: 0.75rem; margin-top: 0.25rem; font-weight: 500;">
                                <?php echo !empty($badge['name']) ? htmlspecialchars($badge['name']) : 'Badge'; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- NEXT DONATION -->
                <?php if($stats['next_donation_date']): ?>
                <div style="background: #e9f7ef; padding: 1rem; border-radius: 10px; border-left: 4px solid #28a745;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <i class="fas fa-calendar-check" style="color: #28a745;"></i>
                        <span style="font-weight: 600; color: #155724;">Next donation eligible</span>
                    </div>
                    <div style="font-size: 0.9rem; color: #666;">
                        From <strong><?php echo date('d/m/Y', strtotime($stats['next_donation_date'])); ?></strong>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- CENTRAL CONTENT -->
            <div class="main-content">
                <!-- WELCOME CARD -->
                <div class="welcome-card">
                    <h2 style="font-size: 1.8rem; margin-bottom: 0.5rem;">Welcome, <?php echo $user_name; ?>!</h2>
                    <p style="opacity: 0.9; margin-bottom: 1.5rem;">Your commitment saves lives. Thank you for being part of our community.</p>
                    
                    <div class="stats-grid">
                        <div class="stat-card-dashboard">
                            <div class="stat-number"><?php echo $stats['total_donations']; ?></div>
                            <div class="stat-label">Donations made</div>
                        </div>
                        <div class="stat-card-dashboard">
                            <div class="stat-number"><?php echo $stats['lives_saved']; ?></div>
                            <div class="stat-label">Lives saved</div>
                        </div>
                        <div class="stat-card-dashboard">
                            <div class="stat-number"><?php echo count($urgent_requests); ?></div>
                            <div class="stat-label">Urgent near you</div>
                        </div>
                        <div class="stat-card-dashboard">
                            <div class="stat-number"><?php echo $stats['consecutive_donations']; ?></div>
                            <div class="stat-label">Consecutive</div>
                        </div>
                    </div>
                </div>
                
                <!-- URGENT REQUESTS -->
                <div class="urgent-requests">
                    <div class="widget-header">
                        <h3 class="widget-title"><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Urgent requests nearby</h3>
                        <a href="requests.php" class="btn btn-primary btn-small">View all</a>
                    </div>
                    
                    <?php if(!empty($urgent_requests)): ?>
                    <div class="requests-list">
                        <?php foreach($urgent_requests as $request): ?>
                        <div class="request-item">
                            <div class="request-info">
                                <h4><?php echo htmlspecialchars($request['hospital_name'] ?? 'Hospital'); ?></h4>
                                <div class="request-meta">
                                    <span>Group <?php echo htmlspecialchars($request['blood_group_name'] ?? ''); ?></span>
                                    <span><?php echo isset($request['distance_km']) ? round($request['distance_km'], 1) : '0'; ?> km</span>
                                    <span><?php echo timeAgo($request['created_at'] ?? ''); ?></span>
                                </div>
                            </div>
                            <a href="view-request.php?id=<?php echo $request['id'] ?? 0; ?>" class="btn btn-primary btn-small">
                                Respond
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: #666;">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: #28a745; margin-bottom: 1rem;"></i>
                        <p>No urgent requests nearby</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- MAP -->
                <div class="map-section">
                    <div class="widget-header">
                        <h3 class="widget-title"><i class="fas fa-map-marker-alt" style="color: #4e54c8;"></i> Requests map</h3>
                        <button onclick="refreshLocation()" class="btn btn-outline btn-small">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <div id="dashboardMap"></div>
                </div>
            </div>
            
            <!-- RIGHT SIDEBAR -->
            <div class="right-sidebar">
                <!-- MESSAGES -->
                <?php if (!empty($recent_conversations)): ?>
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title"><i class="fas fa-comments" style="color: #6a11cb;"></i> Recent messages</h3>
                        <a href="../messages/chat.php" class="btn btn-outline btn-small">View all</a>
                    </div>
                    
                    <div class="messages-list">
                        <?php foreach($recent_conversations as $conv): 
                            $other_id = ($conv['participant1_id'] == $user_id && isset($conv['participant1_type']) && $conv['participant1_type'] == 'donor') 
                                ? $conv['participant2_id'] 
                                : $conv['participant1_id'];
                            $other_type = ($conv['participant1_id'] == $user_id && isset($conv['participant1_type']) && $conv['participant1_type'] == 'donor') 
                                ? $conv['participant2_type'] 
                                : $conv['participant1_type'];
                            
                            $other_info = getParticipantInfoSimple($other_id, $other_type);
                            $last_message = getLastMessagePreview($conv['id']);
                        ?>
                        <a href="../messages/chat.php?conversation=<?php echo $conv['id']; ?>" class="message-item">
                            <div class="message-avatar">
                                <?php echo strtoupper(substr($other_info['name'], 0, 1)); ?>
                            </div>
                            <div class="message-content">
                                <div class="message-sender"><?php echo htmlspecialchars($other_info['name']); ?></div>
                                <div class="message-preview"><?php echo htmlspecialchars($last_message); ?></div>
                            </div>
                            <div class="message-time"><?php echo timeAgo($conv['last_message_at'] ?? $conv['created_at'] ?? ''); ?></div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- APPOINTMENTS -->
                <?php if (!empty($upcoming_appointments)): ?>
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title"><i class="fas fa-calendar-alt" style="color: #fd7e14;"></i> Upcoming appointments</h3>
                        <a href="appointments.php" class="btn btn-outline btn-small">View all</a>
                    </div>
                    
                    <div class="appointments-list">
                        <?php foreach($upcoming_appointments as $appointment): ?>
                        <div class="appointment-item">
                            <div class="appointment-date">
                                <div class="appointment-day"><?php echo date('d', strtotime($appointment['appointment_date'])); ?></div>
                                <div class="appointment-month"><?php echo date('M', strtotime($appointment['appointment_date'])); ?></div>
                            </div>
                            <div class="appointment-details">
                                <h4><?php echo htmlspecialchars($appointment['hospital_name']); ?></h4>
                                <div style="font-size: 0.9rem; color: #666; margin: 0.25rem 0;">
                                    <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                                </div>
                                <span class="appointment-status status-<?php echo $appointment['status']; ?>">
                                    <?php echo $appointment['status'] == 'confirmed' ? 'Confirmed' : 'Scheduled'; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title"><i class="fas fa-calendar-alt" style="color: #fd7e14;"></i> Appointments</h3>
                    </div>
                    <div style="text-align: center; padding: 1rem; color: #666;">
                        <i class="fas fa-calendar-plus" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                        <p>No appointments</p>
                        <a href="schedule-appointment.php" class="btn btn-primary btn-small" style="margin-top: 0.5rem;">
                            <i class="fas fa-plus"></i> Schedule
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- QUICK ACTIONS -->
                <div class="widget">
                    <h3 class="widget-title"><i class="fas fa-bolt" style="color: #ffc107;"></i> Quick actions</h3>
                    <div class="quick-actions-grid">
                        <a href="map-requests.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>
                            Map
                        </a>
                        <a href="find-hospitals.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-hospital"></i>
                            </div>
                            Find hospital
                        </a>
                        <a href="profile.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            Edit profile
                        </a>
                        <a href="?tab=history" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            History
                        </a>
                    </div>
                </div>
                
                <!-- IMPORTANT INFO -->
                <div class="widget" style="background: linear-gradient(135deg, #2575fc 0%, #6a11cb 100%); color: white;">
                    <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-info-circle"></i> Information
                    </h3>
                    <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem;">
                        <li style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-heart" style="color: #ff6b6b;"></i>
                            <span>Donation possible every 2 months</span>
                        </li>
                        <li style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-shield-alt" style="color: #4cd964;"></i>
                            <span>One donation saves up to 3 lives</span>
                        </li>
                        <li style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-trophy" style="color: #ffd700;"></i>
                            <span>Earn badges and XP</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    
    <?php elseif ($active_tab === 'history'): ?>
        <!-- HISTORY PAGE -->
        <div class="history-container">
            <!-- Header -->
            <div class="history-header">
                <h1><i class="fas fa-history"></i> My donation history</h1>
                <p>Track your donation journey and discover the impact of your generosity. Every donation counts!</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_donations; ?></div>
                    <div class="stat-label">Donations made</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div class="stat-number"><?php echo $lives_saved; ?></div>
                    <div class="stat-label">Potential lives saved</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <div class="stat-number"><?php echo $hospitals_count; ?></div>
                    <div class="stat-label">Hospitals helped</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number">
                        <?php echo $last_donation ? date('d/m/Y', strtotime($last_donation)) : '--/--/----'; ?>
                    </div>
                    <div class="stat-label">Last donation</div>
                </div>
            </div>

            <!-- History Timeline -->
            <div class="history-content">
                <div class="section-header">
                    <h2><i class="fas fa-timeline"></i> Your donation timeline</h2>
                    <span class="donation-status status-completed">Total: <?php echo $total_donations; ?> donations</span>
                </div>

                <?php if (!empty($donations)): ?>
                    <div class="timeline">
                        <?php foreach ($donations as $index => $donation): ?>
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="donation-header">
                                        <span class="donation-date">
                                            <i class="far fa-calendar-alt"></i>
                                            <?php echo date('d/m/Y', strtotime($donation['donation_date'] ?? $donation['created_at'])); ?>
                                        </span>
                                        <span class="donation-status status-<?php echo $donation['status']; ?>">
                                            <?php 
                                            $status_labels = [
                                                'completed' => 'Completed',
                                                'scheduled' => 'Scheduled',
                                                'cancelled' => 'Cancelled',
                                                'pending' => 'Pending'
                                            ];
                                            echo $status_labels[$donation['status']] ?? $donation['status'];
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <div class="donation-body">
                                        <h3>
                                            <i class="fas fa-hospital"></i>
                                            <?php echo htmlspecialchars($donation['hospital_name'] ?? 'Hospital'); ?>
                                        </h3>
                                        <?php if (!empty($donation['hospital_city'])): ?>
                                            <p style="color: #666; margin-bottom: 1rem;">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($donation['hospital_city']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="donation-details">
                                            <div class="detail-item">
                                                <span class="detail-label">Blood group</span>
                                                <span class="detail-value">
                                                    <i class="fas fa-tint"></i>
                                                    <?php echo htmlspecialchars($donation['blood_group_name'] ?? 'Not specified'); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="detail-item">
                                                <span class="detail-label">Quantity</span>
                                                <span class="detail-value">
                                                    <i class="fas fa-weight"></i>
                                                    <?php echo htmlspecialchars($donation['quantity'] ?? '450'); ?> ml
                                                </span>
                                            </div>
                                            
                                            <?php if (!empty($donation['notes'])): ?>
                                            <div class="detail-item" style="grid-column: 1 / -1;">
                                                <span class="detail-label">Notes</span>
                                                <span class="detail-value" style="font-weight: normal;">
                                                    <?php echo htmlspecialchars($donation['notes']); ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="timeline-marker"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No donations recorded</h3>
                        <p>Start your donor journey and save lives!</p>
                        <a href="?tab=dashboard" style="
                            display: inline-block;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            padding: 1rem 2rem;
                            border-radius: 10px;
                            text-decoration: none;
                            font-weight: 600;
                            transition: transform 0.3s;
                        ">
                            <i class="fas fa-tachometer-alt"></i> Back to dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Legend -->
            <div class="history-content" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-info-circle"></i> Status legend
                </h3>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="donation-status status-completed">Completed</span>
                        <span>Successful donation</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="donation-status status-scheduled">Scheduled</span>
                        <span>Appointment scheduled</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="donation-status status-cancelled">Cancelled</span>
                        <span>Donation cancelled</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    
    <script>
        <?php if ($active_tab === 'dashboard'): ?>
        // Initialize map
        let map;
        let userMarker;
        
        function initMap() {
            const userLat = <?php echo $user_latitude; ?>;
            const userLon = <?php echo $user_longitude; ?>;
            
            // Create map
            map = L.map('dashboardMap').setView([userLat, userLon], 12);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(map);
            
            // Add user marker
            userMarker = L.marker([userLat, userLon], {
                icon: L.divIcon({
                    html: '<div style="background: #dc3545; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3);">📍</div>',
                    className: 'user-marker',
                    iconSize: [40, 40]
                })
            }).addTo(map).bindPopup('<strong>Your location</strong>');
            
            // Add urgent request markers
            <?php foreach ($urgent_requests as $request): ?>
                <?php if (!empty($request['latitude']) && !empty($request['longitude'])): ?>
                    L.marker([<?php echo $request['latitude']; ?>, <?php echo $request['longitude']; ?>], {
                        icon: L.divIcon({
                            html: '<div style="background: #ff4757; color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);">🩸</div>',
                            className: 'request-marker',
                            iconSize: [36, 36]
                        })
                    }).addTo(map).bindPopup('<strong><?php echo addslashes($request['hospital_name'] ?? 'Hospital'); ?></strong><br>Urgent - <?php echo addslashes($request['blood_group_name'] ?? ''); ?>');
                <?php endif; ?>
            <?php endforeach; ?>
            
            // Add 50km circle around user
            L.circle([userLat, userLon], {
                color: '#dc3545',
                fillColor: '#dc3545',
                fillOpacity: 0.1,
                radius: 50000
            }).addTo(map);
        }
        
        // Refresh location
        function refreshLocation() {
            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    
                    if (userMarker) {
                        userMarker.setLatLng([lat, lon]);
                        map.setView([lat, lon], 13);
                    }
                    
                    // Update location via AJAX
                    const formData = new FormData();
                    formData.append('latitude', lat);
                    formData.append('longitude', lon);
                    
                    fetch('../ajax/update-location.php', {
                        method: 'POST',
                        body: formData
                    }).then(response => {
                        if (response.ok) {
                            alert('Location updated');
                        }
                    }).catch(error => {
                        console.error('Error:', error);
                    });
                    
                }, function(error) {
                    alert('Unable to get your location');
                });
            } else {
                alert('Geolocation not supported');
            }
        }
        
        // Initialize map on load
        document.addEventListener('DOMContentLoaded', function() {
            try {
                initMap();
                
                // Handle resize
                window.addEventListener('resize', function() {
                    if (map) {
                        setTimeout(() => map.invalidateSize(), 100);
                    }
                });
            } catch (error) {
                console.error('Map initialization error:', error);
                const mapContainer = document.getElementById('dashboardMap');
                if (mapContainer) {
                    mapContainer.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">Map not available</div>';
                }
            }
        });
        <?php endif; ?>
        
        <?php if ($active_tab === 'history'): ?>
        // Timeline animation
        document.addEventListener('DOMContentLoaded', function() {
            const timelineItems = document.querySelectorAll('.timeline-item');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, {
                threshold: 0.1
            });
            
            timelineItems.forEach(item => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(item);
            });
            
            // Stats cards animation
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>