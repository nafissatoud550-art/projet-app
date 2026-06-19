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
                <h1>Gestion des Fournisseurs</h1>
                <p>Gérez les fournisseurs de votre stock</p>
            </div>
            <div class="header-actions">
                <button onclick="openAddModal()" class="btn btn-primary">Ajouter un fournisseur</button>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $stats['total_fournisseurs']; ?></div>
                <div class="label">Total fournisseurs</div>
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

        <!-- Liste des fournisseurs -->
        <div class="card">
            <div class="card-header">
                <h3>Liste des fournisseurs</h3>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Rechercher..." onkeyup="filterTable()">
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
                                            class="btn btn-warning btn-sm">Modifier</a>
                                        <a href="#"
                                            onclick="confirmDelete(<?php echo $row['Id_fournisseur']; ?>, '<?php echo htmlspecialchars($row['prenom_fournisseur'] . ' ' . $row['nom_fournisseur']); ?>', <?php echo $row['nb_approvisionnements']; ?>)"
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
                    Aucun fournisseur enregistré.<br>
                    <small>Cliquez sur "Ajouter un fournisseur" pour commencer.</small>
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
                <h3 id="modalTitle">
                    <?php echo $edit_data ? 'Modifier le fournisseur' : 'Ajouter un fournisseur'; ?></h3>
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
                <p>Êtes-vous sûr de vouloir supprimer le fournisseur <strong id="deleteName"></strong> ?</p>
                <div id="deleteWarning"
                    style="display: none; padding: 15px; background: #fff3cd; border-radius: 10px; margin-top: 15px;">
                    <p style="color: #856404; margin: 0;">
                        Ce fournisseur est lié à <strong id="approvCount"></strong> approvisionnement(s).
                        Vous devez d'abord les supprimer ou les réaffecter.
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
        document.getElementById('modalTitle').textContent = 'Ajouter un fournisseur';
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