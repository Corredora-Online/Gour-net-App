<?php
/**
 * Plugin Name: Gournet Dashboard
 * Plugin URI:  https://novelty8.com
 * Description: Dashboard de ventas en tiempo real para locales Gournet. Usa el shortcode [gournet_dashboard] para embeber el panel.
 * Version:     1.0.15
 * Author:      Novelty8
 * License:     GPL-2.0+
 * Text Domain: gournet-dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ── Auto-updater desde GitHub ── */
require_once plugin_dir_path( __FILE__ ) . 'updater.php';

if ( class_exists( 'Gournet_Dashboard_Updater' ) ) {
    $gournet_updater = new Gournet_Dashboard_Updater( __FILE__ );
    $gournet_updater->initialize();
}

define( 'GOURNET_VERSION', get_file_data( __FILE__, [ 'Version' => 'Version' ] )['Version'] );
define( 'GOURNET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GOURNET_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GOURNET_WEBHOOK_URL',         'https://atm.novelty8.com/webhook/b364359a-e56e-45b6-b288-e69f27456437' );
define( 'GOURNET_SERVER_CHECK_URL',   'https://atm.novelty8.com/webhook/b9b1ee39-2a7a-430d-9676-b739751c6751' );
define( 'GOURNET_SERVER_CHECK_TOKEN', 'hPYwgXq8DARZ6IxM73b6qJbV' );
define( 'GOURNET_APP_ICON', 'https://app.gour-net.cl/wp-content/uploads/2026/03/gour_net_logo.jpeg' );

/* Ocultar la barra de administración para todos los usuarios */
add_filter( 'show_admin_bar', '__return_false' );

/* -----------------------------------------------------------------------
   PWA – Endpoints: manifest, service worker, offline page
----------------------------------------------------------------------- */
add_action( 'init', 'gournet_pwa_endpoints', 1 );
function gournet_pwa_endpoints() {

    /* ── Manifest ── */
    if ( isset( $_GET['gd-manifest'] ) ) {
        $start_url = get_option( 'gournet_dashboard_url', home_url( '/' ) );
        $manifest  = [
            'id'               => home_url( '/' ),
            'name'             => 'Gournet Dashboard',
            'short_name'       => 'Gournet',
            'description'      => 'Dashboard de ventas en tiempo real',
            'start_url'        => $start_url,
            'scope'            => home_url( '/' ),
            'display'          => 'standalone',
            'background_color' => '#13141a',
            'theme_color'      => '#EA529F',
            'orientation'      => 'portrait-primary',
            'lang'             => 'es-CL',
            'categories'       => [ 'business' ],
            'icons'            => [
                [
                    'src'     => GOURNET_APP_ICON,
                    'sizes'   => '192x192',
                    'type'    => 'image/jpeg',
                    'purpose' => 'any',
                ],
                [
                    'src'     => GOURNET_APP_ICON,
                    'sizes'   => '512x512',
                    'type'    => 'image/jpeg',
                    'purpose' => 'any',
                ],
            ],
        ];
        header( 'Content-Type: application/manifest+json; charset=utf-8' );
        header( 'Cache-Control: no-cache' );
        echo wp_json_encode( $manifest );
        exit;
    }

    /* ── Service Worker ── */
    if ( isset( $_GET['gd-sw'] ) ) {
        header( 'Content-Type: application/javascript; charset=utf-8' );
        header( 'Service-Worker-Allowed: /' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $sw_content = file_get_contents( GOURNET_PLUGIN_DIR . 'assets/sw.js' );
        $sw_content = str_replace( '___GOURNET_VERSION___', GOURNET_VERSION, $sw_content );
        echo $sw_content;
        exit;
    }

    /* ── Offline page ── */
    if ( isset( $_GET['gd-offline'] ) ) {
        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'Cache-Control: no-cache' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        echo file_get_contents( GOURNET_PLUGIN_DIR . 'assets/offline.html' );
        exit;
    }
}

/* -----------------------------------------------------------------------
   PWA – Meta tags en <head>
----------------------------------------------------------------------- */
add_action( 'wp_head', 'gournet_pwa_head', 1 );
function gournet_pwa_head() {
    if ( ! is_singular() ) return;
    $manifest_url = esc_url( home_url( '/?gd-manifest=1' ) );
    $icon         = esc_url( GOURNET_APP_ICON );
    ?>
    <link rel="manifest" href="<?php echo $manifest_url; ?>">
    <meta name="theme-color" content="#EA529F">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Gournet">
    <link rel="apple-touch-icon" href="<?php echo $icon; ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Gournet Dashboard">
    <?php
}

/* -----------------------------------------------------------------------
   Enqueue assets
----------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'gournet_enqueue_assets' );
function gournet_enqueue_assets() {
    if ( ! is_singular() ) return;

    // Poppins from Google Fonts
    wp_enqueue_style(
        'poppins',
        'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap',
        [],
        null
    );

    // Chart.js from CDN (lightweight, no npm needed)
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js',
        [],
        '4.4.2',
        true
    );

    wp_enqueue_style(
        'gournet-dashboard',
        GOURNET_PLUGIN_URL . 'assets/dashboard.css',
        [],
        GOURNET_VERSION
    );

    wp_enqueue_script(
        'gournet-dashboard',
        GOURNET_PLUGIN_URL . 'assets/dashboard.js',
        [ 'chartjs' ],
        GOURNET_VERSION,
        true
    );

    wp_localize_script( 'gournet-dashboard', 'GournetConfig', [
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'gournet_fetch' ),
        'refreshMs' => 60000, // auto-refresh cada 60 s
    ] );
}

/* -----------------------------------------------------------------------
   AJAX handler – Login seguro
----------------------------------------------------------------------- */
add_action( 'wp_ajax_nopriv_gournet_login', 'gournet_ajax_login' );
add_action( 'wp_ajax_gournet_login',        'gournet_ajax_login' );

function gournet_ajax_login() {

    /* 1 · CSRF – nonce */
    if ( ! check_ajax_referer( 'gournet_login_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Solicitud no válida. Recarga la página.' ], 403 );
    }

    /* 2 · Honeypot anti-bot: campo vacío esperado */
    if ( ! empty( $_POST['website'] ) ) {
        // Finge éxito para no revelar la trampa
        wp_send_json_error( [ 'message' => 'RUT o contraseña incorrectos.' ], 401 );
    }

    /* 3 · Rate limiting por IP — máx. 5 intentos / 15 min */
    $raw_ip    = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] )
                 ? explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) )[0]
                 : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown' );
    $ip        = trim( $raw_ip );
    $lock_key  = 'gd_lock_'  . md5( $ip );
    $count_key = 'gd_fails_' . md5( $ip );
    $max_tries = 5;
    $lockout   = 15 * MINUTE_IN_SECONDS;

    if ( get_transient( $lock_key ) ) {
        $ttl  = (int) get_option( '_transient_timeout_' . $lock_key ) - time();
        $mins = max( 1, (int) ceil( $ttl / 60 ) );
        wp_send_json_error( [
            'message' => "Demasiados intentos fallidos. Intenta de nuevo en {$mins} min.",
            'locked'  => true,
        ], 429 );
    }

    /* 4 · Validar y sanear inputs */
    $username = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
    $password = isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : '';

    if ( empty( $username ) || empty( $password ) ) {
        wp_send_json_error( [ 'message' => 'Completa todos los campos.' ], 400 );
    }

    if ( mb_strlen( $username ) > 60 || mb_strlen( $password ) > 72 ) {
        wp_send_json_error( [ 'message' => 'RUT o contraseña incorrectos.' ], 401 );
    }

    /* 5 · Cookie de larga duración (1 año) para "recuérdame" */
    add_filter( 'auth_cookie_expiration', function () {
        return YEAR_IN_SECONDS;
    } );

    /* 6 · Autenticación con WordPress */
    $credentials = [
        'user_login'    => $username,
        'user_password' => $password,
        'remember'      => true,
    ];
    $user = wp_signon( $credentials, is_ssl() );

    if ( is_wp_error( $user ) ) {
        /* Incrementar contador de fallos */
        $fails = (int) get_transient( $count_key ) + 1;
        set_transient( $count_key, $fails, $lockout );

        if ( $fails >= $max_tries ) {
            set_transient( $lock_key, 1, $lockout );
            delete_transient( $count_key );
            wp_send_json_error( [
                'message' => 'Cuenta bloqueada temporalmente por seguridad. Intenta en 15 min.',
                'locked'  => true,
            ], 429 );
        }

        $remaining = $max_tries - $fails;
        wp_send_json_error( [
            'message'   => 'RUT o contraseña incorrectos.',
            'remaining' => $remaining,
        ], 401 );
    }

    /* 7 · Login exitoso — limpiar contadores */
    delete_transient( $count_key );
    delete_transient( $lock_key );

    wp_set_current_user( $user->ID );

    wp_send_json_success( [
        'redirect' => get_permalink(),
    ] );
}

/* -----------------------------------------------------------------------
   AJAX handler – Verificación de conectividad con servidor externo
----------------------------------------------------------------------- */
add_action( 'wp_ajax_nopriv_gournet_verify_server', 'gournet_ajax_verify_server' );
add_action( 'wp_ajax_gournet_verify_server',        'gournet_ajax_verify_server' );

function gournet_ajax_verify_server() {

    /* 1 · CSRF */
    if ( ! check_ajax_referer( 'gournet_verify_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Solicitud no válida. Recarga la página.' ], 403 );
    }

    /* 2 · Rate limiting: máx 10 verificaciones / 5 min por IP */
    $raw_ip   = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] )
                ? explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) )[0]
                : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown' );
    $ip       = trim( $raw_ip );
    $rate_key = 'gd_verify_' . md5( $ip );
    $count    = (int) get_transient( $rate_key );
    if ( $count >= 10 ) {
        wp_send_json_error( [ 'message' => 'Demasiadas solicitudes. Intenta en unos minutos.' ], 429 );
    }
    set_transient( $rate_key, $count + 1, 5 * MINUTE_IN_SECONDS );

    /* 3 · Recibir y sanear el RUT enviado por el frontend */
    $user_rut = isset( $_POST['user_rut'] ) ? sanitize_text_field( wp_unslash( $_POST['user_rut'] ) ) : '';

    /* 4 · Llamada al servidor externo — URL y token nunca salen de PHP */
    $response = wp_remote_post( GOURNET_SERVER_CHECK_URL, [
        'timeout'   => 10,
        'sslverify' => true,
        'headers'   => [
            'Authorization' => GOURNET_SERVER_CHECK_TOKEN,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [ 'user_rut' => $user_rut ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'message' => 'No pudimos conectarnos con el servidor, intente de nuevo más tarde' ], 503 );
    }

    $code = (int) wp_remote_retrieve_response_code( $response );

    if ( $code === 200 ) {
        wp_send_json_success( [ 'ok' => true ] );
    }

    wp_send_json_error( [ 'message' => 'No pudimos conectarnos con el servidor, intente de nuevo más tarde' ], 503 );
}

/* -----------------------------------------------------------------------
   Helper – Formatear RUT con puntos y guión
----------------------------------------------------------------------- */
function gournet_formatear_rut( $rut_limpio ) {
    // Eliminar caracteres no numéricos (excepto K)
    $rut_limpio = preg_replace( '/[^0-9K]/i', '', $rut_limpio );

    if ( strlen( $rut_limpio ) === 0 ) {
        return '';
    }

    // Extraer DV (último carácter)
    $dv = strtoupper( substr( $rut_limpio, -1 ) );
    $numeros = substr( $rut_limpio, 0, -1 );

    // Formatear con puntos (de derecha a izquierda cada 3 dígitos)
    $numeros_formateado = preg_replace( '/\B(?=(\d{3})+(?!\d))/', '.', $numeros );

    return $numeros_formateado . '-' . $dv;
}

/* -----------------------------------------------------------------------
   AJAX handler – proxy al webhook externo
----------------------------------------------------------------------- */
add_action( 'wp_ajax_gournet_fetch_data',        'gournet_ajax_fetch' );
add_action( 'wp_ajax_nopriv_gournet_fetch_data', 'gournet_ajax_fetch' );

function gournet_ajax_fetch() {
    check_ajax_referer( 'gournet_fetch', 'nonce' );

    /* Verificar que el usuario esté autenticado */
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Usuario no autenticado' ], 401 );
    }

    /* Obtener el RUT del usuario autenticado */
    $current_user = wp_get_current_user();
    $user_rut = '';

    if ( $current_user->ID && ! empty( $current_user->user_login ) ) {
        /* El username está guardado sin puntos, sin guión, en mayúsculas */
        $user_rut = gournet_formatear_rut( $current_user->user_login );
    }

    /* Si no hay RUT, retornar error */
    if ( empty( $user_rut ) ) {
        wp_send_json_error( [ 'message' => 'No se pudo obtener el RUT del usuario' ], 400 );
    }

    /* Construir URL con query param user_rut */
    $url = add_query_arg( 'user_rut', rawurlencode( $user_rut ), GOURNET_WEBHOOK_URL );

    $response = wp_remote_get( $url, [
        'timeout'   => 15,
        'sslverify' => true,
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'message' => $response->get_error_message() ], 502 );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( $code !== 200 ) {
        wp_send_json_error( [ 'message' => "Webhook respondió con código $code" ], 502 );
    }

    $data = json_decode( $body, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        wp_send_json_error( [ 'message' => 'Respuesta no es JSON válido' ], 502 );
    }

    wp_send_json_success( $data );
}

/* -----------------------------------------------------------------------
   Shortcode [gournet_dashboard]
----------------------------------------------------------------------- */
add_shortcode( 'gournet_dashboard', 'gournet_render_dashboard' );

function gournet_render_dashboard( $atts ) {

    /* Guardar la URL de esta página para usarla como start_url en el manifest */
    $current_url = get_permalink();
    if ( $current_url ) {
        update_option( 'gournet_dashboard_url', $current_url, false );
    }

    /* ── Verificación de autenticación ── */
    if ( ! is_user_logged_in() ) {
        $login_nonce  = wp_create_nonce( 'gournet_login_nonce' );
        $verify_nonce = wp_create_nonce( 'gournet_verify_nonce' );
        $ajax_url     = esc_url( admin_url( 'admin-ajax.php' ) );
        ob_start();
        ?>
        <script>document.documentElement.classList.add('gd-page');</script>
        <div id="gournet-app" class="gournet-app gournet-app--login" role="main">
            <div class="gd-login-wrap">
                <div class="gd-login-card">

                    <div class="gd-login-logo">
                        <img src="https://app.gour-net.cl/wp-content/uploads/2026/03/logo_blanco.png" alt="Gournet">
                    </div>

                    <h1 class="gd-login-title">Bienvenido</h1>
                    <p class="gd-login-subtitle">Ingresa tus credenciales para acceder al dashboard</p>

                    <form id="gd-login-form" novalidate autocomplete="on">

                        <!-- Campo trampa anti-bot (invisible para humanos) -->
                        <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;">
                            <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
                        </div>

                        <input type="hidden" name="nonce" value="<?php echo esc_attr( $login_nonce ); ?>">
                        <input type="hidden" name="action" value="gournet_login">
                        <!-- Recuérdame siempre activo y oculto -->
                        <input type="hidden" name="rememberme" value="1">

                        <div class="gd-login-field">
                            <label class="gd-login-label" for="gd-rut">RUT Empresa</label>
                            <div class="gd-login-input-wrap">
                                <svg class="gd-login-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <input class="gd-login-input" type="text" id="gd-rut" name="log"
                                    placeholder="12.345.678-9"
                                    autocomplete="username"
                                    inputmode="text"
                                    maxlength="12"
                                    required>
                                <span id="gd-rut-spinner" class="gd-rut-spinner" hidden aria-hidden="true"></span>
                            </div>
                        </div>

                        <div class="gd-login-field">
                            <label class="gd-login-label" for="gd-pwd">Contraseña</label>
                            <div class="gd-login-input-wrap">
                                <svg class="gd-login-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <input class="gd-login-input" type="password" id="gd-pwd" name="pwd"
                                    placeholder="••••••••"
                                    autocomplete="current-password"
                                    required disabled>
                                <button type="button" class="gd-login-toggle-pwd" id="gd-toggle-pwd" aria-label="Mostrar contraseña" tabindex="-1" disabled>
                                    <svg id="gd-eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg id="gd-eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" hidden><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                        </div>

                        <div id="gd-login-error" class="gd-login-error" hidden role="alert"></div>
                        <div id="gd-login-lock" class="gd-login-lock" hidden role="alert">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <span id="gd-login-lock-msg"></span>
                        </div>

                        <button class="gd-login-submit" type="submit" id="gd-login-submit" disabled>
                            <span id="gd-login-btn-text">Ingresar</span>
                            <span id="gd-login-spinner" class="gd-login-spinner" hidden></span>
                        </button>

                    </form>
                </div>
            </div>
        </div>

        <script>
        ( function () {
            'use strict';

            const AJAX_URL     = '<?php echo $ajax_url; ?>';
            const VERIFY_NONCE = '<?php echo esc_js( $verify_nonce ); ?>';

            const form       = document.getElementById( 'gd-login-form' );
            const rutInput   = document.getElementById( 'gd-rut' );
            const pwdInput   = document.getElementById( 'gd-pwd' );
            const submitBtn  = document.getElementById( 'gd-login-submit' );
            const btnText    = document.getElementById( 'gd-login-btn-text' );
            const spinner    = document.getElementById( 'gd-login-spinner' );
            const errorBox   = document.getElementById( 'gd-login-error' );
            const lockBox    = document.getElementById( 'gd-login-lock' );
            const lockMsg    = document.getElementById( 'gd-login-lock-msg' );
            const togglePwd  = document.getElementById( 'gd-toggle-pwd' );
            const eyeShow    = document.getElementById( 'gd-eye-show' );
            const eyeHide    = document.getElementById( 'gd-eye-hide' );
            const rutSpinner = document.getElementById( 'gd-rut-spinner' );

            /* ── Estado de verificación del servidor ── */
            let serverVerified = false;
            let isVerifying    = false;

            /* ── Helpers UI ── */
            function setLoading( on ) {
                submitBtn.disabled = on;
                btnText.hidden     = on;
                spinner.hidden     = ! on;
            }

            function showError( msg ) {
                errorBox.textContent = msg;
                errorBox.hidden      = false;
                lockBox.hidden       = true;
                rutInput.classList.add( 'gd-login-input--error' );
                /* Solo marcar contraseña si está habilitada */
                if ( ! pwdInput.disabled ) {
                    pwdInput.classList.add( 'gd-login-input--error' );
                }
            }

            function showLock( msg ) {
                lockMsg.textContent = msg;
                lockBox.hidden      = false;
                errorBox.hidden     = true;
                submitBtn.disabled  = true;
            }

            function clearErrors() {
                errorBox.hidden = true;
                lockBox.hidden  = true;
                rutInput.classList.remove( 'gd-login-input--error' );
                pwdInput.classList.remove( 'gd-login-input--error' );
            }

            /* ── Bloquear / desbloquear contraseña ── */
            function lockPassword() {
                serverVerified     = false;
                pwdInput.disabled  = true;
                togglePwd.disabled = true;
                submitBtn.disabled = true;
                pwdInput.value     = '';
            }

            function unlockPassword() {
                serverVerified     = true;
                pwdInput.disabled  = false;
                togglePwd.disabled = false;
                submitBtn.disabled = false;
            }

            function setChecking( on ) {
                isVerifying    = on;
                rutSpinner.hidden = ! on;
                rutInput.classList.toggle( 'gd-login-input--checking', on );
            }

            /* ── Verificar que el servidor externo está operativo ── */
            function verifyServer() {
                if ( isVerifying ) return;
                clearErrors();
                setChecking( true );

                const params = new URLSearchParams();
                params.set( 'action',    'gournet_verify_server' );
                params.set( 'nonce',     VERIFY_NONCE );
                params.set( 'user_rut',  formatearRUT( rutInput.value ) );

                fetch( AJAX_URL, {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:        params.toString(),
                } )
                .then( r => r.json() )
                .then( json => {
                    setChecking( false );
                    if ( json.success ) {
                        unlockPassword();
                        pwdInput.focus();
                    } else {
                        lockPassword();
                        showError( json.data?.message || 'No pudimos conectarnos con el servidor, intente de nuevo más tarde' );
                    }
                } )
                .catch( () => {
                    setChecking( false );
                    lockPassword();
                    showError( 'No pudimos conectarnos con el servidor, intente de nuevo más tarde' );
                } );
            }

            /* ── Autocompletar último RUT usado ── */
            ( function () {
                try {
                    const saved = localStorage.getItem( 'gournet_last_rut' );
                    if ( saved ) {
                        rutInput.value = saved;
                        /* Verificar servidor automáticamente con RUT guardado */
                        setTimeout( verifyServer, 0 );
                    }
                } catch(e) {}
            } )();

            /* ── Formatear RUT chileno mientras escribe ── */
            rutInput.addEventListener( 'input', function () {
                let v = this.value.replace( /[^0-9kK]/g, '' ).toUpperCase();
                if ( v.length > 1 ) {
                    const dv   = v.slice( -1 );
                    const body = v.slice( 0, -1 ).replace( /\B(?=(\d{3})+(?!\d))/g, '.' );
                    v = body + '-' + dv;
                }
                this.value = v;

                /* Si el usuario edita el RUT después de verificar, bloquear de nuevo */
                if ( serverVerified ) {
                    lockPassword();
                    clearErrors();
                }
            } );

            /* ── Verificar servidor al salir del campo RUT ── */
            rutInput.addEventListener( 'blur', function () {
                const rutValue = this.value.trim();

                if ( ! rutValue ) return;

                /* Validar RUT chileno */
                if ( ! validarRUT( rutValue ) ) {
                    lockPassword();
                    showError( 'RUT inválido. Verifica que sea correcto.' );
                    return;
                }

                /* Si RUT es válido, verificar servidor */
                if ( ! serverVerified && ! isVerifying ) {
                    verifyServer();
                }
            } );

            /* ── Toggle mostrar/ocultar contraseña ── */
            togglePwd.addEventListener( 'click', function () {
                const show = pwdInput.type === 'password';
                pwdInput.type = show ? 'text' : 'password';
                eyeShow.hidden = show;
                eyeHide.hidden = ! show;
                this.setAttribute( 'aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña' );
            } );

            /* ── Calcular dígito verificador (módulo 11) ── */
            function calcularDigitoVerificador( rutSinDV ) {
                const multiplicadores = [ 2, 3, 4, 5, 6, 7 ];
                let suma = 0;
                const rutStr = rutSinDV.toString();

                for ( let i = rutStr.length - 1, j = 0; i >= 0; i--, j++ ) {
                    const digito = parseInt( rutStr[i] );
                    const multiplicador = multiplicadores[ j % 6 ];
                    suma += digito * multiplicador;
                }

                const resto = suma % 11;
                const dv = 11 - resto;

                if ( dv === 11 ) return '0';
                if ( dv === 10 ) return 'K';
                return dv.toString();
            }

            /* ── Validar RUT chileno ── */
            function validarRUT( rut ) {
                rut = rut.replace( /\s/g, '' ).toUpperCase();

                if ( ! rut.includes( '-' ) ) {
                    return false;
                }

                const [ rutNumeros, dvIngresado ] = rut.split( '-' );
                const rutLimpio = rutNumeros.replace( /\./g, '' );

                if ( ! /^\d+$/.test( rutLimpio ) || rutLimpio.length === 0 ) {
                    return false;
                }

                const dvEsperado = calcularDigitoVerificador( rutLimpio );
                return dvIngresado === dvEsperado;
            }

            /* ── Formatear RUT con puntos y guión (para enviar a API) ── */
            function formatearRUT( rut ) {
                rut = rut.replace( /\D/g, '' ).toUpperCase();

                if ( rut.length === 0 ) return '';

                let dv;
                if ( rut.length <= 8 ) {
                    dv = calcularDigitoVerificador( rut );
                } else {
                    dv = rut.charAt( rut.length - 1 );
                    rut = rut.substring( 0, rut.length - 1 );
                }

                const rutFormato = rut.replace( /\B(?=(\d{3})+(?!\d))/g, '.' );
                return `${rutFormato}-${dv}`;
            }

            /* ── Obtener RUT sin formato (solo números y letra DV) ── */
            function limpiarRUT( rut ) {
                return rut.replace( /[.\-]/g, '' ).trim();
            }

            /* ── Submit ── */
            form.addEventListener( 'submit', function ( e ) {
                e.preventDefault();
                clearErrors();

                /* Guardia: servidor debe estar verificado */
                if ( ! serverVerified ) {
                    showError( 'Verifica tu RUT antes de continuar.' );
                    return;
                }

                const rutFormateado = formatearRUT( rutInput.value );
                const rutLimpio = limpiarRUT( rutFormateado ).toUpperCase();
                const pwd = pwdInput.value;

                if ( ! rutFormateado || ! pwd ) {
                    showError( 'Completa todos los campos.' );
                    return;
                }

                setLoading( true );

                const body = new URLSearchParams( new FormData( form ) );
                /* Envía el RUT limpio (sin puntos, sin guión, en mayúsculas) como se guarda en BD */
                body.set( 'log', rutLimpio );

                fetch( AJAX_URL, {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:        body.toString(),
                } )
                .then( r => r.json() )
                .then( json => {
                    if ( json.success ) {
                        /* Guardar RUT para autocompletar en próximo login */
                        try { localStorage.setItem( 'gournet_last_rut', rutInput.value ); } catch(e) {}
                        /* Redirigir — cookie ya está seteada por WordPress */
                        btnText.hidden      = false;
                        btnText.textContent = '✓ Ingresando…';
                        spinner.hidden      = true;
                        window.location.href = json.data.redirect || window.location.href;
                        return;
                    }

                    setLoading( false );

                    if ( json.data?.locked ) {
                        showLock( json.data.message );
                    } else {
                        let msg = json.data?.message || 'Error al iniciar sesión.';
                        if ( json.data?.remaining !== undefined ) {
                            msg += ' (' + json.data.remaining + ' intentos restantes)';
                        }
                        showError( msg );
                    }
                } )
                .catch( () => {
                    setLoading( false );
                    showError( 'Error de conexión. Verifica tu internet e intenta de nuevo.' );
                } );
            } );

            /* Limpiar errores al escribir */
            [rutInput, pwdInput].forEach( el => el.addEventListener( 'input', clearErrors ) );

        } )();
        </script>
        <?php
        return ob_get_clean();
    }

    /* ── Datos del usuario ── */
    $user       = wp_get_current_user();
    $first      = trim( $user->first_name );
    $last       = trim( $user->last_name );
    $full_name  = $first || $last ? trim( "$first $last" ) : '';
    $display_name = $full_name ?: ( $user->display_name !== $user->user_login ? $user->display_name : '' );
    $display_name = $display_name ?: $user->user_login;
    $user_login   = $user->user_login;

    $parts    = preg_split( '/\s+/', trim( $display_name ) );
    $initials = strtoupper( mb_substr( $parts[0], 0, 1 ) );
    if ( count( $parts ) > 1 ) {
        $initials .= strtoupper( mb_substr( end( $parts ), 0, 1 ) );
    }
    $logout_url = wp_logout_url( get_permalink() );

    ob_start();
    ?>
    <div id="gournet-app" class="gournet-app" role="main" aria-label="Dashboard de ventas Gournet">

        <!-- ── Header ── -->
        <header class="gd-header">
            <div class="gd-header__brand">
                <img class="gd-logo" src="https://app.gour-net.cl/wp-content/uploads/2026/03/logo_blanco.png" alt="Gournet">
            </div>
            <div class="gd-header__actions">
                <span class="gd-last-update" id="gd-last-update">Cargando…</span>
                <button class="gd-btn gd-btn--icon" id="gd-refresh" title="Actualizar datos" aria-label="Actualizar datos">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                </button>

                <!-- ── User menu ── -->
                <div class="gd-user-menu" id="gd-user-menu">
                    <button class="gd-user-btn" id="gd-user-btn" aria-haspopup="true" aria-expanded="false">
                        <span class="gd-user-avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
                        <span class="gd-user-name"><?php echo esc_html( $display_name ); ?></span>
                        <svg class="gd-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>

                    <div class="gd-dropdown" id="gd-dropdown" hidden role="menu">
                        <div class="gd-dropdown__header">
                            <span class="gd-dropdown__user-full"><?php echo esc_html( $display_name ); ?></span>
                            <span class="gd-dropdown__user-role">@<?php echo esc_html( $user_login ); ?></span>
                        </div>
                        <div class="gd-dropdown__divider"></div>
                        <button class="gd-dropdown__item" id="gd-theme-toggle" role="menuitem">
                            <span class="gd-dropdown__item-icon" id="gd-theme-icon">
                                <!-- icon injected by JS -->
                            </span>
                            <span id="gd-theme-label">Modo claro</span>
                        </button>
                        <!-- Instalar PWA (JS controla visibilidad) -->
                        <button class="gd-dropdown__item gd-dropdown__item--install" id="gd-install-btn" hidden role="menuitem">
                            <span class="gd-dropdown__item-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </span>
                            <span>Instalar app</span>
                        </button>
                        <div class="gd-dropdown__divider" id="gd-install-divider" hidden></div>
                        <div class="gd-dropdown__divider"></div>
                        <button class="gd-dropdown__item" id="gd-clear-cache-btn" role="menuitem">
                            <span class="gd-dropdown__item-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.5"/></svg>
                            </span>
                            <span>Liberar caché</span>
                        </button>
                        <div class="gd-dropdown__divider"></div>
                        <!-- Chat de soporte -->
                        <button class="gd-dropdown__item" id="gd-support-chat-btn" role="menuitem">
                            <span class="gd-dropdown__item-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                            </span>
                            <span>Chat de soporte</span>
                        </button>
                        <div class="gd-dropdown__divider"></div>
                        <a class="gd-dropdown__item gd-dropdown__item--danger" href="<?php echo esc_url( $logout_url ); ?>" role="menuitem">
                            <span class="gd-dropdown__item-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            </span>
                            <span>Cerrar sesión</span>
                        </a>
                    </div>
                </div><!-- /gd-user-menu -->
            </div>
        </header>

        <!-- ── Loading / Error states ── -->
        <div class="gd-loading" id="gd-loading" aria-live="polite">
            <div class="gd-spinner"></div>
            <p>Obteniendo datos…</p>
        </div>
        <div class="gd-error" id="gd-error" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p id="gd-error-msg">Error al cargar datos</p>
            <button class="gd-btn gd-btn--primary" id="gd-retry">Reintentar</button>
        </div>

        <!-- ── Main content (hidden until data arrives) ── -->
        <div class="gd-content" id="gd-content" hidden>

            <!-- Branch tabs -->
            <nav class="gd-tabs" id="gd-tabs" aria-label="Seleccionar sucursal" role="tablist"></nav>

            <!-- KPI Cards -->
            <section class="gd-kpis" id="gd-kpis" aria-label="Indicadores clave"></section>

            <!-- Charts row -->
            <div class="gd-charts-row">
                <section class="gd-card gd-card--chart gd-card--wide" aria-label="Ventas por hora">
                    <div class="gd-card__header">
                        <h2 class="gd-card__title">Ventas por hora — <span id="gd-chart-branch-name"></span></h2>
                        <div class="gd-legend" id="gd-hourly-legend"></div>
                    </div>
                    <div class="gd-chart-wrap">
                        <canvas id="gd-chart-hourly" role="img" aria-label="Gráfico de ventas por hora"></canvas>
                    </div>
                </section>
            </div>

            <!-- Comparison chart -->
            <div class="gd-charts-row">
                <section class="gd-card gd-card--chart gd-card--wide" aria-label="Comparación entre locales">
                    <div class="gd-card__header">
                        <h2 class="gd-card__title">Comparación de ventas — todos los locales</h2>
                    </div>
                    <div class="gd-chart-wrap gd-chart-wrap--md">
                        <canvas id="gd-chart-compare" role="img" aria-label="Gráfico comparativo de locales"></canvas>
                    </div>
                </section>
            </div>

            <!-- Ranking table -->
            <section class="gd-card" aria-label="Ranking de locales">
                <div class="gd-card__header">
                    <h2 class="gd-card__title">Ranking de locales — día actual</h2>
                </div>
                <div class="gd-table-wrap">
                    <table class="gd-table" id="gd-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Local</th>
                                <th>Venta hoy</th>
                                <th><?php $dias_semana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado']; echo esc_html( $dias_semana[ (int) date('w') ] . ' pasado' ); ?></th>
                                <th>Variación</th>
                                <th>Hora pico</th>
                                <th>Barra</th>
                            </tr>
                        </thead>
                        <tbody id="gd-table-body"></tbody>
                    </table>
                </div>
            </section>

        </div><!-- /gd-content -->

        <!-- ── Footer ── -->
        <footer class="gd-footer">
            <span>v<?php echo esc_html( GOURNET_VERSION ); ?></span>
            <span class="gd-footer__sep">·</span>
            <span>Desarrollado por <a class="gd-footer__link" href="https://novelty8.com" target="_blank" rel="noopener noreferrer">Novelty8</a></span>
        </footer>

        <!-- ── Banner instalar PWA (primera vez) ── -->
        <div class="gd-install-banner" id="gd-install-banner" hidden role="complementary" aria-label="Instalar aplicación">
            <img class="gd-install-banner__icon" src="<?php echo esc_url( GOURNET_APP_ICON ); ?>" alt="Gournet">
            <div class="gd-install-banner__text">
                <strong>Instala Gournet Dashboard</strong>
                <span>Accede más rápido desde tu pantalla de inicio</span>
            </div>
            <div class="gd-install-banner__actions">
                <button class="gd-install-banner__btn" id="gd-banner-install">Instalar</button>
                <button class="gd-install-banner__dismiss" id="gd-banner-dismiss" aria-label="Cerrar">✕</button>
            </div>
        </div>

        <!-- ── Modal instrucciones iOS ── -->
        <div class="gd-ios-overlay" id="gd-ios-overlay" hidden role="dialog" aria-modal="true" aria-label="Cómo instalar en iOS">
            <div class="gd-ios-modal">
                <button class="gd-ios-modal__close" id="gd-ios-close" aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <img class="gd-ios-modal__logo" src="<?php echo esc_url( GOURNET_APP_ICON ); ?>" alt="Gournet">
                <h2 class="gd-ios-modal__title">Instalar en iPhone / iPad</h2>
                <p class="gd-ios-modal__sub">Sigue estos pasos en Safari</p>
                <ol class="gd-ios-steps">
                    <li>
                        <span class="gd-ios-step__num">1</span>
                        <span>Toca el botón <strong>Compartir</strong> <span class="gd-ios-share-icon">&#xFE0F;</span> en la barra inferior de Safari</span>
                    </li>
                    <li>
                        <span class="gd-ios-step__num">2</span>
                        <span>Desliza hacia abajo y toca <strong>"Agregar a pantalla de inicio"</strong></span>
                    </li>
                    <li>
                        <span class="gd-ios-step__num">3</span>
                        <span>Toca <strong>"Agregar"</strong> para confirmar</span>
                    </li>
                </ol>
            </div>
        </div>

    </div><!-- /gournet-app -->
    <?php
    return ob_get_clean();
}
