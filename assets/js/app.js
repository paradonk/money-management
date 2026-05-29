/* ── Sidebar toggle ────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });
    }

    /* Auto-dismiss alerts */
    document.querySelectorAll('.alert.alert-success').forEach(el => {
        setTimeout(() => {
            const bs = bootstrap.Alert.getOrCreateInstance(el);
            if (bs) bs.close();
        }, 4000);
    });

    /* Number formatting on input focus */
    document.querySelectorAll('input[type=number]').forEach(input => {
        input.addEventListener('wheel', e => e.preventDefault());
    });
});

/* ── Utility: format number ────────────────────────────────────────── */
function fmtNum(n) {
    return new Intl.NumberFormat('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

/* ── Copy to clipboard ─────────────────────────────────────────────── */
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({ icon: 'success', title: 'Copied!', timer: 1000, showConfirmButton: false });
    });
}
