<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['usuario']);

$conn = getConnection();
$paciente_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (empty($paciente_id)) {
    $_SESSION['error'] = 'Paciente não encontrado!';
    header('Location: pacientes.php');
    exit;
}

try {
    $stmt = $conn->prepare('SELECT * FROM pacientes WHERE id = ? AND ativo = 1');
    $stmt->execute([$paciente_id]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        $_SESSION['error'] = 'Paciente não encontrado!';
        header('Location: pacientes.php');
        exit;
    }

    $sqlReceitas = <<<SQL
        SELECT r.*, COUNT(ri.id) AS total_itens,
               SUM(CASE WHEN ri.quantidade_retirada < ri.quantidade_autorizada THEN 1 ELSE 0 END) AS itens_pendentes
        FROM receitas r
        LEFT JOIN receitas_itens ri ON ri.receita_id = r.id
        WHERE r.paciente_id = ?
          AND r.status = 'ativa'
          AND r.data_validade >= CURDATE()
        GROUP BY r.id
        ORDER BY r.data_emissao DESC
    SQL;
    $stmt = $conn->prepare($sqlReceitas);
    $stmt->execute([$paciente_id]);
    $receitas_ativas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sqlPendentes = <<<SQL
        SELECT ri.*, r.id AS receita_id, r.data_validade, m.nome AS medicamento_nome, m.descricao AS apresentacao
        FROM receitas_itens ri
        INNER JOIN receitas r ON r.id = ri.receita_id
        INNER JOIN medicamentos m ON m.id = ri.medicamento_id
        WHERE r.paciente_id = ?
          AND r.status = 'ativa'
          AND r.data_validade >= CURDATE()
          AND ri.quantidade_retirada < ri.quantidade_autorizada
        ORDER BY r.data_validade ASC
    SQL;
    $stmt = $conn->prepare($sqlPendentes);
    $stmt->execute([$paciente_id]);
    $itens_pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sqlHistorico = <<<SQL
        SELECT d.*,
               m.nome AS medicamento_nome,
               m.descricao AS apresentacao,
               u.nome AS usuario_nome,
               l.numero_lote AS lote_numero,
               CASE WHEN d.receita_item_id IS NOT NULL THEN 'Com Receita' ELSE 'Avulsa' END AS tipo_dispensacao
        FROM dispensacoes d
        INNER JOIN medicamentos m ON m.id = d.medicamento_id
        INNER JOIN lotes l ON l.id = d.lote_id
        INNER JOIN usuarios u ON u.id = d.usuario_id
        WHERE d.paciente_id = ?
        ORDER BY d.data_dispensacao DESC
        LIMIT 50
    SQL;
    $stmt = $conn->prepare($sqlHistorico);
    $stmt->execute([$paciente_id]);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare('SELECT COUNT(*) FROM dispensacoes WHERE paciente_id = ?');
    $stmt->execute([$paciente_id]);
    $total_dispensacoes = (int) $stmt->fetchColumn();

    $stmt = $conn->prepare('SELECT COUNT(DISTINCT medicamento_id) FROM dispensacoes WHERE paciente_id = ?');
    $stmt->execute([$paciente_id]);
    $medicamentos_unicos = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Erro ao buscar histórico: ' . $e->getMessage();
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
    <meta name="description" content="Histórico do paciente - Farmácia de Laje">
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="Histórico do paciente - Farmácia de Laje">
    <meta property="og:type" content="website">
    <meta property="og:image" content="../images/logo.svg">
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
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
</head>
<body class="admin-shell">
    <div class="flex min-h-screen">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-12 lg:py-10 space-y-8">
            <header class="space-y-2">
                <nav class="flex items-center gap-2 text-xs sm:text-sm text-slate-500">
                    <a href="pacientes.php" class="hover:text-primary-600 transition">Pacientes</a>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg>
                    <span class="text-slate-900 font-medium">Histórico</span>
                </nav>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($paciente['nome']); ?></h1>
                        <div class="flex flex-wrap gap-4 mt-2 text-sm text-slate-500">
                            <?php if (!empty($paciente['cpf'])): ?>
                                <span>CPF: <?php echo htmlspecialchars($paciente['cpf']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($paciente['cartao_sus'])): ?>
                                <span>Cartão SUS: <?php echo htmlspecialchars($paciente['cartao_sus']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                        <span class="font-semibold text-slate-600 block">Última atualização</span>
                        <span><?php echo date('d/m/Y H:i'); ?></span>
                    </div>
                </div>
            </header>

            <section class="grid gap-4 lg:grid-cols-3">
                <div class="glass-card p-6">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total de dispensações</span>
                    <p class="mt-2 text-3xl font-bold text-primary-600"><?php echo $total_dispensacoes; ?></p>
                    <p class="mt-2 text-sm text-slate-500">Quantidade de retiradas registradas para este paciente.</p>
                </div>
                <div class="glass-card p-6">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Medicamentos diferentes</span>
                    <p class="mt-2 text-3xl font-bold text-emerald-600"><?php echo $medicamentos_unicos; ?></p>
                    <p class="mt-2 text-sm text-slate-500">Variedade de medicamentos dispensados ao paciente.</p>
                </div>
                <div class="glass-card p-6">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Receitas ativas</span>
                    <p class="mt-2 text-3xl font-bold text-amber-600"><?php echo count($receitas_ativas); ?></p>
                    <p class="mt-2 text-sm text-slate-500">Receitas dentro da validade com itens disponíveis.</p>
                </div>
            </section>

            <?php if (!empty($itens_pendentes)): ?>
                <section class="glass-card p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Itens pendentes</h2>
                        <span class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400"><?php echo count($itens_pendentes); ?> itens</span>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($itens_pendentes as $item): ?>
                            <div class="rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 flex flex-col gap-1 text-sm text-amber-800">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <strong><?php echo htmlspecialchars($item['medicamento_nome']); ?></strong>
                                    <span class="inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                                        Pendentes: <?php echo (int) ($item['quantidade_autorizada'] - $item['quantidade_retirada']); ?>
                                    </span>
                                </div>
                                <span class="text-xs text-amber-700/80">Validade da receita: <?php echo date('d/m/Y', strtotime($item['data_validade'])); ?></span>
                                <span class="text-xs text-amber-700/80">Apresentação: <?php echo htmlspecialchars($item['apresentacao'] ?? '—'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($receitas_ativas)): ?>
                <section class="glass-card p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Receitas em vigência</h2>
                        <span class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400"><?php echo count($receitas_ativas); ?> receitas</span>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($receitas_ativas as $receita): ?>
                            <article class="rounded-2xl border border-slate-200 bg-white px-5 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h3 class="text-base font-semibold text-slate-900">Receita #<?php echo htmlspecialchars($receita['numero_receita'] ?: $receita['id']); ?></h3>
                                        <p class="text-xs text-slate-500">Emitida em <?php echo date('d/m/Y', strtotime($receita['data_emissao'])); ?> • Validade <?php echo date('d/m/Y', strtotime($receita['data_validade'])); ?></p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                        <?php echo $receita['total_itens']; ?> itens | <?php echo $receita['itens_pendentes']; ?> pendentes
                                    </span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="glass-card p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Histórico de dispensações</h2>
                    <span class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400"><?php echo count($historico); ?> registros</span>
                </div>
                <?php if (!empty($historico)): ?>
                    <div class="space-y-3">
                        <?php foreach ($historico as $registro): ?>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h3 class="text-base font-semibold text-slate-900"><?php echo htmlspecialchars($registro['medicamento_nome']); ?></h3>
                                        <p class="text-xs text-slate-500">
                                            Retirado em <?php echo date('d/m/Y H:i', strtotime($registro['data_dispensacao'])); ?> por <?php echo htmlspecialchars($registro['usuario_nome']); ?>
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                                        <?php echo $registro['quantidade']; ?> unidade(s)
                                    </span>
                                </div>
                                <div class="mt-2 grid gap-4 text-xs text-slate-500 sm:grid-cols-3">
                                    <span>Lote: <?php echo htmlspecialchars($registro['lote_numero']); ?></span>
                                    <span>Tipo: <?php echo htmlspecialchars($registro['tipo_dispensacao']); ?></span>
                                    <?php if (!empty($registro['observacoes'])): ?>
                                        <span>Obs.: <?php echo htmlspecialchars($registro['observacoes']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="rounded-2xl border border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-500">
                        Nenhuma dispensação registrada para este paciente nas últimas movimentações.
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
