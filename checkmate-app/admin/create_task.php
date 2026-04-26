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
$error    = '';

// POST handling before HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title']        ?? '');
    $description  = trim($_POST['description']  ?? '');
    $due_datetime = $_POST['due_datetime']       ?? '';
    $assigned_to  = !empty($_POST['user_id'])
                    ? (int)$_POST['user_id'] : null;

    if (empty($title) || empty($due_datetime)) {
        $error = "Title and due date are required.";
    } else {
        $pdo->prepare("
            INSERT INTO tasks
                (title, description, due_datetime, user_id,
                 created_by, is_private, status_id)
            VALUES (?, ?, ?, ?, ?, 0, 1)
        ")->execute([
            $title, $description, $due_datetime,
            $assigned_to, $admin_id
        ]);
        $msg = $assigned_to ? 'assigned' : 'admin';
        header("Location: tasks.php?success=$msg"); exit();
    }
}

$members = $pdo->query("
    SELECT id, email, username FROM users
    WHERE role_id = 2 ORDER BY username, email
")->fetchAll();

// HTML output starts here
require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="cm-breadcrumb mb-4">
            <a href="tasks.php" class="cm-breadcrumb-link">
                <i class="bi bi-chevron-left me-1"></i>Tasks
            </a>
            <span class="cm-breadcrumb-sep">/</span>
            <span class="cm-breadcrumb-current">Create Task</span>
        </div>

        <div class="cm-card">
            <div class="cm-card-header">
                <div>
                    <div class="cm-card-title">Create New Task</div>
                    <div class="cm-card-subtitle">
                        Assign to a member or keep as admin task
                    </div>
                </div>
            </div>
            <div class="cm-card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger mb-4">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="mb-4">
                        <label class="form-label">Task Title *</label>
                        <input type="text" name="title"
                               class="form-control form-control-lg"
                               required
                               placeholder="Enter a clear task title"
                               value="<?php echo htmlspecialchars(
                                   $_POST['title'] ?? ''); ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Description</label>
                        <textarea name="description"
                                  class="form-control" rows="4"
                                  placeholder="Detailed instructions..."><?php
                            echo htmlspecialchars(
                                $_POST['description'] ?? '');
                        ?></textarea>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">
                                Due Date & Time *
                            </label>
                            <input type="datetime-local"
                                   name="due_datetime"
                                   class="form-control form-control-lg"
                                   required
                                   value="<?php echo htmlspecialchars(
                                       $_POST['due_datetime'] ??
                                       date('Y-m-d\TH:i',
                                           strtotime('+2 days'))); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assign To</label>
                            <select name="user_id"
                                    class="form-select form-select-lg">
                                <option value="">Keep as Admin Task</option>
                                <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['id']; ?>"
                                    <?php echo ($_POST['user_id'] ?? '')
                                        == $m['id']?'selected':''; ?>>
                                    <?php echo htmlspecialchars(
                                        $m['username']??$m['email']); ?>
                                    (<?php echo htmlspecialchars(
                                        $m['email']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <hr style="margin:28px 0">
                    <div class="d-flex justify-content-end gap-3">
                        <a href="tasks.php" class="cm-btn cm-btn-ghost">
                            Cancel
                        </a>
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
</div>

<?php require_once '../includes/footer.php'; ?>