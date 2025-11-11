// Estado global da dispensação
const dispensacao = {
    medicamento: null,
    quantidade: 1,
    paciente: null,
    receita_item_id: null,
    tipo: 'avulsa',
    lote_id: null
};

let medicamentoSearchTimeout;
let pacienteSearchTimeout;

console.log('🚀 Script dispensacao simplificado carregado!');

// Inicializar quando o DOM estiver pronto
function initDispensacao() {
    console.log('📄 Inicializando dispensação...');
    
    const medicamentoSearchInput = document.getElementById('medicamentoSearch');
    if (!medicamentoSearchInput) {
        console.error('❌ ERRO: Campo medicamentoSearch não encontrado no DOM!');
        return;
    }
    
    console.log('✅ Campos encontrados, adicionando event listeners');
    
    // Focar no campo de busca
    medicamentoSearchInput.focus();
    
    // Event listener para busca de medicamento
    medicamentoSearchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        console.log('⌨️ Input medicamento:', query);
        
        clearTimeout(medicamentoSearchTimeout);
        
        if (query.length < 2) {
            document.getElementById('medicamentoResults').classList.add('hidden');
            return;
        }
        
        document.getElementById('medicamentoLoader').classList.remove('hidden');
        
        medicamentoSearchTimeout = setTimeout(() => {
            buscarMedicamento(query);
        }, 300);
    });

    // Event listener para busca de paciente
    const pacienteSearchInput = document.getElementById('pacienteSearch');
    if (pacienteSearchInput) {
        pacienteSearchInput.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            console.log('⌨️ Input paciente:', query);
            
            clearTimeout(pacienteSearchTimeout);
            
            if (query.length < 2) {
                document.getElementById('pacienteResults').classList.add('hidden');
                return;
            }
            
            document.getElementById('pacienteLoader').classList.remove('hidden');
            
            pacienteSearchTimeout = setTimeout(() => {
                buscarPaciente(query);
            }, 300);
        });
    }
    
    // Event listener para o formulário
    const form = document.getElementById('dispensacaoForm');
    if (form) {
        form.addEventListener('submit', processarDispensacao);
        console.log('✅ Form listener adicionado');
    }
    
    // Event listener para o botão confirmar (fallback)
    const btnConfirmar = document.getElementById('btnConfirmar');
    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', function(e) {
            if (!btnConfirmar.disabled) {
                e.preventDefault();
                processarDispensacao(e);
            }
        });
        console.log('✅ Botão confirmar listener adicionado');
    }
}

// ============ BUSCAR MEDICAMENTO ============

async function buscarMedicamento(query) {
    try {
        console.log('🔍 Buscando medicamento:', query);
        const response = await fetch(`api/buscar_medicamento.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        document.getElementById('medicamentoLoader').classList.add('hidden');
        
        if (data.success && data.medicamentos.length > 0) {
            exibirResultadosMedicamentos(data.medicamentos);
        } else {
            document.getElementById('medicamentoResults').innerHTML = '<p class="p-3 text-sm text-slate-500 text-center">Nenhum medicamento encontrado</p>';
            document.getElementById('medicamentoResults').classList.remove('hidden');
        }
    } catch (error) {
        console.error('💥 Erro ao buscar medicamento:', error);
        mostrarAlerta('Erro ao buscar medicamento', 'error');
        document.getElementById('medicamentoLoader').classList.add('hidden');
    }
}

function exibirResultadosMedicamentos(medicamentos) {
    const container = document.getElementById('medicamentoResults');
    container.innerHTML = medicamentos.map(med => `
        <div class="search-result-item" onclick='selecionarMedicamento(${JSON.stringify(med)})'>
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <h4 class="font-semibold text-sm text-slate-900 truncate">${med.nome}</h4>
                    <p class="text-xs text-slate-600 mt-1">${med.apresentacao || 'Sem apresentação'}</p>
                </div>
                <div class="text-right flex-shrink-0 ml-2">
                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold ${med.estoque_atual > 0 ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'}">
                        ${med.estoque_atual}
                    </span>
                </div>
            </div>
        </div>
    `).join('');
    container.classList.remove('hidden');
}

function selecionarMedicamento(medicamento) {
    if (medicamento.estoque_atual <= 0) {
        mostrarAlerta('Medicamento sem estoque disponível', 'error');
        return;
    }
    
    dispensacao.medicamento = medicamento;
    dispensacao.lote_id = medicamento.lote_id;
    
    // Atualizar UI
    document.getElementById('medNome').textContent = medicamento.nome;
    document.getElementById('medApresentacao').textContent = medicamento.apresentacao || 'N/A';
    document.getElementById('medEstoque').textContent = medicamento.estoque_atual;
    document.getElementById('medicamentoSelecionado').classList.remove('hidden');
    document.getElementById('medicamentoResults').classList.add('hidden');
    document.getElementById('medicamentoSearch').value = '';
    
    // Ativar step 2 e 3
    document.getElementById('step2').classList.remove('opacity-50', 'pointer-events-none');
    document.getElementById('step2').classList.add('active');
    document.getElementById('step3').classList.remove('opacity-50', 'pointer-events-none');
    document.getElementById('step3').classList.add('active');
    
    document.getElementById('maxQuantidade').textContent = medicamento.estoque_atual;
    document.getElementById('quantidade').max = medicamento.estoque_atual;
    
    verificarHabilitarConfirmacao();
}

function limparMedicamento() {
    dispensacao.medicamento = null;
    dispensacao.lote_id = null;
    document.getElementById('medicamentoSelecionado').classList.add('hidden');
    
    // Desativar steps 2 e 3
    document.getElementById('step2').classList.add('opacity-50', 'pointer-events-none');
    document.getElementById('step2').classList.remove('active');
    document.getElementById('step3').classList.add('opacity-50', 'pointer-events-none');
    document.getElementById('step3').classList.remove('active');
    
    limparPaciente();
    verificarHabilitarConfirmacao();
}

// ============ QUANTIDADE ============

function alterarQuantidade(delta) {
    const input = document.getElementById('quantidade');
    let novaQtd = parseInt(input.value) + delta;
    const max = dispensacao.medicamento?.estoque_atual || 0;
    
    if (novaQtd < 1) novaQtd = 1;
    if (novaQtd > max) novaQtd = max;
    
    input.value = novaQtd;
    dispensacao.quantidade = novaQtd;
    verificarHabilitarConfirmacao();
}

function validarQuantidade() {
    const input = document.getElementById('quantidade');
    let qtd = parseInt(input.value);
    const max = dispensacao.medicamento?.estoque_atual || 0;
    
    if (isNaN(qtd) || qtd < 1) qtd = 1;
    if (qtd > max) {
        qtd = max;
        mostrarAlerta(`Quantidade máxima disponível: ${max}`, 'warning');
    }
    
    input.value = qtd;
    dispensacao.quantidade = qtd;
    verificarHabilitarConfirmacao();
}

// ============ BUSCAR PACIENTE ============

async function buscarPaciente(query) {
    try {
        const medId = dispensacao.medicamento?.id || 0;
        const response = await fetch(`api/buscar_paciente.php?q=${encodeURIComponent(query)}&medicamento_id=${medId}`);
        const data = await response.json();
        
        document.getElementById('pacienteLoader').classList.add('hidden');
        
        if (data.success && data.pacientes.length > 0) {
            exibirResultadosPacientes(data.pacientes);
        } else {
            document.getElementById('pacienteResults').innerHTML = '<p class="p-3 text-sm text-slate-500 text-center">Nenhum paciente encontrado</p>';
            document.getElementById('pacienteResults').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Erro ao buscar paciente:', error);
        mostrarAlerta('Erro ao buscar paciente', 'error');
        document.getElementById('pacienteLoader').classList.add('hidden');
    }
}

function exibirResultadosPacientes(pacientes) {
    const container = document.getElementById('pacienteResults');
    container.innerHTML = pacientes.map(pac => `
        <div class="search-result-item" onclick='selecionarPaciente(${JSON.stringify(pac)})'>
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <h4 class="font-semibold text-sm text-slate-900 truncate">${pac.nome}</h4>
                    <p class="text-xs text-slate-600 mt-1">CPF: ${pac.cpf || 'N/A'}</p>
                </div>
                ${pac.receitas_ativas > 0 ? `<span class="text-xs bg-primary-100 text-primary-700 px-2 py-1 rounded-full flex-shrink-0 ml-2">${pac.receitas_ativas} receita(s)</span>` : ''}
            </div>
        </div>
    `).join('');
    container.classList.remove('hidden');
}

async function selecionarPaciente(paciente) {
    dispensacao.paciente = paciente;
    
    // Atualizar UI
    document.getElementById('pacNome').textContent = paciente.nome;
    document.getElementById('pacCpf').textContent = paciente.cpf || 'N/A';
    document.getElementById('pacienteSelecionado').classList.remove('hidden');
    document.getElementById('pacienteResults').classList.add('hidden');
    document.getElementById('pacienteSearch').value = '';
    
    // Buscar receitas se houver
    if (paciente.receitas_ativas > 0 && dispensacao.medicamento) {
        await buscarReceitas(paciente.id);
    }
    
    verificarHabilitarConfirmacao();
}

function limparPaciente() {
    dispensacao.paciente = null;
    dispensacao.receita_item_id = null;
    dispensacao.tipo = 'avulsa';
    document.getElementById('pacienteSelecionado').classList.add('hidden');
    document.getElementById('receitasDisponiveis')?.classList.add('hidden');
    verificarHabilitarConfirmacao();
}

async function buscarReceitas(pacienteId) {
    try {
        const medId = dispensacao.medicamento.id;
        const response = await fetch(`api/buscar_receitas.php?paciente_id=${pacienteId}&medicamento_id=${medId}`);
        const data = await response.json();
        
        if (data.success && data.receitas && data.receitas.length > 0) {
            exibirReceitas(data.receitas);
        }
    } catch (error) {
        console.error('Erro ao buscar receitas:', error);
    }
}

function exibirReceitas(receitas) {
    const container = document.getElementById('listaReceitas');
    container.innerHTML = receitas.map(r => `
        <label class="flex items-center gap-2 p-2 rounded bg-white border cursor-pointer hover:bg-primary-50">
            <input type="radio" name="receita_opcao" value="${r.id}" onchange="selecionarReceita(${r.id})" class="text-primary-600">
            <span class="text-xs flex-1">
                ${r.medicamento_nome} - ${r.quantidade_disponivel} disponível
                ${r.intervalo_dias ? ` (cada ${r.intervalo_dias} dias)` : ''}
            </span>
        </label>
    `).join('');
    document.getElementById('receitasDisponiveis').classList.remove('hidden');
}

function selecionarReceita(receitaItemId) {
    if (receitaItemId === 'avulsa') {
        dispensacao.receita_item_id = null;
        dispensacao.tipo = 'avulsa';
    } else {
        dispensacao.receita_item_id = receitaItemId;
        dispensacao.tipo = 'receita';
    }
}

// ============ PROCESSAR DISPENSAÇÃO ============

function verificarHabilitarConfirmacao() {
    const btn = document.getElementById('btnConfirmar');
    const habilitado = dispensacao.medicamento && dispensacao.quantidade > 0 && dispensacao.paciente;
    
    btn.disabled = !habilitado;
}

async function processarDispensacao(event) {
    if (event) {
        event.preventDefault();
    }
    
    const btn = document.getElementById('btnConfirmar');
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Processando...';
    
    console.log('🚀 Processando dispensação:', dispensacao);
    
    try {
        const observacoes = document.getElementById('observacoes').value;
        
        const response = await fetch('api/processar_dispensacao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                medicamento_id: dispensacao.medicamento.id,
                lote_id: dispensacao.lote_id,
                quantidade: dispensacao.quantidade,
                paciente_id: dispensacao.paciente.id,
                receita_item_id: dispensacao.receita_item_id,
                tipo: dispensacao.tipo,
                observacoes: observacoes
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarModalSucesso();
        } else {
            mostrarAlerta(data.message || 'Erro ao processar dispensação', 'error');
            btn.disabled = false;
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Confirmar Dispensação';
        }
    } catch (error) {
        console.error('Erro:', error);
        mostrarAlerta('Erro ao processar dispensação', 'error');
        btn.disabled = false;
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Confirmar Dispensação';
    }
}

function mostrarModalSucesso() {
    document.getElementById('modalMedicamento').textContent = dispensacao.medicamento.nome;
    document.getElementById('modalQuantidade').textContent = dispensacao.quantidade;
    document.getElementById('modalPaciente').textContent = dispensacao.paciente.nome;
    document.getElementById('modalConfirmacao').classList.remove('hidden');
}

function fecharModal() {
    document.getElementById('modalConfirmacao').classList.add('hidden');
    reiniciarProcesso();
}

function reiniciarProcesso() {
    dispensacao.medicamento = null;
    dispensacao.quantidade = 1;
    dispensacao.paciente = null;
    dispensacao.receita_item_id = null;
    dispensacao.tipo = 'avulsa';
    dispensacao.lote_id = null;
    
    document.getElementById('medicamentoSearch').value = '';
    document.getElementById('pacienteSearch').value = '';
    document.getElementById('quantidade').value = 1;
    document.getElementById('observacoes').value = '';
    document.getElementById('medicamentoSelecionado').classList.add('hidden');
    document.getElementById('pacienteSelecionado').classList.add('hidden');
    document.getElementById('receitasDisponiveis')?.classList.add('hidden');
    
    document.getElementById('step2').classList.add('opacity-50', 'pointer-events-none');
    document.getElementById('step3').classList.add('opacity-50', 'pointer-events-none');
    
    const btn = document.getElementById('btnConfirmar');
    btn.disabled = true;
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Confirmar Dispensação';
    
    document.getElementById('medicamentoSearch').focus();
}

function mostrarAlerta(mensagem, tipo = 'info') {
    const container = document.getElementById('alertContainer');
    const cores = {
        success: 'bg-emerald-50 border-emerald-200 text-emerald-700',
        error: 'bg-rose-50 border-rose-200 text-rose-700',
        warning: 'bg-amber-50 border-amber-200 text-amber-700',
        info: 'bg-blue-50 border-blue-200 text-blue-700'
    };
    
    container.className = `glass-card border px-6 py-4 ${cores[tipo] || cores.info}`;
    container.innerHTML = `<span class="text-sm font-medium">${mensagem}</span>`;
    container.classList.remove('hidden');
    
    setTimeout(() => {
        container.classList.add('hidden');
    }, 5000);
}

function mostrarModalCadastroPaciente() {
    window.open('pacientes_form.php', '_blank');
}

// Chamar função de inicialização quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDispensacao);
} else {
    initDispensacao();
}