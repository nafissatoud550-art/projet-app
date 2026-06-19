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

// --- Statistiques globales ---
$total_produits     = (int) ($conn->query("SELECT COUNT(*) t FROM produit")->fetch_assoc()['t'] ?? 0);
$total_categories   = (int) ($conn->query("SELECT COUNT(*) t FROM categorie")->fetch_assoc()['t'] ?? 0);
$total_fournisseurs = (int) ($conn->query("SELECT COUNT(*) t FROM fournisseur")->fetch_assoc()['t'] ?? 0);
$total_approv       = (int) ($conn->query("SELECT COUNT(*) t FROM approvisionnement")->fetch_assoc()['t'] ?? 0);

// Valeur totale du stock
$valeur_stock = (float) ($conn->query("SELECT COALESCE(SUM(quantite * prix_unitaire),0) v FROM produit")->fetch_assoc()['v'] ?? 0);

// Répartition de l'état du stock
$dist = $conn->query("SELECT
        SUM(CASE WHEN quantite > stock_minimum THEN 1 ELSE 0 END) AS ok,
        SUM(CASE WHEN quantite <= stock_minimum AND quantite > 0 THEN 1 ELSE 0 END) AS low,
        SUM(CASE WHEN quantite <= 0 THEN 1 ELSE 0 END) AS rupture
        FROM produit")->fetch_assoc();
$nb_ok      = (int) ($dist['ok'] ?? 0);
$nb_low     = (int) ($dist['low'] ?? 0);
$nb_rupture = (int) ($dist['rupture'] ?? 0);
$nb_alertes = $nb_low + $nb_rupture;

// Produits par catégorie (top 6)
$cat_data = [];
$res = $conn->query("SELECT c.nom_categorie AS nom, COUNT(p.Id_produit) AS cnt
        FROM categorie c LEFT JOIN produit p ON p.Id_categorie = c.Id_categorie
        GROUP BY c.Id_categorie, c.nom_categorie
        ORDER BY cnt DESC LIMIT 6");
if ($res) {
    while ($r = $res->fetch_assoc()) $cat_data[] = $r;
}

// Approvisionnements sur les 6 derniers mois
$mois_data = [];
$res = $conn->query("SELECT DATE_FORMAT(Date_approvisionnement,'%m/%y') AS mois, COUNT(*) AS cnt
        FROM approvisionnement
        GROUP BY YEAR(Date_approvisionnement), MONTH(Date_approvisionnement)
        ORDER BY YEAR(Date_approvisionnement) DESC, MONTH(Date_approvisionnement) DESC
        LIMIT 6");
if ($res) {
    while ($r = $res->fetch_assoc()) $mois_data[] = $r;
}
$mois_data = array_reverse($mois_data);

// Produits avec stock bas
$stock_bas = [];
$res = $conn->query("SELECT nom_produit, quantite, stock_minimum
        FROM produit WHERE quantite <= stock_minimum ORDER BY quantite ASC LIMIT 6");
if ($res) {
    while ($r = $res->fetch_assoc()) $stock_bas[] = $r;
}

// Derniers approvisionnements
$dern_appro = [];
$res = $conn->query("SELECT a.Id_approvisionnement, a.nom_approvisionnement, a.Date_approvisionnement, u.noms_utilisateur
        FROM approvisionnement a
        JOIN utilisateur u ON a.Id_utilisateur = u.Id_utilisateur
        ORDER BY a.Date_approvisionnement DESC LIMIT 5");
if ($res) {
    while ($r = $res->fetch_assoc()) $dern_appro[] = $r;
}

$conn->close();

// --- Helpers d'affichage ---
function pct($part, $total)
{
    return $total > 0 ? (int) round($part / $total * 100) : 0;
}

function render_ring($percent, $grad)
{
    $percent = max(0, min(100, (int) $percent));
    echo '<div class="ring-center">';
    echo '<svg class="ring" viewBox="0 0 36 36">';
    echo '<circle class="ring-bg" cx="18" cy="18" r="15.9155"/>';
    echo '<circle class="ring-val" stroke="url(#' . $grad . ')" cx="18" cy="18" r="15.9155" stroke-dasharray="' . $percent . ' 100"/>';
    echo '</svg>';
    echo '<span class="ring-text">' . $percent . '%</span>';
    echo '</div>';
}

function render_gauge($percent, $grad, $label)
{
    $percent = max(0, min(100, (int) $percent));
    $val = round($percent / 100 * 295.3, 1);
    echo '<div class="gauge-wrap">';
    echo '<svg class="gauge" viewBox="0 0 220 124">';
    echo '<path class="gauge-bg" d="M16 112 A94 94 0 0 1 204 112"/>';
    echo '<path class="gauge-val" stroke="url(#' . $grad . ')" d="M16 112 A94 94 0 0 1 204 112" stroke-dasharray="' . $val . ' 999"/>';
    echo '</svg>';
    echo '<div class="gauge-value">' . $percent . '%</div>';
    echo '<div class="gauge-label">' . htmlspecialchars($label) . '</div>';
    echo '</div>';
}

function icon($n)
{
    $p = [
        'box'    => '<path d="M3 7.5l9-4.5 9 4.5v9l-9 4.5-9-4.5v-9z"/><path d="M3 7.5l9 4.5 9-4.5M12 21v-9"/>',
        'tag'    => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9-9-9z"/><circle cx="7.5" cy="7.5" r="1.4"/>',
        'truck'  => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="7.5" cy="18" r="1.7"/><circle cx="17.5" cy="18" r="1.7"/>',
        'wallet' => '<path d="M3 7h14a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1V7z"/><path d="M16 12.5h2.5"/>',
    ];
    echo '<svg viewBox="0 0 24 24">' . ($p[$n] ?? '') . '</svg>';
}

$max_cat  = 1;
foreach ($cat_data as $c) $max_cat = max($max_cat, (int) $c['cnt']);
$max_mois = 1;
foreach ($mois_data as $m) $max_mois = max($max_mois, (int) $m['cnt']);
$bar_colors = ['blue', 'purple', 'orange', 'green', 'pink'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tableau de Bord - Gestion de Stock</title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>

<body>
  <!-- Dégradés partagés pour les graphiques SVG -->
  <svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <defs>
      <linearGradient id="gGreen" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#5eead4" />
        <stop offset="100%" stop-color="#18bfa4" />
      </linearGradient>
      <linearGradient id="gOrange" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#ffc46b" />
        <stop offset="100%" stop-color="#ff9a5a" />
      </linearGradient>
      <linearGradient id="gPink" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#fb7ba2" />
        <stop offset="100%" stop-color="#f5577c" />
      </linearGradient>
      <linearGradient id="gBlue" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#7cc3ff" />
        <stop offset="100%" stop-color="#4f9cf9" />
      </linearGradient>
      <linearGradient id="gPurple" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#c4b5fd" />
        <stop offset="100%" stop-color="#8b5cf6" />
      </linearGradient>
    </defs>
  </svg>

  <!-- Navbar -->
  <nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">Gestion de Stock</a>
    <div class="navbar-right">
      <span class="user-info">
        Bienvenue,
        <strong><?php echo htmlspecialchars($user_data['noms_utilisateur'] ?? 'Utilisateur'); ?></strong>
      </span>
      <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
  </nav>

  <!-- Conteneur principal -->
  <div class="container">
    <!-- En-tête -->
    <div class="dashboard-header">
      <div>
        <h1>Tableau de <strong>Bord</strong></h1>
        <p>Vue d'ensemble de votre système de gestion de stock</p>
      </div>
      <div class="badge-circle" title="Alertes de stock"><?php echo $nb_alertes; ?></div>
    </div>

    <!-- Cartes KPI -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-top">
          <span class="kpi-label">Produits en stock</span>
          <span class="icon-chip blue"><?php icon('box'); ?></span>
        </div>
        <div class="kpi-value"><?php echo number_format($total_produits, 0, ',', ' '); ?></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-top">
          <span class="kpi-label">Catégories</span>
          <span class="icon-chip purple"><?php icon('tag'); ?></span>
        </div>
        <div class="kpi-value"><?php echo number_format($total_categories, 0, ',', ' '); ?></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-top">
          <span class="kpi-label">Fournisseurs</span>
          <span class="icon-chip orange"><?php icon('truck'); ?></span>
        </div>
        <div class="kpi-value"><?php echo number_format($total_fournisseurs, 0, ',', ' '); ?></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-top">
          <span class="kpi-label">Valeur du stock</span>
          <span class="icon-chip green"><?php icon('wallet'); ?></span>
        </div>
        <div class="kpi-value" style="font-size:24px;">
          <?php echo number_format($valeur_stock, 0, ',', ' '); ?>
          <span style="font-size:14px;color:var(--text-secondary);font-weight:600;">FCFA</span>
        </div>
      </div>
    </div>

    <!-- État du stock : anneaux + jauge -->
    <div class="row-2">
      <div class="card">
        <div class="card-header">
          <h3>Répartition du stock</h3>
          <span class="badge badge-primary">Total : <?php echo $total_produits; ?></span>
        </div>
        <div class="card-body">
          <div class="ring-set">
            <div class="ring-item">
              <?php render_ring(pct($nb_ok, $total_produits), 'gGreen'); ?>
              <div class="ring-caption">Stock sain<small><?php echo $nb_ok; ?> produits</small></div>
            </div>
            <div class="ring-item">
              <?php render_ring(pct($nb_low, $total_produits), 'gOrange'); ?>
              <div class="ring-caption">En alerte<small><?php echo $nb_low; ?> produits</small></div>
            </div>
            <div class="ring-item">
              <?php render_ring(pct($nb_rupture, $total_produits), 'gPink'); ?>
              <div class="ring-caption">Rupture<small><?php echo $nb_rupture; ?> produits</small></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3>Santé globale du stock</h3>
        </div>
        <div class="card-body">
          <?php render_gauge(pct($nb_ok, $total_produits), 'gGreen', 'Produits au-dessus du seuil minimum'); ?>
          <div class="legend">
            <span class="legend-item"><span class="legend-dot" style="background:var(--viz-green)"></span>Sain</span>
            <span class="legend-item"><span class="legend-dot" style="background:var(--viz-orange)"></span>Alerte</span>
            <span class="legend-item"><span class="legend-dot" style="background:var(--viz-pink)"></span>Rupture</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Graphiques en barres -->
    <div class="row-2">
      <div class="card">
        <div class="card-header">
          <h3>Produits par catégorie</h3>
          <span class="badge">Top <?php echo count($cat_data); ?></span>
        </div>
        <div class="card-body">
          <?php if (!empty($cat_data)): ?>
          <div class="bar-chart">
            <?php foreach ($cat_data as $i => $c):
                            $h = (int) round($c['cnt'] / $max_cat * 100);
                            $col = $bar_colors[$i % count($bar_colors)];
                        ?>
            <div class="bar-col">
              <span class="bar-val"><?php echo (int) $c['cnt']; ?></span>
              <div class="bar <?php echo $col; ?>" style="height: <?php echo max(4, $h); ?>%;"></div>
              <span class="bar-label"><?php echo htmlspecialchars($c['nom'] ?? '—'); ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="empty-message">Aucune catégorie à afficher.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3>Approvisionnements par mois</h3>
          <span class="badge">6 derniers</span>
        </div>
        <div class="card-body">
          <?php if (!empty($mois_data)): ?>
          <div class="bar-chart">
            <?php foreach ($mois_data as $m):
                            $h = (int) round($m['cnt'] / $max_mois * 100);
                        ?>
            <div class="bar-col">
              <span class="bar-val"><?php echo (int) $m['cnt']; ?></span>
              <div class="bar blue" style="height: <?php echo max(4, $h); ?>%;"></div>
              <span class="bar-label"><?php echo htmlspecialchars($m['mois']); ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="empty-message">Aucun approvisionnement à afficher.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Alertes + actions rapides -->
    <div class="content-grid">
      <div class="card">
        <div class="card-header">
          <h3>Alertes de stock</h3>
          <span class="badge badge-warning"><?php echo $nb_alertes; ?> à surveiller</span>
        </div>
        <div class="card-body">
          <?php if (!empty($stock_bas)): ?>
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
                <?php foreach ($stock_bas as $row):
                                    $rupture = ((int) $row['quantite'] <= 0);
                                ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['nom_produit']); ?></td>
                  <td><strong><?php echo (int) $row['quantite']; ?></strong></td>
                  <td><?php echo (int) $row['stock_minimum']; ?></td>
                  <td>
                    <span class="status-badge danger">
                      <span class="stock-dot red"></span>
                      <?php echo $rupture ? 'Rupture' : 'Stock bas'; ?>
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="empty-message">Tous les produits ont un stock suffisant.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3>Actions rapides</h3>
        </div>
        <div class="card-body">
          <div class="quick-actions">
            <a href="ajouter_produit.php" class="quick-action-btn">Ajouter un produit</a>
            <a href="approvisionnement.php" class="quick-action-btn">Approvisionnement</a>
            <a href="categorie.php" class="quick-action-btn">Catégories</a>
            <a href="fournisseur.php" class="quick-action-btn">Fournisseurs</a>
            <a href="sortie.php" class="quick-action-btn" style="grid-column:1 / -1;">Gérer les sorties</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Derniers approvisionnements -->
    <div class="card">
      <div class="card-header">
        <h3>Derniers approvisionnements</h3>
        <span class="badge">Historique</span>
      </div>
      <div class="card-body">
        <?php if (!empty($dern_appro)): ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Nom</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dern_appro as $row): ?>
              <tr>
                <td><span class="badge badge-primary">#<?php echo (int) $row['Id_approvisionnement']; ?></span></td>
                <td><?php echo htmlspecialchars($row['nom_approvisionnement']); ?></td>
                <td><?php echo date('d/m/Y', strtotime($row['Date_approvisionnement'])); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="empty-message">Aucun approvisionnement enregistré.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Footer -->
    <div class="footer">
      <p>&copy; 2026 - Système de Gestion de Stock. Tous droits réservés.</p>
    </div>
  </div>
</body>

</html>