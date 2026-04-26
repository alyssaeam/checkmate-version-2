<?php
/*
 * config/database.php
 * InfinityFree production credentials
 */

$host        = 'sql102.infinityfree.com';
$dbname      = 'if0_41756542_checkmate_dbv2';
$username_db = 'if0_41756542';
$password_db = 'v2checkmate2026';
$port        = '3306';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username_db,
        $password_db,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 10,
        ]
    );
} catch (PDOException $e) {
    $is_local = (
        $_SERVER['REMOTE_ADDR'] === '127.0.0.1' ||
        $_SERVER['REMOTE_ADDR'] === '::1'
    );
    if ($is_local) {
        die("<pre>DB Error (local): " . $e->getMessage() . "</pre>");
    }
    die("Service temporarily unavailable. Please try again later.");
}