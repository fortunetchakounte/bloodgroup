<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

echo "<h1>🔍 Diagnostic Session Admin</h1>";

// Afficher la session actuelle
echo "<h2>Session actuelle :</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Vérifier la connexion à la base
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'admin'");
    $admin_count = $stmt->fetch()['count'];
    echo "<p>✅ Nombre d'admins dans la base : $admin_count</p>";
    
    // Afficher les admins
    $stmt = $pdo->query("SELECT id, email, full_name, user_type FROM users WHERE user_type = 'admin'");
    $admins = $stmt->fetchAll();
    
    echo "<h3>Admins enregistrés :</h3>";
    foreach ($admins as $admin) {
        echo "- {$admin['full_name']} ({$admin['email']}) - ID: {$admin['id']}<br>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erreur BDD : " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<a href='admin_login.php'>🔑 Aller à la connexion admin</a><br>";
echo "<a href='../admin/dashboard.php'>📊 Aller au dashboard admin</a>";
?>