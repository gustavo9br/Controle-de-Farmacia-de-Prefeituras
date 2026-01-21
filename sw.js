// Service Worker para PWA
const CACHE_NAME = 'farmacia-v1';
const RUNTIME_CACHE = 'farmacia-runtime-v1';

// Arquivos estáticos para cachear
const STATIC_ASSETS = [
  '/',
  '/login.php',
  '/css/admin_new.css',
  '/images/logo.png',
  '/images/logo.svg',
  'https://cdn.tailwindcss.com'
];

// Instalar Service Worker
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Instalando...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Cacheando arquivos estáticos');
        return cache.addAll(STATIC_ASSETS.filter(url => !url.startsWith('http')));
      })
      .then(() => self.skipWaiting())
  );
});

// Ativar Service Worker
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Ativando...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME && cacheName !== RUNTIME_CACHE) {
            console.log('[Service Worker] Removendo cache antigo:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Interceptar requisições
self.addEventListener('fetch', (event) => {
  // Ignorar requisições que não são GET
  if (event.request.method !== 'GET') {
    return;
  }

  const requestUrl = new URL(event.request.url);

  // Ignorar requisições de extensões do navegador (chrome-extension, moz-extension, etc)
  if (requestUrl.protocol === 'chrome-extension:' || 
      requestUrl.protocol === 'moz-extension:' || 
      requestUrl.protocol === 'safari-extension:' ||
      requestUrl.protocol === 'ms-browser-extension:') {
    return;
  }

  // Ignorar requisições que não são HTTP/HTTPS
  if (requestUrl.protocol !== 'http:' && requestUrl.protocol !== 'https:') {
    return;
  }

  // Ignorar requisições de API (sempre buscar do servidor)
  if (event.request.url.includes('/api/')) {
    return;
  }

  // Estratégia: Network First, fallback para cache
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // Clonar a resposta
        const responseToCache = response.clone();

        // Cachear apenas respostas válidas e que sejam do mesmo origin
        // (evita tentar fazer cache de recursos de outros domínios ou extensões)
        if (response.status === 200 && 
            response.type === 'basic' && 
            requestUrl.protocol.startsWith('http')) {
          try {
            caches.open(RUNTIME_CACHE).then((cache) => {
              cache.put(event.request, responseToCache).catch((error) => {
                // Ignorar erros de cache silenciosamente (ex: extensões do Chrome)
                console.warn('[Service Worker] Erro ao fazer cache:', error.message);
              });
            });
          } catch (error) {
            // Ignorar erros de cache silenciosamente
            console.warn('[Service Worker] Erro ao abrir cache:', error.message);
          }
        }

        return response;
      })
      .catch(() => {
        // Se a rede falhar, tentar buscar do cache
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }

          // Se não houver no cache, retornar página offline
          if (event.request.destination === 'document') {
            return caches.match('/offline.html');
          }
        });
      })
  );
});

// Notificações push (para uso futuro)
self.addEventListener('push', (event) => {
  const options = {
    body: event.data ? event.data.text() : 'Nova notificação',
    icon: '/images/logo.png',
    badge: '/images/logo.png',
    vibrate: [200, 100, 200],
    tag: 'notification'
  };

  event.waitUntil(
    self.registration.showNotification('Sistema de Farmácia', options)
  );
});

// Clique em notificação
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow('/')
  );
});

