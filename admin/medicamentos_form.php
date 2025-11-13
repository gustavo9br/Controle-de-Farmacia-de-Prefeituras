<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditing = $id > 0;

$errors = [];

$medicamento = [
    'id' => 0,
    'nome' => '',
    'descricao' => '',
    'categoria_id' => null,
    'apresentacao_id' => null,
    'estoque_minimo' => ESTOQUE_MINIMO_PADRAO,
    'ativo' => 1,
];

try {
    $categorias = $conn->query("SELECT id, nome FROM categorias WHERE ativo = 1 ORDER BY nome ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
    $apresentacoes = $conn->query("SELECT id, nome, sigla FROM apresentacoes WHERE ativo = 1 ORDER BY nome ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Erro ao carregar dados de referência: ' . $e->getMessage();
}

if ($isEditing && empty($errors)) {
    try {
        $stmt = $conn->prepare('SELECT * FROM medicamentos WHERE id = ?');
        $stmt->bindValue(1, $id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            $_SESSION['error'] = 'Medicamento não encontrado.';
            header('Location: medicamentos.php');
            exit;
        }

        $medicamento = array_merge($medicamento, $data);

        if (empty($medicamento['categoria_id']) && !empty($medicamento['categoria'])) {
            $stmt = $conn->prepare('SELECT id FROM categorias WHERE nome = ? LIMIT 1');
            $stmt->execute([$medicamento['categoria']]);
            if ($idFound = $stmt->fetchColumn()) {
                $medicamento['categoria_id'] = $idFound;
            }
        }
        if (empty($medicamento['apresentacao_id']) && !empty($medicamento['apresentacao'])) {
            $stmt = $conn->prepare('SELECT id FROM apresentacoes WHERE nome = ? LIMIT 1');
            $stmt->execute([$medicamento['apresentacao']]);
            if ($idFound = $stmt->fetchColumn()) {
                $medicamento['apresentacao_id'] = $idFound;
            }
        }
    } catch (PDOException $e) {
        $errors[] = 'Erro ao carregar dados do medicamento: ' . $e->getMessage();
    }
}

$csrfToken = gerarCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verificarCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Token de segurança inválido. Recarregue a página e tente novamente.';
    } else {
        $medicamento['nome'] = trim($_POST['nome'] ?? '');
        $medicamento['descricao'] = trim($_POST['descricao'] ?? '');
        $medicamento['categoria_id'] = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
        $medicamento['apresentacao_id'] = !empty($_POST['apresentacao_id']) ? (int)$_POST['apresentacao_id'] : null;
        $medicamento['estoque_minimo'] = max(0, (int)($_POST['estoque_minimo'] ?? ESTOQUE_MINIMO_PADRAO));
        $medicamento['ativo'] = isset($_POST['ativo']) ? 1 : 0;

        if (empty($medicamento['nome'])) {
            $errors[] = 'Informe o nome do medicamento.';
        }

        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                if ($isEditing) {
                    $sql = 'UPDATE medicamentos SET nome = ?, descricao = ?, categoria_id = ?, apresentacao_id = ?, estoque_minimo = ?, ativo = ?, atualizado_em = NOW() WHERE id = ?';
                    $stmt = $conn->prepare($sql);
                    $stmt->bindValue(1, $medicamento['nome']);
                    $stmt->bindValue(2, $medicamento['descricao']);
                    $stmt->bindValue(3, $medicamento['categoria_id'], $medicamento['categoria_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
                    $stmt->bindValue(4, $medicamento['apresentacao_id'], $medicamento['apresentacao_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
                    $stmt->bindValue(5, $medicamento['estoque_minimo'], PDO::PARAM_INT);
                    $stmt->bindValue(6, $medicamento['ativo'], PDO::PARAM_INT);
                    $stmt->bindValue(7, $id, PDO::PARAM_INT);
                    $stmt->execute();
                } else {
                    $sql = 'INSERT INTO medicamentos (nome, descricao, categoria_id, apresentacao_id, estoque_minimo, estoque_atual, ativo, criado_em, atualizado_em) VALUES (?, ?, ?, ?, ?, 0, ?, NOW(), NOW())';
                    $stmt = $conn->prepare($sql);
                    $stmt->bindValue(1, $medicamento['nome']);
                    $stmt->bindValue(2, $medicamento['descricao']);
                    $stmt->bindValue(3, $medicamento['categoria_id'], $medicamento['categoria_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
                    $stmt->bindValue(4, $medicamento['apresentacao_id'], $medicamento['apresentacao_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
                    $stmt->bindValue(5, $medicamento['estoque_minimo'], PDO::PARAM_INT);
                    $stmt->bindValue(6, $medicamento['ativo'], PDO::PARAM_INT);
                    $stmt->execute();
                    $medicamento['id'] = (int)$conn->lastInsertId();
                }

                $conn->commit();

                $_SESSION['success'] = $isEditing ? 'Medicamento atualizado com sucesso.' : 'Medicamento cadastrado com sucesso.';

                $acao = $_POST['acao'] ?? 'save';
                if ($acao === 'save_new') {
                    header('Location: medicamentos_form.php');
                } else {
                    header('Location: medicamentos.php');
                }
                exit;
            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $errors[] = 'Erro ao salvar os dados: ' . $e->getMessage();
            }
        }
    }

    $csrfToken = gerarCSRFToken();
}

$pageTitle = $isEditing ? 'Editar medicamento' : 'Novo medicamento';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Controle de medicamentos, lotes, pacientes e receitas">
    <meta name="keywords" content="farmácia, medicamentos, gestão, controle de estoque">
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
    <button id="mobileMenuButton" class="mobile-menu-button" aria-label="Abrir menu">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div id="mobileMenuOverlay" class="mobile-menu-overlay"></div>
    <div class="flex min-h-screen">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <main class="flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-12 lg:py-10 space-y-6 lg:space-y-8">
            <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Cadastro</span>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p class="text-sm sm:text-base text-slate-500 max-w-2xl">Registre um novo item do estoque institucional. As informações de lote e código de barras serão preenchidas nas telas específicas de entrada e saída.</p>
                </div>
                <div class="rounded-2xl sm:rounded-full bg-white/80 px-4 sm:px-5 py-2.5 sm:py-3 shadow text-xs sm:text-sm text-slate-500">
                    Cadastro único por medicamento. Utilize as demais telas para controlar movimentações.
                </div>
            </header>

            <?php if (!empty($errors)): ?>
                <div class="glass-card border border-rose-200 bg-rose-50/80 px-4 sm:px-6 py-4 sm:py-5 text-rose-700 shadow">
                    <h2 class="text-base sm:text-lg font-semibold mb-2">Corrija os pontos abaixo:</h2>
                    <ul class="list-disc list-inside space-y-1 text-xs sm:text-sm">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-6 lg:space-y-8" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <section class="glass-card p-5 sm:p-6 space-y-5 sm:space-y-6">
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold text-slate-900">Dados principais</h2>
                        <p class="text-xs sm:text-sm text-slate-500 mt-1">Informações essenciais para identificação e classificação do medicamento.</p>
                    </div>
                    <div class="grid gap-4 sm:gap-5 lg:grid-cols-2">
                        <label class="flex flex-col gap-2">
                            <span class="text-xs sm:text-sm font-medium text-slate-600">Nome do medicamento *</span>
                            <input type="text" name="nome" value="<?php echo htmlspecialchars($medicamento['nome']); ?>" class="rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-800 shadow focus:border-primary-500 focus:ring-primary-500" required>
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-600">Categoria</span>
                            <select name="categoria_id" class="rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                                <option value="">Selecione</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($medicamento['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="text-xs text-slate-400">Novo cadastro disponível em Configurações &gt; Categorias.</span>
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-600">Apresentação</span>
                            <select name="apresentacao_id" class="rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                                <option value="">Selecione</option>
                                <?php foreach ($apresentacoes as $apresentacao): ?>
                                    <option value="<?php echo $apresentacao['id']; ?>" <?php echo ($medicamento['apresentacao_id'] == $apresentacao['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($apresentacao['nome']); ?> (<?php echo htmlspecialchars($apresentacao['sigla']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <span class="text-xs text-slate-400">Gerencie apresentações em Configurações &gt; Apresentações.</span>
                        </label>
                        <label class="flex flex-col gap-2 lg:col-span-2">
                            <span class="text-sm font-medium text-slate-600">Descrição</span>
                            <textarea name="descricao" rows="3" class="rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500"><?php echo htmlspecialchars($medicamento['descricao']); ?></textarea>
                        </label>
                    </div>

                    <div class="grid gap-4 sm:gap-5 lg:grid-cols-3">
                        <label class="flex flex-col gap-2">
                            <span class="text-xs sm:text-sm font-medium text-slate-600">Estoque mínimo *</span>
                            <input type="number" min="0" name="estoque_minimo" value="<?php echo (int)$medicamento['estoque_minimo']; ?>" class="rounded-2xl border border-slate-100 bg-white px-4 sm:px-5 py-2.5 sm:py-3 text-sm sm:text-base text-slate-800 shadow focus:border-primary-500 focus:ring-primary-500" required>
                        </label>
                        <div class="flex items-center gap-3 bg-white rounded-2xl border border-slate-100 px-4 sm:px-5 py-2.5 sm:py-3 shadow">
                            <input type="checkbox" name="ativo" id="ativo" value="1" class="h-4 w-4 sm:h-5 sm:w-5 rounded border-slate-300 text-primary-600 focus:ring-primary-500" <?php echo ($medicamento['ativo']) ? 'checked' : ''; ?>>
                            <label for="ativo" class="text-xs sm:text-sm font-medium text-slate-600">Medicamento ativo</label>
                        </div>
                    </div>
                </section>

                <section class="glass-card p-5 sm:p-6 space-y-3 sm:space-y-4">
                    <h2 class="text-base sm:text-lg font-semibold text-slate-900">Próximos passos</h2>
                    <p class="text-xs sm:text-sm text-slate-500">Após salvar, utilize a tela de lotes para cadastrar códigos de barras e lotes deste medicamento. Cada lote deve ter um código de barras associado.</p>
                </section>

                <section class="flex flex-col sm:flex-row flex-wrap gap-3 sm:gap-4">
                    <button type="submit" name="acao" value="save" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-5 sm:px-6 py-2.5 sm:py-3 text-sm sm:text-base text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6"/></svg>
                        Salvar
                    </button>
                    <button type="submit" name="acao" value="save_new" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-5 sm:px-6 py-2.5 sm:py-3 text-sm sm:text-base text-primary-600 font-semibold shadow hover:shadow-lg transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 12H7m5 5V7"/></svg>
                        <span class="hidden sm:inline">Salvar e cadastrar outro</span>
                        <span class="sm:hidden">Salvar e novo</span>
                    </button>
                    <a href="medicamentos.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-white/80 px-5 sm:px-6 py-2.5 sm:py-3 text-sm sm:text-base text-slate-500 font-semibold shadow hover:shadow-lg transition">
                        Cancelar
                    </a>
                </section>
            </form>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
</body>
</html>
