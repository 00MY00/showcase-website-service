<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;


if (!isset($_SESSION['user_id']) || $_SESSION['user_lvl'] < 10) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/assets/PHPMailer/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/assets/PHPMailer/vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/assets/PHPMailer/vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pdo = new PDO('sqlite:assets/SQL/mes-services-db.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$message = '';
$auth_ok = $_SESSION['smtp_admin_ok'] ?? false;

// Authentification via mot de passe utilisateur
if (!$auth_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_password'])) {
    $stmt = $pdo->prepare("SELECT MDP FROM USER WHERE ID = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();

    if (password_verify($_POST['access_password'], $hash)) {
        $_SESSION['smtp_admin_ok'] = true;
        $auth_ok = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $message = "❌ Mot de passe incorrect.";
    }
}

// Enregistrement config
if ($auth_ok && isset($_POST['save_config'])) {
    $stmt = $pdo->prepare("DELETE FROM SMTP_CONFIG");
    $stmt->execute();

    $stmt = $pdo->prepare("INSERT INTO SMTP_CONFIG (HOST, PORT, SECURE, USERNAME, PASSWORD) VALUES (:host, :port, :secure, :user, :pass)");
    $stmt->execute([
        'host' => $_POST['smtp_host'],
        'port' => $_POST['smtp_port'],
        'secure' => $_POST['smtp_secure'],
        'user' => $_POST['smtp_user'],
        'pass' => $_POST['smtp_pass']
    ]);

    $message = "✅ Configuration enregistrée.";
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Envoi de test
if ($auth_ok && isset($_POST['send_test']) && !empty($_POST['email_to'])) {
    $smtp = $pdo->query("SELECT * FROM SMTP_CONFIG LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    if ($smtp) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $smtp['HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp['USERNAME'];
            $mail->Password = $smtp['PASSWORD'];
            $mail->SMTPSecure = $smtp['SECURE'];
            $mail->Port = (int)$smtp['PORT'];

            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->setFrom($smtp['USERNAME'], 'Test SMTP');
            $mail->addAddress($_POST['email_to']);
            $mail->Subject = 'Test SMTP réussi';
            $mail->Body    = "Ceci est un test d'envoi depuis votre configuration SMTP.";

            $mail->send();
            $message = "✅ E-mail envoyé avec succès à " . htmlspecialchars($_POST['email_to']) . ".";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $message = "❌ Échec de l'envoi : " . $mail->ErrorInfo;
        }
    } else {
        $message = "❌ Aucune configuration SMTP enregistrée.";
    }
}

// Charger config actuelle
$config = $pdo->query("SELECT * FROM SMTP_CONFIG LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Configuration SMTP</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/ico/0.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">



</head>
<body>
<div class="form-container">
    <h1>Configuration SMTP</h1>

    <?php if ($message): ?>
        <p><strong><?= htmlspecialchars($message) ?></strong></p>
    <?php endif; ?>

    <?php if (!$auth_ok): ?>
        <form method="POST">
            <label>Mot de passe de votre compte :</label>
            <input type="password" name="access_password" required>
            <button type="submit">S'authentifier</button>
        </form>
    <?php else: ?>
        <form method="POST">
            <label>Hôte SMTP</label>
            <input type="text" name="smtp_host" required value="<?= htmlspecialchars($config['HOST'] ?? '') ?>">

            <label>Port</label>
            <input type="number" name="smtp_port" required value="<?= htmlspecialchars($config['PORT'] ?? 587) ?>">

            <label>Sécurité</label>
            <select name="smtp_secure">
                <option value="tls" <?= ($config['SECURE'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                <option value="ssl" <?= ($config['SECURE'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
            </select>

            <label>Nom d'utilisateur SMTP</label>
            <input type="text" name="smtp_user" required value="<?= htmlspecialchars($config['USERNAME'] ?? '') ?>">

            <label>Mot de passe SMTP</label>
            <input type="password" name="smtp_pass" required value="<?= htmlspecialchars($config['PASSWORD'] ?? '') ?>">

            <button type="submit" name="save_config">📅 Enregistrer la configuration</button>
        </form>

        <hr>

        <form method="POST">
            <label>Adresse e-mail de test :</label>
            <input type="email" name="email_to" required>
            <button type="submit" name="send_test">✉ Tester l'envoi</button>
        </form>

        <hr>

        <h2>Configuration SMTP actuelle</h2>
        <?php if ($config): ?>
            <table border="1" cellpadding="5">
                <tr><th>Hôte</th><td><?= htmlspecialchars($config['HOST']) ?></td></tr>
                <tr><th>Port</th><td><?= htmlspecialchars($config['PORT']) ?></td></tr>
                <tr><th>Sécurité</th><td><?= htmlspecialchars($config['SECURE']) ?></td></tr>
                <tr><th>Utilisateur</th><td><?= htmlspecialchars($config['USERNAME']) ?></td></tr>
                <tr><th>Mot de passe</th><td><?= str_repeat('*', strlen($config['PASSWORD'])) ?></td></tr>
            </table>
        <?php else: ?>
            <p><em>Aucune configuration trouvée.</em></p>
        <?php endif; ?>
    <?php endif; ?>

    <form action="home.php" method="get" style="margin-top: 2rem;">
        <button type="submit">← Retour accueil</button>
    </form>

    <form action="admin-panel.php" method="get" style="margin-top: 2rem;">
        <button type="submit">← Retour au panneau d'administration</button>
    </form>
</div>

    <footer>
        © <?= date('Y') ?> [FOOTER_CREDIT] Services. Tous droits réservés.
    </footer>
</body>
</html>
