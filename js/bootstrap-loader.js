// Verificar se o Bootstrap já está carregado
if (typeof bootstrap === 'undefined') {
    // Carregar o Bootstrap Bundle (com Popper.js incluído)
    const bootstrapScript = document.createElement('script');
    bootstrapScript.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
    bootstrapScript.integrity = 'sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz';
    bootstrapScript.crossOrigin = 'anonymous';
    document.body.appendChild(bootstrapScript);
    
    // Consertar o dropdown mobile quando o script carregar
    bootstrapScript.onload = function() {
        // Ativar todos os dropdowns
        document.querySelectorAll('.dropdown-toggle').forEach(function(dropdownToggle) {
            new bootstrap.Dropdown(dropdownToggle);
        });
        
        // Consertar o menu hambúrguer para dispositivos móveis
        document.querySelectorAll('.navbar-toggler').forEach(function(toggler) {
            toggler.addEventListener('click', function() {
                const target = document.querySelector(this.dataset.bsTarget || this.getAttribute('data-bs-target'));
                if (target) {
                    if (target.classList.contains('show')) {
                        target.classList.remove('show');
                    } else {
                        target.classList.add('show');
                    }
                }
            });
        });
    };
}
