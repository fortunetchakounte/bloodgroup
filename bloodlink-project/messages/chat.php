<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/messaging.php';

if (!isLoggedIn()) {
    redirect('../public/login.php');
}

$conversation_id = (int)($_GET['conversation'] ?? 0);
$action = $_GET['action'] ?? '';
$recipient_id = (int)($_GET['recipient_id'] ?? 0);
$recipient_type = $_GET['recipient_type'] ?? '';

// Créer une nouvelle conversation
if ($action === 'new' && $recipient_id > 0 && $recipient_type) {
    $conversation = getOrCreateConversation(
        $_SESSION['user_id'],
        $_SESSION['user_role'],
        $recipient_id,
        $recipient_type
    );
    
    header('Location: chat.php?conversation=' . $conversation['id']);
    exit();
}

// Envoyer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    $conversation_id = (int)$_POST['conversation_id'];
    
    if (!empty($message) && $conversation_id > 0) {
        $message_id = sendMessage(
            $conversation_id,
            $_SESSION['user_id'],
            $_SESSION['user_role'],
            $message
        );
        
        if ($message_id) {
            // Succès - rafraîchir la page
            header('Location: chat.php?conversation=' . $conversation_id);
            exit();
        }
    }
}

// Récupérer les conversations
$conversations = getUserConversations($_SESSION['user_id'], $_SESSION['user_role'], 20);

// Récupérer la conversation active
$current_conversation = null;
$messages = [];
$other_participant = null;

if ($conversation_id > 0) {
    // Vérifier l'accès
    $stmt = $pdo->prepare("
        SELECT * FROM conversations 
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
    
    $current_conversation = $stmt->fetch();
    
    if ($current_conversation) {
        // Récupérer les messages
        $messages = getConversationMessages(
            $conversation_id,
            $_SESSION['user_id'],
            $_SESSION['user_role'],
            100
        );
        
        // Identifier l'autre participant
        if ($current_conversation['participant1_id'] == $_SESSION['user_id'] 
            && $current_conversation['participant1_type'] == $_SESSION['user_role']) {
            $other_id = $current_conversation['participant2_id'];
            $other_type = $current_conversation['participant2_type'];
        } else {
            $other_id = $current_conversation['participant1_id'];
            $other_type = $current_conversation['participant1_type'];
        }
        
        $other_participant = getParticipantInfo($other_id, $other_type);
    } else {
        $conversation_id = 0;
    }
}

$page_title = "Messages";
$page_subtitle = "Communiquez avec les donneurs et hôpitaux";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - BloodLink</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="../css/messaging.css">
    
    <style>
        /* Styles complémentaires */
        .messaging-page {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: calc(100vh - 80px);
            padding: 2rem 0;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .page-title i {
            color: var(--primary-color);
            background: rgba(230, 57, 70, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .new-conversation-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius-md);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .new-conversation-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
    </style>
</head>
<body class="bloodlink-body">
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Contenu principal -->
    <main class="messaging-page">
        <div class="container">
            <!-- En-tête de page -->
            <div class="page-header">
                <div class="page-header-content">
                    <h1 class="page-title">
                        <i class="fas fa-comments"></i>
                        <span>
                            <?php echo $page_title; ?>
                            <small class="page-subtitle"><?php echo $page_subtitle; ?></small>
                        </span>
                    </h1>
                    
                    <div class="page-actions">
                        <a href="new-conversation.php" class="new-conversation-btn">
                            <i class="fas fa-plus"></i> Nouvelle conversation
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Container de messagerie -->
            <div class="messaging-container">
                <!-- Sidebar des conversations -->
                <div class="conversations-sidebar">
                    <div class="conversations-header">
                        <h3 style="margin: 0 0 1rem 0; color: var(--dark-color);">Conversations</h3>
                        
                        <div class="conversations-search">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" placeholder="Rechercher une conversation...">
                        </div>
                        
                        <div class="conversations-filters">
                            <button class="filter-btn active">Toutes</button>
                            <button class="filter-btn">Non lues</button>
                            <button class="filter-btn">Importantes</button>
                        </div>
                    </div>
                    
                    <div class="conversations-list">
                        <?php if (empty($conversations)): ?>
                            <div class="empty-state" style="padding: 2rem;">
                                <div class="empty-state-icon">💬</div>
                                <h4 class="empty-state-title">Aucune conversation</h4>
                                <p class="empty-state-description">
                                    Commencez une nouvelle conversation pour échanger avec d'autres membres.
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($conversations as $conv): 
                                $other_id = ($conv['participant1_id'] == $_SESSION['user_id'] && $conv['participant1_type'] == $_SESSION['user_role']) 
                                    ? $conv['participant2_id'] 
                                    : $conv['participant1_id'];
                                $other_type = ($conv['participant1_id'] == $_SESSION['user_id'] && $conv['participant1_type'] == $_SESSION['user_role']) 
                                    ? $conv['participant2_type'] 
                                    : $conv['participant1_type'];
                                
                                $other_info = getParticipantInfo($other_id, $other_type);
                                $last_message = getLastMessagePreview($conv['id']);
                                $unread_count = getUnreadCount($conv['id'], $_SESSION['user_id'], $_SESSION['user_role']);
                            ?>
                                <a href="chat.php?conversation=<?php echo $conv['id']; ?>" 
                                   class="conversation-item <?php echo $conv['id'] == $conversation_id ? 'active' : ''; ?>">
                                    <div class="conversation-avatar">
                                        <?php if (isset($other_info['avatar']) && $other_info['avatar']): ?>
                                            <img src="../uploads/<?php echo $other_info['avatar']; ?>" alt="<?php echo htmlspecialchars($other_info['name']); ?>">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($other_info['name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="conversation-info">
                                        <div class="conversation-header">
                                            <span class="conversation-name"><?php echo htmlspecialchars($other_info['name']); ?></span>
                                            <span class="conversation-time">
                                                <?php echo timeAgo($conv['last_message_at'] ?? $conv['created_at']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="conversation-preview">
                                            <?php echo htmlspecialchars($last_message); ?>
                                        </div>
                                        
                                        <div class="conversation-meta">
                                            <?php if ($unread_count > 0): ?>
                                                <span class="unread-badge"><?php echo $unread_count; ?></span>
                                            <?php endif; ?>
                                            
                                            <span class="conversation-status <?php echo $other_info['is_online'] ? '' : 'offline'; ?>"></span>
                                            
                                            <span class="conversation-type">
                                                <?php echo $other_type == 'donor' ? '👤 Donneur' : 
                                                       ($other_type == 'hospital' ? '🏥 Hôpital' : '🛡️ Admin'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Zone de chat principale -->
                <div class="chat-main">
                    <?php if ($conversation_id > 0 && $other_participant): ?>
                        <!-- En-tête du chat -->
                        <div class="chat-header">
                            <div class="chat-header-info">
                                <div class="conversation-avatar">
                                    <?php if (isset($other_participant['avatar']) && $other_participant['avatar']): ?>
                                        <img src="../uploads/<?php echo $other_participant['avatar']; ?>" alt="<?php echo htmlspecialchars($other_participant['name']); ?>">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($other_participant['name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <h4 style="margin: 0; color: var(--dark-color);">
                                        <?php echo htmlspecialchars($other_participant['name']); ?>
                                        <span class="conversation-status <?php echo $other_participant['is_online'] ? '' : 'offline'; ?>"></span>
                                    </h4>
                                    <small style="color: var(--gray-medium);">
                                        <?php if ($other_participant['is_online']): ?>
                                            <span style="color: var(--success-color);">● En ligne</span>
                                        <?php else: ?>
                                            <?php echo $other_participant['last_seen'] ? 'Vu ' . timeAgo($other_participant['last_seen']) : 'Hors ligne'; ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="chat-header-actions">
                                <button class="chat-header-action" title="Appeler">
                                    <i class="fas fa-phone"></i>
                                </button>
                                <button class="chat-header-action" title="Infos">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                                <button class="chat-header-action" title="Menu">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Messages -->
                        <div class="chat-messages" id="chatMessages">
                            <?php if (empty($messages)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">👋</div>
                                    <h4 class="empty-state-title">Commencez la conversation</h4>
                                    <p class="empty-state-description">
                                        Envoyez votre premier message à <?php echo htmlspecialchars($other_participant['name']); ?>.
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php
                                $current_date = null;
                                foreach ($messages as $message):
                                    $message_date = date('Y-m-d', strtotime($message['created_at']));
                                    if ($message_date != $current_date):
                                        $current_date = $message_date;
                                ?>
                                    <div class="message-group-date">
                                        <span>
                                            <?php echo date('d F Y', strtotime($current_date)); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="message <?php echo $message['sender_id'] == $_SESSION['user_id'] && $message['sender_type'] == $_SESSION['user_role'] ? 'own' : 'other'; ?>">
                                    <?php if ($message['sender_id'] != $_SESSION['user_id'] || $message['sender_type'] != $_SESSION['user_role']): ?>
                                        <div class="message-avatar">
                                            <?php echo strtoupper(substr(getSenderName($message['sender_id'], $message['sender_type']), 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="message-content">
                                        <div class="message-bubble">
                                            <div class="message-text">
                                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                            </div>
                                            
                                            <?php if ($message['file_path']): ?>
                                                <div class="message-attachment">
                                                    <a href="../uploads/<?php echo $message['file_path']; ?>" target="_blank" class="attachment-file">
                                                        <div class="file-icon">
                                                            <i class="fas fa-file"></i>
                                                        </div>
                                                        <div class="file-info">
                                                            <div class="file-name">Fichier joint</div>
                                                            <div class="file-size"><?php echo formatFileSize(filesize('../uploads/' . $message['file_path'])); ?></div>
                                                        </div>
                                                        <div class="file-download">
                                                            <i class="fas fa-download"></i>
                                                        </div>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="message-time">
                                            <?php echo date('H:i', strtotime($message['created_at'])); ?>
                                            
                                            <?php if ($message['sender_id'] == $_SESSION['user_id'] && $message['sender_type'] == $_SESSION['user_role']): ?>
                                                <span class="message-status">
                                                    <?php if ($message['is_read']): ?>
                                                        <i class="fas fa-check-double" style="color: var(--success-color);"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-check" style="color: var(--gray-medium);"></i>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($message['sender_id'] == $_SESSION['user_id'] && $message['sender_type'] == $_SESSION['user_role']): ?>
                                        <div class="message-avatar">
                                            <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <!-- Indicateur de frappe -->
                            <div class="typing-indicator" id="typingIndicator" style="display: none;">
                                <span><?php echo htmlspecialchars($other_participant['name']); ?> est en train d'écrire</span>
                                <div class="typing-dots">
                                    <div class="typing-dot"></div>
                                    <div class="typing-dot"></div>
                                    <div class="typing-dot"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Zone de saisie -->
                        <div class="chat-input">
                            <form method="POST" id="messageForm">
                                <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
                                
                                <div class="input-container">
                                    <div class="input-actions">
                                        <button type="button" class="input-action" title="Ajouter une image">
                                            <i class="fas fa-image"></i>
                                        </button>
                                        <button type="button" class="input-action" title="Ajouter un fichier">
                                            <i class="fas fa-paperclip"></i>
                                        </button>
                                        <button type="button" class="input-action" title="Émoticônes">
                                            <i class="fas fa-smile"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="message-input-container">
                                        <textarea name="message" 
                                                  class="message-input" 
                                                  placeholder="Tapez votre message..." 
                                                  rows="1"
                                                  id="messageInput"
                                                  oninput="autoResize(this)"></textarea>
                                    </div>
                                    
                                    <button type="submit" class="send-button" title="Envoyer">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                    <?php else: ?>
                        <!-- Aucune conversation sélectionnée -->
                        <div class="chat-main" style="justify-content: center;">
                            <div class="empty-state">
                                <div class="empty-state-icon">💬</div>
                                <h2 class="empty-state-title">Bienvenue dans la messagerie</h2>
                                <p class="empty-state-description">
                                    Sélectionnez une conversation pour commencer à discuter,<br>
                                    ou démarrez une nouvelle conversation.
                                </p>
                                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                                    <a href="new-conversation.php" class="new-conversation-btn">
                                        <i class="fas fa-plus"></i> Nouvelle conversation
                                    </a>
                                    <a href="../donor/map-requests.php" class="new-conversation-btn" style="background: var(--secondary-color);">
                                        <i class="fas fa-map-marker-alt"></i> Trouver des donneurs
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script>
        // Auto-resize du textarea
        function autoResize(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
        }
        
        // Scroll vers le bas des messages
        function scrollToBottom() {
            const messagesDiv = document.getElementById('chatMessages');
            if (messagesDiv) {
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }
        }
        
        // Envoi du formulaire avec AJAX
        document.getElementById('messageForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (!message) return;
            
            // Désactiver le bouton d'envoi
            const sendBtn = this.querySelector('.send-button');
            sendBtn.disabled = true;
            
            try {
                const response = await fetch('ajax/send-message.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Réinitialiser le champ
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    
                    // Recharger la page pour voir le nouveau message
                    window.location.reload();
                } else {
                    alert('Erreur: ' + result.error);
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur réseau');
            } finally {
                sendBtn.disabled = false;
            }
        });
        
        // WebSocket pour le chat en temps réel
        let ws = null;
        const conversationId = <?php echo $conversation_id; ?>;
        
        function connectWebSocket() {
            if (conversationId > 0 && 'WebSocket' in window) {
                const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                const wsUrl = `${protocol}//${window.location.host}/bloodlink/ws/chat.php?conversation=${conversationId}`;
                
                ws = new WebSocket(wsUrl);
                
                ws.onopen = function() {
                    console.log('WebSocket connecté');
                };
                
                ws.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    handleWebSocketMessage(data);
                };
                
                ws.onclose = function() {
                    console.log('WebSocket déconnecté');
                    // Reconnexion après 3 secondes
                    setTimeout(connectWebSocket, 3000);
                };
            }
        }
        
        function handleWebSocketMessage(data) {
            switch (data.type) {
                case 'new_message':
                    addNewMessage(data.message);
                    break;
                    
                case 'typing':
                    showTypingIndicator(data.user_name);
                    break;
                    
                case 'stop_typing':
                    hideTypingIndicator();
                    break;
                    
                case 'message_read':
                    updateMessageStatus(data.message_id);
                    break;
            }
        }
        
        function addNewMessage(message) {
            // Implémentation AJAX pour ajouter un message
            // (simplifiée pour l'exemple)
            window.location.reload();
        }
        
        function showTypingIndicator(userName) {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) {
                indicator.querySelector('span').textContent = userName + ' est en train d\'écrire';
                indicator.style.display = 'flex';
                scrollToBottom();
            }
        }
        
        function hideTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) {
                indicator.style.display = 'none';
            }
        }
        
        // Détecter la frappe
        let typingTimeout = null;
        document.getElementById('messageInput')?.addEventListener('input', function() {
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({
                    type: 'typing',
                    conversation_id: conversationId
                }));
                
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => {
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        ws.send(JSON.stringify({
                            type: 'stop_typing',
                            conversation_id: conversationId
                        }));
                    }
                }, 2000);
            }
        });
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            scrollToBottom();
            
            // Connecter le WebSocket
            if (conversationId > 0) {
                connectWebSocket();
                
                // Polling fallback
                setInterval(checkNewMessages, 5000);
            }
            
            // Focus sur le champ de message si conversation active
            if (conversationId > 0) {
                document.getElementById('messageInput')?.focus();
            }
        });
        
        // Recherche de conversations
        document.querySelector('.search-input')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.conversation-item');
            
            items.forEach(item => {
                const name = item.querySelector('.conversation-name').textContent.toLowerCase();
                const preview = item.querySelector('.conversation-preview').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || preview.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>

<?php
// Fonctions utilitaires pour la messagerie

function getLastMessagePreview($conversation_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT message 
        FROM messages 
        WHERE conversation_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$conversation_id]);
    $message = $stmt->fetch();
    
    if ($message) {
        $preview = $message['message'];
        if (strlen($preview) > 50) {
            $preview = substr($preview, 0, 47) . '...';
        }
        return $preview;
    }
    
    return 'Aucun message';
}

function getUnreadCount($conversation_id, $user_id, $user_type) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM messages m
        WHERE m.conversation_id = ?
        AND m.is_read = FALSE
        AND NOT (m.sender_id = ? AND m.sender_type = ?)
    ");
    
    $stmt->execute([$conversation_id, $user_id, $user_type]);
    $result = $stmt->fetch();
    
    return $result['count'] ?? 0;
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'à l\'instant';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return "il y a $mins min";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "il y a $hours h";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return "il y a $days j";
    } else {
        return date('d/m/Y', $time);
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}