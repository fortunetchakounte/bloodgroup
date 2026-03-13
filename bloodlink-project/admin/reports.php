<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/functions.php';

// Verify admin access
if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../public/login.php');
}

$page_title = "Report Generator - BloodLink";
$message = '';
$message_type = '';

// Available report types
$report_types = [
    'donors' => [
        'name' => 'Donors Report',
        'description' => 'Complete list of donors with statistics',
        'fields' => ['id', 'full_name', 'email', 'blood_group', 'city', 'phone', 'is_verified', 'can_donate', 'created_at']
    ],
    'hospitals' => [
        'name' => 'Hospitals Report',
        'description' => 'List of hospitals with activity',
        'fields' => ['id', 'hospital_name', 'email', 'city', 'contact_person', 'phone', 'is_verified', 'created_at', 'request_count']
    ],
    'requests' => [
        'name' => 'Blood Requests Report',
        'description' => 'Blood requests with status',
        'fields' => ['id', 'hospital_name', 'blood_group', 'quantity', 'urgency', 'status', 'created_at', 'fulfilled_at', 'donor_count']
    ],
    'donations' => [
        'name' => 'Donations Report',
        'description' => 'History of completed donations',
        'fields' => ['id', 'donor_name', 'hospital_name', 'blood_group', 'donation_date', 'status', 'created_at', 'hospital_confirmed']
    ],
    'activity' => [
        'name' => 'Activity Report',
        'description' => 'System activity overview',
        'fields' => ['date', 'new_donors', 'new_hospitals', 'new_requests', 'new_donations', 'completed_donations']
    ]
];

// Generate report - Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report']) && $_POST['generate_report'] === '1') {
    $report_type = $_POST['report_type'] ?? '';
    $format = $_POST['format'] ?? '';
    $date_from = $_POST['date_from'] ?? null;
    $date_to = $_POST['date_to'] ?? null;
    $filters = $_POST['filters'] ?? [];
    
    if (!isset($report_types[$report_type])) {
        $message = "Invalid report type.";
        $message_type = 'danger';
    } elseif (!in_array($format, ['csv', 'pdf', 'excel'])) {
        $message = "Invalid format.";
        $message_type = 'danger';
    } else {
        // Generate report data
        $report_data = generateReport($pdo, $report_type, $date_from, $date_to, $filters);
        
        if (!empty($report_data)) {
            // Save report to file
            $file_path = saveReportToFile($report_data, $report_type, $format, $filters);
            
            if ($file_path) {
                $message = "✅ Report generated successfully!";
                $message_type = 'success';
                
                // Save to database
                saveReportToDatabase($pdo, $report_type, $report_types[$report_type]['name'], 
                                   "Report generated on " . date('Y-m-d H:i'), $file_path, $format, $filters);
                
                // Redirect to prevent form resubmission
                header("Location: reports.php?success=1&t=" . time());
                exit();
            } else {
                $message = "❌ Error generating file. Format not supported.";
                $message_type = 'danger';
            }
        } else {
            $message = "❌ No data found for this report.";
            $message_type = 'warning';
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "✅ Report generated successfully!";
    $message_type = 'success';
}

// Handle direct download
if (isset($_GET['download']) && isset($_GET['id'])) {
    $report_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT file_path, file_format FROM admin_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();
    
    if ($report && file_exists($report['file_path'])) {
        // Increment download counter
        $pdo->prepare("UPDATE admin_reports SET download_count = download_count + 1 WHERE id = ?")
            ->execute([$report_id]);
        
        // Set appropriate content type
        $content_type = 'application/octet-stream';
        if ($report['file_format'] === 'csv') {
            $content_type = 'text/csv';
        } elseif ($report['file_format'] === 'pdf') {
            $content_type = 'application/pdf';
        } elseif ($report['file_format'] === 'excel') {
            $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }
        
        // Force download
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . basename($report['file_path']) . '"');
        header('Content-Length: ' . filesize($report['file_path']));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($report['file_path']);
        exit();
    } else {
        $message = "❌ File not found.";
        $message_type = 'danger';
    }
}

// Handle report deletion
if (isset($_POST['delete_report']) && isset($_POST['report_id'])) {
    $report_id = (int)$_POST['report_id'];
    
    // Get file path first
    $stmt = $pdo->prepare("SELECT file_path FROM admin_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();
    
    if ($report) {
        // Delete file if exists
        if (file_exists($report['file_path'])) {
            unlink($report['file_path']);
        }
        
        // Delete from database
        $pdo->prepare("DELETE FROM admin_reports WHERE id = ?")->execute([$report_id]);
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Report not found']);
    exit();
}

// Function to generate report data
function generateReport($pdo, $type, $date_from = null, $date_to = null, $filters = []) {
    $sql = "";
    $params = [];
    
    switch ($type) {
        case 'donors':
            $sql = "
                SELECT 
                    u.id,
                    u.full_name,
                    u.email,
                    bg.name as blood_group,
                    u.city,
                    u.phone,
                    CASE WHEN u.is_verified THEN 'Yes' ELSE 'No' END as verified,
                    CASE WHEN u.can_donate THEN 'Yes' ELSE 'No' END as can_donate,
                    DATE(u.created_at) as registration_date,
                    (SELECT COUNT(*) FROM donations WHERE donor_id = u.id) as donation_count
                FROM users u
                LEFT JOIN blood_groups bg ON u.blood_group_id = bg.id
                WHERE u.user_type = 'donor'
            ";
            
            if ($date_from) {
                $sql .= " AND u.created_at >= ?";
                $params[] = $date_from;
            }
            if ($date_to) {
                $sql .= " AND u.created_at <= ?";
                $params[] = $date_to . ' 23:59:59';
            }
            
            $sql .= " ORDER BY u.created_at DESC";
            break;
            
        case 'hospitals':
            $sql = "
                SELECT 
                    h.id,
                    h.hospital_name,
                    h.email,
                    h.city,
                    h.contact_person,
                    h.phone,
                    CASE WHEN h.is_verified THEN 'Yes' ELSE 'No' END as verified,
                    DATE(h.created_at) as registration_date,
                    (SELECT COUNT(*) FROM donation_requests WHERE hospital_id = h.id) as request_count,
                    (SELECT COUNT(*) FROM donation_requests WHERE hospital_id = h.id AND status = 'fulfilled') as fulfilled_requests
                FROM hospitals h
            ";
            
            if ($date_from) {
                $sql .= " WHERE h.created_at >= ?";
                $params[] = $date_from;
            }
            if ($date_to) {
                $sql .= ($date_from ? " AND" : " WHERE") . " h.created_at <= ?";
                $params[] = $date_to . ' 23:59:59';
            }
            
            $sql .= " ORDER BY h.created_at DESC";
            break;
            
        case 'requests':
            $sql = "
                SELECT 
                    dr.id,
                    h.hospital_name,
                    bg.name as blood_group,
                    dr.quantity,
                    CASE dr.urgency 
                        WHEN 'high' THEN 'High' 
                        WHEN 'medium' THEN 'Medium' 
                        ELSE 'Low' 
                    END as urgency,
                    CASE dr.status 
                        WHEN 'pending' THEN 'Pending' 
                        WHEN 'fulfilled' THEN 'Fulfilled' 
                        WHEN 'cancelled' THEN 'Cancelled'
                        ELSE dr.status
                    END as status,
                    DATE(dr.created_at) as created_date,
                    DATE(dr.fulfilled_at) as fulfilled_date,
                    (SELECT COUNT(*) FROM donations WHERE request_id = dr.id) as donor_count
                FROM donation_requests dr
                JOIN hospitals h ON dr.hospital_id = h.id
                JOIN blood_groups bg ON dr.blood_group_id = bg.id
            ";
            
            $where = [];
            if ($date_from) {
                $where[] = "dr.created_at >= ?";
                $params[] = $date_from;
            }
            if ($date_to) {
                $where[] = "dr.created_at <= ?";
                $params[] = $date_to . ' 23:59:59';
            }
            if (isset($filters['status']) && $filters['status']) {
                $where[] = "dr.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            
            $sql .= " ORDER BY dr.created_at DESC";
            break;
            
        case 'donations':
            $sql = "
                SELECT 
                    d.id,
                    u.full_name as donor_name,
                    h.hospital_name,
                    bg.name as blood_group,
                    DATE(d.donation_date) as donation_date,
                    CASE d.status 
                        WHEN 'scheduled' THEN 'Scheduled' 
                        WHEN 'completed' THEN 'Completed' 
                        WHEN 'cancelled' THEN 'Cancelled'
                        ELSE d.status
                    END as status,
                    DATE(d.created_at) as created_date,
                    CASE WHEN d.hospital_confirmed THEN 'Yes' ELSE 'No' END as hospital_confirmed
                FROM donations d
                JOIN users u ON d.donor_id = u.id
                JOIN donation_requests dr ON d.request_id = dr.id
                JOIN hospitals h ON dr.hospital_id = h.id
                JOIN blood_groups bg ON dr.blood_group_id = bg.id
            ";
            
            $where = [];
            if ($date_from) {
                $where[] = "d.created_at >= ?";
                $params[] = $date_from;
            }
            if ($date_to) {
                $where[] = "d.created_at <= ?";
                $params[] = $date_to . ' 23:59:59';
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            
            $sql .= " ORDER BY d.created_at DESC";
            break;
            
        case 'activity':
            // Check if daily_stats table exists
            try {
                $sql = "
                    SELECT 
                        stat_date as date,
                        new_donors,
                        new_hospitals,
                        new_requests,
                        new_donations,
                        completed_donations
                    FROM daily_stats
                ";
                
                if ($date_from) {
                    $sql .= " WHERE stat_date >= ?";
                    $params[] = $date_from;
                }
                if ($date_to) {
                    $sql .= ($date_from ? " AND" : " WHERE") . " stat_date <= ?";
                    $params[] = $date_to;
                }
                
                $sql .= " ORDER BY stat_date DESC";
            } catch (Exception $e) {
                // Table doesn't exist, return empty array
                return [];
            }
            break;
    }
    
    if ($sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Report generation error: " . $e->getMessage());
            return [];
        }
    }
    
    return [];
}

// Function to save report to file
function saveReportToFile($data, $type, $format, $filters) {
    if (empty($data)) return false;
    
    $filename = "report_" . $type . "_" . date('Ymd_His') . "." . $format;
    $filepath = __DIR__ . "/../reports/" . $filename;
    
    // Create reports directory if it doesn't exist
    if (!file_exists(__DIR__ . '/../reports')) {
        mkdir(__DIR__ . '/../reports', 0777, true);
    }
    
    switch ($format) {
        case 'csv':
            return saveAsCSV($data, $filepath);
        case 'pdf':
            // Show message that PDF generation is not available
            $_SESSION['report_message'] = "PDF generation is not available. Please use CSV format.";
            return false;
        case 'excel':
            // Show message that Excel generation is not available
            $_SESSION['report_message'] = "Excel generation is not available. Please use CSV format.";
            return false;
        default:
            return false;
    }
}

// Function to save as CSV
function saveAsCSV($data, $filepath) {
    if (empty($data)) return false;
    
    $fp = fopen($filepath, 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($fp, array_keys($data[0]), ';');
    
    // Data
    foreach ($data as $row) {
        fputcsv($fp, $row, ';');
    }
    
    fclose($fp);
    return $filepath;
}

// Function to save to database
function saveReportToDatabase($pdo, $report_type, $title, $description, $file_path, $format, $filters) {
    $stmt = $pdo->prepare("
        INSERT INTO admin_reports 
        (report_type, title, description, file_path, file_format, filters, generated_by, generated_at, download_count)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0)
    ");
    
    return $stmt->execute([
        $report_type,
        $title . " - " . date('Y-m-d H:i'),
        $description,
        $file_path,
        $format,
        json_encode($filters),
        $_SESSION['user_id']
    ]);
}

// Get generated reports
$reports_stmt = $pdo->query("
    SELECT r.*, u.full_name as generated_by_name
    FROM admin_reports r
    JOIN users u ON r.generated_by = u.id
    ORDER BY r.generated_at DESC
    LIMIT 20
");
$all_reports = $reports_stmt->fetchAll();

// Check for session message
if (isset($_SESSION['report_message'])) {
    $message = $_SESSION['report_message'];
    $message_type = 'warning';
    unset($_SESSION['report_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f172a;
            --gray-dark: #334155;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --light: #f8fafc;
            --white: #ffffff;
            --shadow: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.05);
            --radius: 10px;
            --transition: all 0.2s;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: var(--dark);
            line-height: 1.5;
        }

        /* Navigation */
        .navbar {
            background: var(--white);
            padding: 0.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--gray-light);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .logo {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--dark);
        }

        .logo i {
            color: var(--primary);
        }

        .user-info {
            background: var(--light);
            padding: 0.35rem 1rem;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--gray);
            border: 1px solid var(--gray-light);
        }

        .nav-right {
            display: flex;
            gap: 0.25rem;
        }

        .nav-right a {
            color: var(--gray);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .nav-right a:hover {
            background: var(--light);
            color: var(--primary);
        }

        .nav-right a.active {
            background: var(--primary);
            color: white;
        }

        .logout-btn {
            background: #fee2e2 !important;
            color: var(--danger) !important;
        }

        .logout-btn:hover {
            background: #fecaca !important;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .page-header p {
            color: var(--gray);
            font-size: 0.95rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #dcfce7;
            border-color: var(--success);
            color: #166534;
        }

        .alert-danger {
            background: #fee2e2;
            border-color: var(--danger);
            color: #991b1b;
        }

        .alert-warning {
            background: #fef3c7;
            border-color: var(--warning);
            color: #92400e;
        }

        .alert-info {
            background: #dbeafe;
            border-color: var(--primary);
            color: #1e40af;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            border: 1px solid var(--gray-light);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card h3 i {
            color: var(--primary);
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            padding: 0.6rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Buttons */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .action-btn.primary {
            background: var(--primary);
            color: white;
            width: 100%;
        }

        .action-btn.primary:hover {
            background: var(--primary-dark);
        }

        .action-btn.secondary {
            background: var(--light);
            color: var(--gray);
            border: 1px solid var(--gray-light);
        }

        .action-btn.secondary:hover {
            background: var(--gray-light);
            color: var(--dark);
        }

        .action-btn.small {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 1rem 0.75rem;
            color: var(--gray);
            font-weight: 500;
            font-size: 0.85rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .data-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--gray-light);
            font-size: 0.9rem;
        }

        .data-table tr:hover td {
            background: var(--light);
        }

        /* Badges */
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--gray-light);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
        }

        .empty-state p {
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .nav-right {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .data-table {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">
                <i class="fas fa-tint"></i>
                BloodLink
            </div>
            <div class="user-info">
                <i class="fas fa-user-shield"></i>
                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
        </div>
        <div class="nav-right">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="donors.php"><i class="fas fa-users"></i> Donors</a>
            <a href="hospitals.php"><i class="fas fa-hospital"></i> Hospitals</a>
            <a href="requests.php"><i class="fas fa-tint"></i> Requests</a>
            <a href="validation.php"><i class="fas fa-check-circle"></i> Validations</a>
            <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>📊 Report Generator</h1>
            <p>Generate and export your data in CSV format</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Generation Form -->
        <div class="card">
            <h3><i class="fas fa-file-export"></i> Generate New Report</h3>
            
            <form method="POST" action="">
                <input type="hidden" name="generate_report" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Report Type *</label>
                        <select name="report_type" required class="form-control" id="reportType">
                            <option value="">Select...</option>
                            <?php foreach ($report_types as $key => $type): ?>
                                <option value="<?php echo $key; ?>">
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Format *</label>
                        <select name="format" required class="form-control" id="reportFormat">
                            <option value="csv" selected>CSV (Excel compatible)</option>
                            <!-- <option value="pdf" disabled>PDF (Coming soon)</option>
                            <option value="excel" disabled>Excel (Coming soon)</option> -->
                        </select>
                        <!-- <small style="color: var(--warning);">PDF and Excel formats are currently unavailable. Please use CSV.</small> -->
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="date_from" class="form-control" id="dateFrom">
                    </div>
                    
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="date_to" class="form-control" id="dateTo">
                    </div>
                </div>
                
                <!-- Dynamic filters -->
                <div id="dynamicFilters" style="margin-bottom: 1.5rem;"></div>
                
                <button type="submit" class="action-btn primary">
                    <i class="fas fa-play"></i> Generate Report
                </button>
            </form>
        </div>
        
        <!-- Generated Reports -->
        <div class="card">
            <h3><i class="fas fa-history"></i> Previous Reports</h3>
            
            <?php if (empty($all_reports)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No reports generated</h3>
                    <p>Generate your first report using the form above.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Report</th>
                                <th>Type</th>
                                <th>Format</th>
                                <th>Date</th>
                                <th>Generated By</th>
                                <th>Downloads</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_reports as $report): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($report['title']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo htmlspecialchars($report['report_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo strtoupper($report['file_format']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($report['generated_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($report['generated_by_name']); ?></td>
                                    <td style="text-align: center;"><?php echo $report['download_count']; ?></td>
                                    <td>
                                        <?php if (file_exists($report['file_path'])): ?>
                                            <a href="?download=1&id=<?php echo $report['id']; ?>" 
                                               class="action-btn secondary small" 
                                               title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button onclick="deleteReport(<?php echo $report['id']; ?>)" 
                                                    class="action-btn secondary small" 
                                                    title="Delete"
                                                    style="margin-left: 0.25rem;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <span style="color: var(--danger); font-size: 0.8rem;">File missing</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Dynamic filters based on report type
        document.getElementById('reportType').addEventListener('change', function() {
            const type = this.value;
            const filtersDiv = document.getElementById('dynamicFilters');
            
            let filtersHtml = '';
            
            switch(type) {
                case 'requests':
                    filtersHtml = `
                        <div class="form-group">
                            <label>Status</label>
                            <select name="filters[status]" class="form-control">
                                <option value="">All statuses</option>
                                <option value="pending">Pending</option>
                                <option value="fulfilled">Fulfilled</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'donations':
                    filtersHtml = `
                        <div class="form-group">
                            <label>Donation Status</label>
                            <select name="filters[status]" class="form-control">
                                <option value="">All statuses</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'donors':
                    filtersHtml = `
                        <div class="form-grid">
                            <div class="form-group">
                                <label>City</label>
                                <input type="text" name="filters[city]" class="form-control" placeholder="Filter by city">
                            </div>
                            <div class="form-group">
                                <label>Verification Status</label>
                                <select name="filters[verified]" class="form-control">
                                    <option value="">All</option>
                                    <option value="1">Verified</option>
                                    <option value="0">Not verified</option>
                                </select>
                            </div>
                        </div>
                    `;
                    break;
            }
            
            filtersDiv.innerHTML = filtersHtml;
        });

        // Delete report function
        function deleteReport(reportId) {
            if (confirm('Delete this report permanently?')) {
                fetch('reports.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'delete_report=1&report_id=' + reportId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Could not delete report'));
                    }
                })
                .catch(error => {
                    alert('Network error');
                    console.error('Error:', error);
                });
            }
        }

        // Set default dates (last 30 days)
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const monthAgo = new Date();
            monthAgo.setDate(monthAgo.getDate() - 30);
            const monthAgoStr = monthAgo.toISOString().split('T')[0];
            
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            
            if (dateFrom && !dateFrom.value) dateFrom.value = monthAgoStr;
            if (dateTo && !dateTo.value) dateTo.value = today;
        });

        // Disable PDF and Excel selection
        document.getElementById('reportFormat').addEventListener('change', function() {
            if (this.value !== 'csv') {
                alert('PDF and Excel formats are currently unavailable. Please use CSV.');
                this.value = 'csv';
            }
        });
    </script>
</body>
</html>