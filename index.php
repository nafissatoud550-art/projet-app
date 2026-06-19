<?php
// Démarrer la session
session_start();

// Si l'utilisateur est déjà connecté, le rediriger vers le dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Inclure la connexion à la base de données
require_once 'connect.php';

$error_message = '';

// Traitement du formulaire de connexion
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifiant = trim($_POST['identifiant']);
    $mot_de_pass = trim($_POST['mot_de_pass']);
    $remember_me = isset($_POST['remember_me']) ? true : false;

    if (!empty($identifiant) && !empty($mot_de_pass)) {
        // Requête préparée pour éviter les injections SQL (version MySQLi)
        $sql = "SELECT * FROM utilisateur WHERE identifiant = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $identifiant);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            // Vérifier le mot de passe (si haché, utilisez password_verify)
            if (password_verify($mot_de_pass, $user['mot_de_pass'])) {
                // Connexion réussie
                $_SESSION['user_id'] = $user['Id_utilisateur'];
                $_SESSION['user_name'] = $user['prenom_utilisateur'] . ' ' . $user['nom_utilisateur'];

                // Si "Se souvenir de moi" est coché
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), "/"); // 30 jours
                    // Vous pouvez stocker ce token dans la base de données
                }

                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Identifiant ou mot de passe incorrect";
            }
        } else {
            $error_message = "Identifiant ou mot de passe incorrect";
        }
        $stmt->close();
    } else {
        $error_message = "Veuillez remplir tous les champs";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion de Stock</title>
    <style>
    :root {
        --bg: #e9edf5;
        --well: #eef1f8;
        --card: #ffffff;
        --text: #2a3447;
        --text-strong: #1b2538;
        --text-secondary: #7a8699;
        --muted: #9aa4b6;
        --accent: #6366f1;
        --accent-2: #818cf8;
        --danger: #e11d48;
        --radius: 20px;
        --radius-sm: 12px;
        --shadow-d: rgba(99, 116, 160, 0.22);
        --shadow-l: rgba(255, 255, 255, 0.9);
        --soft: 8px 8px 22px var(--shadow-d), -8px -8px 22px var(--shadow-l);
        --soft-sm: 3px 3px 8px var(--shadow-d), -3px -3px 8px var(--shadow-l);
        --soft-inset: inset 3px 3px 7px rgba(99, 116, 160, 0.20), inset -3px -3px 6px rgba(255, 255, 255, 0.85);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
        -webkit-font-smoothing: antialiased;
    }

    .container {
        width: 100%;
        max-width: 410px;
    }

    .brand {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        margin-bottom: 26px;
        font-size: 19px;
        font-weight: 700;
        color: var(--text-strong);
    }

    .brand::before {
        content: "";
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        box-shadow: var(--soft-sm);
    }

    .card {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--soft);
        overflow: hidden;
    }

    .card-header {
        padding: 32px 34px 10px;
        text-align: center;
    }

    .card-header h1 {
        color: var(--text-strong);
        font-size: 23px;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin-bottom: 6px;
    }

    .card-header p {
        color: var(--text-secondary);
        font-size: 14px;
    }

    .card-body {
        padding: 26px 34px 34px;
    }

    .form-group {
        margin-bottom: 18px;
    }

    .form-group label {
        display: block;
        margin-bottom: 7px;
        color: var(--text);
        font-weight: 600;
        font-size: 13px;
    }

    .input-group input {
        width: 100%;
        padding: 13px 16px;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 14px;
        color: var(--text);
        background: var(--well);
        box-shadow: var(--soft-inset);
        transition: box-shadow .18s ease;
        outline: none;
    }

    .input-group input::placeholder {
        color: var(--muted);
    }

    .input-group input:focus {
        box-shadow: var(--soft-inset), 0 0 0 2px var(--accent);
    }

    .checkbox-group {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .checkbox-label input {
        margin-right: 8px;
        cursor: pointer;
        accent-color: var(--accent);
    }

    .checkbox-label span {
        font-size: 13.5px;
        color: var(--text-secondary);
    }

    .recover-link {
        color: var(--accent);
        text-decoration: none;
        font-size: 13.5px;
        font-weight: 600;
        transition: color .15s ease;
    }

    .recover-link:hover {
        text-decoration: underline;
    }

    .btn-signin {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: #fff;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: var(--soft-sm);
        transition: transform .18s ease;
    }

    .btn-signin:hover {
        transform: translateY(-1px);
    }

    .btn-signin:active {
        transform: translateY(0);
        box-shadow: var(--soft-inset);
    }

    .alert {
        padding: 13px 16px;
        border-radius: var(--radius-sm);
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: 500;
        box-shadow: var(--soft-sm);
    }

    .alert-danger {
        background: #fdeaf0;
        color: #b4123f;
    }

    .footer {
        text-align: center;
        margin-top: 24px;
        font-size: 12.5px;
        color: var(--muted);
    }

    @media (max-width: 480px) {
        .card-header {
            padding: 28px 24px 10px;
        }

        .card-body {
            padding: 24px 24px 30px;
        }
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="brand">Gestion de Stock</div>
        <div class="card">
            <div class="card-header">
                <h1>Connexion</h1>
                <p>Accédez à votre espace de gestion</p>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="identifiant">Adresse email</label>
                        <div class="input-group">
                            <input type="email" id="identifiant" name="identifiant" placeholder="vous@exemple.com"
                                required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="mot_de_pass">Mot de passe</label>
                        <div class="input-group">
                            <input type="password" id="mot_de_pass" name="mot_de_pass"
                                placeholder="Votre mot de passe" required>
                        </div>
                    </div>

                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember_me">
                            <span>Se souvenir de moi</span>
                        </label>
                        <a href="recover_password.php" class="recover-link">Mot de passe oublié ?</a>
                    </div>

                    <button type="submit" class="btn-signin">Se connecter</button>
                </form>
            </div>
        </div>
        <div class="footer">
            <p>&copy; 2026 - Système de Gestion de Stock</p>
        </div>
    </div>
</body>

</html>
