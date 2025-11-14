<?php
/* Arquivo de estilo compartilhado para os sidebars do painel - Glass Design */
?>
/* Mobile Menu Button */
.mobile-menu-btn,
.mobile-menu-button {
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 50;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    color: #1e293b;
    padding: 0.75rem;
    border-radius: 0.75rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    display: none;
    transition: all 0.3s;
    border: 1px solid rgba(255, 255, 255, 0.2);
    width: 3rem;
    height: 3rem;
}

.mobile-menu-btn:hover,
.mobile-menu-button:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
    background: rgba(255, 255, 255, 1);
}

@media (max-width: 1023px) {
    .mobile-menu-btn,
    .mobile-menu-button {
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

/* Glass Sidebar - Efeito de vidro fosco */
.modern-sidebar {
    position: fixed;
    left: 1rem;
    top: 1rem;
    bottom: 1rem;
    width: 280px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    z-index: 45;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

/* Sidebar collapsed state */
.modern-sidebar.collapsed {
    width: 80px;
}

.modern-sidebar.collapsed .brand-text,
.modern-sidebar.collapsed .menu-label,
.modern-sidebar.collapsed .user-info,
.modern-sidebar.collapsed .sidebar-toggle-btn svg:first-child {
    opacity: 0;
    display: none;
}

.modern-sidebar.collapsed .sidebar-toggle-btn {
    justify-content: center;
}

.modern-sidebar.collapsed .sidebar-toggle-btn::after {
    content: '→';
    font-size: 1.25rem;
    color: rgba(0, 0, 0, 0.6);
}

.modern-sidebar:not(.collapsed) .sidebar-toggle-btn::after {
    content: '←';
    font-size: 1.25rem;
    color: rgba(0, 0, 0, 0.6);
}

.modern-sidebar.collapsed .menu-item-wrapper {
    position: relative;
}

.modern-sidebar.collapsed .menu-item,
.modern-sidebar.collapsed .sidebar-brand {
    justify-content: center;
    padding-left: 0.75rem;
    padding-right: 0.75rem;
}


/* Scrollbar */
.modern-sidebar {
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
}

.modern-sidebar::-webkit-scrollbar {
    width: 6px;
}

.modern-sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.modern-sidebar::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 3px;
}

.modern-sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.3);
}

/* Header */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 1.25rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    gap: 0.5rem;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    min-width: 0;
    transition: all 0.3s;
}

.brand-icon {
    width: 40px;
    height: 40px;
    min-width: 40px;
    background: white;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
}

.brand-text {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.025em;
    white-space: nowrap;
    transition: opacity 0.3s;
}

.sidebar-toggle-btn {
    color: rgba(0, 0, 0, 0.6);
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.2s;
    flex-shrink: 0;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: transparent;
    border: none;
    font-size: 1.25rem;
    line-height: 1;
}

.sidebar-toggle-btn:hover {
    background: rgba(0, 0, 0, 0.05);
    color: #1e293b;
}

.sidebar-toggle-btn svg {
    display: none;
}

/* Menu */
.sidebar-menu {
    flex: 1;
    padding: 1rem 0.75rem;
    overflow-y: auto;
}

.menu-section-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(0, 0, 0, 0.4);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.5rem 1rem;
    margin-top: 0.5rem;
    margin-bottom: 0.25rem;
}

.modern-sidebar.collapsed .menu-section-title {
    display: none;
}

.menu-separator {
    height: 1px;
    background: rgba(0, 0, 0, 0.1);
    margin: 0.75rem 1rem;
}

.modern-sidebar.collapsed .menu-separator {
    margin: 0.5rem 0.75rem;
}

.menu-item-wrapper {
    position: relative;
    margin-bottom: 0.25rem;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: rgba(0, 0, 0, 0.7);
    border-radius: 12px;
    transition: all 0.2s;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    position: relative;
    cursor: pointer;
    width: 100%;
}

.menu-item svg {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    color: rgba(0, 0, 0, 0.6);
    transition: color 0.2s;
}

.menu-item:hover {
    background: rgba(0, 0, 0, 0.05);
    color: #1e293b;
}

.menu-item:hover svg {
    color: #6366f1;
}

.menu-item.active {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.15) 100%);
    color: #6366f1;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.2);
}

.menu-item.active svg {
    color: #6366f1;
}

.menu-label {
    flex: 1;
    white-space: nowrap;
}

/* Footer */
.sidebar-footer {
    padding: 1rem;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: auto;
    flex-direction: column;
}

.modern-sidebar:not(.collapsed) .sidebar-footer {
    flex-direction: row;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    padding: 0.5rem;
    margin: -0.5rem;
    border-radius: 12px;
    transition: all 0.2s;
    text-decoration: none;
    cursor: pointer;
    min-width: 0;
}

.user-profile:hover {
    background: rgba(0, 0, 0, 0.05);
}

.user-avatar {
    width: 40px;
    height: 40px;
    min-width: 40px;
    min-height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.875rem;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.user-avatar-img {
    width: 40px;
    height: 40px;
    min-width: 40px;
    min-height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.5);
    background: white;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    color: #1e293b;
    font-size: 0.875rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    color: rgba(0, 0, 0, 0.5);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.logout-btn {
    color: rgba(0, 0, 0, 0.6);
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    order: -1;
}

.modern-sidebar:not(.collapsed) .logout-btn {
    order: 1;
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.logout-btn svg {
    width: 20px;
    height: 20px;
}

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(2px);
    z-index: 39;
}

/* Mobile */
@media (max-width: 1023px) {
    .modern-sidebar {
        left: 0;
        top: 0;
        bottom: 0;
        border-radius: 0;
        transform: translateX(-100%);
        width: 280px !important;
        border-left: none;
        border-top: none;
        border-bottom: none;
    }
    
    .modern-sidebar.open {
        transform: translateX(0);
    }
    
    .modern-sidebar.collapsed {
        width: 280px !important;
    }
    
    .sidebar-overlay.active {
        display: block;
    }
    
    .sidebar-toggle-btn {
        display: none !important;
    }
    
    /* Ajustar posicionamento do logo no mobile para aparecer quando menu estiver aberto */
    .sidebar-header {
        padding-left: 4.5rem; /* Espaço para o botão do menu mobile (3rem) + margem (1.5rem) */
        padding-right: 1.25rem;
    }
    
    .sidebar-brand {
        margin-left: 0;
    }
}

/* Desktop */
@media (min-width: 1024px) {
    body.admin-shell {
        margin-left: 300px;
        padding-left: 0;
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    body.admin-shell.sidebar-collapsed {
        margin-left: 100px;
    }
}
