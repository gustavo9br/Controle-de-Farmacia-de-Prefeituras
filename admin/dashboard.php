<?php<?php

// Redirecionamento automático para a nova página principalrequire_once __DIR__ . '/../includes/auth.php';

header('Location: index.php');requireAdmin();

exit;

$conn = getConnection();

function tableExists(PDO $conn, string $table): bool
{
    try {
        $stmt = $conn->prepare("SHOW TABLES LIKE :table");
        $stmt->bindValue(':table', $table);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

$stats = [
    'saidasHoje' => 0,
    'itensDispensados' => 0,
    'pacientesAtendidos' => 0,
    'estoqueCritico' => 0,
    'validadeCritica' => 0,
    'estoqueTotal' => 0,
];

$recentSaidas = [];
$estoquesCriticos = [];
$validadeProxima = [];

$hasSaidas = tableExists($conn, 'saidas');
$hasSaidaItens = tableExists($conn, 'saida_itens');

try {
    // Total de medicamentos no estoque
    $stmt = $conn->query("SELECT COALESCE(SUM(estoque_atual), 0) FROM medicamentos");
    $stats['estoqueTotal'] = (int)$stmt->fetchColumn();

    // Medicamentos com estoque crítico
    $stmt = $conn->query("SELECT COUNT(*) FROM medicamentos WHERE estoque_atual <= estoque_minimo AND estoque_minimo > 0");
    $stats['estoqueCritico'] = (int)$stmt->fetchColumn();

    // Lotes próximos ao vencimento (<= 30 dias)
    $stmt = $conn->query("SELECT COUNT(*) FROM lotes WHERE quantidade_atual > 0 AND data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $stats['validadeCritica'] = (int)$stmt->fetchColumn();

    if ($hasSaidas && $hasSaidaItens) {
        // Saídas do dia
        $stmt = $conn->query("SELECT COUNT(*) FROM saidas WHERE status = 'finalizado' AND DATE(data_saida) = CURDATE()");
        $stats['saidasHoje'] = (int)$stmt->fetchColumn();

        // Itens dispensados no dia
        $stmt = $conn->query("SELECT COALESCE(SUM(si.quantidade), 0)
                               FROM saida_itens si
                               INNER JOIN saidas s ON s.id = si.saida_id
                               WHERE s.status = 'finalizado' AND DATE(s.data_saida) = CURDATE()");
        $stats['itensDispensados'] = (int)$stmt->fetchColumn();

        // Pacientes atendidos hoje
        $stmt = $conn->query("SELECT COUNT(DISTINCT paciente_cpf)
                               FROM saidas
                               WHERE status = 'finalizado' AND DATE(data_saida) = CURDATE() AND paciente_cpf IS NOT NULL AND paciente_cpf <> ''");
        $stats['pacientesAtendidos'] = (int)$stmt->fetchColumn();

        // Saídas recentes
        $stmt = $conn->query("SELECT s.id, s.paciente_nome, s.paciente_cpf, s.data_saida, s.status,
                                      COALESCE((SELECT SUM(quantidade) FROM saida_itens WHERE saida_id = s.id), 0) AS total_itens
                               FROM saidas s
                               WHERE s.status = 'finalizado'
                               ORDER BY s.data_saida DESC
                               LIMIT 5");
        $recentSaidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Fallback para tabela de movimentações
        if (tableExists($conn, 'movimentacoes')) {
            $stmt = $conn->query("SELECT COUNT(*) FROM movimentacoes WHERE tipo = 'saida' AND DATE(data_movimentacao) = CURDATE()");
            $stats['saidasHoje'] = (int)$stmt->fetchColumn();

            $stmt = $conn->query("SELECT COALESCE(SUM(quantidade), 0) FROM movimentacoes WHERE tipo = 'saida' AND DATE(data_movimentacao) = CURDATE()");
            $stats['itensDispensados'] = (int)$stmt->fetchColumn();

            $stmt = $conn->query("SELECT medicamento_id, lote_id, quantidade, data_movimentacao
                                   FROM movimentacoes WHERE tipo = 'saida'
                                   ORDER BY data_movimentacao DESC LIMIT 5");
            $recentSaidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Estoques críticos detalhados
    $stmt = $conn->query("SELECT nome, estoque_atual, estoque_minimo
                           FROM medicamentos
                           WHERE estoque_minimo > 0 AND estoque_atual <= estoque_minimo
                           ORDER BY estoque_atual ASC
                           LIMIT 6");
    $estoquesCriticos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lotes próximos da validade
    $stmt = $conn->query("SELECT m.nome AS medicamento, l.numero_lote, l.data_validade, l.quantidade_atual
                           FROM lotes l
                           INNER JOIN medicamentos m ON m.id = l.medicamento_id
                           WHERE l.quantidade_atual > 0 AND l.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                           ORDER BY l.data_validade ASC
                           LIMIT 6");
    $validadeProxima = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $statsError = $e->getMessage();
}

$pageTitle = 'Central da Farmácia Popular';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Painel administrativo da Farmácia Popular - controle de estoques e saídas de medicamentos.">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
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
    <div class="flex">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-12 lg:py-10">
            <header class="flex flex-col gap-4 lg:gap-6 lg:flex-row lg:items-center lg:justify-between mb-6 lg:mb-8">
                <div>
                    <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Farmácia Popular</span>
                    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900">Central de Saídas e Estoque</h1>
                    <p class="mt-2 text-sm sm:text-base text-slate-500 max-w-xl">Acompanhe as dispensações do dia, ative o leitor de código de barras e mantenha o estoque sempre saudável.</p>
                </div>
                <div class="flex flex-col gap-3 lg:items-end w-full lg:w-auto">
                    <div class="flex items-center gap-3 glass-card px-4 py-2.5">
                        <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                        <span class="text-xs sm:text-sm font-medium text-slate-600">Leitor de código pronto</span>
                    </div>
                    <form class="relative w-full">
                        <input type="search" placeholder="Buscar paciente, medicamento..." class="w-full rounded-full border-0 px-5 py-2.5 sm:py-3 pl-11 text-sm sm:text-base text-slate-600 placeholder:text-slate-400 shadow focus:ring-2 focus:ring-primary-500" autocomplete="off">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-primary-500 absolute left-4 top-1/2 -translate-y-1/2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                    </form>
                </div>
            </header>

            <?php if (isset($statsError)): ?>
                <div class="glass-card border border-rose-100 bg-rose-50/90 text-rose-600 px-6 py-4 mb-6">
                    <strong>Falha ao carregar dados:</strong> <?php echo htmlspecialchars($statsError); ?>
                </div>
            <?php endif; ?>

            <section class="grid gap-4 sm:gap-6 lg:grid-cols-3 mb-6 lg:mb-10">
                <div class="glass-card p-5 sm:p-6 lg:col-span-2">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 sm:gap-6">
                        <div>
                            <h2 class="text-xl sm:text-2xl font-semibold text-slate-900">Saída rápida</h2>
                            <p class="mt-2 text-sm sm:text-base text-slate-500">Comece uma nova dispensação manualmente ou utilizando o leitor de código de barras.</p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2.5 sm:gap-3">
                            <a href="../usuario/saida_form.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-5 sm:px-6 py-2.5 sm:py-3 text-sm sm:text-base text-white font-semibold shadow-glow transition hover:bg-primary-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6"/></svg>
                                <span class="hidden sm:inline">Registrar saída manual</span>
                                <span class="sm:hidden">Saída manual</span>
                            </a>
                            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-full bg-white/80 px-5 sm:px-6 py-2.5 sm:py-3 text-sm sm:text-base font-semibold text-primary-600 shadow hover:shadow-lg transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><rect x="3" y="5" width="18" height="14" rx="2" stroke-width="1.5"/><path d="M7 9h1v6H7zM16 9h1v6h-1zM12 9.75v4.5" stroke-width="1.5" stroke-linecap="round"/></svg>
                                <span class="hidden sm:inline">Iniciar leitor</span>
                                <span class="sm:hidden">Leitor</span>
                            </button>
                        </div>
                    </div>
                    <div class="mt-6 sm:mt-8 grid gap-4 sm:gap-5 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-2xl sm:rounded-3xl bg-gradient-to-br from-primary-600/90 to-accent-500/80 px-5 py-5 sm:px-6 sm:py-6 text-white shadow-glow">
                            <span class="text-xs sm:text-sm uppercase tracking-wider sm:tracking-widest text-white/70">Saídas de hoje</span>
                            <p class="mt-3 sm:mt-4 text-3xl sm:text-4xl font-bold"><?php echo number_format($stats['saidasHoje']); ?></p>
                            <div class="metric-pill positive mt-4 sm:mt-5 bg-white/15 text-white text-xs sm:text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 12.75 9 17.25l10.5-10.5"/></svg>
                                Fluxo regular
                            </div>
                        </div>
                        <div class="glass-card p-5 sm:p-6">
                            <span class="text-xs sm:text-sm uppercase tracking-wider sm:tracking-widest text-slate-400">Itens dispensados</span>
                            <p class="mt-3 sm:mt-4 text-2xl sm:text-3xl font-semibold text-slate-900"><?php echo number_format($stats['itensDispensados']); ?></p>
                            <p class="mt-2 sm:mt-3 text-xs sm:text-sm text-slate-500">Quantidade total de unidades entregues hoje.</p>
                        </div>
                        <div class="glass-card p-5 sm:p-6">
                            <span class="text-xs sm:text-sm uppercase tracking-wider sm:tracking-widest text-slate-400">Pacientes atendidos</span>
                            <p class="mt-3 sm:mt-4 text-2xl sm:text-3xl font-semibold text-slate-900"><?php echo number_format($stats['pacientesAtendidos']); ?></p>
                            <p class="mt-2 sm:mt-3 text-xs sm:text-sm text-slate-500">CPF único registrado em dispensações finalizadas.</p>
                        </div>
                    </div>
                </div>
                <div class="glass-card p-5 sm:p-6 flex flex-col gap-5 sm:gap-6">
                    <div>
                        <span class="text-xs sm:text-sm uppercase tracking-wider sm:tracking-widest text-slate-400">Status do estoque</span>
                        <h3 class="mt-2 text-2xl sm:text-3xl font-semibold text-slate-900"><?php echo number_format($stats['estoqueTotal']); ?> unidades</h3>
                        <p class="mt-2 text-xs sm:text-sm text-slate-500">Soma do estoque disponível para dispensação.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:gap-4">
                        <div class="flex items-center justify-between rounded-2xl sm:rounded-3xl bg-white/80 px-3.5 sm:px-4 py-2.5 sm:py-3 shadow">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-amber-400"></div>
                                <span class="text-sm sm:text-base font-medium text-slate-700">Estoque crítico</span>
                            </div>
                            <span class="text-base sm:text-lg font-semibold text-amber-500"><?php echo number_format($stats['estoqueCritico']); ?></span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl sm:rounded-3xl bg-white/80 px-3.5 sm:px-4 py-2.5 sm:py-3 shadow">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-rose-400"></div>
                                <span class="text-sm sm:text-base font-medium text-slate-700">Validades &lt; 30 dias</span>
                            </div>
                            <span class="text-base sm:text-lg font-semibold text-rose-500"><?php echo number_format($stats['validadeCritica']); ?></span>
                        </div>
                    </div>
                    <div class="chart-placeholder text-sm sm:text-base">
                        <span>Resumo visual em desenvolvimento</span>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:gap-6 lg:grid-cols-3 mb-6 lg:mb-10">
                <div class="glass-card p-5 sm:p-6 lg:col-span-2">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0 mb-5 sm:mb-6">
                        <div>
                            <h3 class="text-lg sm:text-xl font-semibold text-slate-900">Saídas recentes</h3>
                            <p class="text-xs sm:text-sm text-slate-500 mt-1">Últimas dispensações registradas no sistema.</p>
                        </div>
                        <a href="../admin_old/saidas.php" class="text-xs sm:text-sm font-medium text-primary-600 hover:text-primary-500 self-start sm:self-auto">Ver histórico →</a>
                    </div>
                    <?php if (!empty($recentSaidas)): ?>
                        <div class="space-y-3 sm:space-y-4">
                            <?php foreach ($recentSaidas as $saida): ?>
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0 rounded-2xl sm:rounded-3xl bg-white/80 px-4 sm:px-5 py-3 sm:py-4 shadow">
                                    <div>
                                        <p class="text-xs sm:text-sm uppercase tracking-wider sm:tracking-widest text-slate-400">Paciente</p>
                                        <p class="text-sm sm:text-base font-semibold text-slate-900"><?php echo htmlspecialchars($saida['paciente_nome'] ?? 'Registro de saída'); ?></p>
                                        <p class="text-xs text-slate-400 mt-1">Itens: <?php echo isset($saida['total_itens']) ? (int)$saida['total_itens'] : (int)($saida['quantidade'] ?? 0); ?></p>
                                    </div>
                                    <div class="flex items-center gap-2 sm:gap-3">
                                        <span class="text-xs sm:text-sm font-medium text-slate-500"><?php echo isset($saida['data_saida']) ? date('d/m H:i', strtotime($saida['data_saida'])) : date('d/m H:i', strtotime($saida['data_movimentacao'])); ?></span>
                                        <span class="metric-pill positive bg-emerald-50 text-emerald-600 text-xs sm:text-sm">Finalizado</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="rounded-2xl sm:rounded-3xl border border-dashed border-slate-200 px-4 sm:px-5 py-8 sm:py-12 text-center text-sm sm:text-base text-slate-500">
                            Ainda não há saídas registradas hoje.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="glass-card p-5 sm:p-6 flex flex-col gap-4 sm:gap-5">
                    <div>
                        <h3 class="text-lg sm:text-xl font-semibold text-slate-900">Alertas rápidos</h3>
                        <p class="text-xs sm:text-sm text-slate-500 mt-1">Itens que precisam da sua atenção imediata.</p>
                    </div>
                    <div class="space-y-3 sm:space-y-4">
                        <?php if (!empty($estoquesCriticos)): ?>
                            <?php foreach ($estoquesCriticos as $med): ?>
                                <div class="rounded-2xl sm:rounded-3xl bg-rose-50/90 px-3.5 sm:px-4 py-3 sm:py-4">
                                    <p class="text-xs sm:text-sm font-semibold text-rose-600"><?php echo htmlspecialchars($med['nome']); ?></p>
                                    <div class="flex items-center justify-between text-xs text-rose-500 mt-2">
                                        <span>Atual: <?php echo (int)$med['estoque_atual']; ?></span>
                                        <span>Mínimo: <?php echo (int)$med['estoque_minimo']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="rounded-2xl sm:rounded-3xl border border-dashed border-emerald-200 px-3.5 sm:px-4 py-5 sm:py-6 text-center text-xs sm:text-sm text-emerald-500">
                                Nenhum estoque crítico encontrado.
                            </div>
                        <?php endif; ?>
                    </div>
                    <a href="medicamentos.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-4 sm:px-5 py-2.5 sm:py-3 text-xs sm:text-sm font-semibold text-white shadow-glow hover:bg-primary-500">Gerenciar estoque</a>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2 pb-12">
                <div class="glass-card p-6">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900">Validades próximas</h3>
                            <p class="text-sm text-slate-500">Lotes com vencimento em até 90 dias.</p>
                        </div>
                        <a href="../admin_old/medicamentos_vencimento.php" class="text-sm font-medium text-primary-600 hover:text-primary-500">Ver todos</a>
                    </div>
                    <div class="space-y-4">
                        <?php if (!empty($validadeProxima)): ?>
                            <?php foreach ($validadeProxima as $lote):?>
                                <div class="rounded-3xl bg-white/80 px-4 py-4 shadow">
                                    <p class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($lote['medicamento']); ?></p>
                                    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                        <span>Lote: <?php echo htmlspecialchars($lote['numero_lote']); ?></span>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-amber-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5"/></svg>
                                            <?php echo date('d/m/Y', strtotime($lote['data_validade'])); ?>
                                        </span>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-3 py-1 text-indigo-500">Estoque: <?php echo (int)$lote['quantidade_atual']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="rounded-3xl border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">
                                Sem lotes com vencimento próximo.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="glass-card p-6">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900">Checklist rápido</h3>
                            <p class="text-sm text-slate-500">As próximas ações sugeridas para manter a operação redonda.</p>
                        </div>
                    </div>
                    <ul class="space-y-4 text-sm text-slate-600">
                        <li class="flex items-start gap-3">
                            <span class="mt-1 h-2.5 w-2.5 rounded-full bg-primary-500"></span>
                            <span>Revise o carrinho de dispensação antes de iniciar atendimentos com o leitor de código.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-1 h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                            <span>Confira os medicamentos com estoque crítico e planeje nova entrada.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-1 h-2.5 w-2.5 rounded-full bg-rose-400"></span>
                            <span>Separe os lotes com validade inferior a 30 dias para priorizar a dispensação.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-1 h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                            <span>Crie um relatório rápido para a coordenação usando o link de relatórios.</span>
                        </li>
                    </ul>
                </div>
            </section>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
</body>
</html>
