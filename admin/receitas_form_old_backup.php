<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();
$pageTitle = 'Cadastrar Receita Médica';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verificarCSRFToken($csrfToken)) {
        $_SESSION['error'] = 'Token de segurança inválido. Atualize a página e tente novamente.';
    } else {
        $paciente_id = (int)($_POST['paciente_id'] ?? 0);
        $data_emissao = trim($_POST['data_emissao'] ?? '');
        $data_validade = trim($_POST['data_validade'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');
        $medicamentos = json_decode($_POST['medicamentos_json'] ?? '[]', true);
        
        $errors = [];
        
        if (empty($paciente_id)) {
            $errors[] = "Selecione um paciente.";
        }
        
        if (empty($data_emissao)) {
            $errors[] = "Data de emissão é obrigatória.";
        }
        
        if (empty($data_validade)) {
            $errors[] = "Data de validade é obrigatória.";
        } elseif ($data_validade <= $data_emissao) {
            $errors[] = "Data de validade deve ser posterior à data de emissão.";
        }
        
        if (empty($medicamentos) || !is_array($medicamentos)) {
            $errors[] = "Adicione pelo menos um medicamento à receita.";
        }
        
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                // Inserir receita
                $stmt = $conn->prepare("
                    INSERT INTO receitas (paciente_id, data_emissao, data_validade, observacoes, status)
                    VALUES (?, ?, ?, ?, 'ativa')
                ");
                $stmt->execute([
                    $paciente_id,
                    $data_emissao,
                    $data_validade,
                    $observacoes ?: null
                ]);
                
                $receita_id = $conn->lastInsertId();
                
                // Inserir itens da receita
                $stmt = $conn->prepare("
                    INSERT INTO receitas_itens 
                    (receita_id, medicamento_id, quantidade_autorizada, intervalo_dias, observacoes)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($medicamentos as $med) {
                    $stmt->execute([
                        $receita_id,
                        (int)$med['medicamento_id'],
                        (int)$med['quantidade_autorizada'],
                        (int)($med['intervalo_dias'] ?? 30),
                        trim($med['observacoes'] ?? '') ?: null
                    ]);
                }
                
                $conn->commit();
                $_SESSION['success'] = "Receita cadastrada com sucesso! Você pode iniciar a dispensação.";
                header('Location: index.php');
                exit;
                
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Erro ao cadastrar receita: " . $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
        }
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
        <main class="flex-1 px-6 py-10 lg:px-12 space-y-8">
            <header class="flex flex-col gap-4">
                <nav class="flex items-center gap-2 text-sm text-slate-500">
                    <a href="index.php" class="hover:text-primary-600 transition">Dispensação</a>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg>
                    <span class="text-slate-900 font-medium">Nova Receita</span>
                </nav>
                <div>
                    <h1 class="text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p class="mt-2 text-slate-500 max-w-2xl">Registre receitas médicas com múltiplos medicamentos e controle de retiradas.</p>
                </div>
            </header>

            <?php if (!empty($errorMessage)): ?>
                <div class="glass-card border border-rose-200/60 bg-rose-50/80 px-6 py-4 text-rose-700">
                    <strong class="block text-sm font-semibold">Atenção</strong>
                    <span class="text-sm"><?php echo $errorMessage; ?></span>
                </div>
            <?php endif; ?>

            <form method="post" id="receitaForm" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="paciente_id" id="paciente_id" value="">
                <input type="hidden" name="medicamentos_json" id="medicamentos_json" value="">

                <!-- Seleção de Paciente -->
                <section class="glass-card p-6 lg:p-8 space-y-6">
                    <h2 class="text-lg font-semibold text-slate-900 border-b border-white/60 pb-3">Dados do Paciente</h2>
                    
                    <div>
                        <label for="pacienteSearch" class="text-sm font-medium text-slate-700">Buscar paciente <span class="text-rose-500">*</span></label>
                        <div class="relative mt-2">
                            <input 
                                type="text" 
                                id="pacienteSearch" 
                                class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 pl-11 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" 
                                placeholder="Digite nome, CPF ou Cartão SUS..."
                                autocomplete="off">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                        </div>

                        <!-- Resultados -->
                        <div id="pacienteResults" class="hidden mt-3 rounded-2xl border border-slate-200 bg-white shadow-lg overflow-hidden max-h-64 overflow-y-auto"></div>

                        <!-- Paciente selecionado -->
                        <div id="pacienteSelecionado" class="hidden mt-3 rounded-2xl border-2 border-primary-200 bg-primary-50/50 p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-semibold text-slate-900" id="pacNome"></h4>
                                    <p class="text-sm text-slate-600 mt-1">
                                        <span id="pacCpf"></span>
                                        <span class="mx-2">•</span>
                                        <span id="pacCartaoSus"></span>
                                    </p>
                                </div>
                                <button type="button" onclick="limparPaciente()" class="text-slate-400 hover:text-rose-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Datas da Receita -->
                <section class="glass-card p-6 lg:p-8 space-y-6">
                    <h2 class="text-lg font-semibold text-slate-900 border-b border-white/60 pb-3">Dados da Receita</h2>
                    
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <label for="data_emissao" class="text-sm font-medium text-slate-700">Data de emissão <span class="text-rose-500">*</span></label>
                            <input type="date" name="data_emissao" id="data_emissao" required value="<?php echo date('Y-m-d'); ?>" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                        </div>
                        <div>
                            <label for="data_validade" class="text-sm font-medium text-slate-700">Data de validade <span class="text-rose-500">*</span></label>
                            <input type="date" name="data_validade" id="data_validade" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d', strtotime('+90 days')); ?>" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                        </div>
                    </div>

                    <div>
                        <label for="observacoes" class="text-sm font-medium text-slate-700">Observações gerais</label>
                        <textarea name="observacoes" id="observacoes" rows="3" class="mt-2 w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="Informações adicionais sobre a receita..."></textarea>
                    </div>
                </section>

                <!-- Medicamentos -->
                <section class="glass-card p-6 lg:p-8 space-y-6">
                    <div class="flex items-center justify-between border-b border-white/60 pb-3">
                        <h2 class="text-lg font-semibold text-slate-900">Medicamentos da Receita</h2>
                        <button type="button" onclick="mostrarModalMedicamento()" class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-5 py-2 text-sm text-white font-semibold shadow hover:bg-primary-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Adicionar Medicamento
                        </button>
                    </div>

                    <div id="medicamentosList" class="space-y-3">
                        <p class="text-sm text-slate-400 text-center py-8">Nenhum medicamento adicionado. Clique no botão acima para adicionar.</p>
                    </div>
                </section>

                <!-- Botões -->
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-8 py-3 text-white font-semibold shadow-glow hover:bg-emerald-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
                        Cadastrar Receita
                    </button>
                    <a href="dispensacao.php" class="inline-flex items-center gap-2 rounded-full bg-white px-8 py-3 text-slate-600 font-semibold shadow hover:shadow-lg transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        Cancelar
                    </a>
                </div>
            </form>
        </main>
    </div>

    <!-- Modal Adicionar Medicamento -->
    <div id="modalMedicamento" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="glass-card max-w-2xl w-full mx-4 p-8 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-semibold text-slate-900">Adicionar Medicamento</h3>
                <button type="button" onclick="fecharModalMedicamento()" class="text-slate-400 hover:text-slate-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label for="modalMedicamentoSearch" class="text-sm font-medium text-slate-700">Buscar medicamento</label>
                    <input 
                        type="text" 
                        id="modalMedicamentoSearch" 
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" 
                        placeholder="Digite o nome do medicamento..."
                        autocomplete="off">
                    <div id="modalMedicamentoResults" class="hidden mt-2 rounded-2xl border border-slate-200 bg-white shadow-lg overflow-hidden max-h-48 overflow-y-auto"></div>
                </div>

                <div id="modalMedicamentoSelecionado" class="hidden space-y-4">
                    <div class="rounded-2xl bg-primary-50 p-4">
                        <h4 class="font-semibold text-slate-900" id="modalMedNome"></h4>
                        <p class="text-sm text-slate-600 mt-1" id="modalMedApresentacao"></p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <label for="modalQuantidade" class="text-sm font-medium text-slate-700">Quantidade autorizada</label>
                            <input type="number" id="modalQuantidade" min="1" value="1" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                        </div>
                        <div>
                            <label for="modalIntervalo" class="text-sm font-medium text-slate-700">Intervalo entre retiradas (dias)</label>
                            <input type="number" id="modalIntervalo" min="1" value="30" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                        </div>
                    </div>

                    <div>
                        <label for="modalObservacoes" class="text-sm font-medium text-slate-700">Observações</label>
                        <textarea id="modalObservacoes" rows="2" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500" placeholder="Ex.: Tomar 1 vez ao dia"></textarea>
                    </div>

                    <button type="button" onclick="adicionarMedicamento()" class="w-full inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-6 py-3 text-white font-semibold shadow hover:bg-primary-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Adicionar à Receita
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/sidebar.js" defer></script>
    <script src="js/receitas_form.js"></script>
</body>
</html>
