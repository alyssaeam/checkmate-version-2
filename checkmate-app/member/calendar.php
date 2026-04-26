<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/login.php"); exit();
}
if ((int)$_SESSION['role_id'] === 1) {
    header("Location: ../admin/home.php"); exit();
}

// ── DATA LOGIC ───────────────────────────────────────────
$member_id      = $_SESSION['user_id'];
$month          = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year           = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month          = max(1, min(12, $month));
$year           = max(2020, min(2035, $year));
$selected_date  = $_GET['date'] ?? date('Y-m-d');
$show_completed = isset($_GET['completed']);

$prev = new DateTime("$year-$month-01"); $prev->modify('-1 month');
$next = new DateTime("$year-$month-01"); $next->modify('+1 month');

function memberCalUrl($m, $y, $date, $completed) {
    $q = ['month'=>$m,'year'=>$y,'date'=>$date];
    if ($completed) $q['completed']='1';
    return '?'.http_build_query($q);
}

$completed_cond = $show_completed ? "" : "AND t.status_id != 3";

$dot_stmt = $pdo->prepare("
    SELECT DATE(t.due_datetime) as d, COUNT(*) as cnt
    FROM tasks t
    WHERE (t.created_by=? OR t.user_id=?)
    AND MONTH(t.due_datetime)=?
    AND YEAR(t.due_datetime)=?
    $completed_cond
    GROUP BY DATE(t.due_datetime)
");
$dot_stmt->execute([$member_id,$member_id,$month,$year]);
$task_dates=[];
foreach ($dot_stmt->fetchAll() as $row) {
    $task_dates[$row['d']]=(int)$row['cnt'];
}

$day_stmt = $pdo->prepare("
    SELECT t.*,
           ts.name  as status,
           ts.id    as status_id_val,
           tc.name  as category,
           tc.color as cat_color,
           CASE WHEN t.created_by=? THEN 'own' ELSE 'assigned' END as task_type
    FROM tasks t
    LEFT JOIN task_status ts     ON t.status_id=ts.id
    LEFT JOIN task_categories tc ON t.category_id=tc.id
    WHERE (t.created_by=? OR t.user_id=?)
    AND DATE(t.due_datetime)=?
    $completed_cond
    ORDER BY COALESCE(t.priority,0) DESC, t.due_datetime ASC
");
$day_stmt->execute([$member_id,$member_id,$member_id,$selected_date]);
$day_tasks=$day_stmt->fetchAll(PDO::FETCH_ASSOC);

$first_day     = (int)(new DateTime("$year-$month-01"))->format('w');
$days_in_month = (int)(new DateTime("$year-$month-01"))->format('t');
$today         = date('Y-m-d');
$months_list   = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];
$years_list    = range(2020,2035);

// ── OUTPUT HTML ──────────────────────────────────────────
require_once '../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h1 class="page-title">Calendar</h1>
        <p class="page-subtitle">View your tasks by date</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo memberCalUrl($month,$year,$selected_date,!$show_completed); ?>"
           class="cm-btn cm-btn-ghost">
            <i class="bi bi-<?php echo $show_completed?'eye-slash':'eye'; ?> me-1"></i>
            <?php echo $show_completed?'Hide Completed':'Show Completed'; ?>
        </a>
        <a href="<?php echo memberCalUrl((int)date('n'),(int)date('Y'),$today,$show_completed); ?>"
           class="cm-btn cm-btn-ghost">
            <i class="bi bi-calendar-check me-1"></i>Today
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="cm-card">
            <div style="padding:14px 16px;border-bottom:1px solid var(--border)">
                <div class="cal-nav">
                    <a href="<?php echo memberCalUrl(
                            (int)$prev->format('n'),(int)$prev->format('Y'),
                            $selected_date,$show_completed); ?>"
                       class="cm-icon-btn flex-shrink-0">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <div class="cal-nav-center">
                        <form method="GET" id="calNavForm"
                              style="display:flex;align-items:center;gap:6px">
                            <input type="hidden" name="date"
                                   value="<?php echo htmlspecialchars($selected_date); ?>">
                            <?php if ($show_completed): ?>
                            <input type="hidden" name="completed" value="1">
                            <?php endif; ?>
                            <select name="month" class="cm-select-inline"
                                    onchange="document.getElementById('calNavForm').submit()">
                                <?php foreach ($months_list as $i=>$mn): ?>
                                <option value="<?php echo $i+1; ?>"
                                        <?php echo ($i+1)===$month?'selected':''; ?>>
                                    <?php echo $mn; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="year" class="cm-select-inline"
                                    onchange="document.getElementById('calNavForm').submit()">
                                <?php foreach ($years_list as $yr): ?>
                                <option value="<?php echo $yr; ?>"
                                        <?php echo $yr===$year?'selected':''; ?>>
                                    <?php echo $yr; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <a href="<?php echo memberCalUrl(
                            (int)$next->format('n'),(int)$next->format('Y'),
                            $selected_date,$show_completed); ?>"
                       class="cm-icon-btn flex-shrink-0">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>

            <div style="padding:14px 16px">
                <div class="cal-grid">
                    <?php foreach(['S','M','T','W','T','F','S'] as $dh): ?>
                    <div class="cal-header-cell"><?php echo $dh; ?></div>
                    <?php endforeach; ?>

                    <?php for($i=0;$i<$first_day;$i++): ?>
                    <div class="cal-cell cal-cell-empty"></div>
                    <?php endfor; ?>

                    <?php for($day=1;$day<=$days_in_month;$day++):
                        $ds      = sprintf('%04d-%02d-%02d',$year,$month,$day);
                        $is_today= ($ds===$today);
                        $is_sel  = ($ds===$selected_date);
                        $cnt     = $task_dates[$ds] ?? 0;
                        $cls     = 'cal-cell';
                        if ($is_sel)       $cls .= ' selected';
                        elseif ($is_today) $cls .= ' today';
                    ?>
                    <a href="<?php echo memberCalUrl($month,$year,$ds,$show_completed); ?>"
                       class="<?php echo $cls; ?>">
                        <span class="cal-day-num"><?php echo $day; ?></span>
                        <?php if ($cnt>0): ?>
                        <span class="cal-dot"><?php echo $cnt; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endfor; ?>

                    <?php
                    $trail=(7-(($first_day+$days_in_month)%7))%7;
                    for($i=0;$i<$trail;$i++): ?>
                    <div class="cal-cell cal-cell-empty"></div>
                    <?php endfor; ?>
                </div>

                <div class="cal-legend">
                    <span class="cal-legend-item">
                        <span class="cal-legend-dot"
                              style="background:var(--primary-light);
                                     border:1px solid var(--primary)"></span>
                        Today
                    </span>
                    <span class="cal-legend-item">
                        <span class="cal-legend-dot"
                              style="background:var(--primary)"></span>
                        Selected
                    </span>
                    <span class="cal-legend-item">
                        <span class="cal-dot"
                              style="position:static;font-size:9px">2</span>
                        Tasks
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="cm-card" style="height:100%">
            <div class="cm-card-header">
                <div>
                    <div class="cm-card-title">
                        <?php echo date('F j, Y',strtotime($selected_date)); ?>
                    </div>
                    <div class="cm-card-subtitle">
                        <?php echo count($day_tasks); ?> task<?php
                            echo count($day_tasks)!==1?'s':''; ?>
                        &nbsp;&mdash;&nbsp;
                        <?php echo $show_completed?'all tasks':'active only'; ?>
                    </div>
                </div>
            </div>
            <?php if (empty($day_tasks)): ?>
            <div class="cm-empty-state">
                <i class="bi bi-calendar-x cm-empty-icon"></i>
                <p class="cm-empty-text">No tasks on this date</p>
            </div>
            <?php else: ?>
            <div class="cm-list">
                <?php foreach ($day_tasks as $task):
                    $sv=(int)($task['status_id_val']??$task['status_id']);
                ?>
                <a href="task_detail.php?id=<?php echo $task['id'];
                    ?>&from=<?php echo $task['task_type']==='own'
                        ?'private':'assigned'; ?>"
                   class="cm-list-item">
                    <div class="cm-list-item-body">
                        <div class="d-flex align-items-center gap-2">
                            <?php if (!empty($task['priority'])): ?>
                            <span class="cm-priority-badge">
                                <?php echo (int)$task['priority']; ?>
                            </span>
                            <?php endif; ?>
                            <div class="cm-list-item-title">
                                <?php echo htmlspecialchars($task['title']); ?>
                            </div>
                            <?php if (!empty($task['cat_color'])): ?>
                            <span class="cm-cat-dot"
                                  style="background:<?php echo htmlspecialchars(
                                      $task['cat_color']); ?>">
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="cm-list-item-meta">
                            <i class="bi bi-clock me-1"></i>
                            <?php echo date('g:i A',strtotime($task['due_datetime'])); ?>
                            <?php if ($task['task_type']==='assigned'): ?>
                            &nbsp;&middot;&nbsp;
                            <span style="color:var(--warning)">Admin</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="cm-status-badge cm-status-<?php
                        echo $sv===3?'done':($sv===2?'confirm':'pending'); ?>">
                        <?php echo htmlspecialchars($task['status']); ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>