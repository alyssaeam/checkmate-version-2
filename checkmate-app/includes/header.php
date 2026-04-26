<?php
/*
 * includes/header.php
 *
 * RULE: This file only outputs HTML.
 * Session, DB, auth checks, and POST handling must ALL be done
 * in the page file BEFORE this file is included.
 *
 * This file defines helper functions wrapped in function_exists
 * so they can be safely called from page files before this include.
 */

// Safety net — session must already be started by page file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Safety net — $pdo must already exist from page file
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/database.php';
}

// ── HELPER FUNCTIONS ──────────────────────────────────────
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id'])
            && !empty($_SESSION['user_id'])
            && isset($_SESSION['role_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isLoggedIn() && (int)$_SESSION['role_id'] === 1;
    }
}

if (!function_exists('isMember')) {
    function isMember() {
        return isLoggedIn() && (int)$_SESSION['role_id'] === 2;
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        if (!isLoggedIn()) {
            header("Location: ../shared/login.php");
            exit();
        }
        if (!isAdmin()) {
            header("Location: ../member/home.php");
            exit();
        }
    }
}

if (!function_exists('requireMember')) {
    function requireMember() {
        if (!isLoggedIn()) {
            header("Location: ../shared/login.php");
            exit();
        }
        if (isAdmin()) {
            header("Location: ../admin/home.php");
            exit();
        }
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

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php require_once __DIR__ . '/../includes/nav.php'; ?>

<div class="topbar" id="topbar">
    <button class="topbar-toggle" id="sidebarToggle"
            aria-label="Toggle menu">
        <i class="bi bi-list"></i>
    </button>
    <span class="topbar-title">Checkmate</span>
</div>

<div class="main-content" id="mainContent">
    <div class="page-content">

<?php endif; ?>