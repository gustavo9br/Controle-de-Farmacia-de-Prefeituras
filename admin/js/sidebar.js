document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('#adminSidebar');
    const toggleButton = document.querySelector('#sidebarToggle');
    const mobileMenuButton = document.querySelector('#mobileMenuButton');
    const mobileOverlay = document.querySelector('#mobileMenuOverlay');
    const body = document.body;

    if (!sidebar) {
        return;
    }

    const persistKey = 'adminSidebarExpanded';
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isMobile = () => window.innerWidth <= 768;

    const applyState = (expanded) => {
        const method = expanded ? 'add' : 'remove';
        sidebar.classList[method]('expanded');
        sidebar.classList[expanded ? 'remove' : 'add']('collapsed');
        body.classList[method]('sidebar-expanded');
        
        if (toggleButton) {
            toggleButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggleButton.querySelector('span').textContent = expanded ? 'Recolher menu' : 'Expandir menu';
            const icon = toggleButton.querySelector('svg');
            if (icon) {
                icon.style.transform = expanded ? 'rotate(180deg)' : 'rotate(0deg)';
                icon.style.transition = prefersReducedMotion ? 'none' : 'transform 0.3s ease';
            }
        }
    };

    const openMobileMenu = () => {
        sidebar.classList.add('mobile-open');
        if (mobileOverlay) {
            mobileOverlay.classList.add('active');
        }
        body.style.overflow = 'hidden';
    };

    const closeMobileMenu = () => {
        sidebar.classList.remove('mobile-open');
        if (mobileOverlay) {
            mobileOverlay.classList.remove('active');
        }
        body.style.overflow = '';
    };

    let isExpanded = true;
    try {
        const stored = localStorage.getItem(persistKey);
        if (stored !== null) {
            isExpanded = stored === 'true';
        }
    } catch (error) {
        console.warn('Não foi possível ler o estado do menu no localStorage.', error);
    }

    if (!isMobile()) {
        applyState(isExpanded);
    }

    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            isExpanded = !sidebar.classList.contains('expanded');
            applyState(isExpanded);
            try {
                localStorage.setItem(persistKey, String(isExpanded));
            } catch (error) {
                console.warn('Não foi possível persistir o estado do menu.', error);
            }
        });
    }

    if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', () => {
            if (sidebar.classList.contains('mobile-open')) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        });
    }

    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', closeMobileMenu);
    }

    // Close mobile menu when clicking a link
    const sidebarLinks = sidebar.querySelectorAll('a:not(.sidebar-toggle)');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (isMobile()) {
                closeMobileMenu();
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
            closeMobileMenu();
        }
        
        if (event.altKey && event.key.toLowerCase() === 'm' && !isMobile()) {
            event.preventDefault();
            if (toggleButton) {
                toggleButton.click();
            }
        }
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (!isMobile()) {
                closeMobileMenu();
                applyState(isExpanded);
            }
        }, 250);
    });
});
