<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

$error = $success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'categorias';
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_data = [];

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verificarCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Erro de segurança. Por favor, tente novamente.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_categoria':
                $nome = trim($_POST['nome'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                
                if (empty($nome)) {
                    $_SESSION['error'] = "O nome da categoria é obrigatório.";
                } else {
                    try {
                        $stmt = $conn->prepare("INSERT INTO categorias (nome, descricao, ativo) VALUES (?, ?, 1)");
                        $stmt->execute([$nome, $descricao]);
                        $_SESSION['success'] = "Categoria adicionada com sucesso!";
                    } catch (PDOException $e) {
                        $_SESSION['error'] = "Erro ao adicionar categoria: " . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_categoria':
                $id = (int)$_POST['id'];
                $nome = trim($_POST['nome'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                
                if (empty($nome)) {
                    $_SESSION['error'] = "O nome da categoria é obrigatório.";
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE categorias SET nome = ?, descricao = ? WHERE id = ?");
                        $stmt->execute([$nome, $descricao, $id]);
                        $_SESSION['success'] = "Categoria atualizada com sucesso!";
                    } catch (PDOException $e) {
                        $_SESSION['error'] = "Erro ao atualizar categoria: " . $e->getMessage();
                    }
                }
                break;
                
            case 'toggle_status_categoria':
                $id = (int)$_POST['id'];
                $status = (int)$_POST['status'];
                $novo_status = $status ? 0 : 1;
                
                try {
                    $stmt = $conn->prepare("UPDATE categorias SET ativo = ? WHERE id = ?");
                    $stmt->execute([$novo_status, $id]);
                    $_SESSION['success'] = "Status da categoria atualizado!";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Erro ao atualizar status: " . $e->getMessage();
                }
                break;
                
            case 'add_unidade':
                $nome = trim($_POST['nome'] ?? '');
                $sigla = trim($_POST['sigla'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                
                if (empty($nome) || empty($sigla)) {
                    $_SESSION['error'] = "O nome e a sigla da unidade são obrigatórios.";
                } else {
                    try {
                        $stmt = $conn->prepare("INSERT INTO apresentacoes (nome, sigla, descricao, ativo) VALUES (?, ?, ?, 1)");
                        $stmt->execute([$nome, $sigla, $descricao]);
                        $_SESSION['success'] = "Apresentação adicionada com sucesso!";
                    } catch (PDOException $e) {
                        $_SESSION['error'] = "Erro ao adicionar unidade: " . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_unidade':
                $id = (int)$_POST['id'];
                $nome = trim($_POST['nome'] ?? '');
                $sigla = trim($_POST['sigla'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                
                if (empty($nome) || empty($sigla)) {
                    $_SESSION['error'] = "O nome e a sigla da unidade são obrigatórios.";
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE apresentacoes SET nome = ?, sigla = ?, descricao = ? WHERE id = ?");
                        $stmt->execute([$nome, $sigla, $descricao, $id]);
                        $_SESSION['success'] = "Apresentação atualizada com sucesso!";
                    } catch (PDOException $e) {
                        $_SESSION['error'] = "Erro ao atualizar unidade: " . $e->getMessage();
                    }
                }
                break;
                
            case 'toggle_status_unidade':
                $id = (int)$_POST['id'];
                $status = (int)$_POST['status'];
                $novo_status = $status ? 0 : 1;
                
                try {
                    $stmt = $conn->prepare("UPDATE apresentacoes SET ativo = ? WHERE id = ?");
                    $stmt->execute([$novo_status, $id]);
                    $_SESSION['success'] = "Status da apresentação atualizado!";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Erro ao atualizar status: " . $e->getMessage();
                }
                break;
        }
    }
    
    header("Location: configuracoes.php?tab=$active_tab");
    exit;
}

// Carregar dados para edição
if ($edit_id > 0) {
    try {
        switch ($active_tab) {
            case 'categorias':
                $stmt = $conn->prepare("SELECT * FROM categorias WHERE id = ?");
                break;
            case 'unidades':
                $stmt = $conn->prepare("SELECT * FROM apresentacoes WHERE id = ?");
                break;
            default:
                $stmt = $conn->prepare("SELECT * FROM categorias WHERE id = ?");
                break;
        }
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erro ao carregar dados para edição: " . $e->getMessage();
    }
}

// Buscar dados de todas as tabelas
$categorias = $unidades = [];

try {
    $categorias = $conn->query("SELECT * FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    $unidades = $conn->query("SELECT * FROM apresentacoes ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Erro ao buscar dados: " . $e->getMessage();
}

$csrf_token = gerarCSRFToken();
$successMessage = getSuccessMessage();
$errorMessage = getErrorMessage();

$pageTitle = 'Configurações do Sistema';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Controle de medicamentos, lotes, pacientes e receitas">
    <meta name="keywords" content="farmácia, medicamentos, gestão, controle de estoque, receitas">
    <meta name="author" content="Sistema Farmácia">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?> - <?php echo SYSTEM_NAME; ?>">
    <meta property="og:description" content="Sistema de gestão de farmácia">
    <meta property="og:type" content="website">
    <meta property="og:image" content="../images/logo.svg">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="../images/logo.svg">
    <link rel="apple-touch-icon" href="../images/logo.svg">
    
    <?php include '../includes/pwa_head.php'; ?>
    
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo SYSTEM_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3'
                        },
                        accent: {
                            400: '#f472b6',
                            500: '#ec4899',
                            600: '#db2777'
                        }
                    },
                    boxShadow: {
                        glow: '0 25px 60px rgba(99, 102, 241, 0.2)'
                    },
                    borderRadius: {
                        ultra: '32px'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="../css/admin_new.css">
</head>
<body class="admin-shell">
    <button id="mobileMenuButton" class="mobile-menu-button" aria-label="Abrir menu">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div id="mobileMenuOverlay" class="mobile-menu-overlay"></div>
    <div class="flex min-h-screen">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <main class="content-area">
            <div class="space-y-10">
            <!-- Header -->
            <header class="flex flex-col gap-4 lg:gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2 sm:space-y-3">
                    <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Sistema</span>
                    <div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="mt-2 text-sm sm:text-base text-slate-500 max-w-2xl">Gerencie categorias e apresentações dos medicamentos.</p>
                    </div>
                </div>
            </header>

            <?php if (!empty($errorMessage)): ?>
                <div class="glass-card border border-rose-200/60 bg-rose-50/80 px-6 py-4 text-rose-700">
                    <strong class="block text-sm font-semibold">Atenção</strong>
                    <span class="text-sm"><?php echo htmlspecialchars($errorMessage); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($successMessage)): ?>
                <div class="glass-card border border-emerald-200/60 bg-emerald-50/80 px-6 py-4 text-emerald-700">
                    <strong class="block text-sm font-semibold">Tudo certo!</strong>
                    <span class="text-sm"><?php echo htmlspecialchars($successMessage); ?></span>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="glass-card p-0 overflow-hidden">
                <div class="flex flex-wrap gap-1 sm:gap-2 p-2 sm:p-3 bg-white/70 border-b border-white/60">
                    <a href="?tab=categorias" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-lg px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-semibold transition-all <?php echo $active_tab === 'categorias' ? 'bg-primary-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        <span>Categorias</span>
                    </a>
                    <a href="?tab=unidades" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-lg px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-semibold transition-all <?php echo $active_tab === 'unidades' ? 'bg-primary-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100'; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span>Apresentações</span>
                    </a>
                </div>

                <!-- Content -->
                <div class="p-4 sm:p-6">
                    <?php
                    // Determinar qual conteúdo mostrar
                    $tab_config = [
                        'categorias' => [
                            'title' => 'Categoria',
                            'data' => $categorias,
                            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>',
                            'fields' => ['nome', 'descricao'],
                            'form_type' => 'categoria'
                        ],
                        'unidades' => [
                            'title' => 'Apresentação',
                            'data' => $unidades,
                            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>',
                            'fields' => ['nome', 'sigla', 'descricao'],
                            'form_type' => 'unidade'
                        ]
                    ];

                    $config = $tab_config[$active_tab] ?? $tab_config['categorias'];
                    include __DIR__ . '/includes/config_tab_content.php';
                    ?>
                </div>
            </div>
            </div>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
</body>
</html>

