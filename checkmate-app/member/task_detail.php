<?php
// ── POST LOGIC BEFORE ANY OUTPUT ─────────────────────────
$base_dir = dirname(__DIR__);
require_once $base_dir.'/config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php"); exit();
}
if ($_SESSION['role_id'] == 1) {
    header("Location: ../admin/tasks.php"); exit();
}

$member_id = (int)$_SESSION['user_id'];
$task_id   = (int)($_GET['id'] ?? 0);
if (!$task_id) { header("Location: tasks.php"); exit(); }

$stmt = $pdo->prepare("
    SELECT t.*,
           ts.name     AS status_name,
           ts.id       AS status_id_val,
           tc.name     AS category_name,
           tc.color    AS category_color,
           cb.username AS creator_name,
           cb.email    AS creator_email
    FROM tasks t
    LEFT JOIN task_status     ts ON t.status_id=ts.id
    LEFT JOIN task_categories tc ON t.category_id=tc.id
    LEFT JOIN users           cb ON t.created_by=cb.id
    WHERE t.id=?
      AND (t.created_by=? OR t.user_id=?)
    LIMIT 1
");
$stmt->execute([$task_id,$member_id,$member_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task) { header("Location: tasks.php?error=notfound"); exit(); }

$is_own  = ((int)$task['created_by']===$member_id);
$sid     = (int)($task['status_id_val'] ?? $task['status_id']);
$is_done = ($sid===3);
$is_conf = ($sid===2);
$is_over = !$is_done && !$is_conf && strtotime($task['due_datetime'])<time();
$diff    = strtotime($task['due_datetime'])-time();
$filter  = htmlspecialchars($_GET['from'] ?? ($is_own?'private':'assigned'));

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    if ($action==='mark_done') {
        if ($is_own) {
            $pdo->prepare("
                UPDATE tasks SET status_id=3, completed_at=NOW()
                WHERE id=? AND created_by=?
            ")->execute([$task_id,$member_id]);
        } else {
            $pdo->prepare("
                UPDATE tasks SET status_id=2 WHERE id=? AND status_id=1
            ")->execute([$task_id]);
        }
        header("Location: task_detail.php?id=$task_id&from=$filter&success=done");
        exit();
    }
    if ($action==='delete' && $is_own) {
        $pdo->prepare("DELETE FROM tasks WHERE id=? AND created_by=?")
            ->execute([$task_id,$member_id]);
        header("Location: tasks.php?filter=$filter&success=deleted");
        exit();
    }
}

$status_label = match($sid){
    1=>'Pending',2=>'For Confirmation',3=>'Completed',default=>'Unknown'};
$status_cls = match($sid){
    1=>'pending',2=>'confirm',3=>'done',default=>'pending'};

// ── NOW output HTML ───────────────────────────────────────
require_once $base_dir.'/includes/header.php';
?>

<div class="cm-breadcrumb mb-4">
    <a href="tasks.php?filter=<?php echo $filter; ?>"
       class="cm-breadcrumb-link">
        <i class="bi bi-chevron-left me-1"></i>Tasks
    </a>
    <span class="cm-breadcrumb-sep">/</span>
    <span class="cm-breadcrumb-current">Task Detail</span>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-4">
    <i class="bi bi-check-circle me-2"></i>
    <?php echo $is_own
        ?'Task marked as completed!'
        :'Submitted for admin confirmation.'; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="cm-detail-layout">
    <div class="cm-card">

        <div class="cm-detail-header">
            <div class="cm-detail-header-left">
                <div class="cm-detail-badges">
                    <?php if ($is_own): ?>
                    <span class="cm-tag cm-tag-neutral">
                        <i class="bi bi-lock me-1"></i>Private
                    </span>
                    <?php else: ?>
                    <span class="cm-tag cm-tag-warning">
                        <i class="bi bi-person me-1"></i>
                        Assigned by
                        <?php echo htmlspecialchars(
                            $task['creator_name']??'Admin'); ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($task['priority'])): ?>
                    <span class="cm-tag cm-tag-danger">
                        Priority <?php echo (int)$task['priority']; ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($task['category_color'])): ?>
                    <span class="cm-tag"
                          style="background:<?php echo htmlspecialchars(
                              $task['category_color']); ?>20;
                                 color:<?php echo htmlspecialchars(
                              $task['category_color']); ?>;
                                 border:1px solid <?php echo htmlspecialchars(
                              $task['category_color']); ?>40">
                        <?php echo htmlspecialchars($task['category_name']); ?>
                    </span>
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
                        <?php echo date('F j, Y',
                            strtotime($task['due_datetime'])); ?>
                    </div>
                    <div class="cm-meta-sub">
                        <?php echo date('g:i A',
                            strtotime($task['due_datetime'])); ?>
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
                            ?date('F j, Y',strtotime($task['completed_at'])):'—'; ?>
                    </div>
                    <div class="cm-meta-sub">
                        <?php echo !empty($task['completed_at'])
                            ?date('g:i A',strtotime($task['completed_at'])):''; ?>
                    </div>
                    <?php elseif ($is_over): ?>
                    <div class="cm-meta-label text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>Overdue
                    </div>
                    <?php
                    $ago=abs($diff);
                    $d=floor($ago/86400);$h=floor(($ago%86400)/3600);
                    ?>
                    <div class="cm-meta-value text-danger">
                        <?php echo $d>0?"$d day".($d>1?'s':'')." ago"
                                       :"$h hour".($h>1?'s':'')." ago"; ?>
                    </div>
                    <?php elseif ($is_conf): ?>
                    <div class="cm-meta-label">
                        <i class="bi bi-hourglass-split me-1"></i>Status
                    </div>
                    <div class="cm-meta-value"
                         style="color:var(--warning)">
                        Waiting for admin
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
                        <i class="bi bi-person me-1"></i>
                        <?php echo $is_own?'Created by':'Assigned by'; ?>
                    </div>
                    <div class="cm-meta-value">
                        <?php echo $is_own?'You'
                            :htmlspecialchars(
                                $task['creator_name']??'Admin'); ?>
                    </div>
                </div>

                <div class="cm-meta-cell">
                    <div class="cm-meta-label">
                        <i class="bi bi-clock-history me-1"></i>Created On
                    </div>
                    <div class="cm-meta-value">
                        <?php echo date('M j, Y',
                            strtotime($task['created_at'])); ?>
                    </div>
                    <div class="cm-meta-sub">
                        <?php echo date('g:i A',
                            strtotime($task['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="cm-detail-actions">
            <a href="tasks.php?filter=<?php echo $filter; ?>"
               class="cm-btn cm-btn-ghost">
                <i class="bi bi-chevron-left me-1"></i>Back
            </a>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($sid===1): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_done">
                    <button type="submit"
                            class="cm-btn cm-btn-success">
                        <i class="bi bi-check-circle me-1"></i>
                        <?php echo $is_own
                            ?'Mark as Done'
                            :'Submit for Confirmation'; ?>
                    </button>
                </form>
                <?php endif; ?>
                <?php if ($is_own && $sid===1): ?>
                <a href="tasks.php?edit=<?php echo $task['id'];
                    ?>&filter=<?php echo $filter; ?>"
                   class="cm-btn cm-btn-primary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <?php endif; ?>
                <?php if ($is_own): ?>
                <form method="POST"
                      onsubmit="return confirm('Delete this task permanently?')">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="cm-btn cm-btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once $base_dir.'/includes/footer.php'; ?>