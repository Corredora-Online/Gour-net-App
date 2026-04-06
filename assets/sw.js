'use strict';

const CACHE       = 'gournet-v2';
const OFFLINE_URL = self.location.origin + '/?gd-offline=1';

/* ── Install: pre-cache offline page ── */
self.addEventListener( 'install', event => {
    event.waitUntil(
        caches.open( CACHE )
            .then( c => c.add( OFFLINE_URL ) )
            .then( () => self.skipWaiting() )
    );
} );

/* ── Activate: limpiar caches antiguas ── */
self.addEventListener( 'activate', event => {
    event.waitUntil(
        caches.keys()
            .then( keys => Promise.all(
                keys.filter( k => k !== CACHE ).map( k => caches.delete( k ) )
            ) )
            .then( () => self.clients.claim() )
    );
} );

/* ── Fetch ── */
self.addEventListener( 'fetch', event => {
    const { request } = event;
    if ( request.method !== 'GET' ) return;

    const url = new URL( request.url );

    /* Nunca interceptar AJAX ni endpoints especiales del plugin */
    if ( url.pathname.includes( 'admin-ajax.php' ) ) return;
    if ( url.searchParams.has( 'gd-sw' ) )           return;
    if ( url.searchParams.has( 'gd-manifest' ) )     return;

    /* Navegación: network-first con fallback offline */
    if ( request.mode === 'navigate' ) {
        event.respondWith(
            fetch( request )
                .then( res => {
                    const clone = res.clone();
                    caches.open( CACHE ).then( c => c.put( request, clone ) );
                    return res;
                } )
                .catch( () =>
                    caches.match( request )
                        .then( cached => cached || caches.match( OFFLINE_URL ) )
                )
        );
        return;
    }

    /* Assets estáticos (CSS/JS/imágenes): cache-first */
    if ( /\.(css|js|png|jpe?g|svg|woff2?|ico|webp)$/i.test( url.pathname ) ) {
        event.respondWith(
            caches.match( request ).then( cached => {
                if ( cached ) return cached;
                return fetch( request ).then( res => {
                    if ( res.ok ) {
                        const clone = res.clone();
                        caches.open( CACHE ).then( c => c.put( request, clone ) );
                    }
                    return res;
                } );
            } )
        );
    }
} );
