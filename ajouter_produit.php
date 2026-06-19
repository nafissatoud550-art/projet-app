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

// Récupérer les catégories pour le formulaire
$sql_categories = "SELECT * FROM categorie ORDER BY nom_categorie";
$result_categories = $conn->query($sql_categories);

// --- TRAITEMENT DES ACTIONS ---
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// --- SUPPRESSION ---
if ($action === 'delete' && $id > 0) {
    // Vérifier si le produit est utilisé dans des approvisionnements
    $check_sql = "SELECT COUNT(*) as count FROM detail_approvisionnement WHERE Id_produit = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_data = $check_result->fetch_assoc();
    $check_stmt->close();

    if ($check_data['count'] > 0) {
        $error_message = "Ce produit ne peut pas être supprimé car il est lié à des approvisionnements.";
    } else {
        $delete_sql = "DELETE FROM produit WHERE Id_produit = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Produit supprimé avec succès !";
        } else {
            $error_message = "Erreur lors de la suppression du produit.";
        }
        $delete_stmt->close();
    }
}

// --- AJOUT / MODIFICATION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom_produit = trim($_POST['nom_produit']);
    $prix_unitaire = trim($_POST['prix_unitaire']);
    $quantite = trim($_POST['quantite']);
    $stock_minimum = trim($_POST['stock_minimum']);
    $id_categorie = intval($_POST['id_categorie']);
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

    // Validation
    $errors = [];
    
    if (empty($nom_produit)) {
        $errors[] = "Le nom du produit est obligatoire.";
    }
    if (!is_numeric($prix_unitaire) || $prix_unitaire < 0) {
        $errors[] = "Le prix unitaire doit être un nombre valide.";
    }
    if (!is_numeric($quantite) || $quantite < 0) {
        $errors[] = "La quantité doit être un nombre valide.";
    }
    if (!is_numeric($stock_minimum) || $stock_minimum < 0) {
        $errors[] = "Le stock minimum doit être un nombre valide.";
    }
    if ($id_categorie <= 0) {
        $errors[] = "Veuillez sélectionner une catégorie.";
    }

    if (empty($errors)) {
        if ($edit_id > 0) {
            // MODIFICATION
            $update_sql = "UPDATE produit SET 
                           nom_produit = ?, 
                           prix_unitaire = ?, 
                           quantite = ?, 
                           stock_minimum = ?, 
                           Id_categorie = ? 
                           WHERE Id_produit = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("siiiii", $nom_produit, $prix_unitaire, $quantite, $stock_minimum, $id_categorie, $edit_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Produit modifié avec succès !";
            } else {
                $error_message = "Erreur lors de la modification du produit : " . $conn->error;
            }
            $update_stmt->close();
        } else {
            // AJOUT
            $insert_sql = "INSERT INTO produit (nom_produit, prix_unitaire, quantite, stock_minimum, Id_categorie) 
                           VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("siiii", $nom_produit, $prix_unitaire, $quantite, $stock_minimum, $id_categorie);
            
            if ($insert_stmt->execute()) {
                $success_message = "Produit ajouté avec succès !";
            } else {
                $error_message = "Erreur lors de l'ajout du produit : " . $conn->error;
            }
            $insert_stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Récupération des produits avec leur catégorie
$sql = "SELECT p.*, c.nom_categorie 
        FROM produit p
        LEFT JOIN categorie c ON p.Id_categorie = c.Id_categorie
        ORDER BY p.Id_produit DESC";
$result = $conn->query($sql);

// Récupération des données du produit à modifier
$edit_data = null;
if ($action === 'edit' && $id > 0) {
    $edit_sql = "SELECT * FROM produit WHERE Id_produit = ?";
    $edit_stmt = $conn->prepare($edit_sql);
    $edit_stmt->bind_param("i", $id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    $edit_data = $edit_result->fetch_assoc();
    $edit_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - Gestion de Stock</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>

<body>

    <!-- Navbar -->
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

    <!-- Container -->
    <div class="container">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>Gestion des Produits</h1>
                <p>Ajoutez, modifiez ou supprimez des produits du stock</p>
            </div>
            <div class="header-actions">
                <button onclick="openAddModal()" class="btn btn-primary">Ajouter un produit</button>
            </div>
        </div>

        <!-- Messages -->
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

        <!-- Liste des produits -->
        <div class="card">
            <div class="card-header">
                <h3>Liste des produits</h3>
                <span class="badge badge-primary">Total: <?php echo $result->num_rows; ?></span>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Produit</th>
                                <th>Catégorie</th>
                                <th>Prix unitaire</th>
                                <th>Stock</th>
                                <th>Stock min</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): 
                                $stock_status = '';
                                $stock_color = '';
                                if ($row['quantite'] <= 0) {
                                    $stock_status = 'Rupture';
                                    $stock_color = 'danger';
                                } elseif ($row['quantite'] <= $row['stock_minimum']) {
                                    $stock_status = 'Stock bas';
                                    $stock_color = 'warning';
                                } else {
                                    $stock_status = 'OK';
                                    $stock_color = 'success';
                                }
                            ?>
                            <tr>
                                <td><span class="badge badge-primary">#<?php echo $row['Id_produit']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($row['nom_produit']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['nom_categorie'] ?? 'Non catégorisé'); ?></td>
                                <td><?php echo number_format($row['prix_unitaire'], 0, ',', ' '); ?> FCFA</td>
                                <td><strong><?php echo $row['quantite']; ?></strong></td>
                                <td><?php echo $row['stock_minimum']; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $stock_color; ?>">
                                        <span class="stock-dot <?php echo $stock_color; ?>"></span>
                                        <?php echo $stock_status; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="?action=edit&id=<?php echo $row['Id_produit']; ?>"
                                            class="btn btn-warning btn-sm">Modifier</a>
                                        <a href="#"
                                            onclick="confirmDelete(<?php echo $row['Id_produit']; ?>, '<?php echo htmlspecialchars($row['nom_produit']); ?>')"
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
                    Aucun produit enregistré.<br>
                    <small>Cliquez sur "Ajouter un produit" pour commencer.</small>
                </div>
                <?php endif; ?>
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
                <h3 id="modalTitle"><?php echo $edit_data ? 'Modifier le produit' : 'Ajouter un produit'; ?></h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <?php if ($edit_data): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_data['Id_produit']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nom_produit">Nom du produit <span class="required">*</span></label>
                        <input type="text" id="nom_produit" name="nom_produit" class="form-control"
                            placeholder="Ex: Riz, Ordinateur..." required
                            value="<?php echo $edit_data ? htmlspecialchars($edit_data['nom_produit']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="id_categorie">Catégorie <span class="required">*</span></label>
                        <select id="id_categorie" name="id_categorie" class="form-control" required>
                            <option value="">-- Sélectionner une catégorie --</option>
                            <?php 
                            $result_categories->data_seek(0);
                            while ($categorie = $result_categories->fetch_assoc()): 
                                $selected = ($edit_data && $edit_data['Id_categorie'] == $categorie['Id_categorie']) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $categorie['Id_categorie']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($categorie['nom_categorie']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="prix_unitaire">Prix unitaire (FCFA) <span class="required">*</span></label>
                            <input type="number" id="prix_unitaire" name="prix_unitaire" class="form-control"
                                placeholder="0" required min="0"
                                value="<?php echo $edit_data ? $edit_data['prix_unitaire'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="quantite">Quantité en stock <span class="required">*</span></label>
                            <input type="number" id="quantite" name="quantite" class="form-control" placeholder="0"
                                required min="0" value="<?php echo $edit_data ? $edit_data['quantite'] : '0'; ?>">
                        </div>
                        <div class="form-group">
                            <label for="stock_minimum">Stock minimum <span class="required">*</span></label>
                            <input type="number" id="stock_minimum" name="stock_minimum" class="form-control"
                                placeholder="0" required min="0"
                                value="<?php echo $edit_data ? $edit_data['stock_minimum'] : '5'; ?>">
                        </div>
                    </div>

                    <?php if ($edit_data): ?>
                    <div class="form-check">
                        <input type="checkbox" id="reset_stock" name="reset_stock" value="1">
                        <label for="reset_stock">Réinitialiser la quantité à zéro</label>
                    </div>
                    <script>
                    document.getElementById('reset_stock')?.addEventListener('change', function() {
                        const qtyInput = document.getElementById('quantite');
                        if (this.checked) {
                            qtyInput.value = 0;
                            qtyInput.disabled = true;
                        } else {
                            qtyInput.disabled = false;
                            qtyInput.value = <?php echo $edit_data['quantite']; ?>;
                        }
                    });
                    </script>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <?php echo $edit_data ? 'Mettre à jour' : 'Ajouter'; ?>
                    </button>
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
                <p>Êtes-vous sûr de vouloir supprimer le produit <strong id="deleteName"></strong> ?</p>
                <p style="color: #dc3545; font-size: 14px; margin-top: 10px;">
                    Cette action est irréversible et supprimera également les détails d'approvisionnement associés.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Annuler</button>
                <a href="#" id="deleteLink" class="btn btn-danger">Supprimer</a>
            </div>
        </div>
    </div>

    <script>
    // Ouvrir le modal d'ajout
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Ajouter un produit';
        document.getElementById('formModal').classList.add('active');
    }

    // Fermer le modal
    function closeModal() {
        document.getElementById('formModal').classList.remove('active');
    }

    // Confirmer la suppression
    function confirmDelete(id, name) {
        document.getElementById('deleteName').textContent = name;
        document.getElementById('deleteLink').href = '?action=delete&id=' + id;
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

    // Fonction de recherche/filtrage (simple)
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
    </script>

</body>

</html>