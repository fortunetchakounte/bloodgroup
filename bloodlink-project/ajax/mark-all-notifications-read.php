<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit();
}

$success = markAllNotificationsAsRead($pdo, $_SESSION['user_id'], $_SESSION['user_role']);
echo json_encode(['success' => $success]);
?>