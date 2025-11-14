<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();
$paciente_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($paciente_id)) {
    $_SESSION['error'] = "Paciente não encontrado!";
    header('Location: pacientes.php');
    exit;
}

try {
    // Buscar dados do paciente
    $stmt = $conn->prepare("SELECT * FROM pacientes WHERE id = ? AND ativo = 1");
    $stmt->execute([$paciente_id]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paciente) {
        $_SESSION['error'] = "Paciente não encontrado!";
        header('Location: pacientes.php');
        exit;
    }
    
    // Buscar receitas ativas
    $stmt = $conn->prepare("
        SELECT r.*, COUNT(ri.id) as total_itens,
               SUM(CASE WHEN COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) < ri.quantidade_autorizada THEN 1 ELSE 0 END) as itens_pendentes
        FROM receitas r
        LEFT JOIN receitas_itens ri ON ri.receita_id = r.id
        WHERE r.paciente_id = ?
        AND r.status = 'ativa'
        AND r.data_validade >= CURDATE()
        GROUP BY r.id
        ORDER BY r.data_emissao DESC
    ");
    $stmt->execute([$paciente_id]);
    $receitas_ativas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar itens pendentes detalhados
    $stmt = $conn->prepare("
        SELECT ri.*, r.id as receita_id, r.data_validade, m.nome as medicamento_nome, m.descricao as apresentacao
        FROM receitas_itens ri
        INNER JOIN receitas r ON r.id = ri.receita_id
        INNER JOIN medicamentos m ON m.id = ri.medicamento_id
        WHERE r.paciente_id = ?
        AND r.status = 'ativa'
        AND r.data_validade >= CURDATE()
        AND COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) < ri.quantidade_autorizada
        ORDER BY r.data_validade ASC
    ");
    $stmt->execute([$paciente_id]);
    $itens_pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar histórico completo de dispensações
    $stmt = $conn->prepare("
        SELECT d.*, 
               m.nome as medicamento_nome, 
               m.descricao as apresentacao,
               u.nome as usuario_nome,
               l.numero_lote as lote_numero,
               CASE 
                   WHEN d.receita_item_id IS NOT NULL THEN 'Com Receita'
                   ELSE 'Avulsa'
               END as tipo_dispensacao
        FROM dispensacoes d
        INNER JOIN medicamentos m ON m.id = d.medicamento_id
        INNER JOIN lotes l ON l.id = d.lote_id
        INNER JOIN usuarios u ON u.id = d.usuario_id
        WHERE d.paciente_id = ?
        ORDER BY d.data_dispensacao DESC
        LIMIT 50
    ");
    $stmt->execute([$paciente_id]);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas
    $stmt = $conn->prepare("SELECT COUNT(*) FROM dispensacoes WHERE paciente_id = ?");
    $stmt->execute([$paciente_id]);
    $total_dispensacoes = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT medicamento_id) FROM dispensacoes WHERE paciente_id = ?");
    $stmt->execute([$paciente_id]);
    $medicamentos_unicos = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erro ao buscar histórico: " . $e->getMessage();
    header('Location: pacientes.php');
    exit;
}

$pageTitle = 'Histórico do Paciente';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Controle de medicamentos, lotes, pacientes e receitas">
    <meta name="keywords" content="farmácia, medicamentos, gestão, controle de estoque, pacientes, histórico">
    <meta name="author" content="Sistema Farmácia">
    <meta name="robots" content="noindex, nofollow">
    
    <?php 
    $ogTitle = htmlspecialchars($pageTitle) . ' - Gov Farma';
    $ogDescription = 'Gov Farma - Histórico completo do paciente. Receitas ativas, dispensações e medicamentos pendentes.';
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
    <div class="flex min-h-screen">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <main class="flex-1 px-6 py-10 lg:px-12 space-y-8">
            <header class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <a href="pacientes.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white hover:bg-gray-50 text-slate-700 font-medium shadow-sm hover:shadow transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Voltar
                    </a>
                    <a href="pacientes_form.php?id=<?php echo $paciente_id; ?>" class="inline-flex items-center gap-2 rounded-full bg-white px-5 py-2 text-sm text-slate-600 font-semibold shadow hover:shadow-lg transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487 19.5 7.125m-2.638-2.638L9.75 14.25 7.5 16.5m12-9-5.25-5.25M7.5 16.5v2.25h2.25L18.75 9"/></svg>
                        Editar dados
                    </a>
                </div>
                <div>
                    <h1 class="text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($paciente['nome']); ?></h1>
                    <div class="flex gap-4 mt-2 text-slate-600">
                        <?php if (!empty($paciente['cpf'])): ?>
                            <span class="text-sm">CPF: <?php echo htmlspecialchars($paciente['cpf']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($paciente['cartao_sus'])): ?>
                            <span class="text-sm">Cartão SUS: <?php echo htmlspecialchars($paciente['cartao_sus']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <!-- Estatísticas -->
            <section class="grid gap-4 lg:grid-cols-3">
                <div class="glass-card p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Total de Dispensações</p>
                            <p class="text-3xl font-bold text-primary-600 mt-2"><?php echo $total_dispensacoes; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-primary-100 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/></svg>
                        </div>
                    </div>
                </div>

                <div class="glass-card p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Medicamentos Diferentes</p>
                            <p class="text-3xl font-bold text-emerald-600 mt-2"><?php echo $medicamentos_unicos; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 3.75a6 6 0 1 0 0 12 6 6 0 0 0 0-12z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 9.75a5.25 5.25 0 0 0-10.5 0v4.5a5.25 5.25 0 1 0 10.5 0z"/></svg>
                        </div>
                    </div>
                </div>

                <div class="glass-card p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Receitas Ativas</p>
                            <p class="text-3xl font-bold text-amber-600 mt-2"><?php echo count($receitas_ativas); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Itens Pendentes -->
            <?php if (count($itens_pendentes) > 0): ?>
                <section class="glass-card p-6 space-y-4">
                    <div class="flex items-center gap-2 border-b border-white/60 pb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h2 class="text-lg font-semibold text-slate-900">Medicamentos Pendentes</h2>
                        <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-700 px-3 py-1 text-xs font-semibold"><?php echo count($itens_pendentes); ?> pendente(s)</span>
                    </div>

                    <div class="space-y-3">
                        <?php foreach ($itens_pendentes as $item): ?>
                            <div class="rounded-xl border-2 border-amber-200 bg-amber-50/50 p-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-slate-900"><?php echo htmlspecialchars($item['medicamento_nome']); ?></h4>
                                        <p class="text-sm text-slate-600 mt-1"><?php echo htmlspecialchars($item['apresentacao'] ?? ''); ?></p>
                                        <div class="flex gap-4 mt-3 text-sm">
                                            <span class="text-slate-600">
                                                <strong class="text-amber-600"><?php 
                                                    $stmt_ret = $conn->prepare("SELECT COALESCE(SUM(quantidade), 0) FROM receitas_retiradas WHERE receita_item_id = ?");
                                                    $stmt_ret->execute([$item['id']]);
                                                    $total_retiradas = $stmt_ret->fetchColumn();
                                                    echo $item['quantidade_autorizada'] - $total_retiradas; 
                                                ?></strong> 
                                                de <?php echo $item['quantidade_autorizada']; ?> pendente(s)
                                            </span>
                                            <span class="text-slate-500">
                                                Validade: <?php echo formatarData($item['data_validade']); ?>
                                            </span>
                                        </div>
                                        <?php if ($item['ultima_retirada']): ?>
                                            <p class="text-xs text-slate-400 mt-2">
                                                Última retirada: <?php echo formatarData($item['ultima_retirada']); ?>
                                                (Intervalo: <?php echo $item['intervalo_dias']; ?> dias)
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <a href="index.php" class="inline-flex items-center gap-1 rounded-full bg-primary-600 px-4 py-2 text-xs text-white font-semibold hover:bg-primary-500 transition">
                                        Dispensar
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Receitas Ativas -->
            <?php if (count($receitas_ativas) > 0): ?>
                <section class="glass-card p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900 border-b border-white/60 pb-3">Receitas Ativas</h2>
                    <div class="space-y-3">
                        <?php foreach ($receitas_ativas as $receita): ?>
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-semibold text-slate-900">Receita #<?php echo $receita['id']; ?></h4>
                                    <span class="text-xs text-slate-500">Validade: <?php echo formatarData($receita['data_validade']); ?></span>
                                </div>
                                <div class="flex gap-4 text-sm text-slate-600">
                                    <span><?php echo $receita['total_itens']; ?> medicamento(s)</span>
                                    <?php if ($receita['itens_pendentes'] > 0): ?>
                                        <span class="text-amber-600 font-medium"><?php echo $receita['itens_pendentes']; ?> pendente(s)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Histórico de Dispensações -->
            <section class="glass-card p-0 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-white/60 bg-white/70">
                    <h2 class="text-lg font-semibold text-slate-900">Histórico de Dispensações</h2>
                    <span class="text-sm text-slate-500">Últimas 50 dispensações</span>
                </div>

                <?php if (count($historico) > 0): ?>
                    <!-- Desktop -->
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-white/60">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-6 py-3 text-left">Data</th>
                                    <th class="px-6 py-3 text-left">Medicamento</th>
                                    <th class="px-6 py-3 text-left">Quantidade</th>
                                    <th class="px-6 py-3 text-left">Lote</th>
                                    <th class="px-6 py-3 text-left">Tipo</th>
                                    <th class="px-6 py-3 text-left">Dispensado por</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white/80">
                                <?php foreach ($historico as $disp): ?>
                                    <tr class="text-sm hover:bg-primary-50/50 transition">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php echo date('d/m/Y H:i', strtotime($disp['data_dispensacao'])); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div>
                                                <p class="font-medium text-slate-900"><?php echo htmlspecialchars($disp['medicamento_nome']); ?></p>
                                                <p class="text-xs text-slate-500"><?php echo htmlspecialchars($disp['apresentacao'] ?? ''); ?></p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-semibold text-primary-600">
                                            <?php echo $disp['quantidade']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-slate-600 font-mono text-xs">
                                            <?php echo htmlspecialchars($disp['lote_numero']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo $disp['tipo'] === 'receita' ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-600'; ?>">
                                                <?php echo htmlspecialchars($disp['tipo_dispensacao']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600">
                                            <?php echo htmlspecialchars($disp['usuario_nome']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile -->
                    <div class="lg:hidden divide-y divide-slate-100">
                        <?php foreach ($historico as $disp): ?>
                            <div class="p-4 bg-white/80">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($disp['medicamento_nome']); ?></p>
                                        <p class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($disp['apresentacao'] ?? ''); ?></p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo $disp['tipo'] === 'receita' ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-600'; ?>">
                                        <?php echo htmlspecialchars($disp['tipo_dispensacao']); ?>
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <span class="text-slate-500">Data:</span>
                                        <span class="ml-1 text-slate-700"><?php echo date('d/m/Y H:i', strtotime($disp['data_dispensacao'])); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500">Quantidade:</span>
                                        <span class="ml-1 font-semibold text-primary-600"><?php echo $disp['quantidade']; ?></span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500">Lote:</span>
                                        <span class="ml-1 text-slate-700 font-mono"><?php echo htmlspecialchars($disp['lote_numero']); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500">Por:</span>
                                        <span class="ml-1 text-slate-700"><?php echo htmlspecialchars($disp['usuario_nome']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/></svg>
                        <p class="text-sm">Nenhuma dispensação registrada para este paciente.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
</body>
</html>
