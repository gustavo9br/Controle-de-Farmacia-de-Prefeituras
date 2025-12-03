<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

$codigo_barras = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';
$med_id_get = isset($_GET['med_id']) ? (int)$_GET['med_id'] : 0;
$medicamento = null;
$lotes_existentes = [];
$med_id = 0;
$codigos_barras_disponiveis = []; // Lista de códigos de barras disponíveis para o medicamento

// Buscar medicamento por código de barras ou med_id
if (!empty($codigo_barras) || $med_id_get > 0) {
    try {
        if ($med_id_get > 0) {
            // Buscar medicamento pelo ID
            $stmt = $conn->prepare("
                SELECT m.*
                FROM medicamentos m
                WHERE m.id = ? AND m.ativo = 1
                LIMIT 1
            ");
            $stmt->execute([$med_id_get]);
            $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($medicamento) {
                $med_id = (int)$medicamento['id'];
                
                // Buscar todos os códigos de barras disponíveis para este medicamento
                $stmt = $conn->prepare("SELECT id, codigo FROM codigos_barras WHERE medicamento_id = ? ORDER BY id ASC");
                $stmt->execute([$med_id]);
                $codigos_barras_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Se tiver código de barras, buscar o ID
                if (!empty($codigo_barras)) {
                    $stmt = $conn->prepare("SELECT id FROM codigos_barras WHERE medicamento_id = ? AND codigo = ?");
                    $stmt->execute([$med_id, $codigo_barras]);
                    $cb_row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $codigo_barras_id = $cb_row ? (int)$cb_row['id'] : 0;
                } else {
                    // Não pegar automaticamente, deixar o usuário escolher
                    $codigo_barras_id = 0;
                }
            }
        } else {
            // Buscar medicamento pelo código de barras
            $stmt = $conn->prepare("
                SELECT m.*, cb.id as codigo_barras_id
                FROM medicamentos m
                INNER JOIN codigos_barras cb ON cb.medicamento_id = m.id
                WHERE cb.codigo = ? AND m.ativo = 1
                LIMIT 1
            ");
            $stmt->execute([$codigo_barras]);
            $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($medicamento) {
                $med_id = (int)$medicamento['id'];
                $codigo_barras_id = (int)$medicamento['codigo_barras_id'];
                
                // Buscar todos os códigos de barras disponíveis para este medicamento
                $stmt = $conn->prepare("SELECT id, codigo FROM codigos_barras WHERE medicamento_id = ? ORDER BY id ASC");
                $stmt->execute([$med_id]);
                $codigos_barras_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        if ($medicamento && !empty($codigo_barras) && $codigo_barras_id > 0) {
            // Buscar lotes existentes (não vencidos e com estoque) apenas se tiver código de barras específico
            $stmt = $conn->prepare("
                SELECT 
                    l.id,
                    l.numero_lote,
                    l.data_recebimento,
                    DATE_FORMAT(l.data_recebimento, '%d/%m/%Y') as data_recebimento_formatada,
                    l.data_validade,
                    DATE_FORMAT(l.data_validade, '%d/%m/%Y') as data_validade_formatada,
                    l.quantidade_atual,
                    l.observacoes,
                    DATEDIFF(l.data_validade, CURDATE()) as dias_para_vencer
                FROM lotes l
                WHERE l.medicamento_id = ?
                  AND l.codigo_barras_id = ?
                  AND l.data_validade >= CURDATE()
                  AND l.quantidade_atual > 0
                ORDER BY l.data_validade ASC, l.numero_lote ASC
            ");
            $stmt->execute([$med_id, $codigo_barras_id]);
            $lotes_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erro ao buscar medicamento: " . $e->getMessage();
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verificarCSRFToken($csrfToken)) {
        $_SESSION['error'] = 'Token de segurança inválido. Atualize a página e tente novamente.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_lote') {
            $codigo_barras_post = trim($_POST['codigo_barras'] ?? '');
            $numero_lote = trim($_POST['numero_lote'] ?? '');
            $data_recebimento = trim($_POST['data_recebimento'] ?? '');
            $data_validade = trim($_POST['data_validade'] ?? '');
            $quantidade_total = (int)($_POST['quantidade_total'] ?? 0);
            $observacoes = trim($_POST['observacoes'] ?? '');
            $usar_lote_existente = isset($_POST['usar_lote_existente']) && $_POST['usar_lote_existente'] === '1';
            $lote_existente_id = isset($_POST['lote_existente_id']) ? (int)$_POST['lote_existente_id'] : 0;
            $med_id_post = isset($_POST['med_id']) ? (int)$_POST['med_id'] : 0;
            
            if (empty($codigo_barras_post)) {
                $_SESSION['error'] = "O código de barras é obrigatório.";
            } elseif ($quantidade_total <= 0) {
                $_SESSION['error'] = "A quantidade total deve ser maior que zero.";
            } else {
                try {
                    $conn->beginTransaction();
                    
                    // 1. Verificar se o código de barras já existe para este medicamento
                    $stmt = $conn->prepare("SELECT id FROM codigos_barras WHERE medicamento_id = ? AND codigo = ?");
                    $stmt->execute([$med_id_post, $codigo_barras_post]);
                    $codigo_barras_existente = $stmt->fetch();
                    
                    if ($codigo_barras_existente) {
                        $codigo_barras_id = $codigo_barras_existente['id'];
                    } else {
                        // 2. Criar novo código de barras para este medicamento
                        $stmt = $conn->prepare("INSERT INTO codigos_barras (medicamento_id, codigo) VALUES (?, ?)");
                        $stmt->execute([$med_id_post, $codigo_barras_post]);
                        $codigo_barras_id = (int)$conn->lastInsertId();
                    }
                    
                    // Se está usando um lote existente selecionado, apenas adicionar quantidade
                    if ($usar_lote_existente && $lote_existente_id > 0) {
                        $stmt = $conn->prepare("SELECT id, quantidade_atual FROM lotes WHERE id = ? AND medicamento_id = ? AND codigo_barras_id = ?");
                        $stmt->execute([$lote_existente_id, $med_id_post, $codigo_barras_id]);
                        $lote_selecionado = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$lote_selecionado) {
                            throw new RuntimeException("Lote selecionado não encontrado.");
                        }
                        
                        // Atualizar apenas a quantidade e data de recebimento (se for mais recente)
                        $quantidade_atual_anterior = (int)$lote_selecionado['quantidade_atual'];
                        $nova_quantidade_atual = $quantidade_atual_anterior + $quantidade_total;
                        
                        // Verificar se a nova data de recebimento é mais recente
                        $stmt = $conn->prepare("SELECT data_recebimento FROM lotes WHERE id = ?");
                        $stmt->execute([$lote_existente_id]);
                        $data_recebimento_anterior = $stmt->fetchColumn();
                        
                        $data_recebimento_final = $data_recebimento;
                        if ($data_recebimento_anterior && $data_recebimento_anterior > $data_recebimento) {
                            $data_recebimento_final = $data_recebimento_anterior;
                        }
                        
                        $sql = "UPDATE lotes SET 
                                quantidade_atual = ?,
                                data_recebimento = ?,
                                atualizado_em = NOW()
                                WHERE id = ?";
                        
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $nova_quantidade_atual,
                            $data_recebimento_final,
                            $lote_existente_id
                        ]);
                        
                        $mensagem_sucesso = "Quantidade adicionada ao lote existente com sucesso!";
                    } else {
                        // Criar novo lote
                        if (empty($numero_lote)) {
                            throw new RuntimeException("O número do lote é obrigatório.");
                        }
                        if (empty($data_recebimento) || empty($data_validade)) {
                            throw new RuntimeException("As datas de recebimento e validade são obrigatórias.");
                        }
                        
                        // 3. Verificar se o lote já existe para este código de barras e medicamento
                        $stmt = $conn->prepare("SELECT id, quantidade_atual FROM lotes WHERE codigo_barras_id = ? AND medicamento_id = ? AND numero_lote = ?");
                        $stmt->execute([$codigo_barras_id, $med_id_post, $numero_lote]);
                        $lote_existente = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($lote_existente) {
                            // Reutilizar lote existente: atualizar dados e adicionar quantidade
                            $lote_id = $lote_existente['id'];
                            $quantidade_atual_anterior = (int)$lote_existente['quantidade_atual'];
                            $nova_quantidade_atual = $quantidade_atual_anterior + $quantidade_total;
                            
                            $sql = "UPDATE lotes SET 
                                    data_recebimento = ?, 
                                    data_validade = ?, 
                                    quantidade_atual = ?,
                                    observacoes = ?,
                                    atualizado_em = NOW()
                                    WHERE id = ?";
                            
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([
                                $data_recebimento, $data_validade, $nova_quantidade_atual,
                                $observacoes, $lote_id
                            ]);
                            
                            $mensagem_sucesso = "Lote reutilizado e atualizado com sucesso! A quantidade foi adicionada ao lote existente.";
                        } else {
                            // 4. Inserir novo lote (ligado ao código de barras)
                            $sql = "INSERT INTO lotes (codigo_barras_id, medicamento_id, numero_lote, data_recebimento, data_validade, 
                                    quantidade_atual, observacoes, criado_em) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                            
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([
                                $codigo_barras_id, $med_id_post, $numero_lote, $data_recebimento, $data_validade,
                                $quantidade_total, $observacoes
                            ]);
                            
                            $mensagem_sucesso = "Lote adicionado com sucesso!";
                        }
                    }
                    
                    // 5. Atualizar estoque do medicamento (soma de todos os lotes de todos os códigos de barras)
                    $stmt = $conn->prepare("
                        SELECT COALESCE(SUM(quantidade_atual), 0) as estoque_total
                        FROM lotes 
                        WHERE medicamento_id = ?
                    ");
                    $stmt->execute([$med_id_post]);
                    $estoque_total = (int)$stmt->fetchColumn();
                    
                    $sql = "UPDATE medicamentos SET estoque_atual = ?, atualizado_em = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$estoque_total, $med_id_post]);
                    
                    $conn->commit();
                    $_SESSION['success'] = $mensagem_sucesso;
                    
                    // Redirecionar de volta para a página de origem
                    $redirect = isset($_POST['from_medicamentos_lotes']) && $_POST['from_medicamentos_lotes'] === '1' 
                        ? "medicamentos_lotes.php?med_id=$med_id_post" 
                        : "lotes.php";
                    header("Location: $redirect");
                    exit;
                    
                } catch (Exception $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    $_SESSION['error'] = $e->getMessage();
                }
            }
        }
    }
}

$successMessage = getSuccessMessage();
$errorMessage = getErrorMessage();
$csrfToken = gerarCSRFToken();

$pageTitle = 'Adicionar lote';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Adicionar lote">
    <meta name="robots" content="noindex, nofollow">
    
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
    <div class="flex min-h-screen">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <main class="content-area">
            <div class="space-y-6">
                <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="mt-2 text-sm text-slate-500">Adicione um novo lote ao sistema</p>
                    </div>
                    <a href="lotes.php" class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-slate-500 font-semibold shadow hover:shadow-lg transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                        Voltar
                    </a>
                </header>

                <?php if (!empty($errorMessage)): ?>
                    <div class="glass-card border border-rose-200/60 bg-rose-50/80 px-6 py-4 text-rose-700">
                        <strong class="block text-sm font-semibold">Atenção</strong>
                        <span class="text-sm"><?php echo htmlspecialchars($errorMessage); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($successMessage)): ?>
                    <div class="glass-card border border-emerald-200/60 bg-emerald-50/80 px-6 py-4 text-emerald-700">
                        <strong class="block text-sm font-semibold">Tudo certo!</strong>
                        <span class="text-sm"><?php echo htmlspecialchars($successMessage); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Buscar medicamento por código de barras -->
                <?php if (!$medicamento): ?>
                    <section class="glass-card p-6 space-y-4">
                        <div>
                            <label for="codigo_barras_scanner" class="block text-sm font-medium text-slate-700 mb-2">
                                Escaneie ou digite o código de barras do medicamento <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="text" id="codigo_barras_scanner" placeholder="Escaneie ou digite o código de barras" 
                                    class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-base text-slate-700 focus:border-primary-500 focus:ring-primary-500" 
                                    autofocus>
                                <div id="scannerLoader" class="hidden absolute right-4 top-1/2 -translate-y-1/2">
                                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-500"></div>
                                </div>
                            </div>
                            <span class="text-xs text-slate-400 mt-1 block">O sistema buscará o medicamento automaticamente.</span>
                        </div>
                        <div id="erroMedicamento" class="hidden p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-sm">
                            <p id="erroMedicamentoTexto"></p>
                        </div>
                    </section>
                <?php else: ?>
                    <!-- Informações do medicamento -->
                    <section class="glass-card p-6">
                        <div class="flex items-start justify-between mb-6">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900"><?php echo htmlspecialchars($medicamento['nome']); ?></h2>
                                <?php if (!empty($medicamento['descricao'])): ?>
                                    <p class="text-sm text-slate-600 mt-1"><?php echo htmlspecialchars($medicamento['descricao']); ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-slate-500 mt-2">Estoque atual: <span class="font-semibold"><?php echo (int)$medicamento['estoque_atual']; ?></span> unidades</p>
                            </div>
                            <a href="lotes_adicionar.php" class="text-slate-400 hover:text-slate-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
                            </a>
                        </div>
                        <div>
                            <label for="codigo_barras_select" class="block text-sm font-medium text-slate-700 mb-2">Código de Barras <span class="text-rose-500">*</span></label>
                            <?php if (count($codigos_barras_disponiveis) > 0): ?>
                                <select name="codigo_barras_select" id="codigo_barras_select" class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500 mb-2">
                                    <option value="">Selecione um código existente ou digite um novo</option>
                                    <?php foreach ($codigos_barras_disponiveis as $cb): ?>
                                        <option value="<?php echo htmlspecialchars($cb['codigo']); ?>" <?php echo ($codigo_barras === $cb['codigo']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cb['codigo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <input type="text" name="codigo_barras" id="codigo_barras_input" value="<?php echo htmlspecialchars($codigo_barras); ?>" 
                                placeholder="Digite ou escaneie o código de barras" 
                                class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-base text-slate-700 focus:border-primary-500 focus:ring-primary-500" 
                                required autofocus>
                            <span class="text-xs text-slate-400 mt-1 block">Se o código não existir, será criado automaticamente para este medicamento.</span>
                        </div>
                    </section>

                    <!-- Lotes existentes -->
                    <?php if (count($lotes_existentes) > 0): ?>
                        <section id="lotesExistentesContainer" class="glass-card p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-slate-900">Lotes existentes para este código de barras</h3>
                                <button type="button" id="btnNovoLote" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                                    Criar novo lote
                                </button>
                            </div>
                            <div id="lotesExistentesLista" class="space-y-3">
                                <?php foreach ($lotes_existentes as $lote): ?>
                                    <?php
                                        $diasVenc = (int)$lote['dias_para_vencer'];
                                        $badgeClass = 'bg-emerald-100 text-emerald-600';
                                        $badgeText = "Vence em {$diasVenc} dias";
                                        
                                        if ($diasVenc < 0) {
                                            $badgeClass = 'bg-rose-100 text-rose-600';
                                            $badgeText = 'Vencido há ' . abs($diasVenc) . ' dias';
                                        } elseif ($diasVenc <= 30) {
                                            $badgeClass = 'bg-amber-100 text-amber-700';
                                        } elseif ($diasVenc <= 90) {
                                            $badgeClass = 'bg-sky-100 text-sky-700';
                                        }
                                    ?>
                                    <div class="lote-card bg-white rounded-xl p-4 border border-slate-200 hover:border-primary-300 cursor-pointer transition" 
                                         data-lote-id="<?php echo (int)$lote['id']; ?>"
                                         data-numero-lote="<?php echo htmlspecialchars($lote['numero_lote']); ?>"
                                         data-data-validade="<?php echo htmlspecialchars($lote['data_validade']); ?>"
                                         data-data-recebimento="<?php echo htmlspecialchars($lote['data_recebimento']); ?>"
                                         data-observacoes="<?php echo htmlspecialchars($lote['observacoes'] ?? ''); ?>">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <span class="font-semibold text-slate-900">Lote: <?php echo htmlspecialchars($lote['numero_lote']); ?></span>
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo $badgeClass; ?>">
                                                        <?php echo $badgeText; ?>
                                                    </span>
                                                </div>
                                                <div class="text-xs text-slate-600 space-y-1">
                                                    <p>Validade: <?php echo htmlspecialchars($lote['data_validade_formatada']); ?></p>
                                                    <p>Recebimento: <?php echo htmlspecialchars($lote['data_recebimento_formatada']); ?></p>
                                                    <p>Qtd. Atual: <span class="font-semibold"><?php echo (int)$lote['quantidade_atual']; ?></span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <!-- Formulário de lote -->
                    <section class="glass-card p-6">
                        <form id="formLote" method="post" class="space-y-6">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="add_lote">
                            <input type="hidden" name="med_id" value="<?php echo $med_id; ?>">
                            <input type="hidden" name="codigo_barras" id="codigo_barras_hidden" value="<?php echo htmlspecialchars($codigo_barras); ?>">
                            <input type="hidden" name="lote_existente_id" id="lote_existente_id" value="">
                            <input type="hidden" name="usar_lote_existente" id="usar_lote_existente" value="0">
                            <?php if ($med_id_get > 0): ?>
                                <input type="hidden" name="from_medicamentos_lotes" value="1">
                            <?php endif; ?>

                            <div class="grid gap-6 md:grid-cols-2">
                                <div>
                                    <label for="numero_lote" class="block text-sm font-medium text-slate-700 mb-2">Número do Lote <span class="text-rose-500">*</span></label>
                                    <input type="text" name="numero_lote" id="numero_lote" required 
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-base text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                <div>
                                    <label for="data_validade" class="block text-sm font-medium text-slate-700 mb-2">Data de Validade <span class="text-rose-500">*</span></label>
                                    <input type="date" name="data_validade" id="data_validade" required 
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-base text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                                </div>
                            </div>

                            <div class="grid gap-6 md:grid-cols-2">
                                <div>
                                    <label for="data_recebimento" class="block text-sm font-medium text-slate-700 mb-2">Data de Recebimento <span class="text-rose-500">*</span></label>
                                    <input type="date" name="data_recebimento" id="data_recebimento" value="<?php echo date('Y-m-d'); ?>" required 
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-base text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                <div>
                                    <label for="quantidade_total" class="block text-sm font-medium text-slate-700 mb-2">Quantidade (unidades) <span class="text-rose-500">*</span></label>
                                    <input type="number" name="quantidade_total" id="quantidade_total" min="1" value="1" required 
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-base text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                                    <span class="text-xs text-slate-400 mt-1 block">Quantidade sempre em unidades.</span>
                                </div>
                            </div>

                            <div>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" id="mostrar_observacoes" class="w-4 h-4 text-primary-600 border-slate-300 rounded focus:ring-primary-500">
                                    <span class="text-sm font-medium text-slate-700">Adicionar observações</span>
                                </label>
                                <div id="observacoes_container" class="hidden mt-3">
                                    <label for="observacoes" class="block text-sm font-medium text-slate-700 mb-2">Observações</label>
                                    <textarea name="observacoes" id="observacoes" rows="3" 
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-base text-slate-700 focus:border-primary-500 focus:ring-primary-500"></textarea>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-3 pt-6 justify-end">
                                <a href="lotes.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-6 py-2.5 text-slate-500 font-semibold shadow hover:shadow-lg transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18 18 6M6 6l12 12"/></svg>
                                    Cancelar
                                </a>
                                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-6 py-2.5 text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
                                    Adicionar lote
                                </button>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
    <script>
        let scannerTimeout = null;

        document.addEventListener('DOMContentLoaded', function() {
            const codigoBarrasScanner = document.getElementById('codigo_barras_scanner');
            
            if (codigoBarrasScanner) {
                codigoBarrasScanner.addEventListener('input', function(e) {
                    const codigo = this.value.trim();
                    
                    clearTimeout(scannerTimeout);
                    
                    if (codigo.length >= 8) {
                        scannerTimeout = setTimeout(() => {
                            window.location.href = `lotes_adicionar.php?codigo=${encodeURIComponent(codigo)}`;
                        }, 500);
                    }
                });
            }

            // Selecionar lote existente
            const loteCards = document.querySelectorAll('.lote-card');
            loteCards.forEach(card => {
                card.addEventListener('click', function() {
                    selecionarLoteExistente(this);
                });
            });

            // Botão criar novo lote
            const btnNovoLote = document.getElementById('btnNovoLote');
            if (btnNovoLote) {
                btnNovoLote.addEventListener('click', function() {
                    criarNovoLote();
                });
            }
        });

        function selecionarLoteExistente(card) {
            // Remover seleção anterior
            document.querySelectorAll('.lote-card').forEach(c => {
                c.classList.remove('ring-2', 'ring-primary-500', 'border-primary-500');
            });
            
            // Destacar card selecionado
            card.classList.add('ring-2', 'ring-primary-500', 'border-primary-500');
            
            // Preencher campos (apenas para visualização, não editáveis)
            document.getElementById('numero_lote').value = card.dataset.numeroLote;
            document.getElementById('numero_lote').readOnly = true;
            document.getElementById('data_validade').value = card.dataset.dataValidade;
            document.getElementById('data_validade').readOnly = true;
            document.getElementById('data_recebimento').value = card.dataset.dataRecebimento;
            document.getElementById('data_recebimento').readOnly = true;
            document.getElementById('observacoes').value = card.dataset.observacoes || '';
            document.getElementById('observacoes').readOnly = true;
            
            // Marcar que está usando lote existente
            document.getElementById('lote_existente_id').value = card.dataset.loteId;
            document.getElementById('usar_lote_existente').value = '1';
            
            // Focar no campo de quantidade
            document.getElementById('quantidade_total').focus();
        }

        function criarNovoLote() {
            // Limpar seleção
            document.querySelectorAll('.lote-card').forEach(c => {
                c.classList.remove('ring-2', 'ring-primary-500', 'border-primary-500');
            });
            
            document.getElementById('lote_existente_id').value = '';
            document.getElementById('usar_lote_existente').value = '0';
            
            // Limpar e habilitar campos
            const campos = ['numero_lote', 'data_validade', 'data_recebimento', 'observacoes'];
            campos.forEach(campo => {
                const el = document.getElementById(campo);
                if (el) {
                    if (campo === 'data_recebimento') {
                        el.value = '<?php echo date('Y-m-d'); ?>';
                    } else {
                        el.value = '';
                    }
                    el.readOnly = false;
                }
            });
            
            document.getElementById('quantidade_total').value = '1';
            document.getElementById('numero_lote').focus();
        }
        
        // Sincronizar select e input de código de barras
        const codigoBarrasSelect = document.getElementById('codigo_barras_select');
        const codigoBarrasInput = document.getElementById('codigo_barras_input');
        let timeoutBuscarLotes = null;
        
        if (codigoBarrasSelect && codigoBarrasInput) {
            // Função para atualizar o campo hidden e buscar lotes
            function atualizarCodigoBarras(codigo) {
                const codigoBarrasHidden = document.getElementById('codigo_barras_hidden');
                if (codigoBarrasHidden) {
                    codigoBarrasHidden.value = codigo;
                }
                if (codigo && codigo.trim() !== '') {
                    buscarLotesExistentes(codigo);
                } else {
                    const lotesContainer = document.getElementById('lotesExistentesContainer');
                    if (lotesContainer) {
                        lotesContainer.classList.add('hidden');
                    }
                }
            }
            
            // Quando selecionar um código existente, preencher o input e buscar lotes
            codigoBarrasSelect.addEventListener('change', function() {
                if (this.value !== '') {
                    codigoBarrasInput.value = this.value;
                    atualizarCodigoBarras(this.value);
                    codigoBarrasInput.focus();
                } else {
                    codigoBarrasInput.value = '';
                    atualizarCodigoBarras('');
                }
            });
            
            // Quando digitar no input, limpar select se diferente e buscar lotes
            codigoBarrasInput.addEventListener('input', function() {
                if (codigoBarrasSelect && codigoBarrasSelect.value !== '' && codigoBarrasSelect.value !== this.value.trim()) {
                    codigoBarrasSelect.value = '';
                }
                
                // Atualizar campo hidden imediatamente
                atualizarCodigoBarras(this.value.trim());
                
                // Limpar timeout anterior
                if (timeoutBuscarLotes) {
                    clearTimeout(timeoutBuscarLotes);
                }
                
                // Buscar lotes após 500ms de inatividade
                const codigo = this.value.trim();
                timeoutBuscarLotes = setTimeout(() => {
                    if (codigo.length >= 3) {
                        buscarLotesExistentes(codigo);
                    } else {
                        const lotesContainer = document.getElementById('lotesExistentesContainer');
                        if (lotesContainer) {
                            lotesContainer.classList.add('hidden');
                        }
                    }
                }, 500);
            });
            
            // Se já tiver código de barras inicial, buscar lotes
            if (codigoBarrasInput.value.trim() !== '') {
                buscarLotesExistentes(codigoBarrasInput.value.trim());
            }
        }
        
        // Função para buscar lotes existentes via API
        async function buscarLotesExistentes(codigoBarras) {
            if (!codigoBarras || codigoBarras.trim() === '' || !medId) {
                const lotesContainer = document.getElementById('lotesExistentesContainer');
                if (lotesContainer) {
                    lotesContainer.classList.add('hidden');
                }
                return;
            }
            
            try {
                const response = await fetch(`api/buscar_lotes_por_codigo.php?medicamento_id=${medId}&codigo_barras=${encodeURIComponent(codigoBarras)}`);
                const data = await response.json();
                
                if (data.success && data.lotes && data.lotes.length > 0) {
                    mostrarLotesExistentes(data.lotes);
                } else {
                    const lotesContainer = document.getElementById('lotesExistentesContainer');
                    if (lotesContainer) {
                        lotesContainer.classList.add('hidden');
                    }
                }
            } catch (error) {
                console.error('Erro ao buscar lotes:', error);
                const lotesContainer = document.getElementById('lotesExistentesContainer');
                if (lotesContainer) {
                    lotesContainer.classList.add('hidden');
                }
            }
        }
        
        // Função para mostrar lotes existentes dinamicamente
        function mostrarLotesExistentes(lotes) {
            const lotesContainer = document.getElementById('lotesExistentesContainer');
            const lotesLista = document.getElementById('lotesExistentesLista');
            
            if (!lotesContainer || !lotesLista) {
                // Criar container se não existir
                const medicamentoSection = document.querySelector('.glass-card');
                if (medicamentoSection && !lotesContainer) {
                    const novoContainer = document.createElement('section');
                    novoContainer.id = 'lotesExistentesContainer';
                    novoContainer.className = 'glass-card p-6';
                    novoContainer.innerHTML = `
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-slate-900">Lotes existentes para este código de barras</h3>
                            <button type="button" id="btnNovoLote" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                                Criar novo lote
                            </button>
                        </div>
                        <div id="lotesExistentesLista" class="space-y-3"></div>
                    `;
                    medicamentoSection.insertAdjacentElement('afterend', novoContainer);
                    
                    // Adicionar listener ao botão (se não existir já)
                    const btnNovoLote = document.getElementById('btnNovoLote');
                    if (btnNovoLote && !btnNovoLote.dataset.listenerAdded) {
                        btnNovoLote.addEventListener('click', criarNovoLote);
                        btnNovoLote.dataset.listenerAdded = 'true';
                    }
                }
            }
            
            const container = document.getElementById('lotesExistentesContainer');
            const lista = document.getElementById('lotesExistentesLista');
            
            if (!container || !lista) return;
            
            lista.innerHTML = '';
            
            lotes.forEach(lote => {
                const diasVenc = parseInt(lote.dias_para_vencer) || 0;
                let badgeClass = 'bg-emerald-100 text-emerald-600';
                let badgeText = `Vence em ${diasVenc} dias`;
                
                if (diasVenc < 0) {
                    badgeClass = 'bg-rose-100 text-rose-600';
                    badgeText = `Vencido há ${Math.abs(diasVenc)} dias`;
                } else if (diasVenc <= 30) {
                    badgeClass = 'bg-amber-100 text-amber-700';
                } else if (diasVenc <= 90) {
                    badgeClass = 'bg-sky-100 text-sky-700';
                }
                
                const loteCard = document.createElement('div');
                loteCard.className = 'lote-card bg-white rounded-xl p-3 border border-sky-200 hover:border-sky-300 cursor-pointer transition';
                loteCard.dataset.loteId = lote.id;
                loteCard.dataset.numeroLote = lote.numero_lote;
                loteCard.dataset.dataValidade = lote.data_validade;
                loteCard.dataset.dataRecebimento = lote.data_recebimento;
                loteCard.dataset.observacoes = lote.observacoes || '';
                loteCard.innerHTML = `
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-semibold text-slate-900">Lote: ${lote.numero_lote}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ${badgeClass}">
                                    ${badgeText}
                                </span>
                            </div>
                            <div class="text-xs text-slate-600 space-y-0.5">
                                <p>Validade: ${lote.data_validade_formatada}</p>
                                <p>Recebimento: ${lote.data_recebimento_formatada}</p>
                                <p>Qtd. Atual: <span class="font-semibold">${lote.quantidade_atual}</span></p>
                            </div>
                        </div>
                    </div>
                `;
                
                loteCard.addEventListener('click', () => selecionarLoteExistente(loteCard));
                lista.appendChild(loteCard);
            });
            
            container.classList.remove('hidden');
        }
        
        // Variável global para medId
        const medId = <?php echo $med_id; ?>;
        
        // Adicionar listener ao botão "Criar novo lote" se já existir na página
        document.addEventListener('DOMContentLoaded', function() {
            const btnNovoLote = document.getElementById('btnNovoLote');
            if (btnNovoLote && !btnNovoLote.dataset.listenerAdded) {
                btnNovoLote.addEventListener('click', criarNovoLote);
                btnNovoLote.dataset.listenerAdded = 'true';
            }
        });
        
        // Garantir que o formulário envie o código de barras correto
        const formLote = document.getElementById('formLote');
        if (formLote) {
            formLote.addEventListener('submit', function(e) {
                const codigoBarrasInput = document.getElementById('codigo_barras_input');
                const codigoBarrasHidden = document.getElementById('codigo_barras_hidden');
                if (codigoBarrasInput && codigoBarrasHidden) {
                    codigoBarrasHidden.value = codigoBarrasInput.value.trim();
                }
            });
        }
        
        // Controlar visibilidade da caixa de observações
        const checkboxObservacoes = document.getElementById('mostrar_observacoes');
        const containerObservacoes = document.getElementById('observacoes_container');
        
        if (checkboxObservacoes && containerObservacoes) {
            checkboxObservacoes.addEventListener('change', function() {
                if (this.checked) {
                    containerObservacoes.classList.remove('hidden');
                    document.getElementById('observacoes').focus();
                } else {
                    containerObservacoes.classList.add('hidden');
                    document.getElementById('observacoes').value = '';
                }
            });
        }
    </script>
</body>
</html>

