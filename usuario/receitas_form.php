<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['usuario']);

$pageTitle = 'Nova Receita';
$receita = null;

if (isset($_GET['id'])) {
    $receita_id = (int) $_GET['id'];
    $db = getConnection();
    $stmt = $db->prepare('SELECT r.*, p.nome as paciente_nome FROM receitas r INNER JOIN pacientes p ON r.paciente_id = p.id WHERE r.id = ?');
    $stmt->execute([$receita_id]);
    $receita = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($receita) {
        $pageTitle = 'Editar Receita #' . $receita['numero_receita'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Controle de receitas">
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="<?php echo $pageTitle; ?> - Farmácia de Laje">
    <meta property="og:description" content="Sistema de gestão de farmácia">
    <meta property="og:type" content="website">
    <meta property="og:image" content="../images/logo.svg">
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
    <title><?php echo $pageTitle; ?> - Farmácia de Laje</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/admin_new.css">
</head>
<body class="admin-shell bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-50 min-h-screen">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="content-area">
        <div class="glass-card p-4 mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <span class="text-2xl">📄</span>
                        <?php echo $pageTitle; ?>
                    </h1>
                    <p class="text-xs text-gray-600 mt-1">Cadastre receitas para dispensação controlada de medicamentos</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="receitas.php" class="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-all font-medium">Cancelar</a>
                    <button type="submit" form="formReceita" class="px-8 py-2.5 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white rounded-lg shadow-lg hover:shadow-xl transition-all font-bold">✓ Cadastrar Receita</button>
                </div>
            </div>
        </div>

        <div class="glass-card p-6 max-w-4xl">
            <form id="formReceita" class="space-y-6">
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2"><span>📋</span> Dados da Receita</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2"><span class="text-red-500">*</span> Paciente</label>
                            <div class="relative">
                                <input type="text" id="pacienteSearch" placeholder="🔍 Digite o nome, CPF ou SUS..." class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm" autocomplete="off">
                                <input type="hidden" id="paciente_id" value="<?php echo $receita['paciente_id'] ?? ''; ?>">
                                <div id="pacienteResults" class="hidden absolute z-50 w-full mt-2 bg-white rounded-lg shadow-xl border border-gray-200 max-h-48 overflow-y-auto"></div>
                            </div>
                            <div id="pacienteInfo" class="hidden mt-3"></div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2"><span class="text-red-500">*</span> Número da Receita</label>
                            <input type="text" id="numero_receita" value="<?php echo htmlspecialchars($receita['numero_receita'] ?? ''); ?>" placeholder="Ex: REC-2025-0001" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2"><span class="text-red-500">*</span> Data de Apresentação</label>
                            <input type="date" id="data_emissao" value="<?php echo htmlspecialchars($receita['data_emissao'] ?? date('Y-m-d')); ?>" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm" required>
                            <p class="text-xs text-gray-500 mt-1">Data em que a receita foi apresentada</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2"><span class="text-red-500">*</span> Data de Validade</label>
                            <input type="date" id="data_validade" value="<?php echo htmlspecialchars($receita['data_validade'] ?? date('Y-m-d', strtotime('+1 year'))); ?>" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm" required>
                            <p class="text-xs text-gray-500 mt-1">Data até quando a receita é válida</p>
                        </div>
                    </div>
                </div>

                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2"><span>💊</span> Medicamento da Receita</h3>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2"><span class="text-red-500">*</span> Medicamento</label>
                            <div class="relative">
                                <input type="text" id="medicamentoSearch" placeholder="🔍 Digite o código ou nome do medicamento..." class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" autocomplete="off" disabled>
                                <input type="hidden" id="medicamento_id" value="<?php echo $receita['medicamento_id'] ?? ''; ?>">
                                <div id="medicamentoResults" class="hidden absolute z-50 w-full mt-2 bg-white rounded-lg shadow-xl border border-gray-200 max-h-48 overflow-y-auto"></div>
                            </div>
                            <div id="medicamentoInfo" class="hidden mt-3"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2"><span class="text-red-500">*</span> Quantidade por Retirada</label>
                                <input type="number" id="quantidade_por_retirada" value="<?php echo htmlspecialchars($receita['quantidade_por_retirada'] ?? ''); ?>" min="1" placeholder="Ex: 30" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" required>
                                <p class="text-xs text-gray-500 mt-1">Quantidade que o paciente leva por vez</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2"><span class="text-red-500">*</span> Número de Retiradas</label>
                                <input type="number" id="numero_retiradas" value="<?php echo htmlspecialchars($receita['numero_retiradas'] ?? ''); ?>" min="1" max="12" placeholder="Ex: 3" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" required>
                                <p class="text-xs text-gray-500 mt-1">Quantas vezes pode retirar (1-12)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2"><span class="text-red-500">*</span> Intervalo (dias)</label>
                                <select id="intervalo_dias" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" required>
                                    <option value="7" <?php echo isset($receita['intervalo_dias']) && (int) $receita['intervalo_dias'] === 7 ? 'selected' : ''; ?>>7 dias (1 semana)</option>
                                    <option value="15" <?php echo isset($receita['intervalo_dias']) && (int) $receita['intervalo_dias'] === 15 ? 'selected' : ''; ?>>15 dias</option>
                                    <option value="30" <?php echo !isset($receita['intervalo_dias']) || (int) $receita['intervalo_dias'] === 30 ? 'selected' : ''; ?>>30 dias (1 mês)</option>
                                    <option value="60" <?php echo isset($receita['intervalo_dias']) && (int) $receita['intervalo_dias'] === 60 ? 'selected' : ''; ?>>60 dias (2 meses)</option>
                                    <option value="90" <?php echo isset($receita['intervalo_dias']) && (int) $receita['intervalo_dias'] === 90 ? 'selected' : ''; ?>>90 dias (3 meses)</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Período mínimo entre retiradas</p>
                            </div>
                        </div>
                    </div>
                    <div id="previewRetiradas" class="mt-6 p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border-2 border-green-300 hidden">
                        <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2"><span>📅</span> Preview das Retiradas - Cronograma</h4>
                        <div id="listaRetiradas" class="space-y-2"></div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Observações</label>
                    <textarea id="observacoes" rows="3" placeholder="Informações adicionais sobre a receita (opcional)..." class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm resize-none"><?php echo htmlspecialchars($receita['observacoes'] ?? ''); ?></textarea>
                </div>
            </form>
        </div>
    </main>

    <script>
        window.RECEITAS_API_BASE = '../admin/api/';
        <?php if ($receita): ?>
        window.RECEITA_EDIT = <?php echo json_encode($receita, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        <?php endif; ?>
    </script>
    <script src="../admin/js/receitas_form.js"></script>
</body>
</html>
