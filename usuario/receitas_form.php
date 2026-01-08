<?php
require_once '../includes/auth.php';
requireRole(['usuario']);

$pageTitle = "Nova Receita";
$receita = null;

if (isset($_GET['id'])) {
    $receita_id = $_GET['id'];
    $db = getConnection();
    $stmt = $db->prepare("SELECT r.*, p.nome as paciente_nome FROM receitas r INNER JOIN pacientes p ON r.paciente_id = p.id WHERE r.id = ?");
    $stmt->execute([$receita_id]);
    $receita = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($receita) {
        $pageTitle = "Editar Receita #" . $receita['numero_receita'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Controle de medicamentos, lotes, pacientes e receitas">
    <meta name="keywords" content="farmácia, medicamentos, gestão, controle de estoque, receitas">
    <meta name="author" content="Sistema Farmácia">
    <meta name="robots" content="noindex, nofollow">
    
    <?php 
    $ogTitle = $pageTitle . ' - Gov Farma';
    $ogDescription = 'Gov Farma - Cadastro e edição de receitas médicas. Prescrições com controle de retiradas planejadas.';
    include '../includes/og_meta.php'; 
    ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="../images/logo.svg">
    <link rel="apple-touch-icon" href="../images/logo.svg">
    
    <?php include '../includes/pwa_head.php'; ?>
    
    <title><?php echo $pageTitle; ?> - Farmácia Popular</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/admin_new.css">
</head>
<body class="admin-shell bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-50 min-h-screen">
    
    <?php include 'includes/sidebar.php'; ?>

    <main class="content-area">
        <div class="glass-card p-4 mb-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <h1 class="text-lg sm:text-xl font-bold text-gray-800 flex items-center gap-2">
                        <span class="text-xl sm:text-2xl">📄</span>
                        <span class="break-words"><?php echo $pageTitle; ?></span>
                    </h1>
                    <p class="text-xs text-gray-600 mt-1">Cadastre receitas para dispensação controlada de medicamentos</p>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
                    <a href="receitas.php" class="flex-1 sm:flex-none px-4 sm:px-6 py-2 sm:py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-all font-medium text-sm sm:text-base text-center">
                        Cancelar
                    </a>
                    <button type="submit" form="formReceita" class="flex-1 sm:flex-none px-4 sm:px-8 py-2 sm:py-2.5 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white rounded-lg shadow-lg hover:shadow-xl transition-all font-bold text-sm sm:text-base">
                        ✓ Cadastrar
                    </button>
                </div>
            </div>
        </div>

        <div class="glass-card p-6">
            <form id="formReceita" class="space-y-6">
                
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span>📋</span> Dados da Receita
                    </h3>
                    
                    <!-- Paciente -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <span class="text-red-500">*</span> Paciente
                        </label>
                        <div class="space-y-4">
                            <div class="relative">
                                <input type="text" id="pacienteSearch" placeholder="🔍 Digite o nome, CPF ou SUS..." class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm" autocomplete="off">
                                <input type="hidden" id="paciente_id">
                                <div id="pacienteResults" class="hidden absolute z-50 w-full mt-2 bg-white rounded-lg shadow-xl border border-gray-200 max-h-48 overflow-y-auto"></div>
                            </div>
                            <div id="pacienteInfo" class="hidden"></div>
                        </div>
                    </div>

                    <!-- Tipo de Receita e Número -->
                    <div class="mb-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Tipo de Receita
                                </label>
                                <select id="tipo_receita" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm" required>
                                    <option value="azul">Azul</option>
                                    <option value="branca">Branca</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Tipo de receita médica</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Número da Receita
                                </label>
                                <input type="text" id="numero_receita" placeholder="Ex: REC-2025-0001" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm bg-gray-50" required>
                                <p class="text-xs text-gray-500 mt-1" id="numero_receita_help">Digite o número da receita azul</p>
                            </div>
                        </div>
                    </div>

                    <!-- Datas -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <span class="text-red-500">*</span> Data de Apresentação
                            </label>
                            <input type="date" id="data_emissao" value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm" required>
                            <p class="text-xs text-gray-500 mt-1">Data em que a receita foi apresentada</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <span class="text-red-500">*</span> Data de Validade
                            </label>
                            <input type="date" id="data_validade" value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm" required>
                            <p class="text-xs text-gray-500 mt-1">Data até quando a receita é válida</p>
                        </div>
                    </div>
                </div>

                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span>💊</span> Medicamento da Receita
                    </h3>
                    
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <span class="text-red-500">*</span> Medicamento
                            </label>
                            <div class="relative">
                                <input type="text" id="medicamentoSearch" placeholder="🔍 Digite o código de barras ou nome do medicamento..." class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" autocomplete="off" disabled>
                                <input type="hidden" id="medicamento_id">
                                <div id="medicamentoResults" class="hidden absolute z-50 w-full mt-2 bg-white rounded-lg shadow-xl border border-gray-200 max-h-48 overflow-y-auto"></div>
                            </div>
                            <div id="medicamentoInfo" class="hidden mt-3"></div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-2">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Quantidade por Retirada
                                </label>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="alterarQuantidadeRetirada(-1)" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-bold transition-all text-sm">-1</button>
                                    <input type="number" id="quantidade_por_retirada" min="1" value="1" placeholder="Ex: 30" class="flex-1 px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm text-center" required>
                                    <button type="button" onclick="alterarQuantidadeRetirada(1)" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-bold transition-all text-sm">+1</button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Quantidade que o paciente leva por vez</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Número de Retiradas
                                </label>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="alterarNumeroRetiradas(-1)" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-bold transition-all text-sm">-1</button>
                                    <input type="number" id="numero_retiradas" min="1" max="12" value="1" placeholder="Ex: 3" class="flex-1 px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm text-center" required>
                                    <button type="button" onclick="alterarNumeroRetiradas(1)" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-bold transition-all text-sm">+1</button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Quantas vezes pode retirar (1-12)</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <span class="text-red-500">*</span> Intervalo (dias)
                                </label>
                                <select id="intervalo_dias" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" required>
                                    <option value="7">7 dias (1 semana)</option>
                                    <option value="15">15 dias</option>
                                    <option value="30" selected>30 dias (1 mês)</option>
                                    <option value="60">60 dias (2 meses)</option>
                                    <option value="90">90 dias (3 meses)</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Período mínimo entre retiradas</p>
                            </div>
                        </div>
                    </div>

                    <div id="previewRetiradas" class="mt-6 p-3 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border border-green-200 hidden">
                        <h4 class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Preview das Retiradas
                        </h4>
                        <div id="listaRetiradas" class="flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Observações</label>
                    <textarea id="observacoes" rows="3" placeholder="Informações adicionais sobre a receita (opcional)..." class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 text-sm resize-none"></textarea>
                </div>
            </form>
        </div>
    </main>

    <!-- Modal de Alerta Customizado -->
    <div id="modalAlerta" class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 transform transition-all">
            <div class="text-center">
                <div id="alertaIcon" class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-white text-3xl">ℹ</span>
                </div>
                <h3 id="alertaTitulo" class="text-xl font-bold text-gray-900 mb-2">Informação</h3>
                <p id="alertaMensagem" class="text-gray-600 mb-6"></p>
                <button onclick="fecharAlerta()" class="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white rounded-lg font-semibold transition-all">
                    OK
                </button>
            </div>
        </div>
    </div>

    <script>
        window.RECEITAS_API_BASE = '../admin/api/';
    </script>
    <script src="../admin/js/receitas_form.js"></script>
</body>
</html>
