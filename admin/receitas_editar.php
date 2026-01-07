<?php
require_once '../includes/auth.php';
requireAdmin();

$receita_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($receita_id <= 0) {
    $_SESSION['error'] = 'ID da receita não informado';
    header('Location: receitas.php');
    exit;
}

$conn = getConnection();

// Buscar dados da receita
$stmt = $conn->prepare("SELECT r.*, p.nome as paciente_nome, p.cpf as paciente_cpf, p.cartao_sus as paciente_sus FROM receitas r INNER JOIN pacientes p ON r.paciente_id = p.id WHERE r.id = ?");
$stmt->execute([$receita_id]);
$receita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receita) {
    $_SESSION['error'] = 'Receita não encontrada';
    header('Location: receitas.php');
    exit;
}

// Buscar todos os itens da receita com informações de dispensações
$stmt = $conn->prepare("
    SELECT 
        ri.id,
        ri.receita_id,
        ri.medicamento_id,
        ri.quantidade_autorizada,
        ri.intervalo_dias,
        ri.observacoes,
        m.nome as medicamento_nome,
        m.codigo_barras,
        m.estoque_atual,
        COALESCE((SELECT COUNT(*) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) as tem_dispensacoes,
        COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) as total_retiradas
    FROM receitas_itens ri
    INNER JOIN medicamentos m ON m.id = ri.medicamento_id
    WHERE ri.receita_id = ?
    ORDER BY ri.id
");
$stmt->execute([$receita_id]);
$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separar itens com e sem dispensações
$itens_com_dispensacoes = [];
$itens_sem_dispensacoes = [];

foreach ($itens as $item) {
    if ($item['tem_dispensacoes'] > 0) {
        $itens_com_dispensacoes[] = $item;
    } else {
        $itens_sem_dispensacoes[] = $item;
    }
}

// Pegar o primeiro item para preencher o formulário
$item = !empty($itens) ? $itens[0] : null;

// Buscar média de quantidade apenas dos itens sem dispensações (ou todos se não houver dispensações)
$stmt = $conn->prepare("
    SELECT 
        AVG(quantidade_autorizada) as quantidade_media,
        MAX(intervalo_dias) as intervalo_dias
    FROM receitas_itens
    WHERE receita_id = ?
");
$stmt->execute([$receita_id]);
$medias = $stmt->fetch(PDO::FETCH_ASSOC);

if ($item && $medias) {
    $item['quantidade_media'] = (int)$medias['quantidade_media'];
    $item['intervalo_dias'] = (int)$medias['intervalo_dias'];
}

// Contar total de itens (retiradas)
$numero_retiradas = count($itens) ?: 1;

$pageTitle = "Editar Receita #" . $receita['numero_receita'];
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
    $ogTitle = $pageTitle . ' - Gov Farma';
    $ogDescription = 'Gov Farma - Edição de receitas médicas. Prescrições com controle de retiradas planejadas.';
    include '../includes/og_meta.php'; 
    ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="../images/logo.svg">
    <link rel="apple-touch-icon" href="../images/logo.svg">
    
    <?php include '../includes/pwa_head.php'; ?>
    
    <title><?php echo $pageTitle; ?> - Farmácia Popular</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/admin_new.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="admin-shell bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-50 min-h-screen">
    
    <?php include 'includes/sidebar.php'; ?>

    <main class="content-area">
        <div class="glass-card p-4 mb-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <h1 class="text-lg sm:text-xl font-bold text-gray-800 flex items-center gap-2">
                        <span class="text-xl sm:text-2xl">📄</span>
                        <span class="break-words"><?php echo $pageTitle; ?></span>
                    </h1>
                    <p class="text-xs text-gray-600 mt-1">Edite os dados da receita</p>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
                    <a href="receitas_dispensar.php?id=<?php echo $receita_id; ?>" class="flex-1 sm:flex-none px-4 sm:px-6 py-2 sm:py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-all font-medium text-sm sm:text-base text-center">
                        Cancelar
                    </a>
                    <button type="submit" form="formReceita" class="flex-1 sm:flex-none px-4 sm:px-8 py-2 sm:py-2.5 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white rounded-lg shadow-lg hover:shadow-xl transition-all font-bold text-sm sm:text-base">
                        ✓ Salvar
                    </button>
                </div>
            </div>
        </div>

        <div class="glass-card p-6">
            <form id="formReceita" class="space-y-6">
                <input type="hidden" id="receita_id" value="<?php echo $receita_id; ?>">
                
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span>📋</span> Dados da Receita
                    </h3>
                    
                    <!-- Paciente -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <span class="text-red-500">*</span> Paciente
                        </label>
                        <div class="space-y-4">
                            <div class="relative">
                                <input type="text" id="pacienteSearch" value="<?php echo htmlspecialchars($receita['paciente_nome']); ?>" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm bg-gray-50" readonly>
                                <input type="hidden" id="paciente_id" value="<?php echo $receita['paciente_id']; ?>">
                            </div>
                            <div id="pacienteInfo" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($receita['paciente_nome']); ?></span>
                                </div>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <div>CPF: <?php echo $receita['paciente_cpf'] ? htmlspecialchars($receita['paciente_cpf']) : 'N/A'; ?></div>
                                    <?php if ($receita['paciente_sus']): ?>
                                        <div>Cartão SUS: <?php echo htmlspecialchars($receita['paciente_sus']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tipo de Receita e Número -->
                    <div class="mb-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Tipo de Receita
                                </label>
                                <select id="tipo_receita" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm" required>
                                    <option value="azul" <?php echo $receita['tipo_receita'] === 'azul' ? 'selected' : ''; ?>>Azul</option>
                                    <option value="branca" <?php echo $receita['tipo_receita'] === 'branca' ? 'selected' : ''; ?>>Branca</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Tipo de receita médica</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Número da Receita
                                </label>
                                <input type="text" id="numero_receita" value="<?php echo htmlspecialchars($receita['numero_receita']); ?>" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm <?php echo $receita['tipo_receita'] === 'branca' ? 'bg-gray-50' : ''; ?>" <?php echo $receita['tipo_receita'] === 'branca' ? 'readonly' : 'required'; ?>>
                                <p class="text-xs text-gray-500 mt-1" id="numero_receita_help">
                                    <?php echo $receita['tipo_receita'] === 'branca' ? 'Número gerado automaticamente' : 'Digite o número da receita azul'; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Datas -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <span class="text-red-500">*</span> Data de Apresentação
                            </label>
                            <input type="date" id="data_emissao" value="<?php echo date('Y-m-d', strtotime($receita['data_emissao'])); ?>" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm" required>
                            <p class="text-xs text-gray-500 mt-1">Data em que a receita foi apresentada</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <span class="text-red-500">*</span> Data de Validade
                            </label>
                            <input type="date" id="data_validade" value="<?php echo date('Y-m-d', strtotime($receita['data_validade'])); ?>" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm" required>
                            <p class="text-xs text-gray-500 mt-1">Data até quando a receita é válida</p>
                        </div>
                    </div>
                </div>

                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span>💊</span> Medicamento da Receita
                    </h3>
                    
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <span class="text-red-500">*</span> Medicamento
                            </label>
                            <div class="relative">
                                <input type="text" id="medicamentoSearch" value="<?php echo $item ? htmlspecialchars($item['medicamento_nome']) : ''; ?>" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm bg-gray-50" readonly>
                                <input type="hidden" id="medicamento_id" value="<?php echo $item ? $item['medicamento_id'] : ''; ?>">
                            </div>
                            <?php if ($item): ?>
                                <div id="medicamentoInfo" class="mt-3 bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                            </svg>
                                            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($item['medicamento_nome']); ?></span>
                                        </div>
                                        <span class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                            Estoque: <?php echo $item['estoque_atual'] ?? 0; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-2">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Quantidade por Retirada
                                </label>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="alterarQuantidadeRetirada(-1)" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-bold transition-all text-sm">-1</button>
                                    <input type="number" id="quantidade_por_retirada" min="1" value="<?php echo $item ? (int)$item['quantidade_media'] : 1; ?>" placeholder="Ex: 30" class="flex-1 px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm text-center" required>
                                    <button type="button" onclick="alterarQuantidadeRetirada(1)" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-bold transition-all text-sm">+1</button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Quantidade que o paciente leva por vez</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Número de Retiradas
                                </label>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="alterarNumeroRetiradas(-1)" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-bold transition-all text-sm">-1</button>
                                    <input type="number" id="numero_retiradas" min="1" max="12" value="<?php echo $numero_retiradas; ?>" placeholder="Ex: 3" class="flex-1 px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm text-center" required>
                                    <button type="button" onclick="alterarNumeroRetiradas(1)" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-bold transition-all text-sm">+1</button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Quantas vezes pode retirar (1-12)</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Intervalo (dias)
                                </label>
                                <select id="intervalo_dias" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" required>
                                    <option value="7" <?php echo ($item && $item['intervalo_dias'] == 7) ? 'selected' : ''; ?>>7 dias (1 semana)</option>
                                    <option value="15" <?php echo ($item && $item['intervalo_dias'] == 15) ? 'selected' : ''; ?>>15 dias</option>
                                    <option value="30" <?php echo (!$item || $item['intervalo_dias'] == 30) ? 'selected' : ''; ?>>30 dias (1 mês)</option>
                                    <option value="60" <?php echo ($item && $item['intervalo_dias'] == 60) ? 'selected' : ''; ?>>60 dias (2 meses)</option>
                                    <option value="90" <?php echo ($item && $item['intervalo_dias'] == 90) ? 'selected' : ''; ?>>90 dias (3 meses)</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Período mínimo entre retiradas</p>
                            </div>
                        </div>
                    </div>

                    <div id="previewRetiradas" class="mt-6 p-3 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border border-green-200 hidden">
                        <h4 class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Preview das Retiradas
                        </h4>
                        <div id="listaRetiradas" class="flex flex-wrap gap-2"></div>
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
        // Passar dados das dispensações para JavaScript
        const dispensacoesRealizadas = <?php 
            $dispensacoes_data = [];
            foreach ($itens_com_dispensacoes as $item_disp) {
                // Buscar detalhes das dispensações deste item
                $stmtDisp = $conn->prepare("
                    SELECT 
                        rr.id,
                        rr.quantidade,
                        rr.data_planejada,
                        rr.criado_em,
                        ri.quantidade_autorizada
                    FROM receitas_retiradas rr
                    INNER JOIN receitas_itens ri ON ri.id = rr.receita_item_id
                    WHERE rr.receita_item_id = ?
                    ORDER BY COALESCE(rr.data_planejada, rr.criado_em) ASC
                ");
                $stmtDisp->execute([$item_disp['id']]);
                $retiradas = $stmtDisp->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($retiradas as $retirada) {
                    $data_disp = $retirada['data_planejada'] ?: date('Y-m-d', strtotime($retirada['criado_em']));
                    $dispensacoes_data[] = [
                        'data' => $data_disp,
                        'quantidade' => (int)$retirada['quantidade'],
                        'item_id' => $item_disp['id']
                    ];
                }
            }
            echo json_encode($dispensacoes_data, JSON_UNESCAPED_UNICODE);
        ?>;
    </script>
    <script src="js/receitas_editar.js"></script>
</body>
</html>

