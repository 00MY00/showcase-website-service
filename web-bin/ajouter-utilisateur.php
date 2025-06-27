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

try {
    $pdo = new PDO('sqlite:assets/SQL/mes-services-db.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (
        isset($_POST['prenom'], $_POST['nom'], $_POST['email'], $_POST['mdp'], $_POST['groupe']) &&
        trim($_POST['prenom']) !== '' && trim($_POST['email']) !== '' && trim($_POST['mdp']) !== ''
    ) {
        $prenom = trim($_POST['prenom']);
        $nom = trim($_POST['nom']);
        $email = trim($_POST['email']);
        $mobile = trim($_POST['mobile'] ?? '');
        $groupe = intval($_POST['groupe']);
        $mdp = password_hash($_POST['mdp'], PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("INSERT INTO USER (PRENOM, NOM, EMAIL, NUMEROMOBIL, MDP, GROUP_ID, TOKEN, ACTIVER)
            VALUES (:prenom, :nom, :email, :mobile, :mdp, :groupe, :token, 0)");
        $stmt->execute([
            'prenom' => $prenom,
            'nom' => $nom,
            'email' => $email,
            'mobile' => $mobile,
            'mdp' => $mdp,
            'groupe' => $groupe,
            'token' => $token
        ]);

        // Envoi de mail de confirmation
        $mail = new PHPMailer(true);
        $smtp = $pdo->query("SELECT * FROM SMTP_CONFIG LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if ($smtp) {
            try {
                $mail->isSMTP();
                $mail->Host = $smtp['HOST'];
                $mail->SMTPAuth = true;
                $mail->Username = $smtp['USERNAME'];
                $mail->Password = $smtp['PASSWORD'];
                $mail->SMTPSecure = $smtp['SECURE'];
                $mail->Port = (int)$smtp['PORT'];

                $mail->setFrom($smtp['USERNAME'], 'Confirmation de compte');
                $mail->addAddress($email);
                $mail->Subject = 'Activez votre compte';
                $mail->Body = "Bienvenue $prenom,\n\nCliquez ici pour activer votre compte : $base_url/activate.php?token=$token";
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                $mail->send();
                $message = "Utilisateur ajouté et e-mail de confirmation envoyé.";
            } catch (Exception $e) {
                $message = "Utilisateur ajouté, mais erreur d'envoi de mail : " . $mail->ErrorInfo;
            }
        } else {
            $message = "Utilisateur ajouté. SMTP non configuré.";
        }
    } else {
        $message = "Veuillez remplir tous les champs requis.";
    }

} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajout Utilisateur</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/ico/0.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">


</head>
<body>
    <h1>Ajout d'un utilisateur</h1>
    <p><?= htmlspecialchars($message ?? '') ?></p>

    <form action="home.php" method="get" style="margin-top: 2rem;">
        <button type="submit">← Retour accueil</button>
    </form>
    
    <form action="admin-panel.php" method="get" style="margin-top: 2rem;">
        <button type="submit">← Retour au panneau d'administration</button>
    </form>

    <footer>
        © <?= date('Y') ?> [FOOTER_CREDIT] Services. Tous droits réservés.
    </footer>
</body>
</html>
