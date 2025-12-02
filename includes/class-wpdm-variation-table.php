<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tabla de variaciones para productos personalizados (colores x tallas).
 * 
 * Permite seleccionar cantidades en formato tabla y calcular precios
 * basados en la suma total de todas las variaciones.
 */
class WPDM_Variation_Table {

	/**
	 * Opción para activar/desactivar la tabla de variaciones.
	 */
	const OPTION_ENABLED = 'wpdm_variation_table_enabled';
	
	/**
	 * Opción para el tamaño del círculo de color (en píxeles).
	 */
	const OPTION_COLOR_SWATCH_SIZE = 'wpdm_color_swatch_size';
	
	/**
	 * Opción para el umbral de stock bajo.
	 */
	const OPTION_STOCK_THRESHOLD = 'wpdm_stock_threshold';
	
	/**
	 * Opción para el color de stock alto.
	 */
	const OPTION_STOCK_HIGH_COLOR = 'wpdm_stock_high_color';
	
	/**
	 * Opción para el color de stock bajo.
	 */
	const OPTION_STOCK_LOW_COLOR = 'wpdm_stock_low_color';
	
	/**
	 * Opción para el color de sin stock.
	 */
	const OPTION_STOCK_NONE_COLOR = 'wpdm_stock_none_color';

	/**
	 * Flag para controlar si el script ya se ha cargado.
	 *
	 * @var bool
	 */
	private static $script_loaded = false;

	/**
	 * Registrar hooks.
	 */
	public static function init() {
		// Solo para productos variables - renderizado automático
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_variation_table' ), 15 );
		add_action( 'wp_footer', array( __CLASS__, 'output_variation_table_script' ), 30 );
		add_action( 'wp_ajax_wpdm_calculate_table_price', array( __CLASS__, 'ajax_calculate_table_price' ) );
		add_action( 'wp_ajax_nopriv_wpdm_calculate_table_price', array( __CLASS__, 'ajax_calculate_table_price' ) );
		add_action( 'wp_ajax_wpdm_add_table_to_cart', array( __CLASS__, 'ajax_add_table_to_cart' ) );
		add_action( 'wp_ajax_nopriv_wpdm_add_table_to_cart', array( __CLASS__, 'ajax_add_table_to_cart' ) );
		
		// Añadir nonce para AJAX
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'localize_script' ) );
		
		// Shortcode para insertar manualmente
		add_shortcode( 'wpdm_variation_table', array( __CLASS__, 'shortcode_variation_table' ) );
	}

	/**
	 * Verificar si la tabla de variaciones está habilitada.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * Verificar si hay una tabla de variaciones en la página actual.
	 * 
	 * @return bool
	 */
	private static function has_variation_table_on_page() {
		// Verificar si hay un shortcode en el contenido de la página
		global $post;
		if ( $post && has_shortcode( $post->post_content, 'wpdm_variation_table' ) ) {
			return true;
		}

		// Verificar si hay una tabla en el DOM (para casos dinámicos)
		// Esto se verifica en el frontend, así que retornamos false aquí
		return false;
	}

	/**
	 * Shortcode: [wpdm_variation_table]
	 * 
	 * Permite insertar la tabla de variaciones manualmente.
	 * 
	 * Atributos:
	 * - product_id: ID del producto (opcional, usa el producto actual si no se especifica)
	 * 
	 * @param array $atts Atributos del shortcode
	 * @return string HTML de la tabla
	 */
	public static function shortcode_variation_table( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
			),
			$atts,
			'wpdm_variation_table'
		);

		$product_id = absint( $atts['product_id'] );

		// Si no se especifica product_id, intentar obtener del producto actual
		if ( $product_id <= 0 ) {
			global $product;
			if ( $product && is_a( $product, 'WC_Product' ) ) {
				$product_id = absint( $product->get_id() );
			}
		}

		if ( $product_id <= 0 ) {
			return '<p>' . esc_html__( 'Error: No se pudo determinar el producto.', 'woo-prices-dynamics-makito' ) . '</p>';
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return '<p>' . esc_html__( 'Este shortcode solo funciona con productos variables.', 'woo-prices-dynamics-makito' ) . '</p>';
		}

		// Asegurar que el script se cargue en el footer
		add_action( 'wp_footer', array( __CLASS__, 'output_variation_table_script' ), 30 );

		// Renderizar la tabla
		return self::get_variation_table_html( $product );
	}

	/**
	 * Renderizar la tabla de variaciones automáticamente (hook).
	 */
	public static function render_variation_table() {
		if ( ! self::is_enabled() ) {
			return;
		}

		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		// Solo para productos variables
		if ( ! $product->is_type( 'variable' ) ) {
			return;
		}

		// Renderizar usando el método común
		echo self::get_variation_table_html( $product );
	}

	/**
	 * Generar el HTML de la tabla de variaciones.
	 * 
	 * @param WC_Product_Variable $product Producto variable
	 * @return string HTML de la tabla
	 */
	private static function get_variation_table_html( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}

		// Solo para productos variables
		if ( ! $product->is_type( 'variable' ) ) {
			return '';
		}

		// Obtener atributos del producto
		$attributes = $product->get_variation_attributes();
		
		if ( empty( $attributes ) || count( $attributes ) < 2 ) {
			return; // Necesitamos al menos 2 atributos (color y talla)
		}

		// Obtener todas las variaciones disponibles
		$variations = $product->get_available_variations();
		
		if ( empty( $variations ) ) {
			return;
		}

		// Identificar qué atributo es color y cuál es talla
		// Prioridad: si existe pa_color, usarlo como FILA (colores en filas)
		// Las tallas irán en las columnas para evitar tablas demasiado anchas
		$attribute_keys = array_keys( $attributes );
		$row_attribute = '';
		$col_attribute = '';
		
		// Buscar pa_color primero - será la FILA (colores)
		if ( in_array( 'pa_color', $attribute_keys, true ) ) {
			$row_attribute = 'pa_color';
			// El otro atributo será la columna (tallas)
			foreach ( $attribute_keys as $key ) {
				if ( $key !== 'pa_color' ) {
					$col_attribute = $key;
					break;
				}
			}
		} else {
			// Por defecto: primer atributo = filas (colores), segundo = columnas (tallas)
			$row_attribute = isset( $attribute_keys[0] ) ? $attribute_keys[0] : '';
			$col_attribute = isset( $attribute_keys[1] ) ? $attribute_keys[1] : '';
		}

		// Si no hay suficientes atributos, no mostrar tabla
		if ( empty( $row_attribute ) || empty( $col_attribute ) ) {
			return;
		}

		// Obtener valores únicos de cada atributo
		$row_values = isset( $attributes[ $row_attribute ] ) ? $attributes[ $row_attribute ] : array();
		$col_values = isset( $attributes[ $col_attribute ] ) ? $attributes[ $col_attribute ] : array();

		if ( empty( $row_values ) || empty( $col_values ) ) {
			return;
		}

		// Crear mapa de variaciones: [row_value][col_value] => variation_id
		$variation_map = array();
		foreach ( $variations as $variation_data ) {
			$variation_id = isset( $variation_data['variation_id'] ) ? absint( $variation_data['variation_id'] ) : 0;
			if ( $variation_id <= 0 ) {
				continue;
			}

			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_purchasable() ) {
				continue;
			}

			$variation_attributes = $variation->get_attributes();
			$row_value = isset( $variation_attributes[ $row_attribute ] ) ? $variation_attributes[ $row_attribute ] : '';
			$col_value = isset( $variation_attributes[ $col_attribute ] ) ? $variation_attributes[ $col_attribute ] : '';

			if ( ! empty( $row_value ) && ! empty( $col_value ) ) {
				if ( ! isset( $variation_map[ $row_value ] ) ) {
					$variation_map[ $row_value ] = array();
				}
				$variation_map[ $row_value ][ $col_value ] = $variation_id;
			}
		}

		if ( empty( $variation_map ) ) {
			return;
		}

		// Obtener nombres de atributos para mostrar
		$row_label = wc_attribute_label( $row_attribute, $product );
		$col_label = wc_attribute_label( $col_attribute, $product );

		// Obtener taxonomy del atributo de FILA (colores) para buscar imágenes/colores
		$row_taxonomy = wc_attribute_taxonomy_name( str_replace( 'pa_', '', $row_attribute ) );
		if ( ! taxonomy_exists( $row_taxonomy ) ) {
			// Si no existe como taxonomy, intentar con el nombre tal cual
			$row_taxonomy = $row_attribute;
		}

		// Obtener tramos de precio del producto
		$price_tiers = WPDM_Price_Tiers::get_price_tiers( $product->get_id() );

		// Obtener configuración de formato de moneda de WooCommerce
		$currency_symbol = get_woocommerce_currency_symbol();
		$currency_pos = get_option( 'woocommerce_currency_pos', 'left' );
		$price_decimals = wc_get_price_decimals();
		$price_decimal_sep = wc_get_price_decimal_separator();
		$price_thousand_sep = wc_get_price_thousand_separator();
		
		// Obtener tamaño del círculo de color desde configuración
		$swatch_size = absint( get_option( self::OPTION_COLOR_SWATCH_SIZE, 36 ) );

		ob_start();
		?>
		<div class="wpdm-variation-table-wrapper">
			<h3 class="wpdm-variation-table-title"><?php echo esc_html( sprintf( __( 'Selecciona cantidades (%s x %s)', 'woo-prices-dynamics-makito' ), $row_label, $col_label ) ); ?></h3>
			
			<div class="wpdm-variation-table-container">
				<table class="wpdm-variation-table">
					<thead>
						<tr>
							<th class="wpdm-table-header-row">
								<?php echo esc_html( $row_label ); ?> \ <?php echo esc_html( $col_label ); ?>
							</th>
							<?php foreach ( $col_values as $col_value ) : ?>
								<th class="wpdm-table-header-col">
									<?php echo esc_html( $col_value ); ?>
								</th>
							<?php endforeach; ?>
							<th class="wpdm-table-header-total">
								<?php esc_html_e( 'Total', 'woo-prices-dynamics-makito' ); ?>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $row_values as $row_value ) : 
							// Obtener el término para obtener su nombre (más limpio que el slug)
							$term = get_term_by( 'slug', $row_value, $row_taxonomy );
							$raw_term_name = $term && ! is_wp_error( $term ) ? $term->name : $row_value;
							
							// Limpiar el nombre del color (eliminar prefijos y duplicados)
							$term_name = self::clean_color_name( $raw_term_name );
							
							// Obtener imagen o color del término o de la variación (colores en filas)
							$color_data = self::get_color_swatch_data( $row_taxonomy, $row_value, $term_name, $variation_map, $row_attribute );
						?>
							<tr>
								<td class="wpdm-table-row-label">
									<div class="wpdm-color-header">
										<?php if ( ! empty( $color_data['image'] ) ) : ?>
											<img src="<?php echo esc_url( $color_data['image'] ); ?>" alt="<?php echo esc_attr( $term_name ); ?>" class="wpdm-color-image" />
										<?php elseif ( ! empty( $color_data['color'] ) ) : ?>
											<span class="wpdm-color-swatch" style="background-color: <?php echo esc_attr( $color_data['color'] ); ?>;"></span>
										<?php endif; ?>
										<span class="wpdm-color-name"><?php echo esc_html( $term_name ); ?></span>
									</div>
								</td>
								<?php 
								$row_total = 0;
								foreach ( $col_values as $col_value ) : 
									$variation_id = isset( $variation_map[ $row_value ][ $col_value ] ) ? absint( $variation_map[ $row_value ][ $col_value ] ) : 0;
									$is_available = $variation_id > 0;
								?>
									<td class="wpdm-table-cell">
										<?php if ( $is_available ) : 
											// Obtener el stock de la variación
											$variation_obj = wc_get_product( $variation_id );
											$stock_quantity = '';
											$stock_class = '';
											$stock_text = '';
											// Obtener umbral desde configuración
											$stock_threshold = absint( get_option( self::OPTION_STOCK_THRESHOLD, 50 ) );
											
											if ( $variation_obj ) {
												if ( $variation_obj->managing_stock() ) {
													$stock_qty = $variation_obj->get_stock_quantity();
													$stock_quantity = $stock_qty !== null ? absint( $stock_qty ) : 0;
													
													// Determinar clase y texto según el stock
													if ( $stock_quantity === 0 ) {
														$stock_class = 'wpdm-stock-none';
														$stock_text = esc_html__( 'NO', 'woo-prices-dynamics-makito' );
													} elseif ( $stock_quantity <= $stock_threshold ) {
														$stock_class = 'wpdm-stock-low';
														/* translators: %d: stock quantity */
														$stock_text = sprintf( esc_html__( 'Stock: %d', 'woo-prices-dynamics-makito' ), $stock_quantity );
													} else {
														$stock_class = 'wpdm-stock-high';
														/* translators: %d: stock quantity */
														$stock_text = sprintf( esc_html__( 'Stock: %d', 'woo-prices-dynamics-makito' ), $stock_quantity );
													}
												} elseif ( $variation_obj->get_stock_status() === 'instock' ) {
													// Si no gestiona stock pero está en stock, mostrar como disponible
													$stock_quantity = '∞';
													$stock_class = 'wpdm-stock-high';
													$stock_text = esc_html__( 'Stock: ∞', 'woo-prices-dynamics-makito' );
												} else {
													$stock_quantity = 0;
													$stock_class = 'wpdm-stock-none';
													$stock_text = esc_html__( 'NO', 'woo-prices-dynamics-makito' );
												}
											}
										?>
											<div class="wpdm-cell-content">
												<input 
													type="number" 
													class="wpdm-table-qty-input" 
													data-row="<?php echo esc_attr( $row_value ); ?>"
													data-col="<?php echo esc_attr( $col_value ); ?>"
													data-variation-id="<?php echo esc_attr( $variation_id ); ?>"
													min="0" 
													step="1" 
													value="0" 
												/>
												<?php if ( $stock_text !== '' ) : ?>
													<span class="wpdm-stock-info <?php echo esc_attr( $stock_class ); ?>">
														<?php echo $stock_text; ?>
													</span>
												<?php endif; ?>
											</div>
										<?php else : ?>
											<span class="wpdm-table-unavailable">—</span>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
								<td class="wpdm-row-total">
									0
								</td>
							</tr>
						<?php endforeach; ?>
						<tr class="wpdm-table-totals-row">
							<td class="wpdm-table-totals-label">
								<?php esc_html_e( 'Total', 'woo-prices-dynamics-makito' ); ?>
							</td>
							<?php foreach ( $col_values as $col_value ) : ?>
								<td class="wpdm-col-total">
									0
								</td>
							<?php endforeach; ?>
							<td class="wpdm-grand-total">
								0
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="wpdm-table-summary">
				<div class="wpdm-table-total-qty">
					<strong><?php esc_html_e( 'Cantidad total:', 'woo-prices-dynamics-makito' ); ?></strong> 
					<span class="wpdm-total-quantity">0</span>
				</div>
				<div class="wpdm-table-price-info">
					<strong><?php esc_html_e( 'Precio unitario (según tramo):', 'woo-prices-dynamics-makito' ); ?></strong> 
					<span class="wpdm-unit-price">—</span>
				</div>
				<div class="wpdm-table-total-price">
					<strong><?php esc_html_e( 'Precio total:', 'woo-prices-dynamics-makito' ); ?></strong> 
					<span class="wpdm-total-price">—</span>
				</div>
			</div>

			<div class="wpdm-table-buttons-wrapper" style="display: flex; gap: 10px; justify-content: center; margin-top: 1em;">
				<button 
					type="button" 
					class="wpdm-add-table-to-cart button alt" 
					data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
				>
					<?php esc_html_e( 'Añadir sin personalizar', 'woo-prices-dynamics-makito' ); ?>
				</button>

				<?php
				// Añadir botón de personalización si la clase existe
				if ( class_exists( 'WPDM_Customization_Frontend' ) ) {
					?>
					<button 
						type="button" 
						class="wpdm-add-customized-to-cart button alt" 
						data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
						disabled
					>
						<?php esc_html_e( 'Añadir con personalización', 'woo-prices-dynamics-makito' ); ?>
					</button>
					<?php
				}
				?>
			</div>

			<input type="hidden" class="wpdm-table-data" value="<?php echo esc_attr( wp_json_encode( array(
				'product_id' => $product->get_id(),
				'price_tiers' => $price_tiers,
				'row_attribute' => $row_attribute,
				'col_attribute' => $col_attribute,
				'variation_attributes' => self::get_variation_attributes_map( $variation_map, $row_attribute, $col_attribute ),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wpdm_table_nonce' ),
				'currency_config' => array(
					'symbol' => $currency_symbol,
					'position' => $currency_pos,
					'decimals' => $price_decimals,
					'decimalSep' => $price_decimal_sep,
					'thousandSep' => $price_thousand_sep,
				),
			) ) ); ?>" />
		</div>

		<style>
			:root {
				--wpdm-color-primary: var(--e-global-color-primary, #6EC1E4);
				--wpdm-color-secondary: var(--e-global-color-secondary, #54595F);
				--wpdm-color-text: var(--e-global-color-text, #7A7A7A);
				--wpdm-color-accent: var(--e-global-color-accent, #61CE70);
				--wpdm-color-bg-light: var(--e-global-color-5938fdc, #F1F1F1);
				--wpdm-color-blue-dark: var(--e-global-color-90d3021, #0464AC);
				--wpdm-color-blue-darker: var(--e-global-color-5273eb1, #061B46);
				--wpdm-color-white: var(--e-global-color-1e99445, #FFFFFF);
			}
			
			.wpdm-variation-table-wrapper {
				clear: both;
				margin: 2em 0;
			}
			
			.wpdm-variation-table-title {
				font-size: 1.25em;
				font-weight: 500;
				margin-bottom: 1em;
				color: var(--wpdm-color-secondary);
			}
			
			.wpdm-variation-table-container {
				overflow-x: auto;
				margin: 1.5em 0;
				-webkit-overflow-scrolling: touch;
			}
			
			.wpdm-variation-table {
				width: 100%;
				border-collapse: separate;
				border-spacing: 0;
				background: var(--wpdm-color-white);
				border-radius: 8px;
				overflow: hidden;
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
			}
			
			.wpdm-variation-table thead {
				background: linear-gradient(135deg, var(--wpdm-color-blue-dark) 0%, var(--wpdm-color-blue-darker) 100%);
			}
			
			.wpdm-variation-table th {
				padding: 14px 12px;
				text-align: center;
				font-weight: 500;
				font-size: 0.9em;
				color: var(--wpdm-color-white);
				text-transform: uppercase;
				letter-spacing: 0.5px;
				border-right: 1px solid rgba(255, 255, 255, 0.2);
			}
			
			.wpdm-variation-table th.wpdm-table-header-col {
				padding: 12px 8px;
				vertical-align: middle;
			}
			
			.wpdm-color-header {
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				gap: 8px;
				padding: 8px 4px;
			}
			
			.wpdm-color-image {
				width: <?php echo esc_attr( $swatch_size ); ?>px;
				height: <?php echo esc_attr( $swatch_size ); ?>px;
				border-radius: 50%;
				object-fit: cover;
				border: 3px solid rgba(255, 255, 255, 0.4);
				box-shadow: 0 3px 8px rgba(0, 0, 0, 0.25), inset 0 1px 2px rgba(255, 255, 255, 0.1);
				transition: transform 0.2s ease, box-shadow 0.2s ease;
				flex-shrink: 0;
			}
			
			.wpdm-color-image:hover {
				transform: scale(1.05);
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3), inset 0 1px 2px rgba(255, 255, 255, 0.15);
			}
			
			.wpdm-color-swatch {
				width: <?php echo esc_attr( $swatch_size ); ?>px;
				height: <?php echo esc_attr( $swatch_size ); ?>px;
				border-radius: 50%;
				border: 3px solid rgba(255, 255, 255, 0.4);
				box-shadow: 0 3px 8px rgba(0, 0, 0, 0.25), inset 0 1px 2px rgba(255, 255, 255, 0.1);
				display: block;
				flex-shrink: 0;
				transition: transform 0.2s ease, box-shadow 0.2s ease;
			}
			
			.wpdm-color-swatch:hover {
				transform: scale(1.05);
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3), inset 0 1px 2px rgba(255, 255, 255, 0.15);
			}
			
			.wpdm-color-name {
				font-size: 0.65em;
				font-weight: 400;
				line-height: 1.3;
				color: rgba(255, 255, 255, 0.9);
				text-align: center;
				margin-top: 2px;
				word-break: break-word;
				max-width: 100%;
			}
			
			.wpdm-variation-table th:last-child {
				border-right: none;
			}
			
			.wpdm-variation-table th.wpdm-table-header-row {
				text-align: left;
				background: rgba(255, 255, 255, 0.15);
				font-weight: 600;
			}
			
			.wpdm-variation-table tbody tr {
				transition: background-color 0.2s ease;
			}
			
			.wpdm-variation-table tbody tr:hover {
				background-color: var(--wpdm-color-bg-light);
			}
			
			.wpdm-variation-table tbody tr:last-child {
				border-bottom: none;
			}
			
			.wpdm-variation-table td {
				padding: 12px;
				border-bottom: 1px solid rgba(0, 0, 0, 0.08);
				border-right: 1px solid rgba(0, 0, 0, 0.08);
				text-align: center;
				font-size: 0.9em;
				color: var(--wpdm-color-secondary);
			}
			
			
			.wpdm-variation-table td:last-child {
				border-right: none;
			}
			
			.wpdm-variation-table .wpdm-table-row-label {
				background: var(--wpdm-color-bg-light);
				font-weight: 500;
				text-align: left;
				color: var(--wpdm-color-secondary);
				min-width: 180px;
				vertical-align: middle;
				padding: 12px 16px;
			}
			
			.wpdm-variation-table .wpdm-table-row-label .wpdm-color-header {
				display: flex;
				flex-direction: row;
				align-items: center;
				gap: 12px;
				justify-content: flex-start;
			}
			
			.wpdm-variation-table .wpdm-table-row-label .wpdm-color-image,
			.wpdm-variation-table .wpdm-table-row-label .wpdm-color-swatch {
				flex-shrink: 0;
			}
			
			.wpdm-variation-table .wpdm-table-row-label .wpdm-color-name {
				font-size: 0.70em;
				font-weight: 500;
				color: var(--wpdm-color-secondary);
				text-align: left;
			}
			
			.wpdm-variation-table .wpdm-table-cell {
				min-width: 90px;
			}
			
			.wpdm-variation-table .wpdm-cell-content {
				display: flex;
				flex-direction: column;
				align-items: center;
				gap: 4px;
			}
			
			.wpdm-variation-table .wpdm-table-qty-input {
				width: 70px;
				padding: 8px;
				text-align: center;
				border: 1px solid rgba(0, 0, 0, 0.15);
				border-radius: 4px;
				font-size: 0.9em;
				transition: all 0.2s ease;
				background: var(--wpdm-color-white);
				color: var(--wpdm-color-secondary);
			}
			
			.wpdm-variation-table .wpdm-table-qty-input:focus {
				outline: none;
				border-color: var(--wpdm-color-primary);
				box-shadow: 0 0 0 3px rgba(110, 193, 228, 0.1);
			}
			
			.wpdm-variation-table .wpdm-table-qty-input:invalid {
				border-color: #dc3545;
			}
			
			.wpdm-variation-table .wpdm-stock-info {
				font-size: 0.65em;
				text-align: center;
				margin-top: 2px;
				line-height: 1.2;
				font-weight: 500;
			}
			
			.wpdm-variation-table .wpdm-stock-info.wpdm-stock-high {
				color: <?php echo esc_attr( get_option( self::OPTION_STOCK_HIGH_COLOR, '#28a745' ) ); ?>;
			}
			
			.wpdm-variation-table .wpdm-stock-info.wpdm-stock-low {
				color: <?php echo esc_attr( get_option( self::OPTION_STOCK_LOW_COLOR, '#ff8c00' ) ); ?>;
			}
			
			.wpdm-variation-table .wpdm-stock-info.wpdm-stock-none {
				color: <?php echo esc_attr( get_option( self::OPTION_STOCK_NONE_COLOR, '#dc3545' ) ); ?>;
				font-weight: 600;
			}
			
			.wpdm-variation-table .wpdm-table-unavailable {
				color: var(--wpdm-color-text);
				font-size: 1.2em;
			}
			
			.wpdm-variation-table .wpdm-row-total,
			.wpdm-variation-table .wpdm-col-total {
				font-weight: 600;
				color: var(--wpdm-color-secondary);
				background: var(--wpdm-color-bg-light);
			}
			
			.wpdm-variation-table .wpdm-table-totals-row {
				background: linear-gradient(135deg, var(--wpdm-color-bg-light) 0%, rgba(241, 241, 241, 0.5) 100%);
				font-weight: 600;
			}
			
			.wpdm-variation-table .wpdm-table-totals-row td {
				border-top: 2px solid rgba(0, 0, 0, 0.1);
				border-bottom: none;
			}
			
			.wpdm-variation-table .wpdm-table-totals-label {
				text-align: left;
				color: var(--wpdm-color-secondary);
			}
			
			.wpdm-variation-table .wpdm-grand-total {
				font-size: 1.1em;
				color: var(--wpdm-color-blue-dark);
				font-weight: 700;
			}
			
			.wpdm-table-summary {
				margin: 1.5em 0;
				padding: 1.25em;
				background: linear-gradient(135deg, var(--wpdm-color-bg-light) 0%, var(--wpdm-color-white) 100%);
				border: 1px solid rgba(0, 0, 0, 0.08);
				border-radius: 8px;
				box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
			}
			
			.wpdm-table-summary > div {
				margin-bottom: 0.75em;
				display: flex;
				justify-content: space-between;
				align-items: center;
				font-size: 0.95em;
			}
			
			.wpdm-table-summary > div:last-child {
				margin-bottom: 0;
			}
			
			.wpdm-table-summary strong {
				color: var(--wpdm-color-secondary);
				font-weight: 500;
			}
			
			.wpdm-table-summary .wpdm-total-quantity,
			.wpdm-table-summary .wpdm-unit-price {
				color: var(--wpdm-color-primary);
				font-weight: 600;
			}
			
			.wpdm-table-summary .wpdm-table-total-price {
				margin-top: 0.75em;
				padding-top: 0.75em;
				border-top: 2px solid rgba(0, 0, 0, 0.1);
				font-size: 1.15em;
			}
			
			.wpdm-table-summary .wpdm-table-total-price strong {
				font-weight: 600;
				color: var(--wpdm-color-secondary);
			}
			
			.wpdm-table-summary .wpdm-total-price {
				color: var(--wpdm-color-blue-dark);
				font-weight: 700;
				font-size: 1.1em;
			}
			
			.wpdm-add-table-to-cart {
				padding: 14px 32px;
				font-size: 1em;
				margin-top: 1.5em;
				border-radius: 6px;
				transition: all 0.3s ease;
				font-weight: 500;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				background-color: var(--wpdm-color-accent);
				color: var(--wpdm-color-white);
				border: none;
			}
			
			.wpdm-add-table-to-cart:disabled {
				opacity: 0.5;
				cursor: not-allowed;
			}
			
			.wpdm-add-table-to-cart:not(:disabled):hover {
				transform: translateY(-2px);
				box-shadow: 0 4px 12px rgba(97, 206, 112, 0.3);
				background-color: var(--wpdm-color-accent);
				opacity: 0.9;
			}
			
			@media (max-width: 768px) {
				.wpdm-variation-table-title {
					font-size: 1.1em;
				}
				
				.wpdm-variation-table th,
				.wpdm-variation-table td {
					padding: 10px 8px;
					font-size: 0.85em;
				}
				
				.wpdm-variation-table .wpdm-table-qty-input {
					width: 60px;
					padding: 6px;
				}
				
				.wpdm-table-summary {
					padding: 1em;
				}
				
				.wpdm-table-summary > div {
					flex-direction: column;
					align-items: flex-start;
					gap: 0.25em;
				}
			}
			
			/* Estilos para notificación de éxito */
			.wpdm-cart-notification {
				position: fixed;
				top: 20px;
				right: 20px;
				background: #46b450;
				color: #fff;
				padding: 0;
				border-radius: 4px;
				box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
				z-index: 999999;
				min-width: 300px;
				max-width: 500px;
				opacity: 0;
				transform: translateX(400px);
				transition: all 0.3s ease;
			}
			
			.wpdm-cart-notification.wpdm-notification-show {
				opacity: 1;
				transform: translateX(0);
			}
			
			.wpdm-notification-content {
				display: flex;
				align-items: center;
				padding: 15px 20px;
				gap: 12px;
			}
			
			.wpdm-notification-icon {
				background: rgba(255, 255, 255, 0.2);
				border-radius: 50%;
				width: 24px;
				height: 24px;
				display: flex;
				align-items: center;
				justify-content: center;
				font-weight: bold;
				flex-shrink: 0;
				font-size: 16px;
			}
			
			.wpdm-notification-message {
				flex: 1;
				font-size: 14px;
				line-height: 1.4;
			}
			
			.wpdm-notification-link {
				color: #fff;
				text-decoration: underline;
				font-size: 13px;
				font-weight: 600;
				white-space: nowrap;
				margin-left: 10px;
			}
			
			.wpdm-notification-link:hover {
				text-decoration: none;
			}
			
			.wpdm-notification-close {
				background: transparent;
				border: none;
				color: #fff;
				font-size: 24px;
				line-height: 1;
				cursor: pointer;
				padding: 0;
				width: 24px;
				height: 24px;
				display: flex;
				align-items: center;
				justify-content: center;
				opacity: 0.8;
				transition: opacity 0.2s;
				flex-shrink: 0;
			}
			
			.wpdm-notification-close:hover {
				opacity: 1;
			}
			
			@media (max-width: 768px) {
				.wpdm-cart-notification {
					right: 10px;
					left: 10px;
					max-width: none;
					min-width: auto;
				}
				
				.wpdm-notification-content {
					padding: 12px 15px;
					flex-wrap: wrap;
				}
				
				.wpdm-notification-link {
					margin-left: 0;
					margin-top: 8px;
					width: 100%;
				}
			}
			
			/* Ocultar formulario estándar de WooCommerce cuando la tabla personalizada está activa */
			.wpdm-variation-table-wrapper ~ .single_variation_wrap,
			.wpdm-variation-table-wrapper ~ .variations_button,
			.wpdm-variation-table-wrapper ~ form .single_variation_wrap,
			.wpdm-variation-table-wrapper ~ form .variations_button,
			form.variations_form .single_variation_wrap,
			form.variations_form .variations_button {
				display: none !important;
			}
		</style>
		<?php
		
		return ob_get_clean();
	}

	/**
	 * Obtener datos de imagen o color para un término de atributo.
	 * 
	 * @param string $taxonomy Taxonomy del atributo (ej: pa_color)
	 * @param string $term_slug Slug del término (ej: ro-rojo, bla-blanco)
	 * @param string $term_name Nombre del término (ej: Rojo, Blanco) - opcional
	 * @param array $variation_map Mapa de variaciones [row_value][col_value] => variation_id - opcional
	 * @param string $col_attribute Nombre del atributo (puede ser de fila o columna según el contexto) - opcional
	 * @return array Array con 'image' (URL) y/o 'color' (hex)
	 */
	private static function get_color_swatch_data( $taxonomy, $term_slug, $term_name = '', $variation_map = array(), $col_attribute = '' ) {
		$result = array(
			'image' => '',
			'color' => '',
		);
		
		if ( empty( $taxonomy ) || empty( $term_slug ) ) {
			return $result;
		}
		
		// PRIMERO: Intentar obtener imagen directamente de una variación con este color
		// Buscar en cualquier variación que tenga este color (sin importar la talla)
		// Solo buscar si el atributo o taxonomy es pa_color o contiene "color"
		$is_color_attribute = false;
		if ( ! empty( $col_attribute ) && ( $col_attribute === 'pa_color' || strpos( $col_attribute, 'color' ) !== false ) ) {
			$is_color_attribute = true;
		}
		if ( ! $is_color_attribute && ( $taxonomy === 'pa_color' || strpos( $taxonomy, 'color' ) !== false ) ) {
			$is_color_attribute = true;
		}
		
		if ( ! empty( $variation_map ) && $is_color_attribute ) {
			foreach ( $variation_map as $row_value => $col_map ) {
				if ( isset( $col_map[ $term_slug ] ) ) {
					$variation_id = $col_map[ $term_slug ];
					$variation = wc_get_product( $variation_id );
					
					if ( $variation ) {
						// Obtener imagen de la variación (prioridad 1)
						$variation_image_id = $variation->get_image_id();
						
						if ( ! empty( $variation_image_id ) ) {
							$image_url = wp_get_attachment_image_url( $variation_image_id, 'thumbnail' );
							if ( $image_url ) {
								$result['image'] = $image_url;
								return $result;
							}
						}
						
						// También intentar obtener la galería de imágenes de la variación (prioridad 2)
						$gallery_ids = $variation->get_gallery_image_ids();
						if ( ! empty( $gallery_ids ) && isset( $gallery_ids[0] ) ) {
							$image_url = wp_get_attachment_image_url( $gallery_ids[0], 'thumbnail' );
							if ( $image_url ) {
								$result['image'] = $image_url;
								return $result;
							}
						}
						
						// Si la variación no tiene imagen, intentar obtener la del producto padre (prioridad 3)
						$parent_id = $variation->get_parent_id();
						if ( $parent_id ) {
							$parent_product = wc_get_product( $parent_id );
							if ( $parent_product ) {
								$parent_image_id = $parent_product->get_image_id();
								if ( ! empty( $parent_image_id ) ) {
									$image_url = wp_get_attachment_image_url( $parent_image_id, 'thumbnail' );
									if ( $image_url ) {
										$result['image'] = $image_url;
										return $result;
									}
								}
							}
						}
					}
					break; // Solo necesitamos una variación con este color
				}
			}
		}
		
		// SEGUNDO: Intentar obtener imagen del término (plugins como Variation Swatches)
		$term = get_term_by( 'slug', $term_slug, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			// Si no se encuentra, extraer el nombre del color del slug y buscar en el mapeo
			$color_name = self::extract_color_name_from_slug( $term_slug );
			$result['color'] = self::get_color_from_name( $color_name );
			return $result;
		}
		
		// Intentar obtener imagen del término
		$image_id = get_term_meta( $term->term_id, 'product_attribute_image', true );
		if ( empty( $image_id ) ) {
			$image_id = get_term_meta( $term->term_id, 'image', true );
		}
		if ( empty( $image_id ) ) {
			$image_id = get_term_meta( $term->term_id, 'swatches_image', true );
		}
		if ( empty( $image_id ) ) {
			// Intentar con diferentes nombres de meta fields comunes
			$image_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
		}
		
		if ( ! empty( $image_id ) ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
			if ( $image_url ) {
				$result['image'] = $image_url;
				return $result;
			}
		}
		
		// Intentar obtener color hexadecimal del término
		$color = get_term_meta( $term->term_id, 'product_attribute_color', true );
		if ( empty( $color ) ) {
			$color = get_term_meta( $term->term_id, 'color', true );
		}
		if ( empty( $color ) ) {
			$color = get_term_meta( $term->term_id, 'swatches_color', true );
		}
		if ( empty( $color ) ) {
			// Intentar con diferentes nombres de meta fields comunes
			$color = get_term_meta( $term->term_id, 'pa_color', true );
		}
		
		if ( ! empty( $color ) ) {
			// Asegurar que el color tenga el formato correcto (#RRGGBB)
			$color = trim( $color );
			if ( strpos( $color, '#' ) !== 0 ) {
				$color = '#' . $color;
			}
			// Validar que sea un color hexadecimal válido
			if ( preg_match( '/^#[0-9A-Fa-f]{6}$/', $color ) ) {
				$result['color'] = $color;
				return $result;
			}
		}
		
		// TERCERO: Si no hay imagen ni color, intentar mapeo básico por nombre
		// Usar el nombre del término si está disponible (más limpio), sino extraer del slug
		$color_name = '';
		if ( ! empty( $term_name ) ) {
			$color_name = strtolower( trim( $term_name ) );
		} else {
			$color_name = self::extract_color_name_from_slug( $term_slug );
		}
		
		$result['color'] = self::get_color_from_name( $color_name );
		
		return $result;
	}
	
	/**
	 * Extraer el nombre del color del slug, buscando dentro del slug completo.
	 * 
	 * @param string $slug Slug del término (ej: ro-rojo, bla-blanco, bl-blanco-br)
	 * @return string Nombre del color detectado o slug limpio
	 */
	private static function extract_color_name_from_slug( $slug ) {
		$slug = strtolower( trim( $slug ) );
		
		// Lista de nombres de colores comunes para buscar dentro del slug
		// Ordenar por longitud descendente para priorizar colores compuestos
		$color_names = array(
			// Colores compuestos primero (más específicos)
			'azul claro', 'azul oscuro', 'azul marino', 'azul cielo', 'azul real', 'azul turquesa',
			'gris claro', 'gris oscuro', 'gris perla', 'gris plata', 'gris carbón',
			'verde claro', 'verde oscuro', 'verde botella', 'verde esmeralda', 'verde menta', 'verde oliva', 'verde musgo', 'verde bosque', 'verde manzana', 'verde lima',
			'rojo claro', 'rojo oscuro', 'rojo carmesí', 'rojo cereza', 'rojo sangre', 'rojo tomate',
			'amarillo claro', 'amarillo oscuro', 'amarillo mostaza',
			'naranja claro', 'naranja oscuro', 'naranja azul',
			'marino oscuro',
			'rosa claro', 'rosa oscuro', 'rosa palo',
			'púrpura claro', 'púrpura oscuro',
			'beige claro', 'beige oscuro',
			'coral claro', 'coral oscuro',
			// Colores simples
			'rojo', 'roja', 'azul', 'verde', 'amarillo', 'naranja', 'rosa',
			'negro', 'blanco', 'gris', 'marrón', 'marron', 'morado', 'violeta',
			'beige', 'coral', 'turquesa', 'ocre', 'granate', 'burdeos',
			'marino', 'dorado', 'oro', 'plata', 'cobre',
			'red', 'blue', 'green', 'yellow', 'orange', 'pink', 'black',
			'white', 'gray', 'grey', 'brown', 'purple', 'violet'
		);
		
		// Buscar cada nombre de color dentro del slug
		foreach ( $color_names as $color_name ) {
			if ( strpos( $slug, $color_name ) !== false ) {
				return $color_name;
			}
		}
		
		// Si no se encuentra, intentar eliminar prefijos comunes (ro-, bla-, etc.)
		// Formato común: prefijo-color (ej: ro-rojo, bla-blanco, az-azul)
		if ( preg_match( '/^[a-z]{2,3}-(.+)$/i', $slug, $matches ) ) {
			$cleaned = strtolower( trim( $matches[1] ) );
			// Eliminar también sufijos comunes (-br, -es, etc.)
			$cleaned = preg_replace( '/-[a-z]{1,3}$/i', '', $cleaned );
			return $cleaned;
		}
		
		// Si no tiene prefijo, devolver el slug tal cual
		return $slug;
	}
	
	/**
	 * Limpiar el nombre del color eliminando prefijos y duplicados.
	 * 
	 * Ejemplos:
	 * - "azul-azul" -> "azul"
	 * - "bla-blanco" -> "blanco"
	 * - "neg-negro" -> "negro"
	 * - "ro-rojo" -> "rojo"
	 * - "AZC-AZUL CLARO" -> "Azul Claro"
	 * - "GROS-GRIS OSCURO" -> "Gris Oscuro"
	 * - "MROS-MARINO OSCURO" -> "Marino Oscuro"
	 * - "NARA-NARANJA/AZUL" -> "Naranja/Azul"
	 * - "VEB-VERDE BOTELLA" -> "Verde Botella"
	 * 
	 * @param string $name Nombre del color (puede venir del término o del slug)
	 * @return string Nombre del color limpio
	 */
	private static function clean_color_name( $name ) {
		$name = trim( $name );
		
		// Si está vacío, devolver tal cual
		if ( empty( $name ) ) {
			return $name;
		}
		
		// Convertir a minúsculas para comparar, pero mantener mayúsculas para palabras importantes
		$name_lower = strtolower( $name );
		
		// Patrón 1: prefijo-COLOR MODIFICADOR (ej: AZC-AZUL CLARO, GROS-GRIS OSCURO, MROS-MARINO OSCURO, VEB-VERDE BOTELLA)
		if ( preg_match( '/^[a-z]{2,4}-([a-z]+(?:\s+[a-z]+)*)(?:\s*\/\s*[a-z]+)?$/i', $name, $matches ) ) {
			$color_part = trim( $matches[1] );
			// Si hay una barra, mantenerla (ej: NARA-NARANJA/AZUL)
			if ( preg_match( '/\//', $name ) ) {
				$parts = explode( '/', $name );
				$cleaned_parts = array();
				foreach ( $parts as $part ) {
					$part = trim( $part );
					// Eliminar prefijo si existe
					if ( preg_match( '/^[a-z]{2,4}-(.+)$/i', $part, $part_matches ) ) {
						$part = trim( $part_matches[1] );
					}
					$cleaned_parts[] = self::capitalize_color_name( $part );
				}
				return implode( '/', $cleaned_parts );
			}
			return self::capitalize_color_name( $color_part );
		}
		
		// Patrón 2: prefijo-color-color (ej: azul-azul, rojo-rojo)
		if ( preg_match( '/^([a-z]{2,4})-([a-z]+)-([a-z]+)$/i', $name_lower, $matches ) ) {
			$color1 = $matches[2];
			$color2 = $matches[3];
			
			// Si color1 y color2 son iguales, devolver solo uno
			if ( $color1 === $color2 ) {
				return self::capitalize_color_name( $color1 );
			}
			
			// Si color2 contiene color1 o viceversa, devolver el más largo
			if ( strpos( $color2, $color1 ) !== false ) {
				return self::capitalize_color_name( $color2 );
			}
			if ( strpos( $color1, $color2 ) !== false ) {
				return self::capitalize_color_name( $color1 );
			}
		}
		
		// Patrón 3: prefijo-color (ej: bla-blanco, neg-negro, ro-rojo, az-azul)
		if ( preg_match( '/^[a-z]{2,4}-(.+)$/i', $name, $matches ) ) {
			$color = trim( $matches[1] );
			// Verificar si el color contiene el prefijo
			$prefix = strtolower( substr( $name, 0, strpos( $name, '-' ) ) );
			$color_lower = strtolower( $color );
			if ( strpos( $color_lower, $prefix ) === false ) {
				return self::capitalize_color_name( $color );
			}
		}
		
		// Patrón 4: color-color (duplicado sin prefijo, ej: azul-azul)
		if ( preg_match( '/^([a-z]+)-([a-z]+)$/i', $name_lower, $matches ) ) {
			$color1 = $matches[1];
			$color2 = $matches[2];
			
			// Si son iguales, devolver solo uno
			if ( $color1 === $color2 ) {
				return self::capitalize_color_name( $color1 );
			}
			
			// Si uno contiene al otro, devolver el más largo
			if ( strpos( $color2, $color1 ) !== false && strlen( $color2 ) > strlen( $color1 ) ) {
				return self::capitalize_color_name( $color2 );
			}
			if ( strpos( $color1, $color2 ) !== false && strlen( $color1 ) > strlen( $color2 ) ) {
				return self::capitalize_color_name( $color1 );
			}
		}
		
		// Si no coincide con ningún patrón, devolver el nombre original capitalizado
		return self::capitalize_color_name( $name );
	}
	
	/**
	 * Capitalizar el nombre del color correctamente.
	 * 
	 * @param string $name Nombre del color
	 * @return string Nombre capitalizado
	 */
	private static function capitalize_color_name( $name ) {
		$name = trim( $name );
		if ( empty( $name ) ) {
			return $name;
		}
		
		// Si contiene una barra, capitalizar cada parte
		if ( strpos( $name, '/' ) !== false ) {
			$parts = explode( '/', $name );
			$capitalized = array();
			foreach ( $parts as $part ) {
				$capitalized[] = self::capitalize_color_name( trim( $part ) );
			}
			return implode( '/', $capitalized );
		}
		
		// Capitalizar cada palabra (primera letra mayúscula, resto minúscula)
		$words = explode( ' ', $name );
		$capitalized_words = array();
		foreach ( $words as $word ) {
			$word = strtolower( trim( $word ) );
			if ( ! empty( $word ) ) {
				$capitalized_words[] = ucfirst( $word );
			}
		}
		
		return implode( ' ', $capitalized_words );
	}
	
	/**
	 * Obtener color hexadecimal básico desde el nombre del color.
	 * Busca el color dentro del nombre completo (ej: "bl-blanco-br" detectará "blanco").
	 * 
	 * @param string $color_name Nombre del color (ej: rojo, azul, verde, ro-rojo, bla-blanco, bl-blanco-br)
	 * @return string Color hexadecimal o vacío
	 */
	private static function get_color_from_name( $color_name ) {
		$color_name = strtolower( trim( $color_name ) );
		
		// Mapeo básico de colores comunes en español e inglés
		$color_map = array(
			// Español
			'rojo' => '#FF0000',
			'roja' => '#FF0000',
			'azul' => '#0000FF',
			'azul marino' => '#000080',
			'verde' => '#008000',
			'amarillo' => '#FFFF00',
			'naranja' => '#FFA500',
			'rosa' => '#FFC0CB',
			'negro' => '#000000',
			'blanco' => '#FFFFFF',
			'gris' => '#808080',
			'marrón' => '#A52A2A',
			'marron' => '#A52A2A',
			'morado' => '#800080',
			'violeta' => '#8A2BE2',
			'beige' => '#F5F5DC',
			'coral' => '#FF7F50',
			'turquesa' => '#40E0D0',
			'ocre' => '#CC7722',
			'granate' => '#800020',
			'burdeos' => '#800020',
			'mostaza' => '#FFDB58',
			'menta' => '#98FB98',
			'lavanda' => '#E6E6FA',
			'salmon' => '#FA8072',
			'fucsia' => '#FF00FF',
			'cian' => '#00FFFF',
			'lima' => '#00FF00',
			'oliva' => '#808000',
			'coral' => '#FF7F50',
			'coral claro' => '#FF7F50',
			'coral oscuro' => '#FF6347',
			'oro' => '#FFD700',
			'plata' => '#C0C0C0',
			'cobre' => '#B87333',
			'champagne' => '#F7E7CE',
			'crema' => '#FFFDD0',
			'beige claro' => '#F5F5DC',
			'beige oscuro' => '#D2B48C',
			'arena' => '#EDC9AF',
			'caramelo' => '#AF6E4D',
			'chocolate' => '#7B3F00',
			'café' => '#6F4E37',
			'cafe' => '#6F4E37',
			'piel' => '#FFDBAC',
			'durazno' => '#FFE5B4',
			'melocotón' => '#FFE5B4',
			'coral' => '#FF7F50',
			'salmón' => '#FA8072',
			'salmon' => '#FA8072',
			'terracota' => '#E2725B',
			'ladrillo' => '#B22222',
			'bermejo' => '#DC143C',
			'carmesí' => '#DC143C',
			'carmesi' => '#DC143C',
			'escarlata' => '#FF2400',
			'cereza' => '#DE3163',
			'frambuesa' => '#E30B5C',
			'fresa' => '#FC5A8D',
			'rosa palo' => '#FFB6C1',
			'rosa claro' => '#FFB6C1',
			'rosa oscuro' => '#B76E79',
			'fucsia' => '#FF00FF',
			'magenta' => '#FF00FF',
			'púrpura' => '#800080',
			'purpura' => '#800080',
			'púrpura claro' => '#DA70D6',
			'púrpura oscuro' => '#4B0082',
			'lavanda' => '#E6E6FA',
			'lila' => '#C8A2C8',
			'violeta' => '#8A2BE2',
			'índigo' => '#4B0082',
			'indigo' => '#4B0082',
			'azul cielo' => '#87CEEB',
			'azul claro' => '#ADD8E6',
			'azul oscuro' => '#00008B',
			'azul marino' => '#000080',
			'azul real' => '#4169E1',
			'azul turquesa' => '#40E0D0',
			'turquesa' => '#40E0D0',
			'cian' => '#00FFFF',
			'aqua' => '#00FFFF',
			'verde lima' => '#00FF00',
			'verde claro' => '#90EE90',
			'verde oscuro' => '#006400',
			'verde esmeralda' => '#50C878',
			'verde menta' => '#98FB98',
			'verde oliva' => '#808000',
			'verde musgo' => '#8A9A5B',
			'verde bosque' => '#228B22',
			'verde manzana' => '#8DB600',
			'amarillo claro' => '#FFFFE0',
			'amarillo oscuro' => '#B8860B',
			'amarillo mostaza' => '#FFDB58',
			'oro' => '#FFD700',
			'ámbar' => '#FFBF00',
			'ambar' => '#FFBF00',
			'naranja claro' => '#FFA500',
			'naranja oscuro' => '#FF8C00',
			'calabaza' => '#FF7518',
			'rojo claro' => '#FF6B6B',
			'rojo oscuro' => '#8B0000',
			'rojo carmesí' => '#DC143C',
			'rojo cereza' => '#DE3163',
			'rojo sangre' => '#8B0000',
			'rojo tomate' => '#FF6347',
			'gris claro' => '#D3D3D3',
			'gris oscuro' => '#555555',
			'marino' => '#000080',
			'marino oscuro' => '#000050',
			'verde botella' => '#006A4E',
			'verde botella oscuro' => '#004D3A',
			'dorado' => '#FFD700',
			'naranja azul' => '#FFA500', // Color combinado, usar naranja como base
			'naranja/azul' => '#FFA500', // Color combinado, usar naranja como base
			'gris oscuro' => '#555555',
			'marino' => '#000080',
			'marino oscuro' => '#000050',
			'verde botella' => '#006A4E',
			'verde botella oscuro' => '#004D3A',
			'dorado' => '#FFD700',
			'naranja azul' => '#FFA500', // Color combinado, usar naranja como base
			'naranja/azul' => '#FFA500', // Color combinado, usar naranja como base
			'gris perla' => '#E8E8E8',
			'gris plata' => '#C0C0C0',
			'gris carbón' => '#36454F',
			'negro carbón' => '#1C1C1C',
			'blanco roto' => '#F5F5DC',
			'blanco nieve' => '#FFFAFA',
			'blanco hueso' => '#F5F5DC',
			'beige' => '#F5F5DC',
			'crudo' => '#F5F5DC',
			'natural' => '#F5F5DC',
			// Inglés
			'red' => '#FF0000',
			'blue' => '#0000FF',
			'green' => '#008000',
			'yellow' => '#FFFF00',
			'orange' => '#FFA500',
			'pink' => '#FFC0CB',
			'black' => '#000000',
			'white' => '#FFFFFF',
			'gray' => '#808080',
			'grey' => '#808080',
			'brown' => '#A52A2A',
			'purple' => '#800080',
			'violet' => '#8A2BE2',
			'beige' => '#F5F5DC',
			'coral' => '#FF7F50',
			'turquoise' => '#40E0D0',
			'burgundy' => '#800020',
			'mustard' => '#FFDB58',
			'mint' => '#98FB98',
			'lavender' => '#E6E6FA',
			'salmon' => '#FA8072',
			'fuchsia' => '#FF00FF',
			'cyan' => '#00FFFF',
			'lime' => '#00FF00',
			'olive' => '#808000',
		);
		
		// Buscar coincidencia exacta
		if ( isset( $color_map[ $color_name ] ) ) {
			return $color_map[ $color_name ];
		}
		
		// Buscar coincidencia parcial (para casos como "azul claro", "rojo oscuro", "bl-blanco-br")
		// Ordenar por longitud descendente para priorizar coincidencias más específicas
		$sorted_colors = array();
		foreach ( $color_map as $key => $hex ) {
			$sorted_colors[ strlen( $key ) ][] = array( 'key' => $key, 'hex' => $hex );
		}
		krsort( $sorted_colors ); // Ordenar por longitud descendente
		
		foreach ( $sorted_colors as $length => $colors ) {
			foreach ( $colors as $color_data ) {
				$key = $color_data['key'];
				// Buscar el color dentro del nombre completo
				if ( strpos( $color_name, $key ) !== false ) {
					return $color_data['hex'];
				}
			}
		}
		
		return '';
	}
	
	/**
	 * Obtener mapa de atributos de variaciones para JavaScript.
	 * 
	 * @param array  $variation_map Mapa de variaciones
	 * @param string $row_attribute Atributo de fila
	 * @param string $col_attribute Atributo de columna
	 * @return array Mapa de atributos por variación
	 */
	private static function get_variation_attributes_map( $variation_map, $row_attribute, $col_attribute ) {
		$attributes_map = array();

		foreach ( $variation_map as $row_value => $col_map ) {
			foreach ( $col_map as $col_value => $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation ) {
					continue;
				}

				// Obtener atributos de la variación
				// get_variation_attributes() devuelve los atributos SIN el prefijo 'attribute_'
				// get_attributes() devuelve los atributos CON el prefijo 'attribute_'
				$variation_attrs = $variation->get_variation_attributes();
				
				$formatted_attrs = array();

				// Formatear atributos como WooCommerce los espera en el AJAX
				// WooCommerce espera: attribute_pa_color, attribute_pa_talla, etc.
				foreach ( $variation_attrs as $attr_name => $attr_value ) {
					// Verificar si el nombre ya tiene el prefijo 'attribute_'
					// get_variation_attributes() devuelve nombres como 'pa_color', no 'attribute_pa_color'
					if ( strpos( $attr_name, 'attribute_' ) === 0 ) {
						// Ya tiene el prefijo, usarlo tal cual
						$formatted_key = $attr_name;
					} else {
						// No tiene el prefijo, añadirlo
						$formatted_key = 'attribute_' . $attr_name;
					}
					
					// El valor debe ser el slug del término
					// Asegurar que no esté vacío y sea válido
					if ( ! empty( $attr_value ) && $attr_value !== '' ) {
						$formatted_attrs[ $formatted_key ] = $attr_value;
					}
				}

				// Solo añadir si hay atributos válidos
				if ( ! empty( $formatted_attrs ) ) {
					$attributes_map[ $variation_id ] = $formatted_attrs;
				}
			}
		}

		return $attributes_map;
	}

	/**
	 * Output JavaScript para la tabla de variaciones.
	 * 
	 * Se carga automáticamente si hay una tabla en la página (automática o por shortcode).
	 */
	public static function output_variation_table_script() {
		// Evitar cargar el script múltiples veces
		if ( self::$script_loaded ) {
			return;
		}

		// Verificar si hay una tabla de variaciones en la página
		$should_load = false;

		// Cargar si está habilitado y estamos en una página de producto
		if ( is_product() && self::is_enabled() ) {
			global $product;
			if ( $product && $product->is_type( 'variable' ) ) {
				$should_load = true;
			}
		}

		// Cargar si hay un shortcode en la página
		if ( ! $should_load && self::has_variation_table_on_page() ) {
			$should_load = true;
		}

		// Si no hay razón para cargar, salir
		if ( ! $should_load ) {
			return;
		}

		// Marcar como cargado antes de renderizar para evitar duplicados
		self::$script_loaded = true;

		?>
		<script type="text/javascript">
		(function($) {
			'use strict';

			var WPDMTable = {
				init: function() {
					this.bindEvents();
					this.updateTotals();
				},

				bindEvents: function() {
					var self = this;
					
					// Actualizar cuando cambia cualquier cantidad
					$(document).on('input change', '.wpdm-table-qty-input', function() {
						var value = parseInt($(this).val(), 10) || 0;
						if (value < 0) {
							$(this).val(0);
							value = 0;
						}
						self.updateTotals();
					});

					// Botón añadir al carrito
					$(document).on('click', '.wpdm-add-table-to-cart', function(e) {
						e.preventDefault();
						self.addToCart($(this));
					});
				},

				updateTotals: function() {
					var totalQty = 0;
					var $table = $('.wpdm-variation-table');
					
					// Calcular totales por fila
					$table.find('tbody tr').not(':last').each(function() {
						var rowTotal = 0;
						$(this).find('.wpdm-table-qty-input').each(function() {
							var qty = parseInt($(this).val(), 10) || 0;
							rowTotal += qty;
							totalQty += qty;
						});
						$(this).find('.wpdm-row-total').text(rowTotal);
					});

					// Calcular totales por columna
					var colCount = $table.find('thead th').length - 2; // -2 por la primera columna y la última de total
					for (var i = 0; i < colCount; i++) {
						var colTotal = 0;
						$table.find('tbody tr').not(':last').each(function() {
							var $input = $(this).find('td').eq(i + 1).find('.wpdm-table-qty-input');
							if ($input.length) {
								colTotal += parseInt($input.val(), 10) || 0;
							}
						});
						$table.find('.wpdm-col-total').eq(i).text(colTotal);
					}

					// Actualizar gran total
					$('.wpdm-grand-total').text(totalQty);
					$('.wpdm-total-quantity').text(totalQty);

					// Calcular precio si hay cantidad
					if (totalQty > 0) {
						this.calculatePrice(totalQty);
					} else {
						$('.wpdm-unit-price').text('—');
						$('.wpdm-total-price').text('—');
						$('.wpdm-add-table-to-cart').prop('disabled', true);
						$('.wpdm-add-customized-to-cart').prop('disabled', true);
					}
				},

				calculatePrice: function(totalQty) {
					var self = this;
					var $tableData = $('.wpdm-table-data');
					
					if (!$tableData.length) {
						return;
					}

					var data = JSON.parse($tableData.val());
					var priceTiers = data.price_tiers || [];

					if (priceTiers.length === 0) {
						$('.wpdm-unit-price').text('—');
						$('.wpdm-total-price').text('—');
						return;
					}

					// Encontrar el tramo aplicable
					var selectedTier = null;
					var bestFrom = 0;

					for (var i = 0; i < priceTiers.length; i++) {
						var tier = priceTiers[i];
						var from = parseInt(tier.qty_from, 10) || 0;
						var to = parseInt(tier.qty_to, 10) || 0;

						if (totalQty >= from && (to === 0 || totalQty <= to)) {
							if (from >= bestFrom) {
								selectedTier = tier;
								bestFrom = from;
							}
						}
					}

					// Si no hay match, usar el último tramo (mayor cantidad)
					if (!selectedTier && priceTiers.length > 0) {
						selectedTier = priceTiers[priceTiers.length - 1];
					}

					if (selectedTier) {
						var unitPrice = parseFloat(selectedTier.unit_price) || 0;
						var totalPrice = unitPrice * totalQty;

						$('.wpdm-unit-price').text(self.formatPrice(unitPrice));
						$('.wpdm-total-price').text(self.formatPrice(totalPrice));
						$('.wpdm-add-table-to-cart').prop('disabled', false);
						$('.wpdm-add-customized-to-cart').prop('disabled', false);
					} else {
						$('.wpdm-unit-price').text('—');
						$('.wpdm-total-price').text('—');
						$('.wpdm-add-table-to-cart').prop('disabled', true);
						$('.wpdm-add-customized-to-cart').prop('disabled', true);
					}
				},

				formatPrice: function(price) {
					price = parseFloat(price) || 0;
					
					// Obtener configuración de moneda desde los datos de la tabla
					var $tableData = $('.wpdm-table-data');
					if (!$tableData.length) {
						// Fallback si no hay datos
						return price.toFixed(2) + ' €';
					}
					
					var data = JSON.parse($tableData.val());
					var currencyConfig = data.currency_config || {
						symbol: '€',
						position: 'right',
						decimals: 2,
						decimalSep: ',',
						thousandSep: ''
					};
					
					// Formatear número con decimales y separadores
					var formatted = price.toFixed(currencyConfig.decimals);
					
					// Aplicar separador de miles si es necesario
					if (currencyConfig.thousandSep) {
						var parts = formatted.split('.');
						parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, currencyConfig.thousandSep);
						formatted = parts.join(currencyConfig.decimalSep);
					} else {
						formatted = formatted.replace('.', currencyConfig.decimalSep);
					}
					
					// Aplicar posición del símbolo según configuración de WooCommerce
					switch(currencyConfig.position) {
						case 'left':
							return currencyConfig.symbol + formatted;
						case 'right':
							return formatted + ' ' + currencyConfig.symbol;
						case 'left_space':
							return currencyConfig.symbol + ' ' + formatted;
						case 'right_space':
							return formatted + ' ' + currencyConfig.symbol;
						default:
							return currencyConfig.symbol + formatted;
					}
				},

				addToCart: function($button) {
					var self = this;
					var $table = $('.wpdm-variation-table');
					var items = [];

					// Recopilar todas las variaciones con cantidad > 0
					$table.find('.wpdm-table-qty-input').each(function() {
						var qty = parseInt($(this).val(), 10) || 0;
						if (qty > 0) {
							var variationId = parseInt($(this).data('variation-id'), 10);
							if (variationId > 0) {
								items.push({
									variation_id: variationId,
									quantity: qty
								});
							}
						}
					});

					if (items.length === 0) {
						alert('<?php echo esc_js( __( 'Por favor, selecciona al menos una cantidad.', 'woo-prices-dynamics-makito' ) ); ?>');
						return;
					}

					$button.prop('disabled', true).text('<?php echo esc_js( __( 'Añadiendo...', 'woo-prices-dynamics-makito' ) ); ?>');

					// Obtener datos del producto
					var $tableData = $('.wpdm-table-data');
					var data = JSON.parse($tableData.val());
					var productId = data.product_id;

					// Calcular cantidad total para obtener el precio
					var totalQty = 0;
					items.forEach(function(item) {
						totalQty += item.quantity;
					});

					// Obtener precio unitario según tramo
					var unitPrice = self.getUnitPriceForQuantity(totalQty, data.price_tiers || []);

					if (unitPrice <= 0) {
						alert('<?php echo esc_js( __( 'Error: No se pudo calcular el precio.', 'woo-prices-dynamics-makito' ) ); ?>');
						$button.prop('disabled', false).text('<?php echo esc_js( __( 'Añadir al carrito', 'woo-prices-dynamics-makito' ) ); ?>');
						return;
					}

					// Obtener URL AJAX y nonce desde los datos de la tabla
					var ajaxUrl = data.ajax_url || '/wp-admin/admin-ajax.php';
					var nonce = data.nonce || '';
					
					if (!nonce) {
						console.error('WPDM: No se encontró el nonce');
						alert('<?php echo esc_js( __( 'Error: No se pudo validar la petición. Por favor, recarga la página.', 'woo-prices-dynamics-makito' ) ); ?>');
						$button.prop('disabled', false).text('<?php echo esc_js( __( 'Añadir al carrito', 'woo-prices-dynamics-makito' ) ); ?>');
						return;
					}

					// Enviar todos los items de una vez a nuestro endpoint personalizado
					$.ajax({
						url: ajaxUrl,
						type: 'POST',
						data: {
							action: 'wpdm_add_table_to_cart',
							nonce: nonce,
							product_id: productId,
							items: JSON.stringify(items)
						},
						dataType: 'json',
						timeout: 20000,
						success: function(response) {
							if (console && console.log) {
								console.log('WPDM: Respuesta del servidor', response);
							}

							if (response.success) {
								// Éxito: productos añadidos
								
								// Mostrar notificación de éxito
								var message = response.data && response.data.message 
									? response.data.message 
									: '<?php echo esc_js( __( 'Productos añadidos al carrito correctamente.', 'woo-prices-dynamics-makito' ) ); ?>';
								
								self.showSuccessMessage(message);
								
								// Recargar fragmentos del carrito (esto actualiza el carrito en la página)
								$('body').trigger('wc_fragment_refresh');
								
								// Disparar evento added_to_cart sin parámetros problemáticos
								$('body').trigger('added_to_cart', [{
									fragments: {},
									cart_hash: '',
									product_id: productId
								}]);
								
								$button.prop('disabled', false).text('<?php echo esc_js( __( 'Añadir al carrito', 'woo-prices-dynamics-makito' ) ); ?>');
								
								// Asegurar que el carrito se actualice
								setTimeout(function() {
									$('body').trigger('wc_fragment_refresh');
								}, 300);
							} else {
								// Error
								var errorMsg = response.data && response.data.message 
									? response.data.message 
									: '<?php echo esc_js( __( 'Error al añadir al carrito.', 'woo-prices-dynamics-makito' ) ); ?>';
								alert(errorMsg);
								$button.prop('disabled', false).text('<?php echo esc_js( __( 'Añadir al carrito', 'woo-prices-dynamics-makito' ) ); ?>');
							}
						},
						error: function(xhr, status, error) {
							console.error('WPDM: Error en la petición AJAX', {
								status: status,
								error: error,
								response: xhr.responseText
							});
							
							var errorMsg = '<?php echo esc_js( __( 'Error al añadir al carrito. Por favor, intenta de nuevo.', 'woo-prices-dynamics-makito' ) ); ?>';
							if (xhr.responseText) {
								try {
									var errorResponse = JSON.parse(xhr.responseText);
									if (errorResponse.data && errorResponse.data.message) {
										errorMsg = errorResponse.data.message;
									}
								} catch(e) {
									// Ignorar error de parsing
								}
							}
							
							alert(errorMsg);
							$button.prop('disabled', false).text('<?php echo esc_js( __( 'Añadir al carrito', 'woo-prices-dynamics-makito' ) ); ?>');
						}
					});
				},

				getUnitPriceForQuantity: function(quantity, tiers) {
					var selectedTier = null;
					var bestFrom = 0;

					for (var i = 0; i < tiers.length; i++) {
						var tier = tiers[i];
						var from = parseInt(tier.qty_from, 10) || 0;
						var to = parseInt(tier.qty_to, 10) || 0;

						if (quantity >= from && (to === 0 || quantity <= to)) {
							if (from >= bestFrom) {
								selectedTier = tier;
								bestFrom = from;
							}
						}
					}

					if (!selectedTier && tiers.length > 0) {
						selectedTier = tiers[tiers.length - 1];
					}

					return selectedTier ? parseFloat(selectedTier.unit_price) || 0 : 0;
				},

				showSuccessMessage: function(message) {
					var self = this;
					
					// Crear o obtener el contenedor de notificaciones
					var $notification = $('.wpdm-cart-notification');
					
					// Si ya existe, eliminarlo
					if ($notification.length) {
						$notification.remove();
					}
					
					// Crear la notificación
					var $notice = $('<div class="wpdm-cart-notification wpdm-cart-notification-success">' +
						'<div class="wpdm-notification-content">' +
						'<span class="wpdm-notification-icon">✓</span>' +
						'<span class="wpdm-notification-message">' + message + '</span>' +
						'<a href="' + (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.cart_url ? wc_add_to_cart_params.cart_url : '/carrito/') + '" class="wpdm-notification-link"><?php echo esc_js( __( 'Ver carrito', 'woo-prices-dynamics-makito' ) ); ?></a>' +
						'<button type="button" class="wpdm-notification-close" aria-label="<?php echo esc_js( __( 'Cerrar', 'woo-prices-dynamics-makito' ) ); ?>">×</button>' +
						'</div>' +
						'</div>');
					
					// Añadir al body
					$('body').append($notice);
					
					// Animar entrada
					setTimeout(function() {
						$notice.addClass('wpdm-notification-show');
					}, 10);
					
					// Auto-ocultar después de 5 segundos
					var hideTimeout = setTimeout(function() {
						self.hideNotification($notice);
					}, 5000);
					
					// Cerrar al hacer clic en el botón
					$notice.find('.wpdm-notification-close').on('click', function() {
						clearTimeout(hideTimeout);
						self.hideNotification($notice);
					});
					
					// Cerrar al hacer clic en el enlace (después de un pequeño delay para que se registre el clic)
					$notice.find('.wpdm-notification-link').on('click', function() {
						clearTimeout(hideTimeout);
						setTimeout(function() {
							self.hideNotification($notice);
						}, 100);
					});
				},

				hideNotification: function($notice) {
					$notice.removeClass('wpdm-notification-show');
					setTimeout(function() {
						$notice.remove();
					}, 300);
				}
			};

			$(document).ready(function() {
				if ($('.wpdm-variation-table').length) {
					WPDMTable.init();
				}
			});

		})(jQuery);
		
		// Ocultar formulario estándar de WooCommerce cuando existe la tabla personalizada
		jQuery(document).ready(function($) {
			if ($('.wpdm-variation-table-wrapper').length > 0) {
				// Ocultar el formulario estándar de variaciones
				$('.single_variation_wrap').hide();
				$('.variations_button').hide();
				$('.woocommerce-variation-add-to-cart').hide();
				
				// También ocultar si está dentro del formulario
				$('form.variations_form').find('.single_variation_wrap, .variations_button, .woocommerce-variation-add-to-cart').hide();
			}
		});
		</script>
		<?php
	}

	/**
	 * AJAX: Calcular precio según cantidad total.
	 */
	public static function ajax_calculate_table_price() {
		check_ajax_referer( 'wpdm_table_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$total_qty = isset( $_POST['total_qty'] ) ? absint( $_POST['total_qty'] ) : 0;

		if ( $product_id <= 0 || $total_qty <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Datos inválidos.', 'woo-prices-dynamics-makito' ) ) );
		}

		$unit_price = WPDM_Price_Tiers::get_price_from_tiers( $product_id, $total_qty );

		if ( null === $unit_price ) {
			wp_send_json_error( array( 'message' => __( 'No se pudo calcular el precio.', 'woo-prices-dynamics-makito' ) ) );
		}

		wp_send_json_success( array(
			'unit_price' => $unit_price,
			'total_price' => $unit_price * $total_qty,
		) );
	}

	/**
	 * Localizar script para pasar datos a JavaScript.
	 */
	public static function localize_script() {
		if ( ! is_product() ) {
			return;
		}

		wp_localize_script(
			'jquery',
			'wpdm_variation_table',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpdm_table_nonce' ),
			)
		);
	}

	/**
	 * AJAX: Añadir múltiples variaciones al carrito con precio calculado.
	 * 
	 * Este método añade directamente al carrito usando WC()->cart->add_to_cart()
	 * y aplica el precio calculado basado en la suma total.
	 */
	public static function ajax_add_table_to_cart() {
		// Verificar nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpdm_table_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Error de seguridad. Por favor, recarga la página.', 'woo-prices-dynamics-makito' ) ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$items = isset( $_POST['items'] ) ? json_decode( stripslashes( $_POST['items'] ), true ) : array();

		if ( $product_id <= 0 || empty( $items ) || ! is_array( $items ) ) {
			wp_send_json_error( array( 'message' => __( 'Datos inválidos.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Validar que WooCommerce esté disponible
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce no está disponible.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Calcular cantidad total
		$total_qty = 0;
		foreach ( $items as $item ) {
			$total_qty += isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;
		}

		if ( $total_qty <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'La cantidad total debe ser mayor a 0.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Obtener precio unitario según tramo (basado en la suma total)
		$unit_price = WPDM_Price_Tiers::get_price_from_tiers( $product_id, $total_qty );

		if ( null === $unit_price || $unit_price <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'No se pudo calcular el precio según los tramos.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Añadir cada variación al carrito
		$added = array();
		$errors = array();

		foreach ( $items as $item ) {
			$variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
			$quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;

			if ( $variation_id <= 0 || $quantity <= 0 ) {
				continue;
			}

			// Validar que la variación existe y pertenece al producto
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || $variation->get_parent_id() != $product_id ) {
				$errors[] = sprintf( __( 'Variación %d no válida.', 'woo-prices-dynamics-makito' ), $variation_id );
				continue;
			}

			// Añadir al carrito
			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );

			if ( $cart_item_key ) {
				// Aplicar el precio calculado directamente en el carrito
				if ( isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
					$cart_item = WC()->cart->cart_contents[ $cart_item_key ];
					
					// Guardar el precio del tramo en los datos del carrito
					WC()->cart->cart_contents[ $cart_item_key ]['wpdm_tier_price'] = $unit_price;
					WC()->cart->cart_contents[ $cart_item_key ]['wpdm_tier_qty'] = $total_qty;
					
					// Aplicar el precio al producto
					if ( isset( $cart_item['data'] ) && is_a( $cart_item['data'], 'WC_Product' ) ) {
						$cart_item['data']->set_price( $unit_price );
						$cart_item['data']->set_regular_price( $unit_price );
						WC()->cart->cart_contents[ $cart_item_key ]['data'] = $cart_item['data'];
					}
				}

				$added[] = array(
					'cart_item_key' => $cart_item_key,
					'variation_id' => $variation_id,
					'quantity' => $quantity,
				);
			} else {
				$errors[] = sprintf( __( 'Error al añadir variación %d al carrito.', 'woo-prices-dynamics-makito' ), $variation_id );
			}
		}

		if ( ! empty( $errors ) && empty( $added ) ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
		}

		// Guardar el carrito en la sesión
		WC()->cart->set_session();

		wp_send_json_success( array(
			'message' => sprintf( __( '%d producto(s) añadido(s) al carrito correctamente.', 'woo-prices-dynamics-makito' ), count( $added ) ),
			'added' => $added,
			'unit_price' => $unit_price,
			'total_qty' => $total_qty,
		) );
	}
}



