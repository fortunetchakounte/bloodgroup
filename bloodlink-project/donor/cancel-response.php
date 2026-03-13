<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || getUserRole() !== 'donor') {
    redirect('../public/login.php');
}

$response_id = (int)($_GET['id'] ?? 0);

// Vérifier et annuler
$stmt = $pdo->prepare("SELECT id FROM donations WHERE id = ? AND donor_id = ? AND status = 'scheduled'");
$stmt->execute([$response_id, $_SESSION['user_id']]);

if ($stmt->fetch()) {
    $update_stmt = $pdo->prepare("UPDATE donations SET status = 'cancelled' WHERE id = ?");
    $update_stmt->execute([$response_id]);
    
    header('Location: view-response.php?id=' . $response_id . '&msg=Cancelled');
    exit();
} else {
    header('Location: requests.php?error=InvalidResponse');
    exit();
}
?>