<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();
$receita_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($receita_id <= 0) {
    header('Location: receitas.php');
    exit;
}

try {
    // Buscar dados da receita
    $sql = "SELECT 
                r.*,
                p.id as paciente_id,
                p.nome as paciente_nome,
                p.cpf as paciente_cpf,
                p.cartao_sus as paciente_sus
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
    
    // Buscar itens da receita com informações dos medicamentos
    $sql = "SELECT 
                ri.*,
                m.nome as medicamento_nome,
                m.codigo_barras,
                m.estoque_atual,
                COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas rr WHERE rr.receita_item_id = ri.id), 0) as total_retiradas
            FROM receitas_itens ri
            INNER JOIN medicamentos m ON m.id = ri.medicamento_id
            WHERE ri.receita_id = ?
            ORDER BY m.nome";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$receita_id]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar retiradas (dispensações) para cada item
    foreach ($itens as &$item) {
        $stmtRetiradas = $conn->prepare("
            SELECT 
                rr.id,
                rr.quantidade,
                rr.data_planejada,
                rr.criado_em,
                rr.lote_id,
                l.numero_lote,
                (SELECT d.id FROM dispensacoes d 
                 WHERE d.receita_item_id = rr.receita_item_id 
                 AND d.lote_id = rr.lote_id 
                 AND d.quantidade = rr.quantidade
                 AND DATE(d.data_dispensacao) = DATE(COALESCE(rr.data_planejada, rr.criado_em))
                 LIMIT 1) as dispensacao_id
            FROM receitas_retiradas rr
            LEFT JOIN lotes l ON rr.lote_id = l.id
            WHERE rr.receita_item_id = ?
            ORDER BY COALESCE(rr.data_planejada, rr.criado_em) DESC
        ");
        $stmtRetiradas->execute([$item['id']]);
        $item['retiradas'] = $stmtRetiradas->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($item);
    
    // Buscar próxima data de dispensação planejada para cada item
    foreach ($itens as &$item) {
        $proximaDataDispensacao = null;
        $proximaDataFormatada = null;
        
        // Buscar a próxima data planejada que ainda não foi retirada
        // Verificar se há quantidade restante para retirar
        $quantidadeRestante = $item['quantidade_autorizada'] - $item['total_retiradas'];
        
        // Buscar a próxima data planejada que ainda não foi dispensada
        $sqlProximaData = "SELECT 
                            rrp.data_planejada,
                            rrp.numero_retirada
                          FROM receitas_retiradas_planejadas rrp
                          WHERE rrp.receita_item_id = ?
                            AND rrp.data_planejada >= CURDATE()
                            AND NOT EXISTS (
                                SELECT 1 FROM receitas_retiradas rr 
                                WHERE rr.receita_item_id = rrp.receita_item_id 
                                AND (rr.data_planejada = rrp.data_planejada OR (rr.data_planejada IS NULL AND DATE(rr.criado_em) = rrp.data_planejada))
                            )
                          ORDER BY rrp.data_planejada ASC
                          LIMIT 1";
        
        $stmtProximaData = $conn->prepare($sqlProximaData);
        $stmtProximaData->execute([$item['id']]);
        $proximaData = $stmtProximaData->fetch(PDO::FETCH_ASSOC);
        
        if ($proximaData) {
            // Usar data planejada
            $proximaDataDispensacao = $proximaData['data_planejada'];
            $proximaDataFormatada = date('d/m/Y', strtotime($proximaData['data_planejada']));
        } else {
            // Se não há data planejada futura, verificar se pode dispensar hoje
            // (baseado em ultima_retirada + intervalo_dias como fallback)
            if (empty($item['ultima_retirada'])) {
                // Se nunca retirou, pode dispensar hoje
                $proximaDataDispensacao = date('Y-m-d');
                $proximaDataFormatada = date('d/m/Y');
            } else {
                // Calcular próxima data baseada em ultima_retirada + intervalo_dias
                $ultimaRetirada = new DateTime($item['ultima_retirada']);
                $intervaloDias = (int)($item['intervalo_dias'] ?? 30);
                $ultimaRetirada->modify("+{$intervaloDias} days");
                $proximaDataDispensacao = $ultimaRetirada->format('Y-m-d');
                $proximaDataFormatada = $ultimaRetirada->format('d/m/Y');
                
                // Se a próxima data já passou, pode dispensar hoje
                if ($proximaDataDispensacao <= date('Y-m-d')) {
                    $proximaDataDispensacao = date('Y-m-d');
                    $proximaDataFormatada = date('d/m/Y');
                }
            }
        }
        
        $item['proxima_data_dispensacao'] = $proximaDataDispensacao;
        $item['proxima_data_formatada'] = $proximaDataFormatada;
    }
    unset($item);
    
} catch (PDOException $e) {
    $error = "Erro ao buscar receita: " . $e->getMessage();
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
    <meta name="description" content="Sistema de gestão de farmácia - Controle de medicamentos, lotes, pacientes e receitas">
    <meta name="keywords" content="farmácia, medicamentos, gestão, controle de estoque, receitas, dispensação">
    <meta name="author" content="Sistema Farmácia">
    <meta name="robots" content="noindex, nofollow">
    
    <?php 
    $ogTitle = htmlspecialchars($pageTitle) . ' - Gov Farma';
    $ogDescription = 'Gov Farma - Dispensação de medicamentos por receita. Controle de lotes e validação de prescrições.';
    include '../includes/og_meta.php'; 
    ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="../images/logo.svg">
    <link rel="apple-touch-icon" href="../images/logo.svg">
    
    <?php include '../includes/pwa_head.php'; ?>
    
    <title><?php echo htmlspecialchars($pageTitle); ?> - Farmácia Popular</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/admin_new.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="admin-shell bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-50 min-h-screen">
    
    <?php include 'includes/sidebar.php'; ?>

    <main class="content-area">
        <!-- Header -->
        <div class="glass-card p-4 mb-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <h1 class="text-lg sm:text-xl font-bold text-gray-800 flex flex-wrap items-center gap-2">
                        <span class="text-xl sm:text-2xl">💊</span>
                        <span>Dispensar Receita</span>
                        <span class="text-base sm:text-xl">#<?php echo !empty($receita['numero_receita']) ? htmlspecialchars($receita['numero_receita']) : $receita_id; ?></span>
                        <a 
                            href="receitas_editar.php?id=<?php echo $receita_id; ?>" 
                            class="opacity-40 hover:opacity-100 text-blue-500 hover:text-blue-600 transition-all p-1 rounded ml-1"
                            title="Editar receita"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                        </a>
                        <button 
                            onclick="confirmarDeletarReceita()" 
                            class="opacity-40 hover:opacity-100 text-red-500 hover:text-red-600 transition-all p-1 rounded ml-1"
                            title="Deletar receita"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </h1>
                    <p class="text-xs text-gray-600 mt-1">Realize a dispensação dos medicamentos desta receita</p>
                </div>
                <a href="receitas.php" class="w-full sm:w-auto px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-all text-sm font-medium text-center">
                    ← Voltar
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="glass-card p-4 mb-4 bg-red-50 border border-red-200">
                <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($receita): ?>
            <!-- Informações do Paciente -->
            <div class="glass-card p-6 mb-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <span>👤</span> Dados do Paciente
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
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

            <!-- Informações da Receita -->
            <div class="glass-card p-6 mb-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <span>📋</span> Dados da Receita
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
                        
                        switch($receita['status']) {
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
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($receita['observacoes'])): ?>
                    <div class="mt-4 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                        <p class="text-xs text-gray-600 mb-1">Observações:</p>
                        <p class="text-sm text-gray-800"><?php echo htmlspecialchars($receita['observacoes']); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Medicamentos da Receita -->
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <span>💊</span> Medicamentos Autorizados
                    <?php if (count($itens) > 0 && !empty($itens[0]['estoque_atual'])): ?>
                        <span class="text-sm font-normal text-gray-500 ml-2">(Estoque: <?php echo $itens[0]['estoque_atual']; ?>)</span>
                    <?php endif; ?>
                </h3>

                <?php if (count($itens) > 0): ?>
                    <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-200">
                        <?php foreach ($itens as $item): ?>
                            <div class="p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                    <div class="flex-1 min-w-0 w-full sm:w-auto">
                                        <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
                                            <h4 class="font-semibold text-gray-900 text-sm sm:text-base break-words"><?php echo htmlspecialchars($item['medicamento_nome']); ?></h4>
                                            <?php if ($item['codigo_barras']): ?>
                                                <span class="text-xs text-gray-500 whitespace-nowrap"><?php echo htmlspecialchars($item['codigo_barras']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-xs sm:text-sm">
                                            <span class="text-gray-600">
                                                <span class="font-medium text-blue-600"><?php echo $item['quantidade_autorizada']; ?></span> autorizada
                                            </span>
                                            <span class="text-gray-600">
                                                <span class="font-medium text-green-600"><?php echo $item['total_retiradas']; ?></span> retirada
                                            </span>
                                        </div>
                                        
                                        <?php if ($item['total_retiradas'] < $item['quantidade_autorizada']): ?>
                                            <div class="mt-2 flex items-center gap-2 text-xs sm:text-sm">
                                                <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                                <span class="text-gray-600">
                                                    Próxima: <span class="font-medium text-blue-600"><?php echo $item['proxima_data_formatada']; ?></span>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="w-full sm:w-auto flex-shrink-0">
                                        <?php 
                                        $quantidade_restante = $item['quantidade_autorizada'] - $item['total_retiradas'];
                                        if ($quantidade_restante > 0 && $receita['status'] === 'ativa'): ?>
                                            <button 
                                                onclick="abrirModalDispensacao(<?php echo $item['id']; ?>, '<?php echo addslashes($item['medicamento_nome']); ?>', <?php echo $quantidade_restante; ?>, <?php echo $item['medicamento_id']; ?>)"
                                                class="w-full sm:w-auto px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-all">
                                                ✓ Dispensar
                                            </button>
                                        <?php elseif ($item['total_retiradas'] >= $item['quantidade_autorizada']): ?>
                                            <div class="w-full sm:w-auto flex items-center gap-2">
                                                <?php if (!empty($item['retiradas'])): ?>
                                                    <button 
                                                        onclick="abrirModalDeletarDispensacoes(<?php echo $item['id']; ?>, '<?php echo addslashes($item['medicamento_nome']); ?>')"
                                                        class="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors"
                                                        title="Deletar dispensações"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                <?php endif; ?>
                                                <span class="inline-block px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium text-center">
                                                    ✓ Completo
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
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

    <!-- Modal de Dispensação -->
    <div id="modalDispensacao" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-2xl w-full mx-4 shadow-2xl">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Dispensar Medicamento</h3>
            
            <form id="formDispensacao" class="space-y-4">
                <input type="hidden" id="receita_item_id" name="receita_item_id">
                <input type="hidden" id="medicamento_id" name="medicamento_id">
                <input type="hidden" name="receita_id" value="<?php echo $receita_id; ?>">
                <input type="hidden" name="paciente_id" value="<?php echo $receita['paciente_id'] ?? ''; ?>">
                <input type="hidden" id="quantidade" name="quantidade">
                
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-600">Medicamento</p>
                    <p class="text-lg font-bold text-gray-900" id="modal_medicamento_nome"></p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Buscar lote por código de barras (opcional)
                    </label>
                    <input type="text" id="codigo_barras_busca_dispensar" placeholder="Digite ou escaneie o código de barras..." class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500" autocomplete="off">
                    <p class="text-xs text-gray-500 mt-1">Ou selecione manualmente abaixo</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <span class="text-red-500">*</span> Selecionar Lote
                    </label>
                    <select id="lote_id" name="lote_id" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Carregando lotes...</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Observações</label>
                    <textarea id="observacoes" name="observacoes" rows="3" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="fecharModal()" class="flex-1 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium transition-all">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white rounded-lg font-bold shadow-lg hover:shadow-xl transition-all">
                        ✓ Confirmar Dispensação
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Deletar Dispensações -->
    <div id="modalDeletarDispensacoes" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-2xl w-full mx-4 shadow-2xl max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-bold text-gray-900 mb-4">
                Deletar Dispensações - <span id="modalMedicamentoNome"></span>
            </h3>
            
            <div id="listaDispensacoes" class="space-y-3 mb-4">
                <!-- Lista será preenchida via JavaScript -->
            </div>
            
            <div class="flex gap-3 pt-4 border-t">
                <button type="button" onclick="fecharModalDeletarDispensacoes()" class="flex-1 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium transition-all">
                    Cancelar
                </button>
            </div>
        </div>
    </div>

    <script>
        function confirmarDeletarReceita() {
            Swal.fire({
                title: 'Confirmar Exclusão',
                text: 'Tem certeza que deseja deletar esta receita? Esta ação não pode ser desfeita.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, deletar!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    deletarReceita();
                }
            });
        }

        async function deletarReceita() {
            try {
                const response = await fetch('api/deletar_receita.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        receita_id: <?php echo $receita_id; ?>
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Receita deletada com sucesso!',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'receitas.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: data.message || 'Erro ao deletar receita'
                    });
                }
            } catch (error) {
                console.error('Erro ao deletar receita:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao deletar receita. Verifique o console para mais detalhes.'
                });
            }
        }

        function abrirModalDispensacao(receitaItemId, medicamentoNome, quantidadeRestante, medicamentoId) {
            document.getElementById('receita_item_id').value = receitaItemId;
            document.getElementById('medicamento_id').value = medicamentoId;
            document.getElementById('modal_medicamento_nome').textContent = medicamentoNome;
            // Quantidade sempre será a restante completa (fixa) - retira tudo de uma vez
            document.getElementById('quantidade').value = quantidadeRestante;
            document.getElementById('codigo_barras_busca_dispensar').value = '';
            
            // Carregar lotes disponíveis
            carregarLotes(medicamentoId);
            
            document.getElementById('modalDispensacao').classList.remove('hidden');
            document.getElementById('codigo_barras_busca_dispensar').focus();
        }

        function fecharModal() {
            document.getElementById('modalDispensacao').classList.add('hidden');
            document.getElementById('formDispensacao').reset();
        }

        async function carregarLotes(medicamentoId) {
            const select = document.getElementById('lote_id');
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

        let buscaLoteTimeoutDispensar = null;

        async function buscarLotePorCodigoDispensar() {
            const codigoBarras = document.getElementById('codigo_barras_busca_dispensar').value.trim();
            const medicamentoId = document.getElementById('medicamento_id').value;
            const select = document.getElementById('lote_id');
            
            if (!codigoBarras || codigoBarras.length < 3) {
                // Se o código for muito curto, recarregar todos os lotes
                if (codigoBarras.length === 0) {
                    await carregarLotes(medicamentoId);
                }
                return;
            }
            
            try {
                const response = await fetch(`api/buscar_lote_por_codigo.php?codigo_barras=${encodeURIComponent(codigoBarras)}&medicamento_id=${medicamentoId}`);
                const data = await response.json();
                
                if (data.success && data.lote) {
                    // Limpar e recarregar todos os lotes primeiro
                    await carregarLotes(medicamentoId);
                    
                    // Selecionar o lote encontrado
                    setTimeout(() => {
                        select.value = data.lote.id;
                    }, 100);
                } else {
                    // Se não encontrou, recarregar todos os lotes
                    await carregarLotes(medicamentoId);
                }
            } catch (error) {
                console.error('Erro ao buscar lote:', error);
                await carregarLotes(medicamentoId);
            }
        }

        // Busca automática enquanto digita
        document.addEventListener('DOMContentLoaded', function() {
            const codigoBarrasInput = document.getElementById('codigo_barras_busca_dispensar');
            if (codigoBarrasInput) {
                codigoBarrasInput.addEventListener('input', function(e) {
                    clearTimeout(buscaLoteTimeoutDispensar);
                    buscaLoteTimeoutDispensar = setTimeout(() => {
                        buscarLotePorCodigoDispensar();
                    }, 300); // Aguarda 300ms após parar de digitar
                });
            }
        });

        document.getElementById('formDispensacao').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Solicitar senha do funcionário
            const { value: senhaFuncionario } = await Swal.fire({
                title: 'Senha do Funcionário',
                text: 'Digite a senha numérica do funcionário responsável pela dispensação:',
                input: 'password',
                inputPlaceholder: 'Digite a senha (apenas números)',
                inputAttributes: {
                    maxlength: 20,
                    pattern: '[0-9]*',
                    inputmode: 'numeric',
                    autocomplete: 'new-password', // Usa 'new-password' para evitar autocomplete
                    'data-form-type': 'other', // Indica que não é um formulário de login
                    name: 'funcionario-senha-temp', // Nome único para evitar autocomplete
                    id: 'swal-funcionario-senha-' + Date.now() // ID único com timestamp
                },
                showCancelButton: true,
                confirmButtonText: 'Confirmar',
                cancelButtonText: 'Cancelar',
                allowOutsideClick: false,
                allowEscapeKey: true,
                inputValidator: (value) => {
                    if (!value) {
                        return 'Por favor, digite a senha!';
                    }
                    if (!/^\d+$/.test(value)) {
                        return 'A senha deve conter apenas números!';
                    }
                }
            });
            
            if (!senhaFuncionario) {
                return; // Usuário cancelou
            }
            
            // Validar senha do funcionário
            try {
                const validacaoResponse = await fetch('api/validar_senha_funcionario.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ senha: senhaFuncionario })
                });
                
                const validacaoResult = await validacaoResponse.json();
                
                if (!validacaoResult.success || !validacaoResult.funcionario) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Senha Inválida',
                        text: validacaoResult.message || 'Senha incorreta ou funcionário inativo'
                    });
                    return;
                }
                
                const funcionario = validacaoResult.funcionario;
                
                const formData = new FormData(e.target);
                const data = Object.fromEntries(formData.entries());
                data.funcionario_id = funcionario.id;
                
                const response = await fetch('api/dispensar_receita.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Medicamento dispensado com sucesso!',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: result.message || 'Erro ao processar dispensação'
                    });
                }
            } catch (error) {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao processar dispensação: ' + error.message
                });
            }
        });

        // Fechar modal ao clicar fora
        document.getElementById('modalDispensacao').addEventListener('click', (e) => {
            if (e.target.id === 'modalDispensacao') {
                fecharModal();
            }
        });

        // Dados dos itens para JavaScript
        const itensReceita = <?php echo json_encode($itens, JSON_UNESCAPED_UNICODE); ?>;

        function abrirModalDeletarDispensacoes(receitaItemId, medicamentoNome) {
            const item = itensReceita.find(i => i.id == receitaItemId);
            if (!item || !item.retiradas || item.retiradas.length === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Informação',
                    text: 'Nenhuma dispensação encontrada para este medicamento'
                });
                return;
            }
            
            document.getElementById('modalMedicamentoNome').textContent = medicamentoNome;
            const lista = document.getElementById('listaDispensacoes');
            
            lista.innerHTML = item.retiradas.map(retirada => `
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 flex items-center justify-between">
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900">Quantidade: ${retirada.quantidade}</p>
                        <p class="text-sm text-gray-600">
                            Data: ${retirada.data_planejada ? new Date(retirada.data_planejada).toLocaleDateString('pt-BR') : new Date(retirada.criado_em).toLocaleDateString('pt-BR')}
                        </p>
                        ${retirada.numero_lote ? `<p class="text-xs text-gray-500">Lote: ${retirada.numero_lote}</p>` : ''}
                    </div>
                    <button 
                        onclick="deletarDispensacaoReceita(${retirada.id}, ${retirada.dispensacao_id || 'null'}, ${receitaItemId})"
                        class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors"
                        title="Deletar esta dispensação"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            `).join('');
            
            document.getElementById('modalDeletarDispensacoes').classList.remove('hidden');
        }

        function fecharModalDeletarDispensacoes() {
            document.getElementById('modalDeletarDispensacoes').classList.add('hidden');
        }

        async function deletarDispensacaoReceita(retiradaId, dispensacaoId, receitaItemId) {
            const result = await Swal.fire({
                title: 'Confirmar Exclusão',
                text: 'Tem certeza que deseja deletar esta dispensação? Esta ação irá reverter o estoque e não pode ser desfeita.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, deletar!',
                cancelButtonText: 'Cancelar'
            });

            if (!result.isConfirmed) {
                return;
            }

            try {
                const response = await fetch('api/deletar_dispensacao_receita.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        retirada_id: retiradaId,
                        dispensacao_id: dispensacaoId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Dispensação deletada com sucesso!',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: data.message || 'Erro ao deletar dispensação'
                    });
                }
            } catch (error) {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao deletar dispensação'
                });
            }
        }

        // Fechar modal ao clicar fora
        document.getElementById('modalDeletarDispensacoes').addEventListener('click', (e) => {
            if (e.target.id === 'modalDeletarDispensacoes') {
                fecharModalDeletarDispensacoes();
            }
        });
    </script>
</body>
</html>
