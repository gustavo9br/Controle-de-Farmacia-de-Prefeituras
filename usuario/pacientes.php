<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['usuario']);

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_paciente']) && isset($_POST['paciente_id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verificarCSRFToken($csrfToken)) {
        $_SESSION['error'] = 'Token de segurança inválido. Atualize a página e tente novamente.';
    } else {
        $paciente_id = (int) $_POST['paciente_id'];

        try {
            $stmt = $conn->prepare('DELETE FROM pacientes WHERE id = ?');
            $stmt->execute([$paciente_id]);
            $_SESSION['success'] = 'Paciente excluído com sucesso!';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Erro ao excluir paciente: ' . $e->getMessage();
        }
    }

    header('Location: pacientes.php');
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_sexo = $_GET['sexo'] ?? '';
$sort = $_GET['sort'] ?? 'nome';
$order = strtoupper($_GET['order'] ?? 'ASC');

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
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

$whereClauses = ['ativo = 1'];
$params = [];

if ($search !== '') {
    $whereClauses[] = '(nome LIKE ? OR cpf LIKE ? OR cartao_sus LIKE ?)';
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($filter_sexo) && in_array($filter_sexo, ['M', 'F', 'Outro'], true)) {
    $whereClauses[] = 'sexo = ?';
    $params[] = $filter_sexo;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereClauses);

try {
    $countSql = "SELECT COUNT(*) FROM pacientes $whereClause";
    $stmt = $conn->prepare($countSql);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    $stmt->execute();
    $totalRecords = (int) $stmt->fetchColumn();

    $totalPages = $totalRecords > 0 ? (int) ceil($totalRecords / $perPage) : 1;
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $dataSql = "SELECT *, TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) AS idade, 0 AS total_saidas
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
    $error = 'Erro ao buscar pacientes: ' . $e->getMessage();
    $totalRecords = 0;
    $pacientes = [];
    $totalPages = 1;
}

function sexoBadgeClass(string $sexo): string
{
    return match ($sexo) {
        'M' => 'bg-blue-100 text-blue-600',
        'F' => 'bg-pink-100 text-pink-600',
        default => 'bg-purple-100 text-purple-600',
    };
}

function sexoLabel(string $sexo): string
{
    return match ($sexo) {
        'M' => 'Masculino',
        'F' => 'Feminino',
        default => 'Outro',
    };
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
    <meta name="description" content="Sistema de gestão de farmácia - Controle de pacientes">
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?> - <?php echo SYSTEM_NAME; ?>">
    <meta property="og:description" content="Sistema de gestão de farmácia">
    <meta property="og:type" content="website">
    <meta property="og:image" content="../images/logo.svg">
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
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
                <form method="get" class="grid gap-5 lg:grid-cols-12">
                    <div class="lg:col-span-6">
                        <label for="search" class="text-sm font-medium text-slate-600">Buscar por nome, CPF ou cartão SUS</label>
                        <div class="relative mt-2">
                            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 pl-11 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="Ex.: João Silva" autocomplete="off">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                        </div>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="sexo" class="text-sm font-medium text-slate-600">Sexo</label>
                        <select name="sexo" id="sexo" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Todos</option>
                            <option value="M" <?php echo $filter_sexo === 'M' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="F" <?php echo $filter_sexo === 'F' ? 'selected' : ''; ?>>Feminino</option>
                            <option value="Outro" <?php echo $filter_sexo === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="sort" class="text-sm font-medium text-slate-600">Ordenar por</label>
                        <select name="sort" id="sort" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                            <option value="nome" <?php echo $sort === 'nome' ? 'selected' : ''; ?>>Nome</option>
                            <option value="data_nascimento" <?php echo $sort === 'data_nascimento' ? 'selected' : ''; ?>>Data de nascimento</option>
                            <option value="cpf" <?php echo $sort === 'cpf' ? 'selected' : ''; ?>>CPF</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="order" class="text-sm font-medium text-slate-600">Ordem</label>
                        <select name="order" id="order" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                            <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Crescente</option>
                            <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Decrescente</option>
                        </select>
                    </div>
                    <div class="lg:col-span-12 flex flex-wrap gap-3">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-6 py-3 text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                            Aplicar filtros
                        </button>
                        <a href="pacientes.php" class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-slate-500 font-semibold shadow hover:shadow-lg transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 4.5l15 15M19.5 4.5l-15 15"/></svg>
                            Limpar
                        </a>
                    </div>
                </form>
            </section>

            <section class="glass-card p-0 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-white/60 bg-white/70">
                    <h2 class="text-lg font-semibold text-slate-900">Lista de pacientes</h2>
                    <span class="rounded-full bg-primary-50 px-4 py-1 text-sm font-medium text-primary-600">Página <?php echo $totalRecords > 0 ? $page : 0; ?> de <?php echo $totalRecords > 0 ? $totalPages : 0; ?></span>
                </div>
                <?php if (!empty($pacientes)): ?>
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
                                    <tr class="text-sm text-slate-600">
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
                                            <span class="text-sm text-slate-600"><?php echo (int) $paciente['idade']; ?> anos</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-mono text-slate-600"><?php echo !empty($paciente['cpf']) ? htmlspecialchars($paciente['cpf']) : '—'; ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-mono text-slate-600"><?php echo !empty($paciente['cartao_sus']) ? htmlspecialchars($paciente['cartao_sus']) : '—'; ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo sexoBadgeClass($paciente['sexo']); ?>"><?php echo htmlspecialchars(sexoLabel($paciente['sexo'])); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="pacientes_form.php?id=<?php echo (int) $paciente['id']; ?>" class="action-chip" title="Editar paciente">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487 19.5 7.125m-2.638-2.638L9.75 14.25 7.5 16.5m12-9-5.25-5.25M7.5 16.5v2.25h2.25L18.75 9"/></svg>
                                                </a>
                                                <a href="paciente_historico.php?id=<?php echo (int) $paciente['id']; ?>" class="action-chip" title="Histórico de dispensações">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10m-7 4H8m8 0h-3m-7 6h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"/></svg>
                                                </a>
                                                <form method="post" class="inline" onsubmit="return confirm('Confirma a exclusão do paciente <?php echo htmlspecialchars($paciente['nome'], ENT_QUOTES); ?>? Esta ação não poderá ser desfeita.');">
                                                    <input type="hidden" name="delete_paciente" value="1">
                                                    <input type="hidden" name="paciente_id" value="<?php echo (int) $paciente['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <button type="submit" class="action-chip danger" title="Excluir paciente">
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

                    <div class="lg:hidden divide-y divide-slate-100">
                        <?php foreach ($pacientes as $paciente): ?>
                            <div class="p-5 bg-white/80 space-y-3">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-xs text-slate-500 uppercase tracking-wide">Nome</p>
                                        <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($paciente['nome']); ?></p>
                                        <?php if ($paciente['total_saidas'] > 0): ?>
                                            <p class="text-xs text-slate-400 mt-1"><?php echo $paciente['total_saidas']; ?> saídas</p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo sexoBadgeClass($paciente['sexo']); ?>"><?php echo htmlspecialchars(sexoLabel($paciente['sexo'])); ?></span>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-xs text-slate-500">Data de nascimento</p>
                                        <p class="text-sm text-slate-700"><?php echo htmlspecialchars(formatarData($paciente['data_nascimento'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Idade</p>
                                        <p class="text-sm text-slate-700"><?php echo (int) $paciente['idade']; ?> anos</p>
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

                                <div class="flex gap-2 pt-2 border-t border-slate-100">
                                    <a href="pacientes_form.php?id=<?php echo (int) $paciente['id']; ?>" class="action-chip flex-1 justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487 19.5 7.125m-2.638-2.638L9.75 14.25 7.5 16.5m12-9-5.25-5.25M7.5 16.5v2.25h2.25L18.75 9"/></svg>
                                        Editar
                                    </a>
                                    <a href="paciente_historico.php?id=<?php echo (int) $paciente['id']; ?>" class="action-chip flex-1 justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10m-7 4H8m8 0h-3m-7 6h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"/></svg>
                                        Histórico
                                    </a>
                                    <form method="post" class="flex-1" onsubmit="return confirm('Confirma a exclusão do paciente <?php echo htmlspecialchars($paciente['nome'], ENT_QUOTES); ?>? Esta ação não poderá ser desfeita.');">
                                        <input type="hidden" name="delete_paciente" value="1">
                                        <input type="hidden" name="paciente_id" value="<?php echo (int) $paciente['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="action-chip danger w-full justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 7.5h12M9 7.5V6a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 6v1.5m-6 0v10.5A1.5 1.5 0 0 0 10.5 21h3A1.5 1.5 0 0 0 15 19.5V7.5"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-10 text-center text-slate-400 text-sm">Nenhum paciente encontrado. Ajuste os filtros ou cadastre um novo paciente.</div>
                <?php endif; ?>
            </section>

            <?php if ($totalRecords > 0): ?>
                <nav class="glass-card p-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-500">
                        Mostrando <?php echo min($perPage, $totalRecords - $offset); ?> de <?php echo $totalRecords; ?> pacientes
                    </p>
                    <div class="flex items-center gap-2">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo htmlspecialchars(buildQuery(['page' => $page - 1])); ?>" class="pagination-chip">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7"/></svg>
                                Anterior
                            </a>
                        <?php endif; ?>
                        <span class="pagination-chip bg-primary-600 text-white">
                            Página <?php echo $page; ?>
                        </span>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo htmlspecialchars(buildQuery(['page' => $page + 1])); ?>" class="pagination-chip">
                                Próxima
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
<?php
function buildQuery(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    return '?' . http_build_query($query);
}
?>
