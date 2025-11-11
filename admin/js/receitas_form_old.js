// Estado global
let pacienteSelecionado = null;
let medicamentoModal = null;
let medicamentosReceita = [];
let pacienteSearchTimeout;
let medicamentoModalTimeout;

// ============ BUSCAR PACIENTE ============

document.getElementById('pacienteSearch').addEventListener('input', function(e) {
    const query = e.target.value.trim();
    
    clearTimeout(pacienteSearchTimeout);
    
    if (query.length < 2) {
        document.getElementById('pacienteResults').classList.add('hidden');
        return;
    }
    
    pacienteSearchTimeout = setTimeout(() => {
        buscarPaciente(query);
    }, 300);
});

async function buscarPaciente(query) {
    try {
        const response = await fetch(`api/buscar_paciente.php?q=${encodeURIComponent(query)}&med_id=0`);
        const data = await response.json();
        
        if (data.success && data.pacientes.length > 0) {
            exibirResultadosPacientes(data.pacientes);
        } else {
            document.getElementById('pacienteResults').innerHTML = '<p class="p-4 text-sm text-slate-500">Nenhum paciente encontrado</p>';
            document.getElementById('pacienteResults').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Erro ao buscar paciente:', error);
    }
}

function exibirResultadosPacientes(pacientes) {
    const container = document.getElementById('pacienteResults');
    container.innerHTML = pacientes.map(pac => `
        <div class="p-4 hover:bg-primary-50 cursor-pointer transition border-b border-slate-100 last:border-0" onclick='selecionarPaciente(${JSON.stringify(pac)})'>
            <h4 class="font-semibold text-slate-900">${pac.nome}</h4>
            <p class="text-sm text-slate-600 mt-1">
                ${pac.cpf ? `CPF: ${pac.cpf}` : ''} 
                ${pac.cpf && pac.cartao_sus ? ' • ' : ''}
                ${pac.cartao_sus ? `SUS: ${pac.cartao_sus}` : ''}
            </p>
        </div>
    `).join('');
    container.classList.remove('hidden');
}

function selecionarPaciente(paciente) {
    pacienteSelecionado = paciente;
    document.getElementById('paciente_id').value = paciente.id;
    document.getElementById('pacNome').textContent = paciente.nome;
    document.getElementById('pacCpf').textContent = paciente.cpf ? `CPF: ${paciente.cpf}` : 'CPF não informado';
    document.getElementById('pacCartaoSus').textContent = paciente.cartao_sus ? `SUS: ${paciente.cartao_sus}` : 'SUS não informado';
    
    document.getElementById('pacienteSelecionado').classList.remove('hidden');
    document.getElementById('pacienteResults').classList.add('hidden');
    document.getElementById('pacienteSearch').value = '';
}

function limparPaciente() {
    pacienteSelecionado = null;
    document.getElementById('paciente_id').value = '';
    document.getElementById('pacienteSelecionado').classList.add('hidden');
}

// ============ MODAL MEDICAMENTO ============

function mostrarModalMedicamento() {
    document.getElementById('modalMedicamento').classList.remove('hidden');
    document.getElementById('modalMedicamentoSearch').focus();
}

function fecharModalMedicamento() {
    document.getElementById('modalMedicamento').classList.add('hidden');
    limparModalMedicamento();
}

function limparModalMedicamento() {
    medicamentoModal = null;
    document.getElementById('modalMedicamentoSearch').value = '';
    document.getElementById('modalMedicamentoResults').classList.add('hidden');
    document.getElementById('modalMedicamentoSelecionado').classList.add('hidden');
    document.getElementById('modalQuantidade').value = '1';
    document.getElementById('modalIntervalo').value = '30';
    document.getElementById('modalObservacoes').value = '';
}

document.getElementById('modalMedicamentoSearch').addEventListener('input', function(e) {
    const query = e.target.value.trim();
    
    clearTimeout(medicamentoModalTimeout);
    
    if (query.length < 2) {
        document.getElementById('modalMedicamentoResults').classList.add('hidden');
        return;
    }
    
    medicamentoModalTimeout = setTimeout(() => {
        buscarMedicamentoModal(query);
    }, 300);
});

async function buscarMedicamentoModal(query) {
    try {
        const response = await fetch(`api/buscar_medicamento.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success && data.medicamentos.length > 0) {
            exibirResultadosMedicamentosModal(data.medicamentos);
        } else {
            document.getElementById('modalMedicamentoResults').innerHTML = '<p class="p-4 text-sm text-slate-500">Nenhum medicamento encontrado</p>';
            document.getElementById('modalMedicamentoResults').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Erro ao buscar medicamento:', error);
    }
}

function exibirResultadosMedicamentosModal(medicamentos) {
    const container = document.getElementById('modalMedicamentoResults');
    container.innerHTML = medicamentos.map(med => `
        <div class="p-3 hover:bg-primary-50 cursor-pointer transition border-b border-slate-100 last:border-0" onclick='selecionarMedicamentoModal(${JSON.stringify(med)})'>
            <h4 class="font-semibold text-slate-900 text-sm">${med.nome}</h4>
            <p class="text-xs text-slate-600 mt-1">${med.apresentacao || 'Sem apresentação'}</p>
        </div>
    `).join('');
    container.classList.remove('hidden');
}

function selecionarMedicamentoModal(medicamento) {
    medicamentoModal = medicamento;
    document.getElementById('modalMedNome').textContent = medicamento.nome;
    document.getElementById('modalMedApresentacao').textContent = medicamento.apresentacao || 'Sem apresentação';
    document.getElementById('modalMedicamentoSelecionado').classList.remove('hidden');
    document.getElementById('modalMedicamentoResults').classList.add('hidden');
    document.getElementById('modalMedicamentoSearch').value = '';
}

function adicionarMedicamento() {
    if (!medicamentoModal) {
        alert('Selecione um medicamento');
        return;
    }
    
    const quantidade = parseInt(document.getElementById('modalQuantidade').value);
    const intervalo = parseInt(document.getElementById('modalIntervalo').value);
    const observacoes = document.getElementById('modalObservacoes').value.trim();
    
    if (quantidade < 1) {
        alert('Quantidade deve ser maior que zero');
        return;
    }
    
    if (intervalo < 1) {
        alert('Intervalo deve ser maior que zero');
        return;
    }
    
    // Verificar se medicamento já foi adicionado
    if (medicamentosReceita.find(m => m.medicamento_id === medicamentoModal.id)) {
        alert('Este medicamento já foi adicionado à receita');
        return;
    }
    
    medicamentosReceita.push({
        medicamento_id: medicamentoModal.id,
        nome: medicamentoModal.nome,
        apresentacao: medicamentoModal.apresentacao,
        quantidade_autorizada: quantidade,
        intervalo_dias: intervalo,
        observacoes: observacoes
    });
    
    atualizarListaMedicamentos();
    fecharModalMedicamento();
}

function removerMedicamento(index) {
    if (confirm('Remover este medicamento da receita?')) {
        medicamentosReceita.splice(index, 1);
        atualizarListaMedicamentos();
    }
}

function atualizarListaMedicamentos() {
    const container = document.getElementById('medicamentosList');
    
    if (medicamentosReceita.length === 0) {
        container.innerHTML = '<p class="text-sm text-slate-400 text-center py-8">Nenhum medicamento adicionado. Clique no botão acima para adicionar.</p>';
        return;
    }
    
    container.innerHTML = medicamentosReceita.map((med, index) => `
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <h4 class="font-semibold text-slate-900">${med.nome}</h4>
                    <p class="text-sm text-slate-600 mt-1">${med.apresentacao || 'Sem apresentação'}</p>
                    <div class="flex gap-4 mt-3 text-sm">
                        <span class="inline-flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/></svg>
                            <span class="font-medium text-slate-700">Quantidade: ${med.quantidade_autorizada}</span>
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="font-medium text-slate-700">Intervalo: ${med.intervalo_dias} dias</span>
                        </span>
                    </div>
                    ${med.observacoes ? `<p class="text-xs text-slate-500 mt-2 italic">${med.observacoes}</p>` : ''}
                </div>
                <button type="button" onclick="removerMedicamento(${index})" class="text-slate-400 hover:text-rose-600 transition ml-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>
    `).join('');
}

// ============ SUBMIT FORM ============

document.getElementById('receitaForm').addEventListener('submit', function(e) {
    if (medicamentosReceita.length === 0) {
        e.preventDefault();
        alert('Adicione pelo menos um medicamento à receita');
        return false;
    }
    
    if (!pacienteSelecionado) {
        e.preventDefault();
        alert('Selecione um paciente');
        return false;
    }
    
    // Atualizar campo hidden com JSON dos medicamentos
    document.getElementById('medicamentos_json').value = JSON.stringify(medicamentosReceita);
});

// Fechar modal ao clicar fora
document.getElementById('modalMedicamento').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalMedicamento();
    }
});

// ESC para fechar modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalMedicamento();
    }
});
