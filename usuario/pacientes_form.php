<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['usuario']);

$conn = getConnection();

function validarCPF($cpf)
{
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11) {
        return false;
    }
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += (int) $cpf[$i] * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : (11 - $resto);
    if ((int) $cpf[9] !== $digito1) {
        return false;
    }
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += (int) $cpf[$i] * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : (11 - $resto);
    return (int) $cpf[10] === $digito2;
}

$paciente_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = !empty($paciente_id);
$pageTitle = $isEdit ? 'Editar paciente' : 'Cadastrar novo paciente';

$paciente = [
    'nome' => '',
    'data_nascimento' => '',
    'cpf' => '',
    'cartao_sus' => '',
    'sexo' => '',
    'observacoes' => ''
];

if ($isEdit) {
    try {
        $stmt = $conn->prepare('SELECT * FROM pacientes WHERE id = ? AND ativo = 1');
        $stmt->execute([$paciente_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            $_SESSION['error'] = 'Paciente não encontrado!';
            header('Location: pacientes.php');
            exit;
        }

        $paciente = $result;
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao buscar paciente: ' . $e->getMessage();
        header('Location: pacientes.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verificarCSRFToken($csrfToken)) {
        $_SESSION['error'] = 'Token de segurança inválido. Atualize a página e tente novamente.';
        header('Location: ' . ($isEdit ? "pacientes_form.php?id={$paciente_id}" : 'pacientes_form.php'));
        exit;
    }

    $nome = trim($_POST['nome'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $cpf = preg_replace('/[^0-9]/', '', trim($_POST['cpf'] ?? ''));
    $cartao_sus = preg_replace('/[^0-9]/', '', trim($_POST['cartao_sus'] ?? ''));
    $sexo = trim($_POST['sexo'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    $errors = [];

    if ($nome === '') {
        $errors[] = 'O nome é obrigatório.';
    } elseif (strlen($nome) > 255) {
        $errors[] = 'O nome não pode ter mais de 255 caracteres.';
    }

    if ($data_nascimento === '') {
        $errors[] = 'A data de nascimento é obrigatória.';
    } else {
        $dateObj = DateTime::createFromFormat('Y-m-d', $data_nascimento);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $data_nascimento) {
            $errors[] = 'Data de nascimento inválida.';
        } elseif ($dateObj > new DateTime()) {
            $errors[] = 'A data de nascimento não pode ser no futuro.';
        } elseif ($dateObj < new DateTime('-150 years')) {
            $errors[] = 'Data de nascimento inválida.';
        }
    }

    if ($cpf !== '') {
        if (strlen($cpf) !== 11) {
            $errors[] = 'O CPF deve ter 11 dígitos.';
        } elseif (!validarCPF($cpf)) {
            $errors[] = 'CPF inválido. Por favor, verifique o número digitado.';
        } else {
            try {
                $checkSql = 'SELECT id FROM pacientes WHERE cpf = ? AND ativo = 1';
                $checkParams = [$cpf];

                if ($isEdit) {
                    $checkSql .= ' AND id != ?';
                    $checkParams[] = $paciente_id;
                }

                $stmt = $conn->prepare($checkSql);
                $stmt->execute($checkParams);

                if ($stmt->fetch()) {
                    $errors[] = 'Já existe um paciente cadastrado com este CPF.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Erro ao verificar CPF: ' . $e->getMessage();
            }
        }
    }

    if ($cartao_sus !== '') {
        if (strlen($cartao_sus) !== 15) {
            $errors[] = 'O Cartão SUS deve ter 15 dígitos.';
        } else {
            try {
                $checkSql = 'SELECT id FROM pacientes WHERE cartao_sus = ? AND ativo = 1';
                $checkParams = [$cartao_sus];

                if ($isEdit) {
                    $checkSql .= ' AND id != ?';
                    $checkParams[] = $paciente_id;
                }

                $stmt = $conn->prepare($checkSql);
                $stmt->execute($checkParams);

                if ($stmt->fetch()) {
                    $errors[] = 'Já existe um paciente cadastrado com este Cartão SUS.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Erro ao verificar Cartão SUS: ' . $e->getMessage();
            }
        }
    }

    if ($sexo === '' || !in_array($sexo, ['M', 'F', 'Outro'], true)) {
        $errors[] = 'O sexo é obrigatório.';
    }

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $sql = 'UPDATE pacientes SET nome = ?, data_nascimento = ?, cpf = ?, cartao_sus = ?, sexo = ?, observacoes = ?, atualizado_em = NOW() WHERE id = ?';
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $nome,
                    $data_nascimento,
                    $cpf ?: null,
                    $cartao_sus ?: null,
                    $sexo,
                    $observacoes ?: null,
                    $paciente_id
                ]);
                $_SESSION['success'] = 'Paciente atualizado com sucesso!';
            } else {
                $sql = 'INSERT INTO pacientes (nome, data_nascimento, cpf, cartao_sus, sexo, observacoes) VALUES (?, ?, ?, ?, ?, ?)';
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $nome,
                    $data_nascimento,
                    $cpf ?: null,
                    $cartao_sus ?: null,
                    $sexo,
                    $observacoes ?: null
                ]);
                $_SESSION['success'] = 'Paciente cadastrado com sucesso!';
            }

            header('Location: pacientes.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Erro ao salvar paciente: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        $paciente = array_merge($paciente, [
            'nome' => $nome,
            'data_nascimento' => $data_nascimento,
            'cpf' => $cpf,
            'cartao_sus' => $cartao_sus,
            'sexo' => $sexo,
            'observacoes' => $observacoes
        ]);
    }
}

$errorMessage = getErrorMessage();
$csrfToken = gerarCSRFToken();
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
                    <nav class="flex items-center gap-2 text-sm text-slate-500">
                        <a href="pacientes.php" class="hover:text-primary-600 transition">Pacientes</a>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg>
                        <span class="text-slate-900 font-medium"><?php echo htmlspecialchars($pageTitle); ?></span>
                    </nav>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="mt-2 text-slate-500 max-w-2xl">Preencha os dados do paciente. Campos marcados com * são obrigatórios.</p>
                    </div>
                </div>
            </header>

            <?php if (!empty($errorMessage)): ?>
                <div class="glass-card border border-rose-200/60 bg-rose-50/80 px-6 py-4 text-rose-700">
                    <strong class="block text-sm font-semibold">Atenção</strong>
                    <span class="text-sm"><?php echo $errorMessage; ?></span>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <section class="glass-card p-6 lg:p-8 space-y-6">
                    <h2 class="text-lg font-semibold text-slate-900 border-b border-white/60 pb-3">Dados pessoais</h2>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="lg:col-span-2">
                            <label for="nome" class="text-sm font-medium text-slate-700">Nome completo <span class="text-rose-500">*</span></label>
                            <input type="text" name="nome" id="nome" value="<?php echo htmlspecialchars($paciente['nome']); ?>" required maxlength="255" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="Ex.: João da Silva">
                        </div>
                        <div>
                            <label for="data_nascimento" class="text-sm font-medium text-slate-700">Data de nascimento <span class="text-rose-500">*</span></label>
                            <input type="date" name="data_nascimento" id="data_nascimento" value="<?php echo htmlspecialchars($paciente['data_nascimento']); ?>" required max="<?php echo date('Y-m-d'); ?>" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                        </div>
                        <div>
                            <label for="sexo" class="text-sm font-medium text-slate-700">Sexo <span class="text-rose-500">*</span></label>
                            <select name="sexo" id="sexo" required class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                                <option value="">Selecione...</option>
                                <option value="M" <?php echo $paciente['sexo'] === 'M' ? 'selected' : ''; ?>>Masculino</option>
                                <option value="F" <?php echo $paciente['sexo'] === 'F' ? 'selected' : ''; ?>>Feminino</option>
                                <option value="Outro" <?php echo $paciente['sexo'] === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="glass-card p-6 lg:p-8 space-y-6">
                    <h2 class="text-lg font-semibold text-slate-900 border-b border-white/60 pb-3">Documentos</h2>
                    <p class="text-sm text-slate-500">Pelo menos um dos documentos (CPF ou Cartão SUS) é recomendado para identificação única do paciente.</p>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <label for="cpf" class="text-sm font-medium text-slate-700">CPF</label>
                            <input type="text" name="cpf" id="cpf" value="<?php echo htmlspecialchars($paciente['cpf']); ?>" maxlength="14" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="000.000.000-00">
                            <p class="mt-1 text-xs text-slate-400">Apenas números</p>
                        </div>
                        <div>
                            <label for="cartao_sus" class="text-sm font-medium text-slate-700">Cartão SUS</label>
                            <input type="text" name="cartao_sus" id="cartao_sus" value="<?php echo htmlspecialchars($paciente['cartao_sus']); ?>" maxlength="18" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="000 0000 0000 0000">
                            <p class="mt-1 text-xs text-slate-400">15 dígitos</p>
                        </div>
                    </div>
                </section>

                <section class="glass-card p-6 lg:p-8 space-y-6">
                    <h2 class="text-lg font-semibold text-slate-900 border-b border-white/60 pb-3">Observações</h2>
                    <div>
                        <label for="observacoes" class="text-sm font-medium text-slate-700">Observações adicionais</label>
                        <textarea name="observacoes" id="observacoes" rows="4" maxlength="1000" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="Informações adicionais sobre o paciente, alergias, restrições, etc."><?php echo htmlspecialchars($paciente['observacoes']); ?></textarea>
                        <p class="mt-1 text-xs text-slate-400">Máximo de 1000 caracteres</p>
                    </div>
                </section>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-8 py-3 text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
                        <?php echo $isEdit ? 'Atualizar paciente' : 'Cadastrar paciente'; ?>
                    </button>
                    <a href="pacientes.php" class="inline-flex items-center gap-2 rounded-full bg-white px-8 py-3 text-slate-600 font-semibold shadow hover:shadow-lg transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        Cancelar
                    </a>
                </div>
            </form>
        </main>
    </div>

    <script>
        const cpfInput = document.getElementById('cpf');
        if (cpfInput) {
            cpfInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 11) {
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                    e.target.value = value;
                }
            });
        }

        const cartaoInput = document.getElementById('cartao_sus');
        if (cartaoInput) {
            cartaoInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 15) {
                    value = value.replace(/(\d{3})(\d)/, '$1 $2');
                    value = value.replace(/(\d{4})(\d)/, '$1 $2');
                    value = value.replace(/(\d{4})(\d)/, '$1 $2');
                    value = value.replace(/(\d{4})(\d{1,4})$/, '$1');
                    e.target.value = value;
                }
            });
        }
    </script>
</body>
</html>
