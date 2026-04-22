<?php
// ── ALL POST LOGIC FIRST — before any output ──────────────
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../shared/login.php"); exit();
}

$admin_id = (int)$_SESSION['user_id'];
$filter   = $_GET['filter'] ?? 'all';
$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));

// ── NOW load header (outputs HTML) ────────────────────────
require_once '../includes/header.php';

$conditions = ["t.created_by = ?"];
$params     = [$admin_id];

if ($filter === 'assigned') $conditions[] = "t.user_id IS NOT NULL";
if ($filter === 'confirm')  $conditions[] = "t.status_id = 2";
if ($filter === 'done')     $conditions[] = "t.status_id = 3";

$where = "WHERE " . implode(" AND ", $conditions);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t $where");
$count_stmt->execute($params);
$total_rows  = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$fetch_sql = "
    SELECT t.*, ts.name as status
    FROM tasks t
    LEFT JOIN task_status ts ON t.status_id = ts.id
    $where
    ORDER BY
        CASE WHEN t.status_id = 2 THEN 0 ELSE 1 END,
        t.due_datetime ASC
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($fetch_sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$users_by_id = [];
if (!empty($tasks)) {
    $uid_list = array_filter(array_unique(array_column($tasks, 'user_id')));
    if (!empty($uid_list)) {
        $ph = implode(',', array_fill(0, count($uid_list), '?'));
        $u_stmt = $pdo->prepare(
            "SELECT id, username, email FROM users WHERE id IN ($ph)");
        $u_stmt->execute(array_values($uid_list));
        foreach ($u_stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $users_by_id[$u['id']] = $u;
        }
    }
}

$tab_stmt = $pdo->prepare("
    SELECT
        COUNT(*)                                           as total,
        SUM(CASE WHEN t.user_id IS NOT NULL THEN 1 END)   as assigned,
        SUM(CASE WHEN t.status_id = 2       THEN 1 END)   as confirm,
        SUM(CASE WHEN t.status_id = 3       THEN 1 END)   as done
    FROM tasks t WHERE t.created_by = ?
");
$tab_stmt->execute([$admin_id]);
$counts = $tab_stmt->fetch(PDO::FETCH_ASSOC);

function adminTaskPageUrl($f, $p) {
    return '?filter='.urlencode($f).'&page='.(int)$p;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">Tasks</h1>
        <div class="d-flex gap-2 mt-1 flex-wrap">
            <span class="cm-tag cm-tag-neutral">
                <?php echo (int)$counts['total']; ?> Total
            </span>
            <?php if ((int)$counts['confirm'] > 0): ?>
            <span class="cm-tag cm-tag-warning">
                <?php echo (int)$counts['confirm']; ?> Need Confirmation
            </span>
            <?php endif; ?>
        </div>
    </div>
    <a href="create_task.php" class="cm-btn cm-btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Task
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-4">
    <i class="bi bi-check-circle me-2"></i>
    <?php echo match($_GET['success']) {
        'assigned'  => 'Task assigned!',
        'admin'     => 'Task created!',
        'updated'   => 'Task updated.',
        'deleted'   => 'Task deleted.',
        'confirmed' => 'Task marked as Completed!',
        default     => 'Done!'
    }; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<ul class="nav nav-pills mb-4">
    <?php
    $tabs = [
        'all'      => ['All',              (int)$counts['total'],    'secondary'],
        'assigned' => ['Assigned',         (int)$counts['assigned'], 'info'],
        'confirm'  => ['For Confirmation', (int)$counts['confirm'],  'warning'],
        'done'     => ['Done',             (int)$counts['done'],     'success'],
    ];
    foreach ($tabs as $key => [$label, $count, $color]): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo $filter===$key?'active':''; ?>"
           href="<?php echo adminTaskPageUrl($key,1); ?>">
            <?php echo $label; ?>
            <span class="badge ms-1 bg-<?php
                echo $filter===$key?'light text-dark':$color; ?>">
                <?php echo $count; ?>
            </span>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<div class="cm-card">
    <?php if (empty($tasks)): ?>
    <div class="cm-empty-state">
        <i class="bi bi-inbox cm-empty-icon"></i>
        <p class="cm-empty-text">No tasks in this filter.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task):
                    $assignee = null;
                    if (!empty($task['user_id']) && isset($users_by_id[$task['user_id']])) {
                        $assignee = $users_by_id[$task['user_id']];
                    }
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold" style="font-size:13px">
                            <?php echo htmlspecialchars($task['title']); ?>
                        </div>
                        <?php if (!empty($task['description'])): ?>
                        <div class="text-muted" style="font-size:11px">
                            <?php echo htmlspecialchars(
                                substr($task['description'],0,55)); ?>…
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="<?php
                            echo strtotime($task['due_datetime'])<time()
                              && (int)$task['status_id']!==3
                              ? 'text-danger fw-bold' : ''; ?>"
                              style="font-size:12px">
                            <?php echo date('M j, Y',
                                strtotime($task['due_datetime'])); ?>
                            <br>
                            <?php echo date('H:i',
                                strtotime($task['due_datetime'])); ?>
                        </span>
                    </td>
                    <td>
                        <span class="cm-status-badge cm-status-<?php
                            echo $task['status']==='Completed' ? 'done'
                                : ($task['status']==='For Confirmation'
                                    ? 'confirm' : 'pending'); ?>">
                            <?php echo htmlspecialchars($task['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($assignee): ?>
                        <span class="cm-tag cm-tag-info">
                            <?php echo htmlspecialchars(
                                $assignee['username'] ?? $assignee['email']); ?>
                        </span>
                        <?php else: ?>
                        <span class="cm-tag cm-tag-neutral">Admin only</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <?php if ($task['status']==='For Confirmation'): ?>
                            <form method="POST" action="edit_task.php">
                                <input type="hidden" name="action" value="confirm">
                                <input type="hidden" name="id"
                                       value="<?php echo (int)$task['id']; ?>">
                                <button class="cm-btn cm-btn-success cm-btn-sm">
                                    <i class="bi bi-patch-check me-1"></i>Done
                                </button>
                            </form>
                            <?php endif; ?>
                            <a href="task_detail.php?id=<?php echo (int)$task['id'];
                                ?>&from=<?php echo urlencode($filter); ?>"
                               class="cm-icon-btn" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ((int)$task['status_id']!==3): ?>
                            <a href="edit_task.php?id=<?php echo (int)$task['id']; ?>"
                               class="cm-icon-btn" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <form method="POST" action="edit_task.php"
                                  onsubmit="return confirm('Delete \'<?php
                                      echo addslashes($task['title']); ?>\'?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"
                                       value="<?php echo (int)$task['id']; ?>">
                                <button class="cm-icon-btn"
                                        style="border-color:var(--danger);
                                               color:var(--danger)"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($total_pages > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <li class="page-item <?php echo $page<=1?'disabled':''; ?>">
            <a class="page-link"
               href="<?php echo adminTaskPageUrl($filter,$page-1); ?>">
                &laquo; Prev
            </a>
        </li>
        <?php for ($i=1;$i<=$total_pages;$i++): ?>
        <li class="page-item <?php echo $i===$page?'active':''; ?>">
            <a class="page-link"
               href="<?php echo adminTaskPageUrl($filter,$i); ?>">
                <?php echo $i; ?>
            </a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?php echo $page>=$total_pages?'disabled':''; ?>">
            <a class="page-link"
               href="<?php echo adminTaskPageUrl($filter,$page+1); ?>">
                Next &raquo;
            </a>
        </li>
    </ul>
    <p class="text-center text-muted small">
        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
        (<?php echo $total_rows; ?> tasks)
    </p>
</nav>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>