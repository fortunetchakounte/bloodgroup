<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rewards.php';

if (!isLoggedIn() || getUserRole() !== 'donor') {
    redirect('../public/login.php');
}

$user_id = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'overview';

// Récupérer le profil XP
$xp_profile = getUserXPProfile($user_id);

// Récupérer les badges
$badges = getUserBadges($user_id);

// Classement
$leaderboard = getDonorLeaderboard(10, 'all');
$user_rank = null;
foreach ($leaderboard as $index => $user) {
    if ($user['user_id'] == $user_id) {
        $user_rank = $user['rank'];
        break;
    }
}

$page_title = "Mes récompenses";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        .rewards-container {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .level-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .level-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            opacity: 0.3;
        }
        
        .level-number {
            font-size: 4rem;
            font-weight: bold;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        .level-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .badges-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .badge-item {
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .badge-item:hover {
            transform: scale(1.1);
        }
        
        .badge-name {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-locked {
            opacity: 0.3;
            filter: grayscale(100%);
        }
        
        .badge-progress {
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: #666;
        }
        
        .progress-bar {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 0.25rem;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.3s;
        }
        
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .leaderboard-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        
        .leaderboard-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .leaderboard-table tr:hover {
            background: #f8f9fa;
        }
        
        .rank-1 { background: linear-gradient(135deg, #FFD700, #FFA500); color: white; }
        .rank-2 { background: linear-gradient(135deg, #C0C0C0, #A9A9A9); color: white; }
        .rank-3 { background: linear-gradient(135deg, #CD7F32, #A0522D); color: white; }
        
        .user-rank {
            background: #007bff;
            color: white;
            font-weight: bold;
        }
        
        .achievement-timeline {
            margin-top: 2rem;
        }
        
        .timeline-item {
            display: flex;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 30px;
            top: 40px;
            bottom: -25px;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item:last-child::before {
            display: none;
        }
        
        .timeline-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            border: 2px solid #007bff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
            z-index: 1;
        }
        
        .timeline-content {
            flex: 1;
            padding: 0.5rem 0;
        }
        
        .timeline-date {
            color: #666;
            font-size: 0.85rem;
        }
        
        .rewards-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
        }
        
        .rewards-tab {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            transition: all 0.3s;
        }
        
        .rewards-tab:hover {
            background: #f8f9fa;
            color: #333;
        }
        
        .rewards-tab.active {
            background: #007bff;
            color: white;
        }
        
        .next-rewards {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .next-level-rewards {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .next-reward-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <!-- Navigation standard -->
    </nav>

    <div class="container">
        <h1 style="color: #333; margin-bottom: 1rem;">🏆 Mes récompenses</h1>
        
        <!-- Carte du niveau -->
        <div class="level-card">
            <div class="level-number">Niveau <?php echo $xp_profile['level']; ?></div>
            <div class="level-title"><?php echo htmlspecialchars($xp_profile['level_title']); ?></div>
            
            <?php echo displayLevelProgress($xp_profile['current_xp'], $xp_profile['next_level_xp']); ?>
            
            <div style="display: flex; justify-content: space-between; margin-top: 1.5rem;">
                <div>
                    <div style="font-size: 0.9rem; opacity: 0.9;">XP Total</div>
                    <div style="font-size: 1.5rem; font-weight: bold;"><?php echo number_format($xp_profile['total_xp']); ?></div>
                </div>
                <div>
                    <div style="font-size: 0.9rem; opacity: 0.9;">Classement</div>
                    <div style="font-size: 1.5rem; font-weight: bold;">
                        <?php echo $user_rank ? '#' . $user_rank : 'Non classé'; ?>
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.9rem; opacity: 0.9;">Badges</div>
                    <div style="font-size: 1.5rem; font-weight: bold;">
                        <?php echo $xp_profile['badge_count']; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Onglets -->
        <div class="rewards-tabs">
            <button class="rewards-tab <?php echo $tab === 'overview' ? 'active' : ''; ?>" 
                    onclick="switchTab('overview')">
                📊 Vue d'ensemble
            </button>
            <button class="rewards-tab <?php echo $tab === 'badges' ? 'active' : ''; ?>" 
                    onclick="switchTab('badges')">
                🏆 Mes badges
            </button>
            <button class="rewards-tab <?php echo $tab === 'leaderboard' ? 'active' : ''; ?>" 
                    onclick="switchTab('leaderboard')">
                📈 Classement
            </button>
            <button class="rewards-tab <?php echo $tab === 'achievements' ? 'active' : ''; ?>" 
                    onclick="switchTab('achievements')">
                ⭐ Mes exploits
            </button>
        </div>
        
        <!-- Contenu des onglets -->
        <div class="rewards-container">
            <?php if ($tab === 'overview'): ?>
                <!-- Vue d'ensemble -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $xp_profile['total_donations']; ?></div>
                        <div class="stat-label">Dons effectués</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $xp_profile['unique_hospitals']; ?></div>
                        <div class="stat-label">Hôpitaux aidés</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $xp_profile['badge_count']; ?></div>
                        <div class="stat-label">Badges obtenus</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $xp_profile['level']; ?></div>
                        <div class="stat-label">Niveau atteint</div>
                    </div>
                </div>
                
                <!-- Prochaines récompenses -->
                <div class="next-rewards">
                    <h3 style="margin-bottom: 1rem; color: #333;">🎯 Prochaines récompenses</h3>
                    <p>Récompenses à débloquer au niveau <?php echo $xp_profile['level'] + 1; ?> :</p>
                    
                    <?php
                    $next_level = $xp_profile['level'] + 1;
                    $stmt = $pdo->prepare("SELECT * FROM level_rewards WHERE level = ?");
                    $stmt->execute([$next_level]);
                    $next_rewards = $stmt->fetchAll();
                    ?>
                    
                    <div class="next-level-rewards">
                        <?php if (empty($next_rewards)): ?>
                            <p style="color: #666; font-style: italic;">
                                Aucune récompense spécifique prévue pour le prochain niveau.
                                Continuez vos dons pour progresser !
                            </p>
                        <?php else: ?>
                            <?php foreach ($next_rewards as $reward): ?>
                                <div class="next-reward-item">
                                    <span><?php echo $reward['icon']; ?></span>
                                    <span><?php echo htmlspecialchars($reward['description']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php elseif ($tab === 'badges'): ?>
                <!-- Mes badges -->
                <h3 style="margin-bottom: 1.5rem; color: #333;">🏆 Mes badges</h3>
                
                <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
                    <button class="btn-filter active" data-category="all">Tous</button>
                    <button class="btn-filter" data-category="donation">Dons</button>
                    <button class="btn-filter" data-category="engagement">Engagement</button>
                    <button class="btn-filter" data-category="achievement">Exploits</button>
                    <button class="btn-filter" data-category="special">Spéciaux</button>
                </div>
                
                <div class="badges-container" id="badgesList">
                    <?php foreach ($badges as $badge): ?>
                        <div class="badge-item" data-category="<?php echo $badge['category']; ?>">
                            <?php echo displayBadge($badge, 'medium', true); ?>
                            <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                            <div class="badge-date">
                                <?php echo date('d/m/Y', strtotime($badge['earned_at'])); ?>
                            </div>
                            <?php if ($badge['progress'] < 100): ?>
                                <div class="badge-progress">
                                    Progression : <?php echo $badge['progress']; ?>%
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $badge['progress']; ?>%;"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Badges non débloqués -->
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT b.* 
                        FROM badges b
                        WHERE b.id NOT IN (
                            SELECT badge_id FROM user_badges WHERE user_id = ?
                        )
                        ORDER BY b.requirement_value
                    ");
                    $stmt->execute([$user_id]);
                    $locked_badges = $stmt->fetchAll();
                    ?>
                    
                    <?php foreach ($locked_badges as $badge): ?>
                        <div class="badge-item badge-locked" data-category="<?php echo $badge['category']; ?>">
                            <?php echo displayBadge($badge, 'medium', true); ?>
                            <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                            <div class="badge-progress">
                                <?php echo htmlspecialchars($badge['description']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php elseif ($tab === 'leaderboard'): ?>
                <!-- Classement -->
                <h3 style="margin-bottom: 1.5rem; color: #333;">📈 Classement des donneurs</h3>
                
                <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
                    <button class="btn-timeframe active" data-timeframe="all">Tous les temps</button>
                    <button class="btn-timeframe" data-timeframe="month">Ce mois-ci</button>
                    <button class="btn-timeframe" data-timeframe="week">Cette semaine</button>
                </div>
                
                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th width="80">Rang</th>
                            <th>Donneur</th>
                            <th width="120">Niveau</th>
                            <th width="120">XP Total</th>
                            <th width="100">Dons</th>
                            <th width="100">Badges</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $index => $donor): 
                            $rank_class = '';
                            if ($donor['rank'] == 1) $rank_class = 'rank-1';
                            elseif ($donor['rank'] == 2) $rank_class = 'rank-2';
                            elseif ($donor['rank'] == 3) $rank_class = 'rank-3';
                            elseif ($donor['user_id'] == $user_id) $rank_class = 'user-rank';
                        ?>
                            <tr class="<?php echo $rank_class; ?>">
                                <td>
                                    <?php if ($donor['rank'] <= 3): ?>
                                        <span style="font-size: 1.5rem;">🥇🥈🥉</span>
                                    <?php else: ?>
                                        #<?php echo $donor['rank']; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <?php if ($donor['profile_picture']): ?>
                                            <img src="../uploads/<?php echo $donor['profile_picture']; ?>" 
                                                 style="width: 40px; height: 40px; border-radius: 50%;">
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; border-radius: 50%; 
                                                        background: #007bff; color: white; 
                                                        display: flex; align-items: center; 
                                                        justify-content: center;">
                                                <?php echo substr($donor['full_name'], 0, 1); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($donor['full_name']); ?></div>
                                            <small style="color: <?php echo $rank_class ? 'rgba(255,255,255,0.8)' : '#666'; ?>">
                                                <?php echo $donor['level_title']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: bold; font-size: 1.2rem;">
                                        <?php echo $donor['level']; ?>
                                    </div>
                                </td>
                                <td><?php echo number_format($donor['total_xp']); ?></td>
                                <td><?php echo $donor['total_donations']; ?></td>
                                <td><?php echo $donor['badge_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($tab === 'achievements'): ?>
                <!-- Mes exploits -->
                <h3 style="margin-bottom: 1.5rem; color: #333;">⭐ Historique de mes exploits</h3>
                
                <div class="achievement-timeline">
                    <?php
                    // Récupérer l'historique des succès
                    $stmt = $pdo->prepare("
                        SELECT ub.earned_at, b.*
                        FROM user_badges ub
                        JOIN badges b ON ub.badge_id = b.id
                        WHERE ub.user_id = ?
                        ORDER BY ub.earned_at DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$user_id]);
                    $achievements = $stmt->fetchAll();
                    ?>
                    
                    <?php foreach ($achievements as $achievement): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <?php echo $achievement['icon']; ?>
                            </div>
                            <div class="timeline-content">
                                <div style="font-weight: bold; margin-bottom: 0.25rem;">
                                    <?php echo htmlspecialchars($achievement['name']); ?>
                                </div>
                                <div style="color: #666; margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($achievement['description']); ?>
                                </div>
                                <div class="timeline-date">
                                    <?php echo date('d/m/Y à H:i', strtotime($achievement['earned_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php endif; ?>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            window.location.href = 'rewards.php?tab=' + tabName;
        }
        
        // Filtrage des badges
        document.querySelectorAll('.btn-filter').forEach(button => {
            button.addEventListener('click', function() {
                const category = this.dataset.category;
                
                // Mettre à jour les boutons actifs
                document.querySelectorAll('.btn-filter').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                // Filtrer les badges
                document.querySelectorAll('.badge-item').forEach(badge => {
                    if (category === 'all' || badge.dataset.category === category) {
                        badge.style.display = 'block';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            });
        });
        
        // Filtrage du classement par période
        document.querySelectorAll('.btn-timeframe').forEach(button => {
            button.addEventListener('click', function() {
                const timeframe = this.dataset.timeframe;
                
                // Mettre à jour les boutons actifs
                document.querySelectorAll('.btn-timeframe').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                // Recharger avec le nouveau filtre
                window.location.href = 'rewards.php?tab=leaderboard&timeframe=' + timeframe;
            });
        });
        
        // Effets visuels pour les badges
        document.querySelectorAll('.badge-item').forEach(badge => {
            badge.addEventListener('mouseenter', function() {
                const tooltip = this.querySelector('.badge-tooltip');
                if (tooltip) {
                    tooltip.style.opacity = '1';
                }
            });
            
            badge.addEventListener('mouseleave', function() {
                const tooltip = this.querySelector('.badge-tooltip');
                if (tooltip) {
                    tooltip.style.opacity = '0';
                }
            });
        });
    </script>
</body>
</html>