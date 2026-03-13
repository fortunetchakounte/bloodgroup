<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode([]);
    exit();
}

$limit = (int)($_GET['limit'] ?? 10);
$notifications = getUserNotifications($pdo, $_SESSION['user_id'], $_SESSION['user_role'], $limit);

// Formater pour le JSON
$formatted = [];
foreach ($notifications as $notif) {
    $formatted[] = [
        'id' => $notif['id'],
        'title' => $notif['title'],
        'message' => $notif['message'],
        'icon' => $notif['icon'],
        'type' => $notif['type'],
        'link' => $notif['link'],
        'is_read' => (bool)$notif['is_read'],
        'time_ago' => time_ago($notif['created_at'])
    ];
}

echo json_encode($formatted);

function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'À l\'instant';
    if ($diff < 3600) return floor($diff/60) . ' min';
    if ($diff < 86400) return floor($diff/3600) . ' h';
    if ($diff < 2592000) return floor($diff/86400) . ' j';
    return date('d/m/Y', $time);
}
?>