<?php
$etat = '';
$lien = '';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;


if (!isset($_GET['token'])) {
    $etat = "❌ Lien invalide.";
} else {
    $pdo = new PDO('sqlite:assets/SQL/mes-services-db.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $token = $_GET['token'];

    $stmt = $pdo->prepare("SELECT ID FROM USER WHERE TOKEN = :token AND ACTIVER = 0 LIMIT 1");
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $stmt = $pdo->prepare("UPDATE USER SET ACTIVER = 1, TOKEN = NULL WHERE ID = :id");
        $stmt->execute(['id' => $user['ID']]);
        $etat = "✅ Compte activé.";
        $lien = "<a class='btn-retour' href='login.php'>Se connecter</a>";
    } else {
        $etat = "❌ Lien invalide ou déjà utilisé.";
        $lien = "<a class='btn-retour' href='register.php'>Crée votre compte</a>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Activation</title>
    <link rel="stylesheet" href="style.css"> 
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/ico/0.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">


    
</head>
<body>
    <div class="admin-container">
        <h1>Activation de compte</h1>
        <p><?= $etat ?></p>
        <?= $lien ?>
    </div>
    
    <footer>
        © <?= date('Y') ?> [FOOTER_CREDIT] Services. Tous droits réservés.
    </footer>
</body>
</html>

