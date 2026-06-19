<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Inclure la connexion à la base de données
require_once 'connect.php';

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$sql_user = "SELECT * FROM utilisateur WHERE Id_utilisateur = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$user_data = $user_result->fetch_assoc();
$stmt_user->close();

// Vérifier si l'utilisateur existe
if (!$user_data) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Statistiques pour le tableau de bord
// Nombre total de produits
$sql_produits = "SELECT COUNT(*) as total FROM produit";
$result_produits = $conn->query($sql_produits);
$total_produits = $result_produits ? $result_produits->fetch_assoc()['total'] : 0;

// Nombre total de catégories
$sql_categories = "SELECT COUNT(*) as total FROM categorie";
$result_categories = $conn->query($sql_categories);
$total_categories = $result_categories ? $result_categories->fetch_assoc()['total'] : 0;

// Nombre total de fournisseurs
$sql_fournisseurs = "SELECT COUNT(*) as total FROM fournisseur";
$result_fournisseurs = $conn->query($sql_fournisseurs);
$total_fournisseurs = $result_fournisseurs ? $result_fournisseurs->fetch_assoc()['total'] : 0;

// Nombre total d'approvisionnements
$sql_approv = "SELECT COUNT(*) as total FROM approvisionnement";
$result_approv = $conn->query($sql_approv);
$total_approv = $result_approv ? $result_approv->fetch_assoc()['total'] : 0;

// Produits avec stock bas
$sql_stock_bas = "SELECT * FROM produit WHERE quantite <= stock_minimum ORDER BY quantite ASC LIMIT 5";
$result_stock_bas = $conn->query($sql_stock_bas);

// Derniers approvisionnements
$sql_approvisionnements = "SELECT a.*, u.noms_utilisateur 
                           FROM approvisionnement a 
                           JOIN utilisateur u ON a.Id_utilisateur = u.Id_utilisateur 
                           ORDER BY a.Date_approvisionnement DESC LIMIT 5";
$result_approvisionnements = $conn->query($sql_approvisionnements);

// Fermer la connexion
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Gestion de Stock</title>
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
        background: linear-gradient(135deg, #667eea 0%);
        padding: 15px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        flex-wrap: wrap;
        gap: 10px;
    }

    .navbar-brand {
        color: white;
        font-size: 24px;
        font-weight: 600;
        text-decoration: none;
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
    }

    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 30px 20px;
    }

    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .dashboard-header h1 {
        font-size: 28px;
        color: #333;
    }

    .dashboard-header p {
        color: #666;
        font-size: 14px;
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
        padding: 25px 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        gap: 15px;
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        flex-shrink: 0;
    }

    .stat-icon.blue {
        background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .stat-icon.green {
        background: linear-gradient(135deg, #11998e, #38ef7d);
    }

    .stat-icon.orange {
        background: linear-gradient(135deg, #f093fb, #f5576c);
    }

    .stat-icon.purple {
        background: linear-gradient(135deg, #4facfe, #00f2fe);
    }

    .stat-info h3 {
        font-size: 26px;
        color: #333;
        margin-bottom: 3px;
    }

    .stat-info p {
        color: #888;
        font-size: 13px;
        margin: 0;
    }

    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
    }

    @media (max-width: 992px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }

    .card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .card-header {
        padding: 18px 25px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .card-header h3 {
        font-size: 17px;
        color: #333;
    }

    .card-header .badge {
        background: #667eea;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
    }

    .card-body {
        padding: 20px 25px;
    }

    .card-body .empty-message {
        text-align: center;
        color: #999;
        padding: 30px 0;
        font-size: 14px;
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
        padding: 10px 15px;
        text-align: left;
        color: #555;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        border-bottom: 2px solid #e9ecef;
    }

    table td {
        padding: 10px 15px;
        border-bottom: 1px solid #f0f0f0;
        color: #333;
    }

    table tbody tr:hover {
        background: #f8f9fa;
    }

    .status-badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        display: inline-block;
    }

    .status-badge.danger {
        background: #f8d7da;
        color: #721c24;
    }

    .quick-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .quick-action-btn {
        padding: 15px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        text-align: center;
        text-decoration: none;
        color: #555;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
        background: white;
    }

    .quick-action-btn:hover {
        border-color: #667eea;
        background: #f8f9ff;
        transform: translateY(-2px);
    }

    .quick-action-btn .icon {
        font-size: 22px;
    }

    .quick-action-btn .label {
        font-size: 13px;
        font-weight: 500;
    }

    .footer {
        text-align: center;
        padding: 20px;
        margin-top: 30px;
        color: #888;
        font-size: 13px;
        border-top: 1px solid #e9ecef;
    }

    @media (max-width: 768px) {
        .navbar {
            flex-direction: column;
            text-align: center;
        }

        .navbar-right {
            justify-content: center;
        }

        .dashboard-header {
            flex-direction: column;
            text-align: center;
        }

        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }

        .quick-actions {
            grid-template-columns: 1fr;
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
    <!-- Navbar -->
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">📦 Gestion de Stock</a>
        <div class="navbar-right">
            <span class="user-info">
                Bienvenue,
                <strong><?php echo htmlspecialchars($user_data['noms_utilisateur'] ?? 'Utilisateur'); ?></strong>
                <span style="opacity:0.7;font-size:12px;margin-left:8px;">
                    (<?php echo htmlspecialchars($user_data['role'] ?? 'employe'); ?>)
                </span>
            </span>
            <a href="logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </nav>

    <!-- Conteneur principal -->
    <div class="container">
        <!-- En-tête -->
        <div class="dashboard-header">
            <div>
                <h1>📊 Tableau de Bord</h1>
                <p>Vue d'ensemble de votre système de gestion de stock</p>
            </div>
        </div>

        <!-- Cartes statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">📦</div>
                <div class="stat-info">
                    <h3><?php echo $total_produits; ?></h3>
                    <p>Produits en stock</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">📊</div>
                <div class="stat-info">
                    <h3><?php echo $total_categories; ?></h3>
                    <p>Catégories</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">🏢</div>
                <div class="stat-info">
                    <h3><?php echo $total_fournisseurs; ?></h3>
                    <p>Fournisseurs</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">📋</div>
                <div class="stat-info">
                    <h3><?php echo $total_approv; ?></h3>
                    <p>Approvisionnements</p>
                </div>
            </div>
        </div>

        <!-- Contenu -->
        <div class="content-grid">
            <!-- Produits avec stock bas -->
            <div class="card">
                <div class="card-header">
                    <h3>⚠️ Alertes Stock</h3>
                    <span class="badge">Stock bas</span>
                </div>
                <div class="card-body">
                    <?php if ($result_stock_bas && $result_stock_bas->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Stock</th>
                                    <th>Min</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result_stock_bas->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['nom_produit']); ?></td>
                                    <td><strong><?php echo $row['quantite']; ?></strong></td>
                                    <td><?php echo $row['stock_minimum']; ?></td>
                                    <td>
                                        <span class="status-badge danger">⚠️ Stock critique</span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-message">✅ Tous les produits ont un stock suffisant.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="card">
                <div class="card-header">
                    <h3>⚡ Actions Rapides</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="ajouter_produit.php" class="quick-action-btn">
                            <span class="icon">➕</span>
                            <span class="label">Ajouter un produit</span>
                        </a>
                        <a href="approvisionnement.php" class="quick-action-btn">
                            <span class="icon">📦</span>
                            <span class="label">Nouvel approvisionnement</span>
                        </a>
                        <a href="categorie.php" class="quick-action-btn">
                            <span class="icon">📊</span>
                            <span class="label">Gérer les catégories</span>
                        </a>
                        <a href="fournisseur.php" class="quick-action-btn">
                            <span class="icon">🏢</span>
                            <span class="label">Gérer les fournisseurs</span>
                        </a>
                        <a href="sortie.php" class="quick-action-btn">
                            <span class="icon">📤</span>
                            <span class="label">Gérer les sortie</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Derniers approvisionnements -->
            <div class="card" style="grid-column: 1 / -1;">
                <div class="card-header">
                    <h3>📋 Derniers Approvisionnements</h3>
                    <span class="badge">Historique</span>
                </div>
                <div class="card-body">
                    <?php if ($result_approvisionnements && $result_approvisionnements->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Date</th>
                                    <th>Utilisateur</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result_approvisionnements->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $row['Id_approvisionnement']; ?></td>
                                    <td><?php echo htmlspecialchars($row['nom_approvisionnement']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['Date_approvisionnement'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['noms_utilisateur']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-message">Aucun approvisionnement enregistré.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; 2026 - Système de Gestion de Stock. Tous droits réservés.</p>
        </div>
    </div>
</body>

</html>