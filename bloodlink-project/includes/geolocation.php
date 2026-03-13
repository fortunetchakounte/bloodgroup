<?php
// Fonctions de géolocalisation

/**
 * Géocoder une adresse (utilise Nominatim/OpenStreetMap)
 */
function geocodeAddress($address, $use_cache = true) {
    global $pdo;
    
    // Vérifier le cache
    $address_hash = hash('sha256', $address);
    
    if ($use_cache) {
        $stmt = $pdo->prepare("SELECT * FROM geocoding_cache WHERE address_hash = ?");
        $stmt->execute([$address_hash]);
        $cached = $stmt->fetch();
        
        if ($cached && strtotime($cached['cached_at']) > strtotime('-30 days')) {
            return [
                'latitude' => (float)$cached['latitude'],
                'longitude' => (float)$cached['longitude'],
                'city' => $cached['city'],
                'country' => $cached['country'],
                'cached' => true
            ];
        }
    }
    
    // Géocoder via Nominatim (OpenStreetMap)
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($address) . "&limit=1";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'BloodLinkApp/1.0',
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (!empty($data[0])) {
        $result = [
            'latitude' => (float)$data[0]['lat'],
            'longitude' => (float)$data[0]['lon'],
            'city' => $data[0]['address']['city'] ?? $data[0]['address']['town'] ?? $data[0]['address']['village'] ?? '',
            'country' => $data[0]['address']['country'] ?? '',
            'cached' => false
        ];
        
        // Mettre en cache
        $stmt = $pdo->prepare("
            INSERT INTO geocoding_cache (address_hash, address, latitude, longitude, city, country) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                city = VALUES(city),
                country = VALUES(country),
                cached_at = NOW()
        ");
        
        $stmt->execute([
            $address_hash,
            $address,
            $result['latitude'],
            $result['longitude'],
            $result['city'],
            $result['country']
        ]);
        
        return $result;
    }
    
    return null;
}

/**
 * Calculer la distance entre deux points (formule Haversine)
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Rayon de la Terre en km
    
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

/**
 * Trouver les hôpitaux les plus proches
 */
function findNearestHospitals($user_lat, $user_lon, $limit = 10, $max_distance_km = 50) {
    global $pdo;
    
    // Requête avec calcul de distance
    $stmt = $pdo->prepare("
        SELECT 
            h.*,
            (6371 * acos(
                cos(radians(?)) * cos(radians(h.latitude)) 
                * cos(radians(h.longitude) - radians(?)) 
                + sin(radians(?)) * sin(radians(h.latitude))
            )) as distance_km
        FROM hospitals h
        WHERE h.is_verified = TRUE
        AND h.latitude IS NOT NULL
        AND h.longitude IS NOT NULL
        HAVING distance_km <= ?
        ORDER BY distance_km
        LIMIT ?
    ");
    
    $stmt->execute([$user_lat, $user_lon, $user_lat, $max_distance_km, $limit]);
    return $stmt->fetchAll();
}

/**
 * Mettre à jour les coordonnées d'un utilisateur
 */
function updateUserCoordinates($user_id, $address = null) {
    global $pdo;
    
    if (!$address) {
        // Récupérer l'adresse de l'utilisateur
        $stmt = $pdo->prepare("SELECT city, address FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        $address = $user['address'] . ', ' . $user['city'] . ', France';
    }
    
    $coordinates = geocodeAddress($address);
    
    if ($coordinates) {
        $stmt = $pdo->prepare("UPDATE users SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->execute([$coordinates['latitude'], $coordinates['longitude'], $user_id]);
        return true;
    }
    
    return false;
}

/**
 * Générer une carte Leaflet
 */
function generateMap($center_lat, $center_lon, $markers = [], $zoom = 12, $height = '400px') {
    $map_id = 'map_' . uniqid();
    
    $html = '<div id="' . $map_id . '" style="height: ' . $height . '; border-radius: 10px;"></div>';
    
    $script = '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var map = L.map("' . $map_id . '").setView([' . $center_lat . ', ' . $center_lon . '], ' . $zoom . ');
        
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "© OpenStreetMap contributors",
            maxZoom: 18
        }).addTo(map);
    ';
    
    // Ajouter les marqueurs
    foreach ($markers as $marker) {
        $popup = isset($marker['popup']) ? '.bindPopup("' . addslashes($marker['popup']) . '")' : '';
        $icon = isset($marker['icon']) ? ', {icon: ' . $marker['icon'] . '}' : '';
        
        $script .= '
        L.marker([' . $marker['lat'] . ', ' . $marker['lon'] . ']' . $icon . ')
            ' . $popup . '
            .addTo(map);
        ';
    }
    
    $script .= '});</script>';
    
    return $html . $script;
}

/**
 * Récupérer l'adresse depuis les coordonnées (reverse geocoding)
 */
function reverseGeocode($lat, $lon) {
    global $pdo;
    
    // Vérifier le cache
    $cache_key = $lat . ',' . $lon;
    $address_hash = hash('sha256', $cache_key);
    
    $stmt = $pdo->prepare("SELECT address FROM geocoding_cache WHERE address_hash = ?");
    $stmt->execute([$address_hash]);
    $cached = $stmt->fetch();
    
    if ($cached) {
        return $cached['address'];
    }
    
    // Reverse geocoding via Nominatim
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" . $lat . "&lon=" . $lon;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'BloodLinkApp/1.0',
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['display_name'])) {
        $address = $data['display_name'];
        
        // Mettre en cache
        $stmt = $pdo->prepare("
            INSERT INTO geocoding_cache (address_hash, address, latitude, longitude) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([$address_hash, $address, $lat, $lon]);
        
        return $address;
    }
    
    return null;
}
?>