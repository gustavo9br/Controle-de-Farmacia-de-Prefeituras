<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['usuario']);

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$pageTitle = 'Meu Perfil';

try {
    $stmt = $conn->prepare('SELECT * FROM usuarios WHERE id = ?');
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        $_SESSION['error'] = 'Usuário não encontrado!';
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Erro ao buscar dados do usuário: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verificarCSRFToken($csrfToken)) {
        $errors[] = 'Token de segurança inválido. Atualize a página e tente novamente.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_info') {
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $cpf = preg_replace('/[^0-9]/', '', trim($_POST['cpf'] ?? ''));

            if ($nome === '') {
                $errors[] = 'O nome é obrigatório.';
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido.';
            }

            if (empty($errors)) {
                try {
                    $stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = ? AND id != ?');
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetch()) {
                        $errors[] = 'Este email já está em uso.';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Erro ao verificar email: ' . $e->getMessage();
                }
            }

            if ($cpf !== '' && empty($errors)) {
                if (strlen($cpf) !== 11) {
                    $errors[] = 'O CPF deve ter 11 dígitos.';
                } else {
                    try {
                        $stmt = $conn->prepare('SELECT id FROM usuarios WHERE cpf = ? AND id != ?');
                        $stmt->execute([$cpf, $user_id]);
                        if ($stmt->fetch()) {
                            $errors[] = 'Este CPF já está em uso.';
                        }
                    } catch (PDOException $e) {
                        $errors[] = 'Erro ao verificar CPF: ' . $e->getMessage();
                    }
                }
            }

            if (empty($errors)) {
                try {
                    $stmt = $conn->prepare('UPDATE usuarios SET nome = ?, email = ?, cpf = ? WHERE id = ?');
                    $stmt->execute([$nome, $email, $cpf ?: null, $user_id]);

                    $_SESSION['user_nome'] = $nome;
                    $_SESSION['user_email'] = $email;

                    $stmt = $conn->prepare('SELECT * FROM usuarios WHERE id = ?');
                    $stmt->execute([$user_id]);
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                    $_SESSION['success'] = 'Informações atualizadas com sucesso!';
                    $success = true;
                } catch (PDOException $e) {
                    $errors[] = 'Erro ao atualizar informações: ' . $e->getMessage();
                }
            }
        }

        if ($action === 'change_password') {
            $senha_atual = $_POST['senha_atual'] ?? '';
            $nova_senha = $_POST['nova_senha'] ?? '';
            $confirmar_senha = $_POST['confirmar_senha'] ?? '';

            if ($senha_atual === '') {
                $errors[] = 'A senha atual é obrigatória.';
            } elseif (!password_verify($senha_atual, $usuario['senha'])) {
                $errors[] = 'Senha atual incorreta.';
            }

            if ($nova_senha === '') {
                $errors[] = 'A nova senha é obrigatória.';
            } elseif (strlen($nova_senha) < 6) {
                $errors[] = 'A nova senha deve ter pelo menos 6 caracteres.';
            }

            if ($nova_senha !== $confirmar_senha) {
                $errors[] = 'As senhas não coincidem.';
            }

            if (empty($errors)) {
                try {
                    $stmt = $conn->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
                    $stmt->execute([password_hash($nova_senha, PASSWORD_DEFAULT), $user_id]);

                    $_SESSION['success'] = 'Senha alterada com sucesso!';
                    $success = true;
                } catch (PDOException $e) {
                    $errors[] = 'Erro ao alterar senha: ' . $e->getMessage();
                }
            }
        }

        if ($action === 'upload_photo' && isset($_FILES['foto'])) {
            $foto = $_FILES['foto'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024;

            if ($foto['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Erro ao fazer upload da foto.';
            } elseif (!in_array($foto['type'], $allowed_types, true)) {
                $errors[] = 'Tipo de arquivo não permitido. Use JPG, PNG ou GIF.';
            } elseif ($foto['size'] > $max_size) {
                $errors[] = 'Arquivo muito grande. Máximo 2MB.';
            } else {
                $upload_dir = __DIR__ . '/../uploads/fotos/';
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
                        $errors[] = 'Erro ao criar diretório de upload. Entre em contato com o administrador.';
                    }
                }

                if (!is_writable($upload_dir)) {
                    $errors[] = 'Diretório de upload sem permissão de escrita. Entre em contato com o administrador.';
                }

                if (empty($errors)) {
                    $extensao = pathinfo($foto['name'], PATHINFO_EXTENSION);
                    $nome_arquivo = 'user_' . $user_id . '_' . time() . '.' . $extensao;
                    $caminho_completo = $upload_dir . $nome_arquivo;

                    if (move_uploaded_file($foto['tmp_name'], $caminho_completo)) {
                        if (!empty($usuario['foto']) && file_exists(__DIR__ . '/../' . $usuario['foto'])) {
                            unlink(__DIR__ . '/../' . $usuario['foto']);
                        }

                        $caminho_relativo = 'uploads/fotos/' . $nome_arquivo;

                        try {
                            $stmt = $conn->prepare('UPDATE usuarios SET foto = ? WHERE id = ?');
                            $stmt->execute([$caminho_relativo, $user_id]);

                            $stmt = $conn->prepare('SELECT * FROM usuarios WHERE id = ?');
                            $stmt->execute([$user_id]);
                            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                            $_SESSION['success'] = 'Foto atualizada com sucesso!';
                            $success = true;
                        } catch (PDOException $e) {
                            $errors[] = 'Erro ao salvar foto: ' . $e->getMessage();
                        }
                    } else {
                        $errors[] = 'Erro ao salvar arquivo.';
                    }
                }
            }
        }

        if ($action === 'remove_photo') {
            if (!empty($usuario['foto']) && file_exists(__DIR__ . '/../' . $usuario['foto'])) {
                unlink(__DIR__ . '/../' . $usuario['foto']);
            }

            try {
                $stmt = $conn->prepare('UPDATE usuarios SET foto = NULL WHERE id = ?');
                $stmt->execute([$user_id]);

                $stmt = $conn->prepare('SELECT * FROM usuarios WHERE id = ?');
                $stmt->execute([$user_id]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                $_SESSION['success'] = 'Foto removida com sucesso!';
                $success = true;
            } catch (PDOException $e) {
                $errors[] = 'Erro ao remover foto: ' . $e->getMessage();
            }
        }
    }
}

$successMessage = getSuccessMessage();
$errorMessage = getErrorMessage();
$csrfToken = gerarCSRFToken();
$typeLabels = [
    'admin' => 'Administrador',
    'usuario' => 'Usuário',
    'medico' => 'Médico',
    'hospital' => 'Hospital'
];
$criadoEm = $usuario['criado_em'] ?? null;
$ultimoLogin = $usuario['ultimo_login'] ?? null;
$statusConta = $usuario['status'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Configurações do perfil - Farmácia de Laje">
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="Configurações do perfil - Farmácia de Laje">
    <meta property="og:type" content="website">
    <meta property="og:image" content="../images/logo.svg">
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
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
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-12 lg:py-10 space-y-6 lg:space-y-8">
        <header>
            <div class="space-y-2 sm:space-y-3">
                <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Configurações</span>
                <div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900">Meu Perfil</h1>
                    <p class="mt-2 text-sm sm:text-base text-slate-500 max-w-2xl">Gerencie suas informações pessoais e configurações da conta.</p>
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

        <?php if ($success && !empty($_SESSION['success'])): ?>
            <div class="glass-card border border-emerald-200/60 bg-emerald-50/80 px-6 py-4">
                <strong class="block text-sm font-semibold text-emerald-700">✓ Sucesso!</strong>
                <p class="text-sm text-emerald-700 mt-1"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
            </div>
        <?php endif; ?>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="glass-card p-6 lg:p-8 space-y-6">
                <h2 class="text-lg font-semibold text-slate-900 border-b border-white/60 pb-3">Foto do Perfil</h2>
                <div class="flex flex-col items-center gap-4">
                    <?php if (!empty($usuario['foto'])): ?>
                        <img src="../<?php echo htmlspecialchars($usuario['foto']); ?>" alt="Foto do perfil" class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-lg">
                    <?php else: ?>
                        <div class="w-32 h-32 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white text-3xl font-bold shadow-lg">
                            <?php echo strtoupper(substr($usuario['nome'], 0, 2)); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" class="w-full space-y-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="upload_photo">
                        <label class="block">
                            <input type="file" name="foto" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 cursor-pointer">
                            <p class="text-xs text-slate-400 mt-1">JPG, PNG ou GIF (máx. 2MB)</p>
                        </label>
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-6 py-2.5 text-white text-sm font-semibold shadow hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            Fazer Upload
                        </button>
                    </form>

                    <?php if (!empty($usuario['foto'])): ?>
                        <form method="post" class="w-full">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="remove_photo">
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-full bg-rose-50 px-6 py-2.5 text-rose-600 text-sm font-semibold hover:bg-rose-100 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Remover Foto
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <form method="post" class="glass-card p-6 lg:p-8 space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="update_info">
                    <h2 class="text-lg font-semibold text-slate-900 border-b border-white/60 pb-3">Informações Pessoais</h2>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <label class="sm:col-span-2 flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Nome completo <span class="text-rose-500">*</span></span>
                            <input type="text" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Email <span class="text-rose-500">*</span></span>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">CPF</span>
                            <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($usuario['cpf'] ?? ''); ?>" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" maxlength="14">
                        </label>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-8 py-3 text-white font-semibold shadow-lg hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Salvar Alterações
                        </button>
                    </div>
                </form>

                <form method="post" class="glass-card p-6 lg:p-8 space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="change_password">
                    <h2 class="text-lg font-semibold text-slate-900 border-b border-white/60 pb-3">Alterar Senha</h2>
                    <div class="space-y-5">
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-slate-700">Senha atual <span class="text-rose-500">*</span></span>
                            <input type="password" name="senha_atual" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                        </label>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="flex flex-col gap-2">
                                <span class="text-sm font-medium text-slate-700">Nova senha <span class="text-rose-500">*</span></span>
                                <input type="password" name="nova_senha" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" minlength="6">
                                <span class="text-xs text-slate-400">Mínimo de 6 caracteres</span>
                            </label>
                            <label class="flex flex-col gap-2">
                                <span class="text-sm font-medium text-slate-700">Confirmar senha <span class="text-rose-500">*</span></span>
                                <input type="password" name="confirmar_senha" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500">
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-8 py-3 text-white font-semibold shadow-lg hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            Alterar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <section class="glass-card p-6 lg:p-8 space-y-6">
            <h2 class="text-lg font-semibold text-slate-900">Informações da Conta</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-white/60 bg-white/70 px-5 py-4">
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Perfil</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($typeLabels[$usuario['tipo']] ?? ucfirst($usuario['tipo'])); ?></p>
                </div>
                <div class="rounded-2xl border border-white/60 bg-white/70 px-5 py-4">
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Criado em</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo $criadoEm ? date('d/m/Y H:i', strtotime($criadoEm)) : '—'; ?></p>
                </div>
                <div class="rounded-2xl border border-white/60 bg-white/70 px-5 py-4">
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Último acesso</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo $ultimoLogin ? date('d/m/Y H:i', strtotime($ultimoLogin)) : '—'; ?></p>
                </div>
                <div class="rounded-2xl border border-white/60 bg-white/70 px-5 py-4">
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Status</p>
                    <p class="mt-2 text-sm font-semibold text-<?php echo $statusConta === 'ativo' ? 'emerald' : 'rose'; ?>-600"><?php echo $statusConta ? ucfirst($statusConta) : '—'; ?></p>
                </div>
            </div>
        </section>
    </main>

    <script>
        const cpfInput = document.getElementById('cpf');
        if (cpfInput) {
            cpfInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                e.target.value = value;
            });
        }
    </script>
</body>
</html>
