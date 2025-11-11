// Estado
let pacienteSelecionado = null;
let medicamentoSelecionado = null;
let searchTimeout = null;
const API_BASE = (typeof window !== 'undefined' && window.RECEITAS_API_BASE) ? window.RECEITAS_API_BASE : 'api/';

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('pacienteSearch').addEventListener('input', buscarPacientes);
    document.getElementById('medicamentoSearch').addEventListener('input', buscarMedicamentos);
    document.getElementById('formReceita').addEventListener('submit', salvarReceita);
    
    ['quantidade_por_retirada', 'numero_retiradas', 'intervalo_dias'].forEach(id => {
        document.getElementById(id).addEventListener('input', calcularPreview);
    });
});

function buscarPacientes(e) {
    const query = e.target.value.trim();
    const results = document.getElementById('pacienteResults');
    
    clearTimeout(searchTimeout);
    
    if (query.length < 2) {
        results.classList.add('hidden');
        return;
    }
    
    searchTimeout = setTimeout(async () => {
        try {
            console.log('🔍 Buscando pacientes:', query);
            const url = `${API_BASE}buscar_paciente.php?q=${encodeURIComponent(query)}`;
            console.log('🌐 URL:', url);
            
            const response = await fetch(url);
            console.log('📡 Response status:', response.status);
            console.log('📡 Response headers:', response.headers);
            
            const textResponse = await response.text();
            console.log('📄 Response text:', textResponse);
            
            const data = JSON.parse(textResponse);
            console.log('📦 Data recebida:', data);
            
            if (data.success && data.pacientes && data.pacientes.length > 0) {
                console.log('✅ Pacientes encontrados:', data.pacientes.length);
                
                const html = data.pacientes.map(p => `
                    <div 
                        onclick='selecionarPaciente(${JSON.stringify(p).replace(/'/g, "&apos;")})' 
                        class="p-3 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-0 transition-colors"
                    >
                        <p class="font-semibold text-gray-800 text-sm">${p.nome}</p>
                        <p class="text-xs text-gray-600 mt-1">
                            ${p.cpf ? `CPF: ${p.cpf}` : 'CPF não informado'}
                            ${p.cartao_sus ? ` | SUS: ${p.cartao_sus}` : ''}
                        </p>
                    </div>
                `).join('');
                
                results.innerHTML = html;
                results.classList.remove('hidden');
            } else {
                console.log('⚠️ Nenhum paciente encontrado');
                results.innerHTML = '<div class="p-3 text-gray-500 text-center text-sm">Nenhum paciente encontrado</div>';
                results.classList.remove('hidden');
            }
        } catch (error) {
            console.error('❌ Erro ao buscar pacientes:', error);
            results.innerHTML = '<div class="p-3 text-red-500 text-center text-sm">Erro ao buscar pacientes. Verifique o console.</div>';
            results.classList.remove('hidden');
        }
    }, 300);
}

function selecionarPaciente(paciente) {
    pacienteSelecionado = paciente;
    document.getElementById('pacienteSearch').value = paciente.nome;
    document.getElementById('paciente_id').value = paciente.id;
    document.getElementById('pacienteResults').classList.add('hidden');
    
    // Exibe informações do paciente
    const infoDiv = document.getElementById('pacienteInfo');
    infoDiv.innerHTML = `
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center gap-2 mb-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <span class="font-semibold text-gray-900">${paciente.nome}</span>
            </div>
            <div class="text-sm text-gray-600 space-y-1">
                <div>CPF: ${paciente.cpf || 'N/A'}</div>
                ${paciente.cartao_sus ? `<div>Cartão SUS: ${paciente.cartao_sus}</div>` : ''}
            </div>
        </div>
    `;
    infoDiv.classList.remove('hidden');
    
    // Habilita busca de medicamento
    document.getElementById('medicamentoSearch').disabled = false;
}

function buscarMedicamentos(e) {
    const query = e.target.value.trim();
    const results = document.getElementById('medicamentoResults');
    
    clearTimeout(searchTimeout);
    
    if (query.length < 2) {
        results.classList.add('hidden');
        return;
    }
    
    searchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(`${API_BASE}buscar_medicamento.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success && data.medicamentos.length > 0) {
                let html = '<div class="divide-y divide-gray-100">';
                data.medicamentos.forEach(m => {
                    html += `
                        <div class="p-3 hover:bg-blue-50 cursor-pointer transition-colors"
                             onclick='selecionarMedicamento(${JSON.stringify(m).replace(/'/g, "&#39;")})'>
                            <div class="font-medium text-gray-900">${m.nome}</div>
                            <div class="text-sm text-gray-600">
                                <span class="inline-block bg-green-100 text-green-800 px-2 py-0.5 rounded text-xs">
                                    Estoque: ${m.estoque_atual || 0}
                                </span>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                results.innerHTML = html;
                results.classList.remove('hidden');
            } else {
                results.innerHTML = '<div class="p-3 text-gray-500 text-center">Nenhum medicamento encontrado</div>';
                results.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Erro ao buscar medicamentos:', error);
            results.innerHTML = '<div class="p-3 text-red-500 text-center">Erro ao buscar medicamentos</div>';
            results.classList.remove('hidden');
        }
    }, 300);
}

function selecionarMedicamento(medicamento) {
    medicamentoSelecionado = medicamento;
    document.getElementById('medicamentoSearch').value = medicamento.nome;
    document.getElementById('medicamento_id').value = medicamento.id;
    document.getElementById('medicamentoResults').classList.add('hidden');
    
    // Exibe card do medicamento
    const infoDiv = document.getElementById('medicamentoInfo');
    infoDiv.innerHTML = `
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <span class="font-semibold text-gray-900">${medicamento.nome}</span>
                </div>
                <span class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                    Estoque: ${medicamento.estoque_atual || 0}
                </span>
            </div>
        </div>
    `;
    infoDiv.classList.remove('hidden');
    
    // Habilita campos de quantidade
    ['quantidade_por_retirada', 'numero_retiradas', 'intervalo_dias'].forEach(id => {
        document.getElementById(id).disabled = false;
    });
}

function calcularPreview() {
    const quantidadePorRetirada = parseInt(document.getElementById('quantidade_por_retirada').value) || 0;
    const numeroRetiradas = parseInt(document.getElementById('numero_retiradas').value) || 0;
    const intervaloDias = parseInt(document.getElementById('intervalo_dias').value) || 0;
    const dataEmissao = document.getElementById('data_emissao').value;
    
    const previewDiv = document.getElementById('previewRetiradas');
    const listaDiv = document.getElementById('listaRetiradas');
    
    if (!quantidadePorRetirada || !numeroRetiradas || !intervaloDias || !dataEmissao) {
        previewDiv.classList.add('hidden');
        listaDiv.innerHTML = '';
        return;
    }
    
    let html = '';
    let dataAtual = new Date(dataEmissao + 'T00:00:00');
    
    for (let i = 1; i <= numeroRetiradas; i++) {
        const dataRetirada = new Date(dataAtual);
        dataRetirada.setDate(dataRetirada.getDate() + (intervaloDias * (i - 1)));
        
        html += `
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-lg p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="bg-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm">
                        ${i}
                    </div>
                    <span class="font-semibold text-gray-900">Retirada ${i}</span>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2 text-gray-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>${dataRetirada.toLocaleDateString('pt-BR')}</span>
                    </div>
                    <div class="flex items-center gap-2 text-gray-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <span class="font-medium">${quantidadePorRetirada} unidades</span>
                    </div>
                </div>
            </div>
        `;
    }
    
    listaDiv.innerHTML = html;
    previewDiv.classList.remove('hidden');
}

async function salvarReceita(e) {
    e.preventDefault();
    
    if (!pacienteSelecionado) {
        alert('Selecione um paciente');
        return;
    }
    
    if (!medicamentoSelecionado) {
        alert('Selecione um medicamento');
        return;
    }
    
    const formData = {
        paciente_id: pacienteSelecionado.id,
        numero_receita: document.getElementById('numero_receita').value,
        data_emissao: document.getElementById('data_emissao').value,
        data_validade: document.getElementById('data_validade').value,
        medicamento_id: medicamentoSelecionado.id,
        quantidade_por_retirada: parseInt(document.getElementById('quantidade_por_retirada').value),
        numero_retiradas: parseInt(document.getElementById('numero_retiradas').value),
        intervalo_dias: parseInt(document.getElementById('intervalo_dias').value),
        observacoes: document.getElementById('observacoes').value
    };
    
    try {
        const response = await fetch(`${API_BASE}salvar_receita.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Receita cadastrada com sucesso!');
            window.location.href = 'receitas.php';
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        console.error('Erro ao salvar receita:', error);
        alert('Erro ao salvar receita. Verifique o console para mais detalhes.');
    }
}
