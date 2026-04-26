<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php"); exit();
}
if ((int)$_SESSION['role_id'] !== 1) {
    header("Location: ../member/home.php"); exit();
}

$admin_id   = (int)$_SESSION['user_id'];
$save_error = '';

// All POST handling before HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? 'save';
    $task_id = (int)($_POST['id'] ?? 0);

    $own = $pdo->prepare(
        "SELECT id FROM tasks WHERE id=? AND created_by=?");
    $own->execute([$task_id, $admin_id]);
    if (!$own->fetch()) {
        header("Location: tasks.php?error=unauthorized"); exit();
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM tasks WHERE id=? AND created_by=?")
            ->execute([$task_id, $admin_id]);
        header("Location: tasks.php?success=deleted"); exit();
    }

    if ($action === 'confirm') {
        $pdo->prepare("
            UPDATE tasks SET status_id=3, completed_at=NOW()
            WHERE id=? AND created_by=?
        ")->execute([$task_id, $admin_id]);
        header("Location: tasks.php?success=confirmed"); exit();
    }

    if ($action === 'save') {
        $title       = trim($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $due         = $_POST['due_datetime']      ?? '';
        $assigned_to = !empty($_POST['user_id'])
                       ? (int)$_POST['user_id'] : null;

        if (empty($title) || empty($due)) {
            $save_error = "Title and due date are required.";
        } else {
            $pdo->prepare("
                UPDATE tasks
                SET title=?,description=?,due_datetime=?,user_id=?
                WHERE id=? AND created_by=?
            ")->execute([
                $title,$description,$due,
                $assigned_to,$task_id,$admin_id
            ]);
            header("Location: tasks.php?success=updated"); exit();
        }
    }
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT t.*, ts.name AS status,
           u.email AS assigned_email,
           u.username AS assigned_name
    FROM tasks t
    LEFT JOIN task_status ts ON t.status_id = ts.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.id=? AND t.created_by=?
");
$stmt->execute([$id, $admin_id]);
$task = $stmt->fetch();
if (!$task) { header("Location: tasks.php?error=notfound"); exit(); }

$members = $pdo->query("
    SELECT id, email, username FROM users
    WHERE role_id=2 ORDER BY username, email
")->fetchAll();

$is_done = ((int)$task['status_id'] === 3);
$is_conf = ((int)$task['status_id'] === 2);

$form = [
    'title'        => $save_error
                      ? ($_POST['title']??$task['title'])
                      : $task['title'],
    'description'  => $save_error
                      ? ($_POST['description']??$task['description'])
                      : $task['description'],
    'due_datetime' => $save_error
                      ? ($_POST['due_datetime']
                         ??date('Y-m-d\TH:i',
                             strtotime($task['due_datetime'])))
                      : date('Y-m-d\TH:i',strtotime($task['due_datetime'])),
    'user_id'      => $save_error
                      ? ($_POST['user_id']??$task['user_id'])
                      : $task['user_id'],
];

// HTML starts here
require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="cm-breadcrumb mb-4">
            <a href="tasks.php" class="cm-breadcrumb-link">
                <i class="bi bi-chevron-left me-1"></i>Tasks
            </a>
            <span class="cm-breadcrumb-sep">/</span>
            <span class="cm-breadcrumb-current">
                <?php echo $is_done?'View Task':'Edit Task'; ?>
            </span>
        </div>

        <div class="cm-card">
            <div class="cm-card-header">
                <div class="cm-card-title">
                    <?php echo $is_done?'View Completed Task':'Edit Task'; ?>
                </div>
            </div>
            <div class="cm-card-body">

                <?php if ($save_error): ?>
                <div class="alert alert-danger mb-4">
                    <?php echo htmlspecialchars($save_error); ?>
                </div>
                <?php endif; ?>

                <?php if ($is_conf): ?>
                <div class="cm-notice cm-notice-warning mb-4">
                    <div class="cm-notice-body">
                        <i class="bi bi-hourglass-split cm-notice-icon"></i>
                        <div>
                            <div class="cm-notice-title">
                                Awaiting Confirmation
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="edit_task.php" class="mt-3">
                        <input type="hidden" name="action" value="confirm">
                        <input type="hidden" name="id"
                               value="<?php echo (int)$task['id']; ?>">
                        <button type="submit" class="cm-btn cm-btn-success">
                            <i class="bi bi-patch-check me-1"></i>
                            Mark Accomplished
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($is_done): ?>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <h4 class="fw-bold">
                            <?php echo htmlspecialchars($task['title']); ?>
                        </h4>
                    </div>
                    <div class="col-md-4">
                        <div class="cm-meta-cell">
                            <div class="cm-meta-label">Due</div>
                            <div class="cm-meta-value">
                                <?php echo date('M j, Y H:i',
                                    strtotime($task['due_datetime'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="cm-meta-cell cm-meta-success">
                            <div class="cm-meta-label">Completed</div>
                            <div class="cm-meta-value text-success">
                                <?php echo !empty($task['completed_at'])
                                    ?date('M j, Y H:i',
                                        strtotime($task['completed_at']))
                                    :'—'; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <hr style="margin:20px 0">
                <div class="d-flex justify-content-between">
                    <a href="tasks.php" class="cm-btn cm-btn-ghost">
                        <i class="bi bi-chevron-left me-1"></i>Back
                    </a>
                    <form method="POST" action="edit_task.php"
                          data-confirm="Delete this completed task?"
                          data-confirm-type="danger">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id"
                               value="<?php echo (int)$task['id']; ?>">
                        <button type="submit" class="cm-btn cm-btn-danger">
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                    </form>
                </div>

                <?php else: ?>
                <form method="POST" action="edit_task.php">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id"
                           value="<?php echo (int)$task['id']; ?>">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="mb-4">
                                <label class="form-label">Task Title *</label>
                                <input type="text" name="title"
                                       class="form-control form-control-lg"
                                       required
                                       value="<?php echo htmlspecialchars(
                                           $form['title']); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Description</label>
                                <textarea name="description"
                                          class="form-control"
                                          rows="5"><?php
                                    echo htmlspecialchars(
                                        $form['description']??'');
                                ?></textarea>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="mb-4">
                                <label class="form-label">
                                    Due Date & Time *
                                </label>
                                <input type="datetime-local"
                                       name="due_datetime"
                                       class="form-control form-control-lg"
                                       required
                                       value="<?php echo htmlspecialchars(
                                           $form['due_datetime']); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Assign To</label>
                                <select name="user_id"
                                        class="form-select form-select-lg">
                                    <option value="">Admin only</option>
                                    <?php foreach ($members as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"
                                        <?php echo (int)$form['user_id']
                                            ===(int)$m['id']
                                            ?'selected':''; ?>>
                                        <?php echo htmlspecialchars(
                                            $m['username']??$m['email']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <hr style="margin:24px 0">
                    <div class="d-flex justify-content-between
                                flex-wrap gap-3">
                        <a href="tasks.php" class="cm-btn cm-btn-ghost">
                            Cancel
                        </a>
                        <button type="submit" class="cm-btn cm-btn-primary">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
                <hr style="margin-top:16px">
                <form method="POST" action="edit_task.php"
                      data-confirm="Delete '<?php
                          echo htmlspecialchars(
                              addslashes($task['title'])); ?>'?"
                      data-confirm-type="danger">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"
                           value="<?php echo (int)$task['id']; ?>">
                    <button type="submit" class="cm-btn cm-btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete Task
                    </button>
                </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>