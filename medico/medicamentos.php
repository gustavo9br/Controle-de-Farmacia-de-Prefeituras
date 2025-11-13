<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('medico');

$pageTitle = 'Consulta de Medicamentos';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Consulta de medicamentos - Farmácia de Laje">
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="Consulta de medicamentos - Farmácia de Laje">
    <meta property="og:type" content="website">
    <meta property="og:image" content="../images/logo.svg">
    <link rel="icon" type="image/svg+xml" href="../images/logo.svg">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
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
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="../css/admin_new.css">
</head>
<body class="admin-shell">
    <button id="mobileMenuButton" class="mobile-menu-button" aria-label="Abrir menu">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div id="mobileMenuOverlay" class="mobile-menu-overlay"></div>

    <div class="flex min-h-screen">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-12 lg:py-10 space-y-6 lg:space-y-8">
            <header>
                <div class="space-y-2 sm:space-y-3">
                    <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Consulta de Medicamentos</span>
                    <div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900">Medicamentos disponíveis</h1>
                        <p class="mt-2 text-sm sm:text-base text-slate-500 max-w-3xl">Pesquise pelo nome ou código de barras para verificar rapidamente apresentações e estoque atual.</p>
                    </div>
                </div>
            </header>

            <section class="glass-card p-6 lg:p-8 space-y-6">
                <div class="grid gap-4 lg:grid-cols-2 lg:items-end">
                    <label class="flex flex-col gap-2">
                        <span class="text-sm font-medium text-slate-700">Buscar medicamento</span>
                        <input type="search" id="medicamentoSearch" placeholder="Digite parte do nome ou o código de barras" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" autocomplete="off">
                        <span class="text-xs text-slate-400">Os primeiros 10 resultados são exibidos automaticamente.</span>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resultados</span>
                            <p class="mt-1 text-2xl font-bold text-slate-900" id="resultadoTotal">0</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estoque somado</span>
                            <p class="mt-1 text-2xl font-bold text-slate-900" id="resultadoEstoque">0</p>
                        </div>
                    </div>
                </div>

                <div id="medicamentosFeedback" class="hidden rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700"></div>

                <div id="medicamentosLista" class="space-y-3"></div>
            </section>
        </main>
    </div>

    <script>
        const searchInput = document.getElementById('medicamentoSearch');
        const feedback = document.getElementById('medicamentosFeedback');
        const lista = document.getElementById('medicamentosLista');
        const totalEl = document.getElementById('resultadoTotal');
        const estoqueEl = document.getElementById('resultadoEstoque');
        let debounceTimer = null;

        function formatNumero(valor) {
            return new Intl.NumberFormat('pt-BR').format(valor);
        }

        function renderMedicamentos(medicamentos) {
            lista.innerHTML = '';
            if (!medicamentos || medicamentos.length === 0) {
                feedback.classList.remove('hidden');
                feedback.textContent = 'Nenhum medicamento encontrado. Tente refinar a busca.';
                totalEl.textContent = '0';
                estoqueEl.textContent = '0';
                return;
            }

            feedback.classList.add('hidden');
            let estoqueTotal = 0;
            const fragment = document.createDocumentFragment();

            medicamentos.forEach((item) => {
                estoqueTotal += parseInt(item.estoque_total ?? 0, 10);
                const card = document.createElement('div');
                card.className = 'glass-card p-5 flex flex-col gap-3 border border-white/70';
                card.innerHTML = `
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">${item.nome}</h3>
                            <p class="text-sm text-slate-500">${item.apresentacao ?? 'Sem descrição'}</p>
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-full ${item.estoque_total > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'} px-4 py-1 text-xs font-semibold">
                            Estoque: ${formatNumero(item.estoque_total ?? 0)}
                        </span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3 text-xs sm:text-sm text-slate-500">
                        <div><span class="font-semibold text-slate-600">Código:</span> ${item.codigo_barras ?? '—'}</div>
                        <div><span class="font-semibold text-slate-600">Estoque cadastrado:</span> ${formatNumero(item.estoque_atual ?? 0)}</div>
                    </div>
                `;
                fragment.appendChild(card);
            });

            totalEl.textContent = formatNumero(medicamentos.length);
            estoqueEl.textContent = formatNumero(estoqueTotal);
            lista.appendChild(fragment);
        }

        async function buscarMedicamentos(termo) {
            if (!termo || termo.length < 2) {
                lista.innerHTML = '';
                totalEl.textContent = '0';
                estoqueEl.textContent = '0';
                feedback.classList.remove('hidden');
                feedback.textContent = 'Digite pelo menos 2 caracteres para iniciar a busca.';
                return;
            }

            feedback.classList.add('hidden');
            lista.innerHTML = '<div class="text-sm text-slate-500">Buscando resultados...</div>';

            try {
                const response = await fetch(`../admin/api/buscar_medicamento.php?q=${encodeURIComponent(termo)}`);
                const data = await response.json();

                if (data.success) {
                    renderMedicamentos(data.medicamentos || []);
                } else {
                    lista.innerHTML = '';
                    feedback.classList.remove('hidden');
                    feedback.textContent = data.message || 'Não foi possível recuperar os dados.';
                }
            } catch (error) {
                lista.innerHTML = '';
                feedback.classList.remove('hidden');
                feedback.textContent = 'Erro ao conectar com o servidor. Tente novamente.';
            }
        }

        searchInput.addEventListener('input', (event) => {
            const termo = event.target.value.trim();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => buscarMedicamentos(termo), 250);
        });

        // Foco inicial e pequena busca se houver histórico no campo
        searchInput.focus();
        if (searchInput.value.trim().length >= 2) {
            buscarMedicamentos(searchInput.value.trim());
        }
    </script>
</body>
</html>
