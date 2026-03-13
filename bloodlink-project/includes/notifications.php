<?php
// Fonctions de gestion des notifications

/**
 * Créer une nouvelle notification
 */
function createNotification($pdo, $user_id, $user_type, $title, $message, $type = 'info', $icon = null, $link = null, $expires_hours = 168) {
    try {
        $expires_at = $expires_hours > 0 
            ? date('Y-m-d H:i:s', strtotime("+{$expires_hours} hours"))
            : null;
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, user_type, title, message, type, icon, link, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $user_type,
            $title,
            $message,
            $type,
            $icon,
            $link,
            $expires_at
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupérer les notifications d'un utilisateur
 */
function getUserNotifications($pdo, $user_id, $user_type, $limit = 20, $unread_only = false) {
    try {
        $sql = "
            SELECT * FROM notifications 
            WHERE user_id = ? AND user_type = ? 
            AND (expires_at IS NULL OR expires_at > NOW())
        ";
        
        if ($unread_only) {
            $sql .= " AND is_read = FALSE";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $user_type, $limit]);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Erreur récupération notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Compter les notifications non lues
 */
function countUnreadNotifications($pdo, $user_id, $user_type) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND user_type = ? 
            AND is_read = FALSE
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        
        $stmt->execute([$user_id, $user_type]);
        return $stmt->fetch()['count'];
        
    } catch (PDOException $e) {
        error_log("Erreur comptage notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Marquer une notification comme lue
 */
function markNotificationAsRead($pdo, $notification_id, $user_id = null) {
    try {
        $sql = "UPDATE notifications SET is_read = TRUE WHERE id = ?";
        $params = [$notification_id];
        
        if ($user_id) {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
        
    } catch (PDOException $e) {
        error_log("Erreur marquage notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Marquer toutes les notifications comme lues
 */
function markAllNotificationsAsRead($pdo, $user_id, $user_type) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE user_id = ? AND user_type = ? AND is_read = FALSE
        ");
        
        return $stmt->execute([$user_id, $user_type]);
        
    } catch (PDOException $e) {
        error_log("Erreur marquage toutes notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Supprimer les notifications expirées
 */
function cleanupExpiredNotifications($pdo) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE expires_at IS NOT NULL AND expires_at < NOW()
        ");
        
        return $stmt->execute();
        
    } catch (PDOException $e) {
        error_log("Erreur nettoyage notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupérer les préférences de notification
 */
function getNotificationPreferences($pdo, $user_id, $user_type) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notification_preferences 
            WHERE user_id = ? AND user_type = ?
        ");
        
        $stmt->execute([$user_id, $user_type]);
        $prefs = $stmt->fetch();
        
        if (!$prefs) {
            // Créer des préférences par défaut
            $default_prefs = [
                'email_notifications' => true,
                'push_notifications' => true,
                'new_request_notify' => true,
                'response_notify' => true,
                'urgent_notify' => true,
                'system_notify' => true
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO notification_preferences 
                (user_id, user_type, email_notifications, push_notifications, 
                 new_request_notify, response_notify, urgent_notify, system_notify)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user_id, $user_type,
                $default_prefs['email_notifications'],
                $default_prefs['push_notifications'],
                $default_prefs['new_request_notify'],
                $default_prefs['response_notify'],
                $default_prefs['urgent_notify'],
                $default_prefs['system_notify']
            ]);
            
            return $default_prefs;
        }
        
        return $prefs;
        
    } catch (PDOException $e) {
        error_log("Erreur récupération préférences: " . $e->getMessage());
        return null;
    }
}

/**
 * Mettre à jour les préférences de notification
 */
function updateNotificationPreferences($pdo, $user_id, $user_type, $preferences) {
    try {
        $fields = [];
        $values = [];
        
        foreach ($preferences as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value ? 1 : 0;
        }
        
        $values[] = $user_id;
        $values[] = $user_type;
        
        $sql = "UPDATE notification_preferences 
                SET " . implode(', ', $fields) . " 
                WHERE user_id = ? AND user_type = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
        
    } catch (PDOException $e) {
        error_log("Erreur mise à jour préférences: " . $e->getMessage());
        return false;
    }
}

/**
 * Notifier les donneurs compatibles d'une nouvelle demande
 */
function notifyCompatibleDonorsNewRequest($pdo, $request_id) {
    try {
        // Récupérer les infos de la demande
        $stmt = $pdo->prepare("
            SELECT dr.*, bg.name as blood_group, h.hospital_name, h.city
            FROM donation_requests dr
            JOIN blood_groups bg ON dr.blood_group_id = bg.id
            JOIN hospitals h ON dr.hospital_id = h.id
            WHERE dr.id = ?
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) return 0;
        
        // Trouver les donneurs compatibles dans la même ville
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.email, p.new_request_notify
            FROM users u
            LEFT JOIN notification_preferences p ON u.id = p.user_id AND p.user_type = 'donor'
            WHERE u.user_type = 'donor'
            AND u.is_verified = TRUE
            AND u.can_donate = TRUE
            AND u.blood_group_id = ?
            AND u.city = ?
            AND (p.new_request_notify IS NULL OR p.new_request_notify = TRUE)
        ");
        
        $stmt->execute([$request['blood_group_id'], $request['city']]);
        $donors = $stmt->fetchAll();
        
        $notified_count = 0;
        
        foreach ($donors as $donor) {
            $urgency_text = [
                'high' => 'urgente',
                'medium' => 'moyenne',
                'low' => 'normale'
            ];
            
            $title = "🩸 Nouvelle demande de sang " . $urgency_text[$request['urgency']];
            $message = "L'hôpital " . htmlspecialchars($request['hospital_name']) . " à " . 
                      htmlspecialchars($request['city']) . " recherche du sang de groupe " . 
                      htmlspecialchars($request['blood_group']) . " (" . $request['quantity'] . " poche(s)).";
            
            $link = "../donor/requests.php?highlight=" . $request_id;
            
            createNotification(
                $pdo,
                $donor['id'],
                'donor',
                $title,
                $message,
                $request['urgency'] === 'high' ? 'urgent' : 'warning',
                '🩸',
                $link,
                72 // Expire après 3 jours
            );
            
            $notified_count++;
        }
        
        return $notified_count;
        
    } catch (PDOException $e) {
        error_log("Erreur notification donneurs: " . $e->getMessage());
        return 0;
    }
}

/**
 * Notifier un hôpital d'une nouvelle réponse
 */
function notifyHospitalNewResponse($pdo, $donation_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, u.full_name as donor_name, dr.hospital_id, h.hospital_name
            FROM donations d
            JOIN users u ON d.donor_id = u.id
            JOIN donation_requests dr ON d.request_id = dr.id
            JOIN hospitals h ON dr.hospital_id = h.id
            WHERE d.id = ?
        ");
        $stmt->execute([$donation_id]);
        $donation = $stmt->fetch();
        
        if (!$donation) return false;
        
        // Vérifier les préférences de l'hôpital
        $pref_stmt = $pdo->prepare("
            SELECT response_notify FROM notification_preferences 
            WHERE user_id = ? AND user_type = 'hospital'
        ");
        $pref_stmt->execute([$donation['hospital_id']]);
        $prefs = $pref_stmt->fetch();
        
        if ($prefs && !$prefs['response_notify']) {
            return false;
        }
        
        $title = "✅ Nouvelle réponse à votre demande";
        $message = htmlspecialchars($donation['donor_name']) . " a répondu à votre demande de sang.";
        $link = "../hospital/view-request.php?id=" . $donation['request_id'];
        
        return createNotification(
            $pdo,
            $donation['hospital_id'],
            'hospital',
            $title,
            $message,
            'success',
            '❤️',
            $link,
            168 // Expire après 7 jours
        );
        
    } catch (PDOException $e) {
        error_log("Erreur notification hôpital: " . $e->getMessage());
        return false;
    }
}

/**
 * Notifier un donneur du statut de sa réponse
 */
function notifyDonorResponseStatus($pdo, $donation_id, $status) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, dr.blood_group_id, bg.name as blood_group, h.hospital_name
            FROM donations d
            JOIN donation_requests dr ON d.request_id = dr.id
            JOIN blood_groups bg ON dr.blood_group_id = bg.id
            JOIN hospitals h ON dr.hospital_id = h.id
            WHERE d.id = ?
        ");
        $stmt->execute([$donation_id]);
        $donation = $stmt->fetch();
        
        if (!$donation) return false;
        
        $status_messages = [
            'confirmed' => [
                'title' => '✅ Don confirmé par l\'hôpital',
                'message' => 'Votre don à ' . htmlspecialchars($donation['hospital_name']) . ' a été confirmé.',
                'type' => 'success',
                'icon' => '✅'
            ],
            'cancelled' => [
                'title' => '❌ Don annulé',
                'message' => 'Votre don à ' . htmlspecialchars($donation['hospital_name']) . ' a été annulé.',
                'type' => 'danger',
                'icon' => '❌'
            ],
            'completed' => [
                'title' => '🏆 Don effectué avec succès',
                'message' => 'Merci pour votre don à ' . htmlspecialchars($donation['hospital_name']) . ' ! Vous avez potentiellement sauvé 3 vies.',
                'type' => 'success',
                'icon' => '🏆'
            ]
        ];
        
        if (!isset($status_messages[$status])) return false;
        
        $msg = $status_messages[$status];
        $link = "../donor/view-response.php?id=" . $donation_id;
        
        return createNotification(
            $pdo,
            $donation['donor_id'],
            'donor',
            $msg['title'],
            $msg['message'],
            $msg['type'],
            $msg['icon'],
            $link,
            168
        );
        
    } catch (PDOException $e) {
        error_log("Erreur notification statut donneur: " . $e->getMessage());
        return false;
    }
}
?>