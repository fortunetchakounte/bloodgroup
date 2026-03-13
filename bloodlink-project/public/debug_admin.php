<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

echo "<h1>🔍 Diagnostic des comptes admin</h1>";

// Afficher la session actuelle
echo "<h2>Session actuelle :</h2>";
if (!empty($_SESSION)) {
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
} else {
    echo "Aucune session active<br>";
}

// Lister tous les admins dans la base
echo "<h2>Comptes administrateurs dans la base :</h2>";
try {
    $stmt = $pdo->query("SELECT id, email, full_name, user_type, is_verified, created_at FROM users WHERE user_type = 'admin' ORDER BY id");
    $admins = $stmt->fetchAll();
    
    if (count($admins) > 0) {
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Email</th><th>Nom</th><th>Type</th><th>Vérifié</th><th>Créé le</th><th>Test connexion</th></tr>";
        
        foreach ($admins as $admin) {
            echo "<tr>";
            echo "<td>" . $admin['id'] . "</td>";
            echo "<td>" . $admin['email'] . "</td>";
            echo "<td>" . $admin['full_name'] . "</td>";
            echo "<td>" . $admin['user_type'] . "</td>";
            echo "<td>" . ($admin['is_verified'] ? '✅ Oui' : '❌ Non') . "</td>";
            echo "<td>" . $admin['created_at'] . "</td>";
            echo "<td><a href='test_admin_login.php?email=" . urlencode($admin['email']) . "'>Tester la connexion</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Aucun administrateur trouvé dans la base !";
    }
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}

echo "<hr>";
echo "<h2>Actions :</h2>";
echo "<ul>";
echo "<li><a href='admin_login.php'>🔑 Aller à la page de connexion admin</a></li>";
echo "<li><a href='reset_admin_principal.php'>🔄 Réinitialiser l'admin principal</a></li>";
echo "<li><a href='logout.php'>🚪 Déconnexion</a></li>";
echo "</ul>";
?>