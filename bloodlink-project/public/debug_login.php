<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>DEBUG CONNEXION</h1>";

// Test connexion BDD
try {
    $pdo = new PDO("mysql:host=localhost;dbname=bloodlink_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connexion BDD OK<br>";
} catch (Exception $e) {
    die("❌ BDD KO: " . $e->getMessage());
}

// Test 1 : Chercher admin
echo "<h2>Test compte admin</h2>";
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute(['admin@bloodlink.com']);
$admin = $stmt->fetch();

if ($admin) {
    echo "✅ Admin trouvé<br>";
    echo "ID: " . $admin['id'] . "<br>";
    echo "Email: " . $admin['email'] . "<br>";
    echo "Nom: " . $admin['full_name'] . "<br>";
    echo "Type: " . $admin['user_type'] . "<br>";
    echo "Hash: " . substr($admin['password'], 0, 30) . "...<br>";
    
    // Tester plusieurs mots de passe
    $test_passwords = ['admin123', 'admin', 'password', '123456'];
    foreach ($test_passwords as $pwd) {
        $result = password_verify($pwd, $admin['password']);
        echo "Test '$pwd': " . ($result ? "✅ VALIDE" : "❌ INVALIDE") . "<br>";
    }
    
    // Vérifier le hash
    echo "<br>Hash info: ";
    print_r(password_get_info($admin['password']));
} else {
    echo "❌ Admin NON trouvé<br>";
    
    // Créer l'admin maintenant
    echo "<h3>Création de l'admin...</h3>";
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (email, password, full_name, user_type, is_verified) 
            VALUES ('admin@bloodlink.com', ?, 'Admin Principal', 'admin', 1)";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$hash])) {
        echo "✅ Admin créé avec succès!<br>";
        echo "Mot de passe: admin123<br>";
        echo "Hash généré: " . substr($hash, 0, 30) . "...<br>";
    } else {
        echo "❌ Erreur création admin<br>";
    }
}

// Test 2 : Voir TOUS les utilisateurs
echo "<hr><h2>Tous les utilisateurs</h2>";
$stmt = $pdo->query("SELECT id, email, user_type, full_name FROM users ORDER BY id");
$users = $stmt->fetchAll();

if (empty($users)) {
    echo "Aucun utilisateur<br>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Email</th><th>Type</th><th>Nom</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['email'] . "</td>";
        echo "<td>" . $user['user_type'] . "</td>";
        echo "<td>" . $user['full_name'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 3 : Vérifier la fonction password_verify
echo "<hr><h2>Test password_verify</h2>";
$test_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$test_pass = 'admin123';

echo "Hash test: " . substr($test_hash, 0, 30) . "...<br>";
echo "Password test: $test_pass<br>";
echo "Résultat: " . (password_verify($test_pass, $test_hash) ? "✅ OK" : "❌ KO");

// Test 4 : Vérifier la table hospitals
echo "<hr><h2>Hôpitaux</h2>";
$stmt = $pdo->query("SELECT COUNT(*) as count FROM hospitals");
$result = $stmt->fetch();
echo "Nombre d'hôpitaux: " . $result['count'];
?>

<hr>
<h2>Test de connexion manuel</h2>
<form method="POST" action="login.php">
    <input type="email" name="email" placeholder="Email" value="admin@bloodlink.com"><br>
    <input type="password" name="password" placeholder="Password" value="admin123"><br>
    <select name="user_type">
        <option value="donor">Donneur</option>
        <option value="hospital">Hôpital</option>
        <option value="admin" selected>Admin</option>
    </select><br>
    <button type="submit">Tester connexion</button>
</form>