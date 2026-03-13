<?php
// Composant réutilisable de cloche de notification
if (!isset($pdo) || !isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    return;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_role'];

// Compter les notifications non lues
$unread_count = countUnreadNotifications($pdo, $user_id, $user_type);

// Récupérer les dernières notifications
$notifications = getUserNotifications($pdo, $user_id, $user_type, 10);
?>

<div class="notification-bell-container" id="notificationBell">
    <button class="notification-bell" onclick="toggleNotifications()">
        <span class="bell-icon">🔔</span>
        <?php if ($unread_count > 0): ?>
            <span class="notification-count"><?php echo $unread_count; ?></span>
        <?php endif; ?>
    </button>
    
    <div class="notifications-dropdown" id="notificationsDropdown">
        <div class="notifications-header">
            <h3>Notifications</h3>
            <?php if ($unread_count > 0): ?>
                <button onclick="markAllAsRead()" class="mark-all-read">
                    ✅ Tout marquer comme lu
                </button>
            <?php endif; ?>
        </div>
        
        <div class="notifications-list" id="notificationsList">
            <?php if (empty($notifications)): ?>
                <div class="notification-item empty">
                    <div class="notification-icon">📭</div>
                    <div class="notification-content">
                        <p>Aucune notification</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>"
                         data-id="<?php echo $notification['id']; ?>"
                         onclick="openNotification('<?php echo $notification['link'] ?? '#'; ?>', <?php echo $notification['id']; ?>)">
                        <div class="notification-icon">
                            <?php echo $notification['icon'] ?? '📢'; ?>
                        </div>
                        <div class="notification-content">
                            <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small class="notification-time">
                                <?php echo time_ago($notification['created_at']); ?>
                            </small>
                        </div>
                        <?php if (!$notification['is_read']): ?>
                            <div class="notification-dot"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notifications-footer">
            <a href="notifications.php">Voir toutes les notifications</a>
            <a href="notification-settings.php">⚙️ Paramètres</a>
        </div>
    </div>
</div>

<style>
.notification-bell-container {
    position: relative;
    display: inline-block;
}

.notification-bell {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    position: relative;
    font-size: 1.5rem;
    transition: transform 0.3s;
}

.notification-bell:hover {
    transform: scale(1.1);
}

.notification-count {
    position: absolute;
    top: 0;
    right: 0;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notifications-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 350px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    z-index: 1000;
    display: none;
    margin-top: 0.5rem;
    max-height: 500px;
    overflow: hidden;
}

.notifications-dropdown.show {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.notifications-header {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
}

.notifications-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #333;
}

.mark-all-read {
    background: #28a745;
    color: white;
    border: none;
    padding: 0.3rem 0.6rem;
    border-radius: 5px;
    font-size: 0.8rem;
    cursor: pointer;
}

.mark-all-read:hover {
    background: #218838;
}

.notifications-list {
    max-height: 300px;
    overflow-y: auto;
}

.notification-item {
    padding: 1rem;
    border-bottom: 1px solid #f8f9fa;
    cursor: pointer;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    transition: background 0.2s;
    position: relative;
}

.notification-item:hover {
    background: #f8f9fa;
}

.notification-item.unread {
    background: #f0f7ff;
}

.notification-item.unread:hover {
    background: #e6f0ff;
}

.notification-icon {
    font-size: 1.2rem;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-content h4 {
    margin: 0 0 0.25rem 0;
    font-size: 0.95rem;
    color: #333;
}

.notification-content p {
    margin: 0 0 0.25rem 0;
    font-size: 0.85rem;
    color: #666;
    line-height: 1.4;
}

.notification-time {
    color: #999;
    font-size: 0.75rem;
}

.notification-dot {
    width: 8px;
    height: 8px;
    background: #007bff;
    border-radius: 50%;
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
}

.notification-item.empty {
    cursor: default;
    text-align: center;
    justify-content: center;
    color: #999;
}

.notification-item.empty:hover {
    background: white;
}

.notifications-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    background: #f8f9fa;
}

.notifications-footer a {
    color: #007bff;
    text-decoration: none;
    font-size: 0.85rem;
}

.notifications-footer a:hover {
    text-decoration: underline;
}

/* Types de notification */
.notification-item.type-urgent {
    border-left: 3px solid #dc3545;
}

.notification-item.type-warning {
    border-left: 3px solid #ffc107;
}

.notification-item.type-success {
    border-left: 3px solid #28a745;
}

.notification-item.type-info {
    border-left: 3px solid #17a2b8;
}

.notification-item.type-danger {
    border-left: 3px solid #dc3545;
}
</style>

<script>
function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    dropdown.classList.toggle('show');
    
    // Fermer en cliquant à l'extérieur
    document.addEventListener('click', function closeDropdown(e) {
        if (!dropdown.contains(e.target) && !e.target.closest('.notification-bell')) {
            dropdown.classList.remove('show');
            document.removeEventListener('click', closeDropdown);
        }
    });
}

function openNotification(link, notificationId) {
    // Marquer comme lu
    markAsRead(notificationId);
    
    // Ouvrir le lien
    if (link && link !== '#') {
        window.location.href = link;
    }
}

function markAsRead(notificationId) {
    fetch('ajax/mark-notification-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mettre à jour l'interface
            const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (item) {
                item.classList.remove('unread');
                item.classList.add('read');
                const dot = item.querySelector('.notification-dot');
                if (dot) dot.remove();
            }
            
            // Mettre à jour le compteur
            updateNotificationCount();
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllAsRead() {
    fetch('ajax/mark-all-notifications-read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Marquer toutes comme lues dans l'interface
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.remove('unread');
                item.classList.add('read');
                const dot = item.querySelector('.notification-dot');
                if (dot) dot.remove();
            });
            
            // Mettre à jour le compteur
            updateNotificationCount();
        }
    })
    .catch(error => console.error('Error:', error));
}

function updateNotificationCount() {
    fetch('ajax/get-notification-count.php')
    .then(response => response.json())
    .then(data => {
        const countElement = document.querySelector('.notification-count');
        if (data.count > 0) {
            if (!countElement) {
                const bell = document.querySelector('.notification-bell');
                const countSpan = document.createElement('span');
                countSpan.className = 'notification-count';
                countSpan.textContent = data.count;
                bell.appendChild(countSpan);
            } else {
                countElement.textContent = data.count;
            }
        } else if (countElement) {
            countElement.remove();
        }
    })
    .catch(error => console.error('Error:', error));
}

// Fonction utilitaire pour formater la date
function time_ago(timestamp) {
    // Implémentation simple - à compléter
    return 'il y a un moment';
}

// Mettre à jour les notifications périodiquement (toutes les 30 secondes)
setInterval(updateNotificationCount, 30000);
</script>