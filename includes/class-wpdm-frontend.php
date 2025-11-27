<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lógica de frontend: precio dinámico en ficha de producto.
 */
class WPDM_Frontend {

	/**
	 * Registrar hooks.
	 */
	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'output_dynamic_price_script' ), 30 );
		add_action( 'wp_footer', array( __CLASS__, 'output_cart_price_script' ), 30 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_price_tiers_table_auto' ), 25 );
		add_shortcode( 'wpdm_price_tiers_table', array( __CLASS__, 'shortcode_price_tiers_table' ) );
	}

	/**
	 * Imprimir el script de precio dinámico en la ficha de producto.
	 */
	public static function output_dynamic_price_script() {
		if ( ! is_product() ) {
			return;
		}

		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		if ( ! $product->is_type( 'variable' ) && ! $product->is_type( 'simple' ) ) {
			return;
		}

		$product_id = (int) $product->get_id();
		
		// Para productos variables, necesitamos obtener los tramos de todas las variaciones
		$variations_tiers = array();
		if ( $product->is_type( 'variable' ) ) {
			$variations = $product->get_children();
			foreach ( $variations as $variation_id ) {
				$variation_id = absint( $variation_id );
				if ( $variation_id <= 0 ) {
					continue;
				}

				// Validar que la variación pertenezca realmente al producto.
				$variation = wc_get_product( $variation_id );
				if ( ! $variation || $variation->get_parent_id() !== $product_id ) {
					continue;
				}

				$var_tiers = WPDM_Price_Tiers::get_price_tiers( $variation_id );
				if ( ! empty( $var_tiers ) ) {
					$variations_tiers[ $variation_id ] = $var_tiers;
				}
			}
		}
		
		// Tramos del producto padre (para productos simples o como fallback)
		$price_tiers = WPDM_Price_Tiers::get_price_tiers( $product_id );

		// Si no hay tramos ni en el producto ni en variaciones, no hacer nada
		if ( empty( $price_tiers ) && empty( $variations_tiers ) ) {
			return;
		}

		// Obtener configuración de formato de moneda de WooCommerce
		$currency_symbol = get_woocommerce_currency_symbol();
		$currency_pos = get_option( 'woocommerce_currency_pos', 'left' );
		$price_decimals = wc_get_price_decimals();
		$price_decimal_sep = wc_get_price_decimal_separator();
		$price_thousand_sep = wc_get_price_thousand_separator();

		?>
		<script type="text/javascript">
		(function($) {
			'use strict';

			// Sistema de logging para consola del navegador (deshabilitado en producción)
			var WPDMLogger = {
				enabled: false,
				log: function(level, context, message, data) {
					if (!this.enabled) return;
					
					var logMessage = '[WPDM ' + level.toUpperCase() + '] [' + context + '] ' + message;
					var logData = data || {};
					
					switch(level) {
						case 'error':
							console.error(logMessage, logData);
							break;
						case 'warning':
							console.warn(logMessage, logData);
							break;
						case 'info':
							console.info(logMessage, logData);
							break;
						case 'debug':
						default:
							console.log(logMessage, logData);
							break;
					}
				},
				debug: function(context, message, data) { this.log('debug', context, message, data); },
				info: function(context, message, data) { this.log('info', context, message, data); },
				warning: function(context, message, data) { this.log('warning', context, message, data); },
				error: function(context, message, data) { this.log('error', context, message, data); }
			};

			var priceTiers = <?php echo wp_json_encode( $price_tiers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
			var variationsTiers = <?php echo wp_json_encode( $variations_tiers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
			var isVariable = <?php echo $product->is_type( 'variable' ) ? 'true' : 'false'; ?>;
			
			// Configuración de formato de moneda desde WooCommerce
			var currencyConfig = {
				symbol: <?php echo wp_json_encode( $currency_symbol ); ?>,
				position: <?php echo wp_json_encode( $currency_pos ); ?>,
				decimals: <?php echo absint( $price_decimals ); ?>,
				decimalSep: <?php echo wp_json_encode( $price_decimal_sep ); ?>,
				thousandSep: <?php echo wp_json_encode( $price_thousand_sep ); ?>
			};

			WPDMLogger.info('Frontend', 'Price tiers script initialized', {
				productId: <?php echo esc_js( $product_id ); ?>,
				isVariable: isVariable,
				priceTiers: priceTiers,
				variationsTiers: variationsTiers
			});

			function findTier(quantity, tiers) {
				if (!tiers || tiers.length === 0) {
					WPDMLogger.warning('Frontend', 'No tiers available', { quantity: quantity });
					return null;
				}
				
				var selected = tiers[0];
				quantity = parseInt(quantity, 10) || 1;

				WPDMLogger.debug('Frontend', 'Finding tier for quantity', { quantity: quantity, tiers: tiers });

				for (var i = 0; i < tiers.length; i++) {
					var from = parseInt(tiers[i].qty_from, 10) || 0;
					var to   = parseInt(tiers[i].qty_to, 10) || 0;

					if (quantity >= from && (to === 0 || quantity <= to)) {
						selected = tiers[i];
						WPDMLogger.debug('Frontend', 'Tier matched', { quantity: quantity, tier: selected });
					}
				}

				return selected;
			}

			function formatPrice(price) {
				price = parseFloat(price) || 0;
				
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
			}

			function updatePriceDisplay() {
				WPDMLogger.debug('Frontend', 'updatePriceDisplay called');
				
				var $qtyInput = $('form.cart').find('input.qty');
				if (!$qtyInput.length) {
					WPDMLogger.warning('Frontend', 'Quantity input not found');
					return;
				}

				var quantity = parseInt($qtyInput.val(), 10) || 1;
				var tiers = priceTiers;
				var variationId = null;

				// Si es producto variable, obtener la variación seleccionada
				if (isVariable) {
					variationId = parseInt($('form.variations_form').find('input[name="variation_id"]').val(), 10);
					WPDMLogger.debug('Frontend', 'Variable product - checking variation', { variationId: variationId });
					
					// Validar que variationId sea un número válido y esté en las variaciones permitidas
					if (variationId && variationId > 0 && variationsTiers.hasOwnProperty(variationId)) {
						tiers = variationsTiers[variationId];
						WPDMLogger.info('Frontend', 'Using variation tiers', { variationId: variationId, tiers: tiers });
					} else if (variationId && variationId > 0 && priceTiers.length > 0) {
						// Si la variación no tiene tramos propios, usar los del padre
						tiers = priceTiers;
						WPDMLogger.info('Frontend', 'Using parent tiers for variation', { variationId: variationId });
					} else {
						// Si no hay variación seleccionada, no mostrar precio
						WPDMLogger.warning('Frontend', 'No variation selected or no tiers available');
						return;
					}
				}

				var tier = findTier(quantity, tiers);
				if (!tier) {
					WPDMLogger.warning('Frontend', 'No tier found for quantity', { quantity: quantity, tiers: tiers });
					return;
				}

				var unit  = parseFloat(tier.unit_price) || 0;
				var total = unit * quantity;

				WPDMLogger.info('Frontend', 'Price calculated', {
					quantity: quantity,
					unitPrice: unit,
					totalPrice: total,
					tier: tier
				});

				// Actualizar el precio que WooCommerce muestra (puede variar según el theme)
				// Intentar múltiples selectores comunes
				var priceSelectors = [
					'.precio-producto h2',           // Elementor custom widget
					'.precio-producto',              // Elementor custom widget (sin h2)
					'.summary .price .amount',
					'.summary .price .woocommerce-Price-amount',
					'.summary .price',
					'.product .price .amount',
					'.product .price .woocommerce-Price-amount',
					'.product .price',
					'p.price .amount',
					'p.price .woocommerce-Price-amount',
					'p.price',
					'.woocommerce-Price-amount',
					'.price .amount',
					'.price'
				];
				
				var $priceElement = null;
				var foundSelector = null;
				
				for (var i = 0; i < priceSelectors.length; i++) {
					$priceElement = $(priceSelectors[i]).first();
					if ($priceElement.length && $priceElement.text().trim() !== '') {
						foundSelector = priceSelectors[i];
						break;
					}
				}
				
				if ($priceElement && $priceElement.length && foundSelector) {
					var formattedPrice = formatPrice(unit);
					
					// Si el elemento contiene un span con la clase amount, actualizar ese
					var $amountSpan = $priceElement.find('.amount, .woocommerce-Price-amount').first();
					if ($amountSpan.length) {
						// Usar html() en lugar de text() para que el símbolo € se muestre correctamente
						$amountSpan.html(formattedPrice);
						WPDMLogger.debug('Frontend', 'Price amount span updated', { 
							selector: foundSelector,
							price: formattedPrice 
						});
					} else if ($priceElement.is('h1, h2, h3, h4, h5, h6')) {
						// Si es un heading (como h2 en .precio-producto), actualizar directamente
						// Usar html() para que el símbolo € se muestre correctamente
						$priceElement.html(formattedPrice);
						WPDMLogger.debug('Frontend', 'Price heading updated', { 
							selector: foundSelector,
							price: formattedPrice 
						});
					} else {
						// Si no tiene span, actualizar el texto del elemento principal
						var currentText = $priceElement.text();
						// Usar html() para mantener el formato y mostrar correctamente el símbolo
						$priceElement.html('<span class="amount">' + formattedPrice + '</span>');
						WPDMLogger.debug('Frontend', 'Price element updated', { 
							selector: foundSelector,
							price: formattedPrice,
							originalText: currentText
						});
					}
				} else {
					// Si no encontramos ningún elemento, intentar crear uno o loguear todos los selectores probados
					// Buscar todos los elementos de precio en la página para debugging
					var allPriceElements = [];
					priceSelectors.forEach(function(selector) {
						var $els = $(selector);
						if ($els.length > 0) {
							$els.each(function() {
								allPriceElements.push({
									selector: selector,
									text: $(this).text().trim(),
									html: $(this).html().substring(0, 100)
								});
							});
						}
					});
					
					WPDMLogger.warning('Frontend', 'Price element not found', { 
						selectors_tried: priceSelectors,
						found_elements: allPriceElements,
						suggestion: 'Check theme structure or add custom selector. Found elements logged above.'
					});
					
					// Intentar actualizar usando el hook de WooCommerce
					$(document.body).trigger('wpdm_price_updated', {
						unitPrice: unit,
						totalPrice: total,
						quantity: quantity,
						tier: tier
					});
				}

				// Si existe un elemento para mostrar el total personalizado
				var $totalElement = $('.makito-total-price .amount').first();
				if ($totalElement.length) {
					var formattedTotal = formatPrice(total);
					// Usar html() en lugar de text() para que el símbolo € se muestre correctamente
					$totalElement.html(formattedTotal);
					WPDMLogger.debug('Frontend', 'Total element updated', { total: formattedTotal });
				}
			}

			// Eventos para actualizar precio
			$(document).on('change input', 'form.cart input.qty', function() {
				WPDMLogger.debug('Frontend', 'Quantity input changed');
				updatePriceDisplay();
			});
			
			// Para productos variables, actualizar cuando cambia la variación
			if (isVariable) {
				$(document).on('found_variation', 'form.variations_form', function(event, variation) {
					WPDMLogger.info('Frontend', 'Variation found', { variation: variation });
					updatePriceDisplay();
				});
				$(document).on('reset_data', 'form.variations_form', function() {
					WPDMLogger.debug('Frontend', 'Variation reset');
					// Limpiar precio cuando se resetea la variación
					var $priceElement = $('.summary .price .amount').first();
					if ($priceElement.length) {
						$priceElement.text('');
					}
				});
			}

			$(document).ready(function() {
				WPDMLogger.info('Frontend', 'Document ready - initializing price display');
				// Esperar un poco para que WooCommerce cargue los datos de variaciones
				setTimeout(function() {
					WPDMLogger.debug('Frontend', 'Initial price display update');
					updatePriceDisplay();
				}, 100);
			});

		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Script para actualizar precios en el carrito cuando cambia la cantidad.
	 */
	public static function output_cart_price_script() {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		?>
		<script type="text/javascript">
		(function($) {
			'use strict';

			// Sistema de logging para consola del navegador (deshabilitado en producción)
			var WPDMCartLogger = {
				enabled: false,
				log: function(level, context, message, data) {
					if (!this.enabled) return;
					
					var logMessage = '[WPDM Cart ' + level.toUpperCase() + '] [' + context + '] ' + message;
					var logData = data || {};
					
					switch(level) {
						case 'error':
							console.error(logMessage, logData);
							break;
						case 'warning':
							console.warn(logMessage, logData);
							break;
						case 'info':
							console.info(logMessage, logData);
							break;
						case 'debug':
						default:
							console.log(logMessage, logData);
							break;
					}
				},
				debug: function(context, message, data) { this.log('debug', context, message, data); },
				info: function(context, message, data) { this.log('info', context, message, data); },
				warning: function(context, message, data) { this.log('warning', context, message, data); },
				error: function(context, message, data) { this.log('error', context, message, data); }
			};

			WPDMCartLogger.info('Cart', 'Cart price script initialized');

			// Detectar cambios en las cantidades del carrito (tanto tradicional como Blocks)
			// Selectores para carrito tradicional
			var traditionalSelectors = 'input.qty, input[name*="quantity"]';
			// Selectores para WooCommerce Blocks
			var blocksSelectors = '.wc-block-components-quantity-selector__input, input.wc-block-components-quantity-selector__input';
			
			// Combinar ambos selectores
			var quantitySelectors = traditionalSelectors + ', ' + blocksSelectors;

			$(document.body).on('change input blur', quantitySelectors, function() {
				var $input = $(this);
				var quantity = parseInt($input.val(), 10) || 1;
				
				WPDMCartLogger.info('Cart', 'Quantity changed in cart', {
					quantity: quantity,
					inputValue: $input.val(),
					inputClass: $input.attr('class'),
					isBlocks: $input.hasClass('wc-block-components-quantity-selector__input')
				});

				// Para WooCommerce Blocks, disparar el evento de actualización
				if ($input.hasClass('wc-block-components-quantity-selector__input')) {
					WPDMCartLogger.debug('Cart', 'WooCommerce Blocks cart detected');
					// Los bloques se actualizan automáticamente, pero podemos forzar recálculo
					$('body').trigger('wc_update_cart');
				} else {
					// Carrito tradicional
					$('body').trigger('update_cart');
					
					// Si hay botón de actualizar, hacer clic
					setTimeout(function() {
						var $updateBtn = $('button[name="update_cart"], input[name="update_cart"]');
						if ($updateBtn.length) {
							WPDMCartLogger.debug('Cart', 'Triggering traditional cart update');
							$updateBtn.trigger('click');
						}
					}, 500);
				}
			});

			// Detectar cuando WooCommerce actualiza el carrito
			$(document.body).on('updated_cart_totals', function() {
				WPDMCartLogger.info('Cart', 'Cart totals updated');
			});

			// Detectar cuando se actualiza el carrito vía AJAX
			$(document.body).on('wc_fragment_refresh wc_update_cart', function() {
				WPDMCartLogger.info('Cart', 'Cart updated via AJAX');
			});

			// Para WooCommerce Blocks, también escuchar eventos específicos
			$(document.body).on('wc-blocks-cart-set-item-quantity', function(event, data) {
				WPDMCartLogger.info('Cart', 'WooCommerce Blocks quantity set', data);
			});

			// Log inicial
			$(document).ready(function() {
				var isBlocks = $('.wc-block-cart-item').length > 0;
				var isTraditional = $('table.shop_table.cart').length > 0;
				
				WPDMCartLogger.info('Cart', 'Cart page ready', {
					isBlocks: isBlocks,
					isTraditional: isTraditional,
					cartItems: $('tr.cart_item, div.cart_item, .wc-block-cart-item').length,
					quantityInputs: $(quantitySelectors).length,
					blocksInputs: $(blocksSelectors).length,
					traditionalInputs: $(traditionalSelectors).length
				});
			});

		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Renderizar automáticamente la tabla de tramos debajo del precio
	 * si la opción está activada en ajustes.
	 */
	public static function render_price_tiers_table_auto() {
		$show = (bool) get_option( WPDM_Admin_Settings::OPTION_SHOW_TABLE, false );

		if ( ! $show ) {
			return;
		}

		echo self::get_price_tiers_table_html();
	}

	/**
	 * Shortcode: [wpdm_price_tiers_table]
	 *
	 * Permite mostrar la tabla de tramos en cualquier lugar.
	 *
	 * @param array $atts
	 *
	 * @return string
	 */
	public static function shortcode_price_tiers_table( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
			),
			$atts,
			'wpdm_price_tiers_table'
		);

		$product_id = absint( $atts['product_id'] );

		if ( $product_id <= 0 ) {
			global $product;
			if ( $product && is_a( $product, 'WC_Product' ) ) {
				$product_id = absint( $product->get_id() );
			}
		}

		if ( $product_id <= 0 ) {
			return '';
		}

		// Validar que el product_id sea realmente un producto válido.
		$product_obj = wc_get_product( $product_id );
		if ( ! $product_obj ) {
			return '';
		}

		return self::get_price_tiers_table_html( $product_id );
	}

	/**
	 * Construir el HTML de la tabla de tramos para un producto.
	 *
	 * @param int $product_id
	 *
	 * @return string
	 */
	protected static function get_price_tiers_table_html( $product_id = 0 ) {
		if ( $product_id <= 0 ) {
			global $product;
			if ( $product && is_a( $product, 'WC_Product' ) ) {
				$product_id = absint( $product->get_id() );
			}
		}

		if ( $product_id <= 0 ) {
			return '';
		}

		// Validar que el product_id sea realmente un producto válido.
		$product_obj = wc_get_product( $product_id );
		if ( ! $product_obj ) {
			return '';
		}

		$tiers = WPDM_Price_Tiers::get_price_tiers( $product_id );

		if ( empty( $tiers ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="wpdm-price-tiers">
			<h3 class="wpdm-price-tiers__title"><?php esc_html_e( 'Precios por cantidad', 'woo-prices-dynamics-makito' ); ?></h3>
			<table class="wpdm-price-tiers__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Cantidad', 'woo-prices-dynamics-makito' ); ?></th>
						<th><?php esc_html_e( 'Precio unidad', 'woo-prices-dynamics-makito' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $tiers as $tier ) : ?>
					<?php
					$from = isset( $tier['qty_from'] ) ? (int) $tier['qty_from'] : 0;
					$to   = isset( $tier['qty_to'] ) ? (int) $tier['qty_to'] : 0;

					if ( 0 === $to ) {
						$range_text = sprintf(
							/* translators: %d: minimum quantity */
							esc_html__( '%d+', 'woo-prices-dynamics-makito' ),
							$from
						);
					} elseif ( 0 === $from ) {
						$range_text = sprintf(
							/* translators: %d: maximum quantity */
							esc_html__( 'Hasta %d', 'woo-prices-dynamics-makito' ),
							$to
						);
					} else {
						$range_text = sprintf(
							/* translators: 1: minimum quantity, 2: maximum quantity */
							esc_html__( '%1$d – %2$d', 'woo-prices-dynamics-makito' ),
							$from,
							$to
						);
					}

					$price = isset( $tier['unit_price'] ) ? (float) $tier['unit_price'] : 0;
					?>
					<tr>
						<td><?php echo esc_html( $range_text ); ?></td>
						<td><?php echo wp_kses_post( wc_price( $price ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<style>
			.wpdm-price-tiers {
				margin-top: 1.5em;
			}
			.wpdm-price-tiers__title {
				margin-bottom: 0.5em;
				font-size: 1.1em;
				font-weight: 600;
			}
			.wpdm-price-tiers__table {
				width: 100%;
				border-collapse: collapse;
				font-size: 0.95em;
			}
			.wpdm-price-tiers__table th,
			.wpdm-price-tiers__table td {
				padding: 8px 10px;
				border: 1px solid #e0e0e0;
				text-align: left;
			}
			.wpdm-price-tiers__table th {
				background-color: #f5f5f5;
				font-weight: 600;
			}
		</style>
		<?php

		return (string) ob_get_clean();
	}
}


