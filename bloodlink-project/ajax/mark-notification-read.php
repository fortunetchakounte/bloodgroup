<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit();
}

$notification_id = (int)($_POST['id'] ?? 0);

if ($notification_id > 0) {
    $success = markNotificationAsRead($pdo, $notification_id, $_SESSION['user_id']);
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
}
?>