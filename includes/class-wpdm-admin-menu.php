<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menú principal del plugin en el backend.
 */
class WPDM_Admin_Menu {

	const MENU_SLUG  = 'wpdm-dashboard';
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Registrar hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_main_menu' ), 5 );
	}

	/**
	 * Crear apartado propio del plugin.
	 */
	public static function add_main_menu() {
		add_menu_page(
			__( 'Woo Prices Dynamics Makito', 'woo-prices-dynamics-makito' ),
			__( 'Makito', 'woo-prices-dynamics-makito' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-products',
			56
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Resumen', 'woo-prices-dynamics-makito' ),
			__( 'Resumen', 'woo-prices-dynamics-makito' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Shortcodes', 'woo-prices-dynamics-makito' ),
			__( 'Shortcodes', 'woo-prices-dynamics-makito' ),
			self::CAPABILITY,
			'wpdm-shortcodes',
			array( __CLASS__, 'render_shortcodes_page' )
		);
	}

	/**
	 * Renderizar página inicial del apartado.
	 */
	public static function render_dashboard_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'woo-prices-dynamics-makito' ) );
		}

		$links = array(
			array(
				'title'       => __( 'Ajustes', 'woo-prices-dynamics-makito' ),
				'description' => __( 'Configura tramos, tabla de variaciones, swatches y colores de stock.', 'woo-prices-dynamics-makito' ),
				'url'         => admin_url( 'admin.php?page=wpdm-wooprices-settings' ),
			),
			array(
				'title'       => __( 'Shortcodes', 'woo-prices-dynamics-makito' ),
				'description' => __( 'Consulta los shortcodes disponibles para usarlos en fichas, tabs, plantillas o constructores visuales.', 'woo-prices-dynamics-makito' ),
				'url'         => admin_url( 'admin.php?page=wpdm-shortcodes' ),
			),
			array(
				'title'       => __( 'Imágenes de personalización', 'woo-prices-dynamics-makito' ),
				'description' => __( 'Consulta y gestiona los archivos subidos por los clientes.', 'woo-prices-dynamics-makito' ),
				'url'         => admin_url( 'admin.php?page=wpdm-customization-images' ),
			),
			array(
				'title'       => __( 'Logs', 'woo-prices-dynamics-makito' ),
				'description' => __( 'Activa el logging y revisa eventos técnicos cuando sea necesario.', 'woo-prices-dynamics-makito' ),
				'url'         => admin_url( 'admin.php?page=wpdm-logs' ),
			),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Woo Prices Dynamics Makito', 'woo-prices-dynamics-makito' ); ?></h1>
			<p><?php esc_html_e( 'Panel central del plugin para precios por tramos, variaciones y personalización.', 'woo-prices-dynamics-makito' ); ?></p>

			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-top:20px;max-width:980px;">
				<?php foreach ( $links as $link ) : ?>
					<div class="card" style="max-width:none;margin:0;">
						<h2 style="margin-top:0;"><?php echo esc_html( $link['title'] ); ?></h2>
						<p><?php echo esc_html( $link['description'] ); ?></p>
						<p>
							<a class="button button-primary" href="<?php echo esc_url( $link['url'] ); ?>">
								<?php esc_html_e( 'Abrir', 'woo-prices-dynamics-makito' ); ?>
							</a>
						</p>
					</div>
				<?php endforeach; ?>
				</div>
			</div>
			<?php
		}

	/**
	 * Renderizar página de shortcodes disponibles.
	 */
	public static function render_shortcodes_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'woo-prices-dynamics-makito' ) );
		}

		$shortcodes = array(
			array(
				'title'       => __( 'Tabla de precios por cantidad', 'woo-prices-dynamics-makito' ),
				'shortcode'   => '[wpdm_price_tiers_table]',
				'variants'    => array(
					'[wpdm_price_tiers_table product_id="123"]',
				),
				'description' => __( 'Muestra la banda responsive de precios por tramos del producto.', 'woo-prices-dynamics-makito' ),
			),
			array(
				'title'       => __( 'Tabla de variaciones', 'woo-prices-dynamics-makito' ),
				'shortcode'   => '[wpdm_variation_table]',
				'variants'    => array(
					'[wpdm_variation_table product_id="123"]',
				),
				'description' => __( 'Muestra la tabla de cantidades por color y talla para productos variables.', 'woo-prices-dynamics-makito' ),
			),
			array(
				'title'       => __( 'Áreas de marcaje', 'woo-prices-dynamics-makito' ),
				'shortcode'   => '[wpdm_marking_areas]',
				'variants'    => array(
					'[wpdm_marking_areas product_id="123"]',
					'[wpdm_marking_areas title="Marcaje"]',
				),
				'description' => __( 'Muestra las áreas de impresión, técnicas, medidas, límites de color e imágenes del producto.', 'woo-prices-dynamics-makito' ),
			),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Shortcodes Makito', 'woo-prices-dynamics-makito' ); ?></h1>
			<p><?php esc_html_e( 'Referencia rápida de shortcodes disponibles para insertar módulos del plugin en páginas, tabs, plantillas o constructores visuales.', 'woo-prices-dynamics-makito' ); ?></p>

			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-top:20px;max-width:1180px;">
				<?php foreach ( $shortcodes as $item ) : ?>
					<div class="card" style="max-width:none;margin:0;">
						<h2 style="margin-top:0;"><?php echo esc_html( $item['title'] ); ?></h2>
						<p><?php echo esc_html( $item['description'] ); ?></p>

						<label style="display:block;font-weight:600;margin-bottom:6px;"><?php esc_html_e( 'Uso principal', 'woo-prices-dynamics-makito' ); ?></label>
						<input type="text" class="regular-text code" readonly value="<?php echo esc_attr( $item['shortcode'] ); ?>" onclick="this.select();" style="width:100%;max-width:100%;">

						<?php if ( ! empty( $item['variants'] ) ) : ?>
							<label style="display:block;font-weight:600;margin:14px 0 6px;"><?php esc_html_e( 'Variantes', 'woo-prices-dynamics-makito' ); ?></label>
							<?php foreach ( $item['variants'] as $variant ) : ?>
								<input type="text" class="regular-text code" readonly value="<?php echo esc_attr( $variant ); ?>" onclick="this.select();" style="width:100%;max-width:100%;margin-bottom:8px;">
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<p style="margin-top:18px;color:#646970;">
				<?php esc_html_e( 'Consejo: haz clic dentro de cualquier campo para seleccionarlo y copiarlo rápidamente.', 'woo-prices-dynamics-makito' ); ?>
			</p>
		</div>
		<?php
	}
}
