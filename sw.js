// Service Worker untuk Sistem Absensi PPMA
const CACHE_NAME = 'absensi-ppma-v1.0.2';
const API_CACHE_NAME = 'absensi-ppma-api-v1.0.2';

// Assets yang akan di-cache saat install
const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/pages/dashboard.php',
  '/assets/css/style.css',
  '/assets/img/Logo_PP_Matholi_Anwar.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css',
  'https://code.jquery.com/jquery-3.6.0.min.js',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/chart.js'
];

// Install event - Cache static assets
self.addEventListener('install', (event) => {
  console.log('🔄 Service Worker: Installing...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('📦 Service Worker: Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => {
        console.log('✅ Service Worker: Installed successfully');
        return self.skipWaiting();
      })
      .catch((error) => {
        console.error('❌ Service Worker: Installation failed', error);
      })
  );
});

// Activate event - Clean up old caches
self.addEventListener('activate', (event) => {
  console.log('🔄 Service Worker: Activating...');
  
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME && cacheName !== API_CACHE_NAME) {
            console.log('🧹 Service Worker: Deleting old cache', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('✅ Service Worker: Activated successfully');
      return self.clients.claim();
    })
  );
});

// Fetch event - Smart caching strategy
self.addEventListener('fetch', (event) => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') return;

  // Skip Chrome extensions
  if (event.request.url.includes('chrome-extension')) return;

  // Handle API requests differently
  if (event.request.url.includes('/includes/') || 
      event.request.url.includes('/pages/') ||
      event.request.url.includes('action=')) {
    // Network First for API calls
    event.respondWith(networkFirstStrategy(event.request));
  } else {
    // Cache First for static assets
    event.respondWith(cacheFirstStrategy(event.request));
  }
});

// Network First Strategy for API calls
async function networkFirstStrategy(request) {
  try {
    // Try network first
    const networkResponse = await fetch(request);
    
    // If successful, cache the response
    if (networkResponse && networkResponse.status === 200) {
      const cache = await caches.open(API_CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    // If network fails, try cache
    console.log('🌐 Network failed, trying cache...', error);
    const cachedResponse = await caches.match(request);
    return cachedResponse || generateOfflineResponse(request);
  }
}

// Cache First Strategy for static assets
async function cacheFirstStrategy(request) {
  const cachedResponse = await caches.match(request);
  
  if (cachedResponse) {
    // Return cached version but update cache in background
    updateCacheInBackground(request);
    return cachedResponse;
  }
  
  try {
    // If not in cache, try network
    const networkResponse = await fetch(request);
    
    if (networkResponse && networkResponse.status === 200) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    // If both fail, return offline page for navigation requests
    if (request.destination === 'document') {
      return generateOfflineResponse(request);
    }
    
    throw error;
  }
}

// Update cache in background
async function updateCacheInBackground(request) {
  try {
    const networkResponse = await fetch(request);
    if (networkResponse && networkResponse.status === 200) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
  } catch (error) {
    // Silent fail - we have cached version already
  }
}

// Generate offline response
function generateOfflineResponse(request) {
  if (request.destination === 'document') {
    return caches.match('/offline.html')
      .then((cachedResponse) => {
        return cachedResponse || new Response(
          `
          <!DOCTYPE html>
          <html>
          <head>
            <title>Offline - Absensi PPMA</title>
            <style>
              body { 
                font-family: Arial, sans-serif; 
                background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
                height: 100vh; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                color: white;
                text-align: center;
              }
              .container { 
                background: rgba(255,255,255,0.1); 
                padding: 2rem; 
                border-radius: 10px; 
                backdrop-filter: blur(10px);
              }
            </style>
          </head>
          <body>
            <div class="container">
              <h1>📶 Offline</h1>
              <p>Aplikasi sedang offline. Beberapa fitur mungkin tidak tersedia.</p>
              <button onclick="location.reload()">Coba Lagi</button>
            </div>
          </body>
          </html>
          `,
          { 
            headers: { 'Content-Type': 'text/html' } 
          }
        );
      });
  }
  
  // For other requests, return appropriate offline response
  return new Response('Offline', { 
    status: 503,
    statusText: 'Service Unavailable' 
  });
}

// Background Sync for form submissions
self.addEventListener('sync', (event) => {
  if (event.tag === 'background-sync-absensi') {
    console.log('🔄 Background sync triggered for absensi');
    event.waitUntil(processPendingSubmissions());
  }
});

// Process pending form submissions when online
async function processPendingSubmissions() {
  // Implementation for background sync
  // This would process any pending form submissions
  console.log('Processing pending submissions...');
}

// Push notifications (optional)
self.addEventListener('push', (event) => {
  if (!event.data) return;
  
  const data = event.data.json();
  const options = {
    body: data.body,
    icon: '/assets/icons/icon-192x192.png',
    badge: '/assets/icons/icon-72x72.png',
    vibrate: [100, 50, 100],
    data: {
      url: data.url || '/'
    }
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow(event.notification.data.url)
  );
});