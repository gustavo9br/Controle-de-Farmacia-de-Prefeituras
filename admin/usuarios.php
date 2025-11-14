<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$allowedTypes = [
    'admin' => 'Administrador',
    'usuario' => 'Usuário',
    'medico' => 'Médico',
    'hospital' => 'Hospital'
];
$allowedStatus = ['ativo', 'inativo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    if (!isset($_POST['csrf_token']) || !verificarCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Token de segurança inválido.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'create') {
                $nome = trim($_POST['nome'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $tipo = $_POST['tipo'] ?? 'usuario';
                $senha = $_POST['senha'] ?? '';
                $confirmar = $_POST['confirmar_senha'] ?? '';
                $status = $_POST['status'] ?? 'ativo';

                if ($nome === '' || $email === '' || $senha === '' || $confirmar === '') {
                    $errors[] = 'Preencha todos os campos obrigatórios.';
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Informe um email válido.';
                }
                if (!isset($allowedTypes[$tipo])) {
                    $errors[] = 'Tipo de usuário inválido.';
                }
                if (!in_array($status, $allowedStatus, true)) {
                    $status = 'ativo';
                }
                if ($senha !== $confirmar) {
                    $errors[] = 'As senhas não conferem.';
                }
                if (strlen($senha) < 6) {
                    $errors[] = 'A senha deve possuir pelo menos 6 caracteres.';
                }

                if (empty($errors)) {
                    $stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $errors[] = 'Já existe um usuário cadastrado com este email.';
                    }
                }

                if (empty($errors)) {
                    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('INSERT INTO usuarios (nome, email, senha, tipo, status, data_cadastro) VALUES (?, ?, ?, ?, ?, NOW())');
                    $stmt->execute([$nome, $email, $senhaHash, $tipo, $status]);
                    $_SESSION['success'] = 'Usuário cadastrado com sucesso!';
                }
            } elseif ($action === 'update_type') {
                $userId = (int) ($_POST['user_id'] ?? 0);
                $tipo = $_POST['tipo'] ?? '';
                if ($userId <= 0 || !isset($allowedTypes[$tipo])) {
                    $errors[] = 'Parâmetros inválidos para atualização de perfil.';
                } elseif ($userId === $currentUserId) {
                    $errors[] = 'Você não pode alterar o seu próprio perfil de acesso.';
                } else {
                    $stmt = $conn->prepare('UPDATE usuarios SET tipo = ? WHERE id = ?');
                    $stmt->execute([$tipo, $userId]);
                    $_SESSION['success'] = 'Perfil do usuário atualizado com sucesso.';
                }
            } elseif ($action === 'toggle_status') {
                $userId = (int) ($_POST['user_id'] ?? 0);
                if ($userId <= 0) {
                    $errors[] = 'Usuário inválido para alteração de status.';
                } elseif ($userId === $currentUserId) {
                    $errors[] = 'Você não pode alterar o seu próprio status.';
                } else {
                    $stmt = $conn->prepare('SELECT status FROM usuarios WHERE id = ?');
                    $stmt->execute([$userId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $newStatus = $row['status'] === 'ativo' ? 'inativo' : 'ativo';
                        $stmt = $conn->prepare('UPDATE usuarios SET status = ? WHERE id = ?');
                        $stmt->execute([$newStatus, $userId]);
                        $_SESSION['success'] = 'Status do usuário atualizado para ' . strtoupper($newStatus) . '.';
                    } else {
                        $errors[] = 'Usuário não encontrado.';
                    }
                }
            } elseif ($action === 'reset_password') {
                $userId = (int) ($_POST['user_id'] ?? 0);
                if ($userId <= 0) {
                    $errors[] = 'Usuário inválido para redefinição de senha.';
                } else {
                    $novaSenha = substr(strtoupper(bin2hex(random_bytes(4))), 0, 8);
                    $stmt = $conn->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
                    $stmt->execute([password_hash($novaSenha, PASSWORD_DEFAULT), $userId]);
                    $_SESSION['success'] = 'Senha redefinida com sucesso. Nova senha: ' . $novaSenha;
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Erro ao processar a operação: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode(' ', $errors);
    }

    header('Location: usuarios.php');
    exit;
}

try {
    $stmt = $conn->query('SELECT id, nome, email, tipo, status, data_cadastro, ultimo_acesso FROM usuarios ORDER BY nome ASC');
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuarios = [];
    $_SESSION['error'] = 'Erro ao carregar usuários: ' . $e->getMessage();
}

$pageTitle = 'Gerenciar Usuários';
$successMessage = getSuccessMessage();
$errorMessage = getErrorMessage();
$csrfToken = gerarCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Administração de usuários do sistema Farmácia de Laje">
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?> - <?php echo SYSTEM_NAME; ?>">
    <meta property="og:description" content="Administração de usuários do sistema Farmácia de Laje">
    <meta property="og:type" content="website">
    <meta property="og:image" content="../images/logo.svg">
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
    
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

        <main class="content-area">
            <div class="space-y-10">
            <header>
                <div class="space-y-2 sm:space-y-3">
                    <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Administração</span>
                    <div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900">Usuários do Sistema</h1>
                        <p class="mt-2 text-sm sm:text-base text-slate-500 max-w-3xl">Cadastre novos usuários, defina perfis de acesso e mantenha o controle sobre quem utiliza o sistema.</p>
                    </div>
                </div>
            </header>

            <?php if (!empty($successMessage)): ?>
                <div class="glass-card border border-emerald-200/60 bg-emerald-50/80 px-6 py-4">
                    <strong class="block text-sm font-semibold text-emerald-700">✓ Sucesso</strong>
                    <p class="text-sm text-emerald-700 mt-1"><?php echo htmlspecialchars($successMessage); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="glass-card border border-rose-200/60 bg-rose-50/80 px-6 py-4">
                    <strong class="block text-sm font-semibold text-rose-700">Atenção</strong>
                    <p class="text-sm text-rose-700 mt-1"><?php echo htmlspecialchars($errorMessage); ?></p>
                </div>
            <?php endif; ?>

            <section class="glass-card p-6 lg:p-8 space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Cadastrar novo usuário</h2>
                    <p class="text-sm text-slate-500">Preencha as informações abaixo para conceder acesso ao sistema.</p>
                </div>
                <form method="post" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="grid gap-5 grid-cols-1 sm:grid-cols-2">
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Nome completo <span class="text-rose-500">*</span></span>
                            <input type="text" name="nome" required class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" placeholder="Ex: Maria da Silva" maxlength="255">
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Email <span class="text-rose-500">*</span></span>
                            <input type="email" name="email" required class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" placeholder="usuario@exemplo.com" maxlength="255">
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Senha <span class="text-rose-500">*</span></span>
                            <input type="password" name="senha" required class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" minlength="6" placeholder="Mínimo 6 caracteres">
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Confirmar senha <span class="text-rose-500">*</span></span>
                            <input type="password" name="confirmar_senha" required class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" minlength="6" placeholder="Repita a senha">
                        </label>
                    </div>
                    <div class="grid gap-5 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Perfil de acesso <span class="text-rose-500">*</span></span>
                            <select name="tipo" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                                <?php foreach ($allowedTypes as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Status</span>
                            <select name="status" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </label>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-6 sm:px-8 py-3 text-white font-semibold shadow-lg hover:bg-primary-500 transition text-sm sm:text-base">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Cadastrar usuário
                        </button>
                    </div>
                </form>
            </section>

            <section class="space-y-6">
                <div class="glass-card p-4 sm:p-6 lg:p-8">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
                        <div class="flex-1 min-w-0">
                            <h2 class="text-base sm:text-lg font-semibold text-slate-900">Usuários cadastrados</h2>
                            <p class="text-xs sm:text-sm text-slate-500 mt-1">Gerencie perfis, status e redefina senhas dos usuários existentes.</p>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-400 whitespace-nowrap"><?php echo count($usuarios); ?> usuários</span>
                    </div>

                    <!-- Desktop Table -->
                    <div class="hidden lg:block mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-white/60">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-6 py-3">Usuário</th>
                                    <th class="px-6 py-3">Perfil</th>
                                    <th class="px-6 py-3">Status</th>
                                    <th class="px-6 py-3">Último acesso</th>
                                    <th class="px-6 py-3">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white/80">
                                <?php foreach ($usuarios as $usuario): ?>
                                    <?php $isSelf = (int) $usuario['id'] === $currentUserId; ?>
                                    <tr class="text-sm text-slate-600">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-slate-900"><?php echo htmlspecialchars($usuario['nome']); ?></div>
                                            <div class="text-xs text-slate-400"><?php echo htmlspecialchars($usuario['email']); ?></div>
                                            <div class="text-xs text-slate-400 mt-1">Cadastrado em <?php echo !empty($usuario['data_cadastro']) ? date('d/m/Y', strtotime($usuario['data_cadastro'])) : '—'; ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($isSelf): ?>
                                                <span class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700">
                                                    <?php echo htmlspecialchars($allowedTypes[$usuario['tipo']] ?? ucfirst($usuario['tipo'])); ?>
                                                </span>
                                                <p class="mt-2 text-[11px] text-slate-400">Você não pode alterar o próprio perfil.</p>
                                            <?php else: ?>
                                                <form method="post" class="inline-flex items-center gap-2">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="update_type">
                                                    <input type="hidden" name="user_id" value="<?php echo (int) $usuario['id']; ?>">
                                                    <select name="tipo" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                                                        <?php foreach ($allowedTypes as $key => $label): ?>
                                                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $usuario['tipo'] === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="inline-flex items-center justify-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-primary-600 border border-primary-100 hover:bg-primary-50 transition">
                                                        Atualizar
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($usuario['status'] === 'ativo'): ?>
                                                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Ativo</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-2 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-slate-500 text-xs">
                                            <?php echo !empty($usuario['ultimo_acesso']) ? date('d/m/Y H:i', strtotime($usuario['ultimo_acesso'])) : 'Nunca acessou'; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($isSelf): ?>
                                                <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500" title="Você não pode alterar o próprio status.">
                                                    Ações indisponíveis
                                                </span>
                                            <?php else: ?>
                                                <div class="flex flex-wrap gap-2">
                                                    <form method="post">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="user_id" value="<?php echo (int) $usuario['id']; ?>">
                                                        <button type="submit" class="action-chip <?php echo $usuario['status'] === 'ativo' ? 'danger' : ''; ?>" title="<?php echo $usuario['status'] === 'ativo' ? 'Desativar usuário' : 'Ativar usuário'; ?>">
                                                            <?php if ($usuario['status'] === 'ativo'): ?>
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364 5.636 5.636M18.364 5.636 5.636 18.364"/></svg>
                                                            <?php else: ?>
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6"/></svg>
                                                            <?php endif; ?>
                                                        </button>
                                                    </form>
                                                    <form method="post" onsubmit="return confirm('Gerar nova senha para <?php echo htmlspecialchars($usuario['nome']); ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                        <input type="hidden" name="action" value="reset_password">
                                                        <input type="hidden" name="user_id" value="<?php echo (int) $usuario['id']; ?>">
                                                        <button type="submit" class="action-chip" title="Redefinir senha">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 12a7.5 7.5 0 0 1 13.43-4.387M19.5 12a7.5 7.5 0 0 1-13.43 4.387M12 9v3l1.5 1.5"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($usuarios)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-5 text-center text-sm text-slate-400">Nenhum usuário cadastrado até o momento.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="lg:hidden mt-6 space-y-4">
                        <?php foreach ($usuarios as $usuario): ?>
                            <?php $isSelf = (int) $usuario['id'] === $currentUserId; ?>
                            <div class="bg-white/80 rounded-lg border border-slate-200 p-4 space-y-3">
                                <div>
                                    <div class="font-semibold text-slate-900 text-sm"><?php echo htmlspecialchars($usuario['nome']); ?></div>
                                    <div class="text-xs text-slate-400 mt-1"><?php echo htmlspecialchars($usuario['email']); ?></div>
                                    <div class="text-xs text-slate-400 mt-1">Cadastrado em <?php echo !empty($usuario['data_cadastro']) ? date('d/m/Y', strtotime($usuario['data_cadastro'])) : '—'; ?></div>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-slate-500">Perfil:</span>
                                    <?php if ($isSelf): ?>
                                        <span class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700">
                                            <?php echo htmlspecialchars($allowedTypes[$usuario['tipo']] ?? ucfirst($usuario['tipo'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <form method="post" class="flex items-center gap-2 flex-1 justify-end">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="update_type">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $usuario['id']; ?>">
                                            <select name="tipo" class="flex-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                                                <?php foreach ($allowedTypes as $key => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $usuario['tipo'] === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="inline-flex items-center justify-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-primary-600 border border-primary-100 hover:bg-primary-50 transition">
                                                Atualizar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-slate-500">Status:</span>
                                    <?php if ($usuario['status'] === 'ativo'): ?>
                                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Ativo</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-2 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">Inativo</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-slate-500">Último acesso:</span>
                                    <span class="text-xs text-slate-600"><?php echo !empty($usuario['ultimo_acesso']) ? date('d/m/Y H:i', strtotime($usuario['ultimo_acesso'])) : 'Nunca acessou'; ?></span>
                                </div>
                                
                                <?php if (!$isSelf): ?>
                                    <div class="flex gap-2 pt-2 border-t border-slate-100">
                                        <form method="post" class="flex-1">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $usuario['id']; ?>">
                                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold <?php echo $usuario['status'] === 'ativo' ? 'bg-rose-100 text-rose-700 hover:bg-rose-200' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'; ?> transition">
                                                <?php if ($usuario['status'] === 'ativo'): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364 5.636 5.636M18.364 5.636 5.636 18.364"/></svg>
                                                    Desativar
                                                <?php else: ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6"/></svg>
                                                    Ativar
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Gerar nova senha para <?php echo htmlspecialchars($usuario['nome']); ?>?');" class="flex-1">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $usuario['id']; ?>">
                                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-white border border-primary-200 px-3 py-2 text-xs font-semibold text-primary-600 hover:bg-primary-50 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 12a7.5 7.5 0 0 1 13.43-4.387M19.5 12a7.5 7.5 0 0 1-13.43 4.387M12 9v3l1.5 1.5"/></svg>
                                                Redefinir senha
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="pt-2 border-t border-slate-100">
                                        <span class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500 w-full justify-center">
                                            Ações indisponíveis para seu próprio perfil
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($usuarios)): ?>
                            <div class="text-center py-8 text-sm text-slate-400">
                                Nenhum usuário cadastrado até o momento.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            </div>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
</body>
</html>
