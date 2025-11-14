<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('medico');

$pageTitle = 'Consulta de Pacientes';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Consulta de pacientes - Farmácia de Laje">
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="Consulta de pacientes - Farmácia de Laje">
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
                    <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Consulta de Pacientes</span>
                    <div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900">Histórico de pacientes</h1>
                        <p class="mt-2 text-sm sm:text-base text-slate-500 max-w-3xl">Pesquise pacientes pelo nome, CPF ou Cartão SUS e acesse rapidamente o histórico de dispensações.</p>
                    </div>
                </div>
            </header>

            <section class="glass-card p-6 lg:p-8 space-y-6">
                <div class="grid gap-4 lg:grid-cols-2 lg:items-end">
                    <label class="flex flex-col gap-2">
                        <span class="text-sm font-medium text-slate-700">Buscar paciente</span>
                        <input type="search" id="pacienteSearch" placeholder="Digite o nome, CPF ou Cartão SUS" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 focus:border-primary-500 focus:ring-primary-500" autocomplete="off">
                        <span class="text-xs text-slate-400">Os 15 primeiros resultados são exibidos automaticamente.</span>
                    </label>
                    <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resultados</span>
                        <p class="mt-1 text-2xl font-bold text-slate-900" id="pacienteTotal">0</p>
                    </div>
                </div>

                <div id="pacientesFeedback" class="hidden rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700"></div>

                <div id="pacientesLista" class="space-y-3"></div>
            </section>
        </main>
    </div>

    <script>
        const pacienteInput = document.getElementById('pacienteSearch');
        const pacientesLista = document.getElementById('pacientesLista');
        const feedback = document.getElementById('pacientesFeedback');
        const totalPacientes = document.getElementById('pacienteTotal');
        let debounceTimer = null;

        function renderPacientes(pacientes) {
            pacientesLista.innerHTML = '';
            if (!pacientes || pacientes.length === 0) {
                feedback.classList.remove('hidden');
                feedback.textContent = 'Nenhum paciente encontrado. Tente refinar a busca.';
                totalPacientes.textContent = '0';
                return;
            }

            feedback.classList.add('hidden');
            totalPacientes.textContent = pacientes.length;
            const fragment = document.createDocumentFragment();

            pacientes.forEach((paciente) => {
                const card = document.createElement('div');
                card.className = 'glass-card p-5 flex flex-col gap-3 border border-white/70 cursor-pointer hover:border-primary-300 hover:shadow-md transition-all';
                card.onclick = () => {
                    window.location.href = `paciente_historico.php?id=${paciente.id}`;
                };
                card.innerHTML = `
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">${paciente.nome}</h3>
                            <div class="text-xs sm:text-sm text-slate-500 flex flex-col sm:flex-row sm:items-center sm:gap-4 mt-2">
                                <span>CPF: ${paciente.cpf ? paciente.cpf : '—'}</span>
                                <span>Cartão SUS: ${paciente.cartao_sus ? paciente.cartao_sus : '—'}</span>
                            </div>
                        </div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l2 2m6 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            Histórico
                        </div>
                    </div>
                `;
                fragment.appendChild(card);
            });

            pacientesLista.appendChild(fragment);
        }

        async function buscarPacientes(termo) {
            if (!termo || termo.length < 2) {
                pacientesLista.innerHTML = '';
                totalPacientes.textContent = '0';
                feedback.classList.remove('hidden');
                feedback.textContent = 'Digite pelo menos 2 caracteres para iniciar a busca.';
                return;
            }

            feedback.classList.add('hidden');
            pacientesLista.innerHTML = '<div class="text-sm text-slate-500">Buscando pacientes...</div>';

            try {
                const response = await fetch(`../admin/api/buscar_paciente.php?q=${encodeURIComponent(termo)}`);
                const data = await response.json();

                if (data.success) {
                    renderPacientes(data.pacientes || []);
                } else {
                    pacientesLista.innerHTML = '';
                    feedback.classList.remove('hidden');
                    feedback.textContent = data.message || 'Não foi possível recuperar os dados.';
                }
            } catch (error) {
                pacientesLista.innerHTML = '';
                feedback.classList.remove('hidden');
                feedback.textContent = 'Erro ao conectar com o servidor. Tente novamente.';
            }
        }

        pacienteInput.addEventListener('input', (event) => {
            const termo = event.target.value.trim();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => buscarPacientes(termo), 250);
        });

        pacienteInput.focus();
    </script>
</body>
</html>
