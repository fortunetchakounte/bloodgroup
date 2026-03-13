<?php
// hospital/create-request.php - Page temporaire
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn() || getUserRole() !== 'hospital') redirect('../public/login.php');
?>
<h1>Créer une demande</h1>
<p>Page en construction...</p>
<a href="dashboard.php">Retour</a>