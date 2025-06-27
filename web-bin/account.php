<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;


// Connexion à la base SQLite
try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/assets/SQL/mes-services-db.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erreur DB : ' . $e->getMessage());
}

// Chargement PHPMailer
require 'assets/PHPMailer/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'assets/PHPMailer/vendor/phpmailer/phpmailer/src/SMTP.php';
require 'assets/PHPMailer/vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Vérifie la session
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom           = trim($_POST['nom'] ?? '');
    $prenom        = trim($_POST['prenom'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $numero_mobil  = trim($_POST['numero_mobil'] ?? '');

    $current_mdp   = $_POST['current_mdp'] ?? '';
    $new_mdp       = $_POST['new_mdp'] ?? '';
    $confirm_mdp   = $_POST['confirm_mdp'] ?? '';

    if ($nom && $prenom && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Cas 1 : Aucun champ mot de passe rempli → mise à jour des infos seulement
        if (empty($current_mdp) && empty($new_mdp) && empty($confirm_mdp)) {
            $pdo->prepare("UPDATE USER SET NOM = :nom, PRENOM = :prenom, EMAIL = :email, NUMEROMOBIL = :mobile WHERE ID = :id")
                ->execute([
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':email' => $email,
                    ':mobile' => $numero_mobil,
                    ':id' => $user_id
                ]);
            $success = "✅ Informations mises à jour avec succès.";
        }

        // Cas 2 : Tentative de changement de mot de passe
        else {
            // Vérifie que tous les champs sont remplis
            if (!$current_mdp || !$new_mdp || !$confirm_mdp) {
                $error = "❌ Tous les champs de mot de passe doivent être remplis.";
            } elseif ($new_mdp !== $confirm_mdp) {
                $error = "❌ Le nouveau mot de passe et la confirmation ne correspondent pas.";
            } else {
                // Récupération du mot de passe actuel
                $stmt = $pdo->prepare("SELECT MDP FROM USER WHERE ID = ?");
                $stmt->execute([$user_id]);
                $stored = $stmt->fetchColumn();

                $is_hashed = preg_match('/^\$2[ayb]\$/', $stored);
                $valid_password = $is_hashed
                    ? password_verify($current_mdp, $stored)
                    : $current_mdp === $stored;

                if ($valid_password) {
                    // Vérifie la complexité du nouveau mot de passe
                    $errors = [];
                    if (strlen($new_mdp) < 8) $errors[] = "8 caractères minimum";
                    if (!preg_match('/[A-Z]/', $new_mdp)) $errors[] = "1 majuscule";
                    if (!preg_match('/[a-z]/', $new_mdp)) $errors[] = "1 minuscule";
                    if (!preg_match('/\d/', $new_mdp)) $errors[] = "1 chiffre";

                    if ($errors) {
                        $error = "❌ Mot de passe trop faible : " . implode(', ', $errors);
                    } else {
                        // Hachage + mise à jour
                        $hash = password_hash($new_mdp, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE USER SET MDP = :mdp WHERE ID = :id")
                            ->execute([':mdp' => $hash, ':id' => $user_id]);

                        // Mise à jour des autres infos aussi (si modifiées)
                        $pdo->prepare("UPDATE USER SET NOM = :nom, PRENOM = :prenom, EMAIL = :email, NUMEROMOBIL = :mobile WHERE ID = :id")
                            ->execute([
                                ':nom' => $nom,
                                ':prenom' => $prenom,
                                ':email' => $email,
                                ':mobile' => $numero_mobil,
                                ':id' => $user_id
                            ]);

                        // Envoi mail confirmation
                        $smtp = $pdo->query("SELECT * FROM SMTP_CONFIG LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                        if ($smtp) {
                            try {
                                $mail = new PHPMailer(true);
                                $mail->CharSet = 'UTF-8';

                                $mail->isSMTP();
                                $mail->Host       = $smtp['HOST'];
                                $mail->SMTPAuth   = true;
                                $mail->Username   = $smtp['USERNAME'];
                                $mail->Password   = $smtp['PASSWORD'];
                                $mail->SMTPSecure = $smtp['SECURE'];
                                $mail->Port       = $smtp['PORT'];

                                $mail->setFrom($smtp['USERNAME'], 'Mes Services');
                                $mail->addAddress($email, "$prenom $nom");
                                $mail->Subject = '🔐 Changement de mot de passe';
                                $mail->Body = "Bonjour $prenom,\n\nVotre mot de passe sur '$base_url/' a été modifié avec succès.\n\nSi ce n'était pas vous, merci de contacter immédiatement le support.\n\n[SUPORT_E_MAIL]\n--\n[ENDSIGN]";

                                $mail->send();
                                $success = "✅ Mot de passe changé avec succès. Un e-mail de confirmation a été envoyé.";
                            } catch (Exception $e) {
                                error_log("Erreur mail : " . $mail->ErrorInfo);
                                $success = "✅ Mot de passe changé, mais l'e-mail de confirmation n'a pas pu être envoyé.";
                            }
                        } else {
                            $success = "✅ Mot de passe changé. Aucune configuration SMTP trouvée.";
                        }
                    }
                } else {
                    $error = "❌ Mot de passe actuel incorrect.";

                    // Alerte par e-mail
                    $stmt = $pdo->prepare("SELECT HOST, USERNAME, PASSWORD, SECURE, PORT FROM SMTP_CONFIG LIMIT 1");
                    $stmt->execute();
                    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($smtp) {
                        try {
                            $ip        = $_SERVER['REMOTE_ADDR'] ?? 'IP inconnue';
                            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'User-Agent inconnu';

                            $mail = new PHPMailer(true);
                            $mail->CharSet = 'UTF-8';

                            $mail->isSMTP();
                            $mail->Host       = $smtp['HOST'];
                            $mail->SMTPAuth   = true;
                            $mail->Username   = $smtp['USERNAME'];
                            $mail->Password   = $smtp['PASSWORD'];
                            $mail->SMTPSecure = $smtp['SECURE'];
                            $mail->Port       = $smtp['PORT'];

                            $mail->setFrom($smtp['USERNAME'], 'Mes Services');
                            $mail->addAddress($email, "$prenom $nom");
                            $mail->Subject = '❌ Échec de modification du mot de passe';
                            $mail->Body = "Bonjour $prenom,\n\n"
                                . "⚠️ Une tentative de modification de votre mot de passe sur '$base_url/' a échoué.\n"
                                . "Mot de passe actuel incorrect.\n\n"
                                . "📍 Détails de la tentative :\n"
                                . "IP : $ip\n"
                                . "Navigateur : $userAgent\n\n"
                                . "Si ce n'était pas vous, contactez immédiatement le support : [SUPORT_E_MAIL]\n\n"
                                . "--\n[ENDSIGN]";

                            $mail->send();
                        } catch (Exception $e) {
                            error_log("Erreur mail tentative échouée : " . $mail->ErrorInfo);
                        }
                    }
                }
            }
        }
    } else {
        $error = "❌ Veuillez remplir les champs correctement.";
    }
}


// Infos utilisateur
$stmt = $pdo->prepare("SELECT NOM, PRENOM, EMAIL, NUMEROMOBIL FROM USER WHERE ID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Utilisateur introuvable.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon compte</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/ico/0.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">

</head>
<body class="body-cv">
<div class="cv-container">
    <h1>👤 Mon compte</h1>

    <?php if ($success): ?>
        <p style="color:green"><?= htmlspecialchars($success) ?></p>
    <?php elseif ($error): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <p><strong>Email :</strong> <?= htmlspecialchars($user['EMAIL']) ?></p>

        <label>Nom :</label><br>
        <input type="text" name="nom" value="<?= htmlspecialchars($user['NOM']) ?>" required><br><br>

        <label>Prénom :</label><br>
        <input type="text" name="prenom" value="<?= htmlspecialchars($user['PRENOM']) ?>" required><br><br>

        <label>Email :</label><br>
        <input type="email" name="email" value="<?= htmlspecialchars($user['EMAIL']) ?>" required><br><br>

        <label>Numéro mobile :</label><br>
        <input type="text" name="numero_mobil" value="<?= htmlspecialchars($user['NUMEROMOBIL']) ?>"><br><br>

        <hr>
        <h2>🔒 Modifier le mot de passe</h2>

        <label>Mot de passe actuel :</label><br>
        <input type="password" name="current_mdp"><br><br>

        <label>Nouveau mot de passe :</label><br>
        <input type="password" name="new_mdp"><br><br>

        <label>Confirmer le mot de passe :</label><br>
        <input type="password" name="confirm_mdp"><br><br>

        <button type="submit">💾 Enregistrer les modifications</button>
    </form>

    <form action="home.php" method="get" style="margin-top: 1rem;">
        <button type="submit">← Retour à l’accueil</button>
    </form>
</div>
</body>
</html>
