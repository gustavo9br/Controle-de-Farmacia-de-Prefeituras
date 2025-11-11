<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Paginação
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = ITENS_POR_PAGINA;
$offset = ($page - 1) * $perPage;

// Construir WHERE clauses
$whereClauses = ['1=1'];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(p.nome LIKE ? OR p.cpf LIKE ? OR r.numero_receita LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($status) && in_array($status, ['ativa', 'finalizada', 'vencida', 'cancelada'])) {
    $whereClauses[] = "r.status = ?";
    $params[] = $status;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereClauses);

try {
    // Contar total de registros
    $countSql = "SELECT COUNT(*) 
                 FROM receitas r
                 INNER JOIN pacientes p ON p.id = r.paciente_id
                 $whereClause";
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = (int)$stmt->fetchColumn();

    $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;
    
    // Buscar receitas
    $dataSql = "SELECT 
                    r.*,
                    p.nome as paciente_nome,
                    p.cpf as paciente_cpf,
                    (SELECT COUNT(*) FROM receitas_itens WHERE receita_id = r.id) as total_itens,
                    (SELECT COUNT(*) FROM receitas_itens WHERE receita_id = r.id AND quantidade_retirada < quantidade_autorizada) as itens_pendentes
                FROM receitas r
                INNER JOIN pacientes p ON p.id = r.paciente_id
                $whereClause
                ORDER BY r.criado_em DESC
                LIMIT " . (int)$offset . ", " . (int)$perPage;
    
    $stmt = $conn->prepare($dataSql);
    $stmt->execute($params);
    $receitas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Erro ao buscar receitas: " . $e->getMessage();
    $totalRecords = 0;
    $receitas = [];
    $totalPages = 1;
}

function statusBadgeClass(string $status): string
{
    switch ($status) {
        case 'ativa':
            return 'bg-emerald-100 text-emerald-600';
        case 'finalizada':
            return 'bg-blue-100 text-blue-600';
        case 'vencida':
            return 'bg-rose-100 text-rose-600';
        case 'cancelada':
            return 'bg-slate-100 text-slate-600';
        default:
            return 'bg-slate-100 text-slate-600';
    }
}

function statusLabel(string $status): string
{
    switch ($status) {
        case 'ativa':
            return 'Ativa';
        case 'finalizada':
            return 'Finalizada';
        case 'vencida':
            return 'Vencida';
        case 'cancelada':
            return 'Cancelada';
        default:
            return $status;
    }
}

$errorMessage = isset($error) ? $error : getErrorMessage();
$pageTitle = 'Receitas Médicas';
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
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="content-area">
        <div class="space-y-10">
            <header class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-3">
                    <span class="text-sm uppercase tracking-[0.3em] text-slate-500">Receitas</span>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="mt-2 text-slate-500 max-w-2xl">Controle de receitas médicas dos pacientes.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-3 lg:items-end">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/90 px-5 py-2 text-sm text-slate-500 shadow">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/></svg>
                        <?php echo number_format($totalRecords, 0, ',', '.'); ?> receitas cadastradas
                    </span>
                    <a href="receitas_form.php" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-6 py-3 text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6"/></svg>
                        Nova Receita
                    </a>
                </div>
            </header>

            <?php if (!empty($errorMessage)): ?>
                <div class="glass-card border border-rose-200/60 bg-rose-50/80 px-6 py-4 text-rose-700">
                    <strong class="block text-sm font-semibold">Atenção</strong>
                    <span class="text-sm"><?php echo htmlspecialchars($errorMessage); ?></span>
                </div>
            <?php endif; ?>

            <section class="glass-card p-6 space-y-6">
                <div class="grid gap-5 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <label for="search" class="text-sm font-medium text-slate-600">Buscar por paciente ou número da receita</label>
                        <div class="relative mt-2">
                            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 pl-11 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="Digite nome, CPF ou número da receita..." autocomplete="off">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                        </div>
                    </div>
                    <div class="lg:col-span-4">
                        <label for="status" class="text-sm font-medium text-slate-600">Status</label>
                        <select name="status" id="status" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Todos</option>
                            <option value="ativa" <?php echo $status === 'ativa' ? 'selected' : ''; ?>>Ativa</option>
                            <option value="finalizada" <?php echo $status === 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                            <option value="vencida" <?php echo $status === 'vencida' ? 'selected' : ''; ?>>Vencida</option>
                            <option value="cancelada" <?php echo $status === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <?php if (count($receitas) > 0): ?>
                    <?php foreach ($receitas as $receita): ?>
                        <div class="glass-card p-6 hover:shadow-glow transition cursor-pointer" onclick="window.location.href='receitas_dispensar.php?id=<?php echo $receita['id']; ?>'">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="text-lg font-semibold text-slate-900">Receita #<?php echo !empty($receita['numero_receita']) ? htmlspecialchars($receita['numero_receita']) : $receita['id']; ?></h3>
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?php echo statusBadgeClass($receita['status']); ?>">
                                            <?php echo statusLabel($receita['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mt-4">
                                        <div>
                                            <span class="text-slate-500">Paciente:</span>
                                            <span class="ml-2 font-medium text-slate-700"><?php echo htmlspecialchars($receita['paciente_nome']); ?></span>
                                        </div>
                                        <div>
                                            <span class="text-slate-500">Emissão:</span>
                                            <span class="ml-2 font-medium text-slate-700"><?php echo formatarData($receita['data_emissao']); ?></span>
                                        </div>
                                        <div>
                                            <span class="text-slate-500">Validade:</span>
                                            <span class="ml-2 font-medium text-slate-700"><?php echo formatarData($receita['data_validade']); ?></span>
                                        </div>
                                    </div>

                                    <div class="flex gap-4 mt-4 text-sm">
                                        <span class="inline-flex items-center gap-1 text-slate-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                            <?php echo $receita['total_itens']; ?> medicamento(s)
                                        </span>
                                        <?php if ($receita['itens_pendentes'] > 0): ?>
                                            <span class="inline-flex items-center gap-1 text-amber-600 font-medium">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <?php echo $receita['itens_pendentes']; ?> item(ns) pendente(s)
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($receita['observacoes'])): ?>
                                        <p class="text-sm text-slate-500 mt-3 italic"><?php echo htmlspecialchars($receita['observacoes']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="flex flex-col gap-2 ml-4">
                                    <a href="paciente_historico.php?id=<?php echo $receita['paciente_id']; ?>" class="action-chip" title="Ver histórico do paciente" onclick="event.stopPropagation();">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
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
                                <a href="receitas.php?<?php echo http_build_query(array_merge($queryParams, ['page' => $i])); ?>" class="pagination-chip <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="glass-card flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/></svg>
                        <p class="text-sm">Nenhuma receita encontrada.</p>
                        <a href="receitas_form.php" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-5 py-2 text-white text-sm font-semibold shadow hover:bg-primary-500 transition">Cadastrar primeira receita</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <script src="js/sidebar.js" defer></script>
    <script>
        let searchTimeout = null;
        
        // Busca instantânea ao digitar
        document.getElementById('search').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                aplicarFiltros();
            }, 500);
        });
        
        // Busca ao mudar status
        document.getElementById('status').addEventListener('change', function() {
            aplicarFiltros();
        });
        
        function aplicarFiltros() {
            const search = document.getElementById('search').value;
            const status = document.getElementById('status').value;
            
            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (status) params.append('status', status);
            
            const url = 'receitas.php' + (params.toString() ? '?' + params.toString() : '');
            window.location.href = url;
        }
    </script>
</body>
</html>
