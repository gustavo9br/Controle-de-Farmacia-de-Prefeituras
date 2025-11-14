<?php
// Este arquivo renderiza o conteúdo de cada aba de configuração
// Variáveis disponíveis: $config, $edit_data, $edit_id, $csrf_token
?>

<!-- Formulário -->
<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-base sm:text-lg font-semibold text-slate-900 mb-4 sm:mb-6 flex items-center gap-2">
        <?php echo $config['icon']; ?>
        <span><?php echo $edit_id > 0 ? 'Editar ' . $config['title'] : 'Adicionar ' . $config['title']; ?></span>
    </h2>
    
    <form method="post" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="<?php echo $edit_id > 0 ? 'edit_' . $config['form_type'] : 'add_' . $config['form_type']; ?>">
        <?php if ($edit_id > 0): ?>
            <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
        <?php endif; ?>
        
        <?php if ($config['form_type'] === 'fabricante'): ?>
            <!-- Formulário de Fabricante -->
            <div class="grid gap-5 grid-cols-1 sm:grid-cols-2">
                <div>
                    <label for="nome" class="block text-sm font-medium text-slate-700 mb-2">
                        Nome <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="nome" name="nome" value="<?php echo isset($edit_data['nome']) ? htmlspecialchars($edit_data['nome']) : ''; ?>" 
                           required class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label for="contato" class="block text-sm font-medium text-slate-700 mb-2">Contato</label>
                    <input type="text" id="contato" name="contato" value="<?php echo isset($edit_data['contato']) ? htmlspecialchars($edit_data['contato']) : ''; ?>" 
                           class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                </div>
            </div>
            
            <div class="grid gap-5 grid-cols-1 sm:grid-cols-2">
                <div>
                    <label for="telefone" class="block text-sm font-medium text-slate-700 mb-2">Telefone</label>
                    <input type="text" id="telefone" name="telefone" value="<?php echo isset($edit_data['telefone']) ? htmlspecialchars($edit_data['telefone']) : ''; ?>" 
                           class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-2">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo isset($edit_data['email']) ? htmlspecialchars($edit_data['email']) : ''; ?>" 
                           class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                </div>
            </div>
            
            <div>
                <label for="site" class="block text-sm font-medium text-slate-700 mb-2">Site</label>
                <input type="url" id="site" name="site" value="<?php echo isset($edit_data['site']) ? htmlspecialchars($edit_data['site']) : ''; ?>" 
                       class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
            </div>
            
            <div>
                <label for="observacoes" class="block text-sm font-medium text-slate-700 mb-2">Observações</label>
                <textarea id="observacoes" name="observacoes" rows="3" 
                          class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500"><?php echo isset($edit_data['observacoes']) ? htmlspecialchars($edit_data['observacoes']) : ''; ?></textarea>
            </div>
            
        <?php elseif ($config['form_type'] === 'unidade'): ?>
            <!-- Formulário de Apresentação -->
            <div class="grid gap-5 grid-cols-1 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="nome" class="block text-sm font-medium text-slate-700 mb-2">
                        Nome <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="nome" name="nome" value="<?php echo isset($edit_data['nome']) ? htmlspecialchars($edit_data['nome']) : ''; ?>" 
                           required class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label for="sigla" class="block text-sm font-medium text-slate-700 mb-2">
                        Sigla <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="sigla" name="sigla" value="<?php echo isset($edit_data['sigla']) ? htmlspecialchars($edit_data['sigla']) : ''; ?>" 
                           required class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
                </div>
            </div>
            
            <div>
                <label for="descricao" class="block text-sm font-medium text-slate-700 mb-2">Descrição</label>
                <textarea id="descricao" name="descricao" rows="3" 
                          class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500"><?php echo isset($edit_data['descricao']) ? htmlspecialchars($edit_data['descricao']) : ''; ?></textarea>
            </div>
            
        <?php else: ?>
            <!-- Formulário Simples (Tipo e Categoria) -->
            <div>
                <label for="nome" class="block text-sm font-medium text-slate-700 mb-2">
                    Nome <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="nome" name="nome" value="<?php echo isset($edit_data['nome']) ? htmlspecialchars($edit_data['nome']) : ''; ?>" 
                       required class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500">
            </div>
            
            <div>
                <label for="descricao" class="block text-sm font-medium text-slate-700 mb-2">Descrição</label>
                <textarea id="descricao" name="descricao" rows="3" 
                          class="w-full rounded-2xl border border-slate-100 bg-white px-5 py-3 text-slate-700 shadow focus:border-primary-500 focus:ring-primary-500"><?php echo isset($edit_data['descricao']) ? htmlspecialchars($edit_data['descricao']) : ''; ?></textarea>
            </div>
        <?php endif; ?>
        
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
            <?php if ($edit_id > 0): ?>
                <a href="?tab=<?php echo $active_tab; ?>" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 sm:px-6 py-2.5 sm:py-3 text-slate-600 font-semibold shadow hover:shadow-lg transition text-sm sm:text-base">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Cancelar
                </a>
            <?php endif; ?>
            <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-4 sm:px-6 py-2.5 sm:py-3 text-white font-semibold shadow-glow hover:bg-primary-500 transition text-sm sm:text-base">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <?php echo $edit_id > 0 ? 'Atualizar' : 'Salvar'; ?>
            </button>
        </div>
    </form>
</div>

<!-- Tabela -->
<div class="glass-card p-0 overflow-hidden">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-0 px-4 sm:px-6 py-3 sm:py-4 border-b border-white/60 bg-white/70">
        <h2 class="text-base sm:text-lg font-semibold text-slate-900 flex items-center gap-2">
            <?php echo $config['icon']; ?>
            <span><?php echo $config['title']; ?>s Cadastrados</span>
        </h2>
        <span class="rounded-full bg-primary-50 px-3 sm:px-4 py-1 text-xs sm:text-sm font-medium text-primary-600 whitespace-nowrap">
            <?php echo count($config['data']); ?> registros
        </span>
    </div>
    
    <?php if (empty($config['data'])): ?>
        <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
            <p class="text-sm">Nenhum registro encontrado.</p>
        </div>
    <?php else: ?>
        <div class="responsive-table-wrapper">
            <table class="min-w-full divide-y divide-slate-100 text-left">
                <thead class="bg-white/60">
                    <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-4 sm:px-6 py-3">Nome</th>
                        <?php if ($config['form_type'] === 'fabricante'): ?>
                            <th class="px-4 sm:px-6 py-3">Contato</th>
                            <th class="px-4 sm:px-6 py-3">Telefone</th>
                            <th class="px-4 sm:px-6 py-3">Email</th>
                        <?php elseif ($config['form_type'] === 'unidade'): ?>
                            <th class="px-4 sm:px-6 py-3">Sigla</th>
                            <th class="px-4 sm:px-6 py-3">Descrição</th>
                        <?php else: ?>
                            <th class="px-4 sm:px-6 py-3">Descrição</th>
                        <?php endif; ?>
                        <th class="px-4 sm:px-6 py-3">Status</th>
                        <th class="px-4 sm:px-6 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white/80">
                    <?php foreach ($config['data'] as $item): ?>
                        <tr class="text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                            <td data-label="Nome" class="px-4 sm:px-6 py-3 sm:py-4 font-medium text-slate-900">
                                <?php echo htmlspecialchars($item['nome']); ?>
                            </td>
                            
                            <?php if ($config['form_type'] === 'fabricante'): ?>
                                <td data-label="Contato" class="px-4 sm:px-6 py-3 sm:py-4">
                                    <?php echo htmlspecialchars($item['contato'] ?? '-'); ?>
                                </td>
                                <td data-label="Telefone" class="px-4 sm:px-6 py-3 sm:py-4">
                                    <?php echo htmlspecialchars($item['telefone'] ?? '-'); ?>
                                </td>
                                <td data-label="Email" class="px-4 sm:px-6 py-3 sm:py-4">
                                    <?php echo htmlspecialchars($item['email'] ?? '-'); ?>
                                </td>
                            <?php elseif ($config['form_type'] === 'unidade'): ?>
                                <td data-label="Sigla" class="px-4 sm:px-6 py-3 sm:py-4">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                        <?php echo htmlspecialchars($item['sigla']); ?>
                                    </span>
                                </td>
                                <td data-label="Descrição" class="px-4 sm:px-6 py-3 sm:py-4">
                                    <?php echo htmlspecialchars($item['descricao'] ?? '-'); ?>
                                </td>
                            <?php else: ?>
                                <td data-label="Descrição" class="px-4 sm:px-6 py-3 sm:py-4">
                                    <?php echo htmlspecialchars($item['descricao'] ?? '-'); ?>
                                </td>
                            <?php endif; ?>
                            
                            <td data-label="Status" class="px-4 sm:px-6 py-3 sm:py-4">
                                <?php if ($item['ativo']): ?>
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Ativo
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Inativo
                                    </span>
                                <?php endif; ?>
                            </td>
                            
                            <td data-label="Ações" class="px-4 sm:px-6 py-3 sm:py-4">
                                <div class="flex items-center justify-start sm:justify-end gap-2">
                                    <a href="?tab=<?php echo $active_tab; ?>&edit=<?php echo $item['id']; ?>" class="action-chip" title="Editar">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    
                                    <form method="post" class="inline" onsubmit="return confirm('Tem certeza que deseja <?php echo $item['ativo'] ? 'desativar' : 'ativar'; ?> este registro?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="toggle_status_<?php echo $config['form_type']; ?>">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $item['ativo']; ?>">
                                        
                                        <button type="submit" class="action-chip <?php echo $item['ativo'] ? 'hover:bg-rose-50 hover:text-rose-600' : 'hover:bg-emerald-50 hover:text-emerald-600'; ?>" 
                                                title="<?php echo $item['ativo'] ? 'Desativar' : 'Ativar'; ?>">
                                            <?php if ($item['ativo']): ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                            <?php else: ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

