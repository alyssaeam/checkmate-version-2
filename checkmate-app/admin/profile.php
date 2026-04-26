<?php
// ── SECURITY ──────────────────────────────────────────────
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php"); exit();
}
if ((int)$_SESSION['role_id'] !== 1) {
    header("Location: ../member/home.php"); exit();
}
// ─────────────────────────────────────────────────────────

// ... all POST handling and data loading ...

$user_id = (int)$_SESSION['user_id'];
$stmt    = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_name') {
        $username = trim($_POST['username'] ?? '');
        if (!empty($username)) {
            $pdo->prepare("UPDATE users SET username=? WHERE id=?")
                ->execute([$username, $user_id]);
            $_SESSION['username'] = $username;
            $user['username']     = $username;
            $success = 'Display name updated.';
        }
    }
    if ($action === 'update_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($new,PASSWORD_BCRYPT),$user_id]);
            $success = 'Password updated successfully.';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <h1 class="page-title mb-4">Profile</h1>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4">
            <i class="bi bi-check-circle me-2"></i>
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="bi bi-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="cm-card mb-4">
            <div class="cm-card-header">
                <div class="cm-card-title">Account Settings</div>
            </div>
            <div class="cm-card-body">
                <div class="d-flex align-items-center gap-3 mb-4 p-3"
                     style="background:#f8f9fc;
                            border-radius:var(--radius-sm)">
                    <div class="sidebar-avatar"
                         style="width:44px;height:44px;font-size:15px">
                        <?php echo strtoupper(substr(
                            $user['username']??$user['email'],0,2)); ?>
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:14px">
                            <?php echo htmlspecialchars(
                                $user['username']
                                ??explode('@',$user['email'])[0]); ?>
                        </div>
                        <div class="text-muted" style="font-size:12px">
                            <?php echo htmlspecialchars($user['email']); ?>
                        </div>
                        <span class="cm-tag cm-tag-primary mt-1">
                            Administrator
                        </span>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Display Name</label>
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="action"
                               value="update_name">
                        <input type="text" name="username"
                               class="form-control"
                               value="<?php echo htmlspecialchars(
                                   $user['username']??''); ?>"
                               required>
                        <button type="submit"
                                class="cm-btn cm-btn-primary">
                            Save
                        </button>
                    </form>
                </div>
                <hr style="margin:20px 0">
                <div class="cm-card-title mb-3">Change Password</div>
                <form method="POST">
                    <input type="hidden" name="action"
                           value="update_password">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password"
                               class="form-control"
                               placeholder="Current password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password"
                               class="form-control"
                               placeholder="Min 6 characters" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password"
                               class="form-control"
                               placeholder="Repeat password" required>
                    </div>
                    <button type="submit"
                            class="cm-btn cm-btn-primary w-100">
                        <i class="bi bi-lock me-1"></i>Update Password
                    </button>
                </form>
            </div>
        </div>

        <div class="cm-card">
            <div class="cm-card-body">
                <div class="d-flex align-items-center
                             justify-content-between">
                    <div>
                        <div style="font-size:13px;font-weight:600;
                                    color:var(--text-main)">
                            Sign Out
                        </div>
                        <div style="font-size:12px;color:var(--text-muted)">
                            Redirects to the home screen
                        </div>
                    </div>
                    <a href="../shared/logout.php"
                       class="cm-btn cm-btn-danger"
                       onclick="return confirm('Log out?')">
                        <i class="bi bi-box-arrow-right me-1"></i>
                        Log Out
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>