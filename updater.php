<?php
if ( ! class_exists( 'Gournet_Dashboard_Updater' ) ) {
    class Gournet_Dashboard_Updater {

        private $file;
        private $plugin;
        private $basename;
        private $active;
        private $error_message;

        private $github_repo = 'Corredora-Online/Gour-net-App';

        public function __construct( $file ) {
            $this->file          = $file;
            $this->plugin        = plugin_basename( $file );
            $this->basename      = str_replace( '/', '-', $this->plugin );
            $this->active        = $this->is_plugin_active( $this->plugin );
            $this->error_message = '';
        }

        public function initialize() {
            add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_transient' ), 10, 1 );
            add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
            add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
            add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        }

        private function is_plugin_active( $plugin ) {
            return in_array( $plugin, (array) get_option( 'active_plugins', array() ) );
        }

        private function get_repository_info() {
            $request_uri = 'https://api.github.com/repos/' . $this->github_repo . '/releases';
            $response    = wp_remote_get( $request_uri );

            if ( is_wp_error( $response ) ) {
                $this->error_message = 'Error en la solicitud a GitHub: ' . $response->get_error_message();
                return false;
            }

            $response_body  = wp_remote_retrieve_body( $response );
            $response_array = json_decode( $response_body, true );

            if ( ! is_array( $response_array ) || empty( $response_array ) ) {
                $this->error_message = 'No se pudo decodificar la respuesta JSON de GitHub o está vacía.';
                return false;
            }

            return $response_array;
        }

        public function modify_transient( $transient ) {
            if ( empty( $transient->checked ) ) {
                return $transient;
            }

            $repository = $this->get_repository_info();
            if ( $repository === false || ! is_array( $repository ) || empty( $repository[0]['tag_name'] ) ) {
                return $transient;
            }

            $version         = $repository[0]['tag_name'];
            $current_version = $transient->checked[ $this->plugin ];
            $new_version     = version_compare( $version, $current_version, 'gt' );

            if ( $new_version ) {
                $package = $repository[0]['zipball_url'];

                $obj              = new stdClass();
                $obj->slug        = $this->basename;
                $obj->new_version = $version;
                $obj->url         = $this->plugin;
                $obj->package     = $package;

                $transient->response[ $this->plugin ] = $obj;
            }

            return $transient;
        }

        public function plugin_popup( $result, $action, $args ) {
            if ( ! empty( $args->slug ) && $args->slug == $this->basename ) {
                $repository = $this->get_repository_info();
                if ( $repository !== false ) {
                    $version = $repository[0]['tag_name'];

                    $result                = new stdClass();
                    $result->name          = 'Gournet Dashboard';
                    $result->slug          = $this->basename;
                    $result->version       = $version;
                    $result->author        = '<a href="https://novelty8.com/">Novelty8</a>';
                    $result->homepage      = 'https://novelty8.com';
                    $result->download_link = $repository[0]['zipball_url'];
                    $result->sections      = array(
                        'description' => 'Dashboard de ventas en tiempo real para locales Gournet.',
                    );
                }
            }

            return $result;
        }

        public function after_install( $response, $hook_extra, $result ) {
            global $wp_filesystem;
            $install_directory = plugin_dir_path( $this->file );
            $wp_filesystem->move( $result['destination'], $install_directory );
            $result['destination'] = $install_directory;

            if ( $this->active ) {
                activate_plugin( $this->plugin );
            }

            return $result;
        }

        public function admin_notices() {
            if ( ! empty( $this->error_message ) ) {
                echo '<div class="notice notice-error"><p>' . esc_html( $this->error_message ) . '</p></div>';
            }
        }
    }
}

/* -----------------------------------------------------------------
 * Enlace "Buscar actualizaciones" en la fila del plugin (wp-admin)
 * ----------------------------------------------------------------- */
$gournet_main_plugin_file = plugin_basename( dirname( __FILE__ ) . '/gournet-dashboard.php' );

add_filter( 'plugin_row_meta', 'gournet_add_force_update_link', 10, 2 );
function gournet_add_force_update_link( $links, $file ) {
    global $gournet_main_plugin_file;

    if ( $file === $gournet_main_plugin_file ) {
        $force_update_url = wp_nonce_url(
            add_query_arg( array( 'gournet_force_update' => '1' ) ),
            'gournet_force_update_nonce'
        );

        $links[] = sprintf(
            '<a href="%s" style="color:#2271b1;">%s</a>',
            esc_url( $force_update_url ),
            __( 'Buscar actualizaciones', 'gournet-dashboard' )
        );
    }
    return $links;
}

add_action( 'admin_init', 'gournet_maybe_force_update' );
function gournet_maybe_force_update() {
    if (
        isset( $_GET['gournet_force_update'] ) &&
        $_GET['gournet_force_update'] == '1' &&
        check_admin_referer( 'gournet_force_update_nonce' )
    ) {
        delete_site_transient( 'update_plugins' );
        wp_safe_redirect( admin_url( 'plugins.php?gournet_forced=1' ) );
        exit;
    }
}

add_action( 'admin_notices', 'gournet_show_forced_update_notice' );
function gournet_show_forced_update_notice() {
    if ( isset( $_GET['gournet_forced'] ) && $_GET['gournet_forced'] == '1' ) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        esc_html_e( 'Se ha forzado la búsqueda de actualizaciones de Gournet Dashboard.', 'gournet-dashboard' );
        echo '</p></div>';
    }
}
