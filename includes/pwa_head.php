<?php
// Determinar o caminho base baseado na localização do arquivo
$isSubDir = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || 
             strpos($_SERVER['PHP_SELF'], '/usuario/') !== false || 
             strpos($_SERVER['PHP_SELF'], '/medico/') !== false ||
             strpos($_SERVER['PHP_SELF'], '/hospital/') !== false);
$manifestPath = $isSubDir ? '../manifest.json' : 'manifest.json';
$swPath = $isSubDir ? '../sw.js' : 'sw.js';
$iconPath = $isSubDir ? '../images/logo.png' : 'images/logo.png';
?>
<!-- PWA Manifest -->
<link rel="manifest" href="<?php echo $manifestPath; ?>">
<meta name="theme-color" content="#4f46e5">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Farmácia">
<link rel="apple-touch-icon" href="<?php echo $iconPath; ?>">

<!-- PWA Service Worker -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const swPath = '<?php echo $swPath; ?>';
        
        navigator.serviceWorker.register(swPath)
            .then((registration) => {
                console.log('Service Worker registrado com sucesso:', registration.scope);
                
                // Verificar atualizações periodicamente
                setInterval(() => {
                    registration.update();
                }, 60000); // A cada 1 minuto
            })
            .catch((error) => {
                console.log('Erro ao registrar Service Worker:', error);
            });
    });
    
    // Detectar quando há uma nova versão do service worker
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        window.location.reload();
    });
}
</script>
