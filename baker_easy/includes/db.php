<?php
// db.php - Database connection helper

// Set Malaysia timezone globally in PHP
date_default_timezone_set('Asia/Kuala_Lumpur');

// Load configurations
require_once __DIR__ . '/config.php';

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'bakerease_db';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Set Malaysia timezone in MySQL session
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
