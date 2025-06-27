<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;


if (!isset($_SESSION['user_id']) || $_SESSION['user_lvl'] < 5) {
    header("Location: login.php");
    exit;
}

try {
    $pdo = new PDO('sqlite:assets/SQL/mes-services-db.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ajout
    if (isset($_POST['nom'], $_POST['prenom'], $_POST['email']) && trim($_POST['nom']) !== '') {
        $stmt = $pdo->prepare("INSERT INTO CONTACT (NOM, PRENOM, EMAIL, CERTIFICAT) VALUES (:nom, :prenom, :email, :cert)");
        $stmt->execute([
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'email' => $_POST['email'],
            'cert' => $_POST['certificat'] ?? null
        ]);
        $message = "✅ Contact ajouté.";
    }

    // Suppression
    if (isset($_GET['supprimer'])) {
        $stmt = $pdo->prepare("DELETE FROM CONTACT WHERE ID = :id");
        $stmt->execute(['id' => (int)$_GET['supprimer']]);
        $message = "🗑 Contact supprimé.";
    }

    $contacts = $pdo->query("SELECT * FROM CONTACT ORDER BY ID DESC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur DB : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Configuration des contacts</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/ico/0.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">


    <style>
        .admin-container { max-width: 800px; margin: auto; padding: 2rem; background: #2a2a2a; border-radius: 10px; }
        input, textarea { width: 100%; margin-bottom: 1rem; padding: 0.6rem; border: none; border-radius: 5px; background: #444; color: #fff; }
        button { padding: 0.6rem 1rem; background: #3498db; border: none; border-radius: 5px; color: #fff; cursor: pointer; }
        button:hover { background: #2980b9; }
        .contact-item { background: #1e1e1e; margin-bottom: 1rem; padding: 1rem; border-radius: 5px; }
        .message { color: #7fff7f; font-weight: bold; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>🔧 Gestion des contacts</h1>

        <?php if (isset($message)): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <h2>Ajouter un contact</h2>
        <form method="POST">
            <input type="text" name="prenom" placeholder="Prénom" required>
            <input type="text" name="nom" placeholder="Nom" required>
            <input type="email" name="email" placeholder="Email" required>
            <textarea name="certificat" placeholder="Certificat (optionnel)"></textarea>
            <button type="submit">Ajouter</button>
        </form>

        <h2>📋 Contacts existants</h2>
        <?php foreach ($contacts as $c): ?>
            <div class="contact-item">
                <strong><?= htmlspecialchars($c['PRENOM'] . ' ' . $c['NOM']) ?></strong><br>
                Email : <?= htmlspecialchars($c['EMAIL']) ?><br>
                <?php if ($c['CERTIFICAT']): ?>
                    Certificat : <pre><?= htmlspecialchars($c['CERTIFICAT']) ?></pre>
                <?php endif; ?>
                <form method="get" style="display:inline" onsubmit="return confirm('Supprimer ce contact ?')">
                    <input type="hidden" name="supprimer" value="<?= $c['ID'] ?>">
                    <button type="submit" class="btn-danger">🗑 Supprimer</button>
                </form>

            </div>
        <?php endforeach; ?>

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
