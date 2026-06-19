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
    <link rel="stylesheet" href="assets/css/app.css">
</head>

<body>

    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Gestion de Stock</a>
        <div class="navbar-right">
            <span class="user-info">
                Bienvenue, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Utilisateur'); ?></strong>

            </span>
            <a href="dashboard.php" class="btn-back">Retour</a>
            <a href="logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </nav>

    <div class="container">

        <div class="page-header">
            <div>
                <h1>Gestion des Approvisionnements</h1>
                <p>Enregistrez les entrées de stock et suivez l'historique des approvisionnements</p>
            </div>
            <div class="header-actions">
                <button onclick="openAddModal()" class="btn btn-primary">Nouvel approvisionnement</button>
            </div>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Historique des approvisionnements</h3>
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
                                            class="btn btn-danger btn-sm">Supprimer</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-message">
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
                <h3>Nouvel approvisionnement</h3>
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
                        <h4 style="margin-bottom: 15px; color: #333;">Produits reçus</h4>
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
                                        
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addProductRow()"
                            style="margin-top: 10px;">
                            Ajouter un produit
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-success">Enregistrer l'approvisionnement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: Confirmation suppression -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Confirmer la suppression</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer l'approvisionnement <strong id="deleteName"></strong> ?</p>
                <p style="color: #dc3545; font-size: 14px; margin-top: 10px;">
                    Cette action est irréversible et supprimera tous les détails associés.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Annuler</button>
                <a href="#" id="deleteLink" class="btn btn-danger">Supprimer</a>
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