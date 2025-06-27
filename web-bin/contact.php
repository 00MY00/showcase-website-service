<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;


if (!isset($_SESSION['user_id']) || $_SESSION['user_lvl'] < 1) {
    header('Location: login.php');
    exit;
}

try {
    $pdo = new PDO('sqlite:assets/SQL/mes-services-db.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $contacts = $pdo->query("SELECT * FROM CONTACT ORDER BY ID DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur DB : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste de contacts</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/ico/0.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">



</head>
<body>
    <div class="contact-container">
        <h1>📇 Liste des contacts</h1>

        <?php if (empty($contacts)): ?>
            <p>Aucun contact enregistré.</p>
        <?php else: ?>
            <?php foreach ($contacts as $c): ?>
                <div class="contact-entry">
                    <h3><?= htmlspecialchars($c['PRENOM'] . ' ' . $c['NOM']) ?></h3>
                    <p><strong>Email :</strong> <?= htmlspecialchars($c['EMAIL']) ?></p>
                    <?php if (!empty($c['CERTIFICAT'])): ?>
                        <p><strong>Certificat :</strong> <?= htmlspecialchars($c['CERTIFICAT']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form action="home.php" method="get" style="margin-top: 2rem;">
            <button type="submit">← Retour accueil</button>
        </form>

        <?php if ($_SESSION['user_lvl'] >= 10): ?>
            <form action="admin-panel.php" method="get" style="margin-top: 2rem;">
                <button type="submit">← Retour au panneau d'administration</button>
            </form>
        <?php endif; ?>

    </div>

    <footer>
        © <?= date('Y') ?> [FOOTER_CREDIT] Services. Tous droits réservés.
    </footer>
</body>
</html>
