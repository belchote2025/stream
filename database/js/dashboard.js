document.addEventListener('DOMContentLoaded', () => {
    initBootstrapHelpers();
    initSidebarToggle();
    initImageFallbacks();
});

function initBootstrapHelpers() {
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(el => new bootstrap.Tooltip(el));

        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(el => new bootstrap.Popover(el));
    }
}

function initSidebarToggle() {
    const toggleSidebar = document.querySelector('.toggle-sidebar');
    const dashboardSidebar = document.querySelector('.dashboard-sidebar');

    if (toggleSidebar && dashboardSidebar) {
        toggleSidebar.addEventListener('click', () => {
            dashboardSidebar.classList.toggle('show');
        });

        document.addEventListener('click', (event) => {
            if (window.innerWidth >= 992) return;
            const clickInsideSidebar = dashboardSidebar.contains(event.target);
            const clickOnToggle = toggleSidebar.contains(event.target);
            if (!clickInsideSidebar && !clickOnToggle && dashboardSidebar.classList.contains('show')) {
                dashboardSidebar.classList.remove('show');
            }
        });
    }
}

function initImageFallbacks() {
    const baseUrl = document.body?.dataset?.baseUrl || '';
    const placeholder = `${baseUrl}/assets/images/placeholder.jpg`;

    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('error', function handleError() {
            if (this.dataset.placeholderApplied === 'true') return;
            this.dataset.placeholderApplied = 'true';
            this.src = placeholder;
            this.alt = 'Imagen no disponible';
            this.classList.add('img-error');
        });
    });
}

window.dashboardToast = function dashboardToast({ type = 'info', title = '', text = '', duration = 5000 } = {}) {
    if (typeof Swal === 'undefined') return;
    Swal.fire({
        icon: type,
        title,
        text,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: duration,
        timerProgressBar: true
    });
};

