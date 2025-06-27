<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;



try {
    $pdo = new PDO('sqlite:assets/SQL/mes-services-db.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}


$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $mdp   = $_POST['mdp'] ?? '';

    $stmt = $pdo->prepare("
        SELECT U.ID, U.MDP, G.LVL
        FROM USER U
        JOIN `GROUP` G ON U.GROUP_ID = G.ID
        WHERE U.EMAIL = :email
        LIMIT 1
    ");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($mdp, $user['MDP'])) {
        $_SESSION['user_id'] = $user['ID'];
        $_SESSION['user_lvl'] = $user['LVL'];

        // Redirection selon le niveau d'autorisation
        if ($user['LVL'] >= 10) {
            header('Location: admin-panel.php');
        } elseif ($user['LVL'] >= 1) {
            header('Location: home.php');
        } else {
            $erreur = 'Votre compte n\'a pas les droits nécessaires.';
        }
        exit;
    } else {
        $erreur = 'Identifiants incorrects.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <link rel="stylesheet" href="style.css"> <!-- Fichier CSS global -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/ico/0.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">



</head>
<body>
    <div class="login-container">
        <h2>Connexion</h2>
        <?php if ($erreur): ?>
            <div class="error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <label for="email">Email :</label>
            <input type="email" name="email" required>

            <label for="mdp">Mot de passe :</label>
            <input type="password" name="mdp" required>

            <button type="submit">Se connecter</button>

        </form>
        
        <!-- Bouton Register -->
        <form action="register.php" method="get">
            <button type="submit">← Register</button>
        </form>

        <!-- Bouton Accueil -->
        <form action="home.php" method="get">
            <button type="submit">← Retour à l’accueil</button>
        </form>
    </div>


    <footer>
        © <?= date('Y') ?> [FOOTER_CREDIT] Services. Tous droits réservés.
    </footer>

</body>
</html>
