<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/messaging.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit();
}

$conversation_id = (int)($_POST['conversation_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message vide']);
    exit();
}

if ($conversation_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Conversation invalide']);
    exit();
}

// Vérifier l'accès à la conversation
$stmt = $pdo->prepare("
    SELECT id FROM conversations 
    WHERE id = ? 
    AND (
        (participant1_id = ? AND participant1_type = ?)
        OR (participant2_id = ? AND participant2_type = ?)
    )
");

$stmt->execute([
    $conversation_id,
    $_SESSION['user_id'], $_SESSION['user_role'],
    $_SESSION['user_id'], $_SESSION['user_role']
]);

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit();
}

// Gérer l'upload de fichier
$file_path = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/chat/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_name = time() . '_' . basename($_FILES['attachment']['name']);
    $target_file = $upload_dir . $file_name;
    
    // Validation du fichier
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
    $file_type = mime_content_type($_FILES['attachment']['tmp_name']);
    
    if (in_array($file_type, $allowed_types) && move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
        $file_path = 'chat/' . $file_name;
    }
}

// Envoyer le message
$message_id = sendMessage(
    $conversation_id,
    $_SESSION['user_id'],
    $_SESSION['user_role'],
    $message,
    $file_path ? 'file' : 'text',
    $file_path
);

if ($message_id) {
    echo json_encode([
        'success' => true,
        'message_id' => $message_id,
        'file_path' => $file_path
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'envoi']);
}