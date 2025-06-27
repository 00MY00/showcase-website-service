<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;


// Vérifie que l'utilisateur est authentifié avec un niveau suffisant
$user_ok = isset($_SESSION['user_id']) && isset($_SESSION['user_lvl']) && $_SESSION['user_lvl'] >= 4;

// Détection mobile simple
$is_mobile = preg_match('/(android|iphone|ipad|mobile)/i', $_SERVER['HTTP_USER_AGENT']);

// Recherche du PDF dans le dossier sécurisé
$pdf_name = null;
$pdf_dir = __DIR__ . '/assets/secure';

if ($user_ok && is_dir($pdf_dir)) {
    foreach (scandir($pdf_dir) as $file) {
        if (str_ends_with(strtolower($file), '.pdf')) {
            $pdf_name = $file;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon CV</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/ico/0.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">

</head>
<body class="body-cv">
    <div class="cv-container">
        <?php if ($user_ok && $pdf_name): ?>
            <h1>📄 Mon CV</h1>

            <?php if ($is_mobile): ?>
                <!-- Mobile : bouton pour ouvrir le PDF dans un nouvel onglet -->
                <a href="serve-cv.php?f=<?= urlencode($pdf_name) ?>" target="_blank" class="btn-pdf-mobile">
                    📄 Ouvrir le CV
                </a>
            <?php else: ?>
                <!-- Desktop : affichage intégré dans un iframe -->
                <iframe
                    src="serve-cv.php?f=<?= urlencode($pdf_name) ?>#zoom=page-width"
                    class="cv-frame">
                </iframe>
            <?php endif; ?>

            <form action="home.php" method="get">
                <button type="submit">← Retour à l’accueil</button>
            </form>
        <?php elseif ($user_ok): ?>
            <h1>CV indisponible</h1>
            <p>⚠️ Aucun fichier PDF trouvé dans le dossier sécurisé.</p>
            <form action="home.php" method="get">
                <button type="submit">← Retour à l’accueil</button>
            </form>
        <?php else: ?>
            <h1>Accès restreint</h1>
            <p>🚫 Cette page est réservée aux utilisateurs enregistrés.</p>
            <p>Veuillez créer un compte ou vous connecter pour consulter le CV.</p>
            <form action="register.php" method="get" style="margin-bottom:1rem;">
                <button type="submit">Créer un compte</button>
            </form>
            <form action="home.php" method="get">
                <button type="submit">← Retour à l’accueil</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
