<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || getUserRole() !== 'hospital') {
    redirect('../public/login.php');
}

$request_id = (int)($_GET['id'] ?? 0);

// Récupérer la demande avec vérification
$stmt = $pdo->prepare("
    SELECT dr.*, bg.name as blood_group_name 
    FROM donation_requests dr
    JOIN blood_groups bg ON dr.blood_group_id = bg.id
    WHERE dr.id = ? AND dr.hospital_id = ? AND dr.status = 'pending'
");
$stmt->execute([$request_id, $_SESSION['user_id']]);
$request = $stmt->fetch();

if (!$request) {
    die("<div style='padding: 2rem; text-align: center;'>
        <h2>Impossible de modifier</h2>
        <p>Cette demande n'existe pas, n'est plus en attente, ou vous n'y avez pas accès.</p>
        <a href='my-requests.php'>Retour à mes demandes</a>
    </div>");
}

// Traitement similaire à create-request.php mais avec UPDATE
// ...
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier demande #<?php echo $request_id; ?></title>
</head>
<body>
    <!-- Formulaire similaire à create-request.php avec valeurs pré-remplies -->
</body>
</html>