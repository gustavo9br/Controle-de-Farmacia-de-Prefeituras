<?php
/* Script compartilhado para comportamento do sidebar */
?>
const sidebar = document.getElementById('modernSidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const overlay = document.getElementById('sidebarOverlay');

// Desktop: Toggle collapse
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        document.body.classList.toggle('sidebar-collapsed');

        // Salvar estado no localStorage
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    });
}

// Restaurar estado do sidebar no desktop
if (window.innerWidth >= 1024) {
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') {
        sidebar.classList.add('collapsed');
        document.body.classList.add('sidebar-collapsed');
    }
}

// Mobile: Toggle open/close
function toggleMobileSidebar() {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}

if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener('click', toggleMobileSidebar);
}

if (overlay) {
    overlay.addEventListener('click', toggleMobileSidebar);
}

// Swipe gesture para mobile
let touchStartX = 0;
let touchEndX = 0;
let touchStartY = 0;
let touchEndY = 0;

document.addEventListener('touchstart', e => {
    touchStartX = e.changedTouches[0].screenX;
    touchStartY = e.changedTouches[0].screenY;
}, { passive: true });

document.addEventListener('touchmove', e => {
    touchEndX = e.changedTouches[0].screenX;
    touchEndY = e.changedTouches[0].screenY;
}, { passive: true });

document.addEventListener('touchend', () => {
    handleSwipe();
}, { passive: true });

function handleSwipe() {
    const swipeDistanceX = touchEndX - touchStartX;
    const swipeDistanceY = Math.abs(touchEndY - touchStartY);

    // Só processa swipe se for mais horizontal que vertical
    if (Math.abs(swipeDistanceX) > swipeDistanceY) {
        // Swipe da esquerda para direita (abrir)
        if (swipeDistanceX > 50 && touchStartX < 50) {
            if (window.innerWidth < 1024 && !sidebar.classList.contains('open')) {
                toggleMobileSidebar();
            }
        }

        // Swipe da direita para esquerda (fechar)
        if (swipeDistanceX < -50 && sidebar.classList.contains('open')) {
            if (window.innerWidth < 1024) {
                toggleMobileSidebar();
            }
        }
    }
}

// Fechar sidebar ao mudar de página no mobile
window.addEventListener('beforeunload', () => {
    if (window.innerWidth < 1024) {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    }
});

// Ajustar ao resize
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        if (window.innerWidth >= 1024) {
            // Desktop: restaurar estado salvo
            sidebar.classList.remove('open');
            overlay.classList.remove('active');

            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
        } else {
            // Mobile: remover collapsed
            sidebar.classList.remove('collapsed');
            document.body.classList.remove('sidebar-collapsed');
        }
    }, 150);
});
