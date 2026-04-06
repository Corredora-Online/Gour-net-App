/* ============================================================
   Gournet Dashboard – Frontend Logic
   ============================================================ */
( function () {
    'use strict';

    /* ── Bail si no estamos en el dashboard autenticado ── */
    if ( ! document.getElementById( 'gd-refresh' ) ) return;

    /* ── State ── */
    let allLocales    = [];
    let activeLocal   = null;   // LOCAL id string, or '__all__'
    let hourlyChart   = null;
    let compareChart  = null;
    let refreshTimer  = null;

    /* ── DOM refs ── */
    const app       = document.getElementById( 'gournet-app' );
    const loading   = document.getElementById( 'gd-loading' );
    const error     = document.getElementById( 'gd-error' );
    const errorMsg  = document.getElementById( 'gd-error-msg' );
    const content   = document.getElementById( 'gd-content' );
    const tabs      = document.getElementById( 'gd-tabs' );
    const kpis      = document.getElementById( 'gd-kpis' );
    const tableBody = document.getElementById( 'gd-table-body' );
    const lastUpd   = document.getElementById( 'gd-last-update' );
    const refreshBtn = document.getElementById( 'gd-refresh' );
    const retryBtn   = document.getElementById( 'gd-retry' );
    const themeBtn   = document.getElementById( 'gd-theme-toggle' );
    const userBtn    = document.getElementById( 'gd-user-btn' );
    const dropdown   = document.getElementById( 'gd-dropdown' );
    const chartName = document.getElementById( 'gd-chart-branch-name' );
    const legendEl  = document.getElementById( 'gd-hourly-legend' );

    /* ── Helpers ── */
    const CLP = new Intl.NumberFormat( 'es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 } );

    function formatCLP( n ) { return CLP.format( n ); }

    function pct( a, b ) {
        if ( ! b || b === 0 ) return null;
        return ( ( a - b ) / b * 100 ).toFixed( 1 );
    }

    function hourLabel( h ) {
        return String( h ).padStart( 2, '0' ) + ':00';
    }

    function labelDiaAnterior() {
        const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        return dias[ new Date().getDay() ] + ' pasado';
    }

    function peakHour( loc ) {
        let max = -1, peak = null;
        for ( let h = 0; h < 24; h++ ) {
            const key = 'H_' + String( h ).padStart( 2, '0' );
            if ( loc[ key ] > max ) { max = loc[ key ]; peak = h; }
        }
        return peak !== null ? hourLabel( peak ) : '—';
    }

    function hourlyArray( loc ) {
        const arr = [];
        for ( let h = 0; h < 24; h++ ) {
            arr.push( loc[ 'H_' + String( h ).padStart( 2, '0' ) ] || 0 );
        }
        return arr;
    }

    function totalFromHours( loc ) {
        return hourlyArray( loc ).reduce( ( s, v ) => s + v, 0 );
    }

    function trimName( name ) {
        return ( name || '' ).trim();
    }

    function variationBadge( a, b ) {
        const p = pct( a, b );
        if ( p === null ) return '<span class="gd-badge-var gd-badge-var--neutral">Sin dato</span>';
        const sign = p >= 0 ? '+' : '';
        const cls  = p >= 0 ? 'up' : 'down';
        const arrow= p >= 0 ? '▲' : '▼';
        return `<span class="gd-badge-var gd-badge-var--${ cls }">${ arrow } ${ sign }${ p }%</span>`;
    }

    /* ── Theme ── */
    let currentTheme = localStorage.getItem( 'gd-theme' ) || 'dark';

    const MOON_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
    const SUN_SVG  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';

    function chartColors() {
        return currentTheme === 'light'
            ? { tick: '#64748b', grid: 'rgba(0,0,0,.06)', bgTooltip: '#ffffff', borderTooltip: '#dde3ed', titleTooltip: '#1a202c', bodyTooltip: '#64748b', antBg: 'rgba(0,0,0,.08)', antBorder: 'rgba(0,0,0,.25)' }
            : { tick: '#8892a4', grid: 'rgba(255,255,255,.05)', bgTooltip: '#1a1d27', borderTooltip: '#2a2d3e', titleTooltip: '#e2e8f0', bodyTooltip: '#8892a4', antBg: 'rgba(255,255,255,.1)', antBorder: 'rgba(255,255,255,.3)' };
    }

    function applyTheme( theme ) {
        currentTheme = theme;
        app.dataset.theme = theme;
        localStorage.setItem( 'gd-theme', theme );
        const isDark   = theme === 'dark';
        const iconEl   = document.getElementById( 'gd-theme-icon' );
        const labelEl  = document.getElementById( 'gd-theme-label' );
        if ( iconEl )  iconEl.innerHTML    = isDark ? SUN_SVG : MOON_SVG;
        if ( labelEl ) labelEl.textContent = isDark ? 'Modo claro' : 'Modo oscuro';
    }

    /* ── Dropdown toggle ── */
    function openDropdown() {
        dropdown.hidden = false;
        userBtn.setAttribute( 'aria-expanded', 'true' );
    }
    function closeDropdown() {
        dropdown.hidden = true;
        userBtn.setAttribute( 'aria-expanded', 'false' );
    }

    userBtn.addEventListener( 'click', ( e ) => {
        e.stopPropagation();
        dropdown.hidden ? openDropdown() : closeDropdown();
    } );

    document.addEventListener( 'click', ( e ) => {
        if ( ! document.getElementById( 'gd-user-menu' ).contains( e.target ) ) {
            closeDropdown();
        }
    } );

    document.addEventListener( 'keydown', ( e ) => {
        if ( e.key === 'Escape' ) closeDropdown();
    } );

    themeBtn.addEventListener( 'click', () => {
        applyTheme( currentTheme === 'dark' ? 'light' : 'dark' );
        closeDropdown();
        if ( allLocales.length ) {
            renderHourlyChart();
            renderCompareChart();
        }
    } );

    /* ── Colour palette — brand colors gour-net.cl ── */
    const PALETTE = [
        '#EA529F','#554CFA','#4EB8BA','#FAC632',
        '#f07fbe','#8179DD','#34d399','#a09af7',
        '#d4428f','#06b6d4',
    ];
    function color( i ) { return PALETTE[ i % PALETTE.length ]; }

    /* ── Show / hide states ── */
    function showLoading()  { loading.hidden = false; error.hidden = true; content.hidden = true; }
    function showError( msg){ loading.hidden = true; error.hidden = false; content.hidden = true; errorMsg.textContent = msg; }
    function showContent()  { loading.hidden = true; error.hidden = true; content.hidden = false; }

    /* ── Fetch data from WordPress AJAX proxy ── */
    function fetchData() {
        refreshBtn.classList.add( 'is-spinning' );

        const body = new URLSearchParams( {
            action: 'gournet_fetch_data',
            nonce:  GournetConfig.nonce,
        } );

        fetch( GournetConfig.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body,
        } )
        .then( r => r.json() )
        .then( json => {
            if ( ! json.success ) throw new Error( json.data?.message || 'Error desconocido' );
            // payload may be: { data: [...] }, [ { data: [...] } ], or [...]
            const raw = json.data;
            const locales = Array.isArray( raw ) && raw[0]?.data ? raw[0].data
                          : Array.isArray( raw )                  ? raw
                          : Array.isArray( raw?.data )            ? raw.data
                          : raw;
            if ( ! Array.isArray( locales ) || locales.length === 0 ) throw new Error( 'Sin datos disponibles' );
            allLocales = locales;
            render();
        } )
        .catch( err => {
            showError( err.message || 'No se pudo conectar con el servidor' );
        } )
        .finally( () => {
            refreshBtn.classList.remove( 'is-spinning' );
        } );
    }

    /* ── Render everything ── */
    function render() {
        renderTabs();
        renderKPIs();
        renderHourlyChart();
        renderCompareChart();
        renderTable();
        showContent();

        const now = new Date();
        lastUpd.textContent = 'Actualizado: ' + now.toLocaleTimeString( 'es-CL', { hour: '2-digit', minute: '2-digit' } );
    }

    /* ── Tabs ── */
    function renderTabs() {
        tabs.innerHTML = '';

        // "Todos" tab
        const allTab = makeTab( '__all__', 'Todos los locales', true );
        tabs.appendChild( allTab );

        allLocales.forEach( loc => {
            const tab = makeTab( loc.LOCAL.trim(), trimName( loc.NOMBRE ), false );
            tabs.appendChild( tab );
        } );

        // Select first branch by default (not "Todos") on first load
        if ( ! activeLocal ) {
            activeLocal = allLocales[0]?.LOCAL.trim() || '__all__';
        }

        updateTabSelection();
    }

    function makeTab( id, label, isAll ) {
        const btn = document.createElement( 'button' );
        btn.className     = 'gd-tab' + ( isAll ? ' gd-tab--all' : '' );
        btn.textContent   = label;
        btn.dataset.local = id;
        btn.setAttribute( 'role', 'tab' );
        btn.setAttribute( 'aria-selected', 'false' );
        btn.addEventListener( 'click', () => {
            activeLocal = id;
            updateTabSelection();
            renderKPIs();
            renderHourlyChart();
        } );
        return btn;
    }

    function updateTabSelection() {
        tabs.querySelectorAll( '.gd-tab' ).forEach( btn => {
            const sel = btn.dataset.local === activeLocal;
            btn.setAttribute( 'aria-selected', sel ? 'true' : 'false' );
        } );
    }

    /* ── KPI Cards ── */
    function renderKPIs() {
        kpis.innerHTML = '';

        const isAll     = activeLocal === '__all__';
        const locales   = isAll ? allLocales : allLocales.filter( l => l.LOCAL.trim() === activeLocal );
        const totalAct  = locales.reduce( ( s, l ) => s + ( l.VTADIAACT || 0 ), 0 );
        const totalAnt  = locales.reduce( ( s, l ) => s + ( l.VTADIAANT || 0 ), 0 );
        const variacion = pct( totalAct, totalAnt );
        const numLocales= locales.length;

        // Best hour (aggregate)
        const hourSums = Array( 24 ).fill( 0 );
        locales.forEach( loc => {
            for ( let h = 0; h < 24; h++ ) {
                hourSums[ h ] += loc[ 'H_' + String( h ).padStart( 2, '0' ) ] || 0;
            }
        } );
        const peakHourIdx   = hourSums.indexOf( Math.max( ...hourSums ) );
        const peakHourValue = hourSums[ peakHourIdx ];

        // Average ticket size (VTADIAACT / locales)
        const promedio = numLocales > 1 ? Math.round( totalAct / numLocales ) : null;

        // Build cards
        const cards = [
            {
                icon:   '📈',
                label:  'Venta hoy',
                value:  formatCLP( totalAct ),
                sub:    isAll ? `${ numLocales } locales en total` : trimName( locales[0]?.NOMBRE ),
                accent: '#EA529F',
                badge:  variacion !== null ? variacionBadgeKPI( totalAct, totalAnt ) : null,
            },
            {
                icon:   '📅',
                label:  labelDiaAnterior(),
                value:  totalAnt > 0 ? formatCLP( totalAnt ) : 'Sin dato',
                sub:    totalAnt > 0 ? 'Mismo período' : 'No disponible para comparar',
                accent: '#554CFA',
            },
            {
                icon:   '⚡',
                label:  'Hora pico',
                value:  hourLabel( peakHourIdx ),
                sub:    peakHourValue > 0 ? formatCLP( peakHourValue ) + ' vendidos' : 'Sin ventas en esta hora',
                accent: '#FAC632',
            },
        ];

        if ( promedio ) {
            cards.push( {
                icon:   '🏪',
                label:  'Promedio por local',
                value:  formatCLP( promedio ),
                sub:    'Promedio de venta diaria',
                accent: '#4EB8BA',
            } );
        }

        if ( isAll ) {
            const leader = [ ...allLocales ].sort( ( a, b ) => b.VTADIAACT - a.VTADIAACT )[0];
            cards.push( {
                icon:   '🏆',
                label:  'Local líder',
                value:  trimName( leader?.NOMBRE ) || '—',
                sub:    leader ? formatCLP( leader.VTADIAACT ) : '',
                accent: '#8179DD',
            } );
        }

        cards.forEach( ( c, i ) => {
            const el = document.createElement( 'div' );
            el.className = 'gd-kpi';
            el.style.setProperty( '--kpi-accent', c.accent );
            el.innerHTML = `
                <span class="gd-kpi__icon">${ c.icon }</span>
                <div class="gd-kpi__label">${ c.label }</div>
                <div class="gd-kpi__value">${ c.value }</div>
                <div class="gd-kpi__sub">
                    ${ c.badge ? c.badge + '<span>' : '' }
                    ${ c.sub }
                    ${ c.badge ? '</span>' : '' }
                </div>
            `;
            kpis.appendChild( el );
        } );
    }

    function variacionBadgeKPI( a, b ) {
        const p = pct( a, b );
        if ( p === null ) return '';
        const sign = p >= 0 ? '+' : '';
        const cls  = p >= 0 ? 'up' : 'down';
        const arrow= p >= 0 ? '▲' : '▼';
        return `<span class="gd-kpi__badge gd-kpi__badge--${ cls }">${ arrow } ${ sign }${ p }%</span>`;
    }

    /* ── Hourly chart ── */
    function renderHourlyChart() {
        const ctx = document.getElementById( 'gd-chart-hourly' ).getContext( '2d' );
        const isAll = activeLocal === '__all__';
        const labels = Array.from( { length: 24 }, ( _, i ) => hourLabel( i ) );

        let datasets = [];

        if ( isAll ) {
            // Show all locales as separate lines
            datasets = allLocales.map( ( loc, i ) => ( {
                label:           trimName( loc.NOMBRE ),
                data:            hourlyArray( loc ),
                borderColor:     color( i ),
                backgroundColor: color( i ) + '22',
                borderWidth:     2,
                pointRadius:     3,
                pointHoverRadius:6,
                tension:         0.4,
                fill:            false,
            } ) );
            chartName.textContent = 'Todos los locales';
        } else {
            const loc = allLocales.find( l => l.LOCAL.trim() === activeLocal );
            if ( ! loc ) return;
            datasets = [
                {
                    label:           'Venta hoy',
                    data:            hourlyArray( loc ),
                    borderColor:     '#EA529F',
                    backgroundColor: 'rgba(234,82,159,.15)',
                    borderWidth:     2.5,
                    pointRadius:     4,
                    pointHoverRadius:7,
                    tension:         0.4,
                    fill:            true,
                },
            ];
            chartName.textContent = trimName( loc.NOMBRE );
        }

        if ( hourlyChart ) hourlyChart.destroy();

        hourlyChart = new Chart( ctx, {
            type: 'line',
            data: { labels, datasets },
            options: chartOptions( {
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + formatCLP( ctx.raw ),
                    },
                },
            } ),
        } );

        // Legend
        legendEl.innerHTML = '';
        if ( ! isAll ) {
            legendEl.innerHTML = `
                <div class="gd-legend-item">
                    <span class="gd-legend-dot" style="background:#ff6b35"></span>
                    Venta por hora (hoy)
                </div>
            `;
        }
    }

    /* ── Comparison chart ── */
    function renderCompareChart() {
        const ctx = document.getElementById( 'gd-chart-compare' ).getContext( '2d' );

        const sorted   = [ ...allLocales ].sort( ( a, b ) => b.VTADIAACT - a.VTADIAACT );
        const labels   = sorted.map( l => trimName( l.NOMBRE ) );
        const dataAct  = sorted.map( l => l.VTADIAACT );
        const dataAnt  = sorted.map( l => l.VTADIAANT );
        const hasAnt   = dataAnt.some( v => v > 0 );

        const datasets = [
            {
                label:           'Venta hoy',
                data:            dataAct,
                backgroundColor: sorted.map( ( _, i ) => color( i ) + 'cc' ),
                borderColor:     sorted.map( ( _, i ) => color( i ) ),
                borderWidth:     1,
                borderRadius:    6,
            },
        ];

        if ( hasAnt ) {
            const c = chartColors();
            datasets.push( {
                label:           labelDiaAnterior(),
                data:            dataAnt,
                backgroundColor: c.antBg,
                borderColor:     c.antBorder,
                borderWidth:     1,
                borderRadius:    6,
                borderDash:      [4,4],
            } );
        }

        if ( compareChart ) compareChart.destroy();

        compareChart = new Chart( ctx, {
            type: 'bar',
            data: { labels, datasets },
            options: chartOptions( {
                indexAxis: 'x',
                scales: {
                    x: {
                        ticks: { color: '#8892a4', maxRotation: 35, font: { size: 11 } },
                        grid:  { color: 'rgba(255,255,255,.05)' },
                    },
                    y: {
                        ticks: {
                            color:    '#8892a4',
                            callback: v => formatCLP( v ),
                            font:     { size: 11 },
                        },
                        grid: { color: 'rgba(255,255,255,.05)' },
                    },
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + formatCLP( ctx.raw ),
                    },
                },
            } ),
        } );
    }

    /* ── Table ── */
    function renderTable() {
        const sorted  = [ ...allLocales ].sort( ( a, b ) => b.VTADIAACT - a.VTADIAACT );
        const maxVenta= sorted[0]?.VTADIAACT || 1;
        tableBody.innerHTML = '';

        sorted.forEach( ( loc, i ) => {
            const v       = variationBadge( loc.VTADIAACT, loc.VTADIAANT );
            const pPct    = ( loc.VTADIAACT / maxVenta * 100 ).toFixed( 1 );
            const peak    = peakHour( loc );
            const rankNum = i + 1;
            const rankCls = rankNum <= 3 ? `gd-rank-badge--${ rankNum }` : 'gd-rank-badge--other';
            const rankHtml = `<span class="gd-rank-badge ${ rankCls }">${ rankNum }</span>`;
            const tr   = document.createElement( 'tr' );

            tr.innerHTML = `
                <td>${ rankHtml }</td>
                <td>${ trimName( loc.NOMBRE ) }</td>
                <td><strong>${ formatCLP( loc.VTADIAACT ) }</strong></td>
                <td>${ loc.VTADIAANT > 0 ? formatCLP( loc.VTADIAANT ) : '<span style="color:#8892a4">—</span>' }</td>
                <td>${ v }</td>
                <td>${ peak }</td>
                <td>
                    <div class="gd-rank-bar-wrap">
                        <div class="gd-rank-bar" style="width:${ pPct }%"></div>
                    </div>
                </td>
            `;
            tr.style.cursor = 'pointer';
            tr.title = `Ver detalle de ${ trimName( loc.NOMBRE ) }`;
            tr.addEventListener( 'click', () => {
                activeLocal = loc.LOCAL.trim();
                updateTabSelection();
                renderKPIs();
                renderHourlyChart();
                // Smooth scroll al chart
                document.getElementById( 'gd-chart-hourly' )?.scrollIntoView( { behavior: 'smooth', block: 'center' } );
            } );
            tableBody.appendChild( tr );
        } );
    }

    /* ── Shared Chart.js options ── */
    function chartOptions( overrides = {} ) {
        const c = chartColors();
        const base = {
            responsive:          true,
            maintainAspectRatio: false,
            animation:           { duration: 500 },
            plugins: {
                legend: {
                    display:  true,
                    position: 'top',
                    labels:   { color: c.tick, boxWidth: 12, padding: 18, font: { size: 12 } },
                },
                tooltip: {
                    backgroundColor: c.bgTooltip,
                    borderColor:     c.borderTooltip,
                    borderWidth:     1,
                    titleColor:      c.titleTooltip,
                    bodyColor:       c.bodyTooltip,
                    padding:         12,
                    cornerRadius:    8,
                },
            },
            scales: {
                x: {
                    ticks: { color: c.tick, font: { size: 11 } },
                    grid:  { color: c.grid },
                },
                y: {
                    ticks: { color: c.tick, callback: v => formatCLP( v ), font: { size: 11 } },
                    grid:  { color: c.grid },
                },
            },
        };

        // Deep merge overrides
        return deepMerge( base, overrides );
    }

    function deepMerge( target, source ) {
        const out = Object.assign( {}, target );
        for ( const key of Object.keys( source ) ) {
            if ( source[ key ] && typeof source[ key ] === 'object' && ! Array.isArray( source[ key ] ) ) {
                out[ key ] = deepMerge( target[ key ] || {}, source[ key ] );
            } else {
                out[ key ] = source[ key ];
            }
        }
        return out;
    }

    /* ── PWA ── */
    ( function () {
        const INSTALL_SHOWN_KEY = 'gd_install_prompt_shown';
        const isStandalone = () =>
            window.matchMedia( '(display-mode: standalone)' ).matches ||
            window.navigator.standalone === true;

        const isIOS = () => /iphone|ipad|ipod/i.test( navigator.userAgent ) && ! window.MSStream;

        const installBtn      = document.getElementById( 'gd-install-btn' );
        const installDivider  = document.getElementById( 'gd-install-divider' );
        const banner          = document.getElementById( 'gd-install-banner' );
        const bannerInstall   = document.getElementById( 'gd-banner-install' );
        const bannerDismiss   = document.getElementById( 'gd-banner-dismiss' );
        const iosOverlay      = document.getElementById( 'gd-ios-overlay' );
        const iosClose        = document.getElementById( 'gd-ios-close' );

        let deferredPrompt = null;

        /* ── Registrar Service Worker ── */
        if ( 'serviceWorker' in navigator ) {
            navigator.serviceWorker.register( window.location.origin + '/?gd-sw=1', { scope: '/' } )
                .catch( () => {} );
        }

        /* Si ya está instalado, no mostrar nada */
        if ( isStandalone() ) return;

        /* ── Mostrar opción instalar en dropdown ── */
        function showInstallOption() {
            if ( installBtn ) {
                installBtn.hidden    = false;
                installDivider.hidden = false;
            }
        }

        /* ── Banner primera vez ── */
        function maybeShowBanner() {
            if ( localStorage.getItem( INSTALL_SHOWN_KEY ) ) return;
            /* Pequeño delay para no interrumpir al usuario al entrar */
            setTimeout( () => {
                if ( banner ) banner.hidden = false;
            }, 3500 );
        }

        /* ── Android / Chrome: capturar beforeinstallprompt ── */
        window.addEventListener( 'beforeinstallprompt', e => {
            e.preventDefault();
            deferredPrompt = e;
            showInstallOption();
            maybeShowBanner();
        } );

        /* ── iOS: mostrar siempre la opción con instrucciones ── */
        if ( isIOS() ) {
            showInstallOption();
            maybeShowBanner();
        }

        /* ── Trigger instalación ── */
        function triggerInstall() {
            localStorage.setItem( INSTALL_SHOWN_KEY, '1' );
            if ( banner ) banner.hidden = true;
            closeDropdown();

            if ( deferredPrompt ) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then( () => { deferredPrompt = null; } );
            } else if ( isIOS() && iosOverlay ) {
                iosOverlay.hidden = false;
            }
        }

        /* ── Dismiss banner ── */
        if ( bannerDismiss ) {
            bannerDismiss.addEventListener( 'click', () => {
                banner.hidden = true;
                localStorage.setItem( INSTALL_SHOWN_KEY, '1' );
            } );
        }

        /* ── Banner instalar button ── */
        if ( bannerInstall ) {
            bannerInstall.addEventListener( 'click', triggerInstall );
        }

        /* ── Dropdown instalar button ── */
        if ( installBtn ) {
            installBtn.addEventListener( 'click', triggerInstall );
        }

        /* ── Cerrar modal iOS ── */
        if ( iosClose ) {
            iosClose.addEventListener( 'click', () => { iosOverlay.hidden = true; } );
        }
        if ( iosOverlay ) {
            iosOverlay.addEventListener( 'click', e => {
                if ( e.target === iosOverlay ) iosOverlay.hidden = true;
            } );
        }

        /* ── Ocultar opción cuando ya está instalado ── */
        window.addEventListener( 'appinstalled', () => {
            if ( installBtn )    installBtn.hidden    = true;
            if ( installDivider) installDivider.hidden = true;
            if ( banner )        banner.hidden         = true;
            deferredPrompt = null;
        } );
    } )();

    /* ── Auto-refresh ── */
    function startRefreshTimer() {
        if ( refreshTimer ) clearInterval( refreshTimer );
        refreshTimer = setInterval( fetchData, GournetConfig.refreshMs );
    }

    /* ── Event listeners ── */
    refreshBtn.addEventListener( 'click', () => {
        showLoading();
        fetchData();
    } );
    retryBtn.addEventListener( 'click', () => {
        showLoading();
        fetchData();
    } );

    /* ── Medir header y setear --header-h para sticky tabs ── */
    function updateHeaderHeight() {
        const h = document.querySelector( '.gd-header' );
        if ( h ) app.style.setProperty( '--header-h', h.offsetHeight + 'px' );
    }
    updateHeaderHeight();
    window.addEventListener( 'resize', updateHeaderHeight );

    /* ── Boot ── */
    applyTheme( currentTheme );
    updateHeaderHeight(); // re-medir después de aplicar tema
    showLoading();
    fetchData();
    startRefreshTimer();

} )();
