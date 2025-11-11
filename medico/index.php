<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('medico');

$pageTitle = 'Painel do Médico';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Painel do médico - Farmácia de Laje">
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="Painel do médico - Farmácia de Laje">
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
    <style>
        .report-card {
            display: block;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .report-card .glass-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .report-card:hover .glass-card {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(79, 70, 229, 0.15);
        }
    </style>
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
                    <span class="text-xs sm:text-sm uppercase tracking-[0.2em] sm:tracking-[0.3em] text-slate-500">Painel Médico</span>
                    <div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900">Bem-vindo, <?php echo htmlspecialchars($_SESSION['user_nome'] ?? ''); ?></h1>
                        <p class="mt-2 text-sm sm:text-base text-slate-500 max-w-3xl">Consulte rapidamente os medicamentos disponíveis e o histórico dos pacientes para prescrever com segurança.</p>
                    </div>
                </div>
            </header>

            <section class="glass-card p-6 lg:p-8 space-y-8">
                <div class="space-y-2">
                    <h2 class="text-xl font-semibold text-slate-900">Acesso rápido</h2>
                    <p class="text-sm text-slate-500">Escolha uma das opções abaixo para iniciar sua consulta.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <a href="medicamentos.php" class="report-card">
                        <div class="glass-card h-full p-6 text-left hover:border-primary-200">
                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 9.75V4.875c0-.621-.504-1.125-1.125-1.125h-9.75c-.621 0-1.125.504-1.125 1.125v5.25M16.5 9.75H18c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125H6A1.125 1.125 0 0 1 4.875 19.5v-8.25c0-.621.504-1.125 1.125-1.125h1.5m9 0h-9"/></svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-slate-900">Medicamentos</h3>
                            <p class="mt-2 text-sm text-slate-500">Pesquise medicamentos pelo nome ou código e verifique o estoque disponível em tempo real.</p>
                        </div>
                    </a>
                    <a href="pacientes.php" class="report-card">
                        <div class="glass-card h-full p-6 text-left hover:border-primary-200">
                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0z"/></svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-slate-900">Pacientes</h3>
                            <p class="mt-2 text-sm text-slate-500">Localize pacientes pelo nome, CPF ou Cartão SUS e consulte rapidamente o histórico.</p>
                        </div>
                    </a>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
