<?php
/* Arquivo de estilo compartilhado para os sidebars do painel */
?>
/* Mobile Menu Button */
.mobile-menu-btn {
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 50;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    padding: 0.75rem;
    border-radius: 0.75rem;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
    display: none;
    transition: all 0.3s;
}

.mobile-menu-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.5);
}

@media (max-width: 1023px) {
    .mobile-menu-btn {
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

.modern-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    width: 260px;
    background: rgba(30, 41, 59, 0.98);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    z-index: 45;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

/* Sidebar collapsed state */
.modern-sidebar.collapsed {
    width: 70px;
}

.modern-sidebar.collapsed .brand-text,
.modern-sidebar.collapsed .menu-label,
.modern-sidebar.collapsed .user-info {
    opacity: 0;
    display: none;
}

.modern-sidebar.collapsed .menu-item-wrapper {
    flex-direction: column;
    gap: 0;
}

.modern-sidebar.collapsed .menu-item,
.modern-sidebar.collapsed .sidebar-brand {
    justify-content: center;
}

/* Scrollbar */
.modern-sidebar {
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}

.modern-sidebar::-webkit-scrollbar {
    width: 4px;
}

.modern-sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.modern-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
}

/* Header */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
    width: 36px;
    height: 36px;
    min-width: 36px;
    background: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.brand-text {
    font-size: 1.125rem;
    font-weight: 700;
    color: white;
    letter-spacing: -0.025em;
    white-space: nowrap;
    transition: opacity 0.3s;
}

.sidebar-toggle-btn {
    color: rgba(255, 255, 255, 0.7);
    padding: 0.5rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
    flex-shrink: 0;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sidebar-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.modern-sidebar.collapsed .sidebar-toggle-btn svg {
    transform: rotate(180deg);
}

.sidebar-menu {
    flex: 1;
    padding: 0.5rem;
    overflow-y: auto;
}

.menu-item-wrapper {
    position: relative;
    margin-bottom: 0.25rem;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.875rem;
    padding-right: 2.75rem;
    color: rgba(255, 255, 255, 0.7);
    border-radius: 0.5rem;
    transition: all 0.2s;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    position: relative;
    cursor: pointer;
    width: 100%;
}

.add-btn {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    min-width: 28px;
    background: rgba(99, 102, 241, 0.2);
    color: #6366f1;
    border-radius: 0.375rem;
    transition: all 0.3s ease;
    text-decoration: none;
    opacity: 0;
    pointer-events: none;
    z-index: 10;
}

.menu-item-wrapper:hover .add-btn {
    opacity: 1;
    pointer-events: auto;
}

.add-btn:hover {
    background: rgba(99, 102, 241, 0.3);
    color: #818cf8;
    transform: translateY(-50%) scale(1.1);
}

/* Collapsed state */
.modern-sidebar.collapsed .menu-item-wrapper {
    margin-bottom: 0;
}

.modern-sidebar.collapsed .menu-item {
    padding: 0.625rem;
}

.modern-sidebar.collapsed .add-btn {
    position: static;
    width: 100%;
    height: 32px;
    transform: translateY(-8px);
    margin-top: 0.25rem;
    background: transparent;
    border-radius: 0.5rem;
}

.modern-sidebar.collapsed .menu-item-wrapper:hover .add-btn {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
}

.modern-sidebar.collapsed .add-btn:hover {
    background: rgba(99, 102, 241, 0.2);
    transform: translateY(0);
}

.menu-item:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.menu-item.active {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.9) 0%, rgba(139, 92, 246, 0.9) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.menu-label {
    flex: 1;
}

.sidebar-footer {
    padding: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    padding: 0.5rem;
    margin: -0.5rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
    text-decoration: none;
    cursor: pointer;
}

.user-profile:hover {
    background: rgba(255, 255, 255, 0.1);
}

.user-avatar {
    width: 36px;
    height: 36px;
    min-width: 36px;
    min-height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    flex-shrink: 0;
}

.user-avatar-img {
    width: 36px;
    height: 36px;
    min-width: 36px;
    min-height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.2);
    background: white;
    flex-shrink: 0;
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    color: white;
    font-size: 0.875rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.logout-btn {
    color: rgba(255, 255, 255, 0.7);
    padding: 0.5rem;
    border-radius: 0.375rem;
    transition: all 0.2s;
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
    z-index: 39;
}

/* Mobile */
@media (max-width: 1023px) {
    .modern-sidebar {
        transform: translateX(-100%);
        width: 260px !important;
    }
    
    .modern-sidebar.open {
        transform: translateX(0);
    }
    
    .modern-sidebar.collapsed {
        width: 260px !important;
    }
    
    .sidebar-overlay.active {
        display: block;
    }
    
    .sidebar-toggle-btn {
        display: none !important;
    }
}

/* Desktop */
@media (min-width: 1024px) {
    body.admin-shell {
        margin-left: 260px;
        padding-left: 0;
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    body.admin-shell.sidebar-collapsed {
        margin-left: 70px;
    }
}
