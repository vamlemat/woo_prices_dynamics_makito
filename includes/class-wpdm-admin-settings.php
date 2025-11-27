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
		
		// Registrar opción para tamaño del círculo de color
		register_setting(
			'wpdm_wooprices_settings',
			WPDM_Variation_Table::OPTION_COLOR_SWATCH_SIZE,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_swatch_size' ),
				'default'           => 36,
			)
		);
		
		add_settings_field(
			WPDM_Variation_Table::OPTION_COLOR_SWATCH_SIZE,
			__( 'Tamaño del círculo de color (px)', 'woo-prices-dynamics-makito' ),
			array( __CLASS__, 'render_color_swatch_size_field' ),
			'wpdm-wooprices-settings',
			'wpdm_wooprices_main_section'
		);
		
		// Registrar opciones para configuración de stock
		register_setting(
			'wpdm_wooprices_settings',
			WPDM_Variation_Table::OPTION_STOCK_THRESHOLD,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_stock_threshold' ),
				'default'           => 50,
			)
		);
		
		add_settings_field(
			WPDM_Variation_Table::OPTION_STOCK_THRESHOLD,
			__( 'Umbral de stock bajo', 'woo-prices-dynamics-makito' ),
			array( __CLASS__, 'render_stock_threshold_field' ),
			'wpdm-wooprices-settings',
			'wpdm_wooprices_main_section'
		);
		
		register_setting(
			'wpdm_wooprices_settings',
			WPDM_Variation_Table::OPTION_STOCK_HIGH_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_color' ),
				'default'           => '#28a745',
			)
		);
		
		add_settings_field(
			WPDM_Variation_Table::OPTION_STOCK_HIGH_COLOR,
			__( 'Color para stock alto', 'woo-prices-dynamics-makito' ),
			array( __CLASS__, 'render_stock_high_color_field' ),
			'wpdm-wooprices-settings',
			'wpdm_wooprices_main_section'
		);
		
		register_setting(
			'wpdm_wooprices_settings',
			WPDM_Variation_Table::OPTION_STOCK_LOW_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_color' ),
				'default'           => '#ff8c00',
			)
		);
		
		add_settings_field(
			WPDM_Variation_Table::OPTION_STOCK_LOW_COLOR,
			__( 'Color para stock bajo', 'woo-prices-dynamics-makito' ),
			array( __CLASS__, 'render_stock_low_color_field' ),
			'wpdm-wooprices-settings',
			'wpdm_wooprices_main_section'
		);
		
		register_setting(
			'wpdm_wooprices_settings',
			WPDM_Variation_Table::OPTION_STOCK_NONE_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_color' ),
				'default'           => '#dc3545',
			)
		);
		
		add_settings_field(
			WPDM_Variation_Table::OPTION_STOCK_NONE_COLOR,
			__( 'Color para sin stock', 'woo-prices-dynamics-makito' ),
			array( __CLASS__, 'render_stock_none_color_field' ),
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
	 * Sanitizar tamaño del círculo de color.
	 *
	 * @param mixed $value Valor enviado.
	 *
	 * @return int
	 */
	public static function sanitize_swatch_size( $value ) {
		$value = absint( $value );
		// Limitar entre 20px y 100px
		if ( $value < 20 ) {
			$value = 20;
		} elseif ( $value > 100 ) {
			$value = 100;
		}
		return $value;
	}
	
	/**
	 * Sanitizar umbral de stock.
	 *
	 * @param mixed $value Valor enviado.
	 *
	 * @return int
	 */
	public static function sanitize_stock_threshold( $value ) {
		$value = absint( $value );
		// Limitar entre 1 y 1000
		if ( $value < 1 ) {
			$value = 1;
		} elseif ( $value > 1000 ) {
			$value = 1000;
		}
		return $value;
	}
	
	/**
	 * Sanitizar color hexadecimal.
	 *
	 * @param mixed $value Valor enviado.
	 *
	 * @return string
	 */
	public static function sanitize_color( $value ) {
		$value = sanitize_text_field( $value );
		// Validar formato hexadecimal
		if ( preg_match( '/^#[0-9A-Fa-f]{6}$/', $value ) ) {
			return $value;
		}
		// Si no es válido, devolver el valor por defecto según el campo
		return '#28a745'; // Verde por defecto
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
	 * Campo: tamaño del círculo de color.
	 */
	public static function render_color_swatch_size_field() {
		$value = absint( get_option( WPDM_Variation_Table::OPTION_COLOR_SWATCH_SIZE, 36 ) );
		?>
		<input type="number"
			   id="<?php echo esc_attr( WPDM_Variation_Table::OPTION_COLOR_SWATCH_SIZE ); ?>"
			   name="<?php echo esc_attr( WPDM_Variation_Table::OPTION_COLOR_SWATCH_SIZE ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   min="20"
			   max="100"
			   step="1"
			   style="width: 80px;" />
		<span class="description">
			<?php esc_html_e( 'Tamaño en píxeles del círculo de color/imagen en la tabla de variaciones (mínimo: 20px, máximo: 100px). Valor por defecto: 36px.', 'woo-prices-dynamics-makito' ); ?>
		</span>
		<?php
	}
	
	/**
	 * Campo: umbral de stock bajo.
	 */
	public static function render_stock_threshold_field() {
		$value = absint( get_option( WPDM_Variation_Table::OPTION_STOCK_THRESHOLD, 50 ) );
		?>
		<input type="number"
			   id="<?php echo esc_attr( WPDM_Variation_Table::OPTION_STOCK_THRESHOLD ); ?>"
			   name="<?php echo esc_attr( WPDM_Variation_Table::OPTION_STOCK_THRESHOLD ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   min="1"
			   max="1000"
			   step="1"
			   style="width: 80px;" />
		<span class="description">
			<?php esc_html_e( 'Cantidad máxima de unidades para considerar stock bajo. Valores iguales o menores mostrarán el color de stock bajo. Valor por defecto: 50.', 'woo-prices-dynamics-makito' ); ?>
		</span>
		<?php
	}
	
	/**
	 * Campo: color para stock alto.
	 */
	public static function render_stock_high_color_field() {
		$value = get_option( WPDM_Variation_Table::OPTION_STOCK_HIGH_COLOR, '#28a745' );
		?>
		<input type="color"
			   id="<?php echo esc_attr( WPDM_Variation_Table::OPTION_STOCK_HIGH_COLOR ); ?>"
			   name="<?php echo esc_attr( WPDM_Variation_Table::OPTION_STOCK_HIGH_COLOR ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   style="width: 80px; height: 35px; vertical-align: middle;" />
		<input type="text"
			   value="<?php echo esc_attr( $value ); ?>"
			   onchange="document.getElementById('<?php echo esc_attr( WPDM_Variation_Table::OPTION_STOCK_HIGH_COLOR ); ?>').value = this.value"
			   pattern="^#[0-9A-Fa-f]{6}$"
			   placeholder="#28a745"
			   style="width: 100px; margin-left: 10px;" />
		<span class="description">
			<?php esc_html_e( 'Color para mostrar cuando hay mucho stock (por encima del umbral). Valor por defecto: #28a745 (verde).', 'woo-prices-dynamics-makito' ); ?>
		</span>
		<?php
	}
	
	/**
	 * Campo: color para stock bajo.
	 */
	public static function render_stock_low_color_field() {
		$value = get_option( WPDM_Variation_Table::OPTION_STOCK_LOW_COLOR, '#ff8c00' );
		?>
		<input type="color"
			   id="<?php echo esc_attr( WPDM_Variation_Table::OPTION_STOCK_LOW_COLOR ); ?>"
			   name="<?php echo esc_attr( WPDM_Variation_Table::OPTION_STOCK_LOW_COLOR ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   style="width: 80px; height: 35px; vertical-align: middle;" />
		<input type="text"
			   value="<?php echo esc_attr( $value ); ?>"
			   onchange="document.getElementById('<?php echo esc_attr( WPDM_Variation_Table::OPTION_STOCK_LOW_COLOR ); ?>').value = this.value"
			   pattern="^#[0-9A-Fa-f]{6}$"
			   placeholder="#ff8c00"
			   style="width: 100px; margin-left: 10px;" />
		<span class="description">
			<?php esc_html_e( 'Color para mostrar cuando hay poco stock (igual o menor al umbral). Valor por defecto: #ff8c00 (naranja).', 'woo-prices-dynamics-makito' ); ?>
		</span>
		<?php
	}
	
	/**
	 * Campo: color para sin stock.
	 */
	public static function render_stock_none_color_field() {
		$value = get_option( WPDM_Variation_Table::OPTION_STOCK_NONE_COLOR, '#dc3545' );
		?>
		<input type="color"
			   id="<?php echo esc_attr( WPDM_Variation_Table::OPTION_STOCK_NONE_COLOR ); ?>"
			   name="<?php echo esc_attr( WPDM_Variation_Table::OPTION_STOCK_NONE_COLOR ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   style="width: 80px; height: 35px; vertical-align: middle;" />
		<input type="text"
			   value="<?php echo esc_attr( $value ); ?>"
			   onchange="document.getElementById('<?php echo esc_attr( WPDM_Variation_Table::OPTION_STOCK_NONE_COLOR ); ?>').value = this.value"
			   pattern="^#[0-9A-Fa-f]{6}$"
			   placeholder="#dc3545"
			   style="width: 100px; margin-left: 10px;" />
		<span class="description">
			<?php esc_html_e( 'Color para mostrar cuando no hay stock (0 unidades). Valor por defecto: #dc3545 (rojo).', 'woo-prices-dynamics-makito' ); ?>
		</span>
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



