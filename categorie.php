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
    // Vérifier si la catégorie est utilisée par des produits
    $check_sql = "SELECT COUNT(*) as count FROM produit WHERE Id_categorie = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_data = $check_result->fetch_assoc();
    $check_stmt->close();

    if ($check_data['count'] > 0) {
        $error_message = "Cette catégorie ne peut pas être supprimée car elle contient des produits.";
    } else {
        $delete_sql = "DELETE FROM categorie WHERE Id_categorie = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Catégorie supprimée avec succès !";
        } else {
            $error_message = "Erreur lors de la suppression de la catégorie.";
        }
        $delete_stmt->close();
    }
}

// --- AJOUT / MODIFICATION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom_categorie = trim($_POST['nom_categorie']);
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

    // Validation
    if (empty($nom_categorie)) {
        $error_message = "Le nom de la catégorie est obligatoire.";
    } else {
        // Vérifier si la catégorie existe déjà
        $check_sql = "SELECT Id_categorie FROM categorie WHERE nom_categorie = ?";
        if ($edit_id > 0) {
            $check_sql .= " AND Id_categorie != ?";
        }
        $check_stmt = $conn->prepare($check_sql);
        if ($edit_id > 0) {
            $check_stmt->bind_param("si", $nom_categorie, $edit_id);
        } else {
            $check_stmt->bind_param("s", $nom_categorie);
        }
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Une catégorie avec ce nom existe déjà.";
        } else {
            if ($edit_id > 0) {
                // MODIFICATION
                $update_sql = "UPDATE categorie SET nom_categorie = ? WHERE Id_categorie = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $nom_categorie, $edit_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Catégorie modifiée avec succès !";
                } else {
                    $error_message = "Erreur lors de la modification de la catégorie.";
                }
                $update_stmt->close();
            } else {
                // AJOUT
                $insert_sql = "INSERT INTO categorie (nom_categorie) VALUES (?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("s", $nom_categorie);
                
                if ($insert_stmt->execute()) {
                    $success_message = "Catégorie ajoutée avec succès !";
                } else {
                    $error_message = "Erreur lors de l'ajout de la catégorie.";
                }
                $insert_stmt->close();
            }
        }
        $check_stmt->close();
    }
}

// Récupération des catégories
$sql = "SELECT c.*, COUNT(p.Id_produit) as nb_produits 
        FROM categorie c
        LEFT JOIN produit p ON c.Id_categorie = p.Id_categorie
        GROUP BY c.Id_categorie
        ORDER BY c.Id_categorie DESC";
$result = $conn->query($sql);

// Récupération des données de la catégorie à modifier
$edit_data = null;
if ($action === 'edit' && $id > 0) {
    $edit_sql = "SELECT * FROM categorie WHERE Id_categorie = ?";
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
    <title>Gestion des Catégories - Gestion de Stock</title>
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
                <h1>Gestion des Catégories</h1>
                <p>Organisez vos produits par catégories</p>
            </div>
            <div class="header-actions">
                <button onclick="openAddModal()" class="btn btn-primary">Ajouter une catégorie</button>
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

        <!-- Liste des catégories -->
        <div class="card">
            <div class="card-header">
                <h3>Liste des catégories</h3>
                <span class="badge badge-primary">Total: <?php echo $result->num_rows; ?></span>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Catégorie</th>
                                <th>Produits</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $colors = ['#667eea', '#11998e', '#f093fb', '#4facfe', '#f5576c', '#ffc107', '#17a2b8', '#6c757d'];
                            $color_index = 0;
                            while ($row = $result->fetch_assoc()): 
                                $color = $colors[$color_index % count($colors)];
                                $color_index++;
                            ?>
                            <tr>
                                <td><span class="badge badge-primary">#<?php echo $row['Id_categorie']; ?></span></td>
                                <td>
                                    <span class="category-color" style="background: <?php echo $color; ?>;"></span>
                                    <strong><?php echo htmlspecialchars($row['nom_categorie']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo $row['nb_produits']; ?> produit(s)</span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="?action=edit&id=<?php echo $row['Id_categorie']; ?>"
                                            class="btn btn-warning btn-sm">Modifier</a>
                                        <a href="#"
                                            onclick="confirmDelete(<?php echo $row['Id_categorie']; ?>, '<?php echo htmlspecialchars($row['nom_categorie']); ?>', <?php echo $row['nb_produits']; ?>)"
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
                    Aucune catégorie enregistrée.<br>
                    <small>Cliquez sur "Ajouter une catégorie" pour commencer.</small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Add Form -->
        <div class="card" style="background: #f8f9fa;">
            <div class="card-header">
                <h3>Ajout rapide</h3>
                <span class="badge badge-info">Sans rechargement</span>
            </div>
            <div class="card-body">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>"
                    class="quick-add-form">
                    <div class="form-group">
                        <input type="text" name="nom_categorie" class="form-control"
                            placeholder="Nom de la catégorie..." required>
                    </div>
                    <button type="submit" class="btn btn-success">Ajouter</button>
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
                <h3 id="modalTitle"><?php echo $edit_data ? 'Modifier la catégorie' : 'Ajouter une catégorie'; ?>
                </h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="modal-body">
                    <?php if ($edit_data): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_data['Id_categorie']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nom_categorie">Nom de la catégorie <span class="required">*</span></label>
                        <input type="text" id="nom_categorie" name="nom_categorie" class="form-control"
                            placeholder="Ex: Aliment, Électronique..." required
                            value="<?php echo $edit_data ? htmlspecialchars($edit_data['nom_categorie']) : ''; ?>">
                    </div>

                    <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                        <p style="font-size: 13px; color: #666; margin: 0;">
                            <strong>Conseil :</strong> Utilisez des noms de catégories courts et descriptifs
                            pour faciliter l'organisation de vos produits.
                        </p>
                    </div>
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
                <p>Êtes-vous sûr de vouloir supprimer la catégorie <strong id="deleteName"></strong> ?</p>
                <div id="deleteWarning"
                    style="display: none; padding: 15px; background: #fff3cd; border-radius: 10px; margin-top: 15px;">
                    <p style="color: #856404; margin: 0;">
                        Cette catégorie contient <strong id="productCount"></strong> produit(s).
                        Vous devez d'abord les déplacer ou les supprimer.
                    </p>
                </div>
                <p style="color: #dc3545; font-size: 14px; margin-top: 10px;">
                    Cette action est irréversible.
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
        document.getElementById('modalTitle').textContent = 'Ajouter une catégorie';
        document.getElementById('formModal').classList.add('active');
    }

    // Fermer le modal
    function closeModal() {
        document.getElementById('formModal').classList.remove('active');
    }

    // Confirmer la suppression
    function confirmDelete(id, name, productCount) {
        document.getElementById('deleteName').textContent = name;
        document.getElementById('deleteLink').href = '?action=delete&id=' + id;

        if (productCount > 0) {
            document.getElementById('deleteWarning').style.display = 'block';
            document.getElementById('productCount').textContent = productCount;
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
        const input = this.querySelector('input[name="nom_categorie"]');
        if (input.value.trim() === '') {
            e.preventDefault();
            alert('Veuillez saisir un nom de catégorie.');
        }
    });
    </script>

</body>

</html>