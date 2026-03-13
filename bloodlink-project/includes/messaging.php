<?php
// Système de messagerie

/**
 * Créer ou récupérer une conversation
 */
function getOrCreateConversation($user1_id, $user1_type, $user2_id, $user2_type, $title = null) {
    global $pdo;
    
    // Chercher une conversation existante
    $stmt = $pdo->prepare("
        SELECT * FROM conversations 
        WHERE (
            (participant1_id = ? AND participant1_type = ? AND participant2_id = ? AND participant2_type = ?)
            OR (participant1_id = ? AND participant1_type = ? AND participant2_id = ? AND participant2_type = ?)
        )
        LIMIT 1
    ");
    
    $stmt->execute([
        $user1_id, $user1_type, $user2_id, $user2_type,
        $user2_id, $user2_type, $user1_id, $user1_type
    ]);
    
    $conversation = $stmt->fetch();
    
    if ($conversation) {
        return $conversation;
    }
    
    // Créer une nouvelle conversation
    $conversation_uuid = generateUuid();
    $conversation_type = $user1_type . '_' . $user2_type;
    
    $stmt = $pdo->prepare("
        INSERT INTO conversations 
        (conversation_uuid, title, conversation_type, 
         participant1_id, participant1_type, 
         participant2_id, participant2_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $conversation_uuid,
        $title,
        $conversation_type,
        $user1_id,
        $user1_type,
        $user2_id,
        $user2_type
    ]);
    
    $conversation_id = $pdo->lastInsertId();
    
    // Ajouter les participants
    addParticipantToConversation($conversation_id, $user1_id, $user1_type);
    addParticipantToConversation($conversation_id, $user2_id, $user2_type);
    
    return [
        'id' => $conversation_id,
        'conversation_uuid' => $conversation_uuid,
        'title' => $title,
        'conversation_type' => $conversation_type
    ];
}

/**
 * Envoyer un message
 */
function sendMessage($conversation_id, $sender_id, $sender_type, $message, $message_type = 'text', $file_path = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Insérer le message
        $stmt = $pdo->prepare("
            INSERT INTO messages 
            (conversation_id, sender_id, sender_type, message, message_type, file_path) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $conversation_id,
            $sender_id,
            $sender_type,
            $message,
            $message_type,
            $file_path
        ]);
        
        $message_id = $pdo->lastInsertId();
        
        // Mettre à jour la dernière activité de la conversation
        $pdo->prepare("
            UPDATE conversations 
            SET last_message_at = NOW() 
            WHERE id = ?
        ")->execute([$conversation_id]);
        
        // Marquer comme non lu pour tous les autres participants
        $pdo->prepare("
            UPDATE messages m
            JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
            SET m.is_read = FALSE
            WHERE m.id = ?
            AND (cp.user_id != ? OR cp.user_type != ?)
        ")->execute([$message_id, $sender_id, $sender_type]);
        
        $pdo->commit();
        
        // Notifier les participants
        notifyNewMessage($conversation_id, $message_id, $sender_id, $sender_type);
        
        return $message_id;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur envoi message: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupérer les conversations d'un utilisateur
 */
function getUserConversations($user_id, $user_type, $limit = 20, $offset = 0) {
    global $pdo;
    
    // Convertir en entiers pour éviter les problèmes de syntaxe SQL
    $limit = (int)$limit;
    $offset = (int)$offset;
    
    // Solution 1 : Utiliser bindValue avec le type PDO::PARAM_INT
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            CASE 
                WHEN c.participant1_id = ? AND c.participant1_type = ? 
                THEN CONCAT(c.participant2_type, ':', c.participant2_id)
                ELSE CONCAT(c.participant1_type, ':', c.participant1_id)
            END as other_participant,
            (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND is_read = FALSE 
             AND NOT (sender_id = ? AND sender_type = ?)) as unread_count,
            m.message as last_message,
            m.created_at as last_message_time,
            m.sender_id as last_sender_id,
            m.sender_type as last_sender_type
        FROM conversations c
        LEFT JOIN messages m ON c.id = m.conversation_id
        WHERE (c.participant1_id = ? AND c.participant1_type = ?)
           OR (c.participant2_id = ? AND c.participant2_type = ?)
        GROUP BY c.id
        ORDER BY c.last_message_at DESC
        LIMIT ? OFFSET ?
    ");
    
    // Utiliser bindValue pour spécifier le type
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $user_type, PDO::PARAM_STR);
    $stmt->bindValue(3, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(4, $user_type, PDO::PARAM_STR);
    $stmt->bindValue(5, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(6, $user_type, PDO::PARAM_STR);
    $stmt->bindValue(7, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(8, $user_type, PDO::PARAM_STR);
    $stmt->bindValue(9, $limit, PDO::PARAM_INT);
    $stmt->bindValue(10, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Récupérer les messages d'une conversation
 */
function getConversationMessages($conversation_id, $user_id, $user_type, $limit = 50, $offset = 0) {
    global $pdo;
    
    // Marquer les messages comme lus
    markConversationAsRead($conversation_id, $user_id, $user_type);
    
    // Récupérer les messages
    $stmt = $pdo->prepare("
        SELECT m.*,
               CASE 
                   WHEN m.sender_id = ? AND m.sender_type = ? THEN 1
                   ELSE 0
               END as is_own_message
        FROM messages m
        WHERE m.conversation_id = ?
        ORDER BY m.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$user_id, $user_type, $conversation_id, $limit, $offset]);
    $messages = $stmt->fetchAll();
    
    // Inverser l'ordre pour avoir du plus ancien au plus récent
    return array_reverse($messages);
}

/**
 * Marquer une conversation comme lue
 */
function markConversationAsRead($conversation_id, $user_id, $user_type) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE messages m
        JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
        SET m.is_read = TRUE, 
            m.read_at = NOW()
        WHERE m.conversation_id = ?
        AND cp.user_id = ?
        AND cp.user_type = ?
        AND m.is_read = FALSE
        AND NOT (m.sender_id = ? AND m.sender_type = ?)
    ");
    
    return $stmt->execute([
        $conversation_id,
        $user_id,
        $user_type,
        $user_id,
        $user_type
    ]);
}

/**
 * Notifier les participants d'un nouveau message
 */
function notifyNewMessage($conversation_id, $message_id, $sender_id, $sender_type) {
    global $pdo;
    
    // Récupérer les autres participants
    $stmt = $pdo->prepare("
        SELECT cp.user_id, cp.user_type
        FROM conversation_participants cp
        WHERE cp.conversation_id = ?
        AND NOT (cp.user_id = ? AND cp.user_type = ?)
    ");
    
    $stmt->execute([$conversation_id, $sender_id, $sender_type]);
    $participants = $stmt->fetchAll();
    
    // Récupérer les infos du message
    $msg_stmt = $pdo->prepare("SELECT message FROM messages WHERE id = ?");
    $msg_stmt->execute([$message_id]);
    $message = $msg_stmt->fetch();
    
    // Récupérer les infos de l'expéditeur
    $sender_name = getSenderName($sender_id, $sender_type);
    
    // Notifier chaque participant
    foreach ($participants as $participant) {
        createNotification(
            $pdo,
            $participant['user_id'],
            $participant['user_type'],
            "💬 Nouveau message de " . $sender_name,
            substr($message['message'], 0, 100) . (strlen($message['message']) > 100 ? '...' : ''),
            'info',
            '💬',
            '../messages/chat.php?conversation=' . $conversation_id,
            168
        );
    }
}

/**
 * Ajouter un participant à une conversation
 */
function addParticipantToConversation($conversation_id, $user_id, $user_type) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO conversation_participants 
        (conversation_id, user_id, user_type) 
        VALUES (?, ?, ?)
    ");
    
    return $stmt->execute([$conversation_id, $user_id, $user_type]);
}

/**
 * Générer un UUID
 */
function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Obtenir le nom de l'expéditeur
 */
function getSenderName($sender_id, $sender_type) {
    global $pdo;
    
    switch ($sender_type) {
        case 'donor':
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmt->execute([$sender_id]);
            $result = $stmt->fetch();
            return $result['full_name'] ?? 'Donneur';
            
        case 'hospital':
            $stmt = $pdo->prepare("SELECT hospital_name FROM hospitals WHERE id = ?");
            $stmt->execute([$sender_id]);
            $result = $stmt->fetch();
            return $result['hospital_name'] ?? 'Hôpital';
            
        case 'admin':
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND user_type = 'admin'");
            $stmt->execute([$sender_id]);
            $result = $stmt->fetch();
            return $result['full_name'] ?? 'Administrateur';
            
        default:
            return 'Utilisateur';
    }
}
?>