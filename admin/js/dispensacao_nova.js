// Estado global
let pacienteSelecionado = null;
let medicamentosCarrinho = [];
let searchTimeout = null;
let limiteDispensacoes = 12; // Limite inicial
const API_BASE = (typeof window !== 'undefined' && window.DISPENSACAO_API_BASE) ? window.DISPENSACAO_API_BASE : 'api/';

// Obter o caminho base do diretório admin
function getBasePath() {
    const path = window.location.pathname;
    // Se estiver em /admin/index.php, retorna /admin
    // Se estiver em /farmacia/admin/index.php, retorna /farmacia/admin
    const match = path.match(/^(.+\/)?admin\//);
    if (match) {
        return match[0].replace(/\/$/, '');
    }
    // Fallback: usar o caminho atual sem o nome do arquivo
    return path.substring(0, path.lastIndexOf('/'));
}

// ========================================
// FUNÇÃO DE ALERTA CUSTOMIZADA
// ========================================
function mostrarAlerta(mensagem, tipo = 'info', titulo = null) {
    const modal = document.getElementById('modalAlerta');
    const iconDiv = document.getElementById('alertaIcon');
    const tituloEl = document.getElementById('alertaTitulo');
    const mensagemEl = document.getElementById('alertaMensagem');
    
    // Configurar tipo de alerta
    const tipos = {
        'info': {
            bg: 'from-blue-500 to-indigo-500',
            icon: 'ℹ',
            titulo: 'Informação'
        },
        'erro': {
            bg: 'from-red-500 to-rose-500',
            icon: '✕',
            titulo: 'Erro'
        },
        'aviso': {
            bg: 'from-amber-500 to-orange-500',
            icon: '⚠',
            titulo: 'Atenção'
        },
        'sucesso': {
            bg: 'from-green-500 to-emerald-500',
            icon: '✓',
            titulo: 'Sucesso'
        }
    };
    
    const config = tipos[tipo] || tipos['info'];
    
    // Atualizar conteúdo
    iconDiv.className = `w-16 h-16 bg-gradient-to-br ${config.bg} rounded-full flex items-center justify-center mx-auto mb-4`;
    iconDiv.innerHTML = `<span class="text-white text-3xl">${config.icon}</span>`;
    tituloEl.textContent = titulo || config.titulo;
    mensagemEl.textContent = mensagem;
    
    // Mostrar modal
    modal.classList.remove('hidden');
}

function fecharAlerta() {
    document.getElementById('modalAlerta').classList.add('hidden');
}

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Dispensação iniciada');
    
    // Configurar fechar modal ao clicar fora
    const modalAlerta = document.getElementById('modalAlerta');
    if (modalAlerta) {
        modalAlerta.addEventListener('click', (e) => {
            if (e.target === modalAlerta) {
                fecharAlerta();
            }
        });
    }
    
    document.getElementById('pacienteSearch').addEventListener('input', buscarPacientes);
    document.getElementById('medicamentoSearch').addEventListener('input', buscarMedicamentos);
    
    carregarLog(true); // Resetar para 12 ao carregar a página
    setInterval(() => carregarLog(true), 30000); // Resetar para 12 ao atualizar automaticamente
});

// ========================================
// STEP 1: BUSCAR PACIENTES
// ========================================
function buscarPacientes(e) {
    const query = e.target.value.trim();
    const loader = document.getElementById('pacienteLoader');
    const results = document.getElementById('pacienteResults');
    
    clearTimeout(searchTimeout);
    
    if (query.length < 2) {
        results.classList.add('hidden');
        loader.classList.add('hidden');
        return;
    }
    
    loader.classList.remove('hidden');
    
    searchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(`${API_BASE}buscar_paciente.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            loader.classList.add('hidden');
            
            if (data.success && data.pacientes.length > 0) {
                mostrarResultadosPacientes(data.pacientes);
            } else {
                results.innerHTML = '<div class="p-4 text-center text-gray-500 text-sm">Nenhum paciente encontrado</div>';
                results.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Erro:', error);
            loader.classList.add('hidden');
        }
    }, 300);
}

function mostrarResultadosPacientes(pacientes) {
    const results = document.getElementById('pacienteResults');
    
    results.innerHTML = pacientes.map(p => `
        <div 
            onclick='selecionarPaciente(${JSON.stringify(p).replace(/'/g, "&apos;")})' 
            class="p-3 hover:bg-purple-50 cursor-pointer border-b border-gray-100 last:border-0 transition-colors"
        >
            <p class="font-semibold text-gray-800 text-sm">${p.nome}</p>
            <p class="text-xs text-gray-600 mt-1">
                ${p.cpf ? `CPF: ${p.cpf}` : ''} 
                ${p.cartao_sus ? `| SUS: ${p.cartao_sus}` : ''}
            </p>
        </div>
    `).join('');
    
    results.classList.remove('hidden');
}

async function selecionarPaciente(paciente) {
    pacienteSelecionado = paciente;
    
    document.getElementById('pacienteSearch').value = '';
    document.getElementById('pacienteResults').classList.add('hidden');
    
    const selecionado = document.getElementById('pacienteSelecionado');
    document.getElementById('pacienteAvatar').textContent = paciente.nome.substring(0, 2).toUpperCase();
    document.getElementById('pacienteNome').textContent = paciente.nome;
    document.getElementById('pacienteInfo').textContent = `${paciente.cpf || 'CPF não informado'}`;
    selecionado.classList.remove('hidden');
    
    // Atualizar link do histórico
    const btnHistorico = document.getElementById('btnHistoricoPaciente');
    if (btnHistorico && paciente.id) {
        const basePath = getBasePath();
        btnHistorico.href = `${basePath}/paciente_historico.php?id=${paciente.id}`;
    }
    
    document.getElementById('stepMedicamentos').classList.remove('hidden');
    document.getElementById('medicamentoSearch').focus();
    
    // Carregar histórico de dispensações
    await carregarHistoricoDispensacoes(paciente.id);
    
    console.log('✅ Paciente selecionado:', paciente);
}

function abrirHistoricoPaciente() {
    if (pacienteSelecionado && pacienteSelecionado.id) {
        const basePath = getBasePath();
        window.location.href = `${basePath}/paciente_historico.php?id=${pacienteSelecionado.id}`;
    } else {
        console.error('Erro: Paciente não selecionado ou ID inválido');
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: 'Nenhum paciente selecionado para visualizar o histórico.'
        });
    }
}

function removerPaciente() {
    pacienteSelecionado = null;
    document.getElementById('pacienteSelecionado').classList.add('hidden');
    document.getElementById('stepMedicamentos').classList.add('hidden');
    document.getElementById('stepFinalizar').classList.add('hidden');
    document.getElementById('historicoLista').innerHTML = '<div class="text-center py-2 text-gray-400 text-xs">Carregando...</div>';
    medicamentosCarrinho = [];
    document.getElementById('medicamentosAdicionados').innerHTML = '';
    document.getElementById('pacienteSearch').focus();
}

// ========================================
// STEP 2: BUSCAR E ADICIONAR MEDICAMENTOS
// ========================================
function buscarMedicamentos(e) {
    const query = e.target.value.trim();
    const loader = document.getElementById('medicamentoLoader');
    const results = document.getElementById('medicamentoResults');
    
    clearTimeout(searchTimeout);
    
    // Permitir busca com 1 caractere ou mais (para códigos de barras e nomes)
    if (query.length < 1) {
        results.classList.add('hidden');
        loader.classList.add('hidden');
        return;
    }
    
    loader.classList.remove('hidden');
    
    searchTimeout = setTimeout(async () => {
        try {
            console.log('🔍 Buscando medicamentos:', query);
            const response = await fetch(`${API_BASE}buscar_medicamento.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            console.log('📦 Resposta da API:', data);
            
            loader.classList.add('hidden');
            
            if (data.success && data.medicamentos && data.medicamentos.length > 0) {
                console.log('✅ Medicamentos encontrados:', data.medicamentos.length);
                mostrarResultadosMedicamentos(data.medicamentos);
            } else {
                console.log('⚠️ Nenhum medicamento encontrado');
                results.innerHTML = '<div class="p-4 text-center text-gray-500 text-sm">Nenhum medicamento encontrado</div>';
                results.classList.remove('hidden');
            }
        } catch (error) {
            console.error('❌ Erro ao buscar medicamentos:', error);
            loader.classList.add('hidden');
            results.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Erro ao buscar medicamentos</div>';
            results.classList.remove('hidden');
        }
    }, 300);
}

function mostrarResultadosMedicamentos(medicamentos) {
    const results = document.getElementById('medicamentoResults');
    
    results.innerHTML = medicamentos.map(m => `
        <div 
            onclick='adicionarMedicamento(${JSON.stringify(m).replace(/'/g, "&apos;")})' 
            class="p-3 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-0 transition-colors"
        >
            <p class="font-semibold text-gray-800 text-sm">${m.nome}</p>
            <div class="text-xs text-gray-500 mt-1">
                ${m.codigos_barras ? `<span class="font-mono">Código: ${m.codigos_barras}</span><br>` : ''}
            </div>
            <p class="text-sm text-gray-700 mt-1">
                Estoque: <span class="font-semibold ${m.estoque_total > 50 ? 'text-green-600' : m.estoque_total > 10 ? 'text-amber-600' : 'text-red-600'}">${m.estoque_total || 0}</span>
            </p>
        </div>
    `).join('');
    
    results.classList.remove('hidden');
}

async function adicionarMedicamento(medicamento) {
    // Não precisa verificar aqui, pois vamos verificar depois quando tiver o lote selecionado
    
    try {
        // Se foi encontrado por código de barras, filtrar lotes apenas desse código
        let url = `${API_BASE}buscar_lotes.php?medicamento_id=${medicamento.id}`;
        if (medicamento.codigo_barras_id_match && medicamento.codigo_barras_id_match > 0) {
            url += `&codigo_barras_id=${medicamento.codigo_barras_id_match}`;
            console.log('🔍 Buscando lotes filtrados por código de barras:', medicamento.codigo_barras_id_match);
        } else {
            console.log('🔍 Buscando todos os lotes do medicamento');
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (!data.success || data.lotes.length === 0) {
            mostrarAlerta('Nenhum lote disponível para este medicamento!', 'aviso');
            return;
        }
        
        // Se foi encontrado por código de barras, selecionar automaticamente o primeiro lote
        // Se foi encontrado por nome, deixar o usuário escolher
        const foiPorCodigoBarras = medicamento.codigo_barras_id_match && medicamento.codigo_barras_id_match > 0;
        const loteSelecionado = foiPorCodigoBarras ? data.lotes[0] : (data.lotes.length === 1 ? data.lotes[0] : null);
        
        // Se já tem lote selecionado, verificar se já existe o mesmo medicamento com o mesmo lote
        if (loteSelecionado) {
            const jaExiste = medicamentosCarrinho.find(m => 
                m.id === medicamento.id && 
                m.lote_selecionado && 
                m.lote_selecionado.id === loteSelecionado.id
            );
            
            if (jaExiste) {
                mostrarAlerta('Este medicamento com este lote já foi adicionado! Você pode adicionar o mesmo medicamento de outro lote.', 'aviso');
                return;
            }
        }
        
        const item = {
            ...medicamento,
            lotes: data.lotes,
            lote_selecionado: loteSelecionado,
            quantidade: 1,
            foiPorCodigoBarras: foiPorCodigoBarras // Flag para indicar que foi buscado por código
        };
        
        medicamentosCarrinho.push(item);
        
        document.getElementById('medicamentoSearch').value = '';
        document.getElementById('medicamentoResults').classList.add('hidden');
        
        renderizarCarrinho();
        document.getElementById('btnFinalizarContainer').classList.remove('hidden');
        
        console.log('✅ Medicamento adicionado:', item);
        
    } catch (error) {
        console.error('Erro:', error);
        mostrarAlerta('Erro ao buscar lotes do medicamento', 'erro');
    }
}

function renderizarCarrinho() {
    const container = document.getElementById('medicamentosAdicionados');
    
    if (medicamentosCarrinho.length === 0) {
        container.innerHTML = '';
        document.getElementById('btnFinalizarContainer').classList.add('hidden');
        return;
    }
    
    container.innerHTML = medicamentosCarrinho.map((item, index) => `
        <div class="p-4 bg-white rounded-lg border-2 border-blue-200 hover:border-blue-300 transition-all shadow-sm">
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                    <p class="font-bold text-gray-800 text-base">${item.nome}</p>
                    <p class="text-xs text-gray-600 mt-1">${item.descricao || item.apresentacao || ''}</p>
                </div>
                <button 
                    onclick="removerMedicamento(${index})" 
                    class="w-8 h-8 flex items-center justify-center bg-red-100 hover:bg-red-500 text-red-600 hover:text-white rounded-lg text-xl font-bold transition-all ml-2"
                >×</button>
            </div>
            
            <div class="mb-3">
                <label class="text-xs font-bold text-gray-700 block mb-1.5">📦 Lote:</label>
                ${item.lote_selecionado && (item.lotes.length === 1 || item.foiPorCodigoBarras) ? `
                    <div class="text-sm text-gray-700 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200">
                        <span class="font-semibold">${item.lote_selecionado.numero_lote}</span> - Val: ${formatarData(item.lote_selecionado.data_validade)} - Disp: <span class="font-semibold text-green-600">${item.lote_selecionado.quantidade_atual}</span>
                    </div>
                ` : `
                    <select 
                        onchange="selecionarLote(${index}, this.value)"
                        class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">Selecione um lote</option>
                        ${item.lotes.map(lote => `
                            <option value="${lote.id}" ${item.lote_selecionado?.id === lote.id ? 'selected' : ''}>
                                ${lote.numero_lote} - Val: ${formatarData(lote.data_validade)} - Disp: ${lote.quantidade_atual}
                            </option>
                        `).join('')}
                    </select>
                `}
            </div>
            
            <div>
                <label class="text-xs font-bold text-gray-700 block mb-1.5">🔢 Quantidade:</label>
                <div class="flex items-center justify-center gap-2">
                    <button 
                        onclick="alterarQuantidade(${index}, -10)" 
                        class="w-12 h-12 bg-gradient-to-br from-red-600 to-rose-700 hover:from-red-700 hover:to-rose-800 text-white rounded-xl font-bold transition-all shadow-md hover:shadow-lg text-lg flex items-center justify-center"
                        style="margin-left: -10px;"
                        title="Diminuir 10"
                    >-10</button>
                    
                    <button 
                        onclick="alterarQuantidade(${index}, -1)" 
                        class="w-12 h-12 bg-gradient-to-br from-red-500 to-rose-600 hover:from-red-600 hover:to-rose-700 text-white rounded-xl font-bold transition-all shadow-md hover:shadow-lg text-2xl flex items-center justify-center"
                        title="Diminuir 1"
                    >−</button>
                    
                    <input 
                        type="number" 
                        value="${item.quantidade}" 
                        min="1" 
                        max="${item.lote_selecionado ? item.lote_selecionado.quantidade_atual : 999}"
                        onchange="alterarQuantidade(${index}, 0, this.value)"
                        class="w-20 text-center px-3 py-2 border-2 border-gray-300 rounded-lg font-bold text-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                    
                    <button 
                        onclick="alterarQuantidade(${index}, 1)" 
                        class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl font-bold transition-all shadow-md hover:shadow-lg text-2xl flex items-center justify-center"
                        title="Aumentar 1"
                    >+</button>
                    
                    <button 
                        onclick="alterarQuantidade(${index}, 10)" 
                        class="w-12 h-12 bg-gradient-to-br from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 text-white rounded-xl font-bold transition-all shadow-md hover:shadow-lg text-lg flex items-center justify-center"
                        style="margin-right: 10px;"
                        title="Aumentar 10"
                    >+10</button>
                </div>
            </div>
        </div>
    `).join('');
}

function selecionarLote(index, loteId) {
    const item = medicamentosCarrinho[index];
    const novoLote = item.lotes.find(l => l.id == loteId);
    
    if (!novoLote) {
        item.lote_selecionado = null;
        renderizarCarrinho();
        return;
    }
    
    // Verificar se já existe o mesmo medicamento com este lote
    const jaExiste = medicamentosCarrinho.find((m, i) => 
        i !== index &&
        m.id === item.id && 
        m.lote_selecionado && 
        m.lote_selecionado.id === novoLote.id
    );
    
    if (jaExiste) {
        mostrarAlerta('Este medicamento com este lote já foi adicionado! Você pode adicionar o mesmo medicamento de outro lote.', 'aviso');
        // Reverter para o lote anterior ou null
        renderizarCarrinho();
        return;
    }
    
    item.lote_selecionado = novoLote;
    
    // Ajustar quantidade se necessário
    if (item.quantidade > item.lote_selecionado.quantidade_atual) {
        item.quantidade = item.lote_selecionado.quantidade_atual;
    }
    
    renderizarCarrinho();
}

function alterarQuantidade(index, delta, valor = null) {
    const item = medicamentosCarrinho[index];
    
    if (!item.lote_selecionado) {
        mostrarAlerta('Selecione um lote primeiro!', 'aviso');
        return;
    }
    
    const max = item.lote_selecionado.quantidade_atual;
    
    if (valor !== null) {
        // Quando digita manualmente
        const novoValor = parseInt(valor) || 1;
        item.quantidade = Math.max(1, Math.min(novoValor, max));
    } else {
        // Quando clica nos botões
        // delta pode ser: -10, -1, 1, ou 10
        const novaQuantidade = item.quantidade + delta;
        item.quantidade = Math.max(1, Math.min(novaQuantidade, max));
    }
    
    renderizarCarrinho();
}

function removerMedicamento(index) {
    medicamentosCarrinho.splice(index, 1);
    renderizarCarrinho();
}

// ========================================
// STEP 3: FINALIZAR DISPENSAÇÃO
// ========================================
async function finalizarDispensacao() {
    console.log('🔄 Iniciando finalização...');
    console.log('Paciente:', pacienteSelecionado);
    console.log('Medicamentos:', medicamentosCarrinho);
    
    if (!pacienteSelecionado) {
        mostrarAlerta('Selecione um paciente!', 'aviso');
        return;
    }
    
    if (medicamentosCarrinho.length === 0) {
        mostrarAlerta('Adicione pelo menos um medicamento!', 'aviso');
        return;
    }
    
    for (let item of medicamentosCarrinho) {
        if (!item.lote_selecionado) {
            mostrarAlerta(`Selecione um lote para ${item.nome}!`, 'aviso');
            return;
        }
    }
    
    // Solicitar senha do funcionário usando SweetAlert2
    const { value: senhaFuncionario } = await Swal.fire({
        title: 'Senha do Funcionário',
        text: 'Digite a senha numérica do funcionário responsável pela dispensação:',
        input: 'password',
        inputPlaceholder: 'Digite a senha (apenas números)',
        inputAttributes: {
            maxlength: 20,
            pattern: '[0-9]*',
            inputmode: 'numeric',
            autocomplete: 'off'
        },
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            if (!value) {
                return 'Por favor, digite a senha!';
            }
            if (!/^\d+$/.test(value)) {
                return 'A senha deve conter apenas números!';
            }
        }
    });
    
    if (!senhaFuncionario) {
        return; // Usuário cancelou
    }
    
    // Validar senha do funcionário
    try {
        const validacaoResponse = await fetch(`${API_BASE}validar_senha_funcionario.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ senha: senhaFuncionario })
        });
        
        const validacaoResult = await validacaoResponse.json();
        
        if (!validacaoResult.success || !validacaoResult.funcionario) {
            Swal.fire({
                icon: 'error',
                title: 'Senha Inválida',
                text: validacaoResult.message || 'Senha incorreta ou funcionário inativo'
            });
            return;
        }
        
        const funcionario = validacaoResult.funcionario;
        
        const observacoes = document.getElementById('observacoes').value;
        
        const dados = {
            paciente_id: pacienteSelecionado.id,
            funcionario_id: funcionario.id,
            medicamentos: medicamentosCarrinho.map(item => ({
                medicamento_id: item.id,
                lote_id: item.lote_selecionado.id,
                quantidade: item.quantidade
            })),
            observacoes: observacoes
        };
        
        console.log('📤 Enviando dados:', dados);
        
        const response = await fetch(`${API_BASE}processar_dispensacao.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        });
        
        const result = await response.json();
        console.log('📥 Resposta:', result);
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: result.message || 'Dispensação registrada com sucesso!',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                mostrarSucesso(result.message || 'Dispensação registrada com sucesso!');
                // Recarregar histórico se paciente ainda estiver selecionado
                if (pacienteSelecionado) {
                    carregarHistoricoDispensacoes(pacienteSelecionado.id);
                }
            limparTudo();
            carregarLog(true); // Resetar para 12 após dispensação
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: result.message || 'Erro ao processar dispensação'
            });
        }
        
    } catch (error) {
        console.error('❌ Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: 'Erro ao processar dispensação: ' + error.message
        });
    }
}

function mostrarSucesso(mensagem) {
    document.getElementById('modalMensagem').textContent = mensagem;
    document.getElementById('modalSucesso').classList.remove('hidden');
}

function fecharModal() {
    document.getElementById('modalSucesso').classList.add('hidden');
}

function limparTudo() {
    pacienteSelecionado = null;
    medicamentosCarrinho = [];
    
    document.getElementById('pacienteSearch').value = '';
    document.getElementById('medicamentoSearch').value = '';
    document.getElementById('observacoes').value = '';
    
    document.getElementById('pacienteSelecionado').classList.add('hidden');
    document.getElementById('stepMedicamentos').classList.add('hidden');
    document.getElementById('btnFinalizarContainer').classList.add('hidden');
    document.getElementById('medicamentosAdicionados').innerHTML = '';
    
    document.getElementById('pacienteSearch').focus();
}

// ========================================
// LOG DE DISPENSAÇÕES
// ========================================
async function carregarLog(resetar = false) {
    if (resetar) {
        limiteDispensacoes = 12; // Resetar para 12 ao recarregar a página
    }
    
    console.log('🔄 Carregando log... limite:', limiteDispensacoes);
    try {
        const response = await fetch(`${API_BASE}log_dispensacoes.php?limit=${limiteDispensacoes}`);
        console.log('📥 Response status:', response.status);
        
        const data = await response.json();
        console.log('📊 Dados recebidos:', data);
        
        const container = document.getElementById('logDispensacoes');
        const btnCarregarMais = document.getElementById('btnCarregarMais');
        
        if (data.success && data.dispensacoes && data.dispensacoes.length > 0) {
            console.log('✅ Renderizando', data.dispensacoes.length, 'dispensações');
            container.innerHTML = data.dispensacoes.map(d => `
                <div class="p-4 bg-gradient-to-br from-blue-50 to-cyan-50 rounded-lg border-2 border-blue-200 hover:border-blue-300 shadow-md hover:shadow-lg transition-all">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1">
                            <p class="font-bold text-gray-800 text-sm">${d.paciente_nome}</p>
                            <p class="text-sm text-gray-700 font-medium">${d.medicamento_nome} - <span class="font-bold text-blue-600">${d.quantidade}un</span></p>
                        </div>
                        <span class="text-xs font-semibold text-gray-600 bg-white px-2 py-1 rounded">${formatarDataHora(d.data_dispensacao)}</span>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-blue-200">
                        <p class="text-xs text-gray-600">
                            <span class="font-semibold">Lote:</span> ${d.lote_numero}
                        </p>
                        <p class="text-xs text-gray-600">
                            <span class="font-semibold">👤</span> ${d.responsavel_nome || d.usuario_nome || 'Sistema'}
                        </p>
                    </div>
                </div>
            `).join('');
            
            // Mostrar botão "Carregar Mais" se houver exatamente o limite de dispensações
            // (isso indica que provavelmente há mais para carregar)
            if (btnCarregarMais) {
                if (data.dispensacoes.length === limiteDispensacoes) {
                    btnCarregarMais.classList.remove('hidden');
                } else {
                    btnCarregarMais.classList.add('hidden');
                }
            }
        } else {
            console.log('⚠️ Sem dispensações ou erro:', data);
            const mensagem = data.message || 'Nenhuma dispensação registrada ainda';
            container.innerHTML = `<div class="col-span-full text-center py-8 text-gray-400 text-sm">${mensagem}</div>`;
            if (btnCarregarMais) {
                btnCarregarMais.classList.add('hidden');
            }
        }
    } catch (error) {
        console.error('❌ Erro ao carregar log:', error);
        const container = document.getElementById('logDispensacoes');
        container.innerHTML = '<div class="col-span-full text-center py-8 text-red-400 text-sm">Erro ao carregar dispensações</div>';
        const btnCarregarMais = document.getElementById('btnCarregarMais');
        if (btnCarregarMais) {
            btnCarregarMais.classList.add('hidden');
        }
    }
}

async function carregarMaisDispensacoes() {
    limiteDispensacoes += 12; // Adicionar mais 12
    await carregarLog(false); // Não resetar o limite
}

function formatarData(data) {
    if (!data) return '-';
    const d = new Date(data + 'T00:00:00');
    return d.toLocaleDateString('pt-BR');
}

function formatarDataHora(data) {
    if (!data) return '-';
    const d = new Date(data);
    return d.toLocaleString('pt-BR', { 
        day: '2-digit', 
        month: '2-digit', 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

// ========================================
// HISTÓRICO DE DISPENSAÇÕES DO PACIENTE
// ========================================
async function carregarHistoricoDispensacoes(pacienteId) {
    const container = document.getElementById('historicoLista');
    
    if (!container) {
        console.error('❌ Container historicoLista não encontrado');
        return;
    }
    
    if (!pacienteId) {
        console.error('❌ pacienteId não informado');
        container.innerHTML = '<div class="text-center py-2 text-red-400 text-xs">Erro: ID do paciente não informado</div>';
        return;
    }
    
    console.log('🔄 Carregando histórico para paciente_id:', pacienteId);
    
    try {
        // Buscar dispensações e medicamentos pendentes em paralelo
        const [dispensacoesResponse, pendentesResponse] = await Promise.all([
            fetch(`${API_BASE}buscar_dispensacoes_paciente.php?paciente_id=${pacienteId}`),
            fetch(`${API_BASE}buscar_medicamentos_pendentes_paciente.php?paciente_id=${pacienteId}`)
        ]);
        
        const dispensacoesData = await dispensacoesResponse.json();
        const pendentesData = await pendentesResponse.json();
        
        console.log('📊 Dados recebidos - Dispensações:', dispensacoesData);
        console.log('📊 Dados recebidos - Pendentes:', pendentesData);
        
        // Combinar pendentes (laranja) + dispensações (verde) até ter 5 itens
        const itens = [];
        
        // Adicionar pendentes primeiro (máximo 5)
        if (pendentesData.success && pendentesData.pendentes && pendentesData.pendentes.length > 0) {
            const pendentesLimitados = pendentesData.pendentes.slice(0, 5);
            pendentesLimitados.forEach(p => {
                itens.push({
                    tipo: 'pendente',
                    ...p
                });
            });
        }
        
        // Completar com dispensações até ter 5 itens no total
        if (dispensacoesData.success && dispensacoesData.dispensacoes && dispensacoesData.dispensacoes.length > 0) {
            const espacoRestante = 5 - itens.length;
            if (espacoRestante > 0) {
                const dispensacoesLimitadas = dispensacoesData.dispensacoes.slice(0, espacoRestante);
                dispensacoesLimitadas.forEach(d => {
                    itens.push({
                        tipo: 'dispensacao',
                        ...d
                    });
                });
            }
        }
        
        // Renderizar itens
        if (itens.length > 0) {
            console.log('✅ Renderizando', itens.length, 'itens');
            container.innerHTML = itens.map(item => {
                if (item.tipo === 'pendente') {
                    // Card laranja para medicamentos pendentes de receita
                    const basePath = getBasePath();
                    return `
                        <div class="p-2.5 bg-gradient-to-r from-orange-50 to-amber-50 rounded-lg border-2 border-orange-200 hover:border-orange-300 transition-colors">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-800 truncate">${item.medicamento_nome}</p>
                                    <p class="text-xs text-gray-600 mt-0.5">Receita #${item.receita_id}</p>
                                </div>
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold bg-orange-100 text-orange-700">${item.quantidade_pendente}un</span>
                                    <a 
                                        href="${basePath}/receitas_dispensar.php?id=${item.receita_id}" 
                                        class="inline-flex items-center justify-center px-2 py-1 bg-gradient-to-r from-purple-500 to-indigo-500 hover:from-purple-600 hover:to-indigo-600 text-white rounded-lg text-xs font-medium transition-all shadow-sm hover:shadow"
                                        title="Ir para dispensação da receita"
                                    >
                                        Dispensar
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    // Card verde para dispensações já realizadas
                    return `
                        <div class="p-2.5 bg-white rounded-lg border border-emerald-100 hover:border-emerald-300 transition-colors">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold text-gray-800 flex-1 min-w-0 truncate">${item.medicamento_nome}</p>
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold bg-emerald-100 text-emerald-700">${item.quantidade}un</span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700">${formatarDataHora(item.data_dispensacao)}</span>
                                </div>
                            </div>
                        </div>
                    `;
                }
            }).join('');
        } else {
            console.log('⚠️ Nenhum item encontrado');
            container.innerHTML = `<div class="text-center py-2 text-gray-400 text-xs">Nenhuma dispensação ou receita pendente</div>`;
        }
    } catch (error) {
        console.error('❌ Erro ao carregar histórico:', error);
        container.innerHTML = `<div class="text-center py-2 text-red-400 text-xs">Erro ao carregar histórico: ${error.message}</div>`;
    }
}
