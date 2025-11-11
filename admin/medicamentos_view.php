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
                  f.nome AS fabricante_nome, 
                  c.nome AS categoria_nome,
                  u.nome AS unidade_nome,
                  u.sigla AS unidade_sigla
           FROM medicamentos m
           LEFT JOIN fabricantes f ON m.fabricante_id = f.id
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
                  DATEDIFF(l.data_validade, CURDATE()) AS dias_para_vencer
           FROM lotes l
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
        <main class="flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-12 lg:py-10 space-y-6 lg:space-y-8">
            <!-- Header -->
            <header class="flex flex-col gap-4 lg:gap-6">
                <div class="flex items-center gap-3 text-sm text-slate-500">
                    <a href="medicamentos.php" class="hover:text-primary-600 transition-colors">Medicamentos</a>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span class="text-slate-900 font-medium">Detalhes</span>
                </div>
                
                <div class="space-y-3 sm:space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div class="space-y-2">
                            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($medicamento['nome']); ?></h1>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500">
                                <?php if (!empty($medicamento['codigo_barras'])): ?>
                                    <div class="flex items-center gap-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <span><?php echo htmlspecialchars($medicamento['codigo_barras']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($medicamento['fabricante_nome'])): ?>
                                    <div class="flex items-center gap-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        <span><?php echo htmlspecialchars($medicamento['fabricante_nome']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-2.5">
                            <a href="medicamentos_form.php?id=<?php echo $id; ?>" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-5 py-2.5 text-sm text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Editar
                            </a>
                            <a href="lote_form.php?med_id=<?php echo $id; ?>" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm text-primary-600 font-semibold shadow hover:shadow-lg transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                Adicionar Lote
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
            <div class="grid gap-6 sm:grid-cols-2">
                <!-- Card Estoque -->
                <div class="glass-card p-6 space-y-4">
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
                <div class="glass-card p-6 space-y-4">
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
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Informações Gerais -->
                <div class="glass-card p-6 space-y-6">
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
                        <div class="flex justify-between py-3">
                            <span class="text-sm font-medium text-slate-500">Fabricante</span>
                            <span class="text-sm text-slate-900"><?php echo !empty($medicamento['fabricante_nome']) ? htmlspecialchars($medicamento['fabricante_nome']) : 'Não informado'; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Descrição -->
                <?php if (!empty($medicamento['descricao'])): ?>
                <div class="glass-card p-6 space-y-4">
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
                <div class="flex items-center justify-between px-6 py-4 border-b border-white/60 bg-white/70">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        Lotes Disponíveis
                    </h2>
                    <a href="lote_form.php?med_id=<?php echo $id; ?>" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-4 py-2 text-sm text-white font-semibold shadow hover:bg-primary-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Adicionar Lote
                    </a>
                </div>
                
                <?php if (count($lotes) > 0): ?>
                    <div class="responsive-table-wrapper">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-white/60">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
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
                                        <td data-label="Número" class="px-4 sm:px-6 py-3 sm:py-4 font-medium text-slate-900">
                                            <?php echo htmlspecialchars($lote['numero_lote']); ?>
                                        </td>
                                        <td data-label="Recebimento" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <?php echo empty($lote['data_recebimento']) ? 'N/A' : formatarData($lote['data_recebimento']); ?>
                                        </td>
                                        <td data-label="Validade" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <div class="flex flex-col gap-1">
                                                <span><?php echo empty($lote['data_validade']) ? 'N/A' : formatarData($lote['data_validade']); ?></span>
                                                <?php if (isset($lote['dias_para_vencer'])): ?>
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold w-fit <?php echo validadeBadgeClass($lote['dias_para_vencer']); ?>">
                                                        <?php echo $lote['dias_para_vencer'] < 0 ? 'Vencido' : $lote['dias_para_vencer'] . ' dias'; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td data-label="Qtd. Inicial" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <?php echo $lote['quantidade_total']; ?>
                                        </td>
                                        <td data-label="Qtd. Atual" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <span class="font-semibold <?php echo $lote['quantidade_atual'] <= 0 ? 'text-rose-600' : 'text-emerald-600'; ?>">
                                                <?php echo $lote['quantidade_atual']; ?>
                                            </span>
                                        </td>
                                        <td data-label="Fornecedor" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <?php echo htmlspecialchars($lote['fornecedor'] ?? 'N/A'); ?>
                                        </td>
                                        <td data-label="Ações" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="lote_form.php?med_id=<?php echo $id; ?>&id=<?php echo (int)$lote['id']; ?>" class="action-chip" title="Editar lote">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <p class="text-sm">Não há lotes cadastrados para este medicamento.</p>
                        <a href="lote_form.php?med_id=<?php echo $id; ?>" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-5 py-2 text-white text-sm font-semibold shadow hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            Cadastrar primeiro lote
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Botão Voltar -->
            <div class="flex justify-start">
                <a href="medicamentos.php" class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-slate-600 font-semibold shadow hover:shadow-lg transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Voltar para lista
                </a>
            </div>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
</body>
</html>

