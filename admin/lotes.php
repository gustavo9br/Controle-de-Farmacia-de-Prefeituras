<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Processar exclusão de lote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lote']) && isset($_POST['lote_id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verificarCSRFToken($csrfToken)) {
        $_SESSION['error'] = 'Token de segurança inválido. Atualize a página e tente novamente.';
    } else {
        $lote_id = (int)$_POST['lote_id'];
        
        try {
            $conn->beginTransaction();
            
            // Verificar se o lote existe
            $stmt = $conn->prepare("SELECT * FROM lotes WHERE id = ?");
            $stmt->bindValue(1, $lote_id, PDO::PARAM_INT);
            $stmt->execute();
            $lote = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lote) {
                // Atualizar o estoque do medicamento
                $stmt = $conn->prepare("UPDATE medicamentos SET estoque_atual = estoque_atual - ?, atualizado_em = NOW() WHERE id = ?");
                $stmt->bindValue(1, $lote['quantidade_atual'], PDO::PARAM_INT);
                $stmt->bindValue(2, $lote['medicamento_id'], PDO::PARAM_INT);
                $stmt->execute();
                
                // Excluir o lote
                $stmt = $conn->prepare("DELETE FROM lotes WHERE id = ?");
                $stmt->bindValue(1, $lote_id, PDO::PARAM_INT);
                $stmt->execute();
                
                $conn->commit();
                $_SESSION['success'] = "Lote excluído com sucesso!";
            } else {
                $_SESSION['error'] = "Lote não encontrado.";
                $conn->rollBack();
            }
        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error'] = "Erro ao excluir lote: " . $e->getMessage();
        }
    }
    
    header('Location: lotes.php');
    exit;
}

// Filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_medicamento = isset($_GET['medicamento']) ? (int)$_GET['medicamento'] : 0;
$filter_validade = isset($_GET['validade']) ? (int)$_GET['validade'] : 0;
$filter_status = isset($_GET['status']) ? (int)$_GET['status'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'data_validade';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';

// Paginação
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = ITENS_POR_PAGINA;
$offset = ($page - 1) * $perPage;

$allowedSorts = [
    'numero_lote' => 'l.numero_lote',
    'medicamento' => 'm.nome',
    'data_recebimento' => 'l.data_recebimento',
    'data_validade' => 'l.data_validade',
    'quantidade_atual' => 'l.quantidade_atual'
];

$sortColumn = $allowedSorts[$sort] ?? $allowedSorts['data_validade'];
$order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

// Construir WHERE clauses
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(l.numero_lote LIKE ? OR m.nome LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($filter_medicamento > 0) {
    $whereClauses[] = "l.medicamento_id = ?";
    $params[] = $filter_medicamento;
}

if ($filter_validade == 1) {
    $whereClauses[] = "l.data_validade < CURDATE()";
} elseif ($filter_validade == 2) {
    $whereClauses[] = "l.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
} elseif ($filter_validade == 3) {
    $whereClauses[] = "l.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)";
} elseif ($filter_validade == 4) {
    $whereClauses[] = "l.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
}

if ($filter_status == 1) {
    $whereClauses[] = "l.quantidade_atual > 0";
} elseif ($filter_status == 2) {
    $whereClauses[] = "l.quantidade_atual = 0";
}

// Por padrão, mostrar apenas lotes com estoque (quantidade_atual > 0)
if ($filter_status == 0) {
    $whereClauses[] = "l.quantidade_atual > 0";
}

$whereClause = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

try {
    // Contar total de registros
    $countSql = "SELECT COUNT(*) FROM lotes l LEFT JOIN medicamentos m ON l.medicamento_id = m.id $whereClause";
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

    // Buscar lotes
    $dataSql = "SELECT l.*, 
                m.nome as medicamento_nome, 
                m.estoque_atual as med_estoque_atual,
                m.estoque_minimo as med_estoque_minimo,
                cb.codigo as codigo_barras,
                DATEDIFF(l.data_validade, CURDATE()) AS dias_para_vencer
                FROM lotes l
                LEFT JOIN medicamentos m ON l.medicamento_id = m.id
                LEFT JOIN codigos_barras cb ON l.codigo_barras_id = cb.id
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
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar todos os medicamentos para o filtro
    $stmt = $conn->query("SELECT id, nome FROM medicamentos ORDER BY nome");
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Erro ao buscar lotes: " . $e->getMessage();
    $totalRecords = 0;
    $lotes = [];
    $medicamentos = [];
    $totalPages = 1;
}

function validityBadgeClass(?int $dias): string
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

function stockBadgeClass(int $qtd): string
{
    if ($qtd <= 0) {
        return 'bg-rose-100 text-rose-600';
    }

    if ($qtd <= 10) {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-emerald-100 text-emerald-600';
}

$successMessage = getSuccessMessage();
$errorMessage = isset($error) ? $error : getErrorMessage();
$csrfToken = gerarCSRFToken();

$pageTitle = 'Gerenciamento de lotes';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Controle de medicamentos, lotes, pacientes e receitas">
    <meta name="keywords" content="farmácia, medicamentos, gestão, controle de estoque, lotes">
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
            <header class="flex flex-col gap-4 lg:gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2 sm:space-y-3">
                    <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Lotes</span>
                    <div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="mt-2 text-sm sm:text-base text-slate-500 max-w-2xl">Controle de lotes cadastrados, validade e estoque disponível por lote de medicamento.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-3 lg:items-end">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/90 px-4 sm:px-5 py-2 text-xs sm:text-sm text-slate-500 shadow">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                        <span class="whitespace-nowrap"><?php echo number_format($totalRecords, 0, ',', '.'); ?> lotes</span>
                    </span>
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 w-full sm:w-auto">
                        <a href="lotes_adicionar.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-4 sm:px-6 py-2.5 sm:py-3 text-white font-semibold shadow-glow hover:bg-primary-500 transition text-sm sm:text-base">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6"/></svg>
                            <span>Adicionar Lote</span>
                        </a>
                        <a href="lotes_zerados.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 sm:px-6 py-2.5 sm:py-3 text-gray-600 font-semibold shadow hover:shadow-lg transition border border-gray-200 text-sm sm:text-base">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7m16 0v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5m16 0h-2.586a1 1 0 0 0-.707.293l-2.414 2.414a1 1 0 0 1-.707.293h-3.172a1 1 0 0 1-.707-.293l-2.414-2.414A1 1 0 0 0 6.586 13H4"/></svg>
                            <span>Lotes Zerados</span>
                        </a>
                        <a href="relatorios.php?tipo=vencimento" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 sm:px-6 py-2.5 sm:py-3 text-amber-600 font-semibold shadow hover:shadow-lg transition text-sm sm:text-base">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9z"/></svg>
                            <span>Controle de validades</span>
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

            <section class="glass-card p-4 sm:p-6 space-y-4 sm:space-y-6">
                <div class="grid gap-4 sm:gap-5 grid-cols-1 lg:grid-cols-12">
                    <div class="lg:col-span-6">
                        <label for="search" class="text-xs sm:text-sm font-medium text-slate-600">Buscar por lote, código de barras ou medicamento</label>
                        <div class="relative mt-2">
                            <input type="text" id="search" class="w-full rounded-2xl border border-slate-100 bg-white px-4 sm:px-5 py-2.5 sm:py-3 pl-10 sm:pl-11 text-base text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="Digite o número do lote, código de barras ou nome do medicamento..." autocomplete="off">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 sm:left-4 top-1/2 h-4 w-4 sm:h-5 sm:w-5 -translate-y-1/2 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                            <div id="searchLoader" class="hidden absolute right-3 sm:right-4 top-1/2 -translate-y-1/2">
                                <div class="animate-spin rounded-full h-4 w-4 sm:h-5 sm:w-5 border-b-2 border-primary-500"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="glass-card p-0 overflow-hidden">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-0 px-4 sm:px-6 py-3 sm:py-4 border-b border-white/60 bg-white/70">
                    <h2 class="text-base sm:text-lg font-semibold text-slate-900">Lista de lotes</h2>
                    <span id="totalInfo" class="rounded-full bg-primary-50 px-3 sm:px-4 py-1 text-xs sm:text-sm font-medium text-primary-600 whitespace-nowrap"><?php echo number_format($totalRecords, 0, ',', '.'); ?> lotes</span>
                </div>
                <div id="lotesContainer">
                <?php if (count($lotes) > 0): ?>
                    <!-- Desktop table -->
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-white/60">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-6 py-3">Lote</th>
                                    <th class="px-6 py-3">Medicamento</th>
                                    <th class="px-6 py-3">Recebimento</th>
                                    <th class="px-6 py-3">Validade</th>
                                    <th class="px-6 py-3">Qtd. Atual</th>
                                    <th class="px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white/80">
                                <?php foreach ($lotes as $lote): ?>
                                    <?php
                                        $dias_para_vencer = isset($lote['dias_para_vencer']) ? (int)$lote['dias_para_vencer'] : null;
                                        $quantidade_atual = (int)($lote['quantidade_atual'] ?? 0);
                                    ?>
                                    <tr class="text-sm text-slate-600 cursor-pointer hover:bg-slate-50 transition" onclick="window.location.href='medicamentos_lotes.php?med_id=<?php echo (int)$lote['medicamento_id']; ?>'">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($lote['numero_lote']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($lote['medicamento_nome']); ?></span>
                                                <?php if (!empty($lote['codigo_barras'])): ?>
                                                    <div class="flex gap-2 mt-1">
                                                        <span class="text-xs text-slate-400 font-mono">Código: <?php echo htmlspecialchars($lote['codigo_barras']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-slate-600"><?php echo htmlspecialchars(formatarData($lote['data_recebimento'])); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-1">
                                                <span class="text-sm text-slate-600"><?php echo htmlspecialchars(formatarData($lote['data_validade'])); ?></span>
                                                <?php if ($dias_para_vencer !== null): ?>
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold w-fit <?php echo validityBadgeClass($dias_para_vencer); ?>">
                                                        <?php 
                                                            if ($dias_para_vencer < 0) {
                                                                echo 'Vencido há ' . abs($dias_para_vencer) . ' dias';
                                                            } else {
                                                                echo 'Vence em ' . $dias_para_vencer . ' dias';
                                                            }
                                                        ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?php echo stockBadgeClass($quantidade_atual); ?>">
                                                <?php echo $quantidade_atual; ?> unidades
                                            </span>
                                        </td>
                                        <td class="px-6 py-4" onclick="event.stopPropagation();">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="medicamentos_lotes.php?med_id=<?php echo (int)$lote['medicamento_id']; ?>" class="action-chip" title="Ver lotes do medicamento" onclick="event.stopPropagation();">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    </svg>
                                                </a>
                                                <form method="post" class="inline" onsubmit="return confirm('Confirma a exclusão do lote <?php echo htmlspecialchars($lote['numero_lote'], ENT_QUOTES); ?>? Esta ação não poderá ser desfeita.');" onclick="event.stopPropagation();">
                                                    <input type="hidden" name="delete_lote" value="1">
                                                    <input type="hidden" name="lote_id" value="<?php echo (int)$lote['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <button type="submit" class="action-chip danger" title="Excluir lote">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile cards -->
                    <div class="lg:hidden space-y-4">
                        <?php foreach ($lotes as $lote): ?>
                            <?php
                                $dias_para_vencer = isset($lote['dias_para_vencer']) ? (int)$lote['dias_para_vencer'] : null;
                                $quantidade_atual = (int)($lote['quantidade_atual'] ?? 0);
                            ?>
                            <div class="bg-white/80 rounded-lg border border-slate-200 p-4 sm:p-5 space-y-3 shadow-sm hover:shadow-md transition-all cursor-pointer" onclick="window.location.href='medicamentos_lotes.php?med_id=<?php echo (int)$lote['medicamento_id']; ?>'">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Lote</p>
                                        <p class="text-lg font-bold text-slate-900"><?php echo htmlspecialchars($lote['numero_lote']); ?></p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-3 py-1.5 text-sm font-semibold <?php echo stockBadgeClass($quantidade_atual); ?> whitespace-nowrap ml-3">
                                        <?php echo $quantidade_atual; ?> un
                                    </span>
                                </div>
                                
                                <div class="pt-2 border-t border-slate-100">
                                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Medicamento</p>
                                    <p class="font-semibold text-slate-900 text-sm"><?php echo htmlspecialchars($lote['medicamento_nome']); ?></p>
                                    <?php if (!empty($lote['codigo_barras'])): ?>
                                        <p class="text-xs text-slate-500 mt-1 font-mono">Código: <?php echo htmlspecialchars($lote['codigo_barras']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                                    <div>
                                        <p class="text-xs text-slate-500 mb-1">Recebimento</p>
                                        <p class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars(formatarData($lote['data_recebimento'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500 mb-1">Validade</p>
                                        <p class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars(formatarData($lote['data_validade'])); ?></p>
                                    </div>
                                </div>

                                <?php if ($dias_para_vencer !== null): ?>
                                    <div class="pt-2">
                                        <span class="inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold <?php echo validityBadgeClass($dias_para_vencer); ?>">
                                            <?php 
                                                if ($dias_para_vencer < 0) {
                                                    echo 'Vencido há ' . abs($dias_para_vencer) . ' dias';
                                                } else {
                                                    echo 'Vence em ' . $dias_para_vencer . ' dias';
                                                }
                                            ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="flex gap-2 pt-3 border-t border-slate-200" onclick="event.stopPropagation();">
                                    <a href="medicamentos_lotes.php?med_id=<?php echo (int)$lote['medicamento_id']; ?>" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium transition-all shadow-sm hover:shadow-md" title="Ver lotes" onclick="event.stopPropagation();">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span>Ver</span>
                                    </a>
                                    <form method="post" class="flex-1" onsubmit="return confirm('Confirma a exclusão do lote <?php echo htmlspecialchars($lote['numero_lote'], ENT_QUOTES); ?>?');" onclick="event.stopPropagation();">
                                        <input type="hidden" name="delete_lote" value="1">
                                        <input type="hidden" name="lote_id" value="<?php echo (int)$lote['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-red-500 hover:bg-red-600 text-white text-sm font-medium transition-all shadow-sm hover:shadow-md" title="Excluir lote">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <span>Excluir</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-0 border-t border-white/60 bg-white/70 px-4 sm:px-6 py-3 sm:py-4">
                            <span class="text-xs text-slate-400 text-center sm:text-left">Mostrando <?php echo min($perPage, $totalRecords - $offset); ?> de <?php echo $totalRecords; ?> registros</span>
                            <ul class="flex items-center gap-1 sm:gap-2 text-xs sm:text-sm flex-wrap justify-center">
                                <?php
                                    $queryParams = $_GET;
                                    $rangeStart = max(1, $page - 2);
                                    $rangeEnd = min($totalPages, $rangeStart + 4);
                                    if ($rangeEnd - $rangeStart < 4) {
                                        $rangeStart = max(1, $rangeEnd - 4);
                                    }
                                ?>
                                <li>
                                    <a class="pagination-chip <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : 'lotes.php?' . http_build_query(array_merge($queryParams, ['page' => 1])); ?>" aria-label="Primeira página">«</a>
                                </li>
                                <li>
                                    <a class="pagination-chip <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : 'lotes.php?' . http_build_query(array_merge($queryParams, ['page' => max(1, $page - 1)])); ?>" aria-label="Página anterior">‹</a>
                                </li>
                                <?php for ($i = $rangeStart; $i <= $rangeEnd; $i++): ?>
                                    <li>
                                        <a class="pagination-chip <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo 'lotes.php?' . http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li>
                                    <a class="pagination-chip <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $page >= $totalPages ? '#' : 'lotes.php?' . http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])); ?>" aria-label="Próxima página">›</a>
                                </li>
                                <li>
                                    <a class="pagination-chip <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $page >= $totalPages ? '#' : 'lotes.php?' . http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" aria-label="Última página">»</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m3 7.5 9-4.5 9 4.5m-18 0 9 4.5m9-4.5-9 4.5m0 9v-9"/></svg>
                        <p class="text-sm">Nenhum lote encontrado com os filtros aplicados.</p>
                        <a href="medicamentos.php" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-5 py-2 text-white text-sm font-semibold shadow hover:bg-primary-500 transition">Ver medicamentos</a>
                    </div>
                <?php endif; ?>
                </div>
            </section>
            </div>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
    <script>
        let searchTimeout = null;
        let lotesData = <?php echo json_encode($lotes); ?>;
        const csrfToken = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>';
        
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        buscarLotes();
                    }, 300);
                });
            }
        });
        
        async function buscarLotes() {
            const query = document.getElementById('search').value.trim();
            const loader = document.getElementById('searchLoader');
            const container = document.getElementById('lotesContainer');
            const totalInfo = document.getElementById('totalInfo');
            
            // Se o campo estiver vazio, recarregar a página para mostrar todos os lotes
            if (!query) {
                window.location.reload();
                return;
            }
            
            if (loader) loader.classList.remove('hidden');
            
            try {
                const params = new URLSearchParams();
                params.append('q', query);
                
                const response = await fetch(`api/buscar_lote.php?${params.toString()}`);
                const data = await response.json();
                
                if (loader) loader.classList.add('hidden');
                
                if (data.success && data.lotes && data.lotes.length > 0) {
                    lotesData = data.lotes;
                    renderizarLotes(data.lotes);
                    if (totalInfo) totalInfo.textContent = `${data.lotes.length} lote(s) encontrado(s)`;
                } else {
                    container.innerHTML = `
                        <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m3 7.5 9-4.5 9 4.5m-18 0 9 4.5m9-4.5-9 4.5m0 9v-9"/></svg>
                            <p class="text-sm">Nenhum lote encontrado.</p>
                        </div>
                    `;
                    if (totalInfo) totalInfo.textContent = '0 lotes';
                }
            } catch (error) {
                console.error('Erro ao buscar lotes:', error);
                if (loader) loader.classList.add('hidden');
            }
        }
        
        function renderizarLotes(lotes) {
            const container = document.getElementById('lotesContainer');
            
            const validityBadgeClass = (dias) => {
                if (dias === null) return 'bg-slate-100 text-slate-500';
                if (dias < 0) return 'bg-rose-100 text-rose-600';
                if (dias <= 30) return 'bg-rose-100 text-rose-600';
                if (dias <= 60) return 'bg-amber-100 text-amber-700';
                if (dias <= 90) return 'bg-sky-100 text-sky-700';
                return 'bg-emerald-100 text-emerald-600';
            };
            
            const stockBadgeClass = (quantidade) => {
                if (quantidade <= 0) return 'bg-rose-100 text-rose-600';
                return 'bg-emerald-100 text-emerald-600';
            };
            
            const formatarData = (data) => {
                if (!data) return '-';
                const d = new Date(data + 'T00:00:00');
                return d.toLocaleDateString('pt-BR');
            };
            
            container.innerHTML = `
                <div class="hidden lg:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left">
                        <thead class="bg-white/60">
                            <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Lote</th>
                                <th class="px-6 py-3">Medicamento</th>
                                <th class="px-6 py-3">Recebimento</th>
                                <th class="px-6 py-3">Validade</th>
                                <th class="px-6 py-3">Qtd. Atual</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white/80">
                            ${lotes.map(lote => {
                                const dias_para_vencer = lote.dias_para_vencer !== null ? parseInt(lote.dias_para_vencer) : null;
                                const quantidade_atual = parseInt(lote.quantidade_atual || 0);
                                
                                const numeroLoteEscaped = lote.numero_lote.replace(/'/g, "\\'");
                                
                                return `
                                    <tr class="text-sm text-slate-600 cursor-pointer hover:bg-slate-50 transition" onclick="window.location.href='medicamentos_lotes.php?med_id=${lote.medicamento_id}'">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-900">${lote.numero_lote}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-900">${lote.medicamento_nome}</span>
                                                ${lote.codigo_barras ? `<div class="flex gap-2 mt-1"><span class="text-xs text-slate-400 font-mono">Código: ${lote.codigo_barras}</span></div>` : ''}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-slate-600">${formatarData(lote.data_recebimento)}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-1">
                                                <span class="text-sm text-slate-600">${formatarData(lote.data_validade)}</span>
                                                ${dias_para_vencer !== null ? `
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold w-fit ${validityBadgeClass(dias_para_vencer)}">
                                                        ${dias_para_vencer < 0 ? 'Vencido há ' + Math.abs(dias_para_vencer) + ' dias' : 'Vence em ' + dias_para_vencer + ' dias'}
                                                    </span>
                                                ` : ''}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${stockBadgeClass(quantidade_atual)}">
                                                ${quantidade_atual} unidades
                                            </span>
                                        </td>
                                        <td class="px-6 py-4" onclick="event.stopPropagation();">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="medicamentos_lotes.php?med_id=${lote.medicamento_id}" class="action-chip" title="Ver lotes do medicamento" onclick="event.stopPropagation();">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    </svg>
                                                </a>
                                                <form method="post" class="inline" onsubmit="return confirm('Confirma a exclusão do lote ${numeroLoteEscaped}? Esta ação não poderá ser desfeita.');" onclick="event.stopPropagation();">
                                                    <input type="hidden" name="delete_lote" value="1">
                                                    <input type="hidden" name="lote_id" value="${lote.id}">
                                                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                                                    <button type="submit" class="action-chip danger" title="Excluir lote">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
                <div class="lg:hidden space-y-4">
                    ${lotes.map(lote => {
                        const dias_para_vencer = lote.dias_para_vencer !== null ? parseInt(lote.dias_para_vencer) : null;
                        const quantidade_atual = parseInt(lote.quantidade_atual || 0);
                        const numeroLoteEscaped = lote.numero_lote.replace(/'/g, "\\'");
                        
                        return `
                            <div class="bg-white/80 rounded-lg border border-slate-200 p-4 sm:p-5 space-y-3 shadow-sm hover:shadow-md transition-all cursor-pointer" onclick="window.location.href='medicamentos_lotes.php?med_id=${lote.medicamento_id}'">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Lote</p>
                                        <p class="text-lg font-bold text-slate-900">${lote.numero_lote}</p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-3 py-1.5 text-sm font-semibold ${stockBadgeClass(quantidade_atual)} whitespace-nowrap ml-3">
                                        ${quantidade_atual} un
                                    </span>
                                </div>
                                
                                <div class="pt-2 border-t border-slate-100">
                                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Medicamento</p>
                                    <p class="font-semibold text-slate-900 text-sm">${lote.medicamento_nome}</p>
                                    ${lote.codigo_barras ? `<p class="text-xs text-slate-500 mt-1 font-mono">Código: ${lote.codigo_barras}</p>` : ''}
                                </div>

                                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                                    <div>
                                        <p class="text-xs text-slate-500 mb-1">Recebimento</p>
                                        <p class="text-sm font-medium text-slate-700">${formatarData(lote.data_recebimento)}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500 mb-1">Validade</p>
                                        <p class="text-sm font-medium text-slate-700">${formatarData(lote.data_validade)}</p>
                                    </div>
                                </div>

                                ${dias_para_vencer !== null ? `
                                    <div class="pt-2">
                                        <span class="inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold ${validityBadgeClass(dias_para_vencer)}">
                                            ${dias_para_vencer < 0 ? 'Vencido há ' + Math.abs(dias_para_vencer) + ' dias' : 'Vence em ' + dias_para_vencer + ' dias'}
                                        </span>
                                    </div>
                                ` : ''}

                                <div class="flex gap-2 pt-3 border-t border-slate-200" onclick="event.stopPropagation();">
                                    <a href="medicamentos_lotes.php?med_id=${lote.medicamento_id}" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium transition-all shadow-sm hover:shadow-md" title="Ver lotes" onclick="event.stopPropagation();">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span>Ver</span>
                                    </a>
                                    <form method="post" class="flex-1" onsubmit="return confirm('Confirma a exclusão do lote ${numeroLoteEscaped}?');" onclick="event.stopPropagation();">
                                        <input type="hidden" name="delete_lote" value="1">
                                        <input type="hidden" name="lote_id" value="${lote.id}">
                                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-red-500 hover:bg-red-600 text-white text-sm font-medium transition-all shadow-sm hover:shadow-md" title="Excluir lote">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <span>Excluir</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }
    </script>
</body>
</html>
