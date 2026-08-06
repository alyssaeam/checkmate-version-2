<?php
// ── POST LOGIC BEFORE ANY OUTPUT ─────────────────────────
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php"); exit();
}
if ((int)$_SESSION['role_id'] === 1) {
    header("Location: ../admin/home.php"); exit();
}

$member_id = (int)$_SESSION['user_id'];

// ── Handle quick task creation from home modal ──────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'quick_create') {

    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $due         = $_POST['due_datetime']      ?? '';
    $category_id = !empty($_POST['category_id'])
                   ? (int)$_POST['category_id'] : null;
    $priority    = !empty($_POST['priority'])
                   ? (int)$_POST['priority'] : null;

    if (!empty($title) && !empty($due)) {
        $pdo->prepare("
            INSERT INTO tasks
                (title, description, due_datetime, category_id,
                 priority, is_private, created_by, status_id)
            VALUES (?, ?, ?, ?, ?, 1, ?, 1)
        ")->execute([$title, $description, $due,
                     $category_id, $priority, $member_id]);
    }
    header("Location: home.php?success=task_created");
    exit();
}

// ── Load stats ────────────────────────────────────────────
$stats_stmt = $pdo->prepare("
    SELECT
        COUNT(*)                                                      AS total,
        SUM(CASE WHEN ts.name='Completed'    THEN 1 ELSE 0 END)      AS done,
        SUM(CASE WHEN t.due_datetime < NOW()
                 AND ts.name NOT IN ('Completed')
                 THEN 1 ELSE 0 END)                                   AS overdue
    FROM tasks t
    JOIN task_status ts ON t.status_id = ts.id
    WHERE t.created_by = ? OR t.user_id = ?
");
$stats_stmt->execute([$member_id, $member_id]);
$stats = $stats_stmt->fetch()
       ?: ['total' => 0, 'done' => 0, 'overdue' => 0];

// ── Load recent tasks ─────────────────────────────────────
$recent_stmt = $pdo->prepare("
    SELECT t.*, ts.name AS status, ts.id AS sid,
           tc.name AS category, tc.color AS cat_color,
           CASE WHEN t.created_by = ? THEN 'own'
                ELSE 'assigned' END AS task_type,
           cb.username AS creator_name
    FROM tasks t
    JOIN task_status ts ON t.status_id = ts.id
    LEFT JOIN task_categories tc ON t.category_id = tc.id
    LEFT JOIN users cb ON t.created_by = cb.id
    WHERE (t.created_by = ? OR t.user_id = ?)
    ORDER BY t.created_at DESC
    LIMIT 5
");
$recent_stmt->execute([$member_id, $member_id, $member_id]);
$recent = $recent_stmt->fetchAll();

// ── Load categories for the modal ────────────────────────
$cats_stmt = $pdo->prepare("
    SELECT * FROM task_categories
    WHERE user_id = ? OR user_id IS NULL
    ORDER BY name
");
$cats_stmt->execute([$member_id]);
$categories = $cats_stmt->fetchAll();

// ── NOW output HTML ───────────────────────────────────────
require_once '../includes/header.php';
?>

<!-- Page header -->
<div class="d-flex align-items-center justify-content-between
            mb-4 flex-wrap gap-3">
    <div>
        <h1 class="page-title">Home</h1>
        <p class="page-subtitle">
            Welcome back,
            <strong>
                <?php echo htmlspecialchars(
                    $_SESSION['username'] ?? 'Member'); ?>
            </strong>
        </p>
    </div>
    <!-- Add Task button — triggers modal -->
    <button class="cm-btn cm-btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#quickCreateModal">
        <i class="bi bi-plus-lg me-1"></i>Add Task
    </button>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] === 'task_created'): ?>
<div class="alert alert-success alert-dismissible fade show mb-4">
    <i class="bi bi-check-circle me-2"></i>
    Task created successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-4">
        <div class="stat-card">
            <div class="stat-icon stat-icon-primary">
                <i class="bi bi-list-check"></i>
            </div>
            <div>
                <div class="stat-value">
                    <?php echo (int)$stats['total']; ?>
                </div>
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
                <div class="stat-value">
                    <?php echo (int)$stats['done']; ?>
                </div>
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
                <div class="stat-value">
                    <?php echo (int)$stats['overdue']; ?>
                </div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>
    </div>
</div>

<!-- Recent tasks -->
<div class="cm-card">
    <div class="cm-card-header">
        <div class="cm-card-title">Recent Tasks</div>
        <a href="tasks.php" class="cm-btn cm-btn-ghost cm-btn-sm">
            View All
        </a>
    </div>

    <?php if (empty($recent)): ?>
    <div class="cm-empty-state">
        <i class="bi bi-inbox cm-empty-icon"></i>
        <p class="cm-empty-text">No tasks yet. Create your first task!</p>
        <button class="cm-btn cm-btn-primary mt-3"
                data-bs-toggle="modal"
                data-bs-target="#quickCreateModal">
            <i class="bi bi-plus-circle me-1"></i>Add Task
        </button>
    </div>
    <?php else: ?>
    <div class="cm-list">
        <?php foreach ($recent as $task):
            $sv = (int)($task['sid'] ?? $task['status_id']);
        ?>
        <a href="task_detail.php?id=<?php echo $task['id'];
            ?>&from=<?php echo $task['task_type'] === 'own'
                ? 'private' : 'assigned'; ?>"
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
                              $task['cat_color']); ?>">
                    </span>
                    <?php endif; ?>
                    <?php if ($task['task_type'] === 'assigned'): ?>
                    <span class="cm-tag cm-tag-warning"
                          style="font-size:10px">Admin</span>
                    <?php endif; ?>
                </div>
                <div class="cm-list-item-meta">
                    <i class="bi bi-clock me-1"></i>
                    Due: <?php echo date('M j, Y H:i',
                        strtotime($task['due_datetime'])); ?>
                    <?php if (strtotime($task['due_datetime']) < time()
                              && $sv < 3): ?>
                    <span style="color:var(--danger);margin-left:6px">
                        Overdue
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <span class="cm-status-badge cm-status-<?php
                echo $sv === 3 ? 'done'
                    : ($sv === 2 ? 'confirm' : 'pending'); ?>">
                <?php echo htmlspecialchars($task['status']); ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>


<!-- ── QUICK CREATE TASK MODAL ────────────────────────── -->
<div class="modal fade" id="quickCreateModal" tabindex="-1"
     aria-labelledby="quickCreateModalLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickCreateModalLabel">
                    <i class="bi bi-plus-circle me-2"
                       style="color:var(--primary)"></i>
                    Create New Task
                </h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="quick_create">
                <div class="modal-body p-4">

                    <div class="mb-3">
                        <label class="form-label">Task Title *</label>
                        <input type="text"
                               name="title"
                               class="form-control form-control-lg"
                               placeholder="What needs to be done?"
                               required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Any additional details...">
                        </textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Due Date & Time *</label>
                            <input type="datetime-local"
                                   name="due_datetime"
                                   class="form-control"
                                   required
                                   value="<?php echo date('Y-m-d\TH:i',
                                       strtotime('+1 day')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">No category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"
                                        style="color:<?php echo htmlspecialchars(
                                            $cat['color']); ?>">
                                    <?php echo htmlspecialchars(
                                        $cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Priority (1–5)</label>
                        <select name="priority" class="form-select">
                            <option value="">No priority</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?php echo $i; ?>">
                                <?php echo $i . ' — '
                                    . ['Very Low','Low','Medium',
                                       'High','Critical'][$i-1]; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit"
                            class="cm-btn cm-btn-primary">
                        <i class="bi bi-check-circle me-1"></i>
                        Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>