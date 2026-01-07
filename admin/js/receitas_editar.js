// Estado
let pacienteSelecionado = null;
let medicamentoSelecionado = null;
const API_BASE = (typeof window !== 'undefined' && window.RECEITAS_API_BASE) ? window.RECEITAS_API_BASE : 'api/';

document.addEventListener('DOMContentLoaded', () => {
    // Carregar dados iniciais
    const receitaId = document.getElementById('receita_id').value;
    const pacienteId = document.getElementById('paciente_id').value;
    const medicamentoId = document.getElementById('medicamento_id').value;
    
    if (pacienteId) {
        pacienteSelecionado = { id: pacienteId };
    }
    
    if (medicamentoId) {
        medicamentoSelecionado = { id: medicamentoId };
    }
    
    document.getElementById('formReceita').addEventListener('submit', salvarReceita);
    document.getElementById('tipo_receita').addEventListener('change', atualizarTipoReceita);
    document.getElementById('data_emissao').addEventListener('change', atualizarDataValidade);
    document.getElementById('data_emissao').addEventListener('change', calcularPreview);
    
    ['quantidade_por_retirada', 'numero_retiradas', 'intervalo_dias'].forEach(id => {
        document.getElementById(id).addEventListener('input', calcularPreview);
    });
    
    // Inicializar estado
    atualizarTipoReceita();
    atualizarDataValidade();
    calcularPreview();
});

function atualizarDataValidade() {
    const dataEmissao = document.getElementById('data_emissao').value;
    if (dataEmissao) {
        const data = new Date(dataEmissao);
        data.setMonth(data.getMonth() + 1);
        const dataValidade = data.toISOString().split('T')[0];
        document.getElementById('data_validade').value = dataValidade;
    }
}

async function atualizarTipoReceita() {
    const tipoReceita = document.getElementById('tipo_receita').value;
    const numeroReceitaInput = document.getElementById('numero_receita');
    const numeroReceitaHelp = document.getElementById('numero_receita_help');
    
    if (tipoReceita === 'branca') {
        numeroReceitaInput.disabled = true;
        numeroReceitaInput.required = false;
        numeroReceitaInput.classList.add('bg-gray-100', 'cursor-not-allowed');
        numeroReceitaInput.classList.remove('bg-white');
        numeroReceitaHelp.textContent = 'Número gerado automaticamente';
        numeroReceitaHelp.classList.add('text-green-600');
        numeroReceitaHelp.classList.remove('text-gray-500');
    } else {
        numeroReceitaInput.disabled = false;
        numeroReceitaInput.required = true;
        numeroReceitaInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
        numeroReceitaInput.classList.add('bg-white');
        numeroReceitaHelp.textContent = 'Digite o número da receita azul';
        numeroReceitaHelp.classList.remove('text-green-600');
        numeroReceitaHelp.classList.add('text-gray-500');
    }
}

function alterarQuantidadeRetirada(delta) {
    const input = document.getElementById('quantidade_por_retirada');
    const valorAtual = parseInt(input.value) || 1;
    const novoValor = Math.max(1, valorAtual + delta);
    input.value = novoValor;
    calcularPreview();
}

function alterarNumeroRetiradas(delta) {
    const input = document.getElementById('numero_retiradas');
    const valorAtual = parseInt(input.value) || 1;
    const novoValor = Math.max(1, Math.min(12, valorAtual + delta));
    input.value = novoValor;
    calcularPreview();
}

// Armazenar datas planejadas para envio
let datasPlanejadas = [];

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
        datasPlanejadas = [];
        return;
    }
    
    let html = '';
    let dataAtual = new Date(dataEmissao + 'T00:00:00');
    datasPlanejadas = []; // Resetar array
    
    // Contar quantas dispensações já foram realizadas
    const totalDispensacoes = (typeof dispensacoesRealizadas !== 'undefined' && dispensacoesRealizadas.length > 0) 
        ? dispensacoesRealizadas.length 
        : 0;
    
    for (let i = 1; i <= numeroRetiradas; i++) {
        const dataRetirada = new Date(dataAtual);
        dataRetirada.setDate(dataRetirada.getDate() + (intervaloDias * (i - 1)));
        
        // Formatar data para YYYY-MM-DD para salvar no banco
        const dataFormatada = dataRetirada.toISOString().split('T')[0];
        
        // Verificar se esta é uma das primeiras retiradas que já foi dispensada
        // As primeiras N dispensações (onde N = totalDispensacoes) são consideradas já realizadas
        const jaDispensada = i <= totalDispensacoes;
        
        // Se já foi dispensada, buscar a quantidade da dispensação correspondente
        let quantidadeDispensada = quantidadePorRetirada;
        if (jaDispensada && typeof dispensacoesRealizadas !== 'undefined' && dispensacoesRealizadas[i - 1]) {
            quantidadeDispensada = dispensacoesRealizadas[i - 1].quantidade;
        }
        
        // Se já foi dispensada, usar a quantidade dispensada, senão usar a planejada
        const quantidadeExibir = jaDispensada ? quantidadeDispensada : quantidadePorRetirada;
        
        datasPlanejadas.push({
            numero_retirada: i,
            data_planejada: dataFormatada,
            quantidade_planejada: quantidadeExibir
        });
        
        const dataFormatadaBR = dataRetirada.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        const diaSemana = dataRetirada.toLocaleDateString('pt-BR', { weekday: 'short' });
        
        // Estilos diferentes para dispensado (laranja) e não dispensado (verde/azul)
        const corFundo = jaDispensada ? 'bg-orange-50 border-orange-300' : 'bg-white border-blue-200';
        const corNumero = jaDispensada ? 'bg-orange-500' : 'bg-blue-500';
        const corIcone = jaDispensada ? 'text-orange-500' : 'text-blue-500';
        const corQuantidade = jaDispensada ? 'text-orange-600' : 'text-green-600';
        const corIconeQuantidade = jaDispensada ? 'text-orange-500' : 'text-green-500';
        const textoStatus = jaDispensada ? '✓ Dispensado' : '';
        
        html += `
            <div class="inline-flex items-center gap-2 ${corFundo} rounded-md px-3 py-1.5 text-xs shadow-sm hover:shadow transition-shadow">
                <div class="${corNumero} text-white w-5 h-5 rounded-full flex items-center justify-center font-bold text-[10px] flex-shrink-0">
                    ${i}
                </div>
                <div class="flex items-center gap-1.5 text-gray-700">
                    <svg class="w-3 h-3 ${corIcone} flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="font-medium whitespace-nowrap">${dataFormatadaBR}</span>
                    <span class="text-gray-400">•</span>
                    <span class="text-gray-500">${diaSemana}</span>
                </div>
                <div class="flex items-center gap-1 text-gray-600">
                    <svg class="w-3 h-3 ${corIconeQuantidade} flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <span class="font-semibold ${corQuantidade} whitespace-nowrap">${quantidadeExibir} un</span>
                    ${textoStatus ? `<span class="text-xs ${corQuantidade} font-medium ml-1">${textoStatus}</span>` : ''}
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
        Swal.fire({
            icon: 'warning',
            title: 'Atenção!',
            text: 'Selecione um paciente'
        });
        return;
    }
    
    if (!medicamentoSelecionado) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção!',
            text: 'Selecione um medicamento'
        });
        return;
    }
    
    const receitaId = document.getElementById('receita_id').value;
    const tipoReceita = document.getElementById('tipo_receita').value;
    const numeroReceita = tipoReceita === 'branca' ? '' : document.getElementById('numero_receita').value;
    
    const formData = {
        receita_id: receitaId,
        paciente_id: pacienteSelecionado.id,
        tipo_receita: tipoReceita,
        numero_receita: numeroReceita,
        data_emissao: document.getElementById('data_emissao').value,
        data_validade: document.getElementById('data_validade').value,
        medicamento_id: medicamentoSelecionado.id,
        quantidade_por_retirada: parseInt(document.getElementById('quantidade_por_retirada').value),
        numero_retiradas: parseInt(document.getElementById('numero_retiradas').value),
        intervalo_dias: parseInt(document.getElementById('intervalo_dias').value),
        observacoes: document.getElementById('observacoes').value,
        datas_planejadas: datasPlanejadas
    };
    
    try {
        const response = await fetch(`${API_BASE}editar_receita.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: 'Receita atualizada com sucesso!',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'receitas_dispensar.php?id=' + receitaId;
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: data.message || 'Erro ao atualizar receita'
            });
        }
    } catch (error) {
        console.error('Erro ao salvar receita:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: 'Erro ao atualizar receita. Verifique o console para mais detalhes.'
        });
    }
}

