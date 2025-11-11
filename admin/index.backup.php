<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pageTitle = 'Dispensação de Medicamentos';
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
    <style>
        body, html {
            height: 100%;
        }
        body.admin-shell {
            overflow: hidden;
        }
        main {
            height: 100vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            padding: 1.5rem !important;
        }
        .glass-card.active {
            opacity: 1 !important;
            pointer-events: auto !important;
            border: 2px solid rgba(99, 102, 241, 0.3);
        }
        .qty-btn-compact {
            height: 50px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .qty-btn-compact:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        .qty-btn-compact:active {
            transform: translateY(0);
        }
        .search-result-item {
            @apply p-2 hover:bg-primary-50 cursor-pointer transition-colors border-b border-slate-100 last:border-0;
        }
        .search-result-item:hover {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(236, 72, 153, 0.08) 100%);
        }
        /* Cards compactos */
        .glass-card {
            padding: 1rem !important;
        }
        #step1, #step2, #step3 {
            height: auto;
            max-height: calc(100vh - 250px);
            overflow: visible;
        }
        /* Ajustar header */
        header {
            padding: 1rem 0 !important;
        }
        /* Grid responsivo */
        @media (min-width: 1024px) {
            .dispensacao-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
                flex: 1;
                align-items: start;
            }
        }
        @media (max-width: 1024px) {
            #step1, #step2, #step3 {
                max-height: none;
            }
        }
    </style>
</head>
<body class="admin-shell">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="flex-1 px-6 lg:px-12">
            <header class="flex items-center justify-between py-4 border-b border-slate-200 mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Dispensação</h1>
                    <p class="text-xs text-slate-500">Registrar saída de medicamento</p>
                </div>
                <div class="flex gap-2">
                    <a href="receitas_form.php" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm text-primary-600 font-semibold shadow hover:shadow-md transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/></svg>
                        Receita
                    </a>
                    <a href="pacientes.php" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm text-slate-600 font-semibold shadow hover:shadow-md transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        Pacientes
                    </a>
                </div>
            </header>

            <!-- Mensagens -->
            <div id="alertContainer" class="hidden fixed top-4 right-4 z-50 max-w-md"></div>

            <!-- Formulário de Dispensação -->
            <form id="dispensacaoForm" onsubmit="processarDispensacao(event)">
            
            <!-- Layout em Grade Única - Tudo Visível -->
            <div class="dispensacao-grid gap-4 mb-4">
                
                <!-- Coluna 1: Medicamento -->
                <section id="step1" class="glass-card space-y-3">
                    <div class="flex items-center gap-2 pb-2 border-b border-white/40">
                        <span class="flex items-center justify-center w-7 h-7 rounded-full bg-primary-600 text-white font-bold text-xs">1</span>
                        <h2 class="text-base font-bold text-slate-900">Medicamento</h2>
                    </div>

                    <div class="space-y-2">
                        <div class="relative">
                            <input 
                                type="text" 
                                id="medicamentoSearch" 
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 pl-9 text-sm text-slate-700 focus:border-primary-500 focus:ring-2 focus:ring-primary-200" 
                                placeholder="Buscar medicamento..."
                                autocomplete="off"
                                autofocus>
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 0 7.5 15a7.5 7.5 0 0 0 9.15 1.65z"/></svg>
                            <div id="medicamentoLoader" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-3.5 w-3.5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Resultados da busca -->
                        <div id="medicamentoResults" class="hidden rounded-lg border border-slate-200 bg-white shadow-md overflow-hidden max-h-48 overflow-y-auto"></div>

                        <!-- Medicamento selecionado -->
                        <div id="medicamentoSelecionado" class="hidden rounded-lg border-2 border-emerald-300 bg-emerald-50 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-bold text-slate-900 truncate" id="medNome"></h3>
                                    <p class="text-xs text-slate-600 mt-0.5" id="medApresentacao"></p>
                                    <p class="text-xs font-bold text-emerald-600 mt-1">Estoque: <span id="medEstoque"></span></p>
                                </div>
                                <button onclick="limparMedicamento()" class="text-slate-400 hover:text-rose-600 transition flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Coluna 2: Quantidade -->
                <section id="step2" class="glass-card space-y-3 opacity-50 pointer-events-none transition-all">
                    <div class="flex items-center gap-2 pb-2 border-b border-white/40">
                        <span class="flex items-center justify-center w-7 h-7 rounded-full bg-slate-300 text-white font-bold text-xs">2</span>
                        <h2 class="text-base font-bold text-slate-900">Quantidade</h2>
                    </div>

                    <div class="space-y-2">
                        <div class="grid grid-cols-5 gap-1.5 items-center">
                            <button type="button" onclick="alterarQuantidade(-10)" class="qty-btn-compact bg-slate-200 text-slate-700 hover:bg-slate-300">
                                -10
                            </button>
                            <button type="button" onclick="alterarQuantidade(-1)" class="qty-btn-compact bg-slate-200 text-slate-700 hover:bg-slate-300">
                                -1
                            </button>
                            <input 
                                type="number" 
                                id="quantidade" 
                                value="1" 
                                min="1" 
                                class="w-full h-14 text-center text-2xl font-bold rounded-lg border-2 border-primary-400 bg-white text-primary-600 focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
                                onchange="validarQuantidade()">
                            <button type="button" onclick="alterarQuantidade(1)" class="qty-btn-compact bg-primary-600 text-white hover:bg-primary-500">
                                +1
                            </button>
                            <button type="button" onclick="alterarQuantidade(10)" class="qty-btn-compact bg-primary-600 text-white hover:bg-primary-500">
                                +10
                            </button>
                        </div>
                        <p class="text-xs text-center text-slate-500">Máx: <span id="maxQuantidade" class="font-bold text-primary-600">0</span></p>
                    </div>
                </section>

                <!-- Coluna 3: Paciente -->
                <section id="step3" class="glass-card space-y-3 opacity-50 pointer-events-none transition-all">
                    <div class="flex items-center gap-2 pb-2 border-b border-white/40">
                        <span class="flex items-center justify-center w-7 h-7 rounded-full bg-slate-300 text-white font-bold text-xs">3</span>
                        <h2 class="text-base font-bold text-slate-900">Paciente</h2>
                    </div>

                    <div class="space-y-2">
                        <div class="relative">
                            <input 
                                type="text" 
                                id="pacienteSearch" 
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 pl-9 text-sm text-slate-700 focus:border-primary-500 focus:ring-2 focus:ring-primary-200" 
                                placeholder="Buscar paciente..."
                                autocomplete="off">
                            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                            <div id="pacienteLoader" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-3.5 w-3.5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                        <button type="button" onclick="mostrarModalCadastroPaciente()" class="text-xs text-primary-600 hover:text-primary-700 font-medium">
                            + Novo paciente
                        </button>

                        <!-- Resultados da busca -->
                        <div id="pacienteResults" class="hidden rounded-lg border border-slate-200 bg-white shadow-md overflow-hidden max-h-48 overflow-y-auto"></div>

                        <!-- Paciente selecionado -->
                        <div id="pacienteSelecionado" class="hidden rounded-lg border-2 border-emerald-300 bg-emerald-50 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-bold text-slate-900 truncate" id="pacNome"></h3>
                                    <p class="text-xs text-slate-600 mt-0.5">CPF: <span id="pacCpf"></span></p>
                                </div>
                                <button onclick="limparPaciente()" class="text-slate-400 hover:text-rose-600 transition flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <!-- Receitas ativas do paciente -->
                            <div id="receitasContainer" class="hidden border-t border-emerald-200 pt-4 mt-4">
                                <h4 class="text-sm font-semibold text-slate-700 mb-3">Receitas ativas para este medicamento:</h4>
                                <div id="receitasList" class="space-y-2"></div>
                            </div>

                            <!-- Opção de saída avulsa -->
                            <div class="border-t border-emerald-200 pt-4 mt-4">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="tipoSaida" value="avulsa" id="saidaAvulsa" class="w-5 h-5 text-primary-600 focus:ring-primary-500">
                                    <span class="text-sm font-medium text-slate-700">Saída avulsa (sem receita)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Footer fixo com observações e ações -->
            <div class="glass-card p-2 space-y-2">
                <div>
                    <label for="observacoes" class="block text-xs font-medium text-slate-700 mb-1">Observações (opcional)</label>
                    <textarea 
                        id="observacoes" 
                        rows="1" 
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-primary-500 focus:ring-2 focus:ring-primary-200 resize-none"
                        placeholder="Adicione observações sobre esta dispensação..."></textarea>
                </div>

                <div class="flex gap-2">
                    <button type="button" onclick="limparTudo()" class="flex-1 rounded-lg border-2 border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                        Limpar Tudo
                    </button>
                    <button type="submit" id="btnConfirmar" disabled class="flex-1 rounded-lg bg-gradient-to-r from-emerald-600 to-emerald-500 px-4 py-2 text-sm font-bold text-white shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                        Confirmar Dispensação
                    </button>
                </div>
            </div>
            
            </form>
        </main>
    </div>

    <!-- Modal de Confirmação -->
    <div id="modalConfirmacao" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="glass-card max-w-lg w-full p-8 space-y-6 animate-[scale-in_0.2s_ease-out]">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-emerald-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h3 class="text-2xl font-bold text-slate-900">Dispensação Registrada!</h3>
            </div>
            
            <div class="space-y-3 bg-slate-50 rounded-xl p-4">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600">Medicamento:</span>
                    <span class="font-semibold text-slate-900" id="modalMedicamento"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600">Quantidade:</span>
                    <span class="font-semibold text-emerald-600 text-lg" id="modalQuantidade"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600">Paciente:</span>
                    <span class="font-semibold text-slate-900" id="modalPaciente"></span>
                </div>
            </div>
            
            <button onclick="fecharModal()" class="w-full rounded-xl bg-primary-600 px-6 py-3 text-white font-bold hover:bg-primary-500 transition">
                Fechar e Nova Dispensação
            </button>
        </div>
    </div>

    <script src="js/sidebar.js"></script>
    <script src="js/dispensacao_v2.js"></script>
    
    <style>
        @keyframes scale-in {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
</body>
</html>
