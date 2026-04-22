document.addEventListener('DOMContentLoaded', function () {

    // ── SIDEBAR TOGGLE (mobile) ─────────────────────────────
    const sidebar        = document.getElementById('sidebar');
    const sidebarToggle  = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (sidebarOverlay) sidebarOverlay.classList.add('visible');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (sidebarOverlay) sidebarOverlay.classList.remove('visible');
        document.body.style.overflow = '';
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            sidebar && sidebar.classList.contains('open')
                ? closeSidebar()
                : openSidebar();
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    // Close sidebar on resize to desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) closeSidebar();
    });

    // ── AUTO-DISMISS SUCCESS / INFO ALERTS ──────────────────
    setTimeout(function () {
        document.querySelectorAll('.alert-success, .alert-info')
            .forEach(function (el) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
                if (bsAlert) bsAlert.close();
            });
    }, 4000);

    // ── ACTIVE NAV HIGHLIGHT ────────────────────────────────
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-item-link').forEach(function (link) {
        const href = link.getAttribute('href');
        if (href && currentPath.endsWith(href.replace(/^\.\.\//, '').replace('../', ''))) {
            link.classList.add('active');
        }
    });

});