<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterEstoque = isset($_GET['filter_estoque']) ? $_GET['filter_estoque'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'nome';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = ITENS_POR_PAGINA;
$offset = ($page - 1) * $perPage;

$allowedSorts = [
    'nome' => 'm.nome',
    'estoque_atual' => 'm.estoque_atual',
    'proxima_validade' => 'proxima_validade',
    'fabricante' => 'fabricante_nome'
];

$sortColumn = $allowedSorts[$sort] ?? $allowedSorts['nome'];
$order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = '(m.nome LIKE ? OR m.codigo_barras LIKE ? OR f.nome LIKE ?)';
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($filterEstoque === 'baixo') {
    $whereClauses[] = 'm.estoque_atual > 0 AND m.estoque_atual <= m.estoque_minimo';
} elseif ($filterEstoque === 'zerado') {
    $whereClauses[] = 'm.estoque_atual <= 0';
} elseif ($filterEstoque === 'disponivel') {
    $whereClauses[] = 'm.estoque_atual > m.estoque_minimo';
}

$whereClause = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'delete') {
    $deleteId = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : 0;
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!$deleteId) {
        $_SESSION['error'] = 'Medicamento inválido.';
    } elseif (!verificarCSRFToken($csrfToken)) {
        $_SESSION['error'] = 'Token de segurança inválido. Atualize a página e tente novamente.';
    } elseif (!temPermissao('excluir_medicamento')) {
        $_SESSION['error'] = 'Você não tem permissão para excluir medicamentos.';
    } else {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare('SELECT COUNT(*) FROM movimentacoes WHERE medicamento_id = ?');
            $stmt->bindValue(1, $deleteId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->fetchColumn() > 0) {
                throw new RuntimeException('Este medicamento está associado a movimentações e não pode ser removido.');
            }

            $stmt = $conn->prepare('DELETE FROM lotes WHERE medicamento_id = ?');
            $stmt->bindValue(1, $deleteId, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $conn->prepare('DELETE FROM medicamentos WHERE id = ?');
            $stmt->bindValue(1, $deleteId, PDO::PARAM_INT);
            $stmt->execute();

            $conn->commit();
            $_SESSION['success'] = 'Medicamento removido com sucesso.';
        } catch (Throwable $exception) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error'] = 'Erro ao excluir medicamento: ' . $exception->getMessage();
        }
    }

    header('Location: medicamentos.php');
    exit;
}

try {
    $countSql = 'SELECT COUNT(*) FROM medicamentos m LEFT JOIN fabricantes f ON m.fabricante_id = f.id ' . $whereClause;
    $stmt = $conn->prepare($countSql);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    $stmt->execute();
    $totalRecords = (int)$stmt->fetchColumn();

    $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $dataSql = "SELECT m.*, f.nome AS fabricante_nome,
                (SELECT MIN(l.data_validade) FROM lotes l WHERE l.medicamento_id = m.id AND l.quantidade_atual > 0) AS proxima_validade,
                (SELECT DATEDIFF(MIN(l.data_validade), CURDATE()) FROM lotes l WHERE l.medicamento_id = m.id AND l.quantidade_atual > 0) AS dias_para_vencer
                FROM medicamentos m
                LEFT JOIN fabricantes f ON m.fabricante_id = f.id
                $whereClause
                ORDER BY $sortColumn $order
                LIMIT :offset, :limit";

    $stmt = $conn->prepare($dataSql);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Erro ao carregar medicamentos: ' . $e->getMessage();
    $totalRecords = 0;
    $medicamentos = [];
    $totalPages = 1;
}

$successMessage = getSuccessMessage();
$errorMessage = isset($error) ? $error : getErrorMessage();
$csrfToken = gerarCSRFToken();

$pageTitle = 'Medicamentos cadastrados';
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
        <main class="flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-12 lg:py-10 space-y-6 lg:space-y-10">
            <header class="flex flex-col gap-4 lg:gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2 sm:space-y-3">
                    <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Medicamentos</span>
                    <div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="mt-2 text-sm sm:text-base text-slate-500 max-w-2xl">Consulte o catálogo institucional, monitore estoque e acompanhe a validade dos lotes vinculados.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-3 lg:items-end w-full lg:w-auto">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/90 px-4 sm:px-5 py-1.5 sm:py-2 text-xs sm:text-sm text-slate-500 shadow">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                        <?php echo number_format($totalRecords, 0, ',', '.'); ?> medicamentos cadastrados
                    </span>
                    <div class="flex flex-col sm:flex-row gap-2.5 sm:gap-3 w-full sm:w-auto">
                        <a href="medicamentos_form.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-5 sm:px-6 py-2.5 sm:py-3 text-sm sm:text-base text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6"/></svg>
                            <span class="hidden sm:inline">Cadastrar medicamento</span>
                            <span class="sm:hidden">Novo medicamento</span>
                        </a>
                        <a href="../admin_old/medicamentos_lotes.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-5 sm:px-6 py-2.5 sm:py-3 text-sm sm:text-base text-primary-600 font-semibold shadow hover:shadow-lg transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 7.5h16.5M3.75 12h16.5M3.75 16.5h16.5"/></svg>
                            <span class="hidden sm:inline">Gerenciar lotes</span>
                            <span class="sm:hidden">Lotes</span>
                        </a>
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

            <section class="glass-card p-6 space-y-6">
                <form method="get" class="grid gap-5 lg:grid-cols-12">
                    <div class="lg:col-span-4">
                        <label for="search" class="text-sm font-medium text-slate-600">Buscar por nome, código ou fabricante</label>
                        <div class="relative mt-2">
                            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 pl-11 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="Ex.: Dipirona" autocomplete="off">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                        </div>
                    </div>
                    <div class="lg:col-span-3">
                        <label for="filter_estoque" class="text-sm font-medium text-slate-600">Situação do estoque</label>
                        <select name="filter_estoque" id="filter_estoque" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                            <option value="" <?php echo $filterEstoque === '' ? 'selected' : ''; ?>>Todos</option>
                            <option value="baixo" <?php echo $filterEstoque === 'baixo' ? 'selected' : ''; ?>>Próximo ao mínimo</option>
                            <option value="zerado" <?php echo $filterEstoque === 'zerado' ? 'selected' : ''; ?>>Zerado</option>
                            <option value="disponivel" <?php echo $filterEstoque === 'disponivel' ? 'selected' : ''; ?>>Disponível</option>
                        </select>
                    </div>
                    <div class="lg:col-span-3">
                        <label for="sort" class="text-sm font-medium text-slate-600">Ordenar por</label>
                        <select name="sort" id="sort" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                            <option value="nome" <?php echo $sort === 'nome' ? 'selected' : ''; ?>>Nome</option>
                            <option value="estoque_atual" <?php echo $sort === 'estoque_atual' ? 'selected' : ''; ?>>Estoque</option>
                            <option value="proxima_validade" <?php echo $sort === 'proxima_validade' ? 'selected' : ''; ?>>Validade</option>
                            <option value="fabricante" <?php echo $sort === 'fabricante' ? 'selected' : ''; ?>>Fabricante</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="order" class="text-sm font-medium text-slate-600">Ordem</label>
                        <select name="order" id="order" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                            <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Crescente</option>
                            <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Decrescente</option>
                        </select>
                    </div>
                    <div class="lg:col-span-12 flex flex-wrap gap-3">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-6 py-3 text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                            Aplicar filtros
                        </button>
                        <a href="medicamentos.php" class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-slate-500 font-semibold shadow hover:shadow-lg transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 4.5l15 15M19.5 4.5l-15 15"/></svg>
                            Limpar
                        </a>
                    </div>
                </form>
            </section>

            <section class="glass-card p-0 overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0 px-4 sm:px-6 py-3 sm:py-4 border-b border-white/60 bg-white/70">
                    <h2 class="text-base sm:text-lg font-semibold text-slate-900">Lista de medicamentos</h2>
                    <span class="rounded-full bg-primary-50 px-3 sm:px-4 py-1 text-xs sm:text-sm font-medium text-primary-600 self-start sm:self-auto">Página <?php echo $totalRecords > 0 ? $page : 0; ?> de <?php echo $totalRecords > 0 ? $totalPages : 0; ?></span>
                </div>
                <?php if (count($medicamentos) > 0): ?>
                    <div class="responsive-table-wrapper">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-white/60">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-4 sm:px-6 py-3">Medicamento</th>
                                    <th class="px-4 sm:px-6 py-3">Fabricante</th>
                                    <th class="px-4 sm:px-6 py-3">Estoque</th>
                                    <th class="px-4 sm:px-6 py-3">Próxima validade</th>
                                    <th class="px-4 sm:px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white/80">
                                <?php foreach ($medicamentos as $med): ?>
                                    <?php
                                        $estoqueAtual = (int)($med['estoque_atual'] ?? 0);
                                        $estoqueMinimo = (int)($med['estoque_minimo'] ?? 0);
                                        $proximaValidade = $med['proxima_validade'] ?? null;
                                        $diasParaVencer = isset($med['dias_para_vencer']) ? (int)$med['dias_para_vencer'] : null;
                                    ?>
                                    <tr class="text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                                        <td data-label="Medicamento" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <div class="flex flex-col">
                                                <a href="medicamentos_view.php?id=<?php echo (int)$med['id']; ?>" class="font-semibold text-primary-600 hover:text-primary-700 hover:underline transition-colors">
                                                    <?php echo htmlspecialchars($med['nome']); ?>
                                                </a>
                                                <?php if (!empty($med['descricao'])): ?>
                                                    <span class="text-xs text-slate-400 truncate max-w-md"><?php echo htmlspecialchars(substr($med['descricao'], 0, 80)) . (strlen($med['descricao']) > 80 ? '...' : ''); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($med['codigo_barras'])): ?>
                                                    <span class="text-xs text-slate-400 mt-1">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="inline-block w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                        <?php echo htmlspecialchars($med['codigo_barras']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td data-label="Fabricante" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <span class="text-sm text-slate-600"><?php echo htmlspecialchars($med['fabricante_nome'] ?? 'Não informado'); ?></span>
                                        </td>
                                        <td data-label="Estoque" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <span class="inline-flex items-center rounded-full px-2.5 sm:px-3 py-1 text-xs font-semibold <?php echo estoqueBadgeClass($estoqueAtual, $estoqueMinimo); ?>">
                                                <?php echo $estoqueAtual; ?> unidades
                                            </span>
                                            <?php if ($estoqueMinimo > 0): ?>
                                                <span class="ml-2 text-xs text-slate-400">Mínimo: <?php echo $estoqueMinimo; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Próxima validade" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <?php if ($proximaValidade): ?>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-sm text-slate-600"><?php echo htmlspecialchars(formatarData($proximaValidade)); ?></span>
                                                    <?php if ($diasParaVencer !== null): ?>
                                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold <?php echo validadeBadgeClass($diasParaVencer); ?>">
                                                            <?php echo $diasParaVencer < 0 ? 'Vencido' : $diasParaVencer . ' dias'; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400">Sem lotes ativos</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Ações" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <div class="flex items-center justify-start sm:justify-end gap-2 flex-wrap">
                                                <a href="medicamentos_form.php?id=<?php echo (int)$med['id']; ?>" class="action-chip" title="Editar medicamento">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                                <a href="../admin_old/medicamentos_lotes.php?med_id=<?php echo (int)$med['id']; ?>" class="action-chip" title="Gerenciar lotes">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                                </a>
                                                <form method="post" class="inline" onsubmit="return confirm('Confirma a exclusão do medicamento <?php echo htmlspecialchars($med['nome'], ENT_QUOTES); ?>? Esta ação não poderá ser desfeita.');">
                                                    <input type="hidden" name="acao" value="delete">
                                                    <input type="hidden" name="delete_id" value="<?php echo (int)$med['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <button type="submit" class="action-chip danger" title="Excluir medicamento" <?php echo temPermissao('excluir_medicamento') ? '' : 'disabled'; ?>>
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="flex items-center justify-between border-t border-white/60 bg-white/70 px-6 py-4">
                            <span class="text-xs text-slate-400">Mostrando <?php echo min($perPage, $totalRecords - $offset); ?> de <?php echo $totalRecords; ?> registros</span>
                            <ul class="flex items-center gap-2 text-sm">
                                <?php
                                    $queryParams = $_GET;
                                    $rangeStart = max(1, $page - 2);
                                    $rangeEnd = min($totalPages, $rangeStart + 4);
                                    if ($rangeEnd - $rangeStart < 4) {
                                        $rangeStart = max(1, $rangeEnd - 4);
                                    }

                                    $queryParams['page'] = max(1, $page - 1);
                                ?>
                                <li>
                                    <a class="pagination-chip <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : 'medicamentos.php?' . http_build_query(array_merge($queryParams, ['page' => 1])); ?>" aria-label="Primeira página">«</a>
                                </li>
                                <li>
                                    <a class="pagination-chip <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : 'medicamentos.php?' . http_build_query(array_merge($queryParams, ['page' => max(1, $page - 1)])); ?>" aria-label="Página anterior">‹</a>
                                </li>
                                <?php for ($i = $rangeStart; $i <= $rangeEnd; $i++): ?>
                                    <li>
                                        <a class="pagination-chip <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo 'medicamentos.php?' . http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li>
                                    <a class="pagination-chip <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $page >= $totalPages ? '#' : 'medicamentos.php?' . http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])); ?>" aria-label="Próxima página">›</a>
                                </li>
                                <li>
                                    <a class="pagination-chip <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $page >= $totalPages ? '#' : 'medicamentos.php?' . http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" aria-label="Última página">»</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25a6 6 0 1 0-9 5.197m9-5.197a6 6 0 0 1-9 5.197m9-5.197V6.75A2.25 2.25 0 0 0 17.25 4.5h-10.5A2.25 2.25 0 0 0 4.5 6.75v10.5A2.25 2.25 0 0 0 6.75 19.5h6.75"/></svg>
                        <p class="text-sm">Nenhum medicamento encontrado com os filtros aplicados.</p>
                        <a href="medicamentos_form.php" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-5 py-2 text-white text-sm font-semibold shadow hover:bg-primary-500 transition">Cadastrar o primeiro</a>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
</body>
</html>
