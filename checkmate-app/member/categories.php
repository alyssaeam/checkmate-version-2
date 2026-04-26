<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php"); exit();
}
if ((int)$_SESSION['role_id'] === 1) {
    header("Location: ../admin/home.php"); exit();
}

// ── POST LOGIC ───────────────────────────────────────────
$member_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $color = $_POST['color'] ?? '#667eea';
        if (!empty($name)) {
            $pdo->prepare("
                INSERT INTO task_categories (name,color,user_id)
                VALUES (?,?,?)
            ")->execute([$name,$color,$member_id]);
        }
        header("Location: categories.php?success=created"); exit();
    }

    if ($action === 'edit') {
        $cat_id = (int)($_POST['cat_id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $color  = $_POST['color'] ?? '#667eea';
        if (!empty($name)) {
            $pdo->prepare("
                UPDATE task_categories SET name=?,color=?
                WHERE id=? AND user_id=?
            ")->execute([$name,$color,$cat_id,$member_id]);
        }
        header("Location: categories.php?success=updated"); exit();
    }

    if ($action === 'delete') {
        $cat_id = (int)($_POST['cat_id'] ?? 0);
        $pdo->prepare("
            UPDATE tasks SET category_id=NULL
            WHERE category_id=? AND created_by=?
        ")->execute([$cat_id,$member_id]);
        $pdo->prepare("
            DELETE FROM task_categories WHERE id=? AND user_id=?
        ")->execute([$cat_id,$member_id]);
        header("Location: categories.php?success=deleted"); exit();
    }
}

// ── LOAD DATA ─────────────────────────────────────────────
$categories = $pdo->prepare("
    SELECT tc.*,
           COUNT(t.id) as task_count
    FROM task_categories tc
    LEFT JOIN tasks t ON t.category_id=tc.id AND t.created_by=?
    WHERE tc.user_id=?
    GROUP BY tc.id
    ORDER BY tc.name ASC
");
$categories->execute([$member_id,$member_id]);
$categories = $categories->fetchAll(PDO::FETCH_ASSOC);

$edit_cat = null;
if (isset($_GET['edit'])) {
    $s=$pdo->prepare("
        SELECT * FROM task_categories WHERE id=? AND user_id=?");
    $s->execute([(int)$_GET['edit'],$member_id]);
    $edit_cat=$s->fetch(PDO::FETCH_ASSOC);
}

$preset_colors = [
    '#667eea','#28a745','#dc3545','#ffc107',
    '#17a2b8','#6f42c1','#fd7e14','#20c997',
    '#e83e8c','#6c757d','#343a40','#007bff'
];

// ── OUTPUT HTML ──────────────────────────────────────────
require_once '../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h1 class="page-title">Categories</h1>
        <p class="page-subtitle">Organize your tasks with custom categories</p>
    </div>
    <button class="cm-btn cm-btn-primary"
            data-bs-toggle="modal" data-bs-target="#createCatModal">
        <i class="bi bi-plus-lg me-1"></i>New Category
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-4">
    <i class="bi bi-check-circle me-2"></i>
    <?php echo match($_GET['success']){
        'created'=>'Category created!',
        'updated'=>'Category updated!',
        'deleted'=>'Category deleted.',
        default  =>'Done.'
    }; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (empty($categories)): ?>
<div class="cm-card">
    <div class="cm-empty-state">
        <i class="bi bi-tags cm-empty-icon"></i>
        <p class="cm-empty-text">No categories yet. Create one!</p>
        <button class="cm-btn cm-btn-primary mt-3"
                data-bs-toggle="modal" data-bs-target="#createCatModal">
            <i class="bi bi-plus-circle me-1"></i>Create Category
        </button>
    </div>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($categories as $cat): ?>
    <div class="col-md-6 col-lg-4">
        <div class="cm-card">
            <div style="padding:16px 18px">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;
                                    border-radius:8px;
                                    background:<?php echo htmlspecialchars($cat['color']); ?>;
                                    flex-shrink:0"></div>
                        <div>
                            <div style="font-size:14px;font-weight:600;
                                        color:var(--text-main)">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </div>
                            <div style="font-size:11px;color:var(--text-muted)">
                                <?php echo (int)$cat['task_count']; ?> task<?php
                                    echo $cat['task_count']!=1?'s':''; ?>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <a href="?edit=<?php echo $cat['id']; ?>"
                           class="cm-icon-btn" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST"
                              onsubmit="return confirm('Delete \'<?php
                                  echo addslashes($cat['name']); ?>\'?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="cat_id"
                                   value="<?php echo $cat['id']; ?>">
                            <button class="cm-icon-btn"
                                    style="border-color:var(--danger);
                                           color:var(--danger)"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($edit_cat): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
    new bootstrap.Modal(document.getElementById('editCatModal')).show();
});
</script>
<div class="modal fade" id="editCatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <a href="categories.php" class="btn-close"></a>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="cat_id"
                       value="<?php echo (int)$edit_cat['id']; ?>">
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name"
                               class="form-control" required
                               value="<?php echo htmlspecialchars($edit_cat['name']); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php foreach ($preset_colors as $c): ?>
                            <div style="width:32px;height:32px;
                                        border-radius:6px;
                                        background:<?php echo $c; ?>;
                                        cursor:pointer;
                                        border:<?php echo $edit_cat['color']===$c
                                            ?'3px solid #333':'2px solid transparent'; ?>"
                                 onclick="pickColor('edit_color','<?php
                                     echo $c; ?>',this)">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="color" name="color" id="edit_color"
                               class="form-control form-control-color"
                               value="<?php echo htmlspecialchars($edit_cat['color']); ?>"
                               style="max-width:60px">
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="categories.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="cm-btn cm-btn-primary">
                        <i class="bi bi-save me-1"></i>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="createCatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Category</h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label">Category Name *</label>
                        <input type="text" name="name"
                               class="form-control" required
                               placeholder="e.g. Work, Study, Personal">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php foreach ($preset_colors as $i=>$c): ?>
                            <div style="width:32px;height:32px;
                                        border-radius:6px;
                                        background:<?php echo $c; ?>;
                                        cursor:pointer;
                                        border:<?php echo $i===0
                                            ?'3px solid #333':'2px solid transparent'; ?>"
                                 onclick="pickColor('create_color',
                                     '<?php echo $c; ?>',this)">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="color" name="color" id="create_color"
                               class="form-control form-control-color"
                               value="<?php echo $preset_colors[0]; ?>"
                               style="max-width:60px">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="cm-btn cm-btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function pickColor(inputId, hex, el) {
    document.getElementById(inputId).value = hex;
    document.querySelectorAll('[onclick*="'+inputId+'"]').forEach(function(s){
        s.style.border='2px solid transparent';
    });
    el.style.border='3px solid #333';
}
</script>

<?php require_once '../includes/footer.php'; ?>