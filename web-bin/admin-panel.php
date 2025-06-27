<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;


if (!isset($_SESSION['user_id']) || $_SESSION['user_lvl'] < 1) {
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

    if ($_SESSION['user_lvl'] >= 10) {
        if (isset($_POST['nouveau_groupe'], $_POST['lvl']) && trim($_POST['nouveau_groupe']) !== '') {
            $stmt = $pdo->prepare("INSERT INTO `GROUP` (NAME, LVL) VALUES (:name, :lvl)");
            $stmt->execute([
                'name' => $_POST['nouveau_groupe'],
                'lvl' => intval($_POST['lvl'])
            ]);
            $message = "Groupe ajouté.";
        }

        // Suppression d'un groupe (interdit si Admin)
        if (isset($_GET['delgroup']) && $_SESSION['user_lvl'] >= 10) {
            $group_id = intval($_GET['delgroup']);

            // Récupère le nom du groupe à supprimer
            $stmt = $pdo->prepare("SELECT NAME FROM `GROUP` WHERE ID = :id");
            $stmt->execute(['id' => $group_id]);
            $group_name = $stmt->fetchColumn();

            if ($group_name !== 'Admin') {
                $pdo->prepare("DELETE FROM `GROUP` WHERE ID = :id")->execute(['id' => $group_id]);
                $message = "Groupe supprimé.";
            } else {
                $message = "❌ Le groupe 'Admin' ne peut pas être supprimé.";
            }
        }


        if (isset($_POST['user_id'], $_POST['new_group'])) {
            $pdo->prepare("UPDATE USER SET GROUP_ID = :gid WHERE ID = :uid")
                ->execute(['gid' => $_POST['new_group'], 'uid' => $_POST['user_id']]);
            $message = "Utilisateur mis à jour.";
        }
    }

    if ($_SESSION['user_lvl'] >= 10) {
        $stmt = $pdo->query("SELECT U.ID, U.NOM, U.PRENOM, U.EMAIL, U.NUMEROMOBIL, G.NAME AS GROUPE, U.ACTIVER FROM USER U JOIN `GROUP` G ON U.GROUP_ID = G.ID");
        $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT U.ID, U.NOM, U.PRENOM, U.EMAIL, U.NUMEROMOBIL, G.NAME AS GROUPE, U.ACTIVER FROM USER U JOIN `GROUP` G ON U.GROUP_ID = G.ID WHERE U.ID = :id");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($_SESSION['user_lvl'] >= 10 && isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);

    // Ne pas supprimer soi-même
    if ($id !== $_SESSION['user_id']) {
        // Supprimer tous les messages liés (envoyés ou reçus)
        $pdo->prepare("DELETE FROM MESSAGE WHERE ORIGIN_USER_ID = :id OR DESTINATION_USER_ID = :id")->execute(['id' => $id]);

        // Supprimer dans CONTACT si applicable
        $pdo->prepare("DELETE FROM CONTACT WHERE ID = :id")->execute(['id' => $id]);

        // Supprimer l'utilisateur
        $pdo->prepare("DELETE FROM USER WHERE ID = :id")->execute(['id' => $id]);

        header("Location: admin-panel.php?success=1");
        exit;
    }
}


    if (isset($_POST['nouveau_mdp']) && $_POST['nouveau_mdp'] !== '') {
        $hash = password_hash($_POST['nouveau_mdp'], PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE USER SET MDP = :mdp WHERE ID = :id")
            ->execute(['mdp' => $hash, 'id' => $_SESSION['user_id']]);
        $message = "Mot de passe mis à jour avec succès.";
    }

    if ($_SESSION['user_lvl'] >= 10 && isset($_GET['resend_token'])) {
        $id = intval($_GET['resend_token']);
        $stmt = $pdo->prepare("SELECT EMAIL, ACTIVER FROM USER WHERE ID = :id");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !$user['ACTIVER']) {
            $token = bin2hex(random_bytes(16));
            $pdo->prepare("UPDATE USER SET TOKEN = :token WHERE ID = :id")
                ->execute(['token' => $token, 'id' => $id]);

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

                    $mail->setFrom($smtp['USERNAME'], 'Vérification de compte');
                    $mail->addAddress($user['EMAIL']);
                    $mail->Subject = 'Réactivation de votre compte';
                    $mail->Body = "Merci de cliquer sur ce lien pour activer votre compte : $base_url/activate.php?token=$token";
                    $mail->CharSet = 'UTF-8';
                    $mail->Encoding = 'base64';
                    $mail->send();
                    $message = "E-mail de vérification renvoyé.";
                } catch (Exception $e) {
                    $message = "Erreur d'envoi : " . $mail->ErrorInfo;
                }
            }
        }
    }

    $groupes = $pdo->query("SELECT * FROM `GROUP` ORDER BY LVL DESC")->fetchAll(PDO::FETCH_ASSOC);
    $group_utilisateurs = [];
    foreach ($groupes as $g) {
        $stmt = $pdo->prepare("SELECT * FROM USER WHERE GROUP_ID = :gid");
        $stmt->execute(['gid' => $g['ID']]);
        $group_utilisateurs[$g['ID']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Erreur DB : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Panneau Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/ico/0.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">


</head>
<body>
<div class="admin-container">
    <h1>Panneau de gestion</h1>

    <?php if (isset($message)): ?>
        <p class="success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <h2>Informations <?= $_SESSION['user_lvl'] >= 10 ? 'des utilisateurs' : 'personnelles' ?></h2>
    <table>
        <thead>
            <tr><th>Nom</th><th>Prénom</th><th>Email</th><th>Téléphone</th><th>Groupe</th><th>Statut</th><?php if ($_SESSION['user_lvl'] >= 10): ?><th>Actions</th><?php endif; ?></tr>
        </thead>
        <tbody>
            <?php foreach ($utilisateurs as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['NOM']) ?></td>
                    <td><?= htmlspecialchars($u['PRENOM']) ?></td>
                    <td><?= htmlspecialchars($u['EMAIL']) ?></td>
                    <td><?= htmlspecialchars($u['NUMEROMOBIL']) ?></td>
                    <td><?= htmlspecialchars($u['GROUPE']) ?></td>
                    <td><?= $u['ACTIVER'] ? '<span style="color:green;font-weight:bold;">● Actif</span>' : '<span style="color:red;font-weight:bold;">● Inactif</span>' ?></td>
                    <?php if ($_SESSION['user_lvl'] >= 10): ?>
                        <td>
                            <?php if ($u['ID'] != $_SESSION['user_id']): ?>
                                <a href="?supprimer=<?= $u['ID'] ?>" onclick="return confirm('Confirmer la suppression ?')">🗑 Supprimer</a><br>
                                <?php if (!$u['ACTIVER']): ?>
                                    <a href="?resend_token=<?= $u['ID'] ?>">🔄 Renvoyer le lien d'activation</a>
                                <?php endif; ?>
                            <?php else: ?>Vous<?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Changer votre mot de passe</h2>
    <form method="POST">
        <input type="password" name="nouveau_mdp" placeholder="Nouveau mot de passe" required>
        <button type="submit">Mettre à jour</button>
    </form>

    <?php if ($_SESSION['user_lvl'] >= 10): ?>
        <h2>Ajouter un utilisateur</h2>
        <form method="POST" action="ajouter-utilisateur.php">
            <input type="text" name="prenom" placeholder="Prénom" required>
            <input type="text" name="nom" placeholder="Nom" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="mobile" placeholder="Mobile">
            <input type="password" name="mdp" placeholder="Mot de passe" required>
            <select name="groupe" required>
                <?php foreach ($groupes as $g): ?>
                    <option value="<?= $g['ID'] ?>"><?= htmlspecialchars($g['NAME']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Créer l'utilisateur</button>
        </form>

        <h2>Gestion des groupes</h2>
        <form method="POST">
            <h3>Création de groups</h3>
            <input type="text" name="nouveau_groupe" placeholder="Nom du groupe" required>
            <input type="number" name="lvl" placeholder="Niveau" required>
            <button type="submit">Ajouter</button>
        </form>

        <?php foreach ($groupes as $g): ?>
            <div>
                <h3><?= htmlspecialchars($g['NAME']) ?> (LVL <?= $g['LVL'] ?>)
                    <a href="?delgroup=<?= $g['ID'] ?>" onclick="return confirm('Supprimer ce groupe ?')">🗑</a>
                </h3>
                <ul>
                    <?php foreach ($group_utilisateurs[$g['ID']] as $u): ?>
                        <li>
                            <?= htmlspecialchars($u['PRENOM'] . ' ' . $u['NOM']) ?> (<?= htmlspecialchars($u['EMAIL']) ?>)
                            <form method="POST" style="display:inline">
                                <select name="new_group">
                                    <?php foreach ($groupes as $gg): ?>
                                        <option value="<?= $gg['ID'] ?>" <?= $gg['ID'] == $u['GROUP_ID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($gg['NAME']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="user_id" value="<?= $u['ID'] ?>">
                                <button type="submit">Changer</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>


    <br>
    <form action="config_mail.php" method="get" style="margin-top: 2rem;">
        <button type="submit">← Configuration SMTP</button>
    </form>

    <form action="home.php" method="get" style="margin-top: 2rem;">
        <button type="submit">← Retour accueil</button>
    </form>
    

</div>
    <footer>
        © <?= date('Y') ?> [FOOTER_CREDIT] Services. Tous droits réservés.
    </footer>
</body>
</html>
