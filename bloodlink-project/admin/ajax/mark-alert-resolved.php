<?php
require_once '../../includes/init.php';

header('Content-Type: application/json');

if (!isLoggedIn() || getUserRole() !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit();
}

$alert_id = (int)($_POST['id'] ?? 0);

if ($alert_id > 0) {
    try {
        $stmt = $pdo->prepare("
            UPDATE system_alerts 
            SET is_active = FALSE, 
                resolved_at = NOW(), 
                resolved_by = ? 
            WHERE id = ? AND is_active = TRUE
        ");
        
        $success = $stmt->execute([$_SESSION['user_id'], $alert_id]);
        
        // Log l'action
        logAdminAction($pdo, $_SESSION['user_id'], 'resolve_alert', 'system_alert', $alert_id, 'Alerte système résolue');
        
        echo json_encode(['success' => $success]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
}
?>