<?php
/*
 * header.php — Layout shell only.
 *
 * IMPORTANT: Do NOT call session_start() or require database.php here.
 * Every page file that includes this header has already called:
 *   1. session_start()  (or the PHP_SESSION_NONE guard)
 *   2. require_once '../config/database.php'
 *   3. All POST redirect logic
 * THEN it requires this file to begin HTML output.
 *
 * This file only defines helper functions and outputs HTML.
 */

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
// These are used by nav.php for active link highlighting
$current_page  = basename($_SERVER['PHP_SELF']);
$current_dir   = basename(dirname($_SERVER['PHP_SELF']));

// These are used by nav.php for the sidebar footer
$email         = $_SESSION['email']    ?? '';
$username      = $_SESSION['username'] ?? '';
$role_id       = $_SESSION['role_id']  ?? null;

// Confirmation badge count for admin nav
// $pdo must already be available (set by the page file before this include)
$confirm_count = (isAdmin() && isset($pdo))
                 ? getConfirmationCount($pdo)
                 : 0;

// Avatar initials (2 chars, uppercase)
$initials = strtoupper(
    substr($username ?: explode('@', $email)[0], 0, 2)
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkmate</title>
    <!-- Roboto font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap"
          rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          rel="stylesheet">
    <!-- App styles -->
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php if (isLoggedIn()): ?>

<!-- Sidebar overlay (mobile tap-to-close) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php require_once '../includes/nav.php'; ?>

<!-- Mobile topbar -->
<div class="topbar" id="topbar">
    <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle menu">
        <i class="bi bi-list"></i>
    </button>
    <span class="topbar-title">Checkmate</span>
</div>

<!-- Main content area — offset right of sidebar on desktop -->
<div class="main-content" id="mainContent">
    <div class="page-content">

<?php endif;
// If not logged in (e.g. login.php), no sidebar/shell is rendered.
// The auth pages handle their own full-page layout.
?>
