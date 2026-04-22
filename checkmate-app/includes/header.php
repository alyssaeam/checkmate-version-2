<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

function isLoggedIn()  { return isset($_SESSION['user_id']); }
function isAdmin()     { return isLoggedIn() && $_SESSION['role_id'] == 1; }

function requireLogin($redirect = '../shared/login.php') {
    if (!isLoggedIn()) { header("Location: $redirect"); exit(); }
}

function getConfirmationCount($pdo) {
    if (!isAdmin() || !isset($_SESSION['user_id'])) return 0;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tasks
        WHERE created_by = ? AND status_id = 2
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return (int)$stmt->fetchColumn();
}

$user_id       = $_SESSION['user_id']  ?? null;
$role_id       = $_SESSION['role_id']  ?? null;
$email         = $_SESSION['email']    ?? '';
$username      = $_SESSION['username'] ?? '';
$confirm_count = isAdmin() ? getConfirmationCount($pdo) : 0;

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));

$initials = strtoupper(substr($username ?: explode('@', $email)[0], 0, 2));
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

    <!-- Sidebar overlay for mobile tap-to-close -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php require_once '../includes/nav.php'; ?>

    <!-- Mobile topbar (hidden on desktop) -->
    <div class="topbar" id="topbar">
        <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle menu">
            <i class="bi bi-list"></i>
        </button>
        <span class="topbar-title">Checkmate</span>
    </div>

    <!-- Main content area — pushed right of sidebar -->
    <div class="main-content" id="mainContent">
        <div class="page-content">

<?php endif; ?>