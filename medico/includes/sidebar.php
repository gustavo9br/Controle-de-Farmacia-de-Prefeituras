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
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['foto'])) {
            $userFoto = $result['foto'];
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
            <a href="medicamentos.php" class="menu-item <?php echo in_array($currentPath, ['medicamentos.php', 'index.php']) ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 4.5 0v-8.873l1.732-4.327A2.25 2.25 0 0 1 10.632 3h4.736c.963 0 1.843.58 2.249 1.469L19.35 8.796c.256.51.394 1.083.394 1.668V18a2.25 2.25 0 0 0 4.5 0v-4.162c0-.224-.034-.447-.1-.661L21.738 5.338A2.25 2.25 0 0 0 19.588 3.75H17.25"/>
                </svg>
                <span class="menu-label">Medicamentos</span>
            </a>
        </div>

        <div class="menu-item-wrapper">
            <a href="pacientes.php" class="menu-item <?php echo $currentPath === 'pacientes.php' ? 'active' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0z"/>
                </svg>
                <span class="menu-label">Pacientes</span>
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
                <div class="user-role">Médico</div>
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
