<?php
require_once '../includes/auth.php';
requireAdmin();

$pageTitle = "Dispensação de Medicamentos";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Controle de medicamentos, lotes, pacientes e receitas">
    <meta name="keywords" content="farmácia, medicamentos, gestão, controle de estoque, receitas, dispensação">
    <meta name="author" content="Sistema Farmácia">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo $pageTitle; ?> - Farmácia Popular">
    <meta property="og:description" content="Sistema de gestão de farmácia">
    <meta property="og:type" content="website">
    <meta property="og:image" content="../images/logo.svg">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="../images/logo.svg">
    <link rel="apple-touch-icon" href="../images/logo.svg">
    
    <title><?php echo $pageTitle; ?> - Farmácia Popular</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/admin_new.css">
</head>
<body class="admin-shell bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-50 min-h-screen">
    
    <?php include 'includes/sidebar.php'; ?>

    <main class="content-area">
        <!-- Header com Título -->
        <div class="glass-card p-4 mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <span class="text-2xl">💊</span>
                        Nova Dispensação
                    </h1>
                    <p class="text-xs text-gray-600 mt-1">Registre a dispensação de medicamentos para pacientes</p>
                </div>
                <button onclick="limparTudo()" class="px-4 py-2 bg-gradient-to-r from-purple-500 to-indigo-500 hover:from-purple-600 hover:to-indigo-600 text-white rounded-lg transition-all text-sm font-medium shadow-md">
                    🔄 Limpar Tudo
                </button>
            </div>
        </div>

        <!-- Layout 2 Colunas: Nome + Remédios -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6 min-h-[400px]">
            
            <!-- COLUNA ESQUERDA: NOME DO PACIENTE -->
            <div class="space-y-4">
                <div class="glass-card p-4">
                    <h3 class="text-base font-semibold text-gray-800 mb-3 flex items-center gap-2">
                        <span>👤</span> Nome do Paciente
                    </h3>
                    
                    <div class="relative">
                        <input 
                            type="text" 
                            id="pacienteSearch" 
                            placeholder="🔍 Nome, CPF ou SUS..." 
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all text-sm"
                            autocomplete="off"
                        >
                        
                        <div id="pacienteLoader" class="hidden absolute right-3 top-3">
                            <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-purple-600"></div>
                        </div>
                        
                        <div id="pacienteResults" class="hidden absolute z-50 w-full mt-2 bg-white rounded-lg shadow-xl border border-gray-100 max-h-64 overflow-y-auto"></div>
                    </div>
                    
                    <div id="pacienteSelecionado" class="hidden mt-3 p-3 bg-gradient-to-r from-emerald-50 to-teal-50 rounded-lg border border-emerald-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center text-sm font-bold" id="pacienteAvatar"></div>
                                <div>
                                    <p class="font-semibold text-gray-800 text-sm" id="pacienteNome"></p>
                                    <p class="text-xs text-gray-600" id="pacienteInfo"></p>
                                </div>
                            </div>
                            <button onclick="removerPaciente()" class="text-red-500 hover:text-red-700 text-lg font-bold">×</button>
                        </div>
                    </div>
                </div>

                <!-- Botão Finalizar (abaixo do nome) -->
                <div class="glass-card p-4 hidden" id="btnFinalizarContainer">
                    <textarea 
                        id="observacoes" 
                        placeholder="Observações (opcional)..." 
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all text-sm resize-none mb-3"
                        rows="2"
                    ></textarea>
                    
                    <button 
                        onclick="finalizarDispensacao()" 
                        class="w-full px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white font-bold rounded-lg shadow-lg hover:shadow-xl transition-all text-base"
                    >
                        ✓ Finalizar Dispensação
                    </button>
                </div>
            </div>

            <!-- COLUNA DIREITA: REMÉDIOS -->
            <div class="glass-card p-4 hidden" id="stepMedicamentos">
                <h3 class="text-base font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    <span>💊</span> Remédios
                </h3>
                
                <div class="relative mb-4">
                    <input 
                        type="text" 
                        id="medicamentoSearch" 
                        placeholder="🔍 Código ou nome do medicamento..." 
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                        autocomplete="off"
                    >
                    
                    <div id="medicamentoLoader" class="hidden absolute right-3 top-3">
                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                    </div>
                    
                    <div id="medicamentoResults" class="hidden absolute z-40 w-full mt-2 bg-white rounded-lg shadow-xl border border-gray-100 max-h-64 overflow-y-auto"></div>
                </div>
                
                <div id="medicamentosAdicionados" class="space-y-3"></div>
            </div>

        </div>

        <!-- Log de Dispensações (Abaixo com espaçamento maior) -->
        <div class="glass-card p-4 mt-20">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-base">📋</span>
                <h3 class="text-base font-semibold text-gray-800">Últimas Dispensações</h3>
            </div>
            
            <div id="logDispensacoes" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <div class="col-span-full text-center py-8 text-gray-400 text-sm">
                    Carregando...
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Sucesso -->
    <div id="modalSucesso" class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 transform transition-all">
            <div class="text-center">
                <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-white text-3xl">✓</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Dispensação Registrada!</h3>
                <p class="text-gray-600 mb-6" id="modalMensagem"></p>
                <button 
                    onclick="fecharModal()" 
                    class="w-full px-6 py-3 bg-gradient-to-r from-purple-500 to-indigo-500 hover:from-purple-600 hover:to-indigo-600 text-white font-semibold rounded-lg transition-all"
                >
                    Nova Dispensação
                </button>
            </div>
        </div>
    </div>

    <script src="js/dispensacao_nova.js"></script>
</body>
</html>
