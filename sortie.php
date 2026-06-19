<?php
// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure la connexion à la base de données
require_once 'connect.php';

// Variables pour les messages
$success_message = '';
$error_message = '';

// Récupérer la liste des produits
$sql_produits = "SELECT Id_produit, nom_produit, prix_unitaire, quantite FROM produit ORDER BY nom_produit";
$result_produits = $conn->query($sql_produits);

// --- TRAITEMENT DU FORMULAIRE DE SORTIE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add') {
    $id_produit = intval($_POST['id_produit']);
    $quantite_vendue = intval($_POST['quantite_vendue']);
    $date_sortie = trim($_POST['date_sortie']);
    
    // Validation
    $errors = [];
    
    if ($id_produit <= 0) {
        $errors[] = "Veuillez sélectionner un produit.";
    }
    if ($quantite_vendue <= 0) {
        $errors[] = "La quantité doit être supérieure à 0.";
    }
    if (empty($date_sortie)) {
        $errors[] = "Veuillez sélectionner une date.";
    }
    
    if (empty($errors)) {
        // Vérifier si le stock est suffisant
        $check_sql = "SELECT quantite, nom_produit FROM produit WHERE Id_produit = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $id_produit);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $produit = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if (!$produit) {
            $error_message = "Produit non trouvé.";
        } elseif ($produit['quantite'] < $quantite_vendue) {
            $error_message = "Stock insuffisant. Stock disponible : " . $produit['quantite'] . " " . $produit['nom_produit'];
        } else {
            // Démarrer une transaction
            $conn->begin_transaction();
            
            try {
                // 1. Mettre à jour le stock
                $update_sql = "UPDATE produit SET quantite = quantite - ? WHERE Id_produit = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $quantite_vendue, $id_produit);
                $update_stmt->execute();
                $update_stmt->close();
                
                // 2. Enregistrer la sortie
                $insert_sql = "INSERT INTO sortie (Id_produit, Date_sortie, quantite_vendue) 
                               VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("isi", $id_produit, $date_sortie, $quantite_vendue);
                
                if ($insert_stmt->execute()) {
                    $id_sortie = $conn->insert_id;
                    $insert_stmt->close();
                    
                    // Valider la transaction
                    $conn->commit();
                    $success_message = "Sortie de stock enregistrée avec succès ! ID: " . $id_sortie;
                } else {
                    throw new Exception("Erreur lors de l'insertion : " . $insert_stmt->error);
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Erreur : " . $e->getMessage();
            }
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// --- SUPPRESSION/ANNULATION D'UNE SORTIE ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Vérifier si la sortie existe et récupérer les informations
    $check_sql = "SELECT s.*, p.nom_produit 
                  FROM sortie s 
                  JOIN produit p ON s.Id_produit = p.Id_produit 
                  WHERE s.Id_sortie = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $sortie = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if ($sortie) {
        // Démarrer une transaction
        $conn->begin_transaction();
        
        try {
            // 1. Rétablir le stock
            $update_sql = "UPDATE produit SET quantite = quantite + ? WHERE Id_produit = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $sortie['quantite_vendue'], $sortie['Id_produit']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // 2. Supprimer la sortie
            $delete_sql = "DELETE FROM sortie WHERE Id_sortie = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            $conn->commit();
            $success_message = "Sortie annulée et stock rétabli avec succès !";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Erreur : " . $e->getMessage();
        }
    } else {
        $error_message = "Sortie non trouvée.";
    }
}

// Récupération des sorties avec les noms des produits
$sql_sorties = "SELECT s.*, p.nom_produit 
                FROM sortie s
                JOIN produit p ON s.Id_produit = p.Id_produit
                ORDER BY s.Date_sortie DESC";
$result_sorties = $conn->query($sql_sorties);

// Vérifier si la requête a réussi
if (!$result_sorties) {
    $error_message = "Erreur SQL : " . $conn->error;
}

// Statistiques
$sql_stats = "SELECT 
                COUNT(*) as total_sorties,
                SUM(quantite_vendue) as total_quantite,
                COUNT(DISTINCT Id_produit) as produits_vendus
              FROM sortie";
$stats_result = $conn->query($sql_stats);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total_sorties' => 0, 'total_quantite' => 0, 'produits_vendus' => 0];

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sortie de Stock - Gestion de Stock</title>
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
        flex-wrap: wrap;
        gap: 10px;
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
        flex-wrap: wrap;
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

    .container {
        max-width: 1400px;
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

    .btn-danger {
        background: #dc3545;
        color: white;
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
    }

    .btn-warning {
        background: #ffc107;
        color: #333;
    }

    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(255, 193, 7, 0.3);
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

    select.form-control {
        appearance: auto;
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

    .badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        display: inline-block;
    }

    .badge-primary {
        background: #cce5ff;
        color: #004085;
    }

    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }

    .stock-info {
        padding: 10px 15px;
        background: #f8f9fa;
        border-radius: 8px;
        font-size: 14px;
        color: #333;
        margin-top: 5px;
    }

    .stock-info strong {
        color: #667eea;
    }

    .footer {
        text-align: center;
        padding: 20px;
        margin-top: 30px;
        color: #888;
        font-size: 13px;
        border-top: 1px solid #e9ecef;
    }

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
        max-width: 600px;
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
    </style>
</head>

<body>

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

    <div class="container">

        <div class="page-header">
            <div>
                <h1>📤 Sortie de Stock</h1>
                <p>Enregistrez les sorties de produits (ventes, utilisations, pertes)</p>
            </div>
            <div class="header-actions">
                <button onclick="openAddModal()" class="btn btn-danger">📤 Nouvelle sortie</button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="icon">📤</span>
                <div class="number"><?php echo $stats['total_sorties'] ?? 0; ?></div>
                <div class="label">Total sorties</div>
            </div>
            <div class="stat-card">
                <span class="icon">📦</span>
                <div class="number"><?php echo $stats['total_quantite'] ?? 0; ?></div>
                <div class="label">Produits sortis</div>
            </div>
            <div class="stat-card">
                <span class="icon">🏷️</span>
                <div class="number"><?php echo $stats['produits_vendus'] ?? 0; ?></div>
                <div class="label">Produits différents</div>
            </div>
        </div>

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

        <div class="card">
            <div class="card-header">
                <h3>📋 Historique des sorties</h3>
                <span class="badge badge-primary">Total:
                    <?php echo $result_sorties ? $result_sorties->num_rows : 0; ?></span>
            </div>
            <div class="card-body">
                <?php if ($result_sorties && $result_sorties->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Produit</th>
                                <th>Quantité sortie</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result_sorties->fetch_assoc()): ?>
                            <tr>
                                <td><span class="badge badge-primary">#<?php echo $row['Id_sortie']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($row['nom_produit']); ?></strong></td>
                                <td><span class="badge badge-danger">-<?php echo $row['quantite_vendue']; ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($row['Date_sortie'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="#"
                                            onclick="confirmDelete(<?php echo $row['Id_sortie']; ?>, '<?php echo htmlspecialchars($row['nom_produit']); ?>', <?php echo $row['quantite_vendue']; ?>)"
                                            class="btn btn-warning btn-sm">↩️ Annuler</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-message">
                    <span class="icon">📤</span>
                    Aucune sortie de stock enregistrée.<br>
                    <small>Cliquez sur "Nouvelle sortie" pour commencer.</small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer">
            <p>&copy; 2026 - Système de Gestion de Stock. Tous droits réservés.</p>
        </div>
    </div>

    <!-- MODAL: Nouvelle sortie -->
    <div class="modal-overlay" id="formModal">
        <div class="modal">
            <div class="modal-header">
                <h3>📤 Nouvelle sortie de stock</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="sortieForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="id_produit">Produit <span class="required">*</span></label>
                        <select id="id_produit" name="id_produit" class="form-control" required
                            onchange="updateStockInfo()">
                            <option value="">-- Sélectionner un produit --</option>
                            <?php 
                            if ($result_produits) {
                                $result_produits->data_seek(0);
                                while ($produit = $result_produits->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $produit['Id_produit']; ?>"
                                data-stock="<?php echo $produit['quantite']; ?>">
                                <?php echo htmlspecialchars($produit['nom_produit']); ?>
                                (Stock: <?php echo $produit['quantite']; ?>)
                            </option>
                            <?php endwhile; } ?>
                        </select>
                        <div class="stock-info" id="stockInfo" style="display: none;">
                            Stock disponible : <strong id="stockDisponible">0</strong> unités
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantite_vendue">Quantité à sortir <span class="required">*</span></label>
                            <input type="number" id="quantite_vendue" name="quantite_vendue" class="form-control"
                                placeholder="0" required min="1" onchange="validateQuantity()">
                        </div>
                        <div class="form-group">
                            <label for="date_sortie">Date <span class="required">*</span></label>
                            <input type="date" id="date_sortie" name="date_sortie" class="form-control" required
                                value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                        <p style="font-size: 13px; color: #666; margin: 0;">
                            ⚠️ <strong>Attention :</strong> Cette opération est irréversible sauf
                            annulation par un administrateur. Vérifiez bien la quantité.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-danger">📤 Enregistrer la sortie</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: Confirmation annulation -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <h3>↩️ Annuler la sortie</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir annuler cette sortie ?</p>
                <p style="margin-top: 10px;">
                    Produit : <strong id="deleteProduct"></strong><br>
                    Quantité : <strong id="deleteQuantity"></strong>
                </p>
                <p style="color: #28a745; font-size: 14px; margin-top: 10px;">
                    ✅ Le stock sera automatiquement rétabli.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Non, annuler</button>
                <a href="#" id="deleteLink" class="btn btn-warning">↩️ Oui, annuler la sortie</a>
            </div>
        </div>
    </div>

    <script>
    function updateStockInfo() {
        const select = document.getElementById('id_produit');
        const stockInfo = document.getElementById('stockInfo');
        const stockDisponible = document.getElementById('stockDisponible');

        if (select.value) {
            const selectedOption = select.options[select.selectedIndex];
            const stock = selectedOption.dataset.stock || 0;
            stockDisponible.textContent = stock;
            stockInfo.style.display = 'block';
        } else {
            stockInfo.style.display = 'none';
        }
    }

    function validateQuantity() {
        const select = document.getElementById('id_produit');
        const quantite = document.getElementById('quantite_vendue');

        if (select.value && quantite.value) {
            const selectedOption = select.options[select.selectedIndex];
            const stock = parseInt(selectedOption.dataset.stock) || 0;
            const qty = parseInt(quantite.value) || 0;

            if (qty > stock) {
                quantite.style.borderColor = '#dc3545';
                alert('⚠️ Quantité insuffisante en stock. Stock disponible : ' + stock);
                quantite.value = stock;
            } else {
                quantite.style.borderColor = '#28a745';
            }
        }
    }

    function openAddModal() {
        document.getElementById('formModal').classList.add('active');
        document.getElementById('stockInfo').style.display = 'none';
    }

    function closeModal() {
        document.getElementById('formModal').classList.remove('active');
    }

    function confirmDelete(id, product, quantity) {
        document.getElementById('deleteProduct').textContent = product;
        document.getElementById('deleteQuantity').textContent = quantity;
        document.getElementById('deleteLink').href = '?action=delete&id=' + id;
        document.getElementById('deleteModal').classList.add('active');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });

    document.getElementById('sortieForm').addEventListener('submit', function(e) {
        const select = document.getElementById('id_produit');
        const quantite = document.getElementById('quantite_vendue');
        let errors = [];

        if (!select.value) {
            errors.push('Veuillez sélectionner un produit.');
        }
        if (!quantite.value || parseInt(quantite.value) <= 0) {
            errors.push('La quantité doit être supérieure à 0.');
        }

        if (errors.length > 0) {
            e.preventDefault();
            alert('❌ ' + errors.join('\n'));
        }
    });
    </script>

</body>

</html>