<?php
function navActive($page, $dir = null) {
    global $current_page, $current_dir;
    if ($dir && $current_dir !== $dir) return '';
    if (is_array($page)) {
        return in_array($current_page, $page) ? 'active' : '';
    }
    return $current_page === $page ? 'active' : '';
}
?>
<aside class="sidebar" id="sidebar">

    <!-- Brand / Logo -->
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <!-- Checkmate logo: checkbox with checkmark -->
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="3" width="18" height="18" rx="3"
                      fill="none" stroke="#fff" stroke-width="2"/>
                <path d="M7 12.5l3.5 3.5L17 9"
                      stroke="#fff" stroke-width="2.2"
                      stroke-linecap="round" stroke-linejoin="round"
                      fill="none"/>
            </svg>
        </div>
        <span class="sidebar-brand-name">Checkmate</span>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <?php if (isAdmin()): ?>
        <div class="sidebar-section-label">Main</div>

        <a href="../admin/home.php"
           class="nav-item-link <?php echo navActive('home.php','admin'); ?>">
            <span class="nav-item-icon"><i class="bi bi-house"></i></span>
            <span class="nav-item-label">Home</span>
        </a>

        <a href="../admin/tasks.php"
           class="nav-item-link <?php echo navActive(
               ['tasks.php','create_task.php','edit_task.php','task_detail.php'],
               'admin'); ?>">
            <span class="nav-item-icon"><i class="bi bi-check2-square"></i></span>
            <span class="nav-item-label">Tasks</span>
            <?php if ($confirm_count > 0): ?>
            <span class="nav-badge"><?php echo $confirm_count; ?></span>
            <?php endif; ?>
        </a>

        <a href="../admin/calendar.php"
           class="nav-item-link <?php echo navActive('calendar.php','admin'); ?>">
            <span class="nav-item-icon"><i class="bi bi-calendar3"></i></span>
            <span class="nav-item-label">Calendar</span>
        </a>

        <div class="sidebar-section-label">Management</div>

        <a href="../admin/users.php"
           class="nav-item-link <?php echo navActive('users.php','admin'); ?>">
            <span class="nav-item-icon"><i class="bi bi-people"></i></span>
            <span class="nav-item-label">Users</span>
        </a>

        <div class="sidebar-section-label">Account</div>

        <a href="../admin/profile.php"
           class="nav-item-link <?php echo navActive('profile.php','admin'); ?>">
            <span class="nav-item-icon"><i class="bi bi-person-circle"></i></span>
            <span class="nav-item-label">Profile</span>
        </a>

        <?php else: ?>
        <div class="sidebar-section-label">Main</div>

        <a href="../member/home.php"
           class="nav-item-link <?php echo navActive('home.php','member'); ?>">
            <span class="nav-item-icon"><i class="bi bi-house"></i></span>
            <span class="nav-item-label">Home</span>
        </a>

        <a href="../member/tasks.php"
           class="nav-item-link <?php echo navActive(
               ['tasks.php','task_detail.php'],'member'); ?>">
            <span class="nav-item-icon"><i class="bi bi-check2-square"></i></span>
            <span class="nav-item-label">Tasks</span>
        </a>

        <a href="../member/calendar.php"
           class="nav-item-link <?php echo navActive('calendar.php','member'); ?>">
            <span class="nav-item-icon"><i class="bi bi-calendar3"></i></span>
            <span class="nav-item-label">Calendar</span>
        </a>

        <div class="sidebar-section-label">Organize</div>

        <a href="../member/categories.php"
           class="nav-item-link <?php echo navActive('categories.php','member'); ?>">
            <span class="nav-item-icon"><i class="bi bi-tag"></i></span>
            <span class="nav-item-label">Categories</span>
        </a>

        <div class="sidebar-section-label">Account</div>

        <a href="../member/profile.php"
           class="nav-item-link <?php echo navActive('profile.php','member'); ?>">
            <span class="nav-item-icon"><i class="bi bi-person-circle"></i></span>
            <span class="nav-item-label">Profile</span>
        </a>

        <?php endif; ?>
    </nav>

    <!-- Sidebar footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                <?php echo htmlspecialchars($initials); ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name">
                    <?php echo htmlspecialchars(
                        $username ?: explode('@', $email)[0]); ?>
                </div>
                <div class="sidebar-user-role">
                    <?php echo isAdmin() ? 'Administrator' : 'Member'; ?>
                </div>
            </div>
            <a href="../shared/logout.php"
               class="sidebar-logout" title="Log out">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>

</aside>