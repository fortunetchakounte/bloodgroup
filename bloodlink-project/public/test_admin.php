<?php
require_once __DIR__ . '/../includes/init.php';

$email = 'admin@bloodlink.com';

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<h3>✅ Compte trouvé !</h3>";
        echo "<pre>";
        echo "ID: " . $user['id'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Nom: " . $user['full_name'] . "\n";
        echo "Type utilisateur: " . ($user['user_type'] ?? 'Non défini') . "\n";
        echo "Vérifié: " . ($user['is_verified'] ? 'Oui' : 'Non') . "\n";
        echo "Hash du mot de passe: " . substr($user['password'], 0, 30) . "...\n";
        echo "</pre>";
        
        // Tester le mot de passe 'admin123'
        if (password_verify('admin123', $user['password'])) {
            echo "<p style='color:green'>✅ Le mot de passe 'admin123' est CORRECT !</p>";
        } else {
            echo "<p style='color:red'>❌ Le mot de passe 'admin123' est INCORRECT.</p>";
        }
    } else {
        echo "<p style='color:red'>❌ AUCUN compte trouvé avec l'email: $email</p>";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}
?>