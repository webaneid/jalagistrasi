document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.jg-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var message = btn.getAttribute('data-confirm') || 'Yakin ingin menghapus?';
            if (!window.confirm(message)) {
                e.preventDefault();
            }
        });
    });
});
