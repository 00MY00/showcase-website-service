<?php
session_start();


$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;



$user = null;
$services = [];
$unread_messages = 0;



try {
    $pdo = new PDO('sqlite:assets/SQL/mes-services-db.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Si l'utilisateur est connecté
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("
            SELECT NOM, PRENOM, EMAIL, G.NAME AS GROUPE, G.LVL
            FROM USER U
            JOIN `GROUP` G ON U.GROUP_ID = G.ID
            WHERE U.ID = :id
        ");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);


        // Récupère le nombre de messages non lus pour cet utilisateur
        $msg_stmt = $pdo->prepare("SELECT COUNT(*) FROM MESSAGE WHERE DESTINATION_USER_ID = :id AND LUE = 0");
        // Vérifie s'il y a des messages non lus pour l'utilisateur connecté
        $msg_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM MESSAGE 
            WHERE DESTINATION_USER_ID = :id AND LUE = 0
        ");
        $msg_stmt->execute(['id' => $_SESSION['user_id']]);
        $unread_messages = $msg_stmt->fetchColumn();



        if (!$user) {
            die("Utilisateur non trouvé.");
        }

        if ($user['LVL'] >= 1) {
            $stmt = $pdo->prepare("SELECT * FROM SERVICE WHERE ACTIF = 1 AND VISIBILITE_MIN <= :lvl ORDER BY ORDRE ASC");
            $stmt->execute(['lvl' => $user['LVL']]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } else {
        // Utilisateur non connecté → niveau = 0
        $user = null;
        $stmt = $pdo->prepare("SELECT * FROM SERVICE WHERE ACTIF = 1 AND VISIBILITE_MIN <= 0 ORDER BY ORDRE ASC");
        $stmt->execute();
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Erreur DB : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Accueil</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/ico/0.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    
</head>
<body>
<div class="home-container">
    <?php if ($user): ?>
        <h1>Bienvenue, <?= htmlspecialchars($user['PRENOM'] . ' ' . $user['NOM']) ?> !</h1>
        <p>Groupe : <strong><?= htmlspecialchars($user['GROUPE']) ?></strong> (niveau <?= $user['LVL'] ?>)</p>
        <p>Les services affichés ci-dessous représentent les prestations réalisables par le propriétaire de ce site.<br>
    <?php endif; ?>

    <?php if (!$user || (isset($user['LVL']) && $user['LVL'] == 0)): ?>
        <div class="banner">
            <p><strong>👋 Bienvenue sur [WELCOMPAGENAME] !</strong><br><br>
                Les services affichés ci-dessous représentent les prestations réalisables par le propriétaire de ce site.<br>
                Créez un compte pour contacter le gérant ou accéder à davantage d'informations personnalisées.
            </p>
            <br>
        </div>
    <?php endif; ?>


    

    

    <?php if (!empty($services)): ?>
        <h2>📋 Services disponibles</h2>
        <div class="services-grid">
            <?php foreach ($services as $service): ?>
                <div class="service-card">
                    <?php if (!empty($service['IMAGE'])): ?>
                        <div class="image-container">
                            <img src="<?= htmlspecialchars($service['IMAGE']) ?>" alt="Image service" class="service-image">
                        </div>
                    <?php endif; ?>


                    <h3><?= htmlspecialchars($service['NOM']) ?></h3>
                    <p><?= nl2br(htmlspecialchars($service['DESCRIPTION'])) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($user && $user['LVL'] >= 1): ?>
        <div class="button-group">
            <?php if ($user['LVL'] >= 10): ?>
                <form action="admin-panel.php" method="get"><button type="submit">Panneau d'administration</button></form>
                <form action="config_mail.php" method="get"><button type="submit">Configuration SMTP</button></form>
                <form action="admin-contact.php" method="get"><button type="submit">Configuration Contact</button></form>
            <?php endif; ?>

            <?php if ($user['LVL'] >= 4): ?>
                <form action="admin-service.php" method="get"><button type="submit">Configuration des Services</button></form>
                <form action="cv.php" method="get"><button type="submit">CV PDF</button></form>

            <?php endif; ?>

            <form action="messagerie.php" method="get">
                <button type="submit">✉️ Messagerie</button>
            </form>

            <form action="contact.php" method="get"><button type="submit">Contact</button></form>
            <form action="account.php" method="get">
                <button type="submit">⚙️ Account</button>
            </form>
            <form action="logout.php" method="get"><button type="submit">Se déconnecter</button></form>
            
        </div>
    <?php endif; ?>


    <?php if (!$user || (isset($user['LVL']) && $user['LVL'] === 0)): ?>
        <div class="banner">
            
            <br>
            <form action="register.php" method="get">
                <button type="submit">Créer un compte</button>
            </form>
            <form action="login.php" method="get">
                <button type="submit">Se connecter</button>
            </form>
        </div>
    <?php endif; ?>

</div>

<div id="banniere-message">
    📩 Vous avez <span id="nb-msg">0</span> message(s) non lu(s). 
    <a href="messagerie.php">📬 Ouvrir la messagerie</a>
</div>




<footer>
    © <?= date('Y') ?> [FOOTER_CREDIT] Services. Tous droits réservés.
</footer>





<script>
function checkMessages() {
    fetch('check_unread.php')
        .then(response => response.text())
        .then(data => {
            const count = parseInt(data);
            const banner = document.getElementById('banniere-message');
            const nb = document.getElementById('nb-msg');

            if (count > 0) {
                nb.textContent = count;
                banner.style.display = 'block';
            } else {
                banner.style.display = 'none';
            }
        });
}

// Appel initial
checkMessages();

// Mise à jour toutes les 3 secondes
setInterval(checkMessages, 3000);
</script>




</body>
</html>
