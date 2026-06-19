<?php
// Configuration de la connexion à la base de données (Version MySQLi)
$servername = "127.0.0.1";
$username = "root";
$password = "";
$database = "gestion_de_stock";

// Créer la connexion MySQLi
$conn = new mysqli($servername, $username, $password, $database);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Échec de la connexion à la base de données : " . $conn->connect_error);
}

// Définir le charset UTF-8
$conn->set_charset("utf8mb4");

// La connexion est prête à être utilisée

// Fonction utilitaire pour le debug (optionnelle)
function debug($data) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
}
?>