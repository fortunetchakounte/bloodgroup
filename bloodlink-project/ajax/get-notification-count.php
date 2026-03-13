<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['count' => 0]);
    exit();
}

$count = countUnreadNotifications($pdo, $_SESSION['user_id'], $_SESSION['user_role']);
echo json_encode(['count' => $count]);
?>