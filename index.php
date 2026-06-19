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
        $sql = "SELECT * FROM utilisateur WHERE identifiant = ? AND statut = 'actif'";
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
                $_SESSION['user_role'] = $user['role'];
                
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
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%);
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .container {
        width: 100%;
        max-width: 450px;
    }

    .card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        animation: fadeIn 0.5s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .card-header {
        background: linear-gradient(135deg, #667eea 0%);
        padding: 40px 30px;
        text-align: center;
    }

    .card-header h1 {
        color: white;
        font-size: 28px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .card-header p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 14px;
    }

    .card-body {
        padding: 40px 30px;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #333;
        font-weight: 500;
        font-size: 14px;
    }

    .input-group {
        position: relative;
    }

    .input-group input {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
        outline: none;
    }

    .input-group input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .input-group input::placeholder {
        color: #999;
    }

    .checkbox-group {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .checkbox-label input {
        margin-right: 8px;
        cursor: pointer;
    }

    .checkbox-label span {
        font-size: 14px;
        color: #666;
    }

    .recover-link {
        color: #667eea;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.3s ease;
    }

    .recover-link:hover {
        color: #764ba2;
        text-decoration: underline;
    }

    .btn-signin {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #667eea 0%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .btn-signin:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }

    .btn-signin:active {
        transform: translateY(0);
    }

    .alert {
        padding: 12px 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .alert-danger {
        background-color: #fee;
        color: #c33;
        border-left: 4px solid #c33;
    }

    .alert-success {
        background-color: #efe;
        color: #3c3;
        border-left: 4px solid #3c3;
    }

    .footer {
        text-align: center;
        padding: 20px;
        background: #f8f9fa;
        font-size: 12px;
        color: #666;
    }

    @media (max-width: 480px) {
        .card-body {
            padding: 30px 20px;
        }

        .card-header {
            padding: 30px 20px;
        }

        .card-header h1 {
            font-size: 24px;
        }
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>Connexion</h1>
                <p>Système de Gestion de Stock</p>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="identifiant">Votre email</label>
                        <div class="input-group">
                            <input type="email" id="identifiant" name="identifiant" placeholder="Entrez votre email"
                                required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="mot_de_pass">Mot de passe</label>
                        <div class="input-group">
                            <input type="password" id="mot_de_pass" name="mot_de_pass"
                                placeholder="Entrez votre mot de passe" required>
                        </div>
                    </div>

                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember_me">
                            <span>Se souvenir de moi</span>
                        </label>
                        <a href="recover_password.php" class="recover-link">Mot de passe oublié ?</a>
                    </div>

                    <button type="submit" class="btn-signin">SE CONNECTER</button>
                </form>
            </div>
            <div class="footer">
                <p>&copy; 2026 - Système de Gestion de Stock. Tous droits réservés.</p>
            </div>
        </div>
    </div>

    <script>
    // Animation supplémentaire pour une meilleure expérience utilisateur
    document.querySelectorAll('.input-group input').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });

    // Message de bienvenue en console
    console.log('Page de connexion - Gestion de Stock');
    </script>
</body>

</html>