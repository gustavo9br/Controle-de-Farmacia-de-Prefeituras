<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$conn = getConnection();

$error = $success = '';
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_data = [];

// Buscar funcionários
$funcionarios = [];
try {
    $funcionarios = $conn->query("SELECT id, nome, ativo, criado_em FROM funcionarios ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabela pode não existir ainda
    $funcionarios = [];
}

// Carregar dados para edição
if ($edit_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT id, nome, ativo FROM funcionarios WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Erro ao carregar dados: " . $e->getMessage();
    }
}

$pageTitle = 'Gerenciar Funcionários';
$successMessage = getSuccessMessage();
$errorMessage = getErrorMessage() ?: $error;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo SYSTEM_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../css/admin_new.css">
</head>
<body class="admin-shell">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="content-area">
        <div class="space-y-10">
            <header class="flex flex-col gap-4 lg:gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2 sm:space-y-3">
                    <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Configurações</span>
                    <div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="mt-2 text-sm sm:text-base text-slate-500 max-w-2xl">Gerencie os funcionários responsáveis pelas dispensações.</p>
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

            <!-- Formulário -->
            <div class="glass-card p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-slate-900 mb-4 sm:mb-6 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span><?php echo $edit_id > 0 ? 'Editar Funcionário' : 'Adicionar Funcionário'; ?></span>
                </h2>
                
                <form id="formFuncionario" class="space-y-5">
                    <input type="hidden" id="funcionario_id" value="<?php echo $edit_id; ?>">
                    
                    <div>
                        <label for="nome" class="block text-sm font-medium text-slate-700 mb-2">
                            Nome <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="nome" name="nome" 
                               value="<?php echo isset($edit_data['nome']) ? htmlspecialchars($edit_data['nome']) : ''; ?>" 
                               required 
                               class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    
                    <div>
                        <label for="senha" class="block text-sm font-medium text-slate-700 mb-2">
                            Senha <?php echo $edit_id > 0 ? '(deixe em branco para não alterar)' : ''; ?> <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="senha" name="senha" 
                               placeholder="Digite apenas números (ex: 1234)" 
                               <?php echo $edit_id > 0 ? '' : 'required'; ?>
                               pattern="[0-9]*"
                               inputmode="numeric"
                               class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                        <p class="mt-1 text-xs text-slate-500">A senha deve conter apenas números</p>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-4 border-t border-slate-200 mt-4" style="min-height: 50px;">
                        <?php if ($edit_id > 0): ?>
                            <a href="funcionarios.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-white border-2 border-slate-300 px-4 sm:px-6 py-2.5 sm:py-3 text-slate-700 font-semibold shadow-md hover:shadow-lg hover:bg-slate-50 transition-all text-sm sm:text-base" style="display: inline-flex !important; visibility: visible !important; opacity: 1 !important;">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Cancelar
                            </a>
                        <?php endif; ?>
                        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 px-4 sm:px-6 py-2.5 sm:py-3 text-white font-semibold shadow-lg hover:shadow-xl transition-all text-sm sm:text-base" style="display: inline-flex !important; visibility: visible !important; opacity: 1 !important;">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <?php echo $edit_id > 0 ? 'Atualizar' : 'Salvar'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabela -->
            <div class="glass-card p-0 overflow-hidden">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-0 px-4 sm:px-6 py-3 sm:py-4 border-b border-white/60 bg-white/70">
                    <h2 class="text-base sm:text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span>Funcionários Cadastrados</span>
                    </h2>
                    <span class="rounded-full bg-primary-50 px-3 sm:px-4 py-1 text-xs sm:text-sm font-medium text-primary-600 whitespace-nowrap">
                        <?php echo count($funcionarios); ?> registros
                    </span>
                </div>
                
                <?php if (empty($funcionarios)): ?>
                    <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                        <p class="text-sm">Nenhum funcionário cadastrado.</p>
                    </div>
                <?php else: ?>
                    <div class="responsive-table-wrapper">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-white/60">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-4 sm:px-6 py-3">Nome</th>
                                    <th class="px-4 sm:px-6 py-3">Cadastrado em</th>
                                    <th class="px-4 sm:px-6 py-3">Status</th>
                                    <th class="px-4 sm:px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white/80">
                                <?php foreach ($funcionarios as $funcionario): ?>
                                    <tr class="text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                                        <td data-label="Nome" class="px-4 sm:px-6 py-3 sm:py-4 font-medium text-slate-900">
                                            <?php echo htmlspecialchars($funcionario['nome']); ?>
                                        </td>
                                        <td data-label="Cadastrado em" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <?php echo formatarData($funcionario['criado_em']); ?>
                                        </td>
                                        <td data-label="Status" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <?php if ($funcionario['ativo']): ?>
                                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Ativo
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    Inativo
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Ações" class="px-4 sm:px-6 py-3 sm:py-4">
                                            <div class="flex items-center justify-start sm:justify-end gap-2">
                                                <a href="?edit=<?php echo $funcionario['id']; ?>" class="action-chip" title="Editar">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                                
                                                <button onclick="toggleStatus(<?php echo $funcionario['id']; ?>, <?php echo $funcionario['ativo']; ?>)" 
                                                        class="action-chip <?php echo $funcionario['ativo'] ? 'hover:bg-rose-50 hover:text-rose-600' : 'hover:bg-emerald-50 hover:text-emerald-600'; ?>" 
                                                        title="<?php echo $funcionario['ativo'] ? 'Desativar' : 'Ativar'; ?>">
                                                    <?php if ($funcionario['ativo']): ?>
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                    <?php else: ?>
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    <?php endif; ?>
                                                </button>
                                                
                                                <button onclick="deletarFuncionario(<?php echo $funcionario['id']; ?>)" 
                                                        class="action-chip hover:bg-rose-50 hover:text-rose-600" 
                                                        title="Excluir">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="js/sidebar.js" defer></script>
    <script>
        document.getElementById('formFuncionario').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const id = document.getElementById('funcionario_id').value;
            const nome = document.getElementById('nome').value.trim();
            const senha = document.getElementById('senha').value.trim();
            
            if (!nome) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'O nome é obrigatório'
                });
                return;
            }
            
            if (!id && !senha) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'A senha é obrigatória para novos funcionários'
                });
                return;
            }
            
            if (senha && !/^\d+$/.test(senha)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'A senha deve conter apenas números'
                });
                return;
            }
            
            const data = {
                action: id > 0 ? 'update' : 'create',
                nome: nome,
                senha: senha || undefined
            };
            
            if (id > 0) {
                data.id = id;
            }
            
            try {
                const response = await fetch('api/funcionarios.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: result.message
                    }).then(() => {
                        window.location.href = 'funcionarios.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: result.message
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao processar: ' + error.message
                });
            }
        });
        
        async function toggleStatus(id, ativo) {
            const confirmacao = await Swal.fire({
                title: 'Confirmar Alteração',
                text: `Deseja ${ativo ? 'desativar' : 'ativar'} este funcionário?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim',
                cancelButtonText: 'Cancelar'
            });
            
            if (!confirmacao.isConfirmed) return;
            
            try {
                const response = await fetch('api/funcionarios.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'toggle_status',
                        id: id,
                        ativo: ativo
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: result.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: result.message
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao processar: ' + error.message
                });
            }
        }
        
        async function deletarFuncionario(id) {
            const confirmacao = await Swal.fire({
                title: 'Confirmar Exclusão',
                text: 'Tem certeza que deseja excluir este funcionário? Esta ação não pode ser desfeita.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            });
            
            if (!confirmacao.isConfirmed) return;
            
            try {
                const response = await fetch('api/funcionarios.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        id: id
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: result.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: result.message
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao processar: ' + error.message
                });
            }
        }
    </script>
</body>
</html>

