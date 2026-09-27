<?php
/**
 * Plugin Name:       Order Menu – Pages & Posts Sorter
 * Plugin URI:        https://github.com/seu-usuario/order-menu
 * Description:       Permite reordenar Páginas e Posts via arrastar-e-soltar no painel do WordPress. A ordem personalizada é aplicada automaticamente no front-end.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Seu Nome
 * Author URI:        https://seusite.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gabler-order-menu
 * Domain Path:       /languages
 *
 * @package OrderMenu
 */

defined( 'ABSPATH' ) || exit;

// ──────────────────────────────────────────────────────────────────────────────
// Constants
// ──────────────────────────────────────────────────────────────────────────────
define( 'OM_VERSION',     '1.0.0' );
define( 'OM_PLUGIN_FILE', __FILE__ );
define( 'OM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'OM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'OM_META_KEY',    '_om_menu_order' );

// ──────────────────────────────────────────────────────────────────────────────
// Bootstrap
// ──────────────────────────────────────────────────────────────────────────────
require_once OM_PLUGIN_DIR . 'includes/class-om-admin.php';
require_once OM_PLUGIN_DIR . 'includes/class-om-ajax.php';
require_once OM_PLUGIN_DIR . 'includes/class-om-frontend.php';

register_activation_hook( __FILE__,  array( 'OM_Admin', 'on_activate'   ) );
register_deactivation_hook( __FILE__, array( 'OM_Admin', 'on_deactivate' ) );

add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'gabler-order-menu', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    OM_Admin::init();
    OM_Ajax::init();
    OM_Frontend::init();
} );
