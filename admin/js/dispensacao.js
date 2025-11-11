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

// ============ STEP 1: BUSCAR MEDICAMENTO ============

console.log('🚀 Script dispensacao.js carregado!');

// Inicializar quando o DOM estiver pronto
function initDispensacao() {
    console.log('📄 Inicializando dispensação...');
    
    const medicamentoSearchInput = document.getElementById('medicamentoSearch');
    if (!medicamentoSearchInput) {
        console.error('❌ ERRO: Campo medicamentoSearch não encontrado no DOM!');
        return;
    }
    
    console.log('✅ Campo medicamentoSearch encontrado');
    
    // Focar no campo de busca
    medicamentoSearchInput.focus();
    
    // Event listener para busca de medicamento
    medicamentoSearchInput.addEventListener('input', function(e) {
    const query = e.target.value.trim();
    console.log('⌨️ Input detectado:', query, 'Length:', query.length);
    
    clearTimeout(medicamentoSearchTimeout);
    
    if (query.length < 2) {
        console.log('ℹ️ Query muito curta, escondendo resultados');
        document.getElementById('medicamentoResults').classList.add('hidden');
        return;
    }
    
    console.log('🔄 Mostrando loader...');
    document.getElementById('medicamentoLoader').classList.remove('hidden');
    
    medicamentoSearchTimeout = setTimeout(() => {
        console.log('⏰ Timeout atingido, chamando buscarMedicamento');
        buscarMedicamento(query);
    }, 300);
});

async function buscarMedicamento(query) {
    try {
        console.log('🔍 Buscando medicamento:', query);
        const response = await fetch(`api/buscar_medicamento.php?q=${encodeURIComponent(query)}`);
        console.log('📡 Response status:', response.status);
        
        const text = await response.text();
        console.log('📄 Response text:', text);
        
        let data;
        try {
            data = JSON.parse(text);
            console.log('✅ Response JSON:', data);
        } catch (parseError) {
            console.error('❌ Erro ao parsear JSON:', parseError);
            console.error('📄 Text recebido:', text);
            mostrarAlerta('Erro ao processar resposta do servidor', 'error');
            document.getElementById('medicamentoLoader').classList.add('hidden');
            return;
        }
        
        document.getElementById('medicamentoLoader').classList.add('hidden');
        
        if (data.success && data.medicamentos.length > 0) {
            exibirResultadosMedicamentos(data.medicamentos);
        } else {
            console.log('ℹ️ Nenhum medicamento encontrado:', data);
            document.getElementById('medicamentoResults').innerHTML = '<p class="p-4 text-sm text-slate-500 text-center">Nenhum medicamento encontrado</p>';
            document.getElementById('medicamentoResults').classList.remove('hidden');
        }
    } catch (error) {
        console.error('💥 Erro na requisição:', error);
        mostrarAlerta('Erro ao buscar medicamento', 'error');
        document.getElementById('medicamentoLoader').classList.add('hidden');
    }
}

function exibirResultadosMedicamentos(medicamentos) {
    const container = document.getElementById('medicamentoResults');
    container.innerHTML = medicamentos.map(med => `
        <div class="search-result-item" onclick='selecionarMedicamento(${JSON.stringify(med)})'>
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <h4 class="font-semibold text-slate-900">${med.nome}</h4>
                    <p class="text-sm text-slate-600 mt-1">${med.apresentacao || 'Sem apresentação'}</p>
                    ${med.codigo_barras ? `<p class="text-xs text-slate-400 mt-1 font-mono">COD: ${med.codigo_barras}</p>` : ''}
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${med.estoque_atual > 0 ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'}">
                        Estoque: ${med.estoque_atual}
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
    dispensacao.lote_id = medicamento.lote_id; // O lote com validade mais próxima
    
    // Atualizar UI
    document.getElementById('medNome').textContent = medicamento.nome;
    document.getElementById('medApresentacao').textContent = medicamento.apresentacao || 'N/A';
    document.getElementById('medEstoque').textContent = medicamento.estoque_atual;
    document.getElementById('medicamentoSelecionado').classList.remove('hidden');
    document.getElementById('medicamentoResults').classList.add('hidden');
    document.getElementById('medicamentoSearch').value = '';
    
    // Marcar step 1 como concluído e ativar step 2
    document.getElementById('step1Status').textContent = 'Concluído ✓';
    document.getElementById('step1Status').classList.remove('text-slate-400');
    document.getElementById('step1Status').classList.add('text-emerald-600');
    document.getElementById('step1').classList.add('completed');
    document.getElementById('step1').classList.remove('active');
    
    ativarStep(2);
    document.getElementById('maxQuantidade').textContent = medicamento.estoque_atual;
}

function limparMedicamento() {
    dispensacao.medicamento = null;
    dispensacao.lote_id = null;
    document.getElementById('medicamentoSelecionado').classList.add('hidden');
    document.getElementById('step1Status').textContent = 'Aguardando...';
    document.getElementById('step1Status').classList.add('text-slate-400');
    document.getElementById('step1Status').classList.remove('text-emerald-600');
    document.getElementById('step1').classList.remove('completed');
    document.getElementById('step1').classList.add('active');
    
    desativarStep(2);
    document.getElementById('medicamentoSearch').focus();
}

// ============ STEP 2: DEFINIR QUANTIDADE ============

function alterarQuantidade(delta) {
    const input = document.getElementById('quantidade');
    const novoValor = parseInt(input.value || 1) + delta;
    const max = dispensacao.medicamento?.estoque_atual || 0;
    
    if (novoValor >= 1 && novoValor <= max) {
        input.value = novoValor;
        dispensacao.quantidade = novoValor;
    }
}

function validarQuantidade() {
    const input = document.getElementById('quantidade');
    const valor = parseInt(input.value);
    const max = dispensacao.medicamento?.estoque_atual || 0;
    
    if (isNaN(valor) || valor < 1) {
        input.value = 1;
        dispensacao.quantidade = 1;
    } else if (valor > max) {
        input.value = max;
        dispensacao.quantidade = max;
        mostrarAlerta(`Quantidade máxima disponível: ${max}`, 'warning');
    } else {
        dispensacao.quantidade = valor;
    }
}

function confirmarQuantidade() {
    validarQuantidade();
    
    // Marcar step 2 como concluído e ativar step 3
    document.getElementById('step2Status').textContent = 'Concluído ✓';
    document.getElementById('step2Status').classList.remove('text-slate-400');
    document.getElementById('step2Status').classList.add('text-emerald-600');
    document.getElementById('step2').classList.add('completed');
    document.getElementById('step2').classList.remove('active');
    
    ativarStep(3);
    document.getElementById('pacienteSearch').focus();
}

// ============ STEP 3: BUSCAR PACIENTE ============

    // Event listener para busca de paciente
    const pacienteSearchInput = document.getElementById('pacienteSearch');
    if (pacienteSearchInput) {
        console.log('✅ Campo pacienteSearch encontrado');
        pacienteSearchInput.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            console.log('⌨️ Busca de paciente:', query);
            
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
    } else {
        console.error('❌ ERRO: Campo pacienteSearch não encontrado!');
    }

async function buscarPaciente(query) {
    try {
        const medId = dispensacao.medicamento?.id || 0;
        const response = await fetch(`api/buscar_paciente.php?q=${encodeURIComponent(query)}&med_id=${medId}`);
        const data = await response.json();
        
        document.getElementById('pacienteLoader').classList.add('hidden');
        
        if (data.success && data.pacientes.length > 0) {
            exibirResultadosPacientes(data.pacientes);
        } else {
            document.getElementById('pacienteResults').innerHTML = '<p class="p-4 text-sm text-slate-500 text-center">Nenhum paciente encontrado</p>';
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
                <div class="flex-1">
                    <h4 class="font-semibold text-slate-900">${pac.nome}</h4>
                    <div class="flex gap-4 mt-1 text-sm text-slate-600">
                        ${pac.cpf ? `<span>CPF: ${pac.cpf}</span>` : ''}
                        ${pac.cartao_sus ? `<span>SUS: ${pac.cartao_sus}</span>` : ''}
                    </div>
                    ${pac.receitas_ativas > 0 ? `<span class="inline-flex items-center mt-2 rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-xs font-semibold">${pac.receitas_ativas} receita(s) ativa(s)</span>` : ''}
                </div>
            </div>
        </div>
    `).join('');
    container.classList.remove('hidden');
}

async function selecionarPaciente(paciente) {
    dispensacao.paciente = paciente;
    
    // Atualizar UI
    document.getElementById('pacNome').textContent = paciente.nome;
    document.getElementById('pacCpf').textContent = paciente.cpf || 'Não informado';
    document.getElementById('pacCartaoSus').textContent = paciente.cartao_sus || 'Não informado';
    document.getElementById('pacienteSelecionado').classList.remove('hidden');
    document.getElementById('pacienteResults').classList.add('hidden');
    document.getElementById('pacienteSearch').value = '';
    
    // Buscar receitas ativas do paciente para este medicamento
    await carregarReceitasPaciente(paciente.id, dispensacao.medicamento.id);
    
    // Marcar step 3 como concluído e ativar step 4
    document.getElementById('step3Status').textContent = 'Concluído ✓';
    document.getElementById('step3Status').classList.remove('text-slate-400');
    document.getElementById('step3Status').classList.add('text-emerald-600');
    document.getElementById('step3').classList.add('completed');
    document.getElementById('step3').classList.remove('active');
    
    ativarStep(4);
    atualizarResumo();
}

async function carregarReceitasPaciente(pacienteId, medicamentoId) {
    try {
        const response = await fetch(`api/buscar_receitas.php?paciente_id=${pacienteId}&medicamento_id=${medicamentoId}`);
        const data = await response.json();
        
        if (data.success && data.receitas.length > 0) {
            exibirReceitas(data.receitas);
        } else {
            document.getElementById('receitasContainer').classList.add('hidden');
            // Marcar saída avulsa como padrão
            document.getElementById('saidaAvulsa').checked = true;
            dispensacao.tipo = 'avulsa';
            dispensacao.receita_item_id = null;
        }
    } catch (error) {
        console.error('Erro ao carregar receitas:', error);
        document.getElementById('receitasContainer').classList.add('hidden');
    }
}

function exibirReceitas(receitas) {
    const container = document.getElementById('receitasList');
    container.innerHTML = receitas.map((rec, index) => `
        <label class="flex items-start gap-3 p-3 rounded-xl border border-emerald-200 hover:bg-emerald-50 cursor-pointer transition">
            <input type="radio" name="tipoSaida" value="receita" data-receita-item-id="${rec.id}" class="mt-1 w-5 h-5 text-primary-600 focus:ring-primary-500" ${index === 0 ? 'checked' : ''} onchange="selecionarReceita(${rec.id})">
            <div class="flex-1 text-sm">
                <p class="font-semibold text-slate-900">Receita #${rec.receita_id}</p>
                <p class="text-slate-600 mt-1">Autorizado: ${rec.quantidade_autorizada} | Retirado: ${rec.quantidade_retirada} | <strong class="text-emerald-600">Disponível: ${rec.quantidade_autorizada - rec.quantidade_retirada}</strong></p>
                <p class="text-xs text-slate-400 mt-1">Validade: ${rec.data_validade}</p>
                ${rec.ultima_retirada ? `<p class="text-xs text-slate-400">Última retirada: ${rec.ultima_retirada}</p>` : ''}
            </div>
        </label>
    `).join('');
    
    document.getElementById('receitasContainer').classList.remove('hidden');
    
    // Selecionar a primeira receita automaticamente
    if (receitas.length > 0) {
        selecionarReceita(receitas[0].id);
    }
}

function selecionarReceita(receitaItemId) {
    dispensacao.tipo = 'receita';
    dispensacao.receita_item_id = receitaItemId;
    atualizarResumo();
}

// Listener para saída avulsa
document.addEventListener('DOMContentLoaded', function() {
    const saidaAvulsaRadio = document.getElementById('saidaAvulsa');
    if (saidaAvulsaRadio) {
        saidaAvulsaRadio.addEventListener('change', function() {
            if (this.checked) {
                dispensacao.tipo = 'avulsa';
                dispensacao.receita_item_id = null;
                atualizarResumo();
            }
        });
    }
});

function limparPaciente() {
    dispensacao.paciente = null;
    dispensacao.receita_item_id = null;
    dispensacao.tipo = 'avulsa';
    
    document.getElementById('pacienteSelecionado').classList.add('hidden');
    document.getElementById('receitasContainer').classList.add('hidden');
    document.getElementById('step3Status').textContent = 'Aguardando...';
    document.getElementById('step3Status').classList.add('text-slate-400');
    document.getElementById('step3Status').classList.remove('text-emerald-600');
    document.getElementById('step3').classList.remove('completed');
    document.getElementById('step3').classList.add('active');
    
    desativarStep(4);
    document.getElementById('pacienteSearch').focus();
}

// ============ STEP 4: CONFIRMAR DISPENSAÇÃO ============

function atualizarResumo() {
    document.getElementById('resumoMedicamento').textContent = dispensacao.medicamento?.nome || '-';
    document.getElementById('resumoQuantidade').textContent = dispensacao.quantidade || '-';
    document.getElementById('resumoPaciente').textContent = dispensacao.paciente?.nome || '-';
    document.getElementById('resumoTipo').textContent = dispensacao.tipo === 'receita' ? 'Com Receita' : 'Saída Avulsa';
}

async function processarDispensacao() {
    const btnConfirmar = document.getElementById('btnConfirmar');
    btnConfirmar.disabled = true;
    btnConfirmar.innerHTML = '<svg class="animate-spin h-5 w-5 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processando...';
    
    try {
        const response = await fetch('api/processar_dispensacao.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                medicamento_id: dispensacao.medicamento.id,
                lote_id: dispensacao.lote_id,
                paciente_id: dispensacao.paciente.id,
                quantidade: dispensacao.quantidade,
                tipo: dispensacao.tipo,
                receita_item_id: dispensacao.receita_item_id,
                observacoes: document.getElementById('observacoes').value
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarAlerta('Dispensação realizada com sucesso!', 'success');
            setTimeout(() => {
                reiniciarProcesso();
            }, 2000);
        } else {
            mostrarAlerta(data.message || 'Erro ao processar dispensação', 'error');
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Confirmar Dispensação';
        }
    } catch (error) {
        console.error('Erro ao processar dispensação:', error);
        mostrarAlerta('Erro ao processar dispensação', 'error');
        btnConfirmar.disabled = false;
        btnConfirmar.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Confirmar Dispensação';
    }
}

function reiniciarProcesso() {
    // Resetar estado
    dispensacao.medicamento = null;
    dispensacao.quantidade = 1;
    dispensacao.paciente = null;
    dispensacao.receita_item_id = null;
    dispensacao.tipo = 'avulsa';
    dispensacao.lote_id = null;
    
    // Resetar UI
    document.getElementById('medicamentoSelecionado').classList.add('hidden');
    document.getElementById('pacienteSelecionado').classList.add('hidden');
    document.getElementById('medicamentoSearch').value = '';
    document.getElementById('pacienteSearch').value = '';
    document.getElementById('quantidade').value = 1;
    document.getElementById('observacoes').value = '';
    
    // Resetar steps
    for (let i = 1; i <= 4; i++) {
        const step = document.getElementById(`step${i}`);
        const status = document.getElementById(`step${i}Status`);
        
        if (i === 1) {
            step.classList.add('active');
            step.classList.remove('completed', 'opacity-50', 'pointer-events-none');
        } else {
            step.classList.remove('active', 'completed');
            step.classList.add('opacity-50', 'pointer-events-none');
        }
        
        if (status) {
            status.textContent = 'Aguardando...';
            status.classList.add('text-slate-400');
            status.classList.remove('text-emerald-600');
        }
    }
    
    // Resetar botão
    const btnConfirmar = document.getElementById('btnConfirmar');
    btnConfirmar.disabled = false;
    btnConfirmar.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Confirmar Dispensação';
    
    // Focar no primeiro campo
    document.getElementById('medicamentoSearch').focus();
}

// ============ FUNÇÕES AUXILIARES ============

function ativarStep(stepNumber) {
    const step = document.getElementById(`step${stepNumber}`);
    step.classList.remove('opacity-50', 'pointer-events-none', 'completed');
    step.classList.add('active');
    
    // Atualizar badge do step
    const badge = step.querySelector('.rounded-full');
    badge.classList.remove('bg-slate-300');
    badge.classList.add('bg-primary-600');
}

function desativarStep(stepNumber) {
    const step = document.getElementById(`step${stepNumber}`);
    step.classList.add('opacity-50', 'pointer-events-none');
    step.classList.remove('active', 'completed');
    
    const status = document.getElementById(`step${stepNumber}Status`);
    if (status) {
        status.textContent = 'Aguardando...';
        status.classList.add('text-slate-400');
        status.classList.remove('text-emerald-600');
    }
    
    // Resetar badge
    const badge = step.querySelector('.rounded-full');
    badge.classList.remove('bg-primary-600');
    badge.classList.add('bg-slate-300');
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
    // Abrir página de cadastro em nova aba
    window.open('pacientes_form.php', '_blank');
}

} // Fim da função initDispensacao

// Chamar função de inicialização quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDispensacao);
} else {
    // DOM já está pronto
    initDispensacao();
}
