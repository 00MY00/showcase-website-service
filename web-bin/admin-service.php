<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;


if (!isset($_SESSION['user_id']) || $_SESSION['user_lvl'] < 10) {
    header('Location: login.php');
    exit;
}

try {
    $pdo = new PDO('sqlite:assets/SQL/mes-services-db.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $message = '';


    $service_edition = null;

    // Chargement d’un service existant à modifier
    if (isset($_GET['edit'])) {
        $stmt = $pdo->prepare("SELECT * FROM SERVICE WHERE ID = ?");
        $stmt->execute([intval($_GET['edit'])]);
        $service_edition = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Mise à jour d’un service
    if (isset($_POST['update']) && isset($_POST['id'])) {
        $ordre = intval($_POST['ordre']);

        // Vérifie si un autre service a déjà ce même ordre
        $check = $pdo->prepare("SELECT COUNT(*) FROM SERVICE WHERE ORDRE = ? AND ID != ?");
        $check->execute([$ordre, $_POST['id']]);
        if ($check->fetchColumn() > 0) {
            $message = "⚠️ Un autre service utilise déjà l'ordre $ordre.";
        } else {
            $stmt = $pdo->prepare("UPDATE SERVICE SET NOM = :nom, DESCRIPTION = :desc, IMAGE = :img,
                                ACTIF = :actif, VISIBILITE_MIN = :vis, ORDRE = :ordre WHERE ID = :id");
            $stmt->execute([
                'nom' => trim($_POST['nom']),
                'desc' => trim($_POST['description']),
                'img' => 'assets/img/' . trim($_POST['image'] ?? ''),
                'actif' => isset($_POST['actif']) ? 1 : 0,
                'vis' => intval($_POST['visibilite_min'] ?? 0),
                'ordre' => $ordre,
                'id' => intval($_POST['id'])
            ]);
            $message = "✏️ Service modifié.";
        }
    }











    // Liste des images disponibles dans assets/img/
    $images_disponibles = array_filter(scandir('assets/img'), function($f) {
        return preg_match('/\.(png|jpg|jpeg|gif|svg)$/i', $f);
    });

    // Création d’un nouveau service
    if (isset($_POST['nom'], $_POST['description'], $_POST['ordre']) && trim($_POST['nom']) !== '') {
        $ordre = intval($_POST['ordre']);

        // Vérifier si l'ordre existe déjà
        $check = $pdo->prepare("SELECT COUNT(*) FROM SERVICE WHERE ORDRE = ?");
        $check->execute([$ordre]);
        if ($check->fetchColumn() > 0) {
            $message = "⚠️ Un service utilise déjà l'ordre $ordre. Choisis-en un autre.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO SERVICE (NOM, DESCRIPTION, IMAGE, ACTIF, VISIBILITE_MIN, ORDRE)
                                VALUES (:nom, :desc, :img, 1, :vis, :ordre)");
            $stmt->execute([
                'nom' => trim($_POST['nom']),
                'desc' => trim($_POST['description']),
                'img' => 'assets/img/' . trim($_POST['image'] ?? ''),
                'vis' => intval($_POST['visibilite_min'] ?? 0),
                'ordre' => $ordre
            ]);
            $message = "✅ Service ajouté.";
        }
    }


    // Suppression d’un service
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $pdo->prepare("DELETE FROM SERVICE WHERE ID = :id")->execute(['id' => $id]);
        $message = "🗑 Service supprimé.";
    }

    // Activer / Désactiver un service
    if (isset($_GET['toggle'])) {
        $id = intval($_GET['toggle']);
        $stmt = $pdo->prepare("SELECT ACTIF FROM SERVICE WHERE ID = :id");
        $stmt->execute(['id' => $id]);
        $actif = $stmt->fetchColumn();
        $pdo->prepare("UPDATE SERVICE SET ACTIF = :nv WHERE ID = :id")->execute([
            'nv' => $actif ? 0 : 1,
            'id' => $id
        ]);
        $message = $actif ? "🔴 Service désactivé." : "🟢 Service activé.";
    }

    // Liste des services
    $services = $pdo->query("SELECT * FROM SERVICE ORDER BY ORDRE ASC")->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    die("Erreur DB : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Services</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/ico/0.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">


</head>
<body>
<div class="admin-container">
    <h1>Gestion des Services</h1>

    <?php if ($message): ?>
        <p class="success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <h2>Ajouter un service</h2>
    <h2><?= $service_edition ? 'Modifier un service' : 'Ajouter un service' ?></h2>
    <form method="POST">
        <input type="hidden" name="<?= $service_edition ? 'update' : '' ?>">
        <input type="hidden" name="id" value="<?= $service_edition['ID'] ?? '' ?>">

        <input type="text" name="nom" placeholder="Nom du service" required
            value="<?= htmlspecialchars($service_edition['NOM'] ?? '') ?>">

        <textarea name="description" placeholder="Description du service" rows="3"><?= htmlspecialchars($service_edition['DESCRIPTION'] ?? '') ?></textarea>

        <select name="image" id="image-select">
            <option value="">-- Aucune image --</option>
            <?php foreach ($images_disponibles as $img): ?>
                <option value="<?= htmlspecialchars($img) ?>" <?= (isset($service_edition['IMAGE']) && basename($service_edition['IMAGE']) === $img) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($img) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div id="preview-container" style="margin-top:10px;">
            <img id="preview-image"
                src="<?= isset($service_edition['IMAGE']) ? htmlspecialchars($service_edition['IMAGE']) : '' ?>"
                alt="Aperçu de l'image"
                style="<?= isset($service_edition['IMAGE']) ? '' : 'display:none;' ?> max-height:100px; border:1px solid #ccc; padding:5px;">
        </div>

        <label for="visibilite">Niveau minimum requis :</label>
        <select name="visibilite_min" id="visibilite" required>
            <?php for ($i = 0; $i <= 10; $i++): ?>
                <option value="<?= $i ?>" <?= (isset($service_edition['VISIBILITE_MIN']) && $service_edition['VISIBILITE_MIN'] == $i) ? 'selected' : '' ?>>
                    <?= $i ?>
                </option>
            <?php endfor; ?>
        </select>

        <label for="ordre">Ordre d'affichage :</label>
        <input type="number" name="ordre" min="0" required
            value="<?= htmlspecialchars($service_edition['ORDRE'] ?? 0) ?>">

        <label><input type="checkbox" name="actif" <?= (!isset($service_edition) || $service_edition['ACTIF']) ? 'checked' : '' ?>> Actif</label>

        <button type="submit"><?= $service_edition ? 'Mettre à jour' : 'Ajouter' ?></button>
    </form>


    <h2>Services existants</h2>
    <table>
        <thead>
            <tr><th>Nom</th><th>Ordre</th><th>Description</th><th>Statut</th><th>Visibilité</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($s['NOM']) ?></strong><br>
                        <?php if (!empty($s['IMAGE'])): ?>
                            <img src="<?= htmlspecialchars($s['IMAGE']) ?>" alt="Image" style="max-height:50px; margin-top:5px;">
                        <?php endif; ?>
                    </td>
                    <td><?= intval($s['ORDRE']) ?></td>
                    <td><?= nl2br(htmlspecialchars($s['DESCRIPTION'])) ?></td>
                    <td>≥ <?= intval($s['VISIBILITE_MIN']) ?></td>

                    <td>
                        <?= $s['ACTIF'] ? '🟢 Actif' : '🔴 Inactif' ?>
                    </td>
                    <td>
                        <a href="?toggle=<?= $s['ID'] ?>"><?= $s['ACTIF'] ? 'Désactiver' : 'Activer' ?></a> |
                        <a href="?edit=<?= $s['ID'] ?>">✏️ Modifier</a> |
                        <a href="?delete=<?= $s['ID'] ?>" class="supprimer" onclick="return confirm('Supprimer ce service ?')">🗑 Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

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





<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('image-select');
    const preview = document.getElementById('preview-image');

    select.addEventListener('change', function () {
        const file = select.value;
        if (file) {
            preview.src = 'assets/img/' + file;
            preview.style.display = 'block';
        } else {
            preview.src = '';
            preview.style.display = 'none';
        }
    });
});
</script>



</body>
</html>
