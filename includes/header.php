<?php
// Verificar se o usuário está logado, caso contrário redirecionar para a página de login
if (!isLoggedIn()) {
    // Determinar o caminho relativo para a página de login
    $base_path = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1);
    if ($base_path == '') $base_path = './';
    
    header("Location: {$base_path}login.php");
    exit;
}
?>
<header>
    <!-- Barra de navegação -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-pharmacy">
        <div class="container-fluid">
            <?php
            // Determinar o caminho relativo para as páginas
            $base_path = '';
            $current_dir = dirname($_SERVER['PHP_SELF']);
            
            // Se estamos em um subdiretório como /admin ou /usuario
            if (strpos($current_dir, '/admin') !== false) {
                $base_path = '../';
            } else if (strpos($current_dir, '/usuario') !== false) {
                $base_path = '../';
            }
            ?>
            <a class="navbar-brand" href="<?php echo $base_path; ?>admin/dashboard.php">
                <i class="fas fa-pills me-2"></i>
                <?php echo COMPANY_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navMedicamentos" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-pills me-1"></i> Medicamentos
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navMedicamentos">
                            <li><a class="dropdown-item" href="medicamentos.php">Listar Todos</a></li>
                            <li><a class="dropdown-item" href="medicamentos_form.php">Cadastrar Novo</a></li>
                            <li><a class="dropdown-item" href="lotes.php">Gerenciar Lotes</a></li>
                            <li><a class="dropdown-item" href="medicamentos_vencimento.php">Controle de Validade</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navMovimentacao" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-exchange-alt me-1"></i> Movimentação
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navMovimentacao">
                            <li><a class="dropdown-item" href="saidas.php">Listar Todas</a></li>
                            <li><a class="dropdown-item" href="saida_form.php">Registrar Saída</a></li>
                        </ul>
                    </li>
                    
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navRelatorios" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-chart-bar me-1"></i> Relatórios
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navRelatorios">
                            <li><a class="dropdown-item" href="relatorios.php?tipo=estoque">Estoque Atual</a></li>
                            <li><a class="dropdown-item" href="relatorios.php?tipo=vencimento">Medicamentos a Vencer</a></li>
                            <li><a class="dropdown-item" href="relatorios.php?tipo=movimentacao">Movimentações</a></li>
                            <li><a class="dropdown-item" href="relatorios.php?tipo=estoque_minimo">Estoque Mínimo</a></li>
                        </ul>
                    </li>
                    
                    <?php if (isAdmin()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navAdministracao" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cogs me-1"></i> Administração
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navAdministracao">
                            <li><a class="dropdown-item" href="usuarios.php">Usuários</a></li>
                            <li><a class="dropdown-item" href="configuracoes.php">Configurações</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Menu do Usuário -->
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navUsuario" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> 
                            <?php echo htmlspecialchars($_SESSION['user_nome']); ?>
                            <?php if (isAdmin()): ?>
                            <span class="badge bg-danger ms-1">Admin</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navUsuario">
                            <?php if (isAdmin()): ?>
                                <li><a class="dropdown-item" href="../admin/perfil.php"><i class="fas fa-user me-2"></i> Meu Perfil</a></li>
                                <li><a class="dropdown-item" href="../admin/alterar_senha.php"><i class="fas fa-key me-2"></i> Alterar Senha</a></li>
                                <li><a class="dropdown-item" href="../admin/usuarios.php"><i class="fas fa-users-cog me-2"></i> Gerenciar Usuários</a></li>
                                <li><a class="dropdown-item" href="../admin/configuracoes.php"><i class="fas fa-cogs me-1"></i> Configurações</a></li>

                            <?php else: ?>
                                <li><a class="dropdown-item" href="../usuario/perfil.php"><i class="fas fa-user me-2"></i> Meu Perfil</a></li>
                                <li><a class="dropdown-item" href="../usuario/alterar_senha.php"><i class="fas fa-key me-2"></i> Alterar Senha</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Só ativa o hover em telas grandes (desktop)
    if (window.innerWidth > 991) {
        const dropdowns = document.querySelectorAll('.dropdown');
        dropdowns.forEach(dropdown => {
            dropdown.addEventListener('mouseenter', function() {
                const menu = this.querySelector('.dropdown-menu');
                const toggle = this.querySelector('.dropdown-toggle');
                menu.classList.add('show');
                toggle.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
            });
            dropdown.addEventListener('mouseleave', function() {
                const menu = this.querySelector('.dropdown-menu');
                const toggle = this.querySelector('.dropdown-toggle');
                menu.classList.remove('show');
                toggle.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    }
});
</script>
<!-- Bootstrap JS Bundle (inclui Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

