<?php
require_once '../includes/header.php'; requireLogin();
require_once '../config/database.php';
if (isAdmin()) { header("Location: ../admin/home.php"); exit(); }

$member_id = (int)$_SESSION['user_id'];

$stats_stmt = $pdo->prepare("
    SELECT
        COUNT(*)                                                          AS total,
        SUM(CASE WHEN ts.name='Completed' THEN 1 ELSE 0 END)             AS done,
        SUM(CASE WHEN t.due_datetime<NOW()
                 AND ts.name NOT IN ('Completed') THEN 1 ELSE 0 END)     AS overdue
    FROM tasks t
    JOIN task_status ts ON t.status_id=ts.id
    WHERE t.created_by=? OR t.user_id=?
");
$stats_stmt->execute([$member_id, $member_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC)
       ?: ['total'=>0,'done'=>0,'overdue'=>0];

$recent_stmt = $pdo->prepare("
    SELECT t.*, ts.name AS status, ts.id AS sid,
           tc.name AS category, tc.color AS cat_color,
           CASE WHEN t.created_by=? THEN 'own' ELSE 'assigned' END AS task_type,
           cb.username AS creator_name
    FROM tasks t
    JOIN task_status ts ON t.status_id=ts.id
    LEFT JOIN task_categories tc ON t.category_id=tc.id
    LEFT JOIN users cb ON t.created_by=cb.id
    WHERE (t.created_by=? OR t.user_id=?)
    ORDER BY t.created_at DESC
    LIMIT 5
");
$recent_stmt->execute([$member_id, $member_id, $member_id]);
$recent = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h1 class="page-title">Home</h1>
        <p class="page-subtitle">
            Welcome, <strong><?php echo htmlspecialchars(
                $_SESSION['username'] ?? 'Member'); ?></strong>
        </p>
    </div>
    <a href="tasks.php" data-bs-toggle="modal"
       data-bs-target="#createTaskModalHome"
       class="cm-btn cm-btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Add Task
    </a>
</div>

<!-- Stat cards with color -->
<div class="row g-3 mb-4">
    <div class="col-4">
        <div class="stat-card">
            <div class="stat-icon stat-icon-primary">
                <i class="bi bi-list-check"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card">
            <div class="stat-icon stat-icon-success">
                <i class="bi bi-check-circle"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo (int)$stats['done']; ?></div>
                <div class="stat-label">Done</div>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card">
            <div class="stat-icon stat-icon-warning">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div>
                <div class="stat-value"><?php echo (int)$stats['overdue']; ?></div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>
    </div>
</div>

<!-- Recent tasks -->
<div class="cm-card">
    <div class="cm-card-header">
        <div class="cm-card-title">Recent Tasks</div>
        <a href="tasks.php" class="cm-btn cm-btn-ghost cm-btn-sm">View All</a>
    </div>

    <?php if (empty($recent)): ?>
    <div class="cm-empty-state">
        <i class="bi bi-inbox cm-empty-icon"></i>
        <p class="cm-empty-text">No tasks yet. Create your first task!</p>
    </div>
    <?php else: ?>
    <div class="cm-list">
        <?php foreach ($recent as $task):
            $sv = (int)($task['sid'] ?? $task['status_id']);
        ?>
        <a href="task_detail.php?id=<?php echo $task['id'];
            ?>&from=<?php echo $task['task_type']==='own'?'private':'assigned'; ?>"
           class="cm-list-item">
            <div class="cm-list-item-body">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <?php if (!empty($task['priority'])): ?>
                    <span class="cm-priority-badge">
                        <?php echo (int)$task['priority']; ?>
                    </span>
                    <?php endif; ?>
                    <div class="cm-list-item-title">
                        <?php echo htmlspecialchars($task['title']); ?>
                    </div>
                    <?php if (!empty($task['cat_color'])): ?>
                    <span class="cm-cat-dot"
                          style="background:<?php echo htmlspecialchars(
                              $task['cat_color']); ?>"></span>
                    <?php endif; ?>
                    <?php if ($task['task_type']==='assigned'): ?>
                    <span class="cm-tag cm-tag-warning" style="font-size:10px">
                        Admin
                    </span>
                    <?php endif; ?>
                </div>
                <div class="cm-list-item-meta">
                    <i class="bi bi-clock me-1"></i>
                    Due: <?php echo date('M j, Y H:i',
                        strtotime($task['due_datetime'])); ?>
                </div>
            </div>
            <span class="cm-status-badge cm-status-<?php
                echo $sv===3?'done':($sv===2?'confirm':'pending'); ?>">
                <?php echo htmlspecialchars($task['status']); ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>