<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['usuario']);

$conn = getConnection();

$reportType = $_GET['tipo'] ?? '';
$validReports = ['estoque', 'vencimento', 'movimentacao', 'estoque_minimo'];
if (!in_array($reportType, $validReports, true)) {
    $reportType = '';
}

$pageTitle = 'Relatórios';
$sectionTitle = 'Relatórios do Sistema';
$sectionSubtitle = 'Selecione um tipo de relatório para visualizar';

switch ($reportType) {
    case 'estoque':
        $pageTitle = 'Relatório de Estoque';
        $sectionTitle = 'Visão Geral do Estoque';
        $sectionSubtitle = 'Resumo atualizado dos medicamentos e seus estoques disponíveis';
        break;
    case 'vencimento':
        $pageTitle = 'Relatório de Vencimentos';
        $sectionTitle = 'Medicamentos Próximos do Vencimento';
        $sectionSubtitle = 'Acompanhe os lotes com datas de validade próximas ou vencidas';
        break;
    case 'movimentacao':
        $pageTitle = 'Relatório de Movimentações';
        $sectionTitle = 'Histórico de Movimentações';
        $sectionSubtitle = 'Entradas, saídas e ajustes registrados no sistema';
        break;
    case 'estoque_minimo':
        $pageTitle = 'Relatório de Estoque Mínimo';
        $sectionTitle = 'Medicamentos abaixo do Estoque Mínimo';
        $sectionSubtitle = 'Itens que precisam de atenção para reposição';
        break;
}

$errors = [];
$data = [];
$metrics = [];

function formatNumber($value)
{
    return number_format((float) $value, 0, ',', '.');
}

if ($reportType === 'estoque' || $reportType === 'estoque_minimo') {
    try {
        $stmt = $conn->prepare(
            'SELECT 
                m.id,
                m.nome,
                m.estoque_minimo,
                COALESCE(SUM(CASE WHEN l.quantidade_atual IS NULL THEN 0 ELSE l.quantidade_atual END), 0) AS estoque_total,
                COALESCE(c.nome, "Sem categoria") AS categoria,
                COALESCE(a.nome, "Sem apresentação") AS apresentacao
            FROM medicamentos m
            LEFT JOIN lotes l ON l.medicamento_id = m.id
            LEFT JOIN categorias c ON m.categoria_id = c.id
            LEFT JOIN apresentacoes a ON m.apresentacao_id = a.id
            WHERE m.ativo = 1
            GROUP BY m.id, m.nome, m.estoque_minimo, c.nome, a.nome
            ORDER BY m.nome ASC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(static function ($row) {
            $row['estoque_total'] = (int) $row['estoque_total'];
            $row['estoque_minimo'] = (int) ($row['estoque_minimo'] ?? 0);
            return $row;
        }, $rows);

        $totalMedicamentos = count($data);
        $totalUnidades = array_reduce($data, static fn ($carry, $item) => $carry + $item['estoque_total'], 0);
        $abaixoMinimo = array_reduce($data, static function ($carry, $item) {
            if ($item['estoque_minimo'] > 0 && $item['estoque_total'] < $item['estoque_minimo']) {
                return $carry + 1;
            }
            return $carry;
        }, 0);

        if ($reportType === 'estoque_minimo') {
            $data = array_values(array_filter($data, static fn ($item) => $item['estoque_minimo'] > 0 && $item['estoque_total'] < $item['estoque_minimo']));
        }

        $metrics = [
            'totalMedicamentos' => $totalMedicamentos,
            'totalUnidades' => $totalUnidades,
            'abaixoMinimo' => $abaixoMinimo,
        ];
    } catch (PDOException $e) {
        $errors[] = 'Erro ao carregar dados de estoque: ' . $e->getMessage();
    }
}

if ($reportType === 'vencimento') {
    try {
        $stmt = $conn->prepare(
            'SELECT 
                l.id,
                l.numero_lote,
                l.data_validade,
                l.quantidade_atual,
                m.nome,
                cb.codigo as codigo_barras,
                DATEDIFF(l.data_validade, CURDATE()) AS dias_restantes
            FROM lotes l
            INNER JOIN medicamentos m ON l.medicamento_id = m.id
            LEFT JOIN codigos_barras cb ON l.codigo_barras_id = cb.id
            WHERE l.data_validade IS NOT NULL
              AND l.quantidade_atual > 0
              AND l.data_validade <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
            ORDER BY l.data_validade ASC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = array_map(static function ($row) {
            $row['dias_restantes'] = isset($row['dias_restantes']) ? (int) $row['dias_restantes'] : null;
            return $row;
        }, $rows);

        $metrics = [
            'totalLotes' => count($data),
            'vencidos' => array_reduce($data, static fn ($carry, $item) => $carry + ($item['dias_restantes'] !== null && $item['dias_restantes'] < 0 ? 1 : 0), 0),
            'ate30' => array_reduce($data, static fn ($carry, $item) => $carry + ($item['dias_restantes'] !== null && $item['dias_restantes'] >= 0 && $item['dias_restantes'] <= 30 ? 1 : 0), 0),
            'ate60' => array_reduce($data, static fn ($carry, $item) => $carry + ($item['dias_restantes'] !== null && $item['dias_restantes'] > 30 && $item['dias_restantes'] <= 60 ? 1 : 0), 0),
            'ate90' => array_reduce($data, static fn ($carry, $item) => $carry + ($item['dias_restantes'] !== null && $item['dias_restantes'] > 60 && $item['dias_restantes'] <= 90 ? 1 : 0), 0),
        ];
    } catch (PDOException $e) {
        $errors[] = 'Erro ao carregar dados de vencimento: ' . $e->getMessage();
    }
}

if ($reportType === 'movimentacao') {
    try {
        $stmt = $conn->prepare(
            'SELECT 
                mv.id,
                mv.tipo,
                mv.quantidade,
                mv.quantidade_anterior,
                mv.quantidade_posterior,
                mv.motivo,
                mv.observacoes,
                mv.data_movimentacao,
                mv.criado_em,
                m.nome AS medicamento_nome,
                COALESCE(l.numero_lote, "—") AS numero_lote,
                COALESCE(u.nome, "Sistema") AS usuario_nome
            FROM movimentacoes mv
            LEFT JOIN medicamentos m ON mv.medicamento_id = m.id
            LEFT JOIN lotes l ON mv.lote_id = l.id
            LEFT JOIN usuarios u ON mv.usuario_id = u.id
            ORDER BY COALESCE(mv.data_movimentacao, mv.criado_em) DESC
            LIMIT 100'
        );
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $metrics = [
            'totalRegistros' => count($data),
            'entradas' => array_reduce($data, static fn ($carry, $item) => $carry + ($item['tipo'] === 'entrada' ? 1 : 0), 0),
            'saidas' => array_reduce($data, static fn ($carry, $item) => $carry + ($item['tipo'] === 'saida' || $item['tipo'] === 'dispensacao' ? 1 : 0), 0),
        ];
    } catch (PDOException $e) {
        $errors[] = 'Erro ao carregar dados de movimentação: ' . $e->getMessage();
    }
}

function movimentacaoBadgeClass($tipo)
{
    return match ($tipo) {
        'entrada' => 'bg-emerald-100 text-emerald-700',
        'saida', 'dispensacao' => 'bg-rose-100 text-rose-700',
        'ajuste' => 'bg-amber-100 text-amber-700',
        'devolucao' => 'bg-blue-100 text-blue-700',
        'vencimento' => 'bg-slate-200 text-slate-700',
        default => 'bg-slate-100 text-slate-600',
    };
}

function estoqueBadgeClass($atual, $minimo)
{
    if ($minimo <= 0) {
        return 'bg-slate-100 text-slate-600';
    }
    if ($atual <= 0) {
        return 'bg-rose-100 text-rose-700';
    }
    if ($atual < $minimo) {
        return 'bg-amber-100 text-amber-700';
    }
    return 'bg-emerald-100 text-emerald-700';
}

function vencimentoBadgeClass($dias)
{
    if ($dias === null) {
        return 'bg-slate-100 text-slate-600';
    }
    if ($dias < 0) {
        return 'bg-rose-100 text-rose-700';
    }
    if ($dias <= 30) {
        return 'bg-amber-100 text-amber-700';
    }
    if ($dias <= 60) {
        return 'bg-yellow-100 text-yellow-700';
    }
    if ($dias <= 90) {
        return 'bg-blue-100 text-blue-700';
    }
    return 'bg-slate-100 text-slate-600';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Relatórios completos do sistema de gestão de farmácia">
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?> - <?php echo SYSTEM_NAME; ?>">
    <meta property="og:description" content="Relatórios completos do sistema de gestão de farmácia">
    <meta property="og:type" content="website">
    <meta property="og:image" content="../images/logo.svg">
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
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
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="../css/admin_new.css">
    <style>
        .report-card {
            display: block;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .report-card .glass-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .report-card:hover .glass-card {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(79, 70, 229, 0.15);
        }
    </style>
</head>
<body class="admin-shell">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-12 lg:py-10 space-y-6 lg:space-y-8">
        <header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-2">
                    <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Relatórios</span>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($sectionTitle); ?></h1>
                    <p class="max-w-2xl text-sm sm:text-base text-slate-500"><?php echo htmlspecialchars($sectionSubtitle); ?></p>
                </div>
                <?php if (!empty($reportType)): ?>
                    <div class="flex items-center gap-3">
                        <a href="relatorios.php" class="inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 shadow hover:shadow-lg transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            Voltar
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="glass-card border border-rose-200/60 bg-rose-50/80 px-6 py-4">
                <strong class="block text-sm font-semibold text-rose-700">Atenção</strong>
                <ul class="mt-2 space-y-1 text-sm text-rose-700">
                    <?php foreach ($errors as $message): ?>
                        <li>• <?php echo htmlspecialchars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($reportType)): ?>
            <section class="glass-card p-6 lg:p-8 space-y-8">
                <div class="space-y-2">
                    <h2 class="text-xl font-semibold text-slate-900">Selecione um tipo de relatório:</h2>
                    <p class="text-sm text-slate-500">Escolha abaixo a visão desejada para visualizar informações detalhadas sobre o estoque.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <a href="?tipo=estoque" class="report-card">
                        <div class="glass-card h-full p-6 text-center hover:border-primary-200">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 9.75V4.875c0-.621-.504-1.125-1.125-1.125h-9.75c-.621 0-1.125.504-1.125 1.125v5.25M16.5 9.75H18c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125H6A1.125 1.125 0 0 1 4.875 19.5v-8.25c0-.621.504-1.125 1.125-1.125h1.5m9 0h-9"/></svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-slate-900">Estoque</h3>
                            <p class="mt-2 text-sm text-slate-500">Visão geral do estoque atual de medicamentos</p>
                        </div>
                    </a>
                    <a href="?tipo=vencimento" class="report-card">
                        <div class="glass-card h-full p-6 text-center hover:border-primary-200">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6l4 2m5-.25A8.75 8.75 0 1 1 12 3.25a8.75 8.75 0 0 1 9 8.5Z"/></svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-slate-900">Vencimento</h3>
                            <p class="mt-2 text-sm text-slate-500">Medicamentos próximos ao vencimento</p>
                        </div>
                    </a>
                    <a href="?tipo=movimentacao" class="report-card">
                        <div class="glass-card h-full p-6 text-center hover:border-primary-200">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-slate-900">Movimentação</h3>
                            <p class="mt-2 text-sm text-slate-500">Histórico de entradas e saídas</p>
                        </div>
                    </a>
                    <a href="?tipo=estoque_minimo" class="report-card">
                        <div class="glass-card h-full p-6 text-center hover:border-primary-200">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3m0 3h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-slate-900">Estoque Mínimo</h3>
                            <p class="mt-2 text-sm text-slate-500">Medicamentos abaixo do estoque mínimo</p>
                        </div>
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($reportType === 'estoque' && empty($errors)): ?>
            <section class="space-y-6">
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="glass-card p-6">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Medicamentos</span>
                        <p class="mt-1 text-3xl font-bold text-slate-900"><?php echo formatNumber($metrics['totalMedicamentos'] ?? 0); ?></p>
                        <p class="mt-2 text-sm text-slate-500">Medicamentos ativos cadastrados com estoque registrado</p>
                    </div>
                    <div class="glass-card p-6">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Unidades em estoque</span>
                        <p class="mt-1 text-3xl font-bold text-slate-900"><?php echo formatNumber($metrics['totalUnidades'] ?? 0); ?></p>
                        <p class="mt-2 text-sm text-slate-500">Total de unidades disponíveis considerando todos os lotes</p>
                    </div>
                    <div class="glass-card p-6">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Abaixo do mínimo</span>
                        <p class="mt-1 text-3xl font-bold text-rose-600"><?php echo formatNumber($metrics['abaixoMinimo'] ?? 0); ?></p>
                        <p class="mt-2 text-sm text-slate-500">Itens que precisam de reposição urgente</p>
                    </div>
                </div>

                <div class="glass-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-white">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-6 py-3">Medicamento</th>
                                    <th class="px-6 py-3">Apresentação</th>
                                    <th class="px-6 py-3">Estoque Total</th>
                                    <th class="px-6 py-3">Estoque Mínimo</th>
                                    <th class="px-6 py-3 text-right">Situação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php foreach ($data as $row): ?>
                                    <tr class="text-sm text-slate-600">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($row['nome']); ?></span>
                                                <span class="text-xs text-slate-400">Código: <?php echo htmlspecialchars($row['codigo_barras'] ?? '—'); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['apresentacao']); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-600"><?php echo formatNumber($row['estoque_total']); ?></span>
                                        </td>
                                        <td class="px-6 py-4"><?php echo formatNumber($row['estoque_minimo']); ?></td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?php echo estoqueBadgeClass($row['estoque_total'], $row['estoque_minimo']); ?>">
                                                <?php if ($row['estoque_minimo'] <= 0): ?>Sem mínimo<?php elseif ($row['estoque_total'] <= 0): ?>Sem estoque<?php elseif ($row['estoque_total'] < $row['estoque_minimo']): ?>Abaixo do mínimo<?php else: ?>Adequado<?php endif; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($reportType === 'vencimento' && empty($errors)): ?>
            <section class="space-y-6">
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="glass-card p-6">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Lotes Monitorados</span>
                        <p class="mt-1 text-3xl font-bold text-slate-900"><?php echo formatNumber($metrics['totalLotes'] ?? 0); ?></p>
                        <p class="mt-2 text-sm text-slate-500">Lotes com validade até 90 dias</p>
                    </div>
                    <div class="glass-card p-6">
                        <span class="text-xs font-semibold uppercase tracking-wider text-rose-600">Vencidos</span>
                        <p class="mt-1 text-3xl font-bold text-rose-600"><?php echo formatNumber($metrics['vencidos'] ?? 0); ?></p>
                        <p class="mt-2 text-sm text-slate-500">Lotes com validade expirada</p>
                    </div>
                    <div class="glass-card p-6">
                        <span class="text-xs font-semibold uppercase tracking-wider text-amber-600">Até 30 dias</span>
                        <p class="mt-1 text-3xl font-bold text-amber-600"><?php echo formatNumber($metrics['ate30'] ?? 0); ?></p>
                        <p class="mt-2 text-sm text-slate-500">Lotes que vencem em até 30 dias</p>
                    </div>
                    <div class="glass-card p-6">
                        <span class="text-xs font-semibold uppercase tracking-wider text-blue-600">Até 60/90 dias</span>
                        <p class="mt-1 text-3xl font-bold text-blue-600"><?php echo formatNumber(($metrics['ate60'] ?? 0) + ($metrics['ate90'] ?? 0)); ?></p>
                        <p class="mt-2 text-sm text-slate-500">Lotes que vencem entre 31 e 90 dias</p>
                    </div>
                </div>

                <div class="glass-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-white">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-6 py-3">Medicamento</th>
                                    <th class="px-6 py-3">Número do Lote</th>
                                    <th class="px-6 py-3">Validade</th>
                                    <th class="px-6 py-3">Dias Restantes</th>
                                    <th class="px-6 py-3">Quantidade</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php foreach ($data as $row): ?>
                                    <tr class="text-sm text-slate-600">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($row['nome']); ?></span>
                                                <span class="text-xs text-slate-400">Código: <?php echo htmlspecialchars($row['codigo_barras'] ?? '—'); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['numero_lote']); ?></td>
                                        <td class="px-6 py-4"><?php echo date('d/m/Y', strtotime($row['data_validade'])); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?php echo vencimentoBadgeClass($row['dias_restantes']); ?>">
                                                <?php echo $row['dias_restantes'] === null ? '—' : ($row['dias_restantes'] < 0 ? 'Vencido' : $row['dias_restantes'] . ' dias'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4"><?php echo formatNumber($row['quantidade_atual']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($reportType === 'movimentacao' && empty($errors)): ?>
            <section class="space-y-6">
                <div class="grid gap-5 sm:grid-cols-3">
                    <div class="glass-card p-6">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Registros</span>
                        <p class="mt-1 text-3xl font-bold text-slate-900"><?php echo formatNumber($metrics['totalRegistros'] ?? 0); ?></p>
                        <p class="mt-2 text-sm text-slate-500">Movimentações armazenadas (últimos 100 registros)</p>
                    </div>
                    <div class="glass-card p-6">
                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Entradas</span>
                        <p class="mt-1 text-3xl font-bold text-emerald-600"><?php echo formatNumber($metrics['entradas'] ?? 0); ?></p>
                        <p class="mt-2 text-sm text-slate-500">Registros de entrada no estoque</p>
                    </div>
                    <div class="glass-card p-6">
                        <span class="text-xs font-semibold uppercase tracking-wider text-rose-600">Saídas</span>
                        <p class="mt-1 text-3xl font-bold text-rose-600"><?php echo formatNumber($metrics['saidas'] ?? 0); ?></p>
                        <p class="mt-2 text-sm text-slate-500">Retiradas, dispensações e ajustes negativos</p>
                    </div>
                </div>

                <div class="glass-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-white">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-6 py-3">Medicamento</th>
                                    <th class="px-6 py-3">Lote</th>
                                    <th class="px-6 py-3">Tipo</th>
                                    <th class="px-6 py-3">Quantidade</th>
                                    <th class="px-6 py-3">Data</th>
                                    <th class="px-6 py-3">Usuário</th>
                                    <th class="px-6 py-3">Observações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php foreach ($data as $row): ?>
                                    <tr class="text-sm text-slate-600">
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['medicamento_nome'] ?? '—'); ?></td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['numero_lote']); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?php echo movimentacaoBadgeClass($row['tipo']); ?>"><?php echo htmlspecialchars(ucfirst($row['tipo'])); ?></span>
                                        </td>
                                        <td class="px-6 py-4"><?php echo formatNumber($row['quantidade']); ?></td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $dataMovimentacao = $row['data_movimentacao'] ?? $row['criado_em'];
                                            echo $dataMovimentacao ? date('d/m/Y H:i', strtotime($dataMovimentacao)) : '—';
                                            ?>
                                        </td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['usuario_nome']); ?></td>
                                        <td class="px-6 py-4 max-w-sm text-sm text-slate-500">
                                            <?php echo htmlspecialchars($row['motivo'] ?: $row['observacoes'] ?: '—'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($reportType === 'estoque_minimo' && empty($errors)): ?>
            <section class="space-y-6">
                <div class="glass-card p-6">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Medicamentos abaixo do mínimo</span>
                    <p class="mt-1 text-3xl font-bold text-rose-600"><?php echo formatNumber($metrics['abaixoMinimo'] ?? 0); ?></p>
                    <p class="mt-2 text-sm text-slate-500">Itens que precisam de reposição urgente</p>
                </div>

                <div class="glass-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-white">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-6 py-3">Medicamento</th>
                                    <th class="px-6 py-3">Estoque Total</th>
                                    <th class="px-6 py-3">Estoque Mínimo</th>
                                    <th class="px-6 py-3">Diferença</th>
                                    <th class="px-6 py-3">Apresentação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php foreach ($data as $row): ?>
                                    <tr class="text-sm text-slate-600">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($row['nome']); ?></span>
                                                <span class="text-xs text-slate-400">Código: <?php echo htmlspecialchars($row['codigo_barras'] ?? '—'); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4"><?php echo formatNumber($row['estoque_total']); ?></td>
                                        <td class="px-6 py-4"><?php echo formatNumber($row['estoque_minimo']); ?></td>
                                        <td class="px-6 py-4"><?php echo formatNumber($row['estoque_total'] - $row['estoque_minimo']); ?></td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['apresentacao']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
