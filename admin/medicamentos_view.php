<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['error'] = "ID de medicamento inválido.";
    header("Location: medicamentos.php");
    exit;
}

function estoqueBadgeClass(int $estoqueAtual, int $estoqueMinimo): string
{
    if ($estoqueAtual <= 0) {
        return 'bg-rose-100 text-rose-600';
    }
    if ($estoqueMinimo > 0 && $estoqueAtual <= $estoqueMinimo) {
        return 'bg-amber-100 text-amber-700';
    }
    return 'bg-emerald-100 text-emerald-600';
}

function validadeBadgeClass(?int $dias): string
{
    if ($dias === null) {
        return 'bg-slate-100 text-slate-500';
    }
    if ($dias < 0) {
        return 'bg-rose-100 text-rose-600';
    }
    if ($dias <= ALERTA_VENCIMENTO_CRITICO) {
        return 'bg-rose-100 text-rose-600';
    }
    if ($dias <= ALERTA_VENCIMENTO_ATENCAO) {
        return 'bg-amber-100 text-amber-700';
    }
    if ($dias <= ALERTA_VENCIMENTO_NORMAL) {
        return 'bg-sky-100 text-sky-700';
    }
    return 'bg-emerald-100 text-emerald-600';
}

try {
    $sql = "SELECT m.*, 
                  c.nome AS categoria_nome,
                  u.nome AS unidade_nome,
                  u.sigla AS unidade_sigla
           FROM medicamentos m
           LEFT JOIN categorias c ON m.categoria_id = c.id
           LEFT JOIN apresentacoes u ON m.apresentacao_id = u.id
           WHERE m.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(1, $id, PDO::PARAM_INT);
    $stmt->execute();
    $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$medicamento) {
        $_SESSION['error'] = "Medicamento não encontrado.";
        header("Location: medicamentos.php");
        exit;
    }
    
    $sql = "SELECT l.*,
                  cb.codigo as codigo_barras,
                  DATEDIFF(l.data_validade, CURDATE()) AS dias_para_vencer
           FROM lotes l
           LEFT JOIN codigos_barras cb ON l.codigo_barras_id = cb.id
           WHERE l.medicamento_id = ?
           ORDER BY l.data_validade ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(1, $id, PDO::PARAM_INT);
    $stmt->execute();
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erro ao buscar informações do medicamento: " . $e->getMessage();
    header("Location: medicamentos.php");
    exit;
}

$successMessage = getSuccessMessage();
$errorMessage = getErrorMessage();
$csrfToken = gerarCSRFToken();
$estoqueAtual = (int)($medicamento['estoque_atual'] ?? 0);
$estoqueMinimo = (int)($medicamento['estoque_minimo'] ?? 0);

$pageTitle = 'Detalhes do Medicamento';
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
            <div class="space-y-8">
            <!-- Header -->
            <header class="flex flex-col gap-3 sm:gap-4 lg:gap-6">
                <div class="flex items-center gap-2 sm:gap-3 text-xs sm:text-sm text-slate-500">
                    <a href="medicamentos.php" class="hover:text-primary-600 transition-colors">Medicamentos</a>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span class="text-slate-900 font-medium">Detalhes</span>
                </div>
                
                <div class="space-y-3 sm:space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4">
                        <div class="space-y-2 flex-1 min-w-0">
                            <h1 class="text-xl sm:text-2xl lg:text-3xl xl:text-4xl font-bold text-slate-900 break-words"><?php echo htmlspecialchars($medicamento['nome']); ?></h1>
                            <?php
                            // Buscar códigos de barras do medicamento
                            $stmt = $conn->prepare("SELECT codigo FROM codigos_barras WHERE medicamento_id = ? ORDER BY codigo ASC LIMIT 3");
                            $stmt->execute([$id]);
                            $codigos_barras_med = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            ?>
                            <?php if (!empty($codigos_barras_med)): ?>
                                <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500">
                                    <?php foreach ($codigos_barras_med as $cb): ?>
                                        <div class="flex items-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            <span class="font-mono text-xs"><?php echo htmlspecialchars($cb); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row flex-wrap gap-2 sm:gap-2.5 w-full sm:w-auto">
                            <a href="medicamentos_form.php?id=<?php echo $id; ?>" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-4 sm:px-5 py-2 sm:py-2.5 text-xs sm:text-sm text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Editar
                            </a>
                            <button onclick="document.getElementById('codigosBarrasModal').classList.remove('hidden')" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 sm:px-5 py-2 sm:py-2.5 text-xs sm:text-sm text-primary-600 font-semibold shadow hover:shadow-lg transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                                <span class="hidden sm:inline">Gerenciar Códigos de Barras</span>
                                <span class="sm:hidden">Códigos</span>
                            </button>
                            <a href="medicamentos_lotes.php?med_id=<?php echo $id; ?>" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 sm:px-5 py-2 sm:py-2.5 text-xs sm:text-sm text-primary-600 font-semibold shadow hover:shadow-lg transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                <span class="hidden sm:inline">Gerenciar Lotes</span>
                                <span class="sm:hidden">Lotes</span>
                            </a>
                        </div>
                    </div>
                    
                    <?php if ($medicamento['ativo']): ?>
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-1.5 text-sm font-medium text-emerald-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Ativo
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-2 rounded-full bg-rose-100 px-4 py-1.5 text-sm font-medium text-rose-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Inativo
                        </span>
                    <?php endif; ?>
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

            <!-- Cards de Resumo -->
            <div class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2">
                <!-- Card Estoque -->
                <div class="glass-card p-4 sm:p-6 space-y-3 sm:space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Estoque Atual</h3>
                        <div class="rounded-full bg-amber-100 p-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-slate-900"><?php echo $estoqueAtual; ?></div>
                        <p class="text-sm text-slate-500 mt-1">unidades disponíveis</p>
                        <div class="mt-3">
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?php echo estoqueBadgeClass($estoqueAtual, $estoqueMinimo); ?>">
                                <?php if ($estoqueAtual <= 0): ?>
                                    Estoque zerado
                                <?php elseif ($estoqueAtual <= $estoqueMinimo): ?>
                                    Estoque baixo
                                <?php else: ?>
                                    Estoque adequado
                                <?php endif; ?>
                            </span>
                            <span class="text-xs text-slate-400 ml-2">Mínimo: <?php echo $estoqueMinimo; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Card Lotes -->
                <div class="glass-card p-4 sm:p-6 space-y-3 sm:space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Lotes Cadastrados</h3>
                        <div class="rounded-full bg-sky-100 p-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        </div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-slate-900"><?php echo count($lotes); ?></div>
                        <p class="text-sm text-slate-500 mt-1">lotes registrados</p>
                        <?php if (count($lotes) > 0): ?>
                            <?php 
                            $lotesAtivos = array_filter($lotes, fn($l) => $l['quantidade_atual'] > 0);
                            $lotesVencidos = array_filter($lotes, fn($l) => isset($l['dias_para_vencer']) && $l['dias_para_vencer'] < 0);
                            ?>
                            <div class="mt-3 text-xs text-slate-400">
                                <?php echo count($lotesAtivos); ?> ativos
                                <?php if (count($lotesVencidos) > 0): ?>
                                    <span class="mx-1">•</span>
                                    <span class="text-rose-600"><?php echo count($lotesVencidos); ?> vencidos</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Informações Detalhadas -->
            <div class="grid gap-4 sm:gap-6 grid-cols-1 lg:grid-cols-2">
                <!-- Informações Gerais -->
                <div class="glass-card p-4 sm:p-6 space-y-4 sm:space-y-6">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Informações Gerais
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between py-3 border-b border-slate-100">
                            <span class="text-sm font-medium text-slate-500">Categoria</span>
                            <span class="text-sm text-slate-900"><?php echo !empty($medicamento['categoria_nome']) ? htmlspecialchars($medicamento['categoria_nome']) : 'Não informado'; ?></span>
                        </div>
                        <div class="flex justify-between py-3 border-b border-slate-100">
                            <span class="text-sm font-medium text-slate-500">Apresentação</span>
                            <span class="text-sm text-slate-900">
                                <?php 
                                if (!empty($medicamento['unidade_nome'])) {
                                    echo htmlspecialchars($medicamento['unidade_nome']);
                                    if (!empty($medicamento['unidade_sigla'])) {
                                        echo ' (' . htmlspecialchars($medicamento['unidade_sigla']) . ')';
                                    }
                                } else {
                                    echo 'Não informado';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Descrição -->
                <?php if (!empty($medicamento['descricao'])): ?>
                <div class="glass-card p-4 sm:p-6 space-y-3 sm:space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Descrição
                    </h2>
                    <p class="text-sm text-slate-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($medicamento['descricao'])); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tabela de Lotes -->
            <div class="glass-card p-0 overflow-hidden">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-0 px-4 sm:px-6 py-3 sm:py-4 border-b border-white/60 bg-white/70">
                    <h2 class="text-base sm:text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-5 sm:h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        Lotes Disponíveis
                    </h2>
                    <a href="medicamentos_lotes.php?med_id=<?php echo $id; ?>" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-3 sm:px-4 py-1.5 sm:py-2 text-xs sm:text-sm text-white font-semibold shadow hover:bg-primary-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Gerenciar Lotes
                    </a>
                </div>
                
                <?php if (count($lotes) > 0): ?>
                    <!-- Desktop Table -->
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-white/60">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-4 sm:px-6 py-3">Código de Barras</th>
                                    <th class="px-4 sm:px-6 py-3">Número do Lote</th>
                                    <th class="px-4 sm:px-6 py-3">Recebimento</th>
                                    <th class="px-4 sm:px-6 py-3">Validade</th>
                                    <th class="px-4 sm:px-6 py-3">Qtd. Inicial</th>
                                    <th class="px-4 sm:px-6 py-3">Qtd. Atual</th>
                                    <th class="px-4 sm:px-6 py-3">Fornecedor</th>
                                    <th class="px-4 sm:px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white/80">
                                <?php foreach ($lotes as $lote): ?>
                                    <tr class="text-sm text-slate-600">
                                        <td class="px-4 sm:px-6 py-3 sm:py-4">
                                            <span class="font-mono text-xs text-slate-500"><?php echo !empty($lote['codigo_barras']) ? htmlspecialchars($lote['codigo_barras']) : '—'; ?></span>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4 font-medium text-slate-900">
                                            <?php echo htmlspecialchars($lote['numero_lote']); ?>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4">
                                            <?php echo empty($lote['data_recebimento']) ? 'N/A' : formatarData($lote['data_recebimento']); ?>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4">
                                            <div class="flex flex-col gap-1">
                                                <span><?php echo empty($lote['data_validade']) ? 'N/A' : formatarData($lote['data_validade']); ?></span>
                                                <?php if (isset($lote['dias_para_vencer'])): ?>
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold w-fit <?php echo validadeBadgeClass($lote['dias_para_vencer']); ?>">
                                                        <?php echo $lote['dias_para_vencer'] < 0 ? 'Vencido' : $lote['dias_para_vencer'] . ' dias'; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4">
                                            <?php echo $lote['quantidade_total']; ?>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4">
                                            <span class="font-semibold <?php echo $lote['quantidade_atual'] <= 0 ? 'text-rose-600' : 'text-emerald-600'; ?>">
                                                <?php echo $lote['quantidade_atual']; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4">
                                            <?php echo htmlspecialchars($lote['fornecedor'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3 sm:py-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="medicamentos_lotes.php?med_id=<?php echo $id; ?>&edit=<?php echo (int)$lote['id']; ?>" class="action-chip" title="Editar lote">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Mobile Cards -->
                    <div class="lg:hidden space-y-4">
                        <?php foreach ($lotes as $lote): ?>
                            <div class="p-4 sm:p-5 bg-white/80 rounded-lg border border-slate-200 shadow-sm hover:shadow-md transition-all space-y-3">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Lote</p>
                                        <p class="text-lg font-bold text-slate-900"><?php echo htmlspecialchars($lote['numero_lote']); ?></p>
                                        <?php if (!empty($lote['codigo_barras'])): ?>
                                            <p class="text-xs text-slate-400 mt-1 font-mono">Código: <?php echo htmlspecialchars($lote['codigo_barras']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <a href="medicamentos_lotes.php?med_id=<?php echo $id; ?>&edit=<?php echo (int)$lote['id']; ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-500 hover:bg-blue-600 text-white transition-all shadow-sm hover:shadow-md flex-shrink-0" title="Editar lote">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                                    <div>
                                        <p class="text-xs text-slate-500 mb-1">Recebimento</p>
                                        <p class="text-sm font-medium text-slate-700"><?php echo empty($lote['data_recebimento']) ? 'N/A' : formatarData($lote['data_recebimento']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500 mb-1">Validade</p>
                                        <p class="text-sm font-medium text-slate-700"><?php echo empty($lote['data_validade']) ? 'N/A' : formatarData($lote['data_validade']); ?></p>
                                        <?php if (isset($lote['dias_para_vencer'])): ?>
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold mt-1 <?php echo validadeBadgeClass($lote['dias_para_vencer']); ?>">
                                                <?php echo $lote['dias_para_vencer'] < 0 ? 'Vencido' : $lote['dias_para_vencer'] . ' dias'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500 mb-1">Qtd. Inicial</p>
                                        <p class="text-sm font-medium text-slate-700"><?php echo $lote['quantidade_total']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500 mb-1">Qtd. Atual</p>
                                        <p class="text-sm font-semibold <?php echo $lote['quantidade_atual'] <= 0 ? 'text-rose-600' : 'text-emerald-600'; ?>">
                                            <?php echo $lote['quantidade_atual']; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($lote['fornecedor'])): ?>
                                    <div class="pt-2 border-t border-slate-100">
                                        <p class="text-xs text-slate-500 mb-1">Fornecedor</p>
                                        <p class="text-sm text-slate-700"><?php echo htmlspecialchars($lote['fornecedor']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <p class="text-sm">Não há lotes cadastrados para este medicamento.</p>
                        <a href="medicamentos_lotes.php?med_id=<?php echo $id; ?>" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-5 py-2 text-white text-sm font-semibold shadow hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            Gerenciar Lotes
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Botão Voltar -->
            <div class="flex justify-start">
                <a href="medicamentos.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 sm:px-6 py-2 sm:py-3 text-xs sm:text-sm text-slate-600 font-semibold shadow hover:shadow-lg transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Voltar para lista
                </a>
            </div>
            </div>
        </main>
    </div>

    <!-- Modal Gerenciar Códigos de Barras -->
    <div id="codigosBarrasModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="glass-card w-full max-w-3xl my-8">
            <div class="flex items-center justify-between px-8 py-6 border-b border-white/60">
                <h3 class="text-2xl font-bold text-slate-900">Gerenciar Códigos de Barras</h3>
                <button onclick="document.getElementById('codigosBarrasModal').classList.add('hidden')" type="button" class="rounded-full p-2 hover:bg-slate-100 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-8 space-y-6">
                <!-- Formulário para adicionar novo código -->
                <div class="bg-slate-50 rounded-2xl p-6 space-y-4">
                    <h4 class="text-lg font-semibold text-slate-900">Adicionar Novo Código de Barras</h4>
                    <form id="formAddCodigo" class="flex gap-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="medicamento_id" value="<?php echo $id; ?>">
                        <input type="text" name="codigo" id="novoCodigo" placeholder="Digite ou escaneie o código de barras" class="flex-1 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" required autofocus>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-6 py-3 text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6"/></svg>
                            Adicionar
                        </button>
                    </form>
                </div>

                <!-- Lista de códigos existentes -->
                <div>
                    <h4 class="text-lg font-semibold text-slate-900 mb-4">Códigos Cadastrados</h4>
                    <div id="codigosList" class="space-y-2">
                        <div class="text-center text-slate-400 py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500 mx-auto"></div>
                            <p class="mt-2 text-sm">Carregando...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/sidebar.js" defer></script>
    <script>
        const medicamentoId = <?php echo $id; ?>;
        const csrfToken = '<?php echo htmlspecialchars($csrfToken); ?>';
        let codigosEditando = {};

        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('codigosBarrasModal');
            const formAdd = document.getElementById('formAddCodigo');
            
            // Carregar códigos quando o modal abrir
            if (modal) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                            if (!modal.classList.contains('hidden')) {
                                carregarCodigos();
                            }
                        }
                    });
                });
                observer.observe(modal, { attributes: true });
            }
            
            // Adicionar novo código
            if (formAdd) {
                formAdd.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    await adicionarCodigo();
                });
            }
        });

        async function carregarCodigos() {
            const lista = document.getElementById('codigosList');
            
            try {
                const response = await fetch(`api/listar_codigos_barras.php?medicamento_id=${medicamentoId}`);
                const data = await response.json();
                
                if (data.success && data.codigos && data.codigos.length > 0) {
                    lista.innerHTML = data.codigos.map(cb => {
                        const isEditando = codigosEditando[cb.id];
                        return `
                            <div class="glass-card p-4 flex items-center justify-between" data-id="${cb.id}">
                                ${isEditando ? `
                                    <input type="text" id="edit_codigo_${cb.id}" value="${cb.codigo}" class="flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                                ` : `
                                    <div class="flex items-center gap-3">
                                        <span class="font-mono text-sm text-slate-900">${cb.codigo}</span>
                                        <span class="text-xs text-slate-400">Cadastrado em ${new Date(cb.criado_em).toLocaleDateString('pt-BR')}</span>
                                    </div>
                                `}
                                <div class="flex items-center gap-2">
                                    ${isEditando ? `
                                        <button onclick="salvarCodigo(${cb.id})" class="action-chip" title="Salvar">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                        <button onclick="cancelarEdicao(${cb.id})" class="action-chip" title="Cancelar">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    ` : `
                                        <button onclick="editarCodigo(${cb.id})" class="action-chip" title="Editar">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button onclick="deletarCodigo(${cb.id}, '${cb.codigo.replace(/'/g, "\\'")}')" class="action-chip danger" title="Excluir">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7.5h12M9 7.5V6a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 6v1.5m-6 0v10.5A1.5 1.5 0 0 0 10.5 21h3A1.5 1.5 0 0 0 15 19.5V7.5"/></svg>
                                        </button>
                                    `}
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    lista.innerHTML = `
                        <div class="text-center text-slate-400 py-8">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                            <p class="text-sm">Nenhum código de barras cadastrado</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Erro ao carregar códigos:', error);
                lista.innerHTML = `
                    <div class="text-center text-red-500 py-8">
                        <p class="text-sm">Erro ao carregar códigos de barras</p>
                    </div>
                `;
            }
        }

        async function adicionarCodigo() {
            const codigo = document.getElementById('novoCodigo').value.trim();
            
            if (!codigo) {
                alert('Digite um código de barras');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'add');
                formData.append('csrf_token', csrfToken);
                formData.append('medicamento_id', medicamentoId);
                formData.append('codigo', codigo);
                
                const response = await fetch('api/gerenciar_codigo_barras.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('novoCodigo').value = '';
                    carregarCodigos();
                } else {
                    alert('Erro: ' + data.message);
                }
            } catch (error) {
                console.error('Erro ao adicionar código:', error);
                alert('Erro ao adicionar código de barras');
            }
        }

        function editarCodigo(id) {
            codigosEditando[id] = true;
            carregarCodigos();
        }

        function cancelarEdicao(id) {
            delete codigosEditando[id];
            carregarCodigos();
        }

        async function salvarCodigo(id) {
            const codigo = document.getElementById(`edit_codigo_${id}`).value.trim();
            
            if (!codigo) {
                alert('Digite um código de barras');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'edit');
                formData.append('csrf_token', csrfToken);
                formData.append('id', id);
                formData.append('codigo', codigo);
                
                const response = await fetch('api/gerenciar_codigo_barras.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    delete codigosEditando[id];
                    carregarCodigos();
                } else {
                    alert('Erro: ' + data.message);
                }
            } catch (error) {
                console.error('Erro ao salvar código:', error);
                alert('Erro ao salvar código de barras');
            }
        }

        async function deletarCodigo(id, codigo) {
            if (!confirm(`Confirma a exclusão do código de barras "${codigo}"?`)) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('csrf_token', csrfToken);
                formData.append('id', id);
                
                const response = await fetch('api/gerenciar_codigo_barras.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    carregarCodigos();
                } else {
                    alert('Erro: ' + data.message);
                }
            } catch (error) {
                console.error('Erro ao deletar código:', error);
                alert('Erro ao deletar código de barras');
            }
        }
    </script>
</body>
</html>

