<?php
// ─── AUTH ────────────────────────────────────────────────
function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// ─── ADMIN TASK QUERIES ──────────────────────────────────
function getAdminTasks($pdo, $admin_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.email as assigned_to, u.username as assigned_username,
               ts.name as status, tc.name as category, tc.color
        FROM tasks t
        LEFT JOIN users u       ON t.user_id     = u.id
        LEFT JOIN task_status ts  ON t.status_id   = ts.id
        LEFT JOIN task_categories tc ON t.category_id = tc.id
        WHERE t.created_by = ?
        ORDER BY COALESCE(t.priority,0) DESC, t.due_datetime ASC
    ");
    $stmt->execute([$admin_id]);
    return $stmt->fetchAll();
}

function getAdminStats($pdo, $admin_id) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN ts.name='Pending'          THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN ts.name='For Confirmation' THEN 1 ELSE 0 END) as confirmation,
            SUM(CASE WHEN ts.name='Completed'        THEN 1 ELSE 0 END) as completed
        FROM tasks t JOIN task_status ts ON t.status_id = ts.id
        WHERE t.created_by = ?
    ");
    $stmt->execute([$admin_id]);
    return $stmt->fetch() ?: ['total'=>0,'pending'=>0,'confirmation'=>0,'completed'=>0];
}

function getOverdueCount($pdo, $admin_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE created_by = ? AND due_datetime < NOW() AND status_id != 3");
    $stmt->execute([$admin_id]);
    return $stmt->fetchColumn();
}

function getTotalMembers($pdo) {
    return $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 2")->fetchColumn();
}

// ─── MEMBER TASK QUERIES ─────────────────────────────────
function getMemberStats($pdo, $member_id) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN ts.name='Completed' THEN 1 ELSE 0 END) as done,
            SUM(CASE WHEN t.due_datetime < NOW() AND ts.name != 'Completed' THEN 1 ELSE 0 END) as overdue
        FROM tasks t JOIN task_status ts ON t.status_id = ts.id
        WHERE t.created_by = ? OR t.user_id = ?
    ");
    $stmt->execute([$member_id, $member_id]);
    return $stmt->fetch() ?: ['total'=>0,'done'=>0,'overdue'=>0];
}

function getMemberCategories($pdo, $member_id) {
    $stmt = $pdo->prepare("SELECT * FROM task_categories WHERE user_id = ? OR user_id IS NULL ORDER BY name");
    $stmt->execute([$member_id]);
    return $stmt->fetchAll();
}