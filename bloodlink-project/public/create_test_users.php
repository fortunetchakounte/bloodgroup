<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO("mysql:host=localhost;dbname=bloodlink_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Création des utilisateurs test</h1>";
    
    // Vérifier la structure de la table (optionnel)
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    echo "Colonnes trouvées : " . implode(', ', $columns) . "<br><br>";
    
    // 1. Créer admin
    $admin_password = 'admin123';
    $admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (email, password, full_name, user_type, is_verified) 
            VALUES (?, ?, ?, 'admin', 1) 
            ON DUPLICATE KEY UPDATE password = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['admin@bloodlink.com', $admin_hash, 'Admin Test', $admin_hash]);
    
    echo "✅ Admin créé/mis à jour<br>";
    echo "Email: admin@bloodlink.com<br>";
    echo "Password: $admin_password<br><br>";
    
    // 2. Créer donneur (avec blood_group_id et city si les colonnes existent)
    if (in_array('blood_group_id', $columns) && in_array('city', $columns)) {
        $donor_password = 'donor123';
        $donor_hash = password_hash($donor_password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (email, password, full_name, blood_group_id, city, user_type, is_verified) 
                VALUES (?, ?, ?, 1, 'Paris', 'donor', 1) 
                ON DUPLICATE KEY UPDATE password = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['donor@test.com', $donor_hash, 'Jean Dupont', $donor_hash]);
        
        echo "✅ Donneur créé/mis à jour<br>";
        echo "Email: donor@test.com<br>";
        echo "Password: $donor_password<br><br>";
    } else {
        // Version sans blood_group_id et city
        $donor_password = 'donor123';
        $donor_hash = password_hash($donor_password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (email, password, full_name, user_type, is_verified) 
                VALUES (?, ?, ?, 'donor', 1) 
                ON DUPLICATE KEY UPDATE password = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['donor@test.com', $donor_hash, 'Jean Dupont', $donor_hash]);
        
        echo "✅ Donneur créé/mis à jour (sans groupe sanguin)<br>";
        echo "Email: donor@test.com<br>";
        echo "Password: $donor_password<br><br>";
    }
    
    // 3. Vérifier
    $stmt = $pdo->query("SELECT email, user_type FROM users");
    $users = $stmt->fetchAll();
    
    echo "<h2>Utilisateurs dans la base :</h2>";
    foreach ($users as $user) {
        echo "- " . $user['email'] . " (" . $user['user_type'] . ")<br>";
    }
    
    echo "<hr><h2>Test de connexion :</h2>";
    echo '<a href="login.php">Aller à la page de connexion</a><br>';
    echo '<a href="debug_login.php">Page de debug</a>';
    
} catch (PDOException $e) {
    die("Erreur PDO : " . $e->getMessage() . "<br>Vérifiez la structure de votre table `users`.");
} catch (Exception $e) {
    die("Erreur générale : " . $e->getMessage());
}
?>