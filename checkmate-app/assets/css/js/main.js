document.addEventListener('DOMContentLoaded', function () {

    // ══════════════════════════════════════════════════════
    // BOTTOM NAV — keep active state consistent
    // (PHP already sets .active via navActive(), but this
    //  ensures JS-navigated pages also reflect correctly)
    // ══════════════════════════════════════════════════════
    (function highlightBottomNav() {
        const path  = window.location.pathname;
        const items = document.querySelectorAll('.bottom-nav-item');
        items.forEach(function (item) {
            const href = item.getAttribute('href');
            if (!href) return;
            // Normalize: strip leading ../
            const clean = href.replace(/^\.\.\//, '');
            if (path.endsWith(clean) || path.includes(clean.replace('../',''))) {
                item.classList.add('active');
            }
        });
    })();

    // ══════════════════════════════════════════════════════
    // AUTO-DISMISS SUCCESS / INFO ALERTS
    // ══════════════════════════════════════════════════════
    setTimeout(function () {
        document.querySelectorAll('.alert-success, .alert-info')
            .forEach(function (el) {
                const a = bootstrap.Alert.getOrCreateInstance(el);
                if (a) a.close();
            });
    }, 4000);

    // ══════════════════════════════════════════════════════
    // CUSTOM CONFIRM DIALOG
    // Usage:
    //   <form data-confirm="Sure?" data-confirm-type="danger">
    // ══════════════════════════════════════════════════════

    function cmConfirm(message, type) {
        type = type || 'danger';
        const iconClass = type === 'warning'
            ? 'bi-exclamation-triangle' : 'bi-trash';
        const iconBg = type === 'warning'
            ? 'cm-confirm-icon-warning' : 'cm-confirm-icon-danger';
        const title = type === 'warning' ? 'Confirm Action' : 'Confirm Delete';
        const btnLabel = type === 'warning' ? 'Confirm' : 'Delete';

        return new Promise(function (resolve) {
            const overlay = document.createElement('div');
            overlay.className = 'cm-confirm-overlay';
            overlay.innerHTML =
                '<div class="cm-confirm-box">'
                + '<div class="cm-confirm-header">'
                +   '<div class="cm-confirm-icon ' + iconBg + '">'
                +     '<i class="bi ' + iconClass + '"></i>'
                +   '</div>'
                +   '<div class="cm-confirm-title">' + title + '</div>'
                + '</div>'
                + '<div class="cm-confirm-body">' + message + '</div>'
                + '<div class="cm-confirm-actions">'
                +   '<button class="cm-btn cm-btn-ghost" id="cmCancelBtn">Cancel</button>'
                +   '<button class="cm-btn cm-btn-danger" id="cmConfirmBtn">'
                +     '<i class="bi ' + iconClass + ' me-1"></i>' + btnLabel
                +   '</button>'
                + '</div>'
                + '</div>';

            document.body.appendChild(overlay);

            const confirmBtn = overlay.querySelector('#cmConfirmBtn');
            const cancelBtn  = overlay.querySelector('#cmCancelBtn');
            confirmBtn.focus();

            function cleanup(result) {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                resolve(result);
            }

            confirmBtn.addEventListener('click', function () { cleanup(true);  });
            cancelBtn.addEventListener ('click', function () { cleanup(false); });
            overlay.addEventListener   ('click', function (e) {
                if (e.target === overlay) cleanup(false);
            });

            function keyHandler(e) {
                if (e.key === 'Escape') { document.removeEventListener('keydown', keyHandler); cleanup(false); }
                if (e.key === 'Enter')  { document.removeEventListener('keydown', keyHandler); cleanup(true);  }
            }
            document.addEventListener('keydown', keyHandler);
        });
    }

    // Intercept forms with data-confirm
    document.addEventListener('submit', function (e) {
        const form = e.target;
        const msg  = form.getAttribute('data-confirm');
        if (!msg) return;
        e.preventDefault();
        cmConfirm(msg, form.getAttribute('data-confirm-type') || 'danger')
            .then(function (ok) {
                if (ok) {
                    form.removeAttribute('data-confirm');
                    form.submit();
                }
            });
    });

    window.cmConfirm = cmConfirm;
});