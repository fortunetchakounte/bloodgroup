<?php
require_once __DIR__ . '/../includes/init.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../public/login.php');
}

$page_title = "Nouvelle demande - BloodLink";
$message = '';
$message_type = '';

// Récupérer la liste des hôpitaux
$hospitals = $pdo->query("SELECT id, hospital_name, city FROM hospitals WHERE is_verified = 1 ORDER BY hospital_name")->fetchAll();

// Récupérer les groupes sanguins
$blood_groups = $pdo->query("SELECT id, name FROM blood_groups ORDER BY name")->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hospital_id = (int)($_POST['hospital_id'] ?? 0);
    $blood_group_id = (int)($_POST['blood_group_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $urgency = $_POST['urgency'] ?? 'normal';
    $needed_by = $_POST['needed_by'] ?? '';
    $patient_name = trim($_POST['patient_name'] ?? '');
    
    $errors = [];
    
    if ($hospital_id <= 0) $errors[] = "Veuillez sélectionner un hôpital.";
    if ($blood_group_id <= 0) $errors[] = "Veuillez sélectionner un groupe sanguin.";
    if ($quantity <= 0) $errors[] = "La quantité doit être supérieure à 0.";
    if (empty($needed_by)) $errors[] = "La date limite est requise.";
    if (empty($patient_name)) $errors[] = "Le nom du patient est requis.";
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO blood_requests 
                (hospital_id, blood_group_id, quantity, urgency, needed_by, patient_name, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([
                $hospital_id,
                $blood_group_id,
                $quantity,
                $urgency,
                $needed_by,
                $patient_name
            ]);
            
            $request_id = $pdo->lastInsertId();
            
            $message = "Demande créée avec succès !";
            $message_type = 'success';
            
            // Rediriger vers la liste des demandes après 2 secondes
            header("refresh:2;url=requests.php");
            
        } catch (Exception $e) {
            $message = "Erreur : " . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
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
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --light: #f8fafc;
            --white: #ffffff;
            --shadow: 0 1px 2px rgba(0,0,0,0.05);
            --radius: 8px;
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

        /* Container */
        .container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* En-tête */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 1rem;
            background: var(--white);
            color: var(--gray);
            border: 1px solid var(--gray-light);
            border-radius: var(--radius);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-btn:hover {
            background: var(--light);
        }

        /* Alertes */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Formulaire */
        .form-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            border: 1px solid var(--gray-light);
            box-shadow: var(--shadow);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark);
        }

        .required {
            color: var(--danger);
        }

        .form-control {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius);
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        select.form-control {
            cursor: pointer;
            background-color: white;
        }

        /* Radio group */
        .radio-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            cursor: pointer;
        }

        .radio-option input[type="radio"] {
            width: 16px;
            height: 16px;
        }

        .radio-option span {
            font-size: 0.9rem;
        }

        /* Boutons */
        .form-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.6rem 1.2rem;
            border-radius: var(--radius);
            font-size: 0.9rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            flex: 1;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-light);
            color: var(--gray);
        }

        .btn-outline:hover {
            background: var(--light);
        }

        /* Responsive */
        @media (max-width: 640px) {
            .navbar {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .nav-right {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .page-header {
                flex-direction: column;
                gap: 0.75rem;
                align-items: flex-start;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
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
            <a href="dashboard.php"><i class="fas fa-home"></i> Accueil</a>
            <a href="donors.php"><i class="fas fa-users"></i> Donneurs</a>
            <a href="hospitals.php"><i class="fas fa-hospital"></i> Hôpitaux</a>
            <a href="requests.php" class="active"><i class="fas fa-tint"></i> Demandes</a>
            <a href="validation.php"><i class="fas fa-check-circle"></i> Validations</a>
            <a href="../public/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Sortir</a>
        </div>
    </nav>

    <div class="container">
        <!-- En-tête -->
        <div class="page-header">
            <h1>Nouvelle demande</h1>
            <a href="requests.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire -->
        <div class="form-card">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Hôpital <span class="required">*</span></label>
                    <select name="hospital_id" class="form-control" required>
                        <option value="">Sélectionner...</option>
                        <?php foreach ($hospitals as $h): ?>
                        <option value="<?php echo $h['id']; ?>">
                            <?php echo htmlspecialchars($h['hospital_name']); ?> (<?php echo htmlspecialchars($h['city']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Groupe sanguin <span class="required">*</span></label>
                    <select name="blood_group_id" class="form-control" required>
                        <option value="">Sélectionner...</option>
                        <?php foreach ($blood_groups as $bg): ?>
                        <option value="<?php echo $bg['id']; ?>">
                            Groupe <?php echo $bg['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantité (unités) <span class="required">*</span></label>
                    <input type="number" name="quantity" class="form-control" min="1" max="20" value="1" required>
                </div>

                <div class="form-group">
                    <label>Date limite <span class="required">*</span></label>
                    <input type="date" name="needed_by" class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Patient <span class="required">*</span></label>
                    <input type="text" name="patient_name" class="form-control" placeholder="Nom du patient" required>
                </div>

                <div class="form-group">
                    <label>Urgence</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="urgency" value="normal" checked> <span>Normal</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="urgency" value="urgent"> <span>Haut</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="urgency" value="emergency"> <span>Urgence</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Créer
                    </button>
                    <a href="requests.php" class="btn btn-outline">
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>