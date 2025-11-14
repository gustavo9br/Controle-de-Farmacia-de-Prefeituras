<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Processar exclusão de paciente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_paciente']) && isset($_POST['paciente_id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verificarCSRFToken($csrfToken)) {
        $_SESSION['error'] = 'Token de segurança inválido. Atualize a página e tente novamente.';
    } else {
        $paciente_id = (int)$_POST['paciente_id'];
        
        try {
            // Por enquanto, permitir exclusão direta (quando integrar com saídas, adicionar verificação)
            $stmt = $conn->prepare("DELETE FROM pacientes WHERE id = ?");
            $stmt->execute([$paciente_id]);
            $_SESSION['success'] = "Paciente excluído com sucesso!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erro ao excluir paciente: " . $e->getMessage();
        }
    }
    
    header('Location: pacientes.php');
    exit;
}

// Filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_sexo = isset($_GET['sexo']) ? $_GET['sexo'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'nome';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';

// Paginação
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = ITENS_POR_PAGINA;
$offset = ($page - 1) * $perPage;

$allowedSorts = [
    'nome' => 'nome',
    'data_nascimento' => 'data_nascimento',
    'cpf' => 'cpf',
    'cartao_sus' => 'cartao_sus'
];

$sortColumn = $allowedSorts[$sort] ?? $allowedSorts['nome'];
$order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

// Construir WHERE clauses
$whereClauses = ['ativo = 1'];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(nome LIKE ? OR cpf LIKE ? OR cartao_sus LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($filter_sexo) && in_array($filter_sexo, ['M', 'F', 'Outro'])) {
    $whereClauses[] = "sexo = ?";
    $params[] = $filter_sexo;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereClauses);

try {
    // Contar total de registros
    $countSql = "SELECT COUNT(*) FROM pacientes $whereClause";
    $stmt = $conn->prepare($countSql);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    $stmt->execute();
    $totalRecords = (int)$stmt->fetchColumn();

    $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    // Buscar pacientes
    $dataSql = "SELECT *, 
                TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) AS idade,
                0 AS total_saidas
                FROM pacientes
                $whereClause
                ORDER BY $sortColumn $order
                LIMIT :offset, :limit";

    $stmt = $conn->prepare($dataSql);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Erro ao buscar pacientes: " . $e->getMessage();
    $totalRecords = 0;
    $pacientes = [];
    $totalPages = 1;
}

function sexoBadgeClass(string $sexo): string
{
    switch ($sexo) {
        case 'M':
            return 'bg-blue-100 text-blue-600';
        case 'F':
            return 'bg-pink-100 text-pink-600';
        default:
            return 'bg-purple-100 text-purple-600';
    }
}

function sexoLabel(string $sexo): string
{
    switch ($sexo) {
        case 'M':
            return 'Masculino';
        case 'F':
            return 'Feminino';
        default:
            return 'Outro';
    }
}

$successMessage = getSuccessMessage();
$errorMessage = isset($error) ? $error : getErrorMessage();
$csrfToken = gerarCSRFToken();

$pageTitle = 'Pacientes cadastrados';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Controle de medicamentos, lotes, pacientes e receitas">
    <meta name="keywords" content="farmácia, medicamentos, gestão, controle de estoque, receitas, pacientes">
    <meta name="author" content="Sistema Farmácia">
    <meta name="robots" content="noindex, nofollow">
    
    <?php 
    $ogTitle = htmlspecialchars($pageTitle) . ' - Gov Farma';
    $ogDescription = 'Gov Farma - Cadastro e gestão de pacientes. Histórico completo de dispensações e receitas.';
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
        <main class="flex-1 px-6 py-10 lg:px-12 space-y-10">
            <header class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-3">
                    <span class="text-sm uppercase tracking-[0.3em] text-slate-500">Pacientes</span>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="mt-2 text-slate-500 max-w-2xl">Cadastro e gerenciamento de pacientes atendidos pela farmácia popular.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-3 lg:items-end">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/90 px-5 py-2 text-sm text-slate-500 shadow">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0z"/></svg>
                        <?php echo number_format($totalRecords, 0, ',', '.'); ?> pacientes cadastrados
                    </span>
                    <div class="flex flex-wrap gap-3">
                        <a href="pacientes_form.php" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-6 py-3 text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6"/></svg>
                            Cadastrar paciente
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

            <section class="glass-card p-6 space-y-6">
                <div class="grid gap-5 lg:grid-cols-12">
                    <div class="lg:col-span-6">
                        <label for="search" class="text-sm font-medium text-slate-600">Buscar por nome, CPF ou cartão SUS</label>
                        <div class="relative mt-2">
                            <input type="text" id="search" class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 pl-11 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="Digite o nome, CPF ou cartão SUS..." autocomplete="off">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                            <div id="searchLoader" class="hidden absolute right-4 top-1/2 -translate-y-1/2">
                                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-500"></div>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-3">
                        <label for="sexo" class="text-sm font-medium text-slate-600">Sexo</label>
                        <select id="sexo" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Todos</option>
                            <option value="M" <?php echo $filter_sexo === 'M' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="F" <?php echo $filter_sexo === 'F' ? 'selected' : ''; ?>>Feminino</option>
                            <option value="Outro" <?php echo $filter_sexo === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="glass-card p-0 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-white/60 bg-white/70">
                    <h2 class="text-lg font-semibold text-slate-900">Lista de pacientes</h2>
                    <span id="totalInfo" class="rounded-full bg-primary-50 px-4 py-1 text-sm font-medium text-primary-600"><?php echo number_format($totalRecords, 0, ',', '.'); ?> pacientes</span>
                </div>
                <div id="pacientesContainer">
                <?php if (count($pacientes) > 0): ?>
                    <!-- Desktop table -->
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-white/60">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-6 py-3">Nome</th>
                                    <th class="px-6 py-3">Data de Nascimento</th>
                                    <th class="px-6 py-3">Idade</th>
                                    <th class="px-6 py-3">CPF</th>
                                    <th class="px-6 py-3">Cartão SUS</th>
                                    <th class="px-6 py-3">Sexo</th>
                                    <th class="px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white/80">
                                <?php foreach ($pacientes as $paciente): ?>
                                    <tr class="text-sm text-slate-600 hover:bg-blue-50/50 transition-colors cursor-pointer" onclick="window.location.href='paciente_historico.php?id=<?php echo (int)$paciente['id']; ?>'" title="Clique para ver o histórico do paciente">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($paciente['nome']); ?></span>
                                                <?php if ($paciente['total_saidas'] > 0): ?>
                                                    <span class="text-xs text-slate-400 mt-1"><?php echo $paciente['total_saidas']; ?> saídas registradas</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-slate-600"><?php echo htmlspecialchars(formatarData($paciente['data_nascimento'])); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-slate-600"><?php echo (int)$paciente['idade']; ?> anos</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-mono text-slate-600"><?php echo !empty($paciente['cpf']) ? htmlspecialchars($paciente['cpf']) : '—'; ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-mono text-slate-600"><?php echo !empty($paciente['cartao_sus']) ? htmlspecialchars($paciente['cartao_sus']) : '—'; ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo sexoBadgeClass($paciente['sexo']); ?>">
                                                <?php echo htmlspecialchars(sexoLabel($paciente['sexo'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4" onclick="event.stopPropagation();">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="pacientes_form.php?id=<?php echo (int)$paciente['id']; ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-500 hover:bg-blue-600 text-white transition-all shadow-sm hover:shadow-md" title="Editar paciente">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487l1.687 1.688a1.875 1.875 0 102.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                                </a>
                                                <form method="post" class="inline" onsubmit="event.stopPropagation(); return confirm('Confirma a exclusão do paciente <?php echo htmlspecialchars($paciente['nome'], ENT_QUOTES); ?>? Esta ação não poderá ser desfeita.');">
                                                    <input type="hidden" name="delete_paciente" value="1">
                                                    <input type="hidden" name="paciente_id" value="<?php echo (int)$paciente['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <button type="submit" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-red-500 hover:bg-red-600 text-white transition-all shadow-sm hover:shadow-md" title="Excluir paciente">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
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
                        <?php foreach ($pacientes as $paciente): ?>
                            <div class="p-5 bg-white/80 space-y-3 hover:bg-blue-50/50 transition-colors cursor-pointer" onclick="window.location.href='paciente_historico.php?id=<?php echo (int)$paciente['id']; ?>'" title="Clique para ver o histórico do paciente">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-xs text-slate-500 uppercase tracking-wide">Nome</p>
                                        <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($paciente['nome']); ?></p>
                                        <?php if ($paciente['total_saidas'] > 0): ?>
                                            <p class="text-xs text-slate-400 mt-1"><?php echo $paciente['total_saidas']; ?> saídas</p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo sexoBadgeClass($paciente['sexo']); ?>">
                                        <?php echo htmlspecialchars(sexoLabel($paciente['sexo'])); ?>
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-xs text-slate-500">Data de Nascimento</p>
                                        <p class="text-sm text-slate-700"><?php echo htmlspecialchars(formatarData($paciente['data_nascimento'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Idade</p>
                                        <p class="text-sm text-slate-700"><?php echo (int)$paciente['idade']; ?> anos</p>
                                    </div>
                                </div>

                                <?php if (!empty($paciente['cpf'])): ?>
                                    <div>
                                        <p class="text-xs text-slate-500">CPF</p>
                                        <p class="text-sm font-mono text-slate-700"><?php echo htmlspecialchars($paciente['cpf']); ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($paciente['cartao_sus'])): ?>
                                    <div>
                                        <p class="text-xs text-slate-500">Cartão SUS</p>
                                        <p class="text-sm font-mono text-slate-700"><?php echo htmlspecialchars($paciente['cartao_sus']); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="flex gap-2 pt-2 border-t border-slate-100" onclick="event.stopPropagation();">
                                    <a href="pacientes_form.php?id=<?php echo (int)$paciente['id']; ?>" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium transition-all shadow-sm hover:shadow-md">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487l1.687 1.688a1.875 1.875 0 102.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                        Editar
                                    </a>
                                    <form method="post" class="flex-1" onsubmit="event.stopPropagation(); return confirm('Confirma a exclusão do paciente?');">
                                        <input type="hidden" name="delete_paciente" value="1">
                                        <input type="hidden" name="paciente_id" value="<?php echo (int)$paciente['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-red-500 hover:bg-red-600 text-white text-sm font-medium transition-all shadow-sm hover:shadow-md">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                            Excluir
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="flex items-center justify-between border-t border-white/60 bg-white/70 px-6 py-4">
                            <span class="text-xs text-slate-400">Mostrando <?php echo min($perPage, $totalRecords - $offset); ?> de <?php echo $totalRecords; ?> registros</span>
                            <ul class="flex items-center gap-2 text-sm">
                                <?php
                                    $queryParams = $_GET;
                                    $rangeStart = max(1, $page - 2);
                                    $rangeEnd = min($totalPages, $rangeStart + 4);
                                    if ($rangeEnd - $rangeStart < 4) {
                                        $rangeStart = max(1, $rangeEnd - 4);
                                    }
                                ?>
                                <li>
                                    <a class="pagination-chip <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : 'pacientes.php?' . http_build_query(array_merge($queryParams, ['page' => 1])); ?>" aria-label="Primeira página">«</a>
                                </li>
                                <li>
                                    <a class="pagination-chip <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : 'pacientes.php?' . http_build_query(array_merge($queryParams, ['page' => max(1, $page - 1)])); ?>" aria-label="Página anterior">‹</a>
                                </li>
                                <?php for ($i = $rangeStart; $i <= $rangeEnd; $i++): ?>
                                    <li>
                                        <a class="pagination-chip <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo 'pacientes.php?' . http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li>
                                    <a class="pagination-chip <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $page >= $totalPages ? '#' : 'pacientes.php?' . http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])); ?>" aria-label="Próxima página">›</a>
                                </li>
                                <li>
                                    <a class="pagination-chip <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $page >= $totalPages ? '#' : 'pacientes.php?' . http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" aria-label="Última página">»</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0z"/></svg>
                        <p class="text-sm">Nenhum paciente encontrado com os filtros aplicados.</p>
                        <a href="pacientes_form.php" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-5 py-2 text-white text-sm font-semibold shadow hover:bg-primary-500 transition">Cadastrar o primeiro paciente</a>
                    </div>
                <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
    <script>
        let searchTimeout = null;
        let pacientesData = <?php echo json_encode($pacientes); ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const sexoSelect = document.getElementById('sexo');
            
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        buscarPacientes();
                    }, 300);
                });
            }
            
            if (sexoSelect) {
                sexoSelect.addEventListener('change', function() {
                    buscarPacientes();
                });
            }
        });
        
        async function buscarPacientes() {
            const query = document.getElementById('search').value.trim();
            const sexo = document.getElementById('sexo').value;
            const loader = document.getElementById('searchLoader');
            const container = document.getElementById('pacientesContainer');
            const totalInfo = document.getElementById('totalInfo');
            
            if (loader) loader.classList.remove('hidden');
            
            try {
                const params = new URLSearchParams();
                if (query) params.append('q', query);
                if (sexo) params.append('sexo', sexo);
                
                const response = await fetch(`api/buscar_paciente_lista.php?${params.toString()}`);
                const data = await response.json();
                
                if (loader) loader.classList.add('hidden');
                
                if (data.success && data.pacientes && data.pacientes.length > 0) {
                    pacientesData = data.pacientes;
                    renderizarPacientes(data.pacientes);
                    if (totalInfo) totalInfo.textContent = `${data.pacientes.length} paciente(s) encontrado(s)`;
                } else {
                    container.innerHTML = `
                        <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0z"/></svg>
                            <p class="text-sm">Nenhum paciente encontrado.</p>
                        </div>
                    `;
                    if (totalInfo) totalInfo.textContent = '0 pacientes';
                }
            } catch (error) {
                console.error('Erro ao buscar pacientes:', error);
                if (loader) loader.classList.add('hidden');
            }
        }
        
        function renderizarPacientes(pacientes) {
            const container = document.getElementById('pacientesContainer');
            
            const sexoBadgeClass = (sexo) => {
                switch (sexo) {
                    case 'M': return 'bg-blue-100 text-blue-600';
                    case 'F': return 'bg-pink-100 text-pink-600';
                    default: return 'bg-slate-100 text-slate-600';
                }
            };
            
            const sexoLabel = (sexo) => {
                switch (sexo) {
                    case 'M': return 'Masculino';
                    case 'F': return 'Feminino';
                    case 'Outro': return 'Outro';
                    default: return 'Não informado';
                }
            };
            
            const formatarData = (data) => {
                if (!data) return '-';
                const d = new Date(data + 'T00:00:00');
                return d.toLocaleDateString('pt-BR');
            };
            
            container.innerHTML = `
                <div class="hidden lg:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left">
                        <thead class="bg-white/60">
                            <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Nome</th>
                                <th class="px-6 py-3">Data de Nascimento</th>
                                <th class="px-6 py-3">Idade</th>
                                <th class="px-6 py-3">CPF</th>
                                <th class="px-6 py-3">Cartão SUS</th>
                                <th class="px-6 py-3">Sexo</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white/80">
                            ${pacientes.map(paciente => {
                                return `
                                    <tr class="text-sm text-slate-600 hover:bg-blue-50/50 transition-colors cursor-pointer" onclick="window.location.href='paciente_historico.php?id=${paciente.id}'" title="Clique para ver o histórico do paciente">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-900">${paciente.nome}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-slate-600">${formatarData(paciente.data_nascimento)}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-slate-600">${paciente.idade || 0} anos</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-mono text-slate-600">${paciente.cpf || '—'}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-mono text-slate-600">${paciente.cartao_sus || '—'}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${sexoBadgeClass(paciente.sexo)}">
                                                ${sexoLabel(paciente.sexo)}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4" onclick="event.stopPropagation();">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="pacientes_form.php?id=${paciente.id}" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-500 hover:bg-blue-600 text-white transition-all shadow-sm hover:shadow-md" title="Editar paciente">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487l1.687 1.688a1.875 1.875 0 102.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
    </script>
</body>
</html>
