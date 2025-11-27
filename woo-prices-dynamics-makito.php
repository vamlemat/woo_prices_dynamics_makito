<?php
/**
 * Plugin Name:       Woo Prices Dynamics Makito
 * Plugin URI:        https://github.com/vamlemat/publicmar-estructura
 * Description:       Aplica precios por tramos (price_tiers) a productos WooCommerce y al carrito, usando datos sincronizados desde un panel externo (Makito y otros).
 * Version:           1.3.7
 * Author:            atech / vamlemat
 * Text Domain:       woo-prices-dynamics-makito
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * WC requires at least: 3.0
 * WC tested up to:   9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Definir constantes básicas del plugin.
if ( ! defined( 'WPDM_WOOPRICES_VERSION' ) ) {
	define( 'WPDM_WOOPRICES_VERSION', '1.3.7' );
}

if ( ! defined( 'WPDM_WOOPRICES_PLUGIN_FILE' ) ) {
	define( 'WPDM_WOOPRICES_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WPDM_WOOPRICES_PLUGIN_DIR' ) ) {
	define( 'WPDM_WOOPRICES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Cargar archivos de clases.
 */
require_once WPDM_WOOPRICES_PLUGIN_DIR . 'includes/class-wpdm-logger.php';
require_once WPDM_WOOPRICES_PLUGIN_DIR . 'includes/class-wpdm-price-tiers.php';
require_once WPDM_WOOPRICES_PLUGIN_DIR . 'includes/class-wpdm-cart-adjustments.php';
require_once WPDM_WOOPRICES_PLUGIN_DIR . 'includes/class-wpdm-frontend.php';
require_once WPDM_WOOPRICES_PLUGIN_DIR . 'includes/class-wpdm-order-meta.php';
require_once WPDM_WOOPRICES_PLUGIN_DIR . 'includes/class-wpdm-admin-settings.php';
require_once WPDM_WOOPRICES_PLUGIN_DIR . 'includes/class-wpdm-variation-table.php';

/**
 * Declarar compatibilidad con características de WooCommerce.
 */
function wpdm_wooprices_declare_compatibility() {
	// Declarar compatibilidad con HPOS (High-Performance Order Storage).
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WPDM_WOOPRICES_PLUGIN_FILE, true );
	}
}
add_action( 'before_woocommerce_init', 'wpdm_wooprices_declare_compatibility' );

/**
 * Cargar el text domain del plugin para traducciones.
 */
function wpdm_wooprices_load_textdomain() {
	load_plugin_textdomain(
		'woo-prices-dynamics-makito',
		false,
		dirname( plugin_basename( WPDM_WOOPRICES_PLUGIN_FILE ) ) . '/languages/'
	);
}
add_action( 'plugins_loaded', 'wpdm_wooprices_load_textdomain', 5 );

/**
 * Verificar que WooCommerce esté activo y en versión compatible.
 */
function wpdm_wooprices_check_requirements() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wpdm_wooprices_missing_woocommerce_notice' );
		return false;
	}

	// Verificar versión mínima de WooCommerce (3.0+)
	if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.0', '<' ) ) {
		add_action( 'admin_notices', 'wpdm_wooprices_old_woocommerce_notice' );
		return false;
	}

	return true;
}

/**
 * Aviso si WooCommerce no está activo.
 */
function wpdm_wooprices_missing_woocommerce_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Woo Prices Dynamics Makito requiere que WooCommerce esté instalado y activo.', 'woo-prices-dynamics-makito' ); ?></p>
	</div>
	<?php
}

/**
 * Aviso si WooCommerce es muy antiguo.
 */
function wpdm_wooprices_old_woocommerce_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Woo Prices Dynamics Makito requiere WooCommerce 3.0 o superior.', 'woo-prices-dynamics-makito' ); ?></p>
	</div>
	<?php
}

/**
 * Inicializar el plugin una vez cargados los plugins (para asegurar WooCommerce).
 */
function wpdm_wooprices_init() {
	// Verificar requisitos.
	if ( ! wpdm_wooprices_check_requirements() ) {
		return;
	}

	// Inicializar logger primero (deshabilitado por defecto en producción).
	WPDM_Logger::init();

	// Inicializar componentes.
	WPDM_Price_Tiers::init();
	WPDM_Cart_Adjustments::init();
	WPDM_Frontend::init();
	WPDM_Order_Meta::init();
	WPDM_Admin_Settings::init();
	WPDM_Variation_Table::init();
}
add_action( 'plugins_loaded', 'wpdm_wooprices_init' );


