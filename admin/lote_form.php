<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

$med_id = isset($_GET['med_id']) ? (int)$_GET['med_id'] : 0;
$lote_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditing = $lote_id > 0;

if ($med_id <= 0) {
    $_SESSION['error'] = "ID de medicamento inválido.";
    header("Location: medicamentos.php");
    exit;
}

// Buscar informações do medicamento
try {
    $stmt = $conn->prepare("SELECT id, nome FROM medicamentos WHERE id = ?");
    $stmt->execute([$med_id]);
    $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$medicamento) {
        $_SESSION['error'] = "Medicamento não encontrado.";
        header("Location: medicamentos.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erro ao buscar medicamento: " . $e->getMessage();
    header("Location: medicamentos.php");
    exit;
}

// Dados padrão do lote
$lote = [
    'id' => 0,
    'medicamento_id' => $med_id,
    'numero_lote' => '',
    'data_recebimento' => date('Y-m-d'),
    'data_validade' => '',
    'quantidade_caixas' => 1,
    'quantidade_por_caixa' => 1,
    'quantidade_total' => 0,
    'quantidade_atual' => 0,
    'fornecedor' => '',
    'nota_fiscal' => '',
    'observacoes' => ''
];

// Se estiver editando, buscar dados do lote
if ($isEditing) {
    try {
        $stmt = $conn->prepare("SELECT * FROM lotes WHERE id = ? AND medicamento_id = ?");
        $stmt->execute([$lote_id, $med_id]);
        $loteDb = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($loteDb) {
            $lote = array_merge($lote, $loteDb);
        } else {
            $_SESSION['error'] = "Lote não encontrado.";
            header("Location: medicamentos_view.php?id=$med_id");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erro ao buscar lote: " . $e->getMessage();
        header("Location: medicamentos_view.php?id=$med_id");
        exit;
    }
}

$errors = [];

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verificarCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Token de segurança inválido.';
    } else {
        $lote['numero_lote'] = trim($_POST['numero_lote'] ?? '');
        $lote['data_recebimento'] = trim($_POST['data_recebimento'] ?? '');
        $lote['data_validade'] = trim($_POST['data_validade'] ?? '');
        $lote['quantidade_caixas'] = max(1, (int)($_POST['quantidade_caixas'] ?? 1));
        $lote['quantidade_por_caixa'] = max(1, (int)($_POST['quantidade_por_caixa'] ?? 1));
        $lote['quantidade_total'] = (int)($_POST['quantidade_total'] ?? 0);
        $lote['quantidade_atual'] = $isEditing ? (int)($_POST['quantidade_atual'] ?? 0) : $lote['quantidade_total'];
        $lote['fornecedor'] = trim($_POST['fornecedor'] ?? '');
        $lote['nota_fiscal'] = trim($_POST['nota_fiscal'] ?? '');
        $lote['observacoes'] = trim($_POST['observacoes'] ?? '');
        
        // Validações
        if (empty($lote['numero_lote'])) {
            $errors[] = 'O número do lote é obrigatório.';
        }
        if (empty($lote['data_recebimento'])) {
            $errors[] = 'A data de recebimento é obrigatória.';
        }
        if (empty($lote['data_validade'])) {
            $errors[] = 'A data de validade é obrigatória.';
        }
        if ($lote['quantidade_total'] <= 0) {
            $errors[] = 'A quantidade total deve ser maior que zero.';
        }
        if ($isEditing && $lote['quantidade_atual'] < 0) {
            $errors[] = 'A quantidade atual não pode ser negativa.';
        }
        
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                if ($isEditing) {
                    // Buscar quantidade atual anterior para ajustar estoque
                    $stmt = $conn->prepare("SELECT quantidade_atual FROM lotes WHERE id = ?");
                    $stmt->execute([$lote_id]);
                    $loteAnterior = $stmt->fetch(PDO::FETCH_ASSOC);
                    $diferencaEstoque = $lote['quantidade_atual'] - $loteAnterior['quantidade_atual'];
                    
                    // Atualizar lote
                    $sql = "UPDATE lotes SET 
                            numero_lote = ?, data_recebimento = ?, data_validade = ?, 
                            quantidade_caixas = ?, quantidade_por_caixa = ?, quantidade_total = ?, 
                            quantidade_atual = ?, fornecedor = ?, nota_fiscal = ?, observacoes = ?, 
                            atualizado_em = NOW() 
                            WHERE id = ? AND medicamento_id = ?";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $lote['numero_lote'], $lote['data_recebimento'], $lote['data_validade'],
                        $lote['quantidade_caixas'], $lote['quantidade_por_caixa'], $lote['quantidade_total'],
                        $lote['quantidade_atual'], $lote['fornecedor'], $lote['nota_fiscal'], $lote['observacoes'],
                        $lote_id, $med_id
                    ]);
                    
                    // Atualizar estoque do medicamento
                    if ($diferencaEstoque != 0) {
                        $stmt = $conn->prepare("UPDATE medicamentos SET estoque_atual = estoque_atual + ?, atualizado_em = NOW() WHERE id = ?");
                        $stmt->execute([$diferencaEstoque, $med_id]);
                    }
                    
                    $_SESSION['success'] = "Lote atualizado com sucesso!";
                } else {
                    // Inserir novo lote
                    $sql = "INSERT INTO lotes (medicamento_id, numero_lote, data_recebimento, data_validade, 
                            quantidade_caixas, quantidade_por_caixa, quantidade_total, quantidade_atual, 
                            fornecedor, nota_fiscal, observacoes, criado_em) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $med_id, $lote['numero_lote'], $lote['data_recebimento'], $lote['data_validade'],
                        $lote['quantidade_caixas'], $lote['quantidade_por_caixa'], $lote['quantidade_total'], $lote['quantidade_atual'],
                        $lote['fornecedor'], $lote['nota_fiscal'], $lote['observacoes']
                    ]);
                    
                    // Atualizar estoque do medicamento
                    $stmt = $conn->prepare("UPDATE medicamentos SET estoque_atual = estoque_atual + ?, atualizado_em = NOW() WHERE id = ?");
                    $stmt->execute([$lote['quantidade_total'], $med_id]);
                    
                    $_SESSION['success'] = "Lote cadastrado com sucesso!";
                }
                
                $conn->commit();
                header("Location: medicamentos_view.php?id=$med_id");
                exit;
                
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = 'Erro ao salvar lote: ' . $e->getMessage();
            }
        }
    }
}

$successMessage = getSuccessMessage();
$errorMessage = getErrorMessage();
$csrfToken = gerarCSRFToken();

$pageTitle = $isEditing ? 'Editar Lote' : 'Novo Lote';
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
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?> - <?php echo SYSTEM_NAME; ?>">
    <meta property="og:description" content="Sistema de gestão de farmácia">
    <meta property="og:type" content="website">
    <meta property="og:image" content="../images/logo.svg">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="../images/logo.svg">
    <link rel="apple-touch-icon" href="../images/logo.svg">
    
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
        <main class="flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-12 lg:py-10 space-y-6 lg:space-y-8">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-3 text-sm text-slate-500">
                <a href="medicamentos.php" class="hover:text-primary-600 transition-colors">Medicamentos</a>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="medicamentos_view.php?id=<?php echo $med_id; ?>" class="hover:text-primary-600 transition-colors"><?php echo htmlspecialchars($medicamento['nome']); ?></a>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-slate-900 font-medium"><?php echo $isEditing ? 'Editar Lote' : 'Novo Lote'; ?></span>
            </div>

            <!-- Header -->
            <header>
                <div class="space-y-2 sm:space-y-3">
                    <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Lotes</span>
                    <div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="mt-2 text-sm sm:text-base text-slate-500 max-w-2xl">
                            <?php echo $isEditing ? 'Atualize as informações do lote' : 'Cadastre um novo lote para o medicamento'; ?> 
                            <span class="font-semibold text-primary-600"><?php echo htmlspecialchars($medicamento['nome']); ?></span>
                        </p>
                    </div>
                </div>
            </header>

            <?php if (!empty($errors)): ?>
                <div class="glass-card border border-rose-200/60 bg-rose-50/80 px-6 py-4">
                    <strong class="block text-sm font-semibold text-rose-700">Atenção</strong>
                    <ul class="text-sm text-rose-700 mt-2 space-y-1">
                        <?php foreach ($errors as $error): ?>
                            <li>• <?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Formulário -->
            <form method="post" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <!-- Identificação do Lote -->
                <section class="glass-card p-6 space-y-6">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            Identificação do Lote
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">Informações de rastreabilidade do lote</p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Número do Lote <span class="text-rose-500">*</span></span>
                            <input type="text" name="numero_lote" value="<?php echo htmlspecialchars($lote['numero_lote']); ?>" required class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" placeholder="Ex: L123456">
                        </label>

                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Data de Recebimento <span class="text-rose-500">*</span></span>
                            <input type="date" name="data_recebimento" value="<?php echo htmlspecialchars($lote['data_recebimento']); ?>" required class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                        </label>

                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Data de Validade <span class="text-rose-500">*</span></span>
                            <input type="date" name="data_validade" value="<?php echo htmlspecialchars($lote['data_validade']); ?>" required class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                        </label>
                    </div>
                </section>

                <!-- Quantidades -->
                <section class="glass-card p-6 space-y-6">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            Quantidades
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">Defina as quantidades recebidas e disponíveis</p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Quantidade de Caixas <span class="text-rose-500">*</span></span>
                            <input type="number" name="quantidade_caixas" id="qtd_caixas" value="<?php echo $lote['quantidade_caixas']; ?>" min="1" required class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                        </label>

                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Unidades por Caixa <span class="text-rose-500">*</span></span>
                            <input type="number" name="quantidade_por_caixa" id="qtd_por_caixa" value="<?php echo $lote['quantidade_por_caixa']; ?>" min="1" required class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                        </label>

                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Quantidade Total <span class="text-rose-500">*</span></span>
                            <input type="number" name="quantidade_total" id="qtd_total" value="<?php echo $lote['quantidade_total']; ?>" min="1" required readonly class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                            <span class="text-xs text-slate-400">Calculado automaticamente</span>
                        </label>

                        <?php if ($isEditing): ?>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Quantidade Atual <span class="text-rose-500">*</span></span>
                            <input type="number" name="quantidade_atual" value="<?php echo $lote['quantidade_atual']; ?>" min="0" required class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                            <span class="text-xs text-slate-400">Quantidade disponível</span>
                        </label>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Informações Adicionais -->
                <section class="glass-card p-6 space-y-6">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Informações Adicionais
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">Dados complementares do lote (opcional)</p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Fornecedor</span>
                            <input type="text" name="fornecedor" value="<?php echo htmlspecialchars($lote['fornecedor']); ?>" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" placeholder="Nome do fornecedor">
                        </label>

                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Nota Fiscal</span>
                            <input type="text" name="nota_fiscal" value="<?php echo htmlspecialchars($lote['nota_fiscal']); ?>" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" placeholder="Número da NF">
                        </label>
                    </div>

                    <label class="flex flex-col gap-2">
                        <span class="text-sm font-medium text-slate-700">Observações</span>
                        <textarea name="observacoes" rows="3" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" placeholder="Informações adicionais sobre o lote..."><?php echo htmlspecialchars($lote['observacoes']); ?></textarea>
                    </label>
                </section>

                <!-- Botões -->
                <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
                    <a href="medicamentos_view.php?id=<?php echo $med_id; ?>" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-6 py-3 text-slate-600 font-semibold shadow hover:shadow-lg transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Cancelar
                    </a>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-8 py-3 text-white font-semibold shadow-lg hover:bg-primary-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <?php echo $isEditing ? 'Atualizar Lote' : 'Cadastrar Lote'; ?>
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
    <script>
        // Calcular automaticamente a quantidade total
        const qtdCaixas = document.getElementById('qtd_caixas');
        const qtdPorCaixa = document.getElementById('qtd_por_caixa');
        const qtdTotal = document.getElementById('qtd_total');
        
        function calcularTotal() {
            const caixas = parseInt(qtdCaixas.value) || 0;
            const porCaixa = parseInt(qtdPorCaixa.value) || 0;
            qtdTotal.value = caixas * porCaixa;
        }
        
        qtdCaixas?.addEventListener('input', calcularTotal);
        qtdPorCaixa?.addEventListener('input', calcularTotal);
        
        // Calcular no carregamento da página
        calcularTotal();
    </script>
</body>
</html>

