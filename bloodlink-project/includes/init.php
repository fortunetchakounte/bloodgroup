<?php
// Fichier d'initialisation unique

// 1. Démarrer la session UNE SEULE FOIS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Configuration des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 3. Connexion à la base
define('DB_HOST', 'localhost');
define('DB_NAME', 'bloodlink_db');
define('DB_USER', 'root');
define('DB_PASS', '');

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

// 4. Fonctions globales
function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}
// Inclure les fonctions de notification
require_once __DIR__ . '/notifications.php';

// Nettoyer les notifications expirées occasionnellement (1% des requêtes)
if (rand(1, 100) === 1) {
    cleanupExpiredNotifications($pdo);
}
?>