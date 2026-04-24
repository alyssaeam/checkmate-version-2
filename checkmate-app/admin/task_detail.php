<?php
// ── POST LOGIC BEFORE ANY OUTPUT ─────────────────────────
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../shared/login.php"); exit();
}

$admin_id = (int)$_SESSION['user_id'];
$task_id  = (int)($_GET['id'] ?? 0);

if (!$task_id) { header("Location: tasks.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'confirm') {
        $pdo->prepare("
            UPDATE tasks SET status_id=3, completed_at=NOW()
            WHERE id=? AND created_by=?
        ")->execute([$task_id, $admin_id]);
        header("Location: task_detail.php?id=$task_id&from="
               .urlencode($_GET['from']??'all')."&success=confirmed");
        exit();
    }
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM tasks WHERE id=? AND created_by=?")
            ->execute([$task_id, $admin_id]);
        header("Location: tasks.php?success=deleted"); exit();
    }
}

$stmt = $pdo->prepare("
    SELECT t.*,
           ts.name    AS status_name,
           ts.id      AS status_id_val,
           u.username AS assigned_name,
           u.email    AS assigned_email
    FROM tasks t
    LEFT JOIN task_status ts ON t.status_id=ts.id
    LEFT JOIN users u        ON t.user_id=u.id
    WHERE t.id=? AND t.created_by=?
    LIMIT 1
");
$stmt->execute([$task_id, $admin_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task) { header("Location: tasks.php?error=notfound"); exit(); }

$sid     = (int)($task['status_id_val'] ?? $task['status_id']);
$is_done = ($sid===3);
$is_conf = ($sid===2);
$is_over = !$is_done && !$is_conf && strtotime($task['due_datetime'])<time();
$diff    = strtotime($task['due_datetime'])-time();
$from    = htmlspecialchars($_GET['from'] ?? 'all');

$status_label = match($sid){1=>'Pending',2=>'For Confirmation',3=>'Completed',default=>'Unknown'};
$status_cls   = match($sid){1=>'pending',2=>'confirm',3=>'done',default=>'pending'};

// ── NOW output HTML ───────────────────────────────────────
require_once '../includes/header.php';
?>

<div class="cm-breadcrumb mb-4">
    <a href="tasks.php?filter=<?php echo $from; ?>" class="cm-breadcrumb-link">
        <i class="bi bi-chevron-left me-1"></i>Tasks
    </a>
    <span class="cm-breadcrumb-sep">/</span>
    <span class="cm-breadcrumb-current">Task Detail</span>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-4">
    <i class="bi bi-check-circle me-2"></i>Task marked as Completed.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="cm-detail-layout">
    <div class="cm-card">

        <div class="cm-detail-header">
            <div class="cm-detail-header-left">
                <div class="cm-detail-badges">
                    <span class="cm-tag cm-tag-neutral">
                        <i class="bi bi-shield-check me-1"></i>Admin Task
                    </span>
                    <?php if (!empty($task['user_id'])): ?>
                    <span class="cm-tag cm-tag-info">
                        <i class="bi bi-person me-1"></i>
                        Assigned to
                        <?php echo htmlspecialchars(
                            $task['assigned_name'] ?? $task['assigned_email']); ?>
                    </span>
                    <?php else: ?>
                    <span class="cm-tag cm-tag-muted">Unassigned</span>
                    <?php endif; ?>
                </div>
                <h2 class="cm-detail-title">
                    <?php echo htmlspecialchars($task['title']); ?>
                </h2>
            </div>
            <span class="cm-status-badge cm-status-<?php echo $status_cls; ?>
                         cm-status-lg">
                <?php echo $status_label; ?>
            </span>
        </div>

        <?php if ($is_conf): ?>
        <div class="cm-notice cm-notice-warning"
             style="margin:16px 24px 0;border-radius:var(--radius-sm)">
            <div class="cm-notice-body">
                <i class="bi bi-hourglass-split cm-notice-icon"></i>
                <div>
                    <div class="cm-notice-title">Awaiting Confirmation</div>
                    <div class="cm-notice-text">
                        Member marked this task as done.
                    </div>
                </div>
            </div>
            <form method="POST" class="mt-3">
                <input type="hidden" name="action" value="confirm">
                <button type="submit" class="cm-btn cm-btn-success cm-btn-sm">
                    <i class="bi bi-patch-check me-1"></i>Mark Accomplished
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!empty($task['description'])): ?>
        <div class="cm-detail-section">
            <div class="cm-detail-section-label">Description</div>
            <p class="cm-detail-section-text">
                <?php echo nl2br(htmlspecialchars($task['description'])); ?>
            </p>
        </div>
        <hr class="cm-divider">
        <?php endif; ?>

        <div class="cm-detail-section">
            <div class="cm-meta-grid">
                <div class="cm-meta-cell">
                    <div class="cm-meta-label">
                        <i class="bi bi-calendar-event me-1"></i>Due Date
                    </div>
                    <div class="cm-meta-value">
                        <?php echo date('F j, Y',strtotime($task['due_datetime'])); ?>
                    </div>
                    <div class="cm-meta-sub">
                        <?php echo date('g:i A',strtotime($task['due_datetime'])); ?>
                    </div>
                </div>

                <div class="cm-meta-cell <?php
                    echo $is_done?'cm-meta-success':($is_over?'cm-meta-danger':''); ?>">
                    <?php if ($is_done): ?>
                    <div class="cm-meta-label">
                        <i class="bi bi-check-circle me-1"></i>Completed On
                    </div>
                    <div class="cm-meta-value text-success">
                        <?php echo !empty($task['completed_at'])
                            ? date('F j, Y',strtotime($task['completed_at'])):'—'; ?>
                    </div>
                    <div class="cm-meta-sub">
                        <?php echo !empty($task['completed_at'])
                            ? date('g:i A',strtotime($task['completed_at'])):''; ?>
                    </div>
                    <?php elseif ($is_over): ?>
                    <div class="cm-meta-label text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>Overdue
                    </div>
                    <?php
                    $ago=$diff<0?abs($diff):0;
                    $d=floor($ago/86400); $h=floor(($ago%86400)/3600);
                    ?>
                    <div class="cm-meta-value text-danger">
                        <?php echo $d>0?"$d day".($d>1?'s':'')." ago"
                                       :"$h hour".($h>1?'s':'')." ago"; ?>
                    </div>
                    <?php else: ?>
                    <div class="cm-meta-label">
                        <i class="bi bi-hourglass me-1"></i>Time Remaining
                    </div>
                    <?php
                    $days=floor($diff/86400);
                    $hrs=floor(($diff%86400)/3600);
                    $mins=floor(($diff%3600)/60);
                    $rem=$days>0?"$days day".($days>1?'s':'')
                        :($hrs>0?"$hrs hour".($hrs>1?'s':'')
                             :"$mins min");
                    $rc=$days<1?'danger':($days<3?'warning':'success');
                    ?>
                    <div class="cm-meta-value text-<?php echo $rc; ?>">
                        <?php echo $rem; ?> remaining
                    </div>
                    <?php endif; ?>
                </div>

                <div class="cm-meta-cell">
                    <div class="cm-meta-label">
                        <i class="bi bi-person me-1"></i>Assigned To
                    </div>
                    <div class="cm-meta-value">
                        <?php echo !empty($task['user_id'])
                            ? htmlspecialchars($task['assigned_name']??$task['assigned_email'])
                            : 'Admin only'; ?>
                    </div>
                </div>

                <div class="cm-meta-cell">
                    <div class="cm-meta-label">
                        <i class="bi bi-clock-history me-1"></i>Created On
                    </div>
                    <div class="cm-meta-value">
                        <?php echo date('M j, Y',strtotime($task['created_at'])); ?>
                    </div>
                    <div class="cm-meta-sub">
                        <?php echo date('g:i A',strtotime($task['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="cm-detail-actions">
            <a href="tasks.php?filter=<?php echo $from; ?>"
               class="cm-btn cm-btn-ghost">
                <i class="bi bi-chevron-left me-1"></i>Back
            </a>
            <div class="d-flex gap-2">
                <?php if (!$is_done): ?>
                <a href="edit_task.php?id=<?php echo $task_id; ?>"
                   class="cm-btn cm-btn-primary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <?php endif; ?>
                <form method="POST"
                      onsubmit="return confirm('Delete this task permanently?')">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="cm-btn cm-btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>