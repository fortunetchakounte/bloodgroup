<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=bloodlink_db;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connexion à la base réussie!<br>";
    
    // Test des tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📊 Tables trouvées : " . count($tables) . "<br>";
    foreach ($tables as $table) {
        echo "- $table<br>";
    }
    
    // Test des groupes sanguins
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM blood_groups");
    $result = $stmt->fetch();
    echo "🩸 Groupes sanguins : " . $result['count'] . "<br>";
    
} catch (PDOException $e) {
    echo "❌ Erreur de connexion : " . $e->getMessage();
}
?>