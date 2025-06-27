<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;


if (!isset($_SESSION['user_id']) || $_SESSION['user_lvl'] < 1) {
    http_response_code(403);
    exit("Accès refusé.");
}

if (empty($_GET['f'])) {
    http_response_code(400);
    exit("Fichier non spécifié.");
}

$filename = basename($_GET['f']);
$path = __DIR__ . '/assets/secure/' . $filename;

if (!is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(404);
    exit("Fichier introuvable.");
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($path);
exit;

