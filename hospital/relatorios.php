<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pageTitle = "Relatórios Hospital";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Farmácia Popular</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/admin_new.css">
</head>
<body class="admin-shell min-h-screen">
    
    <?php include '../admin/includes/sidebar.php'; ?>

    <main class="content-area">
        <div class="glass-card p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $pageTitle; ?></h1>
            <p class="text-gray-600 mb-6">Área de gerenciamento de relatórios do hospital</p>
            
            <div class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">Página em construção</h3>
                <p class="mt-2 text-sm text-gray-500">Esta página será desenvolvida no futuro para gerenciar os relatórios do hospital.</p>
            </div>
        </div>
    </main>

</body>
</html>

