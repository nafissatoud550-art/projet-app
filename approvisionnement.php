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

// Récupérer les listes pour les sélections
$sql_produits = "SELECT Id_produit, nom_produit, prix_unitaire, quantite FROM produit ORDER BY nom_produit";
$result_produits = $conn->query($sql_produits);

$sql_fournisseurs = "SELECT Id_fournisseur, nom_fournisseur, prenom_fournisseur FROM fournisseur ORDER BY nom_fournisseur";
$result_fournisseurs = $conn->query($sql_fournisseurs);

// --- TRAITEMENT DU FORMULAIRE D'AJOUT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add') {
    $nom_approvisionnement = trim($_POST['nom_approvisionnement']);
    $date_approvisionnement = trim($_POST['date_approvisionnement']);
    $id_fournisseur = intval($_POST['id_fournisseur']);
    $id_utilisateur = $_SESSION['user_id'];
    
    // Validation
    if (empty($nom_approvisionnement) || empty($date_approvisionnement) || $id_fournisseur <= 0) {
        $error_message = "Veuillez remplir tous les champs obligatoires.";
    } else {
        // Démarrer une transaction
        $conn->begin_transaction();
        
        try {
            // 1. Insérer l'approvisionnement
            $sql_approv = "INSERT INTO approvisionnement (nom_approvisionnement, Date_approvisionnement, Id_utilisateur) 
                           VALUES (?, ?, ?)";
            $stmt_approv = $conn->prepare($sql_approv);
            $stmt_approv->bind_param("ssi", $nom_approvisionnement, $date_approvisionnement, $id_utilisateur);
            $stmt_approv->execute();
            $id_approvisionnement = $conn->insert_id;
            $stmt_approv->close();
            
            // 2. Lier le fournisseur à l'approvisionnement
            $sql_fournir = "INSERT INTO fournir (Id_fournisseur, Id_approvisionnement) VALUES (?, ?)";
            $stmt_fournir = $conn->prepare($sql_fournir);
            $stmt_fournir->bind_param("ii", $id_fournisseur, $id_approvisionnement);
            $stmt_fournir->execute();
            $stmt_fournir->close();
            
            // 3. Ajouter les détails des produits
            $produits = isset($_POST['produits']) ? $_POST['produits'] : [];
            $quantites = isset($_POST['quantites']) ? $_POST['quantites'] : [];
            $prix_achat = isset($_POST['prix_achat']) ? $_POST['prix_achat'] : [];
            
            if (empty($produits)) {
                throw new Exception("Veuillez ajouter au moins un produit.");
            }
            
            $sql_detail = "INSERT INTO detail_approvisionnement (Id_approvisionnement, Id_produit, quantite_recue, prix_d_achat) 
                           VALUES (?, ?, ?, ?)";
            $stmt_detail = $conn->prepare($sql_detail);
            
            for ($i = 0; $i < count($produits); $i++) {
                $id_produit = intval($produits[$i]);
                $quantite = intval($quantites[$i]);
                $prix = intval($prix_achat[$i]);
                
                if ($id_produit > 0 && $quantite > 0 && $prix >= 0) {
                    $stmt_detail->bind_param("iiii", $id_approvisionnement, $id_produit, $quantite, $prix);
                    $stmt_detail->execute();
                    
                    // Mettre à jour la quantité en stock
                    $sql_update_stock = "UPDATE produit SET quantite = quantite + ? WHERE Id_produit = ?";
                    $stmt_update = $conn->prepare($sql_update_stock);
                    $stmt_update->bind_param("ii", $quantite, $id_produit);
                    $stmt_update->execute();
                    $stmt_update->close();
                }
            }
            $stmt_detail->close();
            
            // Valider la transaction
            $conn->commit();
            $success_message = "Approvisionnement enregistré avec succès ! ID: " . $id_approvisionnement;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            $conn->rollback();
            $error_message = "Erreur : " . $e->getMessage();
        }
    }
}

// --- SUPPRESSION D'UN APPROVISIONNEMENT ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Vérifier si l'approvisionnement existe
    $check_sql = "SELECT Id_approvisionnement FROM approvisionnement WHERE Id_approvisionnement = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Supprimer l'approvisionnement (les détails seront supprimés en cascade)
        $delete_sql = "DELETE FROM approvisionnement WHERE Id_approvisionnement = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Approvisionnement supprimé avec succès !";
        } else {
            $error_message = "Erreur lors de la suppression.";
        }
        $delete_stmt->close();
    }
    $check_stmt->close();
}

// Récupération des approvisionnements pour l'affichage
$sql = "SELECT a.*, u.noms_utilisateur, f.nom_fournisseur, f.prenom_fournisseur 
        FROM approvisionnement a
        JOIN utilisateur u ON a.Id_utilisateur = u.Id_utilisateur
        LEFT JOIN fournir fn ON a.Id_approvisionnement = fn.Id_approvisionnement
        LEFT JOIN fournisseur f ON fn.Id_fournisseur = f.Id_fournisseur
        ORDER BY a.Date_approvisionnement DESC";
$result = $conn->query($sql);

// Vérifier si la requête a réussi
if (!$result) {
    $error_message = "Erreur SQL : " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Approvisionnements - Gestion de Stock</title>
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

    .btn-success {
        background: linear-gradient(135deg, #11998e, #38ef7d);
        color: white;
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(17, 153, 142, 0.3);
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

    .product-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 0.5fr;
        gap: 15px;
        align-items: end;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
        margin-bottom: 10px;
    }

    .product-row .form-group {
        margin-bottom: 0;
    }

    @media (max-width: 768px) {
        .product-row {
            grid-template-columns: 1fr;
            gap: 10px;
        }
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
        max-width: 900px;
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
                <h1>📦 Gestion des Approvisionnements</h1>
                <p>Enregistrez les entrées de stock et suivez l'historique des approvisionnements</p>
            </div>
            <div class="header-actions">
                <button onclick="openAddModal()" class="btn btn-primary">➕ Nouvel approvisionnement</button>
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
                <h3>📋 Historique des approvisionnements</h3>
                <span class="badge badge-primary">Total: <?php echo $result ? $result->num_rows : 0; ?></span>
            </div>
            <div class="card-body">
                <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Date</th>
                                <th>Fournisseur</th>
                                <th>Utilisateur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><span
                                        class="badge badge-primary">#<?php echo $row['Id_approvisionnement']; ?></span>
                                </td>
                                <td><strong><?php echo htmlspecialchars($row['nom_approvisionnement']); ?></strong></td>
                                <td><?php echo date('d/m/Y', strtotime($row['Date_approvisionnement'])); ?></td>
                                <td><?php echo htmlspecialchars($row['prenom_fournisseur'] ?? '') . ' ' . htmlspecialchars($row['nom_fournisseur'] ?? ''); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['noms_utilisateur']); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="#"
                                            onclick="confirmDelete(<?php echo $row['Id_approvisionnement']; ?>, '<?php echo htmlspecialchars($row['nom_approvisionnement']); ?>')"
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
                    <span class="icon">📦</span>
                    Aucun approvisionnement enregistré.<br>
                    <small>Cliquez sur "Nouvel approvisionnement" pour commencer.</small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer">
            <p>&copy; 2026 - Système de Gestion de Stock. Tous droits réservés.</p>
        </div>
    </div>

    <!-- MODAL: Ajouter un approvisionnement -->
    <div class="modal-overlay" id="formModal">
        <div class="modal">
            <div class="modal-header">
                <h3>📦 Nouvel approvisionnement</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="approvForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom_approvisionnement">Nom de l'approvisionnement <span
                                    class="required">*</span></label>
                            <input type="text" id="nom_approvisionnement" name="nom_approvisionnement"
                                class="form-control" placeholder="Ex: Livraison Mars 2026" required>
                        </div>
                        <div class="form-group">
                            <label for="date_approvisionnement">Date <span class="required">*</span></label>
                            <input type="date" id="date_approvisionnement" name="date_approvisionnement"
                                class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="id_fournisseur">Fournisseur <span class="required">*</span></label>
                        <select id="id_fournisseur" name="id_fournisseur" class="form-control" required>
                            <option value="">-- Sélectionner un fournisseur --</option>
                            <?php 
                            if ($result_fournisseurs) {
                                $result_fournisseurs->data_seek(0);
                                while ($fournisseur = $result_fournisseurs->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $fournisseur['Id_fournisseur']; ?>">
                                <?php echo htmlspecialchars($fournisseur['prenom_fournisseur'] . ' ' . $fournisseur['nom_fournisseur']); ?>
                            </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>

                    <div style="margin-top: 25px;">
                        <h4 style="margin-bottom: 15px; color: #333;">📦 Produits reçus</h4>
                        <div id="productsContainer">
                            <div class="product-row">
                                <div class="form-group">
                                    <label>Produit <span class="required">*</span></label>
                                    <select name="produits[]" class="form-control" required>
                                        <option value="">-- Choisir --</option>
                                        <?php 
                                        if ($result_produits) {
                                            $result_produits->data_seek(0);
                                            while ($produit = $result_produits->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo $produit['Id_produit']; ?>">
                                            <?php echo htmlspecialchars($produit['nom_produit']); ?>
                                            (<?php echo $produit['prix_unitaire']; ?> FCFA)
                                        </option>
                                        <?php endwhile; } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Quantité <span class="required">*</span></label>
                                    <input type="number" name="quantites[]" class="form-control" placeholder="Qté"
                                        required min="1">
                                </div>
                                <div class="form-group">
                                    <label>Prix d'achat (FCFA)</label>
                                    <input type="number" name="prix_achat[]" class="form-control" placeholder="Prix"
                                        min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeProductRow(this)"
                                        style="margin-top: 24px;">
                                        ❌
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addProductRow()"
                            style="margin-top: 10px;">
                            ➕ Ajouter un produit
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-success">💾 Enregistrer l'approvisionnement</button>
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
                <p>Êtes-vous sûr de vouloir supprimer l'approvisionnement <strong id="deleteName"></strong> ?</p>
                <p style="color: #dc3545; font-size: 14px; margin-top: 10px;">
                    ⚠️ Cette action est irréversible et supprimera tous les détails associés.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Annuler</button>
                <a href="#" id="deleteLink" class="btn btn-danger">🗑️ Supprimer</a>
            </div>
        </div>
    </div>

    <script>
    function addProductRow() {
        const container = document.getElementById('productsContainer');
        const firstRow = container.querySelector('.product-row');
        const newRow = firstRow.cloneNode(true);

        newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
        newRow.querySelectorAll('input').forEach(input => input.value = '');

        container.appendChild(newRow);
    }

    function removeProductRow(button) {
        const container = document.getElementById('productsContainer');
        if (container.querySelectorAll('.product-row').length > 1) {
            button.closest('.product-row').remove();
        } else {
            alert('Vous devez avoir au moins un produit.');
        }
    }

    function openAddModal() {
        document.getElementById('formModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('formModal').classList.remove('active');
    }

    function confirmDelete(id, name) {
        document.getElementById('deleteName').textContent = name;
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

    document.getElementById('approvForm').addEventListener('submit', function(e) {
        const produits = document.querySelectorAll('select[name="produits[]"]');
        let hasProduct = false;

        produits.forEach(select => {
            if (select.value !== '') {
                hasProduct = true;
            }
        });

        if (!hasProduct) {
            e.preventDefault();
            alert('Veuillez sélectionner au moins un produit.');
        }
    });
    </script>

</body>

</html>