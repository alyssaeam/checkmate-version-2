<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php"); exit();
}
if ((int)$_SESSION['role_id'] !== 1) {
    header("Location: ../member/home.php"); exit();
}

$admin_id = (int)$_SESSION['user_id'];
$search   = trim($_GET['search'] ?? '');

// All POST before HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $username     = trim($_POST['new_username'] ?? '');
    $email        = trim($_POST['new_email']    ?? '');
    $tmp_password = password_hash('checkmate123', PASSWORD_BCRYPT);
    $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $check->execute([$email]);
    if ($check->fetch()) {
        header("Location: users.php?error=email_exists"); exit();
    }
    $pdo->prepare("
        INSERT INTO users
            (username,email,password,role_id,must_change_password)
        VALUES (?,?,?,2,1)
    ")->execute([$username,$email,$tmp_password]);
    header("Location: users.php?success=member_added"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['reset_password'])) {
    $uid = (int)$_POST['user_id'];
    if ($uid !== $admin_id) {
        $tmp = password_hash('checkmate123', PASSWORD_BCRYPT);
        $pdo->prepare("
            UPDATE users SET password=?, must_change_password=1
            WHERE id=? AND role_id=2
        ")->execute([$tmp, $uid]);
    }
    header("Location: users.php?success=password_reset&uid=$uid");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['remove_member'])) {
    $uid  = (int)$_POST['user_id'];
    $pass = $_POST['admin_password'] ?? '';
    if ($uid === $admin_id) {
        header("Location: users.php?error=cannot_remove_self"); exit();
    }
    $row = $pdo->prepare("SELECT password FROM users WHERE id=?");
    $row->execute([$admin_id]);
    $row = $row->fetch();
    if (!password_verify($pass,$row['password'])) {
        header("Location: users.php?error=wrong_password&uid=$uid"); exit();
    }
    $pdo->prepare("UPDATE tasks SET user_id=NULL WHERE user_id=?")
        ->execute([$uid]);
    $pdo->prepare("DELETE FROM tasks WHERE created_by=? AND is_private=1")
        ->execute([$uid]);
    $pdo->prepare("DELETE FROM task_categories WHERE user_id=?")
        ->execute([$uid]);
    $pdo->prepare("DELETE FROM users WHERE id=? AND role_id=2")
        ->execute([$uid]);
    header("Location: users.php?success=member_removed"); exit();
}

// Load data
if ($search !== '') {
    $us = $pdo->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM tasks WHERE user_id=u.id) AS task_count
        FROM users u
        WHERE u.role_id=2 AND u.id!=?
          AND (u.username LIKE ? OR u.email LIKE ?)
        ORDER BY u.username ASC, u.email ASC
    ");
    $us->execute([$admin_id,"%$search%","%$search%"]);
} else {
    $us = $pdo->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM tasks WHERE user_id=u.id) AS task_count
        FROM users u
        WHERE u.role_id=2 AND u.id!=?
        ORDER BY u.username ASC, u.email ASC
    ");
    $us->execute([$admin_id]);
}
$users = $us->fetchAll();

$view_uid   = isset($_GET['uid']) ? (int)$_GET['uid'] : null;
$view_user  = null;
$user_tasks = [];

if ($view_uid) {
    $s = $pdo->prepare("
        SELECT u.*, r.name AS role_name
        FROM users u JOIN roles r ON u.role_id=r.id
        WHERE u.id=?
    ");
    $s->execute([$view_uid]);
    $view_user = $s->fetch();

    if ($view_user) {
        $ut = $pdo->prepare("
            SELECT t.*, ts.name AS status
            FROM tasks t
            LEFT JOIN task_status ts ON t.status_id=ts.id
            WHERE t.user_id=?
            ORDER BY
                CASE WHEN t.status_id=2 THEN 0 ELSE 1 END,
                t.due_datetime ASC
        ");
        $ut->execute([$view_uid]);
        $user_tasks = $ut->fetchAll();
    }
}

$member_count = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE role_id=2"
)->fetchColumn();

// HTML starts here
require_once '../includes/header.php';
?>

<div class="d-flex align-items-center
            justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h1 class="page-title">Users</h1>
        <p class="page-subtitle">
            <?php echo $member_count; ?> member<?php
                echo $member_count!==1?'s':''; ?>
        </p>
    </div>
    <button class="cm-btn cm-btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#addMemberModal">
        <i class="bi bi-person-plus me-1"></i>Add Member
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-4">
    <i class="bi bi-check-circle me-2"></i>
    <?php echo match($_GET['success']){
        'member_added'   =>'Member added. Temporary password: <strong>checkmate123</strong>',
        'member_removed' =>'Member removed.',
        'password_reset' =>'Password reset to <strong>checkmate123</strong>. Member must change on next login.',
        default          =>'Done.'
    }; ?>
    <button type="button" class="btn-close"
            data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show mb-4">
    <i class="bi bi-exclamation-circle me-2"></i>
    <?php echo match($_GET['error']){
        'wrong_password'     =>'Incorrect admin password.',
        'email_exists'       =>'Email already registered.',
        'cannot_remove_self' =>'Cannot remove your own account.',
        default              =>'An error occurred.'
    }; ?>
    <button type="button" class="btn-close"
            data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="cm-card"
             style="display:flex;flex-direction:column;height:100%">
            <div class="cm-card-header" style="flex-shrink:0">
                <span class="cm-card-title">All Members</span>
            </div>
            <div style="padding:10px 14px;
                        border-bottom:1px solid var(--border);
                        flex-shrink:0">
                <form method="GET"
                      style="display:flex;gap:6px;align-items:center">
                    <?php if ($view_uid): ?>
                    <input type="hidden" name="uid"
                           value="<?php echo (int)$view_uid; ?>">
                    <?php endif; ?>
                    <input type="text" name="search"
                           class="form-control form-control-sm"
                           placeholder="Search…"
                           value="<?php echo htmlspecialchars($search); ?>">
                    <?php if ($search): ?>
                    <a href="users.php<?php
                        echo $view_uid?'?uid='.(int)$view_uid:''; ?>"
                       class="cm-icon-btn" title="Clear">
                        <i class="bi bi-x-lg"></i>
                    </a>
                    <?php endif; ?>
                    <button type="submit" class="cm-icon-btn" title="Search">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>
            <div style="flex:1;overflow-y:auto;max-height:480px">
                <?php if (empty($users)): ?>
                <div class="cm-empty-state">
                    <i class="bi bi-people cm-empty-icon"></i>
                    <p class="cm-empty-text">
                        <?php echo $search
                            ?'No results for "'.htmlspecialchars($search).'"'
                            :'No members yet'; ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="cm-list">
                    <?php foreach ($users as $u):
                        $uname=($u['username']??explode('@',$u['email'])[0]);
                        $init=strtoupper(substr($uname,0,2));
                        $is_sel=((int)$view_uid===(int)$u['id']);
                    ?>
                    <a href="users.php?uid=<?php echo (int)$u['id'];
                        echo $search?'&search='.urlencode($search):''; ?>"
                       class="cm-list-item <?php
                           echo $is_sel?'cm-list-item-active':''; ?>">
                        <div class="cm-list-avatar">
                            <?php echo htmlspecialchars($init); ?>
                        </div>
                        <div class="cm-list-item-body">
                            <div class="cm-list-item-title">
                                <?php echo htmlspecialchars($uname); ?>
                            </div>
                            <div class="cm-list-item-meta">
                                <?php echo htmlspecialchars($u['email']); ?>
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;
                                    align-items:flex-end;gap:3px">
                            <span class="cm-tag cm-tag-neutral"
                                  style="font-size:10px">
                                <?php echo (int)$u['task_count']; ?> tasks
                            </span>
                            <?php if ((int)$u['must_change_password']): ?>
                            <span class="cm-tag cm-tag-warning"
                                  style="font-size:9px">
                                Temp pwd
                            </span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if ($view_user):
            $vname=($view_user['username']??explode('@',$view_user['email'])[0]);
            $vinit=strtoupper(substr($vname,0,2));
            $must=(int)($view_user['must_change_password']??0);
        ?>
        <div class="cm-card">
            <div class="cm-card-header">
                <div style="display:flex;align-items:center;
                             gap:12px;flex:1;min-width:0">
                    <div class="sidebar-avatar"
                         style="width:40px;height:40px;
                                font-size:14px;flex-shrink:0">
                        <?php echo htmlspecialchars($vinit); ?>
                    </div>
                    <div style="min-width:0">
                        <div class="cm-card-title">
                            <?php echo htmlspecialchars($vname); ?>
                        </div>
                        <div class="cm-card-subtitle">
                            <?php echo htmlspecialchars($view_user['email']); ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:6px">
                    <span class="cm-tag cm-tag-success">Member</span>
                    <?php if ($must): ?>
                    <span class="cm-tag cm-tag-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Temp Password
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="cm-card-body">

                <div class="cm-notice cm-notice-info mb-4"
                     style="border-color:#93c5fd">
                    <div class="cm-notice-body">
                        <i class="bi bi-key cm-notice-icon"
                           style="color:var(--info);font-size:18px;
                                  flex-shrink:0"></i>
                        <div>
                            <div class="cm-notice-title">
                                Reset Member Password
                            </div>
                            <div class="cm-notice-text">
                                Resets to
                                <code style="background:#e0e7ff;
                                             padding:1px 6px;
                                             border-radius:3px;
                                             font-size:11px">
                                    checkmate123
                                </code>
                                and flags account to require change on
                                next login.
                            </div>
                        </div>
                    </div>
                    <form method="POST" class="mt-3"
                          data-confirm="Reset password for <?php
                              echo htmlspecialchars(addslashes($vname)); ?>?"
                          data-confirm-type="warning">
                        <input type="hidden" name="reset_password" value="1">
                        <input type="hidden" name="user_id"
                               value="<?php echo (int)$view_user['id']; ?>">
                        <button type="submit"
                                class="cm-btn cm-btn-ghost cm-btn-sm">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>
                            Reset to Default Password
                        </button>
                    </form>
                </div>

                <div class="cm-notice cm-notice-danger mb-4">
                    <div class="cm-notice-body">
                        <i class="bi bi-person-x cm-notice-icon"
                           style="color:var(--danger)"></i>
                        <div>
                            <div class="cm-notice-title">Remove Member</div>
                            <div class="cm-notice-text">
                                Permanently removes member, unassigns tasks,
                                deletes private tasks.
                            </div>
                        </div>
                    </div>
                    <form method="POST" class="mt-3"
                          data-confirm="Remove <?php
                              echo htmlspecialchars(addslashes($vname)); ?>?"
                          data-confirm-type="danger">
                        <input type="hidden" name="remove_member" value="1">
                        <input type="hidden" name="user_id"
                               value="<?php echo (int)$view_user['id']; ?>">
                        <div style="display:flex;gap:8px;
                                    align-items:flex-end;flex-wrap:wrap">
                            <input type="password" name="admin_password"
                                   class="form-control form-control-sm"
                                   placeholder="Confirm with admin password"
                                   required
                                   style="max-width:260px;flex:1">
                            <button type="submit"
                                    class="cm-btn cm-btn-danger cm-btn-sm">
                                <i class="bi bi-trash me-1"></i>Remove
                            </button>
                        </div>
                    </form>
                </div>

                <div style="display:flex;align-items:center;
                             justify-content:space-between;
                             margin-bottom:12px">
                    <div class="cm-card-title">Assigned Tasks</div>
                    <span class="cm-tag cm-tag-neutral">
                        <?php echo count($user_tasks); ?>
                    </span>
                </div>

                <?php if (empty($user_tasks)): ?>
                <div class="cm-empty-state" style="padding:20px 0">
                    <i class="bi bi-inbox cm-empty-icon"></i>
                    <p class="cm-empty-text">No tasks assigned yet</p>
                </div>
                <?php else: ?>
                <div class="cm-list"
                     style="max-height:320px;overflow-y:auto;
                            border:1px solid var(--border);
                            border-radius:var(--radius-sm)">
                    <?php foreach ($user_tasks as $t): ?>
                    <div class="cm-list-item">
                        <div class="cm-list-item-body">
                            <div class="cm-list-item-title">
                                <?php echo htmlspecialchars($t['title']); ?>
                            </div>
                            <div class="cm-list-item-meta">
                                <i class="bi bi-clock me-1"></i>
                                <?php echo date('M j, Y H:i',
                                    strtotime($t['due_datetime'])); ?>
                                <?php if (strtotime($t['due_datetime'])<time()
                                          && (int)$t['status_id']<3): ?>
                                <span style="color:var(--danger);
                                             margin-left:6px">Overdue</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;
                                     gap:6px;flex-shrink:0">
                            <span class="cm-status-badge cm-status-<?php
                                echo $t['status']==='Completed'?'done'
                                    :($t['status']==='For Confirmation'
                                        ?'confirm':'pending'); ?>">
                                <?php echo htmlspecialchars($t['status']); ?>
                            </span>
                            <a href="edit_task.php?id=<?php
                                   echo (int)$t['id']; ?>"
                               class="cm-icon-btn" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <div class="cm-card" style="min-height:300px">
            <div class="cm-empty-state" style="min-height:260px">
                <i class="bi bi-person-circle cm-empty-icon"></i>
                <p class="cm-empty-text">
                    Select a member to view details
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"
                       style="color:var(--primary)"></i>
                    Add New Member
                </h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="add_member" value="1">
                    <div class="mb-3">
                        <label class="form-label">Full Name / Username</label>
                        <input type="text" name="new_username"
                               class="form-control" required
                               placeholder="e.g. Maria Perez">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="new_email"
                               class="form-control" required
                               placeholder="member@example.com">
                    </div>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Temporary password:
                        <strong>checkmate123</strong><br>
                        <small>
                            Member will be prompted to change it on first login.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="cm-btn cm-btn-primary">
                        <i class="bi bi-person-plus me-1"></i>Add Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>