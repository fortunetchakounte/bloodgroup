<?php
// Intégration hospitalière avancée

/**
 * Mettre à jour l'inventaire sanguin
 */
function updateBloodInventory($hospital_id, $blood_group_id, $quantity_change, $action = 'add') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier si l'entrée existe
        $stmt = $pdo->prepare("
            SELECT * FROM blood_inventory 
            WHERE hospital_id = ? AND blood_group_id = ?
        ");
        $stmt->execute([$hospital_id, $blood_group_id]);
        $inventory = $stmt->fetch();
        
        if ($inventory) {
            // Mettre à jour l'existant
            $new_quantity = $action === 'add' 
                ? $inventory['current_quantity'] + $quantity_change
                : $inventory['current_quantity'] - $quantity_change;
            
            // Ne pas descendre en dessous de 0
            $new_quantity = max(0, $new_quantity);
            
            $stmt = $pdo->prepare("
                UPDATE blood_inventory 
                SET current_quantity = ?, 
                    last_updated = NOW(),
                    last_donation_date = IF(? > 0 AND ? = 'add', CURDATE(), last_donation_date),
                    status = ?
                WHERE id = ?
            ");
            
            $status = calculateInventoryStatus($new_quantity, $inventory['minimum_required'], $inventory['maximum_capacity']);
            
            $stmt->execute([
                $new_quantity,
                $quantity_change,
                $action,
                $status,
                $inventory['id']
            ]);
        } else {
            // Créer une nouvelle entrée
            $new_quantity = $action === 'add' ? $quantity_change : -$quantity_change;
            $new_quantity = max(0, $new_quantity);
            
            $status = calculateInventoryStatus($new_quantity, 5, 50); // Valeurs par défaut
            
            $stmt = $pdo->prepare("
                INSERT INTO blood_inventory 
                (hospital_id, blood_group_id, current_quantity, minimum_required, maximum_capacity, status)
                VALUES (?, ?, ?, 5, 50, ?)
            ");
            
            $stmt->execute([$hospital_id, $blood_group_id, $new_quantity, $status]);
        }
        
        // Vérifier et créer des alertes si nécessaire
        checkInventoryAlerts($hospital_id, $blood_group_id, $new_quantity);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'new_quantity' => $new_quantity,
            'status' => $status
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur mise à jour inventaire: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Calculer le statut de l'inventaire
 */
function calculateInventoryStatus($quantity, $minimum, $maximum) {
    if ($quantity <= 0) {
        return 'critical';
    } elseif ($quantity <= $minimum * 0.5) {
        return 'critical';
    } elseif ($quantity <= $minimum) {
        return 'low';
    } elseif ($quantity <= $minimum * 2) {
        return 'normal';
    } elseif ($quantity <= $maximum * 0.8) {
        return 'good';
    } else {
        return 'full';
    }
}

/**
 * Vérifier et créer des alertes d'inventaire
 */
function checkInventoryAlerts($hospital_id, $blood_group_id, $current_quantity) {
    global $pdo;
    
    // Récupérer les seuils
    $stmt = $pdo->prepare("
        SELECT minimum_required, maximum_capacity 
        FROM blood_inventory 
        WHERE hospital_id = ? AND blood_group_id = ?
    ");
    $stmt->execute([$hospital_id, $blood_group_id]);
    $inventory = $stmt->fetch();
    
    if (!$inventory) return;
    
    $minimum = $inventory['minimum_required'];
    $maximum = $inventory['maximum_capacity'];
    
    // Vérifier les alertes existantes non résolues
    $stmt = $pdo->prepare("
        SELECT id, alert_type 
        FROM inventory_alerts 
        WHERE hospital_id = ? AND blood_group_id = ? AND is_resolved = FALSE
    ");
    $stmt->execute([$hospital_id, $blood_group_id]);
    $existing_alerts = $stmt->fetchAll();
    
    $alert_types = [];
    
    // Déterminer les types d'alertes nécessaires
    if ($current_quantity <= $minimum * 0.3) {
        $alert_types[] = 'critical';
    } elseif ($current_quantity <= $minimum) {
        $alert_types[] = 'low_stock';
    } elseif ($current_quantity >= $maximum * 0.9) {
        $alert_types[] = 'excess_stock';
    }
    
    // Résoudre les alertes qui ne sont plus pertinentes
    foreach ($existing_alerts as $alert) {
        if (!in_array($alert['alert_type'], $alert_types)) {
            $stmt = $pdo->prepare("
                UPDATE inventory_alerts 
                SET is_resolved = TRUE, resolved_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$alert['id']]);
        }
    }
    
    // Créer de nouvelles alertes
    foreach ($alert_types as $alert_type) {
        $message = '';
        $threshold = 0;
        
        switch ($alert_type) {
            case 'critical':
                $message = "Stock CRITIQUE ! Seulement $current_quantity unités restantes.";
                $threshold = round($minimum * 0.3);
                break;
            case 'low_stock':
                $message = "Stock bas : $current_quantity unités. Seuil minimum : $minimum";
                $threshold = $minimum;
                break;
            case 'excess_stock':
                $message = "Stock excédentaire : $current_quantity unités. Capacité maximale : $maximum";
                $threshold = round($maximum * 0.9);
                break;
        }
        
        // Vérifier si une alerte de ce type existe déjà
        $stmt = $pdo->prepare("
            SELECT id FROM inventory_alerts 
            WHERE hospital_id = ? AND blood_group_id = ? 
            AND alert_type = ? AND is_resolved = FALSE
        ");
        $stmt->execute([$hospital_id, $blood_group_id, $alert_type]);
        
        if (!$stmt->fetch()) {
            // Créer la nouvelle alerte
            $stmt = $pdo->prepare("
                INSERT INTO inventory_alerts 
                (hospital_id, blood_group_id, alert_type, current_quantity, threshold_quantity, message)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $hospital_id,
                $blood_group_id,
                $alert_type,
                $current_quantity,
                $threshold,
                $message
            ]);
            
            // Notifier les administrateurs de l'hôpital
            notifyHospitalAdmins($hospital_id, $alert_type, $message);
        }
    }
}

/**
 * Notifier les administrateurs de l'hôpital
 */
function notifyHospitalAdmins($hospital_id, $alert_type, $message) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email
        FROM users u
        JOIN hospital_staff hs ON u.id = hs.user_id
        WHERE hs.hospital_id = ? AND hs.role IN ('admin', 'manager')
        AND hs.is_active = TRUE
    ");
    $stmt->execute([$hospital_id]);
    $admins = $stmt->fetchAll();
    
    $alert_icons = [
        'critical' => '🚨',
        'low_stock' => '⚠️',
        'excess_stock' => '📦'
    ];
    
    $icon = $alert_icons[$alert_type] ?? 'ℹ️';
    
    foreach ($admins as $admin) {
        createNotification(
            $admin['id'],
            'hospital',
            $icon . " Alerte inventaire",
            $message,
            $alert_type === 'critical' ? 'danger' : 'warning',
            $icon,
            "hospital/inventory.php?alert=" . $alert_type
        );
        
        // Envoyer un email si l'alerte est critique
        if ($alert_type === 'critical') {
            sendEmail($admin['email'], "Alerte critique - Stock sanguin", $message);
        }
    }
}

/**
 * Planifier un rendez-vous de don
 */
function scheduleDonationAppointment($hospital_id, $donor_id, $blood_group_id, $date, $time, $procedure_id = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier la disponibilité
        if (!isTimeSlotAvailable($hospital_id, $date, $time)) {
            throw new Exception("Ce créneau horaire n'est pas disponible.");
        }
        
        // Générer un UUID pour le rendez-vous
        $appointment_uuid = generateUuid();
        
        $stmt = $pdo->prepare("
            INSERT INTO donation_appointments 
            (appointment_uuid, hospital_id, donor_id, blood_group_id, 
             appointment_date, appointment_time, procedure_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $appointment_uuid,
            $hospital_id,
            $donor_id,
            $blood_group_id,
            $date,
            $time,
            $procedure_id
        ]);
        
        $appointment_id = $pdo->lastInsertId();
        
        // Notifier le donneur
        $donor_stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $donor_stmt->execute([$donor_id]);
        $donor = $donor_stmt->fetch();
        
        $hospital_stmt = $pdo->prepare("SELECT hospital_name FROM hospitals WHERE id = ?");
        $hospital_stmt->execute([$hospital_id]);
        $hospital = $hospital_stmt->fetch();
        
        $message = "Votre rendez-vous de don est confirmé pour le " . 
                   date('d/m/Y', strtotime($date)) . " à " . date('H:i', strtotime($time)) . 
                   " à " . $hospital['hospital_name'];
        
        createNotification(
            $donor_id,
            'donor',
            "📅 Rendez-vous programmé",
            $message,
            'info',
            '💉',
            "donor/appointments.php"
        );
        
        // Envoyer un email de confirmation
        sendAppointmentConfirmation($donor['email'], $appointment_id);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'appointment_id' => $appointment_id,
            'appointment_uuid' => $appointment_uuid
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Vérifier la disponibilité d'un créneau horaire
 */
function isTimeSlotAvailable($hospital_id, $date, $time) {
    global $pdo;
    
    // Vérifier les rendez-vous existants
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM donation_appointments
        WHERE hospital_id = ?
        AND appointment_date = ?
        AND appointment_time = ?
        AND status IN ('scheduled', 'confirmed')
    ");
    
    $stmt->execute([$hospital_id, $date, $time]);
    $result = $stmt->fetch();
    
    // Limite de 4 rendez-vous par créneau
    return $result['count'] < 4;
}

/**
 * Obtenir les créneaux disponibles pour une date
 */
function getAvailableTimeSlots($hospital_id, $date) {
    // Heures d'ouverture de l'hôpital (à adapter selon l'hôpital)
    $opening_hours = [
        'morning' => ['start' => '09:00', 'end' => '12:00'],
        'afternoon' => ['start' => '14:00', 'end' => '17:00']
    ];
    
    $available_slots = [];
    
    foreach ($opening_hours as $period => $hours) {
        $current = strtotime($date . ' ' . $hours['start']);
        $end = strtotime($date . ' ' . $hours['end']);
        
        while ($current < $end) {
            $time = date('H:i', $current);
            
            if (isTimeSlotAvailable($hospital_id, $date, $time)) {
                $available_slots[] = $time;
            }
            
            // Incrémenter de 45 minutes (durée d'un don)
            $current = strtotime('+45 minutes', $current);
        }
    }
    
    return $available_slots;
}

/**
 * Générer un rapport hospitalier
 */
function generateHospitalReport($hospital_id, $report_type, $period_start, $period_end) {
    global $pdo;
    
    $report_data = [];
    
    // Donations
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_donations,
               COUNT(DISTINCT donor_id) as unique_donors,
               AVG(TIMESTAMPDIFF(HOUR, dr.created_at, d.donation_date)) as avg_response_time
        FROM donations d
        JOIN donation_requests dr ON d.request_id = dr.id
        WHERE d.hospital_id = ?
        AND d.donation_date BETWEEN ? AND ?
        AND d.status = 'completed'
    ");
    $stmt->execute([$hospital_id, $period_start, $period_end]);
    $donations = $stmt->fetch();
    
    // Demandes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_requests,
               SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) as fulfilled_requests,
               SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests
        FROM donation_requests
        WHERE hospital_id = ?
        AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$hospital_id, $period_start, $period_end]);
    $requests = $stmt->fetch();
    
    // Rendez-vous
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show_appointments,
            COUNT(*) as total_appointments
        FROM donation_appointments
        WHERE hospital_id = ?
        AND appointment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$hospital_id, $period_start, $period_end]);
    $appointments = $stmt->fetch();
    
    // Inventaire
    $stmt = $pdo->prepare("
        SELECT bg.name as blood_group,
               bi.current_quantity,
               bi.minimum_required,
               bi.status
        FROM blood_inventory bi
        JOIN blood_groups bg ON bi.blood_group_id = bg.id
        WHERE bi.hospital_id = ?
        ORDER BY bg.name
    ");
    $stmt->execute([$hospital_id]);
    $inventory = $stmt->fetchAll();
    
    // Alertes
    $stmt = $pdo->prepare("
        SELECT alert_type, COUNT(*) as count
        FROM inventory_alerts
        WHERE hospital_id = ?
        AND created_at BETWEEN ? AND ?
        GROUP BY alert_type
    ");
    $stmt->execute([$hospital_id, $period_start, $period_end]);
    $alerts = $stmt->fetchAll();
    
    // Compiler le rapport
    $report_data = [
        'donations' => $donations,
        'requests' => $requests,
        'appointments' => $appointments,
        'inventory' => $inventory,
        'alerts' => $alerts,
        'fulfillment_rate' => $requests['total_requests'] > 0 
            ? round(($requests['fulfilled_requests'] / $requests['total_requests']) * 100, 2)
            : 0
    ];
    
    // Sauvegarder le rapport
    $stmt = $pdo->prepare("
        INSERT INTO hospital_reports 
        (hospital_id, report_type, period_start, period_end, 
         total_donations, total_requests, fulfillment_rate, 
         average_response_time_hours, appointments_completed,
         report_data, generated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $hospital_id,
        $report_type,
        $period_start,
        $period_end,
        $donations['total_donations'],
        $requests['total_requests'],
        $report_data['fulfillment_rate'],
        $donations['avg_response_time'],
        $appointments['completed_appointments'],
        json_encode($report_data),
        $_SESSION['user_id'] ?? null
    ]);
    
    return [
        'success' => true,
        'report_id' => $pdo->lastInsertId(),
        'data' => $report_data
    ];
}

/**
 * Prévoir les besoins futurs
 */
function predictBloodDemand($hospital_id, $blood_group_id, $days_ahead = 7) {
    global $pdo;
    
    // Récupérer les données historiques
    $stmt = $pdo->prepare("
        SELECT DATE(dr.created_at) as date,
               COUNT(*) as demand,
               DAYOFWEEK(dr.created_at) as day_of_week,
               MONTH(dr.created_at) as month
        FROM donation_requests dr
        WHERE dr.hospital_id = ?
        AND dr.blood_group_id = ?
        AND dr.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY DATE(dr.created_at)
        ORDER BY date
    ");
    $stmt->execute([$hospital_id, $blood_group_id]);
    $historical_data = $stmt->fetchAll();
    
    if (count($historical_data) < 14) {
        // Pas assez de données, utiliser la moyenne
        return predictUsingAverage($hospital_id, $blood_group_id, $days_ahead);
    }
    
    // Utiliser un modèle simple de moyenne mobile
    $predictions = [];
    $today = new DateTime();
    
    for ($i = 1; $i <= $days_ahead; $i++) {
        $date = clone $today;
        $date->modify("+$i days");
        $date_str = $date->format('Y-m-d');
        
        // Filtrer par jour de la semaine similaire
        $day_of_week = $date->format('N');
        $similar_days = array_filter($historical_data, function($item) use ($day_of_week) {
            return $item['day_of_week'] == $day_of_week;
        });
        
        if (count($similar_days) > 0) {
            $average = array_sum(array_column($similar_days, 'demand')) / count($similar_days);
            $predicted = round($average);
        } else {
            $predicted = round(array_sum(array_column($historical_data, 'demand')) / count($historical_data));
        }
        
        // Ajuster selon la saison (plus de besoins en hiver/vacances)
        $month = $date->format('n');
        $season_factor = getSeasonFactor($month);
        $predicted = round($predicted * $season_factor);
        
        $predictions[] = [
            'date' => $date_str,
            'predicted_need' => $predicted,
            'confidence_level' => count($similar_days) > 3 ? 'high' : 'medium'
        ];
    }
    
    return $predictions;
}

/**
 * Obtenir le facteur saisonnier
 */
function getSeasonFactor($month) {
    // Plus de besoins en hiver (déc-fév) et pendant les vacances
    $factors = [
        1 => 1.2,  // Janvier
        2 => 1.1,  // Février
        6 => 0.9,  // Juin
        7 => 0.8,  // Juillet (vacances)
        8 => 0.8,  // Août (vacances)
        12 => 1.3  // Décembre (fêtes)
    ];
    
    return $factors[$month] ?? 1.0;
}

/**
 * Envoyer un rappel de rendez-vous
 */
function sendAppointmentReminders() {
    global $pdo;
    
    // Récupérer les rendez-vous du lendemain non rappelés
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    $stmt = $pdo->prepare("
        SELECT da.*, u.full_name, u.email, u.phone,
               h.hospital_name, h.address
        FROM donation_appointments da
        JOIN users u ON da.donor_id = u.id
        JOIN hospitals h ON da.hospital_id = h.id
        WHERE da.appointment_date = ?
        AND da.status = 'scheduled'
        AND da.reminder_sent = FALSE
    ");
    $stmt->execute([$tomorrow]);
    $appointments = $stmt->fetchAll();
    
    $sent_count = 0;
    
    foreach ($appointments as $appointment) {
        // Envoyer SMS (si numéro disponible)
        if (!empty($appointment['phone'])) {
            sendSMSReminder($appointment);
        }
        
        // Envoyer email
        sendEmailReminder($appointment);
        
        // Marquer comme rappel envoyé
        $stmt = $pdo->prepare("
            UPDATE donation_appointments 
            SET reminder_sent = TRUE 
            WHERE id = ?
        ");
        $stmt->execute([$appointment['id']]);
        
        $sent_count++;
    }
    
    return $sent_count;
}

/**
 * Gérer le check-in d'un donneur
 */
function checkInDonor($appointment_id, $check_in_time = null) {
    global $pdo;
    
    if (!$check_in_time) {
        $check_in_time = date('Y-m-d H:i:s');
    }
    
    $stmt = $pdo->prepare("
        UPDATE donation_appointments 
        SET status = 'confirmed',
            check_in_time = ?
        WHERE id = ?
        AND status = 'scheduled'
    ");
    
    return $stmt->execute([$check_in_time, $appointment_id]);
}

/**
 * Compléter un rendez-vous de don
 */
function completeDonationAppointment($appointment_id, $actual_duration = null, $notes = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Récupérer les infos du rendez-vous
        $stmt = $pdo->prepare("
            SELECT da.*, da.blood_group_id, da.hospital_id
            FROM donation_appointments da
            WHERE da.id = ?
        ");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            throw new Exception("Rendez-vous non trouvé");
        }
        
        // Mettre à jour le statut
        $check_out_time = date('Y-m-d H:i:s');
        $actual_duration = $actual_duration ?? 45; // minutes par défaut
        
        $stmt = $pdo->prepare("
            UPDATE donation_appointments 
            SET status = 'completed',
                check_out_time = ?,
                actual_duration = ?
            WHERE id = ?
        ");
        $stmt->execute([$check_out_time, $actual_duration, $appointment_id]);
        
        // Mettre à jour l'inventaire
        updateBloodInventory(
            $appointment['hospital_id'],
            $appointment['blood_group_id'],
            1, // 1 unité de sang
            'add'
        );
        
        // Ajouter de l'XP au donneur
        addUserXP($appointment['donor_id'], 50, 'donation', $appointment_id);
        
        // Mettre à jour les statistiques de don
        updateDonationStats($appointment['donor_id'], [
            'donation_date' => $appointment['appointment_date'],
            'hospital_id' => $appointment['hospital_id'],
            'urgency' => 'normal'
        ]);
        
        $pdo->commit();
        
        return ['success' => true, 'message' => 'Don enregistré avec succès'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>