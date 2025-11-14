<?php
// Determinar o caminho base baseado na localização do arquivo
$isSubDir = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || 
             strpos($_SERVER['PHP_SELF'], '/usuario/') !== false || 
             strpos($_SERVER['PHP_SELF'], '/medico/') !== false ||
             strpos($_SERVER['PHP_SELF'], '/hospital/') !== false);
$imagePath = $isSubDir ? 'https://farmacia.laje.app/images/logo.png' : 'https://farmacia.laje.app/images/logo.png';
$currentUrl = 'https://farmacia.laje.app' . $_SERVER['REQUEST_URI'];

// Título padrão se não for definido
$ogTitle = isset($ogTitle) ? $ogTitle : (isset($pageTitle) ? $pageTitle . ' - Gov Farma' : 'Gov Farma');
$ogDescription = isset($ogDescription) ? $ogDescription : 'Gov Farma - Sistema de gestão de farmácia pública. Controle completo de medicamentos, pacientes, receitas e dispensação.';
?>
<!-- Open Graph -->
<meta property="og:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo htmlspecialchars($currentUrl); ?>">
<meta property="og:image" content="<?php echo $imagePath; ?>">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="512">
<meta property="og:image:height" content="512">
<meta property="og:site_name" content="Gov Farma">
<meta property="og:locale" content="pt_BR">

<!-- Twitter / WhatsApp -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
<meta name="twitter:image" content="<?php echo $imagePath; ?>">

