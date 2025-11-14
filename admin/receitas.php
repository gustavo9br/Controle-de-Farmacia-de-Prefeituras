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
                    (SELECT COUNT(*) FROM receitas_itens ri 
                 WHERE ri.receita_id = r.id 
                 AND COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) < ri.quantidade_autorizada) as itens_pendentes
                FROM receitas r
                INNER JOIN pacientes p ON p.id = r.paciente_id
                $whereClause
                ORDER BY r.criado_em DESC
                LIMIT " . (int)$offset . ", " . (int)$perPage;
    
    $stmt = $conn->prepare($dataSql);
    $stmt->execute($params);
    $receitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar datas planejadas para cada receita
    foreach ($receitas as &$receita) {
        $sqlDatas = "SELECT 
                        rrp.data_planejada,
                        rrp.numero_retirada,
                        ri.id as receita_item_id,
                        ri.medicamento_id,
                        ri.quantidade_autorizada,
                        m.nome as medicamento_nome,
                        COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) as retiradas_feitas,
                        CASE WHEN EXISTS (
                            SELECT 1 FROM receitas_retiradas rr 
                            WHERE rr.receita_item_id = rrp.receita_item_id 
                            AND (rr.data_planejada = rrp.data_planejada OR (rr.data_planejada IS NULL AND DATE(rr.criado_em) = rrp.data_planejada))
                        ) THEN 1 ELSE 0 END as ja_dispensada
                     FROM receitas_retiradas_planejadas rrp
                     INNER JOIN receitas_itens ri ON ri.id = rrp.receita_item_id
                     INNER JOIN medicamentos m ON m.id = ri.medicamento_id
                     WHERE ri.receita_id = ?
                     ORDER BY rrp.data_planejada ASC";
        
        $stmtDatas = $conn->prepare($sqlDatas);
        $stmtDatas->execute([$receita['id']]);
        $receita['datas_planejadas'] = $stmtDatas->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar itens pendentes com informações para dispensação
        $sqlItens = "SELECT 
                        ri.id,
                        ri.medicamento_id,
                        ri.quantidade_autorizada,
                        m.nome as medicamento_nome
                     FROM receitas_itens ri
                     INNER JOIN medicamentos m ON m.id = ri.medicamento_id
                     WHERE ri.receita_id = ?
                       AND COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) < ri.quantidade_autorizada
                     LIMIT 1";
        
        $stmtItens = $conn->prepare($sqlItens);
        $stmtItens->execute([$receita['id']]);
        $receita['primeiro_item_pendente'] = $stmtItens->fetch(PDO::FETCH_ASSOC);
    }
    unset($receita);

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
    
    <?php 
    $ogTitle = htmlspecialchars($pageTitle) . ' - Gov Farma';
    $ogDescription = 'Gov Farma - Gerenciamento de receitas médicas. Controle de prescrições, dispensação e histórico de pacientes.';
    include '../includes/og_meta.php'; 
    ?>
    
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
                            <input type="text" id="search" class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 pl-11 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="Digite nome, CPF ou número da receita..." autocomplete="off">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                            <div id="searchLoader" class="hidden absolute right-4 top-1/2 -translate-y-1/2">
                                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-500"></div>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-4">
                        <label for="status" class="text-sm font-medium text-slate-600">Status</label>
                        <select id="status" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Todos</option>
                            <option value="ativa" <?php echo $status === 'ativa' ? 'selected' : ''; ?>>Ativa</option>
                            <option value="finalizada" <?php echo $status === 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                            <option value="vencida" <?php echo $status === 'vencida' ? 'selected' : ''; ?>>Vencida</option>
                            <option value="cancelada" <?php echo $status === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="space-y-4" id="receitasContainer">
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

                                    <?php if (!empty($receita['datas_planejadas']) && count($receita['datas_planejadas']) > 0): ?>
                                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <span class="font-medium">Retiradas planejadas:</span>
                                            <?php 
                                            foreach ($receita['datas_planejadas'] as $idx => $data): 
                                                // Usar o campo ja_dispensada que vem da query
                                                $jaDispensada = !empty($data['ja_dispensada']);
                                                $podeDispensar = $receita['status'] === 'ativa' && !empty($data['receita_item_id']) && !$jaDispensada;
                                            ?>
                                                <div class="flex items-center gap-1">
                                                    <span class="px-2 py-0.5 <?php echo $jaDispensada ? 'bg-gray-100 text-gray-500' : 'bg-blue-50 text-blue-700'; ?> rounded"><?php echo date('d/m', strtotime($data['data_planejada'])); ?></span>
                                                    <?php if ($podeDispensar): ?>
                                                        <button 
                                                            onclick="event.stopPropagation(); abrirModalDispensacaoReceita(<?php echo $receita['id']; ?>, <?php echo $data['receita_item_id']; ?>, <?php echo $data['medicamento_id']; ?>, '<?php echo addslashes($data['medicamento_nome']); ?>', <?php echo (int)$data['quantidade_autorizada']; ?>, <?php echo $receita['paciente_id']; ?>, '<?php echo $data['data_planejada']; ?>');" 
                                                            class="px-2 py-0.5 bg-green-500 hover:bg-green-600 text-white rounded text-xs font-medium transition-all"
                                                            title="Dispensar para esta data">
                                                            ✓
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

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

    <!-- Modal de Dispensação Reutilizável -->
    <div id="modalDispensacaoReceita" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-2xl w-full mx-4 shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Dispensar Medicamento</h3>
            
            <form id="formDispensacaoReceita" class="space-y-4">
                <input type="hidden" id="modal_receita_id" name="receita_id">
                <input type="hidden" id="modal_receita_item_id" name="receita_item_id">
                <input type="hidden" id="modal_medicamento_id" name="medicamento_id">
                <input type="hidden" id="modal_paciente_id" name="paciente_id">
                <input type="hidden" id="modal_data_planejada" name="data_planejada">
                
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-600">Medicamento</p>
                    <p class="text-lg font-bold text-gray-900" id="modal_medicamento_nome_receita"></p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Buscar lote por código de barras (opcional)
                    </label>
                    <input type="text" id="codigo_barras_busca" placeholder="Digite ou escaneie o código de barras..." class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500" autocomplete="off">
                    <p class="text-xs text-gray-500 mt-1">Ou selecione manualmente abaixo</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <span class="text-red-500">*</span> Selecionar Lote
                    </label>
                    <select id="lote_id_receita" name="lote_id" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Carregando lotes...</option>
                    </select>
                </div>

                <input type="hidden" id="quantidade_receita" name="quantidade">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Observações</label>
                    <textarea id="observacoes_receita" name="observacoes" rows="3" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="fecharModalDispensacaoReceita()" class="flex-1 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium transition-all">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white rounded-lg font-bold shadow-lg hover:shadow-xl transition-all">
                        ✓ Confirmar Dispensação
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Alerta Customizado -->
    <div id="modalAlerta" class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 transform transition-all">
            <div class="text-center">
                <div id="alertaIcon" class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-white text-3xl">ℹ</span>
                </div>
                <h3 id="alertaTitulo" class="text-xl font-bold text-gray-900 mb-2">Informação</h3>
                <p id="alertaMensagem" class="text-gray-600 mb-6"></p>
                <button onclick="fecharAlerta()" class="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white rounded-lg font-semibold transition-all">
                    OK
                </button>
            </div>
        </div>
    </div>

    <script src="js/sidebar.js" defer></script>
    <script>
        let searchTimeout = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const statusSelect = document.getElementById('status');
            
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        buscarReceitas();
                    }, 300);
                });
            }
            
            if (statusSelect) {
                statusSelect.addEventListener('change', function() {
                    buscarReceitas();
                });
            }

            // Fechar modal de alerta ao clicar fora
            const modalAlerta = document.getElementById('modalAlerta');
            if (modalAlerta) {
                modalAlerta.addEventListener('click', (e) => {
                    if (e.target.id === 'modalAlerta') {
                        fecharAlerta();
                    }
                });
            }
        });
        
        async function buscarReceitas() {
            const query = document.getElementById('search').value.trim();
            const status = document.getElementById('status').value;
            const container = document.getElementById('receitasContainer');
            const loader = document.getElementById('searchLoader');
            
            if (loader) loader.classList.remove('hidden');
            
            try {
                const params = new URLSearchParams();
                if (query) params.append('q', query);
                if (status) params.append('status', status);
                
                const response = await fetch(`api/buscar_receita.php?${params.toString()}`);
                const data = await response.json();
                
                if (loader) loader.classList.add('hidden');
                
                if (data.success && data.receitas && data.receitas.length > 0) {
                    renderizarReceitas(data.receitas);
                } else {
                    container.innerHTML = `
                        <div class="glass-card flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/></svg>
                            <p class="text-sm">Nenhuma receita encontrada.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Erro ao buscar receitas:', error);
                if (loader) loader.classList.add('hidden');
            }
        }
        
        function renderizarReceitas(receitas) {
            const container = document.getElementById('receitasContainer');
            
            const statusBadgeClass = (status) => {
                switch (status) {
                    case 'ativa': return 'bg-emerald-100 text-emerald-600';
                    case 'finalizada': return 'bg-blue-100 text-blue-600';
                    case 'vencida': return 'bg-rose-100 text-rose-600';
                    case 'cancelada': return 'bg-slate-100 text-slate-600';
                    default: return 'bg-slate-100 text-slate-600';
                }
            };
            
            const statusLabel = (status) => {
                switch (status) {
                    case 'ativa': return 'Ativa';
                    case 'finalizada': return 'Finalizada';
                    case 'vencida': return 'Vencida';
                    case 'cancelada': return 'Cancelada';
                    default: return status;
                }
            };
            
            const formatarData = (data) => {
                if (!data) return '-';
                const d = new Date(data + 'T00:00:00');
                return d.toLocaleDateString('pt-BR');
            };
            
            container.innerHTML = receitas.map(receita => `
                <div class="glass-card p-6 hover:shadow-glow transition cursor-pointer" onclick="window.location.href='receitas_dispensar.php?id=${receita.id}'">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-lg font-semibold text-slate-900">Receita #${receita.numero_receita || receita.id}</h3>
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(receita.status)}">
                                    ${statusLabel(receita.status)}
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mt-4">
                                <div>
                                    <span class="text-slate-500">Paciente:</span>
                                    <span class="ml-2 font-medium text-slate-700">${receita.paciente_nome}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500">Emissão:</span>
                                    <span class="ml-2 font-medium text-slate-700">${formatarData(receita.data_emissao)}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500">Validade:</span>
                                    <span class="ml-2 font-medium text-slate-700">${formatarData(receita.data_validade)}</span>
                                </div>
                            </div>
                            
                            <div class="flex gap-4 mt-4 text-sm">
                                <span class="inline-flex items-center gap-1 text-slate-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    ${receita.total_itens} medicamento(s)
                                </span>
                                ${receita.itens_pendentes > 0 ? `
                                    <span class="inline-flex items-center gap-1 text-amber-600 font-medium">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        ${receita.itens_pendentes} item(ns) pendente(s)
                                    </span>
                                ` : ''}
                            </div>
                            
                            ${receita.datas_planejadas && receita.datas_planejadas.length > 0 ? `
                                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span class="font-medium">Retiradas planejadas:</span>
                                    ${receita.datas_planejadas.map(data => {
                                        const dataFormatada = new Date(data.data_planejada + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                                        // Usar o campo ja_dispensada que vem da API
                                        const jaDispensada = data.ja_dispensada == 1 || data.ja_dispensada === true;
                                        const podeDispensar = receita.status === 'ativa' && data.receita_item_id && !jaDispensada;
                                        const botaoDispensar = podeDispensar ? `
                                            <button 
                                                onclick="event.stopPropagation(); abrirModalDispensacaoReceita(${receita.id}, ${data.receita_item_id}, ${data.medicamento_id}, '${data.medicamento_nome.replace(/'/g, "\\'")}', ${data.quantidade_autorizada || 0}, ${receita.paciente_id}, '${data.data_planejada}');" 
                                                class="px-2 py-0.5 bg-green-500 hover:bg-green-600 text-white rounded text-xs font-medium transition-all"
                                                title="Dispensar para esta data">
                                                ✓
                                            </button>
                                        ` : '';
                                        return `
                                            <div class="flex items-center gap-1">
                                                <span class="px-2 py-0.5 ${jaDispensada ? 'bg-gray-100 text-gray-500' : 'bg-blue-50 text-blue-700'} rounded">${dataFormatada}</span>
                                                ${botaoDispensar}
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            ` : ''}
                            
                            ${receita.observacoes ? `
                                <p class="text-sm text-slate-500 mt-3 italic">${receita.observacoes}</p>
                            ` : ''}
                        </div>
                        
                        <div class="flex flex-col gap-2 ml-4">
                            <a href="paciente_historico.php?id=${receita.paciente_id}" class="action-chip" title="Ver histórico do paciente" onclick="event.stopPropagation();">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Funções do modal de dispensação
        function abrirModalDispensacaoReceita(receitaId, receitaItemId, medicamentoId, medicamentoNome, quantidadeAutorizada, pacienteId, dataPlanejada = null) {
            document.getElementById('modal_receita_id').value = receitaId;
            document.getElementById('modal_receita_item_id').value = receitaItemId;
            document.getElementById('modal_medicamento_id').value = medicamentoId;
            document.getElementById('modal_paciente_id').value = pacienteId;
            document.getElementById('modal_medicamento_nome_receita').textContent = medicamentoNome;
            // Quantidade sempre será a autorizada completa (fixa) - retira tudo de uma vez
            document.getElementById('quantidade_receita').value = quantidadeAutorizada;
            if (dataPlanejada) {
                document.getElementById('modal_data_planejada').value = dataPlanejada;
            }
            document.getElementById('codigo_barras_busca').value = '';
            
            // Carregar lotes disponíveis
            carregarLotesReceita(medicamentoId);
            
            document.getElementById('modalDispensacaoReceita').classList.remove('hidden');
            document.getElementById('codigo_barras_busca').focus();
        }

        function fecharModalDispensacaoReceita() {
            document.getElementById('modalDispensacaoReceita').classList.add('hidden');
            document.getElementById('formDispensacaoReceita').reset();
        }

        async function carregarLotesReceita(medicamentoId) {
            const select = document.getElementById('lote_id_receita');
            select.innerHTML = '<option value="">Carregando...</option>';
            
            try {
                const response = await fetch(`api/buscar_lotes.php?medicamento_id=${medicamentoId}`);
                const data = await response.json();
                
                if (data.success && data.lotes.length > 0) {
                    select.innerHTML = '<option value="">Selecione um lote</option>';
                    data.lotes.forEach(lote => {
                        const option = document.createElement('option');
                        option.value = lote.id;
                        option.textContent = `Lote ${lote.numero_lote} - Validade: ${lote.data_validade_formatada} - Disponível: ${lote.quantidade_atual}`;
                        select.appendChild(option);
                    });
                } else {
                    select.innerHTML = '<option value="">Nenhum lote disponível</option>';
                }
            } catch (error) {
                console.error('Erro ao carregar lotes:', error);
                select.innerHTML = '<option value="">Erro ao carregar lotes</option>';
            }
        }

        let buscaLoteTimeout = null;

        async function buscarLotePorCodigo() {
            const codigoBarras = document.getElementById('codigo_barras_busca').value.trim();
            const medicamentoId = document.getElementById('modal_medicamento_id').value;
            const select = document.getElementById('lote_id_receita');
            
            if (!codigoBarras || codigoBarras.length < 3) {
                // Se o código for muito curto, recarregar todos os lotes
                if (codigoBarras.length === 0) {
                    await carregarLotesReceita(medicamentoId);
                }
                return;
            }
            
            try {
                const response = await fetch(`api/buscar_lote_por_codigo.php?codigo_barras=${encodeURIComponent(codigoBarras)}&medicamento_id=${medicamentoId}`);
                const data = await response.json();
                
                if (data.success && data.lote) {
                    // Limpar e recarregar todos os lotes primeiro
                    await carregarLotesReceita(medicamentoId);
                    
                    // Selecionar o lote encontrado
                    setTimeout(() => {
                        select.value = data.lote.id;
                    }, 100);
                } else {
                    // Se não encontrou, recarregar todos os lotes
                    await carregarLotesReceita(medicamentoId);
                }
            } catch (error) {
                console.error('Erro ao buscar lote:', error);
                await carregarLotesReceita(medicamentoId);
            }
        }

        // Busca automática enquanto digita
        document.addEventListener('DOMContentLoaded', function() {
            const codigoBarrasInput = document.getElementById('codigo_barras_busca');
            if (codigoBarrasInput) {
                codigoBarrasInput.addEventListener('input', function(e) {
                    clearTimeout(buscaLoteTimeout);
                    buscaLoteTimeout = setTimeout(() => {
                        buscarLotePorCodigo();
                    }, 300); // Aguarda 300ms após parar de digitar
                });
            }

            // Submissão do formulário
            const form = document.getElementById('formDispensacaoReceita');
            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const formData = new FormData(e.target);
                    const data = Object.fromEntries(formData.entries());
                    
                    try {
                        const response = await fetch('api/dispensar_receita.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(data)
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            mostrarAlerta('Medicamento dispensado com sucesso!', 'sucesso');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            mostrarAlerta('Erro: ' + result.message, 'erro');
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        mostrarAlerta('Erro ao processar dispensação', 'erro');
                    }
                });
            }
        });

        // Função para mostrar alerta customizado
        function mostrarAlerta(mensagem, tipo = 'info', titulo = null) {
            const modal = document.getElementById('modalAlerta');
            const icon = document.getElementById('alertaIcon');
            const tituloEl = document.getElementById('alertaTitulo');
            const mensagemEl = document.getElementById('alertaMensagem');
            
            // Definir cores e ícones baseado no tipo
            const config = {
                sucesso: {
                    cor: 'from-green-500 to-emerald-500',
                    icone: '✓',
                    titulo: 'Sucesso!'
                },
                erro: {
                    cor: 'from-red-500 to-rose-500',
                    icone: '✕',
                    titulo: 'Erro!'
                },
                aviso: {
                    cor: 'from-yellow-500 to-amber-500',
                    icone: '⚠',
                    titulo: 'Atenção!'
                },
                info: {
                    cor: 'from-blue-500 to-indigo-500',
                    icone: 'ℹ',
                    titulo: 'Informação'
                }
            };
            
            const tipoConfig = config[tipo] || config.info;
            
            icon.className = `w-16 h-16 bg-gradient-to-br ${tipoConfig.cor} rounded-full flex items-center justify-center mx-auto mb-4`;
            icon.innerHTML = `<span class="text-white text-3xl">${tipoConfig.icone}</span>`;
            tituloEl.textContent = titulo || tipoConfig.titulo;
            mensagemEl.textContent = mensagem;
            
            modal.classList.remove('hidden');
        }

        function fecharAlerta() {
            document.getElementById('modalAlerta').classList.add('hidden');
        }
    </script>
</body>
</html>
