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
$userFoto = null;

try {
    if (isset($_SESSION['user_id'])) {
        $conn = getConnection();
        $stmt = $conn->prepare('SELECT foto FROM usuarios WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $fotoRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fotoRow && !empty($fotoRow['foto'])) {
            $userFoto = $fotoRow['foto'];
        }
    }
} catch (Exception $e) {
    $userFoto = null;
}
?>

<button id="mobileMenuBtn" class="mobile-menu-btn">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>

<aside id="modernSidebar" class="modern-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <img src="../images/logo.svg" alt="Farmácia" style="width: 100%; height: 100%; object-fit: contain; padding: 4px;">
            </div>
            <span class="brand-text">Farmácia</span>
        </div>
        <button id="sidebarToggle" class="sidebar-toggle-btn" title="Recolher menu">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
            </svg>
        </button>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-item-wrapper">
            <a href="index.php" class="menu-item <?php echo in_array($currentPath, ['index.php']) ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/>
                </svg>
                <span class="menu-label">Dispensação</span>
            </a>
        </div>

        <div class="menu-item-wrapper">
            <a href="pacientes.php" class="menu-item <?php echo in_array($currentPath, ['pacientes.php', 'pacientes_form.php', 'paciente_historico.php']) ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0z"/>
                </svg>
                <span class="menu-label">Pacientes</span>
            </a>
            <a href="pacientes_form.php" class="add-btn" title="Cadastrar paciente">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
            </a>
        </div>

        <div class="menu-item-wrapper">
            <a href="receitas.php" class="menu-item <?php echo in_array($currentPath, ['receitas.php', 'receitas_form.php', 'receitas_dispensar.php']) ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z"/>
                </svg>
                <span class="menu-label">Receitas</span>
            </a>
            <a href="receitas_form.php" class="add-btn" title="Nova receita">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
            </a>
        </div>

        <div class="menu-item-wrapper">
            <a href="relatorios.php" class="menu-item <?php echo $currentPath === 'relatorios.php' ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z"/>
                </svg>
                <span class="menu-label">Relatórios</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <a href="perfil.php" class="user-profile" title="Editar perfil">
            <?php if (!empty($userFoto)): ?>
                <img src="../<?php echo htmlspecialchars($userFoto); ?>" alt="Foto do usuário" class="user-avatar user-avatar-img">
            <?php else: ?>
                <div class="user-avatar"><?php echo $userInitials; ?></div>
            <?php endif; ?>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="user-role">Usuário</div>
            </div>
        </a>

        <a href="../logout.php" class="logout-btn" title="Sair do sistema">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M18 12h-9m0 0 3-3m-3 3 3 3"/>
            </svg>
        </a>
    </div>
</aside>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<style>
<?php include __DIR__ . '/../../admin/includes/sidebar_styles.css.php'; ?>
</style>

<script>
<?php include __DIR__ . '/../../admin/includes/sidebar_script.js.php'; ?>
</script>
