<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to correct dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . ((int)$_SESSION['role_id'] === 1
        ? '../admin/home.php'
        : '../member/home.php'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkmate — Task Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          rel="stylesheet">
    <style>
        /* ── Variables ─────────────────────────────────── */
        :root {
            --primary:      #667eea;
            --primary-dark: #5a6fd6;
            --purple:       #764ba2;
            --text-dark:    #1a1a2e;
            --text-muted:   #6b7280;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Roboto', sans-serif;
            background: #fafbff;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── NAV BAR ───────────────────────────────────── */
        .ob-nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 60px;
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(102,126,234,.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            z-index: 100;
        }

        .ob-nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .ob-nav-logo {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ob-nav-logo svg { width: 18px; height: 18px; }

        .ob-nav-name {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .ob-nav-signin {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 20px;
            font-family: 'Roboto', sans-serif;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: background .15s ease, transform .1s ease;
        }

        .ob-nav-signin:hover {
            background: var(--primary-dark);
            color: #fff;
            transform: translateY(-1px);
        }

        /* ── HERO SECTION ──────────────────────────────── */
        .ob-hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 80px 0 60px;
            position: relative;
            overflow: hidden;
        }

        /* Gradient background blob */
        .ob-hero::before {
            content: '';
            position: absolute;
            top: -120px; right: -160px;
            width: 600px; height: 600px;
            background: radial-gradient(circle,
                rgba(102,126,234,.18) 0%,
                rgba(118,75,162,.10) 50%,
                transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .ob-hero::after {
            content: '';
            position: absolute;
            bottom: -80px; left: -120px;
            width: 400px; height: 400px;
            background: radial-gradient(circle,
                rgba(67,233,123,.12) 0%,
                transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .ob-hero-text { position: relative; z-index: 2; }

        .ob-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(102,126,234,.1);
            color: var(--primary);
            border: 1px solid rgba(102,126,234,.25);
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .3px;
            margin-bottom: 24px;
        }

        .ob-badge-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--primary);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: .5; transform: scale(1.4); }
        }

        .ob-headline {
            font-size: clamp(2rem, 5vw, 3.4rem);
            font-weight: 900;
            color: var(--text-dark);
            line-height: 1.15;
            margin-bottom: 20px;
            letter-spacing: -.5px;
        }

        .ob-headline .highlight {
            background: linear-gradient(135deg, var(--primary), var(--purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .ob-subtext {
            font-size: 16px;
            font-weight: 400;
            color: var(--text-muted);
            line-height: 1.7;
            max-width: 480px;
            margin-bottom: 36px;
        }

        .ob-cta-group {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 48px;
        }

        .ob-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px 28px;
            font-family: 'Roboto', sans-serif;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: transform .15s ease, box-shadow .15s ease;
            box-shadow: 0 6px 24px rgba(102,126,234,.35);
        }

        .ob-btn-primary:hover {
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 32px rgba(102,126,234,.45);
        }

        .ob-btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: color .15s;
        }

        .ob-btn-secondary:hover { color: var(--primary); }

        /* Feature pills */
        .ob-features {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .ob-feature-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #fff;
            border: 1px solid rgba(102,126,234,.18);
            border-radius: 24px;
            padding: 7px 14px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-dark);
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }

        .ob-feature-pill i { color: var(--primary); font-size: 13px; }

        /* ── ILLUSTRATION AREA ─────────────────────────── */
        .ob-illustration {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Main card */
        .ob-card-main {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 24px 64px rgba(102,126,234,.18),
                        0 4px 16px rgba(0,0,0,.06);
            padding: 24px;
            width: 340px;
            animation: floatUp 3.5s ease-in-out infinite;
        }

        @keyframes floatUp {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-10px); }
        }

        .ob-card-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .ob-card-title-text {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .ob-card-date {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Progress circle */
        .ob-progress-ring {
            position: relative;
            width: 56px; height: 56px;
            margin: 0 auto 18px;
        }

        .ob-progress-ring svg { transform: rotate(-90deg); }

        .ob-progress-ring-text {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
        }

        /* Task row in illustration */
        .ob-task-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            margin-bottom: 6px;
            background: #f8f9fc;
        }

        .ob-task-row:last-child { margin-bottom: 0; }

        .ob-task-check {
            width: 18px; height: 18px;
            border-radius: 50%;
            border: 2px solid #d1d5db;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ob-task-check.done {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
            border-color: transparent;
        }

        .ob-task-check.done::after {
            content: '';
            width: 5px; height: 8px;
            border: 2px solid #fff;
            border-top: none;
            border-left: none;
            transform: rotate(45deg) translate(-1px, -1px);
        }

        .ob-task-text {
            flex: 1;
            font-size: 12px;
            color: var(--text-dark);
            font-weight: 500;
        }

        .ob-task-text.done { text-decoration: line-through; color: #9ca3af; }

        .ob-task-badge {
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 10px;
            font-weight: 600;
        }

        .ob-task-badge-green  { background: #d1fae5; color: #065f46; }
        .ob-task-badge-orange { background: #fff3cd; color: #856404; }
        .ob-task-badge-blue   { background: #dbeafe; color: #1e40af; }

        /* Floating mini cards */
        .ob-float-card {
            position: absolute;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 28px rgba(0,0,0,.1);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-dark);
            animation: floatAlt 4s ease-in-out infinite;
        }

        @keyframes floatAlt {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-6px); }
        }

        .ob-float-card-1 {
            top: -24px; left: -40px;
            animation-delay: .8s;
        }

        .ob-float-card-2 {
            bottom: 10px; right: -36px;
            animation-delay: 1.6s;
        }

        .ob-float-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        /* ── STATS ROW ─────────────────────────────────── */
        .ob-stats {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .ob-stat {
            display: flex;
            flex-direction: column;
        }

        .ob-stat-value {
            font-size: 28px;
            font-weight: 900;
            color: var(--text-dark);
            line-height: 1;
        }

        .ob-stat-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .ob-stat-divider {
            width: 1px;
            background: #e5e7eb;
            align-self: stretch;
        }

        /* ── FOOTER ────────────────────────────────────── */
        .ob-footer {
            background: #fff;
            border-top: 1px solid #f1f2f6;
            padding: 20px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .ob-footer-text {
            font-size: 12px;
            color: #9ca3af;
        }

        .ob-footer-links {
            display: flex;
            gap: 20px;
        }

        .ob-footer-links a {
            font-size: 12px;
            color: #9ca3af;
            text-decoration: none;
        }

        .ob-footer-links a:hover { color: var(--primary); }

        /* ── RESPONSIVE ────────────────────────────────── */
        @media (max-width: 991.98px) {
            .ob-nav { padding: 0 20px; }
            .ob-hero { padding: 90px 0 40px; text-align: center; }
            .ob-subtext { margin-left: auto; margin-right: auto; }
            .ob-cta-group { justify-content: center; }
            .ob-features { justify-content: center; }
            .ob-stats { justify-content: center; }
            .ob-illustration { margin-top: 48px; }
            .ob-float-card-1 { top: -16px; left: -10px; }
            .ob-float-card-2 { bottom: 0;  right: -10px; }
        }

        @media (max-width: 575.98px) {
            .ob-nav { padding: 0 16px; }
            .ob-headline { font-size: 2rem; }
            .ob-card-main { width: 290px; }
            .ob-footer { flex-direction: column; align-items: center; text-align: center; }
        }
    </style>
</head>
<body>

<!-- ── NAVBAR ──────────────────────────────────────── -->
<nav class="ob-nav">
    <a href="index.php" class="ob-nav-brand">
        <div class="ob-nav-logo">
            <svg viewBox="0 0 24 24">
                <rect x="3" y="3" width="18" height="18" rx="3"
                      fill="none" stroke="#fff" stroke-width="2"/>
                <path d="M7 12.5l3.5 3.5L17 9"
                      stroke="#fff" stroke-width="2.2"
                      stroke-linecap="round" stroke-linejoin="round"
                      fill="none"/>
            </svg>
        </div>
        <span class="ob-nav-name">Checkmate</span>
    </a>
    <a href="login.php" class="ob-nav-signin">
        <i class="bi bi-box-arrow-in-right"></i>
        Sign In
    </a>
</nav>

<!-- ── HERO ────────────────────────────────────────── -->
<section class="ob-hero">
    <div class="container">
        <div class="row align-items-center g-5">

            <!-- Left: Text -->
            <div class="col-lg-6 ob-hero-text">

                <div class="ob-badge">
                    <span class="ob-badge-dot"></span>
                    Task Management System
                </div>

                <h1 class="ob-headline">
                    Manage all your<br>
                    daily tasks with
                    <span class="highlight">Checkmate</span>
                </h1>

                <p class="ob-subtext">
                    Stay organized, hit deadlines, and collaborate
                    effortlessly. Checkmate brings your team's tasks
                    into one clean, focused workspace.
                </p>

                <div class="ob-cta-group">
                    <a href="login.php" class="ob-btn-primary">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Get Started — Sign In
                    </a>
                    <a href="#features" class="ob-btn-secondary">
                        Learn more
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>

                <!-- Stats -->
                <div class="ob-stats mb-4">
                    <div class="ob-stat">
                        <span class="ob-stat-value">2</span>
                        <span class="ob-stat-label">Roles</span>
                    </div>
                    <div class="ob-stat-divider"></div>
                    <div class="ob-stat">
                        <span class="ob-stat-value">100%</span>
                        <span class="ob-stat-label">Web-based</span>
                    </div>
                    <div class="ob-stat-divider"></div>
                    <div class="ob-stat">
                        <span class="ob-stat-value">∞</span>
                        <span class="ob-stat-label">Tasks</span>
                    </div>
                </div>

                <!-- Feature pills -->
                <div class="ob-features" id="features">
                    <span class="ob-feature-pill">
                        <i class="bi bi-check2-square"></i>
                        Task Assignment
                    </span>
                    <span class="ob-feature-pill">
                        <i class="bi bi-calendar3"></i>
                        Calendar View
                    </span>
                    <span class="ob-feature-pill">
                        <i class="bi bi-people"></i>
                        Team Management
                    </span>
                    <span class="ob-feature-pill">
                        <i class="bi bi-tag"></i>
                        Categories
                    </span>
                    <span class="ob-feature-pill">
                        <i class="bi bi-shield-check"></i>
                        Role-based Access
                    </span>
                    <span class="ob-feature-pill">
                        <i class="bi bi-phone"></i>
                        Mobile Friendly
                    </span>
                </div>

            </div>

            <!-- Right: Illustration -->
            <div class="col-lg-6 ob-illustration">

                <div style="position:relative; display:inline-block;">

                    <!-- Floating card 1: Team members -->
                    <div class="ob-float-card ob-float-card-1">
                        <div class="ob-float-icon"
                             style="background:linear-gradient(135deg,#667eea,#764ba2)">
                            <i class="bi bi-people" style="color:#fff"></i>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:700;
                                        color:#1a1a2e">
                                Team Tasks
                            </div>
                            <div style="font-size:10px;color:#6b7280">
                                3 members active
                            </div>
                        </div>
                    </div>

                    <!-- Main task card -->
                    <div class="ob-card-main">

                        <div class="ob-card-header-row">
                            <div>
                                <div class="ob-card-title-text">
                                    Today's Tasks
                                </div>
                                <div class="ob-card-date">
                                    <?php echo date('F j, Y'); ?>
                                </div>
                            </div>
                            <!-- Progress ring -->
                            <div class="ob-progress-ring">
                                <svg width="56" height="56"
                                     viewBox="0 0 56 56">
                                    <circle cx="28" cy="28" r="22"
                                            fill="none" stroke="#f1f2f6"
                                            stroke-width="4"/>
                                    <circle cx="28" cy="28" r="22"
                                            fill="none" stroke="#667eea"
                                            stroke-width="4"
                                            stroke-dasharray="138"
                                            stroke-dashoffset="48"
                                            stroke-linecap="round"/>
                                </svg>
                                <div class="ob-progress-ring-text">65%</div>
                            </div>
                        </div>

                        <!-- Task rows -->
                        <div class="ob-task-row">
                            <div class="ob-task-check done"></div>
                            <span class="ob-task-text done">
                                Review quarterly report
                            </span>
                            <span class="ob-task-badge ob-task-badge-green">
                                Done
                            </span>
                        </div>

                        <div class="ob-task-row">
                            <div class="ob-task-check done"></div>
                            <span class="ob-task-text done">
                                Update project timeline
                            </span>
                            <span class="ob-task-badge ob-task-badge-green">
                                Done
                            </span>
                        </div>

                        <div class="ob-task-row">
                            <div class="ob-task-check"></div>
                            <span class="ob-task-text">
                                Design mockup review
                            </span>
                            <span class="ob-task-badge ob-task-badge-orange">
                                Pending
                            </span>
                        </div>

                        <div class="ob-task-row">
                            <div class="ob-task-check"></div>
                            <span class="ob-task-text">
                                Send weekly summary
                            </span>
                            <span class="ob-task-badge ob-task-badge-blue">
                                Assigned
                            </span>
                        </div>

                        <!-- Bottom summary -->
                        <div style="margin-top:16px;padding-top:14px;
                                    border-top:1px solid #f1f2f6;
                                    display:flex;justify-content:space-between;
                                    align-items:center">
                            <div style="font-size:11px;color:#9ca3af">
                                4 tasks · 2 completed
                            </div>
                            <a href="login.php"
                               style="display:inline-flex;align-items:center;
                                      gap:4px;font-size:11px;font-weight:600;
                                      color:#667eea;text-decoration:none">
                                Open App
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Floating card 2: Due reminder -->
                    <div class="ob-float-card ob-float-card-2">
                        <div class="ob-float-icon"
                             style="background:linear-gradient(135deg,#f093fb,#f5576c)">
                            <i class="bi bi-bell" style="color:#fff"></i>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:700;
                                        color:#1a1a2e">
                                Due Today
                            </div>
                            <div style="font-size:10px;color:#6b7280">
                                2 tasks remaining
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- ── FOOTER ───────────────────────────────────────── -->
<footer class="ob-footer">
    <div class="ob-footer-text">
        &copy; <?php echo date('Y'); ?> Checkmate Task Management System
    </div>
    <div class="ob-footer-links">
        <a href="login.php">Sign In</a>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>