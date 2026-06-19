<?php
// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Inclure la connexion à la base de données
require_once 'connect.php';

// Variables pour les messages
$success_message = '';
$error_message = '';

// --- TRAITEMENT DES ACTIONS ---
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// --- SUPPRESSION ---
if ($action === 'delete' && $id > 0) {
    // Vérifier si le fournisseur est utilisé dans des approvisionnements
    $check_sql = "SELECT COUNT(*) as count FROM fournir WHERE Id_fournisseur = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_data = $check_result->fetch_assoc();
    $check_stmt->close();

    if ($check_data['count'] > 0) {
        $error_message = "Ce fournisseur ne peut pas être supprimé car il est lié à des approvisionnements.";
    } else {
        $delete_sql = "DELETE FROM fournisseur WHERE Id_fournisseur = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Fournisseur supprimé avec succès !";
        } else {
            $error_message = "Erreur lors de la suppression du fournisseur.";
        }
        $delete_stmt->close();
    }
}

// --- AJOUT / MODIFICATION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom_fournisseur = trim($_POST['nom_fournisseur']);
    $prenom_fournisseur = trim($_POST['prenom_fournisseur']);
    $adresse = trim($_POST['adresse']);
    $numero = trim($_POST['numero']);
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

    // Validation
    $errors = [];
    
    if (empty($nom_fournisseur)) {
        $errors[] = "Le nom du fournisseur est obligatoire.";
    }
    if (empty($prenom_fournisseur)) {
        $errors[] = "Le prénom du fournisseur est obligatoire.";
    }
    if (empty($adresse)) {
        $errors[] = "L'adresse est obligatoire.";
    }
    if (empty($numero)) {
        $errors[] = "Le numéro de téléphone est obligatoire.";
    }

    if (empty($errors)) {
        if ($edit_id > 0) {
            // MODIFICATION
            $update_sql = "UPDATE fournisseur SET 
                           nom_fournisseur = ?, 
                           prenom_fournisseur = ?, 
                           adresse = ?, 
                           numero = ?
                           WHERE Id_fournisseur = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssi", $nom_fournisseur, $prenom_fournisseur, $adresse, $numero, $edit_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Fournisseur modifié avec succès !";
            } else {
                $error_message = "Erreur lors de la modification du fournisseur : " . $conn->error;
            }
            $update_stmt->close();
        } else {
            // AJOUT
            $insert_sql = "INSERT INTO fournisseur (nom_fournisseur, prenom_fournisseur, adresse, numero) 
                           VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssss", $nom_fournisseur, $prenom_fournisseur, $adresse, $numero);
            
            if ($insert_stmt->execute()) {
                $success_message = "Fournisseur ajouté avec succès !";
            } else {
                $error_message = "Erreur lors de l'ajout du fournisseur : " . $conn->error;
            }
            $insert_stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Récupération des fournisseurs avec leur nombre d'approvisionnements
$sql = "SELECT f.*, COUNT(fn.Id_approvisionnement) as nb_approvisionnements 
        FROM fournisseur f
        LEFT JOIN fournir fn ON f.Id_fournisseur = fn.Id_fournisseur
        GROUP BY f.Id_fournisseur
        ORDER BY f.Id_fournisseur DESC";
$result = $conn->query($sql);

// Récupération des données du fournisseur à modifier
$edit_data = null;
if ($action === 'edit' && $id > 0) {
    $edit_sql = "SELECT * FROM fournisseur WHERE Id_fournisseur = ?";
    $edit_stmt = $conn->prepare($edit_sql);
    $edit_stmt->bind_param("i", $id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    $edit_data = $edit_result->fetch_assoc();
    $edit_stmt->close();
}

// Statistiques
$sql_stats = "SELECT COUNT(*) as total_fournisseurs FROM fournisseur";
$stats_result = $conn->query($sql_stats);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Fournisseurs - Gestion de Stock</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f0f2f5;
        min-height: 100vh;
    }

    /* Navbar */
    .navbar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 15px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .navbar-brand {
        color: white;
        font-size: 24px;
        font-weight: 600;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .navbar-right .user-info {
        color: rgba(255, 255, 255, 0.9);
        font-size: 14px;
    }

    .navbar-right .user-info strong {
        color: white;
    }

    .btn-logout {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        padding: 8px 20px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        font-size: 14px;
    }

    .btn-logout:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }

    .btn-back {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        padding: 8px 20px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        font-size: 14px;
    }

    .btn-back:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }

    /* Container */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 30px 20px;
        animation: fadeIn 0.5s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .page-header h1 {
        font-size: 28px;
        color: #333;
    }

    .page-header p {
        color: #666;
        font-size: 14px;
    }

    .header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    /* Buttons */
    .btn {
        padding: 10px 25px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, #11998e, #38ef7d);
        color: white;
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(17, 153, 142, 0.3);
    }

    .btn-warning {
        background: #ffc107;
        color: #333;
    }

    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(255, 193, 7, 0.3);
    }

    .btn-danger {
        background: #dc3545;
        color: white;
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        transform: translateY(-2px);
    }

    .btn-sm {
        padding: 6px 15px;
        font-size: 12px;
    }

    .btn-info {
        background: #17a2b8;
        color: white;
    }

    .btn-info:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(23, 162, 184, 0.3);
    }

    /* Card */
    .card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .card-header {
        padding: 20px 25px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .card-header h3 {
        font-size: 18px;
        color: #333;
    }

    .card-body {
        padding: 25px;
    }

    /* Form */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #333;
        font-weight: 500;
        font-size: 14px;
    }

    .form-group label .required {
        color: #dc3545;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
        outline: none;
        font-family: inherit;
    }

    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }

    /* Alert */
    .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border-left: 4px solid #28a745;
    }

    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border-left: 4px solid #dc3545;
    }

    /* Table */
    .table-responsive {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    table thead {
        background: #f8f9fa;
    }

    table th {
        padding: 12px 15px;
        text-align: left;
        color: #555;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e9ecef;
    }

    table td {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        color: #333;
    }

    table tbody tr:hover {
        background: #f8f9fa;
    }

    .actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .empty-message {
        text-align: center;
        color: #999;
        padding: 40px 0;
        font-size: 16px;
    }

    .empty-message .icon {
        font-size: 48px;
        display: block;
        margin-bottom: 15px;
    }

    /* Badge */
    .badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        display: inline-block;
    }

    .badge-success {
        background: #d4edda;
        color: #155724;
    }

    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }

    .badge-warning {
        background: #fff3cd;
        color: #856404;
    }

    .badge-primary {
        background: #cce5ff;
        color: #004085;
    }

    .badge-info {
        background: #d1ecf1;
        color: #0c5460;
    }

    /* Stats cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        text-align: center;
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-3px);
    }

    .stat-card .number {
        font-size: 32px;
        font-weight: 700;
        color: #333;
    }

    .stat-card .label {
        font-size: 14px;
        color: #888;
        margin-top: 5px;
    }

    .stat-card .icon {
        font-size: 30px;
        margin-bottom: 10px;
        display: block;
    }

    /* Footer */
    .footer {
        text-align: center;
        padding: 20px;
        margin-top: 30px;
        color: #888;
        font-size: 13px;
        border-top: 1px solid #e9ecef;
    }

    /* Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal {
        background: white;
        border-radius: 20px;
        max-width: 700px;
        width: 95%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 30px;
        animation: fadeIn 0.3s ease;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f0f0f0;
    }

    .modal-header h3 {
        font-size: 20px;
        color: #333;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: #999;
        transition: color 0.3s ease;
    }

    .modal-close:hover {
        color: #333;
    }

    .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #f0f0f0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .navbar {
            flex-direction: column;
            gap: 10px;
            padding: 15px;
        }

        .navbar-right {
            flex-wrap: wrap;
            justify-content: center;
        }

        .page-header {
            flex-direction: column;
            text-align: center;
        }

        .header-actions {
            justify-content: center;
        }

        .actions {
            flex-direction: column;
        }

        .actions .btn {
            width: 100%;
            justify-content: center;
        }

        .modal {
            padding: 20px;
        }

        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Search */
    .search-box {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .search-box input {
        padding: 8px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.3s ease;
        flex: 1;
        min-width: 200px;
    }

    .search-box input:focus {
        border-color: #667eea;
    }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">📦 Gestion de Stock</a>
        <div class="navbar-right">
            <span class="user-info">
                Bienvenue, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Utilisateur'); ?></strong>
                <span style="opacity:0.7;font-size:12px;margin-left:8px;">
                    (<?php echo htmlspecialchars($_SESSION['user_role'] ?? 'employe'); ?>)
                </span>
            </span>
            <a href="dashboard.php" class="btn-back">⬅ Retour</a>
            <a href="logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </nav>

    <!-- Container -->
    <div class="container">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>🏢 Gestion des Fournisseurs</h1>
                <p>Gérez les fournisseurs de votre stock</p>
            </div>
            <div class="header-actions">
                <button onclick="openAddModal()" class="btn btn-primary">➕ Ajouter un fournisseur</button>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="icon">🏢</span>
                <div class="number"><?php echo $stats['total_fournisseurs']; ?></div>
                <div class="label">Total fournisseurs</div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
        <div class="alert alert-success">
            ✅ <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger">
            ❌ <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Liste des fournisseurs -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Liste des fournisseurs</h3>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="🔍 Rechercher..." onkeyup="filterTable()">
                    <span class="badge badge-primary">Total: <?php echo $result->num_rows; ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Adresse</th>
                                <th>Téléphone</th>
                                <th>Appro.</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><span class="badge badge-primary">#<?php echo $row['Id_fournisseur']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($row['nom_fournisseur']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['prenom_fournisseur']); ?></td>
                                <td><?php echo htmlspecialchars($row['adresse']); ?></td>
                                <td><?php echo htmlspecialchars($row['numero']); ?></td>
                                <td>
                                    <span class="badge badge-info"><?php echo $row['nb_approvisionnements']; ?></span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="?action=edit&id=<?php echo $row['Id_fournisseur']; ?>"
                                            class="btn btn-warning btn-sm">✏️ Modifier</a>
                                        <a href="#"
                                            onclick="confirmDelete(<?php echo $row['Id_fournisseur']; ?>, '<?php echo htmlspecialchars($row['prenom_fournisseur'] . ' ' . $row['nom_fournisseur']); ?>', <?php echo $row['nb_approvisionnements']; ?>)"
                                            class="btn btn-danger btn-sm">🗑️ Supprimer</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-message">
                    <span class="icon">🏢</span>
                    Aucun fournisseur enregistré.<br>
                    <small>Cliquez sur "Ajouter un fournisseur" pour commencer.</small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Add Form -->
        <div class="card" style="background: #f8f9fa;">
            <div class="card-header">
                <h3>⚡ Ajout rapide</h3>
                <span class="badge badge-info">Sans rechargement</span>
            </div>
            <div class="card-body">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>"
                    class="quick-add-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="nom_fournisseur" class="form-control" placeholder="Nom *" required>
                        </div>
                        <div class="form-group">
                            <input type="text" name="prenom_fournisseur" class="form-control" placeholder="Prénom *"
                                required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="adresse" class="form-control" placeholder="Adresse *" required>
                        </div>
                        <div class="form-group">
                            <input type="tel" name="numero" class="form-control" placeholder="Téléphone *" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">➕ Ajouter</button>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; 2026 - Système de Gestion de Stock. Tous droits réservés.</p>
        </div>
    </div>

    <!-- MODAL: Ajouter / Modifier -->
    <div class="modal-overlay" id="formModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle">
                    <?php echo $edit_data ? '✏️ Modifier le fournisseur' : '➕ Ajouter un fournisseur'; ?></h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <?php if ($edit_data): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_data['Id_fournisseur']; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom_fournisseur">Nom <span class="required">*</span></label>
                            <input type="text" id="nom_fournisseur" name="nom_fournisseur" class="form-control"
                                placeholder="Ex: Diallo" required
                                value="<?php echo $edit_data ? htmlspecialchars($edit_data['nom_fournisseur']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="prenom_fournisseur">Prénom <span class="required">*</span></label>
                            <input type="text" id="prenom_fournisseur" name="prenom_fournisseur" class="form-control"
                                placeholder="Ex: Ali" required
                                value="<?php echo $edit_data ? htmlspecialchars($edit_data['prenom_fournisseur']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="adresse">Adresse <span class="required">*</span></label>
                        <input type="text" id="adresse" name="adresse" class="form-control"
                            placeholder="Ex: Sopim, Abidjan" required
                            value="<?php echo $edit_data ? htmlspecialchars($edit_data['adresse']) : ''; ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="numero">Téléphone <span class="required">*</span></label>
                            <input type="tel" id="numero" name="numero" class="form-control"
                                placeholder="Ex: 0504050607" required
                                value="<?php echo $edit_data ? htmlspecialchars($edit_data['numero']) : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <?php echo $edit_data ? '💾 Mettre à jour' : '✅ Ajouter'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: Confirmation suppression -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <h3>🗑️ Confirmer la suppression</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer le fournisseur <strong id="deleteName"></strong> ?</p>
                <div id="deleteWarning"
                    style="display: none; padding: 15px; background: #fff3cd; border-radius: 10px; margin-top: 15px;">
                    <p style="color: #856404; margin: 0;">
                        ⚠️ Ce fournisseur est lié à <strong id="approvCount"></strong> approvisionnement(s).
                        Vous devez d'abord les supprimer ou les réaffecter.
                    </p>
                </div>
                <p style="color: #dc3545; font-size: 14px; margin-top: 10px;">
                    ⚠️ Cette action est irréversible.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Annuler</button>
                <a href="#" id="deleteLink" class="btn btn-danger">🗑️ Supprimer</a>
            </div>
        </div>
    </div>

    <script>
    // Recherche
    function filterTable() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const table = document.querySelector('table tbody');
        if (!table) return;

        const rows = table.querySelectorAll('tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    }

    // Ouvrir le modal d'ajout
    function openAddModal() {
        document.getElementById('modalTitle').textContent = '➕ Ajouter un fournisseur';
        document.getElementById('formModal').classList.add('active');
    }

    // Fermer le modal
    function closeModal() {
        document.getElementById('formModal').classList.remove('active');
    }

    // Confirmer la suppression
    function confirmDelete(id, name, approvCount) {
        document.getElementById('deleteName').textContent = name;
        document.getElementById('deleteLink').href = '?action=delete&id=' + id;

        if (approvCount > 0) {
            document.getElementById('deleteWarning').style.display = 'block';
            document.getElementById('approvCount').textContent = approvCount;
            document.getElementById('deleteLink').style.opacity = '0.5';
            document.getElementById('deleteLink').style.cursor = 'not-allowed';
            document.getElementById('deleteLink').href = '#';
        } else {
            document.getElementById('deleteWarning').style.display = 'none';
            document.getElementById('deleteLink').style.opacity = '1';
            document.getElementById('deleteLink').style.cursor = 'pointer';
            document.getElementById('deleteLink').href = '?action=delete&id=' + id;
        }

        document.getElementById('deleteModal').classList.add('active');
    }

    // Fermer le modal de suppression
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }

    // Fermer les modals en cliquant en dehors
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // Fermer avec la touche Echap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });

    // Ouvrir automatiquement le modal en mode édition
    <?php if ($edit_data): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('formModal').classList.add('active');
    });
    <?php endif; ?>

    // Validation du formulaire rapide
    document.querySelector('.quick-add-form')?.addEventListener('submit', function(e) {
        const inputs = this.querySelectorAll('input[required]');
        let valid = true;
        inputs.forEach(input => {
            if (input.value.trim() === '') {
                valid = false;
                input.style.borderColor = '#dc3545';
            } else {
                input.style.borderColor = '#e0e0e0';
            }
        });
        if (!valid) {
            e.preventDefault();
            alert('Veuillez remplir tous les champs obligatoires (*).');
        }
    });
    </script>

</body>

</html>