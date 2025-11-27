<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Página de ajustes del plugin en el backend.
 *
 * Permite activar/desactivar la tabla de tramos en la ficha de producto.
 */
class WPDM_Admin_Settings {

	const OPTION_SHOW_TABLE = 'wpdm_show_price_tiers_table';

	/**
	 * Registrar hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
	}

	/**
	 * Registrar el ajuste y el campo.
	 */
	public static function register_settings() {
		register_setting(
			'wpdm_wooprices_settings',
			self::OPTION_SHOW_TABLE,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		add_settings_section(
			'wpdm_wooprices_main_section',
			__( 'Woo Prices Dynamics Makito', 'woo-prices-dynamics-makito' ),
			array( __CLASS__, 'render_main_section_description' ),
			'wpdm-wooprices-settings'
		);

		add_settings_field(
			self::OPTION_SHOW_TABLE,
			__( 'Mostrar tabla de tramos en ficha de producto', 'woo-prices-dynamics-makito' ),
			array( __CLASS__, 'render_show_table_field' ),
			'wpdm-wooprices-settings',
			'wpdm_wooprices_main_section'
		);

		// Registrar opción para tabla de variaciones
		register_setting(
			'wpdm_wooprices_settings',
			WPDM_Variation_Table::OPTION_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		add_settings_field(
			WPDM_Variation_Table::OPTION_ENABLED,
			__( 'Mostrar tabla de variaciones (colores x tallas)', 'woo-prices-dynamics-makito' ),
			array( __CLASS__, 'render_variation_table_field' ),
			'wpdm-wooprices-settings',
			'wpdm_wooprices_main_section'
		);
	}

	/**
	 * Sanitizar checkbox.
	 *
	 * @param mixed $value Valor enviado.
	 *
	 * @return bool
	 */
	public static function sanitize_checkbox( $value ) {
		return (bool) $value;
	}

	/**
	 * Añadir página de ajustes bajo WooCommerce.
	 */
	public static function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Precios Makito', 'woo-prices-dynamics-makito' ),
			__( 'Precios Makito', 'woo-prices-dynamics-makito' ),
			'manage_woocommerce',
			'wpdm-wooprices-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Descripción de la sección principal.
	 */
	public static function render_main_section_description() {
		echo '<p>' . esc_html__( 'Configura cómo se muestran los tramos de precios sincronizados desde el panel externo.', 'woo-prices-dynamics-makito' ) . '</p>';
	}

	/**
	 * Campo: mostrar tabla de tramos.
	 */
	public static function render_show_table_field() {
		$value = (bool) get_option( self::OPTION_SHOW_TABLE, false );
		?>
		<label for="<?php echo esc_attr( self::OPTION_SHOW_TABLE ); ?>">
			<input type="checkbox"
				   id="<?php echo esc_attr( self::OPTION_SHOW_TABLE ); ?>"
				   name="<?php echo esc_attr( self::OPTION_SHOW_TABLE ); ?>"
				   value="1" <?php checked( $value ); ?> />
			<?php esc_html_e( 'Si se activa, se mostrará automáticamente la tabla de tramos debajo del precio en la ficha de producto.', 'woo-prices-dynamics-makito' ); ?>
		</label>
		<?php
	}

	/**
	 * Campo: mostrar tabla de variaciones.
	 */
	public static function render_variation_table_field() {
		$value = (bool) get_option( WPDM_Variation_Table::OPTION_ENABLED, false );
		?>
		<label for="<?php echo esc_attr( WPDM_Variation_Table::OPTION_ENABLED ); ?>">
			<input type="checkbox"
				   id="<?php echo esc_attr( WPDM_Variation_Table::OPTION_ENABLED ); ?>"
				   name="<?php echo esc_attr( WPDM_Variation_Table::OPTION_ENABLED ); ?>"
				   value="1" <?php checked( $value ); ?> />
			<?php esc_html_e( 'Si se activa, se mostrará una tabla interactiva (colores x tallas) para seleccionar cantidades. El precio se calculará según la suma total de todas las variaciones seleccionadas.', 'woo-prices-dynamics-makito' ); ?>
		</label>
		<?php
	}

	/**
	 * Renderizar la página de ajustes.
	 */
	public static function render_settings_page() {
		// Verificar permisos.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'woo-prices-dynamics-makito' ) );
		}

		// Verificar nonce al procesar el formulario (settings_fields lo incluye, pero verificamos explícitamente).
		if ( isset( $_POST['submit'] ) && ! empty( $_POST['option_page'] ) && 'wpdm_wooprices_settings' === $_POST['option_page'] ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'wpdm_wooprices_settings-options' ) ) {
				wp_die( esc_html__( 'Error de seguridad. Por favor, intenta de nuevo.', 'woo-prices-dynamics-makito' ) );
			}
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ajustes de Precios Makito', 'woo-prices-dynamics-makito' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'wpdm_wooprices_settings' );
				do_settings_sections( 'wpdm-wooprices-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}



