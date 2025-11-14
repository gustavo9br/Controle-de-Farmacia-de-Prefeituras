<?php
if (!defined('SYSTEM_NAME')) {
    require_once __DIR__ . '/../../config/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPath = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$userName = $_SESSION['user_nome'] ?? 'Usuário';
$userInitials = strtoupper(substr($userName, 0, 2));

// Buscar foto do usuário
$userFoto = null;
if (isset($_SESSION['user_id'])) {
    try {
        if (!function_exists('getConnection')) {
            require_once __DIR__ . '/../../includes/auth.php';
        }
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT foto FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['foto'])) {
            $userFoto = $result['foto'];
        }
    } catch (Exception $e) {
        // Silenciar erro e usar iniciais como fallback
        $userFoto = null;
    }
}
?>

<!-- Botão de toggle para mobile -->
<button id="mobileMenuBtn" class="mobile-menu-btn">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>

<!-- Glass Sidebar - Efeito de vidro fosco -->
<aside id="modernSidebar" class="modern-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <img src="../images/logo.svg" alt="Farmácia" style="width: 100%; height: 100%; object-fit: contain; padding: 4px;">
            </div>
            <span class="brand-text">Gov Farma</span>
        </div>
        
        <button id="sidebarToggle" class="sidebar-toggle-btn" title="Recolher menu"></button>
    </div>

    <nav class="sidebar-menu">
        <!-- Seção MAIN -->
        <div class="menu-section-title">Farmácia</div>
        
        <div class="menu-item-wrapper">
            <a href="index.php" class="menu-item <?php echo $currentPath === 'index.php' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/>
                </svg>
                <span class="menu-label">Dispensação</span>
            </a>
        </div>

        <div class="menu-item-wrapper">
            <a href="pacientes.php" class="menu-item <?php echo in_array($currentPath, ['pacientes.php', 'pacientes_form.php', 'paciente_historico.php']) ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0z"/>
                </svg>
                <span class="menu-label">Pacientes</span>
            </a>
        </div>

        <div class="menu-item-wrapper">
            <a href="receitas.php" class="menu-item <?php echo in_array($currentPath, ['receitas.php', 'receitas_form.php', 'receitas_dispensar.php']) ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z"/>
                </svg>
                <span class="menu-label">Receitas</span>
            </a>
        </div>

        <!-- Separador -->
        <div class="menu-separator"></div>

        <div class="menu-item-wrapper">
            <a href="medicamentos.php" class="menu-item <?php echo in_array($currentPath, ['medicamentos.php', 'medicamentos_form.php', 'medicamentos_view.php']) ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232 1.232 3.228 0 4.46s-3.228 1.232-4.46 0L19.8 15.3Zm-14.8.8-1.402 1.402c-1.232 1.232-1.232 3.228 0 4.46s3.228 1.232 4.46 0L5 14.5Z"/>
                </svg>
                <span class="menu-label">Medicamentos</span>
            </a>
        </div>

        <div class="menu-item-wrapper">
            <a href="lotes.php" class="menu-item <?php echo $currentPath === 'lotes.php' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/>
                </svg>
                <span class="menu-label">Lotes</span>
            </a>
        </div>

        <!-- Separador -->
        <div class="menu-separator"></div>

        <div class="menu-item-wrapper">
            <a href="usuarios.php" class="menu-item <?php echo $currentPath === 'usuarios.php' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0z"/>
                </svg>
                <span class="menu-label">Usuários</span>
            </a>
        </div>

        <div class="menu-item-wrapper">
            <a href="configuracoes.php" class="menu-item <?php echo $currentPath === 'configuracoes.php' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                </svg>
                <span class="menu-label">Configurações</span>
            </a>
        </div>
        
        <div class="menu-item-wrapper">
            <a href="relatorios.php" class="menu-item <?php echo $currentPath === 'relatorios.php' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z"/>
                </svg>
                <span class="menu-label">Relatórios</span>
            </a>
        </div>

        <!-- Separador antes de HOSPITAL -->
        <div class="menu-separator"></div>

        <!-- Seção HOSPITAL -->
        <div class="menu-section-title" style="margin-top: 0.5rem;">HOSPITAL</div>
        
        <div class="menu-item-wrapper">
            <a href="../admin/relatorios.php" class="menu-item <?php echo (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/hospital/relatorios.php') !== false) ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z"/>
                </svg>
                <span class="menu-label">Relatórios</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-btn" title="Sair do sistema">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M18 12h-9m0 0 3-3m-3 3 3 3"/>
            </svg>
        </a>
        
        <a href="perfil.php" class="user-profile" title="Ver perfil">
            <?php if (!empty($userFoto)): ?>
                <img src="../<?php echo htmlspecialchars($userFoto); ?>" alt="Foto do usuário" class="user-avatar user-avatar-img">
            <?php else: ?>
                <div class="user-avatar"><?php echo $userInitials; ?></div>
            <?php endif; ?>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($_SESSION['user_nivel'] ?? 'Usuario'); ?></div>
            </div>
        </a>
    </div>
</aside>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<style>
<?php include __DIR__ . '/sidebar_styles.css.php'; ?>
</style>

<script>
<?php include __DIR__ . '/sidebar_script.js.php'; ?>
</script>
