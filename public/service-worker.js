// Service Worker для Muzic2 PWA
const CACHE_NAME = 'muzic2-v1';
const RUNTIME_CACHE = 'muzic2-runtime-v1';

// Ресурсы для кэширования при установке
const PRECACHE_URLS = [
  '/muzic2/public/index.php',
  '/muzic2/public/assets/css/style.css',
  '/muzic2/public/assets/js/app.js',
  '/muzic2/public/assets/js/player.js',
  '/muzic2/public/assets/img/playlist-placeholder.png',
  '/muzic2/public/manifest.json'
];

// Установка Service Worker
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Precaching static assets');
        return cache.addAll(PRECACHE_URLS.map(url => new Request(url, { cache: 'reload' })));
      })
      .then(() => self.skipWaiting())
  );
});

// Активация Service Worker
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activating...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME && cacheName !== RUNTIME_CACHE) {
            console.log('[Service Worker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
    .then(() => self.clients.claim())
  );
});

// Стратегия кэширования: Network First, Fallback to Cache
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Пропускаем запросы к API и медиа-файлам (они должны быть свежими)
  if (url.pathname.includes('/src/api/') || 
      url.pathname.includes('/tracks/') ||
      url.pathname.includes('.mp3') ||
      url.pathname.includes('.m4a') ||
      url.pathname.includes('.wav') ||
      url.pathname.includes('.mp4')) {
    // Для API и медиа - только сеть, без кэша
    return;
  }

  // Для статических ресурсов - Network First
  event.respondWith(
    fetch(request)
      .then((response) => {
        // Клонируем ответ для кэширования
        const responseToCache = response.clone();
        
        // Кэшируем успешные ответы
        if (response.status === 200) {
          caches.open(RUNTIME_CACHE).then((cache) => {
            cache.put(request, responseToCache);
          });
        }
        
        return response;
      })
      .catch(() => {
        // Если сеть недоступна, используем кэш
        return caches.match(request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          
          // Если нет в кэше, возвращаем оффлайн страницу для HTML
          if (request.headers.get('accept').includes('text/html')) {
            return caches.match('/muzic2/public/index.php');
          }
          
          // Для других ресурсов возвращаем пустой ответ
          return new Response('Offline', { status: 503 });
        });
      })
  );
});

// Обработка сообщений от клиента
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'CACHE_URLS') {
    event.waitUntil(
      caches.open(RUNTIME_CACHE).then((cache) => {
        return cache.addAll(event.data.urls);
      })
    );
  }
});

// Фоновый синхронизация (для будущего использования)
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-likes') {
    event.waitUntil(syncLikes());
  }
});

async function syncLikes() {
  // Здесь можно добавить синхронизацию лайков при восстановлении соединения
  console.log('[Service Worker] Syncing likes...');
}







