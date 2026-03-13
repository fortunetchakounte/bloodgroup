<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/geolocation.php';

if (!isLoggedIn() || getUserRole() !== 'donor') {
    redirect('../public/login.php');
}

$page_title = "Requests Map - BloodLink";
$message = '';
$message_type = '';

// Récupérer les coordonnées du donneur
$stmt = $pdo->prepare("SELECT latitude, longitude, city FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$donor = $stmt->fetch();

// Si pas de coordonnées, géocoder
if (!$donor || !$donor['latitude'] || !$donor['longitude']) {
    updateUserCoordinates($_SESSION['user_id']);
    $stmt->execute([$_SESSION['user_id']]);
    $donor = $stmt->fetch();
}

// Récupérer le groupe sanguin du donneur
$blood_stmt = $pdo->prepare("SELECT blood_group_id FROM users WHERE id = ?");
$blood_stmt->execute([$_SESSION['user_id']]);
$blood_result = $blood_stmt->fetch();
$blood_group_id = $blood_result['blood_group_id'] ?? 0;

// Récupérer la distance max du filtre
$max_distance = isset($_GET['max_distance']) ? (int)$_GET['max_distance'] : 100;

// Récupérer les demandes compatibles avec localisation
$stmt = $pdo->prepare("
    SELECT dr.*, bg.name as blood_group_name, h.*,
           (6371 * acos(
                cos(radians(?)) * cos(radians(h.latitude)) 
                * cos(radians(h.longitude) - radians(?)) 
                + sin(radians(?)) * sin(radians(h.latitude))
            )) as distance_km
    FROM donation_requests dr
    JOIN blood_groups bg ON dr.blood_group_id = bg.id
    JOIN hospitals h ON dr.hospital_id = h.id
    WHERE dr.status = 'pending'
    AND dr.blood_group_id = ?
    AND h.latitude IS NOT NULL
    AND h.longitude IS NOT NULL
    AND h.is_verified = 1
    HAVING distance_km <= ?
    ORDER BY dr.urgency DESC, distance_km
    LIMIT 50
");

$stmt->execute([
    $donor['latitude'] ?? 48.8566, // Paris par défaut
    $donor['longitude'] ?? 2.3522,
    $donor['latitude'] ?? 48.8566,
    $blood_group_id,
    $max_distance
]);

$requests = $stmt->fetchAll();

// Préparer les marqueurs pour la carte
$markers = [];

// Marqueur du donneur
if (!empty($donor['latitude']) && !empty($donor['longitude'])) {
    $markers[] = [
        'lat' => $donor['latitude'],
        'lon' => $donor['longitude'],
        'icon' => "L.divIcon({html: '<div style=\"background: #4285F4; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3);\">❤️</div>', className: 'donor-marker', iconSize: [40, 40]})",
        'popup' => '<strong>Your location</strong><br>' . ($donor['city'] ?? '')
    ];
}

// Marqueurs des hôpitaux
foreach ($requests as $request) {
    $urgency_color = [
        'high' => '#dc3545',
        'emergency' => '#dc3545',
        'medium' => '#ffc107',
        'low' => '#28a745'
    ];
    
    $color = $urgency_color[$request['urgency']] ?? '#6c757d';
    
    $markers[] = [
        'lat' => $request['latitude'],
        'lon' => $request['longitude'],
        'icon' => "L.divIcon({
            html: '<div style=\"background: {$color}; color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);\">🩸</div>',
            className: 'hospital-marker',
            iconSize: [36, 36]
        })",
        'popup' => '
            <strong>' . htmlspecialchars($request['hospital_name']) . '</strong><br>
            Blood Group ' . htmlspecialchars($request['blood_group_name']) . '<br>
            Distance: ' . round($request['distance_km'], 1) . ' km<br>
            Urgency: ' . ucfirst($request['urgency']) . '<br>
            <a href="view-request.php?id=' . $request['id'] . '" style="color: #dc3545; text-decoration: none; font-weight: 600;">View Details →</a>
        '
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body {
            background: #f8f9fa;
        }
        
        .navbar {
            background: #dc3545;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .user-info {
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }
        
        .nav-right a {
            color: white;
            text-decoration: none;
            margin-left: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-right a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-header h1 {
            font-size: 2rem;
            color: #dc3545;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #dc3545;
            color: white;
        }
        
        .btn-primary:hover {
            background: #c82333;
        }
        
        .btn-outline {
            background: white;
            border: 1px solid #dc3545;
            color: #dc3545;
        }
        
        .btn-outline:hover {
            background: #dc3545;
            color: white;
        }
        
        .map-container {
            height: 600px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            position: relative;
        }
        
        #mainMap {
            width: 100%;
            height: 100%;
        }
        
        .map-sidebar {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: 600px;
            overflow-y: auto;
        }
        
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .sidebar-header h3 {
            font-size: 1.2rem;
            color: #333;
        }
        
        .request-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s;
            border-radius: 8px;
        }
        
        .request-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }
        
        .request-item:last-child {
            border-bottom: none;
        }
        
        .request-hospital {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .request-hospital strong {
            font-size: 1rem;
            color: #333;
        }
        
        .request-distance {
            background: #e9ecef;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #666;
        }
        
        .request-details {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: #666;
        }
        
        .urgency-high {
            color: #dc3545;
            font-weight: 600;
        }
        
        .urgency-medium {
            color: #ffc107;
            font-weight: 600;
        }
        
        .urgency-low {
            color: #28a745;
            font-weight: 600;
        }
        
        .map-legend {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            position: absolute;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
        }
        
        .distance-filter {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .distance-filter label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .distance-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .distance-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .distance-btn:hover,
        .distance-btn.active {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #e9ecef;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 992px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .map-container {
                height: 400px;
            }
            
            .map-sidebar {
                height: auto;
                max-height: 400px;
            }
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-right {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">
                <i class="fas fa-heartbeat"></i> BloodLink
            </div>
            <div class="user-info">
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Donor'); ?>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="requests.php"><i class="fas fa-tint"></i> Requests</a>
            <a href="history.php"><i class="fas fa-history"></i> History</a>
            <a href="../public/logout.php" style="border: 2px solid white;">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>
                <i class="fas fa-map-marked-alt"></i>
                Blood Requests Map
            </h1>
            <a href="requests.php" class="btn btn-outline">
                <i class="fas fa-list"></i> View as List
            </a>
        </div>
        
        <div class="grid-2">
            <!-- Map -->
            <div class="map-container" id="mainMap">
                <?php
                if (!empty($donor['latitude']) && !empty($donor['longitude'])) {
                    // La carte sera initialisée par JavaScript
                    echo '<div id="leafletMap" style="height: 100%; width: 100%;"></div>';
                } else {
                    echo '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">
                        <div style="text-align: center;">
                            <i class="fas fa-map-marker-alt" style="font-size: 3rem; margin-bottom: 1rem; color: #e9ecef;"></i>
                            <h3>Location not available</h3>
                            <p>Please complete your address in your profile.</p>
                            <a href="profile.php" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-edit"></i> Complete Profile
                            </a>
                        </div>
                    </div>';
                }
                ?>
            </div>
            
            <!-- Sidebar -->
            <div class="map-sidebar">
                <div class="sidebar-header">
                    <h3><i class="fas fa-list"></i> Requests nearby</h3>
                    <span class="badge" style="background: #dc3545; color: white; padding: 0.25rem 0.75rem; border-radius: 20px;">
                        <?php echo count($requests); ?> found
                    </span>
                </div>
                
                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-map-marked-alt"></i>
                        <h3>No requests nearby</h3>
                        <p>Try increasing the search radius</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <div class="request-item" 
                             onclick="focusOnMarker(<?php echo $request['latitude']; ?>, <?php echo $request['longitude']; ?>)">
                            <div class="request-hospital">
                                <strong><?php echo htmlspecialchars($request['hospital_name']); ?></strong>
                                <span class="request-distance"><?php echo round($request['distance_km'], 1); ?> km</span>
                            </div>
                            <div class="request-details">
                                <span>Group <?php echo htmlspecialchars($request['blood_group_name']); ?></span>
                                <span class="urgency-<?php echo $request['urgency']; ?>">
                                    <?php 
                                    if ($request['urgency'] === 'high' || $request['urgency'] === 'emergency') {
                                        echo '🔴 Urgent';
                                    } elseif ($request['urgency'] === 'medium') {
                                        echo '🟡 Medium';
                                    } else {
                                        echo '🟢 Low';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div style="margin-top: 0.5rem;">
                                <a href="view-request.php?id=<?php echo $request['id']; ?>" style="color: #dc3545; text-decoration: none; font-size: 0.9rem;">
                                    View details <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Map Controls -->
        <div class="map-legend">
            <h4 style="margin-bottom: 0.5rem;">Legend</h4>
            <div class="legend-item">
                <div class="legend-color" style="background: #dc3545;"></div>
                <span>Urgent request</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #ffc107;"></div>
                <span>Medium urgency</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #28a745;"></div>
                <span>Low urgency</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #4285F4;"></div>
                <span>Your location</span>
            </div>
        </div>
        
        <div class="distance-filter">
            <label>Search radius</label>
            <div class="distance-buttons">
                <button class="distance-btn <?php echo $max_distance == 10 ? 'active' : ''; ?>" onclick="setDistance(10)">10 km</button>
                <button class="distance-btn <?php echo $max_distance == 25 ? 'active' : ''; ?>" onclick="setDistance(25)">25 km</button>
                <button class="distance-btn <?php echo $max_distance == 50 ? 'active' : ''; ?>" onclick="setDistance(50)">50 km</button>
                <button class="distance-btn <?php echo $max_distance == 100 ? 'active' : ''; ?>" onclick="setDistance(100)">100 km</button>
            </div>
        </div>
    </div>

    <script>
        let map;
        let markers = [];
        
        // Initialize map
        function initMap() {
            <?php if (!empty($donor['latitude']) && !empty($donor['longitude'])): ?>
            const userLat = <?php echo $donor['latitude']; ?>;
            const userLon = <?php echo $donor['longitude']; ?>;
            
            // Create map
            map = L.map('leafletMap').setView([userLat, userLon], 12);
            
            // Add tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add user marker
            const userIcon = L.divIcon({
                html: '<div style="background: #4285F4; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3);">❤️</div>',
                className: 'user-marker',
                iconSize: [40, 40]
            });
            
            L.marker([userLat, userLon], { icon: userIcon })
                .addTo(map)
                .bindPopup('<strong>Your location</strong><br><?php echo addslashes($donor['city'] ?? ''); ?>');
            
            // Add hospital markers
            <?php foreach ($markers as $index => $marker): ?>
                <?php if ($index > 0): // Skip first marker (user) ?>
                    <?php
                    $popup = addslashes($marker['popup']);
                    ?>
                    const marker<?php echo $index; ?> = L.marker([<?php echo $marker['lat']; ?>, <?php echo $marker['lon']; ?>], {
                        icon: <?php echo $marker['icon']; ?>
                    }).addTo(map).bindPopup('<?php echo $popup; ?>');
                    markers.push(marker<?php echo $index; ?>);
                <?php endif; ?>
            <?php endforeach; ?>
            
            // Add circle for search radius
            L.circle([userLat, userLon], {
                color: '#dc3545',
                fillColor: '#dc3545',
                fillOpacity: 0.1,
                radius: <?php echo $max_distance * 1000; ?>
            }).addTo(map);
            
            <?php endif; ?>
        }
        
        // Focus on marker
        function focusOnMarker(lat, lon) {
            if (map) {
                map.setView([lat, lon], 15);
            }
        }
        
        // Set distance filter
        function setDistance(distance) {
            window.location.href = '?max_distance=' + distance;
        }
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($donor['latitude']) && !empty($donor['longitude'])): ?>
            initMap();
            <?php endif; ?>
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (map) {
                setTimeout(() => map.invalidateSize(), 100);
            }
        });
        
        // Geolocation
        function locateUser() {
            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    
                    fetch('ajax/update-location.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({latitude: lat, longitude: lon})
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        }
                    })
                    .catch(error => console.error('Error:', error));
                }, function(error) {
                    alert('Unable to get your location: ' + error.message);
                });
            } else {
                alert('Geolocation is not supported by your browser');
            }
        }
    </script>
</body>
</html>