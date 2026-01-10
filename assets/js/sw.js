/**
 * Service Worker for Static Wiki
 * Provides offline support and caching for better performance
 */

const CACHE_NAME = 'static-wiki-v1';
const OFFLINE_URL = '/offline.html';

// Assets to cache on install
const PRECACHE_ASSETS = [
  '/',
  '/assets/css/style.css',
  '/assets/js/main.js',
  '/assets/js/search.js'
];

// Cache strategies
const CACHE_STRATEGIES = {
  // Cache first, then network (for static assets)
  cacheFirst: async (request) => {
    const cached = await caches.match(request);
    if (cached) {
      return cached;
    }
    try {
      const response = await fetch(request);
      if (response.ok) {
        const cache = await caches.open(CACHE_NAME);
        cache.put(request, response.clone());
      }
      return response;
    } catch (error) {
      return new Response('Offline', { status: 503 });
    }
  },

  // Network first, then cache (for dynamic content)
  networkFirst: async (request) => {
    try {
      const response = await fetch(request);
      if (response.ok) {
        const cache = await caches.open(CACHE_NAME);
        cache.put(request, response.clone());
      }
      return response;
    } catch (error) {
      const cached = await caches.match(request);
      if (cached) {
        return cached;
      }
      // Return offline page for navigation requests
      if (request.mode === 'navigate') {
        return caches.match(OFFLINE_URL) || new Response('Offline', { status: 503 });
      }
      return new Response('Offline', { status: 503 });
    }
  },

  // Network only (for API requests that shouldn't be cached)
  networkOnly: async (request) => {
    return fetch(request);
  }
};

// Install event - cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[SW] Precaching assets');
        return cache.addAll(PRECACHE_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((name) => name !== CACHE_NAME)
            .map((name) => {
              console.log('[SW] Deleting old cache:', name);
              return caches.delete(name);
            })
        );
      })
      .then(() => self.clients.claim())
  );
});

// Fetch event - handle requests with appropriate strategy
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }

  // Skip cross-origin requests
  if (url.origin !== location.origin) {
    return;
  }

  // Choose strategy based on request type
  let strategy;

  // Search API - network only (results should be fresh)
  if (url.pathname.includes('search-api.php')) {
    strategy = CACHE_STRATEGIES.networkOnly;
  }
  // Static assets - cache first
  else if (
    url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)$/)
  ) {
    strategy = CACHE_STRATEGIES.cacheFirst;
  }
  // Wiki pages - network first (for fresh content)
  else {
    strategy = CACHE_STRATEGIES.networkFirst;
  }

  event.respondWith(strategy(request));
});

// Handle messages from the main thread
self.addEventListener('message', (event) => {
  if (event.data === 'skipWaiting') {
    self.skipWaiting();
  }

  if (event.data === 'clearCache') {
    caches.delete(CACHE_NAME).then(() => {
      console.log('[SW] Cache cleared');
    });
  }
});

// Background sync for offline search queries (if supported)
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-search') {
    console.log('[SW] Background sync triggered');
  }
});
