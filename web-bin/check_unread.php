<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;



if (!isset($_SESSION['user_id'])) {
    echo 0;
    exit;
}

try {
    $pdo = new PDO('sqlite:assets/SQL/mes-services-db.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM MESSAGE 
        WHERE DESTINATION_USER_ID = :id AND LUE = 0
    ");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    echo intval($stmt->fetchColumn());
} catch (PDOException $e) {
    echo 0;
}
