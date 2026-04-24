<?php
/*
 * config/database.php
 * Simple PDO connection. No OTP or must-change-password logic.
 */

$host        = 'localhost';
$dbname      = 'checkmate_db';
$username_db = 'root';
$password_db = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username_db,
        $password_db
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE,         PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}