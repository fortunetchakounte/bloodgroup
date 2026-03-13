<?php
require_once __DIR__ . '/../includes/config.php';

echo "<h1>🔄 Réinitialisation de l'administrateur principal</h1>";

$principal_email = 'admin@bloodlink.com';
$principal_password = 'admin123';
$principal_name = 'Administrateur Principal';

try {
    // Vérifier si l'admin principal existe déjà
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$principal_email]);
    $exists = $stmt->fetch();
    
    $hash = password_hash($principal_password, PASSWORD_DEFAULT);
    
    if ($exists) {
        // Mettre à jour l'existant
        $stmt = $pdo->prepare("UPDATE users SET password = ?, full_name = ?, is_verified = 1 WHERE email = ?");
        $result = $stmt->execute([$hash, $principal_name, $principal_email]);
        
        if ($result) {
            echo "✅ Compte admin principal mis à jour avec succès !<br>";
        } else {
            echo "❌ Erreur lors de la mise à jour<br>";
        }
    } else {
        // Créer un nouveau
        $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, user_type, is_verified) VALUES (?, ?, ?, 'admin', 1)");
        $result = $stmt->execute([$principal_email, $hash, $principal_name]);
        
        if ($result) {
            echo "✅ Compte admin principal créé avec succès !<br>";
        } else {
            echo "❌ Erreur lors de la création<br>";
        }
    }
    
    // Vérification
    echo "<h2>Vérification :</h2>";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$principal_email]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "Email: " . $admin['email'] . "<br>";
        echo "Nom: " . $admin['full_name'] . "<br>";
        echo "Type: " . $admin['user_type'] . "<br>";
        
        if (password_verify('admin123', $admin['password'])) {
            echo "<span style='color:green; font-size:18px;'>✅ Le mot de passe 'admin123' fonctionne !</span><br>";
        } else {
            echo "<span style='color:red; font-size:18px;'>❌ Problème avec le mot de passe</span><br>";
        }
    }
    
    echo "<br><a href='admin_login.php'>🔑 Aller à la page de connexion admin</a>";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
?>