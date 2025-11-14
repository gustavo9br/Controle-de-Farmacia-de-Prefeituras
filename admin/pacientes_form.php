<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Função para validar CPF
function validarCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Validação do primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += intval($cpf[$i]) * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : (11 - $resto);
    
    if (intval($cpf[9]) != $digito1) {
        return false;
    }
    
    // Validação do segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += intval($cpf[$i]) * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : (11 - $resto);
    
    if (intval($cpf[10]) != $digito2) {
        return false;
    }
    
    return true;
}

$paciente_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
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
        $stmt = $conn->prepare("SELECT * FROM pacientes WHERE id = ? AND ativo = 1");
        $stmt->execute([$paciente_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            $_SESSION['error'] = "Paciente não encontrado!";
            header('Location: pacientes.php');
            exit;
        }
        
        $paciente = $result;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erro ao buscar paciente: " . $e->getMessage();
        header('Location: pacientes.php');
        exit;
    }
}

// Processar formulário
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

    // Validações
    if (empty($nome)) {
        $errors[] = "O nome é obrigatório.";
    } elseif (strlen($nome) > 255) {
        $errors[] = "O nome não pode ter mais de 255 caracteres.";
    }

    if (empty($data_nascimento)) {
        $errors[] = "A data de nascimento é obrigatória.";
    } else {
        $dateObj = DateTime::createFromFormat('Y-m-d', $data_nascimento);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $data_nascimento) {
            $errors[] = "Data de nascimento inválida.";
        } elseif ($dateObj > new DateTime()) {
            $errors[] = "A data de nascimento não pode ser no futuro.";
        } elseif ($dateObj < new DateTime('-150 years')) {
            $errors[] = "Data de nascimento inválida.";
        }
    }

    if (!empty($cpf)) {
        if (strlen($cpf) !== 11) {
            $errors[] = "O CPF deve ter 11 dígitos.";
        } elseif (!validarCPF($cpf)) {
            $errors[] = "CPF inválido. Por favor, verifique o número digitado.";
        } else {
            // Verificar se CPF já existe (exceto para o próprio registro em edições)
            try {
                $checkSql = "SELECT id FROM pacientes WHERE cpf = ? AND ativo = 1";
                $checkParams = [$cpf];
                
                if ($isEdit) {
                    $checkSql .= " AND id != ?";
                    $checkParams[] = $paciente_id;
                }
                
                $stmt = $conn->prepare($checkSql);
                $stmt->execute($checkParams);
                
                if ($stmt->fetch()) {
                    $errors[] = "Já existe um paciente cadastrado com este CPF.";
                }
            } catch (PDOException $e) {
                $errors[] = "Erro ao verificar CPF: " . $e->getMessage();
            }
        }
    }

    if (!empty($cartao_sus)) {
        if (strlen($cartao_sus) !== 15) {
            $errors[] = "O Cartão SUS deve ter 15 dígitos.";
        } else {
            // Verificar se cartão SUS já existe
            try {
                $checkSql = "SELECT id FROM pacientes WHERE cartao_sus = ? AND ativo = 1";
                $checkParams = [$cartao_sus];
                
                if ($isEdit) {
                    $checkSql .= " AND id != ?";
                    $checkParams[] = $paciente_id;
                }
                
                $stmt = $conn->prepare($checkSql);
                $stmt->execute($checkParams);
                
                if ($stmt->fetch()) {
                    $errors[] = "Já existe um paciente cadastrado com este Cartão SUS.";
                }
            } catch (PDOException $e) {
                $errors[] = "Erro ao verificar Cartão SUS: " . $e->getMessage();
            }
        }
    }

    if (empty($sexo) || !in_array($sexo, ['M', 'F', 'Outro'])) {
        $errors[] = "O sexo é obrigatório.";
    }

    // Se não há erros, salvar
    if (empty($errors)) {
        try {
            if ($isEdit) {
                $sql = "UPDATE pacientes SET 
                        nome = ?,
                        data_nascimento = ?,
                        cpf = ?,
                        cartao_sus = ?,
                        sexo = ?,
                        observacoes = ?,
                        atualizado_em = NOW()
                        WHERE id = ?";
                
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
                
                $_SESSION['success'] = "Paciente atualizado com sucesso!";
            } else {
                $sql = "INSERT INTO pacientes (nome, data_nascimento, cpf, cartao_sus, sexo, observacoes) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $nome,
                    $data_nascimento,
                    $cpf ?: null,
                    $cartao_sus ?: null,
                    $sexo,
                    $observacoes ?: null
                ]);
                
                $_SESSION['success'] = "Paciente cadastrado com sucesso!";
            }
            
            header('Location: pacientes.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = "Erro ao salvar paciente: " . $e->getMessage();
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
    <meta name="description" content="Sistema de gestão de farmácia - Controle de medicamentos, lotes, pacientes e receitas">
    <meta name="keywords" content="farmácia, medicamentos, gestão, controle de estoque, pacientes">
    <meta name="author" content="Sistema Farmácia">
    <meta name="robots" content="noindex, nofollow">
    
    <?php 
    $ogTitle = htmlspecialchars($pageTitle) . ' - Gov Farma';
    $ogDescription = 'Gov Farma - Cadastro e edição de pacientes. Informações completas para gestão farmacêutica.';
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
        <main class="content-area">
            <div class="space-y-6">
            <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p class="mt-1 text-sm text-slate-500">Campos marcados com * são obrigatórios.</p>
                </div>
                <div class="flex gap-3">
                    <a href="pacientes.php" class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-2.5 text-slate-600 font-semibold shadow hover:shadow-lg transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        Cancelar
                    </a>
                    <button type="submit" form="formPaciente" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-6 py-2.5 text-white font-semibold shadow-glow hover:bg-primary-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
                        <?php echo $isEdit ? 'Atualizar' : 'Cadastrar'; ?>
                    </button>
                </div>
            </header>

            <?php if (!empty($errorMessage)): ?>
                <div class="glass-card border border-rose-200/60 bg-rose-50/80 px-4 py-3 text-rose-700 text-sm">
                    <strong class="block font-semibold mb-1">Atenção</strong>
                    <span><?php echo $errorMessage; ?></span>
                </div>
            <?php endif; ?>

            <form method="post" id="formPaciente" class="glass-card p-5 space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="lg:col-span-2">
                        <label for="nome" class="block text-sm font-semibold text-slate-700 mb-1">Nome completo <span class="text-rose-500">*</span></label>
                        <input type="text" name="nome" id="nome" value="<?php echo htmlspecialchars($paciente['nome'] ?? ''); ?>" required maxlength="255" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500" placeholder="Ex.: João da Silva">
                    </div>
                    
                    <div>
                        <label for="data_nascimento" class="block text-sm font-semibold text-slate-700 mb-1">Data de nascimento <span class="text-rose-500">*</span></label>
                        <input type="date" name="data_nascimento" id="data_nascimento" value="<?php echo htmlspecialchars($paciente['data_nascimento'] ?? ''); ?>" required max="<?php echo date('Y-m-d'); ?>" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500">
                    </div>

                    <div>
                        <label for="sexo" class="block text-sm font-semibold text-slate-700 mb-1">Sexo <span class="text-rose-500">*</span></label>
                        <select name="sexo" id="sexo" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500">
                            <option value="">Selecione...</option>
                            <option value="M" <?php echo $paciente['sexo'] === 'M' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="F" <?php echo $paciente['sexo'] === 'F' ? 'selected' : ''; ?>>Feminino</option>
                            <option value="Outro" <?php echo $paciente['sexo'] === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                        </select>
                    </div>

                    <div>
                        <label for="cpf" class="block text-sm font-semibold text-slate-700 mb-1">CPF</label>
                        <input type="text" name="cpf" id="cpf" value="<?php echo htmlspecialchars($paciente['cpf'] ?? ''); ?>" maxlength="14" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500" placeholder="000.000.000-00">
                    </div>

                    <div>
                        <label for="cartao_sus" class="block text-sm font-semibold text-slate-700 mb-1">Cartão SUS</label>
                        <input type="text" name="cartao_sus" id="cartao_sus" value="<?php echo htmlspecialchars($paciente['cartao_sus'] ?? ''); ?>" maxlength="18" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500" placeholder="000 0000 0000 0000">
                    </div>

                    <div class="lg:col-span-2">
                        <label for="observacoes" class="block text-sm font-semibold text-slate-700 mb-1">Observações</label>
                        <textarea name="observacoes" id="observacoes" rows="3" maxlength="1000" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 resize-none" placeholder="Informações adicionais sobre o paciente, alergias, restrições, etc."><?php echo htmlspecialchars($paciente['observacoes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </form>
            </div>
        </main>
    </div>

    <script src="js/sidebar.js" defer></script>
    <script>
        // Função para validar CPF
        function validarCPF(cpf) {
            // Remove caracteres não numéricos
            cpf = cpf.replace(/\D/g, '');
            
            // Verifica se tem 11 dígitos
            if (cpf.length !== 11) return false;
            
            // Verifica se todos os dígitos são iguais
            if (/^(\d)\1{10}$/.test(cpf)) return false;
            
            // Validação do primeiro dígito verificador
            let soma = 0;
            for (let i = 0; i < 9; i++) {
                soma += parseInt(cpf.charAt(i)) * (10 - i);
            }
            let resto = soma % 11;
            let digito1 = (resto < 2) ? 0 : (11 - resto);
            
            if (parseInt(cpf.charAt(9)) !== digito1) return false;
            
            // Validação do segundo dígito verificador
            soma = 0;
            for (let i = 0; i < 10; i++) {
                soma += parseInt(cpf.charAt(i)) * (11 - i);
            }
            resto = soma % 11;
            let digito2 = (resto < 2) ? 0 : (11 - resto);
            
            if (parseInt(cpf.charAt(10)) !== digito2) return false;
            
            return true;
        }

        // Máscara e validação para CPF
        const cpfInput = document.getElementById('cpf');
        const cpfFeedback = document.createElement('span');
        cpfFeedback.className = 'text-xs mt-1 block';
        cpfInput.parentElement.appendChild(cpfFeedback);

        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // Aplica a máscara
            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                e.target.value = value;
            }
            
            // Valida o CPF se tiver 11 dígitos
            const cpfLimpo = e.target.value.replace(/\D/g, '');
            if (cpfLimpo.length === 11) {
                if (validarCPF(cpfLimpo)) {
                    cpfInput.classList.remove('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
                    cpfInput.classList.add('border-emerald-500', 'focus:border-emerald-500', 'focus:ring-emerald-500');
                    cpfFeedback.textContent = '✓ CPF válido';
                    cpfFeedback.className = 'text-xs mt-1 text-emerald-600 font-medium';
                } else {
                    cpfInput.classList.remove('border-emerald-500', 'focus:border-emerald-500', 'focus:ring-emerald-500');
                    cpfInput.classList.add('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
                    cpfFeedback.textContent = '✗ CPF inválido';
                    cpfFeedback.className = 'text-xs mt-1 text-rose-600 font-medium';
                }
            } else {
                cpfInput.classList.remove('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500', 'border-emerald-500', 'focus:border-emerald-500', 'focus:ring-emerald-500');
                cpfFeedback.textContent = '';
            }
        });

        // Validação ao submeter o formulário
        document.getElementById('formPaciente').addEventListener('submit', function(e) {
            const cpfValue = cpfInput.value.replace(/\D/g, '');
            if (cpfValue.length > 0 && cpfValue.length === 11 && !validarCPF(cpfValue)) {
                e.preventDefault();
                cpfInput.focus();
                cpfInput.classList.add('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
                cpfFeedback.textContent = '✗ Por favor, corrija o CPF antes de salvar';
                cpfFeedback.className = 'text-xs mt-1 text-rose-600 font-medium';
                
                // Mostrar alerta
                alert('O CPF informado é inválido. Por favor, verifique o número digitado.');
            }
        });

        // Máscara para Cartão SUS (formato: 999 9999 9999 9999)
        document.getElementById('cartao_sus').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 15) {
                value = value.replace(/(\d{3})(\d)/, '$1 $2');
                value = value.replace(/(\d{3}) (\d{4})(\d)/, '$1 $2 $3');
                value = value.replace(/(\d{3}) (\d{4}) (\d{4})(\d)/, '$1 $2 $3 $4');
                e.target.value = value;
            }
        });
    </script>
</body>
</html>
