<?php
/*
 * includes/header.php
 *
 * RULE: This file outputs HTML. Always include it AFTER
 * all POST logic and redirects in the page file.
 *
 * Session guard: safe to call whether or not session
 * was already started by the page file.
 *
 * Database: loaded via __DIR__ so the path resolves
 * correctly from any subdirectory.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    require_once __DIR__ . '/../config/database.php';
}

// ── HELPER FUNCTIONS ──────────────────────────────────────
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isLoggedIn() && (int)$_SESSION['role_id'] === 1;
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin($redirect = '../shared/login.php') {
        if (!isLoggedIn()) {
            header("Location: $redirect");
            exit();
        }
    }
}

if (!function_exists('getConfirmationCount')) {
    function getConfirmationCount($pdo) {
        if (!isAdmin() || empty($_SESSION['user_id'])) return 0;
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM tasks
            WHERE created_by = ? AND status_id = 2
        ");
        $stmt->execute([(int)$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn();
    }
}

// ── PAGE VARIABLES ─────────────────────────────────────────
$current_page  = basename($_SERVER['PHP_SELF']);
$current_dir   = basename(dirname($_SERVER['PHP_SELF']));
$email         = $_SESSION['email']    ?? '';
$username      = $_SESSION['username'] ?? '';
$role_id       = $_SESSION['role_id']  ?? null;
$confirm_count = (isAdmin() && isset($pdo))
                 ? getConfirmationCount($pdo)
                 : 0;
$initials      = strtoupper(
    substr($username ?: explode('@', $email)[0], 0, 2)
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkmate</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php if (isLoggedIn()): ?>

<!-- Sidebar overlay (mobile tap-to-close) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar (desktop) -->
<?php require_once __DIR__ . '/../includes/nav.php'; ?>

<!-- Main content — offset right of sidebar on desktop,
     full width on mobile with bottom nav -->
<div class="main-content" id="mainContent">
    <div class="page-content">

<?php endif; ?>