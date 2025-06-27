<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;


// Vérification de l’accès (utilisateur connecté avec niveau >= 1)
if (!isset($_SESSION['user_id']) || $_SESSION['user_lvl'] < 1) {
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Accès Messagerie</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="form-container">
            <h1>🚫 Accès restreint</h1>
            <p>Vous devez être connecté pour accéder à la messagerie.</p>
            <p><a href="login.php">Se connecter</a> ou <a href="register.php">Créer un compte</a></p>
        </div>
    </body>
    </html>
    HTML;
    exit;
}

$pdo = new PDO('sqlite:assets/SQL/mes-services-db.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$user_id = $_SESSION['user_id'];
$user_lvl = $_SESSION['user_lvl'];
$message  = '';  // Contient un éventuel message d’erreur à afficher à l’utilisateur

// Identifiant du contact sélectionné (conversation en cours)
$selected_user = isset($_GET['contact']) ? (int)$_GET['contact'] : null;

// Suppression d’un message envoyé par l’utilisateur (si lien "Supprimer" cliqué)
if (isset($_GET['supprimer'])) {
    $msg_id = intval($_GET['supprimer']);
    $stmt = $pdo->prepare("DELETE FROM MESSAGE WHERE ID = :id AND ORIGIN_USER_ID = :uid");
    $stmt->execute(['id' => $msg_id, 'uid' => $user_id]);
    // Après suppression, on reste sur la même conversation (ou page messagerie par défaut)
    if ($selected_user) {
        header("Location: messagerie.php?contact=$selected_user");
    } else {
        header("Location: messagerie.php");
    }
    exit;
}

// Envoi d’un nouveau message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dest_id'], $_POST['message'])) {
    $dest_id = (int)$_POST['dest_id'];
    $texte   = trim($_POST['message']);
    if ($texte !== '') {
        // Vérifie les droits d’envoi selon les niveaux (LVL 1 ne peut écrire qu’aux LVL 5–9)
        $stmt = $pdo->prepare("
            SELECT G.LVL 
            FROM USER U 
            JOIN `GROUP` G ON U.GROUP_ID = G.ID 
            WHERE U.ID = :id
        ");
        $stmt->execute(['id' => $dest_id]);
        $dest_lvl = $stmt->fetchColumn();
        if ($user_lvl == 1 && ($dest_lvl < 5 || $dest_lvl > 9)) {
            // Utilisateur non autorisé à écrire à ce destinataire
            $message = "❌ Vous n'avez pas le droit d'écrire à cet utilisateur.";
        } else {
            // Insère le message dans la base (marqué non lu par défaut)
            $stmt = $pdo->prepare("
                INSERT INTO MESSAGE (ORIGIN_USER_ID, DESTINATION_USER_ID, MESSAGE, DATEHEUR, LUE) 
                VALUES (:origin, :dest, :msg, datetime('now'), 0)
            ");
            $stmt->execute([
                'origin' => $user_id,
                'dest'   => $dest_id,
                'msg'    => htmlspecialchars($texte)
            ]);
            // Redirection vers la même conversation avec un indicateur de succès
            header("Location: messagerie.php?contact=$dest_id&envoye=1");
            exit;
        }
    }
}

// Gestion AJAX : actualisation périodique des messages de la conversation en cours
if (isset($_GET['ajax']) && isset($_GET['contact'])) {
    $contact_id = (int)$_GET['contact'];
    // Marquer comme lus tous les messages reçus de ce contact
    $stmt = $pdo->prepare("
        UPDATE MESSAGE 
        SET LUE = 1 
        WHERE ORIGIN_USER_ID = :contact AND DESTINATION_USER_ID = :uid AND LUE = 0
    ");
    $stmt->execute(['contact' => $contact_id, 'uid' => $user_id]);
    // Récupérer le nom du contact (pour affichage de l’auteur)
    $stmt = $pdo->prepare("SELECT PRENOM, NOM FROM USER WHERE ID = ?");
    $stmt->execute([$contact_id]);
    $contact_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $contact_name = $contact_row ? htmlspecialchars($contact_row['PRENOM'] . ' ' . $contact_row['NOM']) : 'Contact';
    // Récupérer tous les messages échangés avec ce contact, triés par date croissante
    $stmt = $pdo->prepare("
        SELECT * 
        FROM MESSAGE 
        WHERE (ORIGIN_USER_ID = :uid AND DESTINATION_USER_ID = :contact) 
           OR (ORIGIN_USER_ID = :contact AND DESTINATION_USER_ID = :uid)
        ORDER BY DATEHEUR ASC
    ");
    $stmt->execute(['uid' => $user_id, 'contact' => $contact_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Générer le HTML des messages (bubbles)
    $output = '';
    if (!$messages) {
        $output .= '<p>Aucun message pour le moment.</p>';
    } else {
        foreach ($messages as $m) {
            $isMe   = ($m['ORIGIN_USER_ID'] == $user_id);
            $author = $isMe ? 'Vous' : $contact_name;
            $date   = $m['DATEHEUR'];
            // Statut de lecture (affiché seulement pour les messages de l’utilisateur)
            $status = '';
            if ($isMe) {
                $status = $m['LUE'] ? '✅ Vu' : '🕓 Non lu';
            }
            // Contenu du message avec protection (HTML) et sauts de ligne
            $contenu = nl2br(htmlspecialchars($m['MESSAGE']));
            $msgClass = $isMe ? 'sent' : 'received';
            $output  .= "<div class='message $msgClass'>";
            $output  .= "<div class='message-content'>$contenu</div>";
            $output  .= "<div class='message-info'>$author - $date";
            if ($status) {
                $output .= " - $status";
            }
            $output  .= "</div>";
            if ($isMe) {
                // Lien de suppression pour les messages envoyés (optionnel)
                $msg_id = $m['ID'];
                $output .= "<a href='?contact=$contact_id&amp;supprimer=$msg_id' class='delete-link' onclick='return confirm(\"Supprimer ce message ?\")'>🗑 Supprimer</a>";
            }
            $output .= "</div>";
        }
    }
    echo $output;
    exit;
}

// Construction de la liste des contacts à afficher dans le menu de gauche
$contacts = [];
// Récupérer tous les ID d'utilisateurs ayant une conversation (messages échangés) avec l'utilisateur connecté
$stmt = $pdo->prepare("
    SELECT DISTINCT DESTINATION_USER_ID AS contact_id 
    FROM MESSAGE 
    WHERE ORIGIN_USER_ID = ? 
    UNION 
    SELECT DISTINCT ORIGIN_USER_ID 
    FROM MESSAGE 
    WHERE DESTINATION_USER_ID = ?
");
$stmt->execute([$user_id, $user_id]);
$conv_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Si des conversations existent, on récupère ces contacts, sinon on listera les utilisateurs autorisés
if ($conv_ids) {
    // Filtrer l'ID de l'utilisateur lui-même au cas où (par précaution)
    $conv_ids = array_filter($conv_ids, function($id) use ($user_id) { return $id != $user_id; });
    if (!empty($conv_ids)) {
        // Récupérer les infos (nom) de ces contacts
        $placeholders = implode(',', array_fill(0, count($conv_ids), '?'));
        $stmt = $pdo->prepare("SELECT ID, PRENOM, NOM FROM USER WHERE ID IN ($placeholders)");
        $stmt->execute($conv_ids);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
if (empty($contacts)) {
    // Aucune conversation existante : on liste tous les utilisateurs autorisés selon le niveau
    if ($user_lvl == 1) {
        // Niveau 1 : ne peut écrire qu'aux utilisateurs de niveau 5 à 9
        $stmt = $pdo->prepare("
            SELECT U.ID, U.PRENOM, U.NOM 
            FROM USER U 
            JOIN `GROUP` G ON U.GROUP_ID = G.ID 
            WHERE G.LVL BETWEEN 5 AND 9
        ");
        $stmt->execute();
    } else {
        // Niveau >= 5 : peut écrire à tout le monde (sauf soi-même)
        $stmt = $pdo->prepare("SELECT ID, PRENOM, NOM FROM USER WHERE ID != ?");
        $stmt->execute([$user_id]);
    }
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Comptage des messages non lus par contact (pour indiquer les conversations avec nouveaux messages)
$unread_counts = [];
$stmt = $pdo->prepare("
    SELECT ORIGIN_USER_ID, COUNT(*) AS cnt 
    FROM MESSAGE 
    WHERE DESTINATION_USER_ID = ? AND LUE = 0 
    GROUP BY ORIGIN_USER_ID
");
$stmt->execute([$user_id]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $unread_counts[$row['ORIGIN_USER_ID']] = $row['cnt'];
}

// Vérifier que le contact sélectionné fait partie de la liste (sécurité supplémentaire)
$selected_contact_name = '';
if ($selected_user) {
    $found = false;
    foreach ($contacts as $c) {
        if ($c['ID'] == $selected_user) {
            $found = true;
            $selected_contact_name = htmlspecialchars($c['PRENOM'] . ' ' . $c['NOM']);
            break;
        }
    }
    if (!$found) {
        $selected_user = null;
    }
}

// Marquer comme lus tous les messages reçus du contact sélectionné (lors du chargement initial de la conversation)
if ($selected_user) {
    $stmt = $pdo->prepare("
        UPDATE MESSAGE 
        SET LUE = 1 
        WHERE ORIGIN_USER_ID = :contact AND DESTINATION_USER_ID = :uid AND LUE = 0
    ");
    $stmt->execute(['contact' => $selected_user, 'uid' => $user_id]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Messagerie</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
    <link rel="icon" type="image/png" href="assets/img/ico/0.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
</head>
<body>
<div class="messagerie-container">
    <h1>📬 Messagerie interne</h1>
    <!-- Message de confirmation d'envoi -->
    <?php if (isset($_GET['envoye'])): ?>
        <p class="success-message"><strong>✅ Message envoyé avec succès.</strong></p>
    <?php endif; ?>

    <div class="messagerie-layout">
        <!-- Colonne de gauche : menu des contacts -->
        <div class="contacts-column">
            <h2>Contacts</h2>
            <?php if (empty($contacts)): ?>
                <p>Aucun utilisateur à afficher.</p>
            <?php else: ?>
                <?php foreach ($contacts as $user): 
                    $cid   = $user['ID'];
                    $name  = htmlspecialchars($user['PRENOM'] . ' ' . $user['NOM']);
                    $unread = isset($unread_counts[$cid]) ? (int)$unread_counts[$cid] : 0;
                ?>
                    <div class="contact<?php 
                            if ($selected_user == $cid) echo ' active'; 
                            if ($unread > 0) echo ' unread'; ?>">
                        <a href="?contact=<?= $cid ?>">
                            <span><?= $name ?></span>
                            <?php if ($unread > 0): ?>
                                <span class="unread-count"><?= $unread ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Colonne centrale : zone de conversation -->
        <div class="conversation-column">
            <?php if ($selected_user): ?>
                <h2>Conversation avec <?= $selected_contact_name ?></h2>
                <div id="conversation-messages">
                    <?php 
                    // Charger les messages de la conversation sélectionnée (pour affichage initial)
                    $stmt = $pdo->prepare("
                        SELECT * 
                        FROM MESSAGE 
                        WHERE (ORIGIN_USER_ID = :uid AND DESTINATION_USER_ID = :contact) 
                           OR (ORIGIN_USER_ID = :contact AND DESTINATION_USER_ID = :uid)
                        ORDER BY DATEHEUR ASC
                    ");
                    $stmt->execute(['uid' => $user_id, 'contact' => $selected_user]);
                    $conversation = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!$conversation) {
                        echo "<p>Aucun message pour le moment.</p>";
                    } else {
                        foreach ($conversation as $m) {
                            $isMe   = ($m['ORIGIN_USER_ID'] == $user_id);
                            $author = $isMe ? 'Vous' : $selected_contact_name;
                            $date   = $m['DATEHEUR'];
                            $status = '';
                            if ($isMe) {
                                $status = $m['LUE'] ? '✅ Vu' : '🕓 Non lu';
                            }
                            $msgHtml = nl2br(htmlspecialchars($m['MESSAGE']));
                            $msgClass = $isMe ? 'sent' : 'received';
                            echo "<div class='message $msgClass'>";
                            echo "<div class='message-content'>$msgHtml</div>";
                            echo "<div class='message-info'>$author - $date";
                            if ($status) {
                                echo " - $status";
                            }
                            echo "</div>";
                            if ($isMe) {
                                $mid = $m['ID'];
                                echo "<a href='?contact=$selected_user&amp;supprimer=$mid' class='delete-link' onclick='return confirm(\"Supprimer ce message ?\")'>🗑 Supprimer</a>";
                            }
                            echo "</div>";
                        }
                    }
                    ?>
                </div>
                <!-- Message d’erreur (par ex. droit d’envoi refusé) -->
                <?php if ($message): ?>
                    <p class="error-message"><?= $message ?></p>
                <?php endif; ?>
                <!-- Formulaire d’envoi d’un nouveau message -->
                <form method="POST" action="messagerie.php?contact=<?= $selected_user ?>">
                    <input type="hidden" name="dest_id" value="<?= $selected_user ?>" />
                    <textarea name="message" placeholder="Votre message..." required></textarea><br />
                    <button type="submit">Envoyer</button>
                </form>
            <?php else: ?>
                <h2>Conversation</h2>
                <p>Sélectionnez un contact à gauche pour afficher la conversation.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bouton retour à l’accueil -->
    <div class="messagerie-actions" style="text-align:center; margin-top: 2rem;">
        <a href="home.php" class="button">🏠 Retour à l’accueil</a>
    </div>
    
    <br>

    <div class="messagerie-actions" style="margin-bottom: 1rem;">
        <a href="messagerie.php" class="button">⬅️ Retour aux messages</a>
    </div>

</div>

<footer>
    © <?= date('Y') ?> [FOOTER_CREDIT] Services. Tous droits réservés.
</footer>

<!-- Script d'actualisation automatique de la conversation -->
<script>
    function refreshConversation() {
        <?php if ($selected_user): ?>
        // Requête AJAX pour récupérer les messages à jour de la conversation courante
        fetch("messagerie.php?contact=<?= $selected_user ?>&ajax=1")
            .then(response => response.text())
            .then(html => {
                const convElem = document.getElementById("conversation-messages");
                if (convElem) {
                    convElem.innerHTML = html;
                    // Faire défiler vers le bas pour voir le dernier message
                    convElem.scrollTop = convElem.scrollHeight;
                }
            });
        <?php endif; ?>
    }
    // Actualiser les messages toutes les 3 secondes
    setInterval(refreshConversation, 3000);
    // Au chargement de la page, descendre tout en bas de la conversation et lancer un premier rafraîchissement
    window.onload = function() {
        const convElem = document.getElementById("conversation-messages");
        if (convElem) {
            convElem.scrollTop = convElem.scrollHeight;
        }
        // Lancer un rafraîchissement initial après un court délai
        setTimeout(refreshConversation, 100);
    };
</script>
</body>
</html>
