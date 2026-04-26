<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php"); exit();
}
if ((int)$_SESSION['role_id'] === 1) {
    header("Location: ../admin/home.php"); exit();
}

// ── POST HANDLING (Before any HTML output) ────────────────
$member_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $task_id = (int)($_POST['task_id'] ?? 0);
    $f       = $_POST['filter']  ?? 'private';

    if ($action === 'mark_done') {
        $check = $pdo->prepare("SELECT id, created_by, user_id FROM tasks WHERE id=?");
        $check->execute([$task_id]);
        $t = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($t) {
            $is_own = ((int)$t['created_by'] === $member_id);
            if ($is_own) {
                $pdo->prepare("UPDATE tasks SET status_id=3, completed_at=NOW() WHERE id=? AND created_by=?")
                    ->execute([$task_id, $member_id]);
            } else {
                $pdo->prepare("UPDATE tasks SET status_id=2 WHERE id=? AND status_id=1")
                    ->execute([$task_id]);
            }
        }
        header("Location: tasks.php?filter=$f&success=marked_done"); 
        exit();
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM tasks WHERE id=? AND created_by=?")
            ->execute([$task_id, $member_id]);
        header("Location: tasks.php?filter=$f&success=deleted"); 
        exit();
    }

    if ($action === 'create') {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due         = $_POST['due_datetime'] ?? '';
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $priority    = !empty($_POST['priority']) ? (int)$_POST['priority'] : null;
        
        if (!empty($title) && !empty($due)) {
            $pdo->prepare("INSERT INTO tasks (title, description, due_datetime, category_id, priority, is_private, created_by, status_id) 
                           VALUES (?,?,?,?,?,1,?,1)")
                ->execute([$title, $description, $due, $category_id, $priority, $member_id]);
        }
        header("Location: tasks.php?filter=private&success=created"); 
        exit();
    }

    if ($action === 'edit') {
        $check = $pdo->prepare("SELECT id FROM tasks WHERE id=? AND created_by=?");
        $check->execute([$task_id, $member_id]);
        if ($check->fetch()) {
            $title       = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $due         = $_POST['due_datetime'] ?? '';
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $priority    = !empty($_POST['priority']) ? (int)$_POST['priority'] : null;
            
            $pdo->prepare("UPDATE tasks SET title=?, description=?, due_datetime=?, category_id=?, priority=? WHERE id=? AND created_by=?")
                ->execute([$title, $description, $due, $category_id, $priority, $task_id, $member_id]);
        }
        header("Location: tasks.php?filter=private&success=updated"); 
        exit();
    }
}

// ── DATA LOADING ──────────────────────────────────────────
$filter   = $_GET['filter'] ?? 'private';
$per_page = 8;
$page     = max(1, (int)($_GET['page'] ?? 1));

// Stats Counts
$priv_cnt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE created_by=? AND status_id!=3");
$priv_cnt->execute([$member_id]);
$priv_count = (int)$priv_cnt->fetchColumn();

$asgn_cnt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id=? AND created_by!=? AND status_id!=3");
$asgn_cnt->execute([$member_id, $member_id]);
$asgn_count = (int)$asgn_cnt->fetchColumn();

$done_cnt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE (created_by=? OR user_id=?) AND status_id=3");
$done_cnt->execute([$member_id, $member_id]);
$done_count = (int)$done_cnt->fetchColumn();

// Pagination Logic
if ($filter === 'assigned') {
    $cp = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id=? AND created_by!=? AND status_id!=3");
    $cp->execute([$member_id, $member_id]);
} elseif ($filter === 'done') {
    $cp = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE (created_by=? OR user_id=?) AND status_id=3");
    $cp->execute([$member_id, $member_id]);
} else {
    $cp = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE created_by=? AND status_id!=3");
    $cp->execute([$member_id]);
}

$total_rows  = (int)$cp->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page         = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$base = "SELECT t.*, ts.name as status, ts.id as sid, tc.name as category, tc.color as cat_color, cb.username as creator_name
         FROM tasks t
         LEFT JOIN task_status ts ON t.status_id=ts.id
         LEFT JOIN task_categories tc ON t.category_id=tc.id
         LEFT JOIN users cb ON t.created_by=cb.id";

if ($filter === 'assigned') {
    $stmt = $pdo->prepare($base . " WHERE t.user_id=? AND t.created_by!=? AND t.status_id!=3 ORDER BY t.due_datetime ASC LIMIT $per_page OFFSET $offset");
    $stmt->execute([$member_id, $member_id]);
} elseif ($filter === 'done') {
    $stmt = $pdo->prepare($base . " WHERE (t.created_by=? OR t.user_id=?) AND t.status_id=3 ORDER BY t.completed_at DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute([$member_id, $member_id]);
} else {
    $stmt = $pdo->prepare($base . " WHERE t.created_by=? AND t.status_id!=3 ORDER BY COALESCE(t.priority,0) DESC, t.due_datetime ASC LIMIT $per_page OFFSET $offset");
    $stmt->execute([$member_id]);
}
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cats_stmt = $pdo->prepare("SELECT * FROM task_categories WHERE user_id=? OR user_id IS NULL ORDER BY name");
$cats_stmt->execute([$member_id]);
$categories = $cats_stmt->fetchAll(PDO::FETCH_ASSOC);

$edit_task = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM tasks WHERE id=? AND created_by=?");
    $s->execute([(int)$_GET['edit'], $member_id]);
    $edit_task = $s->fetch(PDO::FETCH_ASSOC);
}

$ov = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE (created_by=? OR user_id=?) AND due_datetime<NOW() AND status_id NOT IN(2,3)");
$ov->execute([$member_id, $member_id]);
$overdue_count = (int)$ov->fetchColumn();

function memberPgUrl($f, $p) {
    return '?filter=' . urlencode($f) . '&page=' . (int)$p;
}

// ── OUTPUT START ──────────────────────────────────────────
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="page-title">My Tasks</h1>
        <div class="d-flex gap-2 mt-1 flex-wrap">
            <span class="cm-tag cm-tag-neutral"><?php echo $priv_count + $asgn_count; ?> Active</span>
            <span class="cm-tag cm-tag-success"><?php echo $done_count; ?> Done</span>
            <?php if ($overdue_count): ?>
                <span class="cm-tag cm-tag-danger"><?php echo $overdue_count; ?> Overdue</span>
            <?php endif; ?>
        </div>
    </div>
    <button class="cm-btn cm-btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
        <i class="bi bi-plus-lg me-1"></i>New Task
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-3">
    <i class="bi bi-check-circle me-2"></i>
    <?php echo match($_GET['success']){
        'created'     => 'Task created!',
        'updated'     => 'Task updated!',
        'deleted'     => 'Task deleted.',
        'marked_done' => 'Task marked as done!',
        default       => 'Done!'
    }; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $filter==='private'?'active':''; ?>" href="<?php echo memberPgUrl('private',1); ?>">
            Private <span class="badge bg-light text-dark ms-1"><?php echo $priv_count; ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $filter==='assigned'?'active':''; ?>" href="<?php echo memberPgUrl('assigned',1); ?>">
            Assigned <span class="badge bg-light text-dark ms-1"><?php echo $asgn_count; ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $filter==='done'?'active':''; ?>" href="<?php echo memberPgUrl('done',1); ?>">
            Done <span class="badge bg-light text-dark ms-1"><?php echo $done_count; ?></span>
        </a>
    </li>
</ul>

<?php if (empty($tasks)): ?>
<div class="cm-card">
    <div class="cm-empty-state">
        <i class="bi bi-inbox cm-empty-icon"></i>
        <p class="cm-empty-text">No tasks here.</p>
    </div>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($tasks as $task): 
        $is_own  = ((int)$task['created_by']===$member_id);
        $sid     = (int)($task['sid'] ?? $task['status_id']);
        $is_over = $sid < 2 && strtotime($task['due_datetime']) < time();
        $can_mark = ($sid === 1);
    ?>
    <div class="cm-card">
        <div style="padding:14px 18px">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div class="flex-grow-1 min-width-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <?php if (!empty($task['priority'])): ?>
                            <span class="cm-priority-badge"><?php echo (int)$task['priority']; ?></span>
                        <?php endif; ?>
                        <span style="font-size:13px; font-weight:600; color:var(--text-main)">
                            <?php echo htmlspecialchars($task['title']); ?>
                        </span>
                        <?php if (!empty($task['cat_color'])): ?>
                            <span class="cm-tag" style="background:<?php echo $task['cat_color']; ?>20; color:<?php echo $task['cat_color']; ?>; border:1px solid <?php echo $task['cat_color']; ?>40; font-size:10px">
                                <?php echo htmlspecialchars($task['category']); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!$is_own): ?>
                            <span class="cm-tag cm-tag-warning" style="font-size:10px">Admin</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($task['description'])): ?>
                        <p style="font-size:12px; color:var(--text-muted); margin:0 0 4px">
                            <?php echo htmlspecialchars(substr($task['description'],0,80)); ?>...
                        </p>
                    <?php endif; ?>
                    <div style="font-size:11px; color:<?php echo $is_over?'var(--danger)':'var(--text-muted)'; ?>">
                        <i class="bi bi-clock me-1"></i>
                        Due: <?php echo date('M j, Y H:i', strtotime($task['due_datetime'])); ?>
                        <?php if ($is_over): ?><strong> — Overdue</strong><?php endif; ?>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end gap-2" style="flex-shrink:0">
                    <span class="cm-status-badge cm-status-<?php echo $sid===3?'done':($sid===2?'confirm':'pending'); ?>">
                        <?php echo htmlspecialchars($task['status']??'Pending'); ?>
                    </span>
                    <div class="d-flex gap-1 flex-wrap justify-content-end">
                        <?php if ($can_mark): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="mark_done">
                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                            <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                            <button class="cm-btn cm-btn-success cm-btn-sm"><i class="bi bi-check-lg me-1"></i>Done</button>
                        </form>
                        <?php endif; ?>
                        <a href="task_detail.php?id=<?php echo $task['id']; ?>&from=<?php echo htmlspecialchars($filter); ?>" class="cm-icon-btn" title="View"><i class="bi bi-eye"></i></a>
                        <?php if ($is_own && $filter !== 'done'): ?>
                            <a href="?edit=<?php echo $task['id']; ?>&filter=<?php echo $filter; ?>" class="cm-icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="POST" onsubmit="return confirm('Delete this task?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                                <button class="cm-icon-btn" style="border-color:var(--danger); color:var(--danger)" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php elseif ($filter === 'done' && $is_own): ?>
                             <form method="POST" onsubmit="return confirm('Delete this task?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="hidden" name="filter" value="done">
                                <button class="cm-icon-btn" style="border-color:var(--danger); color:var(--danger)" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($total_pages > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <li class="page-item <?php echo $page<=1?'disabled':''; ?>">
            <a class="page-link" href="<?php echo memberPgUrl($filter, $page-1); ?>">&laquo;</a>
        </li>
        <?php for($i=1; $i<=$total_pages; $i++): ?>
            <li class="page-item <?php echo $i===$page?'active':''; ?>">
                <a class="page-link" href="<?php echo memberPgUrl($filter, $i); ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?php echo $page>=$total_pages?'disabled':''; ?>">
            <a class="page-link" href="<?php echo memberPgUrl($filter, $page+1); ?>">&raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php if ($edit_task): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    new bootstrap.Modal(document.getElementById('editTaskModal')).show();
});
</script>
<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Task</h5>
                <a href="tasks.php?filter=<?php echo $filter; ?>" class="btn-close"></a>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="task_id" value="<?php echo (int)$edit_task['id']; ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($edit_task['title']); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($edit_task['description']??''); ?></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Due Date & Time *</label>
                            <input type="datetime-local" name="due_datetime" class="form-control" required value="<?php echo date('Y-m-d\TH:i', strtotime($edit_task['due_datetime'])); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">No category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo (int)$edit_task['category_id']===(int)$cat['id']?'selected':''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority (1–5)</label>
                        <select name="priority" class="form-select">
                            <option value="">None</option>
                            <?php for($i=1;$i<=5;$i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo (int)$edit_task['priority']===$i?'selected':''; ?>><?php echo $i.' '.str_repeat('⭐',$i); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="tasks.php?filter=<?php echo $filter; ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="cm-btn cm-btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="createTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="Task title">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Details..."></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Due Date & Time *</label>
                            <input type="datetime-local" name="due_datetime" class="form-control" required value="<?php echo date('Y-m-d\TH:i', strtotime('+1 day')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">No category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority (1–5)</label>
                        <select name="priority" class="form-select">
                            <option value="">None</option>
                            <?php for($i=1;$i<=5;$i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i.' '.str_repeat('⭐',$i); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="cm-btn cm-btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>