<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;



if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

require 'assets/PHPMailer/vendor/autoload.php'; // PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$erreurs = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom = htmlspecialchars(trim($_POST['prenom']));
    $nom = htmlspecialchars(trim($_POST['nom']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $code = ($_POST['indicatif'] === 'custom') ? htmlspecialchars(trim($_POST['custom_indicatif'])) : htmlspecialchars(trim($_POST['indicatif']));
    $numero = preg_replace('/[^0-9]/', '', $_POST['mobile']);
    $mobile = $code . ' ' . preg_replace('/(..)(?!$)/', '$1 ', $numero);
    $mdp = $_POST['mdp'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "Adresse e-mail invalide.";
    }

    if (
        strlen($mdp) < 8 ||
        !preg_match('/[A-Z]/', $mdp) ||
        !preg_match('/[a-z]/', $mdp) ||
        !preg_match('/[0-9]/', $mdp) ||
        !preg_match('/[\W]/', $mdp)
    ) {
        $erreurs[] = "Mot de passe trop faible (8+ caractères, majuscules, minuscules, chiffres, symboles).";
    }

    try {
        $pdo = new PDO('sqlite:assets/SQL/mes-services-db.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM USER WHERE EMAIL = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetchColumn() > 0) {
            $erreurs[] = "Cet e-mail est déjà utilisé.";
        }

        if (empty($erreurs)) {
            $stmt = $pdo->prepare("SELECT ID FROM `GROUP` WHERE NAME = 'utilisateur' LIMIT 1");
            $stmt->execute();
            $groupe_id = $stmt->fetchColumn();

            if (!$groupe_id) {
                $erreurs[] = "Le groupe 'utilisateur' n'existe pas.";
            } else {
                $token = bin2hex(random_bytes(16));
                $hash = password_hash($mdp, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO USER (GROUP_ID, NOM, PRENOM, EMAIL, NUMEROMOBIL, LANGUE, MDP, ACTIVER, TOKEN)
                    VALUES (:groupe, :nom, :prenom, :email, :mobile, 'fr', :mdp, 0, :token)
                ");
                $stmt->execute([
                    'groupe' => $groupe_id,
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'mobile' => $mobile,
                    'mdp' => $hash,
                    'token' => $token
                ]);

                // Récupérer la config SMTP
                $smtp = $pdo->query("SELECT * FROM SMTP_CONFIG LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if (!$smtp) {
                    $erreurs[] = "Configuration SMTP manquante.";
                } else {
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = $smtp['HOST'];
                        $mail->Port = $smtp['PORT'];
                        $mail->SMTPAuth = true;
                        $mail->Username = $smtp['USERNAME'];
                        $mail->Password = $smtp['PASSWORD'];
                        $mail->SMTPSecure = strtolower($smtp['SECURE']); // 'tls' ou 'ssl'

                        $mail->setFrom($smtp['USERNAME'], '[ENDSIGN]');
                        $mail->addAddress($email, "$prenom $nom");

                        $activationLink = "$base_url/activate.php?token=$token";

                        $mail->Subject = "Activation de votre compte";
                        $mail->Body = "Bonjour $prenom,\n\nCliquez ici pour activer votre compte : $activationLink\n\nMerci !";

                        $mail->send();
                        $success = true;
                    } catch (Exception $e) {
                        $erreurs[] = "Erreur lors de l'envoi du mail : " . $mail->ErrorInfo;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $erreurs[] = "Erreur DB : " . $e->getMessage();
    }
}
?>
<!-- Le reste du HTML reste inchangé -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer un compte</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/ico/0.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">


</head>
<body>
<div class="form-container">
    <h1>Créer un compte</h1>

    <?php if ($success): ?>
        <div class="success">
            ✅ Compte créé. Un e-mail de confirmation a été envoyé.
        </div>
    <?php else: ?>
        <?php if (!empty($erreurs)): ?>
            <div class="error">
                <ul>
                    <?php foreach ($erreurs as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Prénom :</label>
            <input type="text" name="prenom" required value="<?= htmlspecialchars($prenom ?? '') ?>">

            <label>Nom :</label>
            <input type="text" name="nom" required value="<?= htmlspecialchars($nom ?? '') ?>">

            <label>Email :</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>">

            <label>Mobile :</label>
            <div style="display:flex; gap:5px; flex-wrap:wrap; align-items: center;">
                <div style="display: flex; gap: 5px; align-items: center;">
                    <select name="indicatif" id="indicatif-select" required>
                        <option value="+41">🇨🇭 +41</option>
                        <option value="+33">🇫🇷 +33</option>
                        <option value="+49">🇩🇪 +49</option>
                        <option value="+39">🇮🇹 +39</option>
                        <option value="+44">🇬🇧 +44</option>
                        <option value="+34">🇪🇸 +34</option>
                        <option value="+1">🇺🇸🇨🇦 +1</option>
                        <option value="custom">🔧 Autre...</option>
                    </select>
                    <input type="text" name="custom_indicatif" id="custom-indicatif" placeholder="+00" style="display:none; width: 60px;" pattern="^\+[0-9]{1,4}$">
                </div>
                <input type="text" name="mobile" pattern="[0-9 ]+" placeholder="78 123 45 67" value="<?= htmlspecialchars($numero ?? '') ?>" style="flex: 1;">
            </div>

            <label>Mot de passe :</label>
            <input type="password" name="mdp" required>

            <button type="submit">Créer mon compte</button>
        </form>
    <?php endif; ?>
    <form action="login.php" method="get" style="margin-top: 2rem;">
        <button type="submit">← Retour à la connexion</button>
    </form>
    <form action="home.php" method="get">
        <button type="submit">← Retour à l’accueil</button>
    </form>
</div>

<footer>
    © <?= date('Y') ?> [FOOTER_CREDIT] Services. Tous droits réservés.
</footer>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const input = document.querySelector('input[name="mobile"]');
    const select = document.getElementById("indicatif-select");
    const customInput = document.getElementById("custom-indicatif");

    input.addEventListener("input", function (e) {
        const digits = e.target.value.replace(/\D/g, "");
        const formatted = digits.replace(/(\d{2})(?=\d)/g, "$1 ").trim();
        e.target.value = formatted;
    });

    select.addEventListener("change", function () {
        if (this.value === "custom") {
            customInput.style.display = "inline-block";
            customInput.required = true;
        } else {
            customInput.style.display = "none";
            customInput.required = false;
        }
    });
});
</script>
</body>
</html>
