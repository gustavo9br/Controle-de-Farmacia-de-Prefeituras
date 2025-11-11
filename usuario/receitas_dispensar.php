<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['usuario']);

$conn = getConnection();
$receita_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($receita_id <= 0) {
    header('Location: receitas.php');
    exit;
}

try {
    $sql = "SELECT r.*, p.id as paciente_id, p.nome as paciente_nome, p.cpf as paciente_cpf, p.cartao_sus as paciente_sus
            FROM receitas r
            INNER JOIN pacientes p ON p.id = r.paciente_id
            WHERE r.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$receita_id]);
    $receita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receita) {
        header('Location: receitas.php');
        exit;
    }

    $sql = "SELECT ri.*, m.nome as medicamento_nome, m.codigo_barras, m.estoque_atual,
                    (ri.quantidade_autorizada - ri.quantidade_retirada) as quantidade_disponivel,
                    (SELECT COUNT(*) FROM receitas_retiradas rr WHERE rr.receita_item_id = ri.id) as total_retiradas
            FROM receitas_itens ri
            INNER JOIN medicamentos m ON m.id = ri.medicamento_id
            WHERE ri.receita_id = ?
            ORDER BY m.nome";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$receita_id]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Erro ao buscar receita: ' . $e->getMessage();
    $receita = null;
    $itens = [];
}

$pageTitle = 'Dispensar Receita #' . (!empty($receita['numero_receita']) ? $receita['numero_receita'] : $receita_id);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Dispensação de receitas">
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?> - Farmácia de Laje">
    <meta property="og:description" content="Sistema de gestão de farmácia">
    <meta property="og:type" content="website">
    <meta property="og:image" content="../images/logo.svg">
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Farmácia de Laje</title>
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
                        <span class="text-2xl">💊</span>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <p class="text-xs text-gray-600 mt-1">Realize a dispensação dos medicamentos desta receita</p>
                </div>
                <a href="receitas.php" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-all text-sm font-medium">← Voltar</a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="glass-card p-4 mb-4 bg-red-50 border border-red-200">
                <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($receita): ?>
            <div class="glass-card p-6 mb-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2"><span>👤</span> Dados do Paciente</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <p class="text-xs text-gray-600 mb-1">Nome</p>
                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($receita['paciente_nome']); ?></p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4">
                        <p class="text-xs text-gray-600 mb-1">CPF</p>
                        <p class="font-semibold text-gray-900"><?php echo $receita['paciente_cpf'] ? htmlspecialchars($receita['paciente_cpf']) : 'N/A'; ?></p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4">
                        <p class="text-xs text-gray-600 mb-1">Cartão SUS</p>
                        <p class="font-semibold text-gray-900"><?php echo $receita['paciente_sus'] ? htmlspecialchars($receita['paciente_sus']) : 'N/A'; ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-card p-6 mb-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2"><span>📋</span> Dados da Receita</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-green-50 rounded-lg p-4">
                        <p class="text-xs text-gray-600 mb-1">Número</p>
                        <p class="font-semibold text-gray-900"><?php echo !empty($receita['numero_receita']) ? htmlspecialchars($receita['numero_receita']) : 'N/A'; ?></p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <p class="text-xs text-gray-600 mb-1">Data de Emissão</p>
                        <p class="font-semibold text-gray-900"><?php echo date('d/m/Y', strtotime($receita['data_emissao'])); ?></p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <p class="text-xs text-gray-600 mb-1">Data de Validade</p>
                        <p class="font-semibold text-gray-900"><?php echo date('d/m/Y', strtotime($receita['data_validade'])); ?></p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <p class="text-xs text-gray-600 mb-1">Status</p>
                        <?php
                        $statusClass = 'bg-gray-200 text-gray-800';
                        $statusText = ucfirst($receita['status']);
                        switch ($receita['status']) {
                            case 'ativa':
                                $statusClass = 'bg-green-100 text-green-800';
                                break;
                            case 'finalizada':
                                $statusClass = 'bg-blue-100 text-blue-800';
                                break;
                            case 'vencida':
                                $statusClass = 'bg-red-100 text-red-800';
                                break;
                            case 'cancelada':
                                $statusClass = 'bg-gray-200 text-gray-700';
                                break;
                        }
                        ?>
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                    </div>
                </div>

                <?php if (!empty($receita['observacoes'])): ?>
                    <div class="mt-4 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                        <p class="text-xs text-gray-600 mb-1">Observações:</p>
                        <p class="text-sm text-gray-800"><?php echo htmlspecialchars($receita['observacoes']); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2"><span>💊</span> Medicamentos Autorizados</h3>
                <?php if (!empty($itens)): ?>
                    <div class="space-y-4">
                        <?php foreach ($itens as $item): ?>
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-5 border-2 border-blue-200">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <h4 class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($item['medicamento_nome']); ?></h4>
                                        <?php if ($item['codigo_barras']): ?>
                                            <p class="text-sm text-gray-600 mt-1">Código: <?php echo htmlspecialchars($item['codigo_barras']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="inline-block px-3 py-1 bg-white rounded-full text-sm font-bold text-gray-700 border-2 border-blue-300">Estoque: <?php echo $item['estoque_atual']; ?></span>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                    <div class="bg-white rounded-lg p-3 border border-blue-200">
                                        <p class="text-xs text-gray-600 mb-1">Qtd. Autorizada</p>
                                        <p class="text-xl font-bold text-blue-600"><?php echo $item['quantidade_autorizada']; ?></p>
                                    </div>
                                    <div class="bg-white rounded-lg p-3 border border-blue-200">
                                        <p class="text-xs text-gray-600 mb-1">Qtd. Retirada</p>
                                        <p class="text-xl font-bold text-green-600"><?php echo $item['quantidade_retirada']; ?></p>
                                    </div>
                                    <div class="bg-white rounded-lg p-3 border border-blue-200">
                                        <p class="text-xs text-gray-600 mb-1">Disponível</p>
                                        <p class="text-xl font-bold text-orange-600"><?php echo $item['quantidade_disponivel']; ?></p>
                                    </div>
                                    <div class="bg-white rounded-lg p-3 border border-blue-200">
                                        <p class="text-xs text-gray-600 mb-1">Retiradas</p>
                                        <p class="text-xl font-bold text-purple-600"><?php echo $item['total_retiradas']; ?>x</p>
                                    </div>
                                </div>
                                <?php if ($item['quantidade_disponivel'] > 0 && $receita['status'] === 'ativa'): ?>
                                    <div class="mt-4">
                                        <button onclick="abrirModalDispensacao(<?php echo $item['id']; ?>, '<?php echo addslashes($item['medicamento_nome']); ?>', <?php echo $item['quantidade_disponivel']; ?>, <?php echo $item['medicamento_id']; ?>)" class="w-full px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white rounded-lg font-bold shadow-lg hover:shadow-xl transition-all">✓ Dispensar Medicamento</button>
                                    </div>
                                <?php elseif ($item['quantidade_disponivel'] <= 0): ?>
                                    <div class="mt-4 p-3 bg-gray-100 rounded-lg text-center">
                                        <p class="text-sm text-gray-600 font-medium">✓ Medicamento totalmente dispensado</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10 text-gray-500">
                        <p>Nenhum medicamento cadastrado nesta receita.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <div id="modalDispensacao" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-2xl w-full mx-4 shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Dispensar Medicamento</h3>
            <form id="formDispensacao" class="space-y-4">
                <input type="hidden" id="receita_item_id" name="receita_item_id">
                <input type="hidden" id="medicamento_id" name="medicamento_id">
                <input type="hidden" name="receita_id" value="<?php echo $receita_id; ?>">
                <input type="hidden" name="paciente_id" value="<?php echo $receita['paciente_id'] ?? ''; ?>">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-600">Medicamento</p>
                    <p class="text-lg font-bold text-gray-900" id="modal_medicamento_nome"></p>
                    <p class="text-sm text-gray-600 mt-2">Quantidade disponível: <span id="modal_qtd_disponivel" class="font-bold text-blue-600"></span></p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><span class="text-red-500">*</span> Selecionar Lote</label>
                    <select id="lote_id" name="lote_id" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Carregando lotes...</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><span class="text-red-500">*</span> Quantidade a Dispensar</label>
                    <input type="number" id="quantidade" name="quantidade" min="1" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Observações</label>
                    <textarea id="observacoes" name="observacoes" rows="3" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="fecharModal()" class="flex-1 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium transition-all">Cancelar</button>
                    <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white rounded-lg font-bold shadow-lg hover:shadow-xl transition-all">✓ Confirmar Dispensação</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const API_BASE = '../admin/api/';

        function abrirModalDispensacao(receitaItemId, medicamentoNome, qtdDisponivel, medicamentoId) {
            document.getElementById('receita_item_id').value = receitaItemId;
            document.getElementById('medicamento_id').value = medicamentoId;
            document.getElementById('modal_medicamento_nome').textContent = medicamentoNome;
            document.getElementById('modal_qtd_disponivel').textContent = qtdDisponivel;
            document.getElementById('quantidade').max = qtdDisponivel;
            document.getElementById('quantidade').value = Math.min(1, qtdDisponivel);
            carregarLotes(medicamentoId);
            document.getElementById('modalDispensacao').classList.remove('hidden');
        }

        function fecharModal() {
            document.getElementById('modalDispensacao').classList.add('hidden');
            document.getElementById('formDispensacao').reset();
        }

        async function carregarLotes(medicamentoId) {
            const select = document.getElementById('lote_id');
            select.innerHTML = '<option value="">Carregando...</option>';
            try {
                const response = await fetch(`${API_BASE}buscar_lotes.php?medicamento_id=${medicamentoId}`);
                const data = await response.json();
                if (data.success && data.lotes.length > 0) {
                    select.innerHTML = '<option value="">Selecione um lote</option>';
                    data.lotes.forEach((lote) => {
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

        document.getElementById('formDispensacao').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            try {
                const response = await fetch(`${API_BASE}dispensar_receita.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    alert('Medicamento dispensado com sucesso!');
                    window.location.reload();
                } else {
                    alert('Erro: ' + result.message);
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao processar dispensação');
            }
        });

        document.getElementById('modalDispensacao').addEventListener('click', (e) => {
            if (e.target.id === 'modalDispensacao') {
                fecharModal();
            }
        });
    </script>
</body>
</html>
