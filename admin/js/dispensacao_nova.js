// Estado global
let pacienteSelecionado = null;
let medicamentosCarrinho = [];
let searchTimeout = null;
const API_BASE = (typeof window !== 'undefined' && window.DISPENSACAO_API_BASE) ? window.DISPENSACAO_API_BASE : 'api/';

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Dispensação iniciada');
    
    document.getElementById('pacienteSearch').addEventListener('input', buscarPacientes);
    document.getElementById('medicamentoSearch').addEventListener('input', buscarMedicamentos);
    
    carregarLog();
    setInterval(carregarLog, 30000);
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

function selecionarPaciente(paciente) {
    pacienteSelecionado = paciente;
    
    document.getElementById('pacienteSearch').value = '';
    document.getElementById('pacienteResults').classList.add('hidden');
    
    const selecionado = document.getElementById('pacienteSelecionado');
    document.getElementById('pacienteAvatar').textContent = paciente.nome.substring(0, 2).toUpperCase();
    document.getElementById('pacienteNome').textContent = paciente.nome;
    document.getElementById('pacienteInfo').textContent = `${paciente.cpf || 'CPF não informado'}`;
    selecionado.classList.remove('hidden');
    
    document.getElementById('stepMedicamentos').classList.remove('hidden');
    document.getElementById('medicamentoSearch').focus();
    
    console.log('✅ Paciente selecionado:', paciente);
}

function removerPaciente() {
    pacienteSelecionado = null;
    document.getElementById('pacienteSelecionado').classList.add('hidden');
    document.getElementById('stepMedicamentos').classList.add('hidden');
    document.getElementById('stepFinalizar').classList.add('hidden');
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
    
    if (query.length < 2) {
        results.classList.add('hidden');
        loader.classList.add('hidden');
        return;
    }
    
    loader.classList.remove('hidden');
    
    searchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(`${API_BASE}buscar_medicamento.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            loader.classList.add('hidden');
            
            if (data.success && data.medicamentos.length > 0) {
                mostrarResultadosMedicamentos(data.medicamentos);
            } else {
                results.innerHTML = '<div class="p-4 text-center text-gray-500 text-sm">Nenhum medicamento encontrado</div>';
                results.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Erro:', error);
            loader.classList.add('hidden');
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
            <p class="text-sm text-gray-700 mt-1">
                ${m.fabricante || 'Fabricante não informado'} | Estoque: <span class="font-semibold ${m.estoque_total > 50 ? 'text-green-600' : m.estoque_total > 10 ? 'text-amber-600' : 'text-red-600'}">${m.estoque_total || 0}</span>
            </p>
        </div>
    `).join('');
    
    results.classList.remove('hidden');
}

async function adicionarMedicamento(medicamento) {
    if (medicamentosCarrinho.find(m => m.id === medicamento.id)) {
        alert('Este medicamento já foi adicionado!');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}buscar_lotes.php?medicamento_id=${medicamento.id}`);
        const data = await response.json();
        
        if (!data.success || data.lotes.length === 0) {
            alert('Nenhum lote disponível para este medicamento!');
            return;
        }
        
        const item = {
            ...medicamento,
            lotes: data.lotes,
            lote_selecionado: data.lotes.length === 1 ? data.lotes[0] : null,
            quantidade: 1
        };
        
        medicamentosCarrinho.push(item);
        
        document.getElementById('medicamentoSearch').value = '';
        document.getElementById('medicamentoResults').classList.add('hidden');
        
        renderizarCarrinho();
        document.getElementById('btnFinalizarContainer').classList.remove('hidden');
        
        console.log('✅ Medicamento adicionado:', item);
        
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao buscar lotes do medicamento');
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
                ${item.lotes.length === 1 ? `
                    <div class="text-sm text-gray-700 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200">
                        <span class="font-semibold">${item.lotes[0].numero_lote}</span> - Val: ${formatarData(item.lotes[0].data_validade)} - Disp: <span class="font-semibold text-green-600">${item.lotes[0].quantidade_atual}</span>
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
                <div class="flex items-center gap-2">
                    <button 
                        onclick="alterarQuantidade(${index}, -1)" 
                        class="w-12 h-12 bg-gradient-to-br from-red-500 to-rose-600 hover:from-red-600 hover:to-rose-700 text-white rounded-xl font-bold transition-all shadow-md hover:shadow-lg text-2xl flex items-center justify-center"
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
                    >+</button>
                    
                    <span class="text-sm text-gray-600 ml-2">
                        Max: <span class="font-bold">${item.lote_selecionado ? item.lote_selecionado.quantidade_atual : '-'}</span>
                    </span>
                </div>
            </div>
        </div>
    `).join('');
}

function selecionarLote(index, loteId) {
    const item = medicamentosCarrinho[index];
    item.lote_selecionado = item.lotes.find(l => l.id == loteId);
    
    if (item.lote_selecionado && item.quantidade > item.lote_selecionado.quantidade_atual) {
        item.quantidade = item.lote_selecionado.quantidade_atual;
    }
    
    renderizarCarrinho();
}

function alterarQuantidade(index, delta, valor = null) {
    const item = medicamentosCarrinho[index];
    const max = item.lote_selecionado ? item.lote_selecionado.quantidade_atual : 999;
    
    if (valor !== null) {
        item.quantidade = Math.max(1, Math.min(parseInt(valor) || 1, max));
    } else {
        item.quantidade = Math.max(1, Math.min(item.quantidade + delta, max));
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
        alert('Selecione um paciente!');
        return;
    }
    
    if (medicamentosCarrinho.length === 0) {
        alert('Adicione pelo menos um medicamento!');
        return;
    }
    
    for (let item of medicamentosCarrinho) {
        if (!item.lote_selecionado) {
            alert(`Selecione um lote para ${item.nome}!`);
            return;
        }
    }
    
    const observacoes = document.getElementById('observacoes').value;
    
    const dados = {
        paciente_id: pacienteSelecionado.id,
        medicamentos: medicamentosCarrinho.map(item => ({
            medicamento_id: item.id,
            lote_id: item.lote_selecionado.id,
            quantidade: item.quantidade
        })),
        observacoes: observacoes
    };
    
    console.log('📤 Enviando dados:', dados);
    
    try {
        const response = await fetch(`${API_BASE}processar_dispensacao.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        });
        
        const result = await response.json();
        console.log('📥 Resposta:', result);
        
        if (result.success) {
            mostrarSucesso(result.message || 'Dispensação registrada com sucesso!');
            limparTudo();
            carregarLog();
        } else {
            alert(result.message || 'Erro ao processar dispensação');
        }
        
    } catch (error) {
        console.error('❌ Erro:', error);
        alert('Erro ao processar dispensação: ' + error.message);
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
async function carregarLog() {
    console.log('🔄 Carregando log...');
    try {
        const response = await fetch(`${API_BASE}log_dispensacoes.php?limit=10`);
        console.log('📥 Response status:', response.status);
        
        const data = await response.json();
        console.log('📊 Dados recebidos:', data);
        
        const container = document.getElementById('logDispensacoes');
        
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
                            <span class="font-semibold">👤</span> ${d.usuario_nome}
                        </p>
                    </div>
                </div>
            `).join('');
        } else {
            console.log('⚠️ Sem dispensações ou erro:', data);
            const mensagem = data.message || 'Nenhuma dispensação registrada ainda';
            container.innerHTML = `<div class="col-span-full text-center py-8 text-gray-400 text-sm">${mensagem}</div>`;
        }
    } catch (error) {
        console.error('❌ Erro ao carregar log:', error);
        const container = document.getElementById('logDispensacoes');
        container.innerHTML = '<div class="col-span-full text-center py-8 text-red-400 text-sm">Erro ao carregar dispensações</div>';
    }
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
