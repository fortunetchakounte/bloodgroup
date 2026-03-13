<?php
// Fonctions utilitaires pour tout le projet

/**
 * Hash un mot de passe
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Vérifie un mot de passe
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Génère un message flash (pour les succès/erreurs)
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Affiche et supprime le message flash
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        $color = $flash['type'] === 'success' ? 'green' : 'red';
        
        echo "<div style='padding: 1rem; margin: 1rem 0; background: {$color}20; color: {$color}; border-radius: 5px;'>
                {$flash['message']}
              </div>";
        
        unset($_SESSION['flash']);
    }
}

/**
 * Protège contre les injections XSS
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../public/login.php');
        exit();
    }
}

/**
 * Vérifie si l'utilisateur est admin
 */
function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: ../public/index.php');
        exit();
    }
}

/**
 * Vérifie si l'utilisateur est un donneur
 */
function requireDonor() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'donor') {
        header('Location: ../public/index.php');
        exit();
    }
}

/**
 * Vérifie si l'utilisateur est un hôpital
 */
function requireHospital() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'hospital') {
        header('Location: ../public/index.php');
        exit();
    }
}




/**
 * Envoyer un email de notification (simulé pour l'instant)
 */
function sendValidationEmail($email, $type, $approved = true) {
    // En production, utiliser PHPMailer ou mail() 
    // Pour l'instant, on simule
    
    $subject = $approved ? "Votre compte BloodLink a été validé" : "Votre compte BloodLink a été rejeté";
    $message = $approved 
        ? "Félicitations ! Votre compte a été validé. Vous pouvez maintenant utiliser toutes les fonctionnalités."
        : "Désolé, votre compte n'a pas été approuvé. Contactez l'administrateur pour plus d'informations.";
    
    // Log pour debug
    error_log("Email à $email ($type): $subject");
    
    return true;
}

/**
 * Compter les utilisateurs en attente de validation
 */
function countPendingUsers($pdo, $type = 'donor') {
    if ($type === 'donor') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor' AND is_verified = FALSE");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM hospitals WHERE is_verified = FALSE");
    }
    
    $stmt->execute();
    return $stmt->fetch()['count'];
}

/**
 * Journaliser les actions admin
 */
function logAdminAction($pdo, $admin_id, $action, $target_type, $target_id, $details = '') {
    // Créer la table logs si elle n'existe pas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_id INT,
            action VARCHAR(50),
            target_type VARCHAR(20),
            target_id INT,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    
    $stmt = $pdo->prepare("
        INSERT INTO admin_logs (admin_id, action, target_type, target_id, details)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$admin_id, $action, $target_type, $target_id, $details]);
}
?>