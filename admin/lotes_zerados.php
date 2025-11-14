<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_medicamento = isset($_GET['medicamento']) ? (int)$_GET['medicamento'] : 0;
$filter_validade = isset($_GET['validade']) ? (int)$_GET['validade'] : 0;
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

// Construir WHERE clauses - APENAS LOTES ZERADOS
$whereClauses = ['l.quantidade_atual = 0'];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(l.numero_lote LIKE ? OR m.nome LIKE ? OR l.fornecedor LIKE ? OR l.nota_fiscal LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
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

$whereClause = 'WHERE ' . implode(' AND ', $whereClauses);

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

$successMessage = getSuccessMessage();
$errorMessage = isset($error) ? $error : getErrorMessage();

$pageTitle = 'Lotes Zerados';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Lotes sem estoque">
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
    <div class="flex min-h-screen">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <main class="flex-1 px-6 py-10 lg:px-12 space-y-10">
            <header class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-3">
                    <span class="text-sm uppercase tracking-[0.3em] text-slate-500">Lotes</span>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="mt-2 text-slate-500 max-w-2xl">Lotes que não possuem mais estoque disponível. Ao adicionar remédios a um lote existente, ele será movido automaticamente para a lista de lotes ativos.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-3 lg:items-end">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/90 px-5 py-2 text-sm text-slate-500 shadow">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                        <?php echo number_format($totalRecords, 0, ',', '.'); ?> lotes zerados
                    </span>
                    <div class="flex flex-wrap gap-3">
                        <a href="lotes.php" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-6 py-3 text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                            Voltar para Lotes
                        </a>
                    </div>
                </div>
            </header>

            <?php if (!empty($errorMessage)): ?>
                <div class="glass-card border border-rose-200/60 bg-rose-50/80 px-6 py-4 text-rose-700">
                    <strong class="block text-sm font-semibold">Erro</strong>
                    <span class="text-sm"><?php echo htmlspecialchars($errorMessage); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($successMessage)): ?>
                <div class="glass-card border border-emerald-200/60 bg-emerald-50/80 px-6 py-4 text-emerald-700">
                    <strong class="block text-sm font-semibold">Sucesso</strong>
                    <span class="text-sm"><?php echo htmlspecialchars($successMessage); ?></span>
                </div>
            <?php endif; ?>

            <!-- Filtros e Busca -->
            <section class="glass-card p-6 space-y-6">
                <div class="grid gap-5 lg:grid-cols-12">
                    <div class="lg:col-span-6">
                        <label for="search" class="text-sm font-medium text-slate-600">Buscar</label>
                        <div class="relative mt-2">
                            <input type="text" id="search" class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 pl-11 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="Número do lote, medicamento, fornecedor ou nota fiscal..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                            <div id="searchLoader" class="hidden absolute right-4 top-1/2 -translate-y-1/2">
                                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-500"></div>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-3">
                        <label for="medicamento" class="text-sm font-medium text-slate-600">Medicamento</label>
                        <select id="medicamento" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                            <option value="0">Todos</option>
                            <?php foreach ($medicamentos as $med): ?>
                                <option value="<?php echo $med['id']; ?>" <?php echo $filter_medicamento == $med['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($med['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="lg:col-span-3">
                        <label for="validade" class="text-sm font-medium text-slate-600">Validade</label>
                        <select id="validade" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                            <option value="0">Todas</option>
                            <option value="1" <?php echo $filter_validade == 1 ? 'selected' : ''; ?>>Vencidas</option>
                            <option value="2" <?php echo $filter_validade == 2 ? 'selected' : ''; ?>>Próximas 30 dias</option>
                            <option value="3" <?php echo $filter_validade == 3 ? 'selected' : ''; ?>>Próximas 60 dias</option>
                            <option value="4" <?php echo $filter_validade == 4 ? 'selected' : ''; ?>>Próximas 90 dias</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Lista de Lotes -->
            <section class="space-y-4" id="lotesContainer">
                <?php if (count($lotes) > 0): ?>
                    <?php foreach ($lotes as $lote): ?>
                        <div class="glass-card p-6 hover:shadow-glow transition">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="text-lg font-semibold text-slate-900">Lote #<?php echo htmlspecialchars($lote['numero_lote']); ?></h3>
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-rose-100 text-rose-600">
                                            Estoque Zerado
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mt-4">
                                        <div>
                                            <span class="text-slate-500">Medicamento:</span>
                                            <span class="ml-2 font-medium text-slate-700"><?php echo htmlspecialchars($lote['medicamento_nome'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div>
                                            <span class="text-slate-500">Código de Barras:</span>
                                            <span class="ml-2 font-medium text-slate-700"><?php echo htmlspecialchars($lote['codigo_barras'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div>
                                            <span class="text-slate-500">Fornecedor:</span>
                                            <span class="ml-2 font-medium text-slate-700"><?php echo htmlspecialchars($lote['fornecedor'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mt-4">
                                        <div>
                                            <span class="text-slate-500">Data de Recebimento:</span>
                                            <span class="ml-2 font-medium text-slate-700"><?php echo $lote['data_recebimento'] ? date('d/m/Y', strtotime($lote['data_recebimento'])) : 'N/A'; ?></span>
                                        </div>
                                        <div>
                                            <span class="text-slate-500">Data de Validade:</span>
                                            <span class="ml-2 font-medium text-slate-700">
                                                <?php echo $lote['data_validade'] ? date('d/m/Y', strtotime($lote['data_validade'])) : 'N/A'; ?>
                                                <?php if ($lote['dias_para_vencer'] !== null): ?>
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ml-2 <?php echo validityBadgeClass($lote['dias_para_vencer']); ?>">
                                                        <?php 
                                                        if ($lote['dias_para_vencer'] < 0) {
                                                            echo abs($lote['dias_para_vencer']) . ' dias vencido';
                                                        } else {
                                                            echo $lote['dias_para_vencer'] . ' dias restantes';
                                                        }
                                                        ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-slate-500">Nota Fiscal:</span>
                                            <span class="ml-2 font-medium text-slate-700"><?php echo htmlspecialchars($lote['nota_fiscal'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>

                                    <?php if (!empty($lote['observacoes'])): ?>
                                        <p class="text-sm text-slate-500 mt-3 italic"><?php echo htmlspecialchars($lote['observacoes']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <a href="medicamentos_lotes.php?med_id=<?php echo (int)$lote['medicamento_id']; ?>" class="action-chip" title="Ver lotes do medicamento">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($totalPages > 1): ?>
                        <nav class="flex items-center justify-center gap-2 mt-8">
                            <?php
                                $queryParams = $_GET;
                                for ($i = 1; $i <= $totalPages; $i++):
                            ?>
                                <a href="lotes_zerados.php?<?php echo http_build_query(array_merge($queryParams, ['page' => $i])); ?>" class="pagination-chip <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="glass-card flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7m16 0v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5m16 0h-2.586a1 1 0 0 0-.707.293l-2.414 2.414a1 1 0 0 1-.707.293h-3.172a1 1 0 0 1-.707-.293l-2.414-2.414A1 1 0 0 0 6.586 13H4"/></svg>
                        <p class="text-sm">Nenhum lote zerado encontrado.</p>
                        <a href="lotes.php" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-5 py-2 text-white text-sm font-semibold shadow hover:bg-primary-500 transition">Ver todos os lotes</a>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
    <script>
        let searchTimeout = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const medicamentoSelect = document.getElementById('medicamento');
            const validadeSelect = document.getElementById('validade');
            
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        buscarLotes();
                    }, 300);
                });
            }
            
            if (medicamentoSelect) {
                medicamentoSelect.addEventListener('change', function() {
                    buscarLotes();
                });
            }
            
            if (validadeSelect) {
                validadeSelect.addEventListener('change', function() {
                    buscarLotes();
                });
            }
        });
        
        async function buscarLotes() {
            const query = document.getElementById('search').value.trim();
            const medicamento = document.getElementById('medicamento').value;
            const validade = document.getElementById('validade').value;
            const container = document.getElementById('lotesContainer');
            const loader = document.getElementById('searchLoader');
            
            if (loader) loader.classList.remove('hidden');
            
            try {
                const params = new URLSearchParams();
                if (query) params.append('search', query);
                if (medicamento) params.append('medicamento', medicamento);
                if (validade) params.append('validade', validade);
                
                const response = await fetch(`api/buscar_lote.php?${params.toString()}&zerados=1`);
                const data = await response.json();
                
                if (loader) loader.classList.add('hidden');
                
                if (data.success && data.lotes && data.lotes.length > 0) {
                    // Recarregar a página para mostrar os resultados filtrados
                    window.location.href = `lotes_zerados.php?${params.toString()}`;
                } else {
                    container.innerHTML = `
                        <div class="glass-card flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7m16 0v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5m16 0h-2.586a1 1 0 0 0-.707.293l-2.414 2.414a1 1 0 0 1-.707.293h-3.172a1 1 0 0 1-.707-.293l-2.414-2.414A1 1 0 0 0 6.586 13H4"/></svg>
                            <p class="text-sm">Nenhum lote zerado encontrado.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Erro ao buscar lotes:', error);
                if (loader) loader.classList.add('hidden');
            }
        }
    </script>
</body>
</html>

