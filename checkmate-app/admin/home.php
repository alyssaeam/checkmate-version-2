<?php
// ══════════════════════════════════════════════════════════
// STEP 1: Session + DB + Auth (ALL before any HTML output)
// ══════════════════════════════════════════════════════════
session_start();
require_once '../config/database.php';
requireAdmin_early();

function requireAdmin_early() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../shared/login.php"); exit();
    }
    if ((int)$_SESSION['role_id'] !== 1) {
        header("Location: ../member/home.php"); exit();
    }
}

// ══════════════════════════════════════════════════════════
// STEP 2: Load data
// ══════════════════════════════════════════════════════════
$admin_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN ts.name='Pending'          THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN ts.name='For Confirmation' THEN 1 ELSE 0 END) AS confirmation,
        SUM(CASE WHEN ts.name='Completed'        THEN 1 ELSE 0 END) AS completed
    FROM tasks t
    JOIN task_status ts ON t.status_id = ts.id
    WHERE t.created_by = ?
");
$stmt->execute([$admin_id]);
$stats = $stmt->fetch()
       ?: ['total'=>0,'pending'=>0,'confirmation'=>0,'completed'=>0];

$total_members = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE role_id = 2"
)->fetchColumn();

$ov = $pdo->prepare("
    SELECT COUNT(*) FROM tasks
    WHERE created_by=? AND due_datetime<NOW() AND status_id!=3
");
$ov->execute([$admin_id]);
$overdue = (int)$ov->fetchColumn();

$recent_stmt = $pdo->prepare("
    SELECT t.*, ts.name AS status,
           u.email AS assigned_to,
           u.username AS assigned_name
    FROM tasks t
    JOIN task_status ts ON t.status_id = ts.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.created_by = ?
    ORDER BY t.created_at DESC
    LIMIT 6
");
$recent_stmt->execute([$admin_id]);
$recent = $recent_stmt->fetchAll();

// ══════════════════════════════════════════════════════════
// STEP 3: Output HTML (header.php first)
// ══════════════════════════════════════════════════════════
require_once '../includes/header.php';
?>

<div class="d-flex align-items-center
            justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">
            Welcome back,
            <strong><?php echo htmlspecialchars(
                $_SESSION['username'] ?? 'Admin'); ?></strong>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($overdue): ?>
        <span class="cm-status-badge cm-status-overdue cm-status-lg">
            <?php echo $overdue; ?> Overdue
        </span>
        <?php endif; ?>
        <span class="cm-tag cm-tag-primary">
            <?php echo $total_members; ?> Member<?php
                echo $total_members !== 1 ? 's' : ''; ?>
        </span>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-primary">
                <i class="bi bi-list-check"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-warning">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo (int)$stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-danger">
                <i class="bi bi-question-circle"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo (int)$stats['confirmation']; ?></div>
                <div class="stat-label">For Confirmation</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-success">
                <i class="bi bi-check-circle"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo (int)$stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
    </div>
</div>

<div class="cm-card">
    <div class="cm-card-header">
        <div>
            <div class="cm-card-title">Recent Tasks</div>
            <div class="cm-card-subtitle">Your tasks only</div>
        </div>
        <a href="tasks.php" class="cm-btn cm-btn-ghost cm-btn-sm">
            View All
        </a>
    </div>
    <?php if (empty($recent)): ?>
    <div class="cm-empty-state">
        <i class="bi bi-inbox cm-empty-icon"></i>
        <p class="cm-empty-text">No tasks yet.</p>
        <a href="create_task.php" class="cm-btn cm-btn-primary mt-3">
            <i class="bi bi-plus-circle me-1"></i>Create Task
        </a>
    </div>
    <?php else: ?>
    <div class="cm-list">
        <?php foreach ($recent as $task): ?>
        <a href="task_detail.php?id=<?php echo $task['id']; ?>&from=all"
           class="cm-list-item">
            <div class="cm-list-item-body">
                <div class="cm-list-item-title">
                    <?php echo htmlspecialchars($task['title']); ?>
                </div>
                <div class="cm-list-item-meta">
                    <i class="bi bi-clock me-1"></i>
                    Due: <?php echo date('M j, Y H:i',
                        strtotime($task['due_datetime'])); ?>
                    <?php if (strtotime($task['due_datetime']) < time()
                              && $task['status_id'] != 3): ?>
                    <span style="color:var(--danger);margin-left:6px">
                        Overdue
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if ($task['assigned_to']): ?>
                <span class="cm-tag cm-tag-info">
                    <?php echo htmlspecialchars(
                        $task['assigned_name']??$task['assigned_to']); ?>
                </span>
                <?php else: ?>
                <span class="cm-tag cm-tag-neutral">Admin only</span>
                <?php endif; ?>
                <span class="cm-status-badge cm-status-<?php
                    echo $task['status']==='Completed'?'done'
                        :($task['status']==='For Confirmation'
                            ?'confirm':'pending'); ?>">
                    <?php echo htmlspecialchars($task['status']); ?>
                </span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>