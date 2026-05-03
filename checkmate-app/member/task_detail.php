<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php"); exit();
}
if ((int)$_SESSION['role_id'] === 1) {
    header("Location: ../admin/home.php"); exit();
}

$member_id = (int)$_SESSION['user_id'];
$task_id   = (int)($_GET['id'] ?? 0);
if (!$task_id) { header("Location: tasks.php"); exit(); }

// Load task
$stmt = $pdo->prepare("
    SELECT t.*,
           ts.name     AS status_name,
           ts.id       AS status_id_val,
           tc.name     AS category_name,
           tc.color    AS category_color,
           cb.username AS creator_name,
           cb.email    AS creator_email
    FROM tasks t
    LEFT JOIN task_status     ts ON t.status_id   = ts.id
    LEFT JOIN task_categories tc ON t.category_id = tc.id
    LEFT JOIN users           cb ON t.created_by  = cb.id
    WHERE t.id = ?
      AND (t.created_by = ? OR t.user_id = ?)
    LIMIT 1
");
$stmt->execute([$task_id, $member_id, $member_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task) { header("Location: tasks.php?error=notfound"); exit(); }

$is_own     = ((int)$task['created_by'] === $member_id);
$is_assigned= (!$is_own && (int)$task['user_id'] === $member_id);
$sid        = (int)($task['status_id_val'] ?? $task['status_id']);
$is_done    = ($sid === 3);
$is_conf    = ($sid === 2);
$is_pend    = ($sid === 1);
$is_over    = !$is_done && !$is_conf && strtotime($task['due_datetime'])<time();
$diff       = strtotime($task['due_datetime']) - time();
$filter     = htmlspecialchars($_GET['from'] ?? ($is_own ? 'private' : 'assigned'));

$status_label = match($sid){
    1=>'Pending', 2=>'For Confirmation', 3=>'Completed', default=>'Unknown'};
$status_cls = match($sid){
    1=>'pending', 2=>'confirm', 3=>'done', default=>'pending'};

// ── POST HANDLERS ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── UPLOAD PROOF ──────────────────────────────────────
    if ($action === 'upload_proof' && $is_assigned && $is_pend) {
        $upload_error = '';

        if (empty($_FILES['proof_image']['name'])) {
            $upload_error = 'Please select an image file.';
        } else {
            $file     = $_FILES['proof_image'];
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','gif','webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($ext, $allowed)) {
                $upload_error = 'Only image files are allowed (JPG, PNG, GIF, WEBP).';
            } elseif ($file['size'] > $max_size) {
                $upload_error = 'Image must be smaller than 5MB.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $upload_error = 'Upload failed. Please try again.';
            } else {
                // Verify it's actually an image
                $img_info = @getimagesize($file['tmp_name']);
                if (!$img_info) {
                    $upload_error = 'Invalid image file.';
                }
            }

            if (!$upload_error) {
                // Delete old proof if exists
                if (!empty($task['proof_image'])) {
                    $old = __DIR__ . '/../uploads/task_proofs/'
                           . $task['proof_image'];
                    if (file_exists($old)) @unlink($old);
                }

                $filename  = 'proof_' . $task_id . '_'
                             . time() . '.' . $ext;
                $upload_dir= __DIR__ . '/../uploads/task_proofs/';

                // Create folder if missing
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'],
                                       $upload_dir . $filename)) {
                    // Save filename and set status to For Confirmation
                    $pdo->prepare("
                        UPDATE tasks
                        SET proof_image = ?,
                            status_id   = 2
                        WHERE id = ? AND user_id = ?
                    ")->execute([$filename, $task_id, $member_id]);

                    header("Location: task_detail.php?id=$task_id"
                           . "&from=$filter&success=proof_uploaded");
                    exit();
                } else {
                    $upload_error = 'Could not save the file. '
                                  . 'Check server permissions.';
                }
            }
        }
    }

    // ── MARK DONE (private tasks only) ────────────────────
    if ($action === 'mark_done' && $is_own && $is_pend) {
        $pdo->prepare("
            UPDATE tasks
            SET status_id = 3, completed_at = NOW()
            WHERE id = ? AND created_by = ?
        ")->execute([$task_id, $member_id]);
        header("Location: task_detail.php?id=$task_id"
               . "&from=$filter&success=done");
        exit();
    }

    // ── DELETE (own tasks only) ───────────────────────────
    if ($action === 'delete' && $is_own) {
        // Delete proof image if exists
        if (!empty($task['proof_image'])) {
            $old = __DIR__ . '/../uploads/task_proofs/'
                   . $task['proof_image'];
            if (file_exists($old)) @unlink($old);
        }
        $pdo->prepare("DELETE FROM tasks WHERE id=? AND created_by=?")
            ->execute([$task_id, $member_id]);
        header("Location: tasks.php?filter=$filter&success=deleted");
        exit();
    }
}

// Proof image URL for display
$proof_url = (!empty($task['proof_image']))
    ? '../uploads/task_proofs/' . htmlspecialchars($task['proof_image'])
    : null;

// HTML starts here
require_once '../includes/header.php';
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
    <?php echo match($_GET['success']) {
        'done'          => 'Task marked as completed!',
        'proof_uploaded'=> 'Proof submitted! Waiting for admin confirmation.',
        default         => 'Done!'
    }; ?>
    <button type="button" class="btn-close"
            data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($upload_error)): ?>
<div class="alert alert-danger alert-dismissible fade show mb-4">
    <i class="bi bi-exclamation-circle me-2"></i>
    <?php echo htmlspecialchars($upload_error); ?>
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
                    <?php if ($is_own): ?>
                    <span class="cm-tag cm-tag-neutral">
                        <i class="bi bi-lock me-1"></i>Private
                    </span>
                    <?php else: ?>
                    <span class="cm-tag cm-tag-warning">
                        <i class="bi bi-person me-1"></i>
                        Assigned by
                        <?php echo htmlspecialchars(
                            $task['creator_name'] ?? 'Admin'); ?>
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
                        <?php echo htmlspecialchars(
                            $task['category_name']); ?>
                    </span>
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
                        <?php echo $d > 0
                            ? "$d day".($d>1?'s':'')." ago"
                            : "$h hour".($h>1?'s':'')." ago"; ?>
                    </div>
                    <?php elseif ($is_conf): ?>
                    <div class="cm-meta-label">
                        <i class="bi bi-hourglass-split me-1"></i>
                        Status
                    </div>
                    <div class="cm-meta-value"
                         style="color:var(--warning)">
                        Waiting for admin
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
                        <i class="bi bi-person me-1"></i>
                        <?php echo $is_own
                            ? 'Created by' : 'Assigned by'; ?>
                    </div>
                    <div class="cm-meta-value">
                        <?php echo $is_own ? 'You'
                            : htmlspecialchars(
                                $task['creator_name'] ?? 'Admin'); ?>
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
             PROOF OF COMPLETION SECTION
             Only shown for assigned tasks
             ═══════════════════════════════════════════════ -->
        <?php if ($is_assigned): ?>
        <hr class="cm-divider">
        <div class="cm-detail-section">
            <div class="cm-detail-section-label">
                <i class="bi bi-image me-1"></i>
                Proof of Completion
            </div>

            <?php if ($proof_url): ?>
            <!-- Proof already uploaded -->
            <div class="proof-container">
                <div class="proof-preview-wrap">
                    <img src="<?php echo $proof_url; ?>"
                         alt="Proof of completion"
                         class="proof-thumbnail"
                         id="proofThumb"
                         onclick="openProofModal(this.src)"
                         loading="lazy">
                    <div class="proof-overlay"
                         onclick="openProofModal(
                             document.getElementById('proofThumb').src)">
                        <i class="bi bi-zoom-in"></i>
                        View Full Size
                    </div>
                </div>
                <div class="proof-info">
                    <div class="proof-info-title">
                        <?php if ($is_conf): ?>
                        <span class="cm-tag cm-tag-warning">
                            <i class="bi bi-hourglass-split me-1"></i>
                            Awaiting admin confirmation
                        </span>
                        <?php elseif ($is_done): ?>
                        <span class="cm-tag cm-tag-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Proof accepted
                        </span>
                        <?php else: ?>
                        <span class="cm-tag cm-tag-info">
                            <i class="bi bi-image me-1"></i>
                            Proof attached
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="proof-info-text">
                        <?php if ($is_pend): ?>
                        You can replace the image with a new one.
                        <?php elseif ($is_conf): ?>
                        Your proof has been submitted and is waiting
                        for admin review.
                        <?php else: ?>
                        This proof was accepted by your admin.
                        <?php endif; ?>
                    </p>
                    <!-- Allow re-upload if still pending -->
                    <?php if ($is_pend): ?>
                    <label for="replaceProof"
                           class="cm-btn cm-btn-ghost cm-btn-sm"
                           style="cursor:pointer;margin-top:8px">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        Replace Image
                    </label>
                    <form method="POST"
                          enctype="multipart/form-data"
                          id="replaceProofForm">
                        <input type="hidden" name="action"
                               value="upload_proof">
                        <input type="file"
                               name="proof_image"
                               id="replaceProof"
                               accept="image/*"
                               class="d-none"
                               onchange="document.getElementById(
                                   'replaceProofForm').submit()">
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($is_pend): ?>
            <!-- No proof yet — show upload area -->
            <form method="POST"
                  enctype="multipart/form-data"
                  id="proofUploadForm">
                <input type="hidden" name="action" value="upload_proof">

                <div class="proof-upload-area"
                     id="proofDropZone"
                     onclick="document.getElementById(
                         'proofFileInput').click()"
                     ondragover="event.preventDefault();
                         this.classList.add('drag-over')"
                     ondragleave="this.classList.remove('drag-over')"
                     ondrop="handleProofDrop(event)">

                    <input type="file"
                           name="proof_image"
                           id="proofFileInput"
                           accept="image/*"
                           class="d-none"
                           onchange="previewProof(this)">

                    <div id="proofUploadPlaceholder">
                        <div class="proof-upload-icon">
                            <i class="bi bi-cloud-upload"></i>
                        </div>
                        <div class="proof-upload-title">
                            Attach Proof of Completion
                        </div>
                        <div class="proof-upload-subtitle">
                            Click to browse or drag & drop an image<br>
                            <small>JPG, PNG, GIF, WEBP · Max 5MB</small>
                        </div>
                    </div>

                    <!-- Preview before submit -->
                    <div id="proofPreviewContainer"
                         class="d-none text-center">
                        <img id="proofPreviewImg"
                             src=""
                             alt="Preview"
                             class="proof-preview-img">
                        <div class="proof-upload-subtitle mt-2">
                            <span id="proofFileName"></span>
                        </div>
                    </div>
                </div>

                <div id="proofSubmitBar"
                     class="d-none mt-3"
                     style="display:flex;gap:10px;align-items:center">
                    <button type="submit"
                            class="cm-btn cm-btn-success">
                        <i class="bi bi-cloud-upload me-1"></i>
                        Upload & Submit for Confirmation
                    </button>
                    <button type="button"
                            class="cm-btn cm-btn-ghost"
                            onclick="cancelProofPreview()">
                        Cancel
                    </button>
                </div>
            </form>

            <?php elseif ($is_conf): ?>
            <!-- Submitted, awaiting confirmation, no file stored -->
            <div class="cm-notice cm-notice-warning">
                <div class="cm-notice-body">
                    <i class="bi bi-hourglass-split cm-notice-icon"></i>
                    <div>
                        <div class="cm-notice-title">
                            Submitted for Confirmation
                        </div>
                        <div class="cm-notice-text">
                            Your task has been submitted and is waiting
                            for admin review.
                        </div>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- Completed -->
            <div class="cm-notice cm-notice-success">
                <div class="cm-notice-body">
                    <i class="bi bi-check-circle cm-notice-icon"></i>
                    <div>
                        <div class="cm-notice-title">
                            Task Completed
                        </div>
                        <div class="cm-notice-text">
                            This task has been confirmed as completed
                            by your admin.
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>

        <!-- Action bar -->
        <div class="cm-detail-actions">
            <a href="tasks.php?filter=<?php echo $filter; ?>"
               class="cm-btn cm-btn-ghost">
                <i class="bi bi-chevron-left me-1"></i>Back
            </a>
            <div class="d-flex gap-2 flex-wrap">
                <!-- Mark as done — private tasks only -->
                <?php if ($is_own && $is_pend): ?>
                <form method="POST">
                    <input type="hidden" name="action"
                           value="mark_done">
                    <button type="submit"
                            class="cm-btn cm-btn-success">
                        <i class="bi bi-check-circle me-1"></i>
                        Mark as Done
                    </button>
                </form>
                <?php endif; ?>
                <!-- Edit — own pending tasks only -->
                <?php if ($is_own && $is_pend): ?>
                <a href="tasks.php?edit=<?php echo $task['id'];
                    ?>&filter=<?php echo $filter; ?>"
                   class="cm-btn cm-btn-primary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <?php endif; ?>
                <!-- Delete — own tasks only -->
                <?php if ($is_own): ?>
                <form method="POST"
                      data-confirm="Delete this task permanently?"
                      data-confirm-type="danger">
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

<!-- ── PROOF IMAGE FULL-SIZE MODAL ── -->
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
                     alt="Proof of completion"
                     style="max-width:100%;max-height:75vh;
                            object-fit:contain;display:block;
                            margin:0 auto">
            </div>
            <div class="modal-footer justify-content-between">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Submitted by
                    <?php echo htmlspecialchars(
                        $_SESSION['username'] ?? 'Member'); ?>
                </small>
                <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* ── PROOF UPLOAD AREA ──────────────────────────────────── */
.proof-upload-area {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 32px 20px;
    text-align: center;
    cursor: pointer;
    transition: all var(--transition);
    background: #fafbfc;
    position: relative;
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
    line-height: 1;
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

/* ── PROOF PREVIEW (after upload) ──────────────────────── */
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
    cursor: pointer;
}

.proof-overlay i { font-size: 20px; }

.proof-preview-wrap:hover .proof-overlay {
    opacity: 1;
}

.proof-info {
    flex: 1;
    min-width: 0;
}

.proof-info-title { margin-bottom: 6px; }

.proof-info-text {
    font-size: 12px;
    color: var(--text-muted);
    line-height: 1.5;
    margin: 0;
}

/* ── Responsive ─────────────────────────────────────────── */
@media (max-width: 575.98px) {
    .proof-container {
        flex-direction: column;
    }
    .proof-preview-wrap {
        width: 100%;
        height: 180px;
    }
}
</style>

<script>
// ── PROOF IMAGE PREVIEW BEFORE UPLOAD ────────────────────
function previewProof(input) {
    if (!input.files || !input.files[0]) return;
    const file   = input.files[0];
    const reader = new FileReader();

    reader.onload = function(e) {
        document.getElementById('proofUploadPlaceholder')
            .classList.add('d-none');
        document.getElementById('proofPreviewContainer')
            .classList.remove('d-none');
        document.getElementById('proofPreviewImg').src = e.target.result;
        document.getElementById('proofFileName').textContent = file.name;

        const bar = document.getElementById('proofSubmitBar');
        bar.classList.remove('d-none');
        bar.style.display = 'flex';
    };
    reader.readAsDataURL(file);
}

// ── DRAG AND DROP ─────────────────────────────────────────
function handleProofDrop(event) {
    event.preventDefault();
    document.getElementById('proofDropZone')
        .classList.remove('drag-over');

    const files = event.dataTransfer.files;
    if (files && files[0]) {
        const input = document.getElementById('proofFileInput');
        // Use DataTransfer to assign dropped files to input
        try {
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            input.files = dt.files;
        } catch(e) {
            // Fallback for browsers that don't support DataTransfer
        }
        previewProof(input);
    }
}

// ── CANCEL PREVIEW ────────────────────────────────────────
function cancelProofPreview() {
    document.getElementById('proofFileInput').value = '';
    document.getElementById('proofUploadPlaceholder')
        .classList.remove('d-none');
    document.getElementById('proofPreviewContainer')
        .classList.add('d-none');
    document.getElementById('proofSubmitBar')
        .classList.add('d-none');
}

// ── OPEN FULL-SIZE MODAL ──────────────────────────────────
function openProofModal(src) {
    document.getElementById('proofModalImg').src = src;
    const modal = new bootstrap.Modal(
        document.getElementById('proofImageModal')
    );
    modal.show();
}
</script>

<?php require_once '../includes/footer.php'; ?>