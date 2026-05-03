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
$task_id  = (int)($_GET['id'] ?? 0);
if (!$task_id) { header("Location: tasks.php"); exit(); }

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'confirm') {
        $pdo->prepare("
            UPDATE tasks
            SET status_id = 3, completed_at = NOW()
            WHERE id = ? AND created_by = ?
        ")->execute([$task_id, $admin_id]);
        header("Location: task_detail.php?id=$task_id&from="
               . urlencode($_GET['from'] ?? 'all')
               . "&success=confirmed");
        exit();
    }
    if ($action === 'delete') {
        // Delete proof image if exists
        $pi = $pdo->prepare(
            "SELECT proof_image FROM tasks WHERE id=?");
        $pi->execute([$task_id]);
        $pi = $pi->fetchColumn();
        if ($pi) {
            $old = __DIR__ . '/../uploads/task_proofs/' . $pi;
            if (file_exists($old)) @unlink($old);
        }
        $pdo->prepare("DELETE FROM tasks WHERE id=? AND created_by=?")
            ->execute([$task_id, $admin_id]);
        header("Location: tasks.php?success=deleted"); exit();
    }
}

// Load task
$stmt = $pdo->prepare("
    SELECT t.*,
           ts.name    AS status_name,
           ts.id      AS status_id_val,
           u.username AS assigned_name,
           u.email    AS assigned_email
    FROM tasks t
    LEFT JOIN task_status ts ON t.status_id = ts.id
    LEFT JOIN users u        ON t.user_id   = u.id
    WHERE t.id = ? AND t.created_by = ?
    LIMIT 1
");
$stmt->execute([$task_id, $admin_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task) { header("Location: tasks.php?error=notfound"); exit(); }

$sid          = (int)($task['status_id_val'] ?? $task['status_id']);
$is_done      = ($sid === 3);
$is_conf      = ($sid === 2);
$is_over      = !$is_done && !$is_conf
                && strtotime($task['due_datetime']) < time();
$diff         = strtotime($task['due_datetime']) - time();
$from         = htmlspecialchars($_GET['from'] ?? 'all');
$status_label = match($sid){
    1=>'Pending',2=>'For Confirmation',3=>'Completed',default=>'Unknown'};
$status_cls   = match($sid){
    1=>'pending',2=>'confirm',3=>'done',default=>'pending'};

// Proof URL
$proof_url = (!empty($task['proof_image']))
    ? '../uploads/task_proofs/'
      . htmlspecialchars($task['proof_image'])
    : null;

$has_assignee = !empty($task['user_id']);

// HTML starts here
require_once '../includes/header.php';
?>

<div class="cm-breadcrumb mb-4">
    <a href="tasks.php?filter=<?php echo $from; ?>"
       class="cm-breadcrumb-link">
        <i class="bi bi-chevron-left me-1"></i>Tasks
    </a>
    <span class="cm-breadcrumb-sep">/</span>
    <span class="cm-breadcrumb-current">Task Detail</span>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-4">
    <i class="bi bi-check-circle me-2"></i>
    <?php echo $_GET['success'] === 'confirmed'
        ? 'Task marked as Completed!'
        : 'Done!'; ?>
    <button type="button" class="btn-close"
            data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="cm-detail-layout">
    <div class="cm-card">

        <!-- Header -->
        <div class="cm-detail-header">
            <div class="cm-detail-header-left">
                <div class="cm-detail-badges">
                    <span class="cm-tag cm-tag-neutral">
                        <i class="bi bi-shield-check me-1"></i>
                        Admin Task
                    </span>
                    <?php if ($has_assignee): ?>
                    <span class="cm-tag cm-tag-info">
                        <i class="bi bi-person me-1"></i>
                        Assigned to
                        <?php echo htmlspecialchars(
                            $task['assigned_name']
                            ?? $task['assigned_email']); ?>
                    </span>
                    <?php else: ?>
                    <span class="cm-tag cm-tag-muted">Unassigned</span>
                    <?php endif; ?>
                </div>
                <h2 class="cm-detail-title">
                    <?php echo htmlspecialchars($task['title']); ?>
                </h2>
            </div>
            <span class="cm-status-badge cm-status-<?php
                    echo $status_cls; ?> cm-status-lg">
                <?php echo $status_label; ?>
            </span>
        </div>

        <!-- For Confirmation notice + proof -->
        <?php if ($is_conf): ?>
        <div style="padding:16px 24px 0">
            <div class="cm-notice cm-notice-warning">
                <div class="cm-notice-body">
                    <i class="bi bi-hourglass-split cm-notice-icon"></i>
                    <div>
                        <div class="cm-notice-title">
                            Awaiting Your Confirmation
                        </div>
                        <div class="cm-notice-text">
                            <?php echo htmlspecialchars(
                                $task['assigned_name']
                                ?? $task['assigned_email']
                                ?? 'The member'); ?>
                            has marked this task as done.
                            <?php if ($proof_url): ?>
                            A proof image has been attached below.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <form method="POST" class="mt-3">
                    <input type="hidden" name="action"
                           value="confirm">
                    <button type="submit"
                            class="cm-btn cm-btn-success">
                        <i class="bi bi-patch-check me-1"></i>
                        Mark as Accomplished
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Description -->
        <?php if (!empty($task['description'])): ?>
        <div class="cm-detail-section">
            <div class="cm-detail-section-label">Description</div>
            <p class="cm-detail-section-text">
                <?php echo nl2br(
                    htmlspecialchars($task['description'])); ?>
            </p>
        </div>
        <hr class="cm-divider">
        <?php endif; ?>

        <!-- Meta grid -->
        <div class="cm-detail-section">
            <div class="cm-meta-grid">

                <div class="cm-meta-cell">
                    <div class="cm-meta-label">
                        <i class="bi bi-calendar-event me-1"></i>
                        Due Date
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
                        echo $is_done ? 'cm-meta-success'
                            : ($is_over ? 'cm-meta-danger' : ''); ?>">
                    <?php if ($is_done): ?>
                    <div class="cm-meta-label">
                        <i class="bi bi-check-circle me-1"></i>
                        Completed On
                    </div>
                    <div class="cm-meta-value text-success">
                        <?php echo !empty($task['completed_at'])
                            ? date('F j, Y',
                                strtotime($task['completed_at']))
                            : '—'; ?>
                    </div>
                    <div class="cm-meta-sub">
                        <?php echo !empty($task['completed_at'])
                            ? date('g:i A',
                                strtotime($task['completed_at']))
                            : ''; ?>
                    </div>
                    <?php elseif ($is_over): ?>
                    <div class="cm-meta-label text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Overdue
                    </div>
                    <?php
                    $ago = abs($diff);
                    $d   = floor($ago/86400);
                    $h   = floor(($ago%86400)/3600);
                    ?>
                    <div class="cm-meta-value text-danger">
                        <?php echo $d>0
                            ?"$d day".($d>1?'s':'')." ago"
                            :"$h hour".($h>1?'s':'')." ago"; ?>
                    </div>
                    <?php else: ?>
                    <div class="cm-meta-label">
                        <i class="bi bi-hourglass me-1"></i>
                        Time Remaining
                    </div>
                    <?php
                    $days = floor($diff/86400);
                    $hrs  = floor(($diff%86400)/3600);
                    $mins = floor(($diff%3600)/60);
                    $rem  = $days>0
                        ? "$days day".($days>1?'s':'')
                        : ($hrs>0
                            ? "$hrs hour".($hrs>1?'s':'')
                            : "$mins min");
                    $rc = $days<1?'danger':($days<3?'warning':'success');
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
                        <?php echo $has_assignee
                            ? htmlspecialchars(
                                $task['assigned_name']
                                ?? $task['assigned_email'])
                            : 'Admin only'; ?>
                    </div>
                </div>

                <div class="cm-meta-cell">
                    <div class="cm-meta-label">
                        <i class="bi bi-clock-history me-1"></i>
                        Created On
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

        <!-- ═══════════════════════════════════════════════
             PROOF OF COMPLETION — ADMIN VIEW
             ═══════════════════════════════════════════════ -->
        <?php if ($has_assignee): ?>
        <hr class="cm-divider">
        <div class="cm-detail-section">
            <div class="cm-detail-section-label">
                <i class="bi bi-image me-1"></i>
                Proof of Completion
            </div>

            <?php if ($proof_url): ?>
            <!-- Proof image attached -->
            <div class="proof-container">
                <div class="proof-preview-wrap"
                     onclick="openProofModal(this.querySelector('img').src)">
                    <img src="<?php echo $proof_url; ?>"
                         alt="Proof of completion"
                         class="proof-thumbnail"
                         loading="lazy">
                    <div class="proof-overlay">
                        <i class="bi bi-zoom-in"></i>
                        View Full Size
                    </div>
                </div>
                <div class="proof-info">
                    <div class="proof-info-title mb-2">
                        <span class="cm-tag cm-tag-<?php
                            echo $is_done?'success':'warning'; ?>">
                            <i class="bi bi-<?php
                                echo $is_done
                                    ?'check-circle':'hourglass-split'; ?>
                                me-1"></i>
                            <?php echo $is_done
                                ?'Accepted proof'
                                :'Proof submitted'; ?>
                        </span>
                    </div>
                    <p class="proof-info-text">
                        <?php echo htmlspecialchars(
                            $task['assigned_name']
                            ?? $task['assigned_email']
                            ?? 'The member'); ?>
                        attached this image as proof of task completion.
                        <?php if ($is_conf): ?>
                        Click the image to view it in full size,
                        then confirm or keep it pending.
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo $proof_url; ?>"
                       download
                       class="cm-btn cm-btn-ghost cm-btn-sm mt-2">
                        <i class="bi bi-download me-1"></i>
                        Download Image
                    </a>
                </div>
            </div>

            <?php else: ?>
            <!-- No proof attached -->
            <div class="cm-notice cm-notice-info">
                <div class="cm-notice-body">
                    <i class="bi bi-image cm-notice-icon"
                       style="color:var(--info)"></i>
                    <div>
                        <div class="cm-notice-title">
                            No Proof Attached
                        </div>
                        <div class="cm-notice-text">
                            <?php if ($is_conf): ?>
                            The member submitted without attaching
                            a proof image.
                            <?php else: ?>
                            The member can attach a proof image
                            when marking this task as done.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>

        <!-- Action bar -->
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
                      data-confirm="Delete this task permanently?"
                      data-confirm-type="danger">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="cm-btn cm-btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Full-size proof image modal -->
<div class="modal fade" id="proofImageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-image me-2"
                       style="color:var(--primary)"></i>
                    Proof of Completion
                </h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 text-center"
                 style="background:#1a1a2e">
                <img id="proofModalImg"
                     src=""
                     alt="Proof"
                     style="max-width:100%;max-height:75vh;
                            object-fit:contain;display:block;
                            margin:0 auto">
            </div>
            <div class="modal-footer justify-content-between">
                <small class="text-muted">
                    <i class="bi bi-person-circle me-1"></i>
                    Submitted by
                    <?php echo htmlspecialchars(
                        $task['assigned_name']
                        ?? $task['assigned_email']
                        ?? 'Member'); ?>
                </small>
                <div class="d-flex gap-2">
                    <?php if ($proof_url): ?>
                    <a href="<?php echo $proof_url; ?>"
                       download
                       class="cm-btn cm-btn-ghost cm-btn-sm">
                        <i class="bi bi-download me-1"></i>Download
                    </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.proof-upload-area {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 32px 20px;
    text-align: center;
    cursor: pointer;
    transition: all var(--transition);
    background: #fafbfc;
}

.proof-upload-area:hover,
.proof-upload-area.drag-over {
    border-color: var(--primary);
    background: var(--primary-light);
}

.proof-upload-icon {
    font-size: 40px;
    color: var(--text-light);
    margin-bottom: 12px;
}

.proof-upload-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-main);
    margin-bottom: 6px;
}

.proof-upload-subtitle {
    font-size: 12px;
    color: var(--text-muted);
    line-height: 1.6;
}

.proof-preview-img {
    max-width: 100%;
    max-height: 240px;
    border-radius: var(--radius-sm);
    object-fit: contain;
}

.proof-container {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    flex-wrap: wrap;
}

.proof-preview-wrap {
    position: relative;
    border-radius: var(--radius);
    overflow: hidden;
    flex-shrink: 0;
    cursor: pointer;
    width: 160px;
    height: 120px;
    border: 1px solid var(--border);
    background: #f1f2f6;
}

.proof-preview-wrap img.proof-thumbnail {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .2s ease;
}

.proof-preview-wrap:hover img.proof-thumbnail {
    transform: scale(1.04);
}

.proof-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.5);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    color: #fff;
    font-size: 12px;
    font-weight: 500;
    opacity: 0;
    transition: opacity .2s ease;
}

.proof-overlay i { font-size: 20px; }

.proof-preview-wrap:hover .proof-overlay { opacity: 1; }

.proof-info { flex: 1; min-width: 0; }
.proof-info-title { margin-bottom: 6px; }
.proof-info-text {
    font-size: 12px;
    color: var(--text-muted);
    line-height: 1.5;
    margin: 0;
}

@media (max-width: 575.98px) {
    .proof-container  { flex-direction: column; }
    .proof-preview-wrap { width: 100%; height: 180px; }
}
</style>

<script>
function openProofModal(src) {
    document.getElementById('proofModalImg').src = src;
    new bootstrap.Modal(
        document.getElementById('proofImageModal')
    ).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>