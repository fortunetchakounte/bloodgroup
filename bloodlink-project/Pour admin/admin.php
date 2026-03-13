<?php
// admin/donors.php - Page temporaire
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn() || getUserRole() !== 'admin') redirect('../public/login.php');
?>
<h1>Gestion des donneurs</h1>
<p>Page en construction...</p>
<a href="dashboard.php">Retour</a>