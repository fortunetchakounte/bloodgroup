<?php
// Système de récompenses et badges

/**
 * Calculer l'XP gagné pour un don
 */
function calculateDonationXP($donation_id) {
    global $pdo;
    
    // Récupérer les infos du don
    $stmt = $pdo->prepare("
        SELECT d.*, dr.urgency 
        FROM donations d
        JOIN donation_requests dr ON d.request_id = dr.id
        WHERE d.id = ?
    ");
    $stmt->execute([$donation_id]);
    $donation = $stmt->fetch();
    
    if (!$donation) return 0;
    
    // XP de base
    $base_xp = 50;
    
    // Bonus d'urgence
    $urgency_bonus = [
        'high' => 30,
        'medium' => 15,
        'low' => 5
    ];
    
    // Bonus de distance (si applicable)
    $distance_bonus = 0;
    if (isset($donation['distance_km'])) {
        $distance_bonus = min(20, floor($donation['distance_km'] / 10));
    }
    
    // Multiplicateur d'événement
    $event_multiplier = getCurrentEventMultiplier();
    
    // XP total
    $total_xp = ($base_xp + $urgency_bonus[$donation['urgency']] + $distance_bonus) * $event_multiplier;
    
    // Arrondir
    return round($total_xp);
}

/**
 * Ajouter de l'XP à un utilisateur
 */
function addUserXP($user_id, $xp_amount, $source = 'donation', $source_id = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier si l'utilisateur a déjà un profil XP
        $stmt = $pdo->prepare("SELECT * FROM user_xp WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_xp = $stmt->fetch();
        
        if (!$user_xp) {
            // Créer le profil XP
            $stmt = $pdo->prepare("
                INSERT INTO user_xp (user_id, current_xp, total_xp, level, next_level_xp)
                VALUES (?, ?, ?, 1, 100)
            ");
            $stmt->execute([$user_id, $xp_amount, $xp_amount]);
            $user_xp_id = $pdo->lastInsertId();
            
            // Récupérer le nouveau profil
            $stmt->execute([$user_id]);
            $user_xp = $stmt->fetch();
        } else {
            // Mettre à jour l'XP
            $new_xp = $user_xp['current_xp'] + $xp_amount;
            $new_total = $user_xp['total_xp'] + $xp_amount;
            $new_level = $user_xp['level'];
            $next_level_xp = $user_xp['next_level_xp'];
            
            // Vérifier le level up
            $levels_gained = 0;
            while ($new_xp >= $next_level_xp) {
                $new_xp -= $next_level_xp;
                $new_level++;
                $levels_gained++;
                $next_level_xp = calculateNextLevelXP($new_level);
            }
            
            $stmt = $pdo->prepare("
                UPDATE user_xp 
                SET current_xp = ?, 
                    total_xp = ?, 
                    level = ?, 
                    next_level_xp = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$new_xp, $new_total, $new_level, $next_level_xp, $user_id]);
            
            // Si level up, distribuer les récompenses
            if ($levels_gained > 0) {
                for ($i = 1; $i <= $levels_gained; $i++) {
                    grantLevelRewards($user_id, $user_xp['level'] + $i);
                }
                
                // Créer une notification pour le level up
                createNotification(
                    $user_id,
                    'donor',
                    "🎉 Félicitations ! Vous êtes passé niveau $new_level !",
                    "Vous avez gagné $levels_gained niveau(x) ! Découvrez vos nouvelles récompenses.",
                    'success',
                    '⭐',
                    'profile.php?tab=rewards'
                );
            }
        }
        
        // Journaliser le gain d'XP
        $stmt = $pdo->prepare("
            INSERT INTO xp_logs (user_id, xp_amount, source, source_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $xp_amount, $source, $source_id]);
        
        // Vérifier les badges débloqués
        checkAndGrantBadges($user_id, $source, $source_id);
        
        $pdo->commit();
        
        return [
            'xp_gained' => $xp_amount,
            'new_level' => $new_level ?? $user_xp['level'],
            'current_xp' => $new_xp ?? $user_xp['current_xp'],
            'next_level_xp' => $next_level_xp ?? $user_xp['next_level_xp'],
            'levels_gained' => $levels_gained
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur ajout XP: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculer l'XP nécessaire pour le prochain niveau
 */
function calculateNextLevelXP($current_level) {
    // Formule progressive : XP requis augmente avec le niveau
    return round(100 * pow(1.1, $current_level - 1));
}

/**
 * Vérifier et attribuer les badges
 */
function checkAndGrantBadges($user_id, $trigger_source = null, $source_id = null) {
    global $pdo;
    
    // Récupérer les statistiques actuelles de l'utilisateur
    $stats = getUserDonationStats($user_id);
    
    // Récupérer les badges non encore obtenus
    $stmt = $pdo->prepare("
        SELECT b.* 
        FROM badges b
        WHERE b.id NOT IN (
            SELECT badge_id FROM user_badges WHERE user_id = ?
        )
    ");
    $stmt->execute([$user_id]);
    $available_badges = $stmt->fetchAll();
    
    $new_badges = [];
    
    foreach ($available_badges as $badge) {
        $grant = false;
        $progress = 0;
        
        switch ($badge['requirement_type']) {
            case 'donation_count':
                if ($stats['total_donations'] >= $badge['requirement_value']) {
                    $grant = true;
                }
                $progress = min(100, round(($stats['total_donations'] / $badge['requirement_value']) * 100));
                break;
                
            case 'consecutive_donations':
                if ($stats['consecutive_donations'] >= $badge['requirement_value']) {
                    $grant = true;
                }
                $progress = min(100, round(($stats['consecutive_donations'] / $badge['requirement_value']) * 100));
                break;
                
            case 'urgent_responses':
                if ($stats['urgent_responses'] >= $badge['requirement_value']) {
                    $grant = true;
                }
                $progress = min(100, round(($stats['urgent_responses'] / $badge['requirement_value']) * 100));
                break;
                
            case 'fast_responses':
                if ($stats['fast_responses'] >= $badge['requirement_value']) {
                    $grant = true;
                }
                $progress = min(100, round(($stats['fast_responses'] / $badge['requirement_value']) * 100));
                break;
                
            case 'helped_hospitals':
                if ($stats['helped_hospitals'] >= $badge['requirement_value']) {
                    $grant = true;
                }
                $progress = min(100, round(($stats['helped_hospitals'] / $badge['requirement_value']) * 100));
                break;
                
            case 'special_event':
                // Vérifier les événements spéciaux actuels
                $grant = checkSpecialEventBadge($user_id, $badge['badge_code']);
                $progress = $grant ? 100 : 0;
                break;
        }
        
        if ($grant) {
            // Attribuer le badge
            grantBadge($user_id, $badge['id'], $progress);
            $new_badges[] = $badge;
            
            // Ajouter l'XP du badge
            addUserXP($user_id, $badge['xp_reward'], 'badge', $badge['id']);
            
            // Notification
            createNotification(
                $user_id,
                'donor',
                "🏆 Nouveau badge débloqué : {$badge['name']}",
                $badge['description'],
                'info',
                $badge['icon'],
                'profile.php?tab=badges'
            );
        }
    }
    
    return $new_badges;
}

/**
 * Attribuer un badge à un utilisateur
 */
function grantBadge($user_id, $badge_id, $progress = 100) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO user_badges (user_id, badge_id, progress)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE progress = GREATEST(progress, ?)
    ");
    
    return $stmt->execute([$user_id, $badge_id, $progress, $progress]);
}

/**
 * Récupérer les statistiques de dons d'un utilisateur
 */
function getUserDonationStats($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM donation_stats 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    if (!$stats) {
        // Créer les stats si elles n'existent pas
        $stats = [
            'total_donations' => 0,
            'consecutive_donations' => 0,
            'last_donation_date' => null,
            'helped_hospitals' => 0,
            'urgent_responses' => 0,
            'fast_responses' => 0
        ];
    }
    
    return $stats;
}

/**
 * Mettre à jour les statistiques après un don
 */
function updateDonationStats($user_id, $donation_data) {
    global $pdo;
    
    $current_stats = getUserDonationStats($user_id);
    
    // Calculer la nouvelle série de dons consécutifs
    $consecutive_donations = $current_stats['consecutive_donations'];
    $last_donation_date = $current_stats['last_donation_date'];
    
    if ($last_donation_date) {
        $last_date = new DateTime($last_donation_date);
        $current_date = new DateTime($donation_data['donation_date']);
        $interval = $last_date->diff($current_date);
        
        // Si le don précédent était il y a moins de 4 mois, c'est consécutif
        if ($interval->days <= 120) {
            $consecutive_donations++;
        } else {
            $consecutive_donations = 1;
        }
    } else {
        $consecutive_donations = 1;
    }
    
    // Compter les hôpitaux uniques aidés
    $helped_hospitals = $current_stats['helped_hospitals'];
    if (!hasUserHelpedHospital($user_id, $donation_data['hospital_id'])) {
        $helped_hospitals++;
    }
    
    // Vérifier si c'était une réponse rapide (< 24h)
    $fast_response = 0;
    if (isset($donation_data['response_hours']) && $donation_data['response_hours'] < 24) {
        $fast_response = 1;
    }
    
    // Vérifier si c'était une demande urgente
    $urgent_response = $donation_data['urgency'] === 'high' ? 1 : 0;
    
    // Mettre à jour la base de données
    $stmt = $pdo->prepare("
        INSERT INTO donation_stats 
        (user_id, total_donations, consecutive_donations, last_donation_date, 
         helped_hospitals, urgent_responses, fast_responses)
        VALUES (?, 1, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_donations = total_donations + 1,
            consecutive_donations = ?,
            last_donation_date = ?,
            helped_hospitals = ?,
            urgent_responses = urgent_responses + ?,
            fast_responses = fast_responses + ?
    ");
    
    return $stmt->execute([
        $user_id,
        $consecutive_donations,
        $donation_data['donation_date'],
        $helped_hospitals,
        $urgent_response,
        $fast_response,
        $consecutive_donations,
        $donation_data['donation_date'],
        $helped_hospitals,
        $urgent_response,
        $fast_response
    ]);
}

/**
 * Attribuer les récompenses de niveau
 */
function grantLevelRewards($user_id, $level) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM level_rewards 
        WHERE level = ?
    ");
    $stmt->execute([$level]);
    $rewards = $stmt->fetchAll();
    
    foreach ($rewards as $reward) {
        switch ($reward['reward_type']) {
            case 'badge':
                // Rechercher le badge par son code
                $badge_stmt = $pdo->prepare("SELECT id FROM badges WHERE badge_code = ?");
                $badge_stmt->execute([$reward['reward_value']]);
                $badge = $badge_stmt->fetch();
                
                if ($badge) {
                    grantBadge($user_id, $badge['id']);
                }
                break;
                
            case 'title':
                // Mettre à jour le titre dans user_xp
                $pdo->prepare("
                    UPDATE user_xp 
                    SET level_title = ? 
                    WHERE user_id = ?
                ")->execute([$reward['description'], $user_id]);
                break;
                
            case 'feature':
                // Activer une fonctionnalité spéciale
                activateUserFeature($user_id, $reward['reward_value']);
                break;
                
            case 'bonus':
                // Appliquer un bonus temporaire
                applyTemporaryBonus($user_id, $reward['reward_value']);
                break;
        }
    }
}

/**
 * Obtenir les badges de l'utilisateur
 */
function getUserBadges($user_id, $category = null) {
    global $pdo;
    
    $sql = "
        SELECT b.*, ub.earned_at, ub.progress, ub.is_equipped
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.user_id = ?
    ";
    
    if ($category) {
        $sql .= " AND b.category = ?";
        $sql .= " ORDER BY ub.earned_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $category]);
    } else {
        $sql .= " ORDER BY ub.earned_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
    }
    
    return $stmt->fetchAll();
}

/**
 * Obtenir le profil XP de l'utilisateur
 */
function getUserXPProfile($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT ux.*, 
               (SELECT COUNT(*) FROM user_badges WHERE user_id = ?) as badge_count,
               (SELECT COUNT(DISTINCT hospital_id) FROM donations WHERE user_id = ?) as unique_hospitals,
               (SELECT COUNT(*) FROM donations WHERE user_id = ?) as total_donations
        FROM user_xp ux
        WHERE ux.user_id = ?
    ");
    
    $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    return $stmt->fetch();
}

/**
 * Obtenir le classement des donneurs
 */
function getDonorLeaderboard($limit = 20, $timeframe = 'all') {
    global $pdo;
    
    $where_clause = '';
    switch ($timeframe) {
        case 'month':
            $where_clause = "WHERE DATE(ux.updated_at) >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'week':
            $where_clause = "WHERE DATE(ux.updated_at) >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
    }
    
    $sql = "
        SELECT ux.*, u.full_name, u.profile_picture,
               (SELECT COUNT(*) FROM user_badges ub WHERE ub.user_id = ux.user_id) as badge_count,
               ROW_NUMBER() OVER (ORDER BY ux.total_xp DESC) as rank
        FROM user_xp ux
        JOIN users u ON ux.user_id = u.id
        $where_clause
        ORDER BY ux.total_xp DESC
        LIMIT ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Vérifier les événements spéciaux actuels
 */
function getCurrentEventMultiplier() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(xp_multiplier), 1.0) as multiplier
        FROM special_events
        WHERE is_active = TRUE
        AND CURDATE() BETWEEN start_date AND end_date
    ");
    
    $stmt->execute();
    $result = $stmt->fetch();
    
    return $result['multiplier'];
}

/**
 * Activer une fonctionnalité spéciale
 */
function activateUserFeature($user_id, $feature_code) {
    global $pdo;
    
    // Insérer dans user_features si la table existe
    // Sinon, utiliser la session ou une autre méthode
    $features = [
        'priority_notifications' => [
            'description' => 'Vos notifications seront prioritaires',
            'expires_at' => null // permanent
        ],
        'custom_profile' => [
            'description' => 'Personnalisation avancée du profil',
            'expires_at' => null
        ]
    ];
    
    if (isset($features[$feature_code])) {
        // Stocker dans la session pour cet exemple
        $_SESSION['user_features'][$feature_code] = $features[$feature_code];
        return true;
    }
    
    return false;
}

/**
 * Afficher un badge dans l'interface
 */
function displayBadge($badge, $size = 'medium', $show_tooltip = true) {
    $sizes = [
        'small' => 'width: 40px; height: 40px; font-size: 20px;',
        'medium' => 'width: 60px; height: 60px; font-size: 30px;',
        'large' => 'width: 80px; height: 80px; font-size: 40px;'
    ];
    
    $style = $sizes[$size] ?? $sizes['medium'];
    
    $html = '<div class="badge-container" style="position: relative;">';
    $html .= '<div class="badge-icon" style="' . $style . ' 
                background: ' . $badge['color'] . ';
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                box-shadow: 0 4px 10px rgba(0,0,0,0.2);
                margin: 0 auto;">';
    $html .= $badge['icon'];
    $html .= '</div>';
    
    if ($show_tooltip) {
        $html .= '<div class="badge-tooltip" style="
                    position: absolute;
                    bottom: 100%;
                    left: 50%;
                    transform: translateX(-50%);
                    background: rgba(0,0,0,0.9);
                    color: white;
                    padding: 0.5rem;
                    border-radius: 5px;
                    font-size: 0.8rem;
                    white-space: nowrap;
                    opacity: 0;
                    transition: opacity 0.3s;
                    pointer-events: none;
                    z-index: 1000;">
                    <strong>' . htmlspecialchars($badge['name']) . '</strong><br>
                    <small>' . htmlspecialchars($badge['description']) . '</small>
                </div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Afficher la barre de progression du niveau
 */
function displayLevelProgress($current_xp, $next_level_xp) {
    $percentage = ($current_xp / $next_level_xp) * 100;
    
    $html = '<div style="margin: 1rem 0;">';
    $html .= '<div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">';
    $html .= '<span>XP: ' . $current_xp . '/' . $next_level_xp . '</span>';
    $html .= '<span>' . round($percentage, 1) . '%</span>';
    $html .= '</div>';
    $html .= '<div style="height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden;">';
    $html .= '<div style="height: 100%; width: ' . $percentage . '%; 
                background: linear-gradient(90deg, #007bff, #6610f2); 
                transition: width 0.5s ease-in-out;"></div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
?>