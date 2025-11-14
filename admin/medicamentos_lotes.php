<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Aceitar med_id via GET ou POST (quando vem do formulário de lotes.php)
$med_id = isset($_GET['med_id']) ? (int)$_GET['med_id'] : (isset($_POST['med_id']) ? (int)$_POST['med_id'] : 0);
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if ($med_id <= 0) {
    $_SESSION['error'] = "ID de medicamento inválido.";
    header("Location: medicamentos.php");
    exit;
}

$medicamento = [];
$lotes = [];
$lote_edit = null;

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verificarCSRFToken($csrfToken)) {
        $_SESSION['error'] = 'Token de segurança inválido. Atualize a página e tente novamente.';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'add_lote':
                    $codigo_barras = trim($_POST['codigo_barras'] ?? '');
                    $numero_lote = trim($_POST['numero_lote'] ?? '');
                    $data_recebimento = trim($_POST['data_recebimento'] ?? '');
                    $data_validade = trim($_POST['data_validade'] ?? '');
                    $quantidade_total = (int)($_POST['quantidade_total'] ?? 0);
                    $fornecedor = trim($_POST['fornecedor'] ?? '');
                    $nota_fiscal = trim($_POST['nota_fiscal'] ?? '');
                    $observacoes = trim($_POST['observacoes'] ?? '');
                    
                    if (empty($codigo_barras)) {
                        throw new RuntimeException("O código de barras é obrigatório.");
                    }
                    if (empty($numero_lote)) {
                        throw new RuntimeException("O número do lote é obrigatório.");
                    }
                    if (empty($data_recebimento) || empty($data_validade)) {
                        throw new RuntimeException("As datas de recebimento e validade são obrigatórias.");
                    }
                    if ($quantidade_total <= 0) {
                        throw new RuntimeException("A quantidade total deve ser maior que zero.");
                    }
                    
                    $conn->beginTransaction();
                    
                    // 1. Verificar se o código de barras já existe para este medicamento
                    $stmt = $conn->prepare("SELECT id FROM codigos_barras WHERE medicamento_id = ? AND codigo = ?");
                    $stmt->execute([$med_id, $codigo_barras]);
                    $codigo_barras_existente = $stmt->fetch();
                    
                    if ($codigo_barras_existente) {
                        $codigo_barras_id = $codigo_barras_existente['id'];
                    } else {
                        // 2. Criar novo código de barras para este medicamento
                        $stmt = $conn->prepare("INSERT INTO codigos_barras (medicamento_id, codigo) VALUES (?, ?)");
                        $stmt->execute([$med_id, $codigo_barras]);
                        $codigo_barras_id = (int)$conn->lastInsertId();
                    }
                    
                    // 3. Verificar se o lote já existe para este código de barras e medicamento
                    $stmt = $conn->prepare("SELECT id, quantidade_atual FROM lotes WHERE codigo_barras_id = ? AND medicamento_id = ? AND numero_lote = ?");
                    $stmt->execute([$codigo_barras_id, $med_id, $numero_lote]);
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
                                fornecedor = ?,
                                nota_fiscal = ?,
                                observacoes = ?,
                                atualizado_em = NOW()
                                WHERE id = ?";
                        
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $data_recebimento, $data_validade, $nova_quantidade_atual,
                            $fornecedor, $nota_fiscal, $observacoes, $lote_id
                        ]);
                    } else {
                        // 4. Inserir novo lote (ligado ao código de barras)
                        // quantidade_caixas e quantidade_por_caixa não são mais usados (valores padrão 0)
                        $sql = "INSERT INTO lotes (codigo_barras_id, medicamento_id, numero_lote, data_recebimento, data_validade, 
                                quantidade_atual, fornecedor, nota_fiscal, observacoes, criado_em) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $codigo_barras_id, $med_id, $numero_lote, $data_recebimento, $data_validade,
                            $quantidade_total, $fornecedor, $nota_fiscal, $observacoes
                        ]);
                    }
                    
                    // 5. Atualizar estoque do medicamento (soma de todos os lotes de todos os códigos de barras)
                    $stmt = $conn->prepare("
                        SELECT COALESCE(SUM(quantidade_atual), 0) as estoque_total
                        FROM lotes 
                        WHERE medicamento_id = ?
                    ");
                    $stmt->execute([$med_id]);
                    $estoque_total = (int)$stmt->fetchColumn();
                    
                    $sql = "UPDATE medicamentos SET estoque_atual = ?, atualizado_em = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$estoque_total, $med_id]);
                    
                    $conn->commit();
                    
                    if ($lote_existente) {
                        $_SESSION['success'] = "Lote reutilizado e atualizado com sucesso! A quantidade foi adicionada ao lote existente.";
                    } else {
                        $_SESSION['success'] = "Lote adicionado com sucesso!";
                    }
                    
                    // Se veio de lotes.php, redirecionar de volta
                    if (isset($_POST['from_lotes']) && $_POST['from_lotes'] === '1') {
                        header("Location: lotes.php");
                    } else {
                        header("Location: medicamentos_lotes.php?med_id=$med_id");
                    }
                    exit;
                    
                case 'edit_lote':
                    $lote_id = (int)$_POST['lote_id'];
                    $codigo_barras = trim($_POST['codigo_barras'] ?? '');
                    $numero_lote = trim($_POST['numero_lote'] ?? '');
                    $data_recebimento = trim($_POST['data_recebimento'] ?? '');
                    $data_validade = trim($_POST['data_validade'] ?? '');
                    $quantidade_total = (int)($_POST['quantidade_total'] ?? 0);
                    $quantidade_atual = (int)($_POST['quantidade_atual'] ?? 0);
                    $fornecedor = trim($_POST['fornecedor'] ?? '');
                    $nota_fiscal = trim($_POST['nota_fiscal'] ?? '');
                    $observacoes = trim($_POST['observacoes'] ?? '');
                    
                    if (empty($codigo_barras)) {
                        throw new RuntimeException("O código de barras é obrigatório.");
                    }
                    if (empty($numero_lote)) {
                        throw new RuntimeException("O número do lote é obrigatório.");
                    }
                    if (empty($data_recebimento) || empty($data_validade)) {
                        throw new RuntimeException("As datas de recebimento e validade são obrigatórias.");
                    }
                    if ($quantidade_total <= 0) {
                        throw new RuntimeException("A quantidade total deve ser maior que zero.");
                    }
                    if ($quantidade_atual < 0) {
                        throw new RuntimeException("A quantidade atual não pode ser negativa.");
                    }
                    
                    // Verificar se o lote existe
                    $stmt = $conn->prepare("SELECT id, quantidade_atual, codigo_barras_id FROM lotes WHERE id = ? AND medicamento_id = ?");
                    $stmt->execute([$lote_id, $med_id]);
                    $lote_original = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$lote_original) {
                        throw new RuntimeException("Lote não encontrado.");
                    }
                    
                    $conn->beginTransaction();
                    
                    // 1. Verificar/criar código de barras
                    $stmt = $conn->prepare("SELECT id FROM codigos_barras WHERE medicamento_id = ? AND codigo = ?");
                    $stmt->execute([$med_id, $codigo_barras]);
                    $codigo_barras_existente = $stmt->fetch();
                    
                    if ($codigo_barras_existente) {
                        $codigo_barras_id = $codigo_barras_existente['id'];
                    } else {
                        // Criar novo código de barras
                        $stmt = $conn->prepare("INSERT INTO codigos_barras (medicamento_id, codigo) VALUES (?, ?)");
                        $stmt->execute([$med_id, $codigo_barras]);
                        $codigo_barras_id = (int)$conn->lastInsertId();
                    }
                    
                    // 2. Verificar se o número do lote não está em uso por outro lote
                    $stmt = $conn->prepare("SELECT id FROM lotes WHERE codigo_barras_id = ? AND numero_lote = ? AND id != ?");
                    $stmt->execute([$codigo_barras_id, $numero_lote, $lote_id]);
                    
                    if ($stmt->fetch()) {
                        throw new RuntimeException("Já existe outro lote com este número para este código de barras.");
                    }
                    
                    // 3. Atualizar lote
                    $sql = "UPDATE lotes SET 
                            codigo_barras_id = ?, numero_lote = ?, data_recebimento = ?, data_validade = ?, 
                            quantidade_atual = ?, fornecedor = ?, nota_fiscal = ?, observacoes = ?, 
                            atualizado_em = NOW() 
                            WHERE id = ? AND medicamento_id = ?";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $codigo_barras_id, $numero_lote, $data_recebimento, $data_validade,
                        $quantidade_atual, $fornecedor, $nota_fiscal, $observacoes,
                        $lote_id, $med_id
                    ]);
                    
                    // 4. Recalcular estoque total do medicamento
                    $stmt = $conn->prepare("
                        SELECT COALESCE(SUM(quantidade_atual), 0) as estoque_total
                        FROM lotes 
                        WHERE medicamento_id = ?
                    ");
                    $stmt->execute([$med_id]);
                    $estoque_total = (int)$stmt->fetchColumn();
                    
                    $sql = "UPDATE medicamentos SET estoque_atual = ?, atualizado_em = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$estoque_total, $med_id]);
                    
                    $conn->commit();
                    $_SESSION['success'] = "Lote atualizado com sucesso!";
                    
                    header("Location: medicamentos_lotes.php?med_id=$med_id");
                    exit;
                    
                case 'delete_lote':
                    $lote_id = (int)$_POST['lote_id'];
                    
                    // Verificar se o lote existe
                    $stmt = $conn->prepare("SELECT id, quantidade_atual FROM lotes WHERE id = ? AND medicamento_id = ?");
                    $stmt->execute([$lote_id, $med_id]);
                    $lote = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$lote) {
                        throw new RuntimeException("Lote não encontrado.");
                    }
                    
                    $conn->beginTransaction();
                    
                    // Remover lote
                    $stmt = $conn->prepare("DELETE FROM lotes WHERE id = ?");
                    $stmt->execute([$lote_id]);
                    
                    // Recalcular estoque total do medicamento
                    $stmt = $conn->prepare("
                        SELECT COALESCE(SUM(quantidade_atual), 0) as estoque_total
                        FROM lotes 
                        WHERE medicamento_id = ?
                    ");
                    $stmt->execute([$med_id]);
                    $estoque_total = (int)$stmt->fetchColumn();
                    
                    $sql = "UPDATE medicamentos SET estoque_atual = ?, atualizado_em = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$estoque_total, $med_id]);
                    
                    $conn->commit();
                    $_SESSION['success'] = "Lote excluído com sucesso!";
                    
                    header("Location: medicamentos_lotes.php?med_id=$med_id");
                    exit;
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error'] = $e->getMessage();
            
            header("Location: medicamentos_lotes.php?med_id=$med_id");
            exit;
        }
    }
}

try {
    // Buscar informações do medicamento
    $sql = "SELECT m.*, 
                  c.nome AS categoria_nome,
                  u.nome AS unidade_nome,
                  u.sigla AS unidade_sigla
           FROM medicamentos m
           LEFT JOIN categorias c ON m.categoria_id = c.id
           LEFT JOIN apresentacoes u ON m.apresentacao_id = u.id
           WHERE m.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$med_id]);
    $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$medicamento) {
        $_SESSION['error'] = "Medicamento não encontrado.";
        header("Location: medicamentos.php");
        exit;
    }
    
    // Buscar códigos de barras do medicamento
    $stmt = $conn->prepare("SELECT id, codigo FROM codigos_barras WHERE medicamento_id = ? ORDER BY codigo ASC");
    $stmt->execute([$med_id]);
    $codigos_barras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar lotes do medicamento (através dos códigos de barras)
    $sql = "SELECT l.*, 
                  cb.codigo AS codigo_barras,
                  DATEDIFF(l.data_validade, CURDATE()) AS dias_para_vencer
           FROM lotes l
           LEFT JOIN codigos_barras cb ON l.codigo_barras_id = cb.id
           WHERE l.medicamento_id = ? 
           ORDER BY l.data_validade ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$med_id]);
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se estiver editando um lote, carregar dados
    if ($edit_id > 0) {
        $stmt = $conn->prepare("
            SELECT l.*, cb.codigo AS codigo_barras
            FROM lotes l
            LEFT JOIN codigos_barras cb ON l.codigo_barras_id = cb.id
            WHERE l.id = ? AND l.medicamento_id = ?
        ");
        $stmt->execute([$edit_id, $med_id]);
        $lote_edit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lote_edit) {
            $_SESSION['error'] = "Lote não encontrado.";
            header("Location: medicamentos_lotes.php?med_id=$med_id");
            exit;
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erro ao buscar dados: " . $e->getMessage();
    header("Location: medicamentos.php");
    exit;
}

function validityBadgeClass(?int $dias): string
{
    if ($dias === null) {
        return 'bg-slate-100 text-slate-500';
    }

    if ($dias < 0) {
        return 'bg-rose-100 text-rose-600';
    }

    if ($dias <= ALERTA_VENCIMENTO_CRITICO) {
        return 'bg-rose-100 text-rose-600';
    }

    if ($dias <= ALERTA_VENCIMENTO_ATENCAO) {
        return 'bg-amber-100 text-amber-700';
    }

    if ($dias <= ALERTA_VENCIMENTO_NORMAL) {
        return 'bg-sky-100 text-sky-700';
    }

    return 'bg-emerald-100 text-emerald-600';
}

function stockBadgeClass(int $qtd): string
{
    if ($qtd <= 0) {
        return 'bg-rose-100 text-rose-600';
    }

    if ($qtd <= 10) {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-emerald-100 text-emerald-600';
}

$successMessage = getSuccessMessage();
$errorMessage = getErrorMessage();
$csrfToken = gerarCSRFToken();

$pageTitle = 'Gerenciar lotes do medicamento';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Controle de medicamentos, lotes, pacientes e receitas">
    <meta name="keywords" content="farmácia, medicamentos, gestão, controle de estoque, lotes">
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
        <main class="flex-1 px-6 py-10 lg:px-12 space-y-10">
            <header class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-3">
                    <span class="text-sm uppercase tracking-[0.3em] text-slate-500">Gerenciar lotes</span>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($medicamento['nome']); ?></h1>
                        <?php if (!empty($medicamento['descricao'])): ?>
                            <p class="mt-2 text-slate-500 max-w-2xl"><?php echo htmlspecialchars(substr($medicamento['descricao'], 0, 200)); ?><?php if (strlen($medicamento['descricao']) > 200) echo '...'; ?></p>
                        <?php endif; ?>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <?php if (!empty($medicamento['codigo_barras'])): ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4.5h.75M21 4.5h-.75M3 9h.75M21 9h-.75M3 13.5h.75M21 13.5h-.75M3 18h.75M21 18h-.75M9 3v18M15 3v18"/></svg>
                                    <?php echo htmlspecialchars($medicamento['codigo_barras']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($medicamento['fabricante_nome'])): ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-3 py-1 text-xs text-primary-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 21h16.5M4.5 3h15l2.25 18h-19.5L4.5 3z"/></svg>
                                    <?php echo htmlspecialchars($medicamento['fabricante_nome']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($medicamento['categoria_nome'])): ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-3 py-1 text-xs text-sky-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 6h.008v.008H6V6z"/></svg>
                                    <?php echo htmlspecialchars($medicamento['categoria_nome']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-3 lg:items-end">
                    <div class="glass-card px-6 py-4 text-center">
                        <p class="text-xs uppercase tracking-wide text-slate-500 mb-1">Estoque atual</p>
                        <?php
                            $estoque_atual = (int)$medicamento['estoque_atual'];
                            $estoque_minimo = (int)$medicamento['estoque_minimo'];
                            
                            if ($estoque_atual <= 0) {
                                $badge_class = 'bg-rose-100 text-rose-600';
                            } elseif ($estoque_atual <= $estoque_minimo) {
                                $badge_class = 'bg-amber-100 text-amber-700';
                            } else {
                                $badge_class = 'bg-emerald-100 text-emerald-600';
                            }
                        ?>
                        <p class="inline-flex items-center rounded-full px-4 py-2 text-2xl font-bold <?php echo $badge_class; ?>"><?php echo $estoque_atual; ?></p>
                        <?php if ($estoque_minimo > 0): ?>
                            <p class="text-xs text-slate-400 mt-2">Mínimo: <?php echo $estoque_minimo; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button onclick="document.getElementById('addLoteModal').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-6 py-3 text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6"/></svg>
                            Novo lote
                        </button>
                        <a href="medicamentos_view.php?id=<?php echo $med_id; ?>" class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-sky-600 font-semibold shadow hover:shadow-lg transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12s3.75-6.75 9.75-6.75 9.75 6.75 9.75 6.75-3.75 6.75-9.75 6.75S2.25 12 2.25 12z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15.375a3.375 3.375 0 1 0 0-6.75 3.375 3.375 0 0 0 0 6.75z"/></svg>
                            Ver detalhes
                        </a>
                        <a href="medicamentos.php" class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-slate-500 font-semibold shadow hover:shadow-lg transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                            Voltar
                        </a>
                    </div>
                </div>
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

            <section class="glass-card p-0 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-white/60 bg-white/70">
                    <h2 class="text-lg font-semibold text-slate-900">Lotes cadastrados</h2>
                    <span class="rounded-full bg-primary-50 px-4 py-1 text-sm font-medium text-primary-600"><?php echo count($lotes); ?> lotes</span>
                </div>
                <?php if (count($lotes) > 0): ?>
                    <!-- Desktop table -->
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-white/60">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-6 py-3">Código de Barras</th>
                                    <th class="px-6 py-3">Número do Lote</th>
                                    <th class="px-6 py-3">Recebimento</th>
                                    <th class="px-6 py-3">Validade</th>
                                    <th class="px-6 py-3">Qtd. Atual</th>
                                    <th class="px-6 py-3">Fornecedor</th>
                                    <th class="px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white/80">
                                <?php foreach ($lotes as $lote): ?>
                                    <?php
                                        $dias_para_vencer = isset($lote['dias_para_vencer']) ? (int)$lote['dias_para_vencer'] : null;
                                        $quantidade_atual = (int)($lote['quantidade_atual'] ?? 0);
                                    ?>
                                    <tr class="text-sm text-slate-600">
                                        <td class="px-6 py-4">
                                            <span class="font-mono text-xs text-slate-700"><?php echo !empty($lote['codigo_barras']) ? htmlspecialchars($lote['codigo_barras']) : '—'; ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($lote['numero_lote']); ?></span>
                                                <?php if (!empty($lote['nota_fiscal'])): ?>
                                                    <span class="text-xs text-slate-400 mt-1">NF: <?php echo htmlspecialchars($lote['nota_fiscal']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-slate-600"><?php echo htmlspecialchars(formatarData($lote['data_recebimento'])); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-1">
                                                <span class="text-sm text-slate-600"><?php echo htmlspecialchars(formatarData($lote['data_validade'])); ?></span>
                                                <?php if ($dias_para_vencer !== null): ?>
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold w-fit <?php echo validityBadgeClass($dias_para_vencer); ?>">
                                                        <?php 
                                                            if ($dias_para_vencer < 0) {
                                                                echo 'Vencido há ' . abs($dias_para_vencer) . ' dias';
                                                            } else {
                                                                echo 'Vence em ' . $dias_para_vencer . ' dias';
                                                            }
                                                        ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?php echo stockBadgeClass($quantidade_atual); ?>">
                                                <?php echo $quantidade_atual; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-slate-600"><?php echo !empty($lote['fornecedor']) ? htmlspecialchars($lote['fornecedor']) : '—'; ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="medicamentos_lotes.php?med_id=<?php echo $med_id; ?>&edit=<?php echo (int)$lote['id']; ?>" class="action-chip" title="Editar lote">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487 19.5 7.125m-2.638-2.638L9.75 14.25 7.5 16.5m12-9-5.25-5.25M7.5 16.5v2.25h2.25L18.75 9"/></svg>
                                                </a>
                                                <form method="post" class="inline" onsubmit="return confirm('Confirma a exclusão do lote <?php echo htmlspecialchars($lote['numero_lote'], ENT_QUOTES); ?>? Esta ação afetará o estoque do medicamento.');">
                                                    <input type="hidden" name="action" value="delete_lote">
                                                    <input type="hidden" name="lote_id" value="<?php echo (int)$lote['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <button type="submit" class="action-chip danger" title="Excluir lote">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 7.5h12M9 7.5V6a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 6v1.5m-6 0v10.5A1.5 1.5 0 0 0 10.5 21h3A1.5 1.5 0 0 0 15 19.5V7.5"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile cards -->
                    <div class="lg:hidden divide-y divide-slate-100">
                        <?php foreach ($lotes as $lote): ?>
                            <?php
                                $dias_para_vencer = isset($lote['dias_para_vencer']) ? (int)$lote['dias_para_vencer'] : null;
                                $quantidade_atual = (int)($lote['quantidade_atual'] ?? 0);
                            ?>
                            <div class="p-5 bg-white/80 space-y-3">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-xs text-slate-500 uppercase tracking-wide">Código de Barras</p>
                                        <p class="font-mono text-xs text-slate-700"><?php echo !empty($lote['codigo_barras']) ? htmlspecialchars($lote['codigo_barras']) : '—'; ?></p>
                                        <p class="text-xs text-slate-500 uppercase tracking-wide mt-2">Lote</p>
                                        <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($lote['numero_lote']); ?></p>
                                        <?php if (!empty($lote['nota_fiscal'])): ?>
                                            <p class="text-xs text-slate-400 mt-1">NF: <?php echo htmlspecialchars($lote['nota_fiscal']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?php echo stockBadgeClass($quantidade_atual); ?>">
                                        <?php echo $quantidade_atual; ?>
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-xs text-slate-500">Recebimento</p>
                                        <p class="text-sm text-slate-700"><?php echo htmlspecialchars(formatarData($lote['data_recebimento'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Qtd. Atual</p>
                                        <p class="text-sm font-semibold <?php echo $quantidade_atual <= 0 ? 'text-rose-600' : 'text-emerald-600'; ?>">
                                            <?php echo $quantidade_atual; ?>
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Validade</p>
                                    <p class="text-sm text-slate-700 mb-2"><?php echo htmlspecialchars(formatarData($lote['data_validade'])); ?></p>
                                    <?php if ($dias_para_vencer !== null): ?>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold <?php echo validityBadgeClass($dias_para_vencer); ?>">
                                            <?php 
                                                if ($dias_para_vencer < 0) {
                                                    echo 'Vencido há ' . abs($dias_para_vencer) . ' dias';
                                                } else {
                                                    echo 'Vence em ' . $dias_para_vencer . ' dias';
                                                }
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($lote['fornecedor'])): ?>
                                    <div>
                                        <p class="text-xs text-slate-500">Fornecedor</p>
                                        <p class="text-sm text-slate-700"><?php echo htmlspecialchars($lote['fornecedor']); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="flex gap-2 pt-2 border-t border-slate-100">
                                    <a href="medicamentos_lotes.php?med_id=<?php echo $med_id; ?>&edit=<?php echo (int)$lote['id']; ?>" class="action-chip flex-1 justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487 19.5 7.125m-2.638-2.638L9.75 14.25 7.5 16.5m12-9-5.25-5.25M7.5 16.5v2.25h2.25L18.75 9"/></svg>
                                        Editar
                                    </a>
                                    <form method="post" class="flex-1" onsubmit="return confirm('Confirma a exclusão do lote?');">
                                        <input type="hidden" name="action" value="delete_lote">
                                        <input type="hidden" name="lote_id" value="<?php echo (int)$lote['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="action-chip danger w-full justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 7.5h12M9 7.5V6a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 6v1.5m-6 0v10.5A1.5 1.5 0 0 0 10.5 21h3A1.5 1.5 0 0 0 15 19.5V7.5"/></svg>
                                            Excluir
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m3 7.5 9-4.5 9 4.5m-18 0 9 4.5m9-4.5-9 4.5m0 9v-9"/></svg>
                        <p class="text-sm">Nenhum lote cadastrado para este medicamento.</p>
                        <button onclick="document.getElementById('addLoteModal').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-5 py-2 text-white text-sm font-semibold shadow hover:bg-primary-500 transition">Cadastrar o primeiro lote</button>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Modal adicionar/editar lote -->
    <div id="<?php echo $lote_edit ? 'editLoteModal' : 'addLoteModal'; ?>" class="<?php echo $lote_edit ? '' : 'hidden'; ?> fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="glass-card w-full max-w-4xl my-8">
            <div class="flex items-center justify-between px-8 py-6 border-b border-white/60">
                <h3 class="text-2xl font-bold text-slate-900">
                    <?php echo $lote_edit ? 'Editar lote' : 'Adicionar novo lote'; ?>
                </h3>
                <button onclick="window.location.href='medicamentos_lotes.php?med_id=<?php echo $med_id; ?>'" type="button" class="rounded-full p-2 hover:bg-slate-100 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="post" class="p-8 space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="<?php echo $lote_edit ? 'edit_lote' : 'add_lote'; ?>">
                <?php if ($lote_edit): ?>
                    <input type="hidden" name="lote_id" value="<?php echo (int)$lote_edit['id']; ?>">
                <?php endif; ?>

                <div class="space-y-4">
                    <div>
                        <label for="codigo_barras_input" class="block text-sm font-medium text-slate-700 mb-2">Código de Barras <span class="text-rose-500">*</span></label>
                        <?php if (!empty($codigos_barras)): ?>
                            <select name="codigo_barras_select" id="codigo_barras" class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500 mb-2">
                                <option value="">Ou selecione um código existente</option>
                                <?php foreach ($codigos_barras as $cb): ?>
                                    <option value="<?php echo htmlspecialchars($cb['codigo']); ?>" <?php echo ($lote_edit && isset($lote_edit['codigo_barras']) && $lote_edit['codigo_barras'] == $cb['codigo']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cb['codigo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <input type="text" name="codigo_barras" id="codigo_barras_input" value="<?php echo $lote_edit && isset($lote_edit['codigo_barras']) ? htmlspecialchars($lote_edit['codigo_barras']) : ''; ?>" placeholder="Digite ou escaneie o código de barras" class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" required autofocus>
                        <span class="text-xs text-slate-400 mt-1 block">Se o código não existir, será criado automaticamente para este medicamento.</span>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="numero_lote" class="block text-sm font-medium text-slate-700 mb-2">Número do Lote <span class="text-rose-500">*</span></label>
                        <input type="text" name="numero_lote" id="numero_lote" value="<?php echo $lote_edit ? htmlspecialchars($lote_edit['numero_lote']) : ''; ?>" required class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="data_validade" class="block text-sm font-medium text-slate-700 mb-2">Data de Validade <span class="text-rose-500">*</span></label>
                        <input type="date" name="data_validade" id="data_validade" value="<?php echo $lote_edit ? $lote_edit['data_validade'] : ''; ?>" required class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <div>
                        <label for="data_recebimento" class="block text-sm font-medium text-slate-700 mb-2">Data de Recebimento <span class="text-rose-500">*</span></label>
                        <input type="date" name="data_recebimento" id="data_recebimento" value="<?php echo $lote_edit ? $lote_edit['data_recebimento'] : date('Y-m-d'); ?>" required class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <?php if ($lote_edit): ?>
                            <label for="quantidade_atual" class="block text-sm font-medium text-slate-700 mb-2">Qtd. Atual <span class="text-rose-500">*</span></label>
                            <input type="number" name="quantidade_atual" id="quantidade_atual" min="0" value="<?php echo (int)$lote_edit['quantidade_atual']; ?>" required class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                        <?php else: ?>
                            <label for="quantidade_total" class="block text-sm font-medium text-slate-700 mb-2">Quantidade (unidades) <span class="text-rose-500">*</span></label>
                            <input type="number" name="quantidade_total" id="quantidade_total" min="1" value="1" required class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                            <span class="text-xs text-slate-400 mt-1 block">Quantidade sempre em unidades.</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="fornecedor" class="block text-sm font-medium text-slate-700 mb-2">Fornecedor</label>
                        <input type="text" name="fornecedor" id="fornecedor" value="<?php echo $lote_edit ? htmlspecialchars($lote_edit['fornecedor'] ?? '') : ''; ?>" class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="nota_fiscal" class="block text-sm font-medium text-slate-700 mb-2">Nota Fiscal</label>
                        <input type="text" name="nota_fiscal" id="nota_fiscal" value="<?php echo $lote_edit ? htmlspecialchars($lote_edit['nota_fiscal']) : ''; ?>" class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <?php if ($lote_edit): ?>
                        <div>
                            <label for="fornecedor" class="block text-sm font-medium text-slate-700 mb-2">Fornecedor</label>
                            <input type="text" name="fornecedor" id="fornecedor" value="<?php echo htmlspecialchars($lote_edit['fornecedor'] ?? ''); ?>" class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="observacoes" class="block text-sm font-medium text-slate-700 mb-2">Observações</label>
                    <textarea name="observacoes" id="observacoes" rows="3" class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500"><?php echo $lote_edit ? htmlspecialchars($lote_edit['observacoes']) : ''; ?></textarea>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-8 py-3 text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
                        <?php echo $lote_edit ? 'Atualizar lote' : 'Adicionar lote'; ?>
                    </button>
                    <a href="medicamentos_lotes.php?med_id=<?php echo $med_id; ?>" class="inline-flex items-center gap-2 rounded-full bg-white px-8 py-3 text-slate-500 font-semibold shadow hover:shadow-lg transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18 18 6M6 6l12 12"/></svg>
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="js/sidebar.js" defer></script>
    <script>
        // Permitir digitar novo código de barras ou selecionar existente
        const codigoBarrasSelect = document.getElementById('codigo_barras');
        const codigoBarrasInput = document.getElementById('codigo_barras_input');
        
        if (codigoBarrasSelect && codigoBarrasInput) {
            // Quando selecionar um código existente, preencher o input
            codigoBarrasSelect.addEventListener('change', function() {
                if (this.value !== '') {
                    codigoBarrasInput.value = this.value;
                    codigoBarrasInput.focus();
                }
            });
            
            // Quando digitar no input, limpar o select se o valor for diferente
            codigoBarrasInput.addEventListener('input', function() {
                if (codigoBarrasSelect.value !== '' && codigoBarrasSelect.value !== this.value.trim()) {
                    codigoBarrasSelect.value = '';
                }
            });
            
            // Focar no input quando o modal abrir
            const modal = document.getElementById('addLoteModal') || document.getElementById('editLoteModal');
            if (modal) {
                // Observer para detectar quando o modal é exibido
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                            if (!modal.classList.contains('hidden') && codigoBarrasInput) {
                                setTimeout(() => {
                                    codigoBarrasInput.focus();
                                }, 100);
                            }
                        }
                    });
                });
                observer.observe(modal, { attributes: true });
                
                // Também focar se o modal já estiver visível
                if (!modal.classList.contains('hidden') && codigoBarrasInput) {
                    setTimeout(() => {
                        codigoBarrasInput.focus();
                    }, 100);
                }
            }
            
            // Focar quando clicar no botão de adicionar lote
            const addLoteButtons = document.querySelectorAll('[onclick*="addLoteModal"]');
            addLoteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    setTimeout(() => {
                        if (codigoBarrasInput) {
                            codigoBarrasInput.focus();
                        }
                    }, 200);
                });
            });
        }
        
        // Ao submeter o formulário, garantir que o valor correto seja enviado
        const form = document.querySelector('form[method="post"]');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (codigoBarrasSelect) {
                    // Desabilitar o select para não enviar valor duplicado
                    codigoBarrasSelect.disabled = true;
                }
            });
        }
    </script>
</body>
</html>
