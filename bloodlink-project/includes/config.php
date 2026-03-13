

<?php
// Retire session_start() d'ici s'il est déjà dans register.php
// Laisser seulement la connexion à la base

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'bloodlink_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Connexion PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Fonctions utilitaires SANS session_start()
function redirect($url) {
    header("Location: $url");
    exit();
}

// Note: Les fonctions isLoggedIn() et getUserRole() 
// nécessitent que session_start() soit déjà appelé
?>