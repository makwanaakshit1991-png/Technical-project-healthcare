// ============================================================
//  SHRS — Main JavaScript
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // Auto-dismiss flash alerts after 5 seconds
    document.querySelectorAll('.alert.alert-dismissible').forEach(function (el) {
        setTimeout(function () {
            var alert = bootstrap.Alert.getOrCreateInstance(el);
            if (alert) alert.close();
        }, 5000);
    });

    // Confirm destructive actions
    document.querySelectorAll('[data-confirm]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Table row click → navigate
    document.querySelectorAll('tr[data-href]').forEach(function (row) {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function () {
            window.location = this.dataset.href;
        });
    });

    // Session timeout warning (25 min = 1500 sec)
    var sessionWarnTimer = setTimeout(function () {
        var modal = new bootstrap.Modal(document.getElementById('sessionWarnModal'));
        if (modal) modal.show();
    }, 1500000);

    // Highlight active nav link
    var path = window.location.pathname;
    document.querySelectorAll('.navbar-nav .nav-link, .dash-nav .nav-link').forEach(function (link) {
        if (link.getAttribute('href') && path.endsWith(link.getAttribute('href').split('/').pop())) {
            link.classList.add('active');
        }
    });

    // Risk score progress bars — animate on load
    document.querySelectorAll('.risk-bar-fill').forEach(function (bar) {
        var target = parseFloat(bar.dataset.score || 0) * 100;
        bar.style.width = '0%';
        setTimeout(function () {
            bar.style.transition = 'width 1.2s ease';
            bar.style.width = target.toFixed(1) + '%';
        }, 200);
    });

});

// Session extend button
function extendSession() {
    fetch(window.SHRS_BASE_URL + '/auth/session_check.php', { credentials: 'same-origin' })
        .then(function () {
            var modal = bootstrap.Modal.getInstance(document.getElementById('sessionWarnModal'));
            if (modal) modal.hide();
        });
}
