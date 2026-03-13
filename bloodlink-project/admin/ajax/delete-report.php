<?php
require_once '../../includes/init.php';

header('Content-Type: application/json');

if (!isLoggedIn() || getUserRole() !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit();
}

$report_id = (int)($_POST['id'] ?? 0);

if ($report_id > 0) {
    try {
        // Récupérer le chemin du fichier
        $stmt = $pdo->prepare("SELECT file_path FROM admin_reports WHERE id = ?");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch();
        
        if ($report) {
            // Supprimer le fichier
            if (file_exists($report['file_path'])) {
                unlink($report['file_path']);
            }
            
            // Supprimer de la base
            $delete_stmt = $pdo->prepare("DELETE FROM admin_reports WHERE id = ?");
            $success = $delete_stmt->execute([$report_id]);
            
            // Log l'action
            logAdminAction($pdo, $_SESSION['user_id'], 'delete_report', 'admin_report', $report_id, 'Rapport supprimé');
            
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Rapport non trouvé']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
}
?>