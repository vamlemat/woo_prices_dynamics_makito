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
		// Por defecto: primer atributo = filas (tallas), segundo = columnas (colores)
		$attribute_keys = array_keys( $attributes );
		$row_attribute = isset( $attribute_keys[0] ) ? $attribute_keys[0] : '';
		$col_attribute = isset( $attribute_keys[1] ) ? $attribute_keys[1] : '';

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

		// Obtener tramos de precio del producto
		$price_tiers = WPDM_Price_Tiers::get_price_tiers( $product->get_id() );

		ob_start();
		?>
		<div class="wpdm-variation-table-wrapper" style="margin: 20px 0;">
			<h3 class="wpdm-variation-table-title"><?php echo esc_html( sprintf( __( 'Selecciona cantidades (%s x %s)', 'woo-prices-dynamics-makito' ), $row_label, $col_label ) ); ?></h3>
			
			<div class="wpdm-variation-table-container" style="overflow-x: auto;">
				<table class="wpdm-variation-table" style="width: 100%; border-collapse: collapse; margin: 15px 0;">
					<thead>
						<tr>
							<th style="padding: 10px; border: 1px solid #ddd; background: #f5f5f5; font-weight: 600;">
								<?php echo esc_html( $row_label ); ?> \ <?php echo esc_html( $col_label ); ?>
							</th>
							<?php foreach ( $col_values as $col_value ) : ?>
								<th style="padding: 10px; border: 1px solid #ddd; background: #f5f5f5; font-weight: 600; text-align: center;">
									<?php echo esc_html( $col_value ); ?>
								</th>
							<?php endforeach; ?>
							<th style="padding: 10px; border: 1px solid #ddd; background: #f5f5f5; font-weight: 600; text-align: center;">
								<?php esc_html_e( 'Total', 'woo-prices-dynamics-makito' ); ?>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $row_values as $row_value ) : ?>
							<tr>
								<td style="padding: 10px; border: 1px solid #ddd; background: #f9f9f9; font-weight: 600;">
									<?php echo esc_html( $row_value ); ?>
								</td>
								<?php 
								$row_total = 0;
								foreach ( $col_values as $col_value ) : 
									$variation_id = isset( $variation_map[ $row_value ][ $col_value ] ) ? absint( $variation_map[ $row_value ][ $col_value ] ) : 0;
									$is_available = $variation_id > 0;
								?>
									<td style="padding: 8px; border: 1px solid #ddd; text-align: center;">
										<?php if ( $is_available ) : ?>
											<input 
												type="number" 
												class="wpdm-table-qty-input" 
												data-row="<?php echo esc_attr( $row_value ); ?>"
												data-col="<?php echo esc_attr( $col_value ); ?>"
												data-variation-id="<?php echo esc_attr( $variation_id ); ?>"
												min="0" 
												step="1" 
												value="0" 
												style="width: 70px; padding: 5px; text-align: center; border: 1px solid #ccc;"
											/>
										<?php else : ?>
											<span style="color: #999;">—</span>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
								<td class="wpdm-row-total" style="padding: 10px; border: 1px solid #ddd; text-align: center; font-weight: 600;">
									0
								</td>
							</tr>
						<?php endforeach; ?>
						<tr style="background: #f0f0f0; font-weight: 600;">
							<td style="padding: 10px; border: 1px solid #ddd;">
								<?php esc_html_e( 'Total', 'woo-prices-dynamics-makito' ); ?>
							</td>
							<?php foreach ( $col_values as $col_value ) : ?>
								<td class="wpdm-col-total" style="padding: 10px; border: 1px solid #ddd; text-align: center;">
									0
								</td>
							<?php endforeach; ?>
							<td class="wpdm-grand-total" style="padding: 10px; border: 1px solid #ddd; text-align: center; font-size: 1.1em; color: #0073aa;">
								0
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="wpdm-table-summary" style="margin: 15px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
				<div class="wpdm-table-total-qty" style="margin-bottom: 10px;">
					<strong><?php esc_html_e( 'Cantidad total:', 'woo-prices-dynamics-makito' ); ?></strong> 
					<span class="wpdm-total-quantity">0</span>
				</div>
				<div class="wpdm-table-price-info" style="margin-bottom: 10px;">
					<strong><?php esc_html_e( 'Precio unitario (según tramo):', 'woo-prices-dynamics-makito' ); ?></strong> 
					<span class="wpdm-unit-price">—</span>
				</div>
				<div class="wpdm-table-total-price" style="font-size: 1.2em; font-weight: 600;">
					<strong><?php esc_html_e( 'Precio total:', 'woo-prices-dynamics-makito' ); ?></strong> 
					<span class="wpdm-total-price">—</span>
				</div>
			</div>

			<button 
				type="button" 
				class="wpdm-add-table-to-cart button alt" 
				style="padding: 12px 30px; font-size: 1.1em; margin-top: 15px;"
				data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
			>
				<?php esc_html_e( 'Añadir al carrito', 'woo-prices-dynamics-makito' ); ?>
			</button>

			<input type="hidden" class="wpdm-table-data" value="<?php echo esc_attr( wp_json_encode( array(
				'product_id' => $product->get_id(),
				'price_tiers' => $price_tiers,
				'row_attribute' => $row_attribute,
				'col_attribute' => $col_attribute,
				'variation_attributes' => self::get_variation_attributes_map( $variation_map, $row_attribute, $col_attribute ),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wpdm_table_nonce' ),
			) ) ); ?>" />
		</div>

		<style>
			.wpdm-variation-table-wrapper {
				clear: both;
			}
			.wpdm-variation-table input[type="number"]:focus {
				outline: 2px solid #0073aa;
				border-color: #0073aa;
			}
			.wpdm-variation-table .wpdm-table-qty-input:invalid {
				border-color: #dc3232;
			}
			.wpdm-add-table-to-cart:disabled {
				opacity: 0.5;
				cursor: not-allowed;
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
		</style>
		<?php
		
		return ob_get_clean();
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
					} else {
						$('.wpdm-unit-price').text('—');
						$('.wpdm-total-price').text('—');
						$('.wpdm-add-table-to-cart').prop('disabled', true);
					}
				},

				formatPrice: function(price) {
					return parseFloat(price).toFixed(2).replace('.', ',') + ' €';
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

