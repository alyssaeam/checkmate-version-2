<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role_id'] == 1
        ? '../admin/home.php' : '../member/home.php'));
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['role_id']  = $user['role_id'];
        $_SESSION['email']    = $user['email'];
        $_SESSION['username'] = $user['username']
                                ?? explode('@', $user['email'])[0];
        header("Location: " . ($user['role_id'] == 1
            ? '../admin/home.php' : '../member/home.php'));
        exit();
    } else {
        $error = 'Invalid email or password. Contact your administrator.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkmate — Sign In</title>
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
<body class="auth-bg">

<div class="auth-card">

    <!-- Logo header -->
    <div class="auth-card-header">
        <!-- Same logo as sidebar -->
        <div class="auth-brand-icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"
                 width="28" height="28">
                <rect x="3" y="3" width="18" height="18" rx="3"
                      fill="none" stroke="#fff" stroke-width="2"/>
                <path d="M7 12.5l3.5 3.5L17 9"
                      stroke="#fff" stroke-width="2.2"
                      stroke-linecap="round" stroke-linejoin="round"
                      fill="none"/>
            </svg>
        </div>
        <h1 class="auth-title">Checkmate</h1>
        <p class="auth-subtitle">Task Management System</p>
    </div>

    <div class="auth-card-body">

        <?php if ($error): ?>
        <div class="alert alert-danger mb-4" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="mb-4">
                <label class="form-label" for="loginEmail">Email address</label>
                <input type="email" id="loginEmail" name="email"
                       class="form-control form-control-lg"
                       placeholder="you@example.com"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       autocomplete="email" required>
            </div>

            <div class="mb-5">
                <label class="form-label" for="loginPassword">Password</label>
                <div class="input-group">
                    <input type="password" id="loginPassword" name="password"
                           class="form-control form-control-lg"
                           placeholder="Enter your password"
                           autocomplete="current-password" required>
                    <button type="button"
                            class="btn btn-outline-secondary px-3"
                            onclick="togglePwd()"
                            tabindex="-1"
                            aria-label="Show or hide password">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                Sign In
            </button>
        </form>

        <p class="text-center mt-4 mb-0" style="font-size:12px;color:#9ca3af">
            <i class="bi bi-lock me-1"></i>
            No account? Contact your administrator.
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
    const input = document.getElementById('loginPassword');
    const icon  = document.getElementById('eyeIcon');
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
</body>
</html>