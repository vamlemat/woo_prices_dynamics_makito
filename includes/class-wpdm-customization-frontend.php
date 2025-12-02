<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend para personalización de productos (modal y UI).
 */
class WPDM_Customization_Frontend {

	/**
	 * Inicialización.
	 */
	public static function init() {
		// Cambiar el texto del botón estándar de WooCommerce
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'change_add_to_cart_text' ), 10, 2 );
		
		// Añadir botón de personalización después del botón estándar (para productos simples o cuando no hay tabla)
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'add_customization_button' ), 31 );
		
		add_action( 'wp_footer', array( __CLASS__, 'output_customization_modal' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		
		// Añadir script de debug en consola
		add_action( 'wp_footer', array( __CLASS__, 'output_debug_script' ), 999 );
	}

	/**
	 * Inyectar botón después de la tabla usando JavaScript (para Elementor).
	 */
	public static function inject_button_after_table() {
		if ( ! is_product() ) {
			return;
		}

		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$product_id = $product->get_id();

		?>
		<script>
		(function($) {
			'use strict';

			function injectCustomizationButton() {
				// Buscar el wrapper de la tabla de variaciones
				var $tableWrapper = $('.wpdm-variation-table-wrapper');
				
				if ($tableWrapper.length === 0) {
					console.log('WPDM: No se encontró la tabla de variaciones');
					return;
				}

				// Verificar si el botón ya existe
				if ($tableWrapper.next('.wpdm-customization-button-wrapper').length > 0) {
					console.log('WPDM: El botón ya existe');
					return;
				}

				// Crear el botón
				var $buttonWrapper = $('<div class="wpdm-customization-button-wrapper" style="margin-top: 1.5em; text-align: center;"></div>');
				var $button = $('<button type="button" class="button wpdm-add-customized-to-cart" data-product-id="' + <?php echo esc_js( $product_id ); ?> + '" style="padding: 14px 32px; font-size: 1em; border-radius: 6px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; background-color: #0073aa; color: #fff; border: none; cursor: pointer;">Añadir con personalización</button>');
				
				$buttonWrapper.append($button);
				
				// Insertar después de la tabla
				$tableWrapper.after($buttonWrapper);
				
				console.log('WPDM: Botón de personalización inyectado correctamente');
			}

			// Intentar inyectar cuando el DOM esté listo
			$(document).ready(function() {
				console.log('WPDM: DOM ready, intentando inyectar botón');
				injectCustomizationButton();
			});

			// También intentar después de un delay (por si Elementor carga después)
			setTimeout(function() {
				console.log('WPDM: Timeout, intentando inyectar botón de nuevo');
				injectCustomizationButton();
			}, 1000);

			// Observar cambios en el DOM (por si Elementor carga dinámicamente)
			if (typeof MutationObserver !== 'undefined') {
				var observer = new MutationObserver(function(mutations) {
					var $tableWrapper = $('.wpdm-variation-table-wrapper');
					if ($tableWrapper.length > 0 && $tableWrapper.next('.wpdm-customization-button-wrapper').length === 0) {
						console.log('WPDM: MutationObserver detectó cambios, inyectando botón');
						injectCustomizationButton();
					}
				});

				observer.observe(document.body, {
					childList: true,
					subtree: true
				});
			}

		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Output script de debug en consola.
	 */
	public static function output_debug_script() {
		if ( ! is_product() ) {
			return;
		}

		?>
		<script>
		console.log('%c[WPDM DEBUG] Script inline ejecutándose', 'background: #222; color: #bada55; font-weight: bold;');
		console.log('[WPDM DEBUG] jQuery disponible:', typeof jQuery !== 'undefined');
		console.log('[WPDM DEBUG] $ disponible:', typeof $ !== 'undefined');
		
		// Verificar si el script principal se cargó
		if (typeof jQuery !== 'undefined') {
			jQuery(document).ready(function($) {
				console.log('%c[WPDM DEBUG] jQuery ready ejecutado', 'background: #222; color: #00ff00; font-weight: bold;');
				console.log('[WPDM DEBUG] Modal en DOM:', $('#wpdm-customization-modal').length > 0);
				console.log('[WPDM DEBUG] Botones personalizados:', $('.wpdm-add-customized-to-cart').length);
				console.log('[WPDM DEBUG] wpdmCustomization objeto:', typeof wpdmCustomization !== 'undefined' ? 'DEFINIDO' : 'NO DEFINIDO');
				
				if (typeof wpdmCustomization !== 'undefined') {
					console.log('[WPDM DEBUG] wpdmCustomization.ajax_url:', wpdmCustomization.ajax_url);
				}
			});
		} else {
			console.error('[WPDM DEBUG] jQuery NO está disponible');
		}
		</script>
		<?php

		// Mostrar logs si es admin
		if ( current_user_can( 'manage_options' ) ) {
			$logs = get_option( 'wpdm_debug_logs', array() );
			if ( ! empty( $logs ) ) {
				?>
				<script>
				console.group('📋 WPDM Debug Logs (últimos 10)');
				var logs = <?php echo wp_json_encode( array_slice( $logs, -10 ) ); ?>;
				logs.forEach(function(log, index) {
					console.log('Log #' + (index + 1) + ':', log);
				});
				console.groupEnd();
				</script>
				<?php
			}
		}
	}

	/**
	 * Cambiar el texto del botón "Añadir al carrito" estándar.
	 */
	public static function change_add_to_cart_text( $text, $product ) {
		return __( 'Añadir sin personalizar', 'woo-prices-dynamics-makito' );
	}

	/**
	 * Añadir botón "Añadir con personalización".
	 * PASO 1: Mostrar el botón siempre que sea un producto (luego añadiremos la validación de áreas)
	 */
	public static function add_customization_button() {
		// Log para debug
		$debug_info = array(
			'action' => 'add_customization_button',
			'is_product' => is_product(),
			'timestamp' => current_time( 'mysql' ),
		);

		if ( ! is_product() ) {
			$debug_info['error'] = 'No es página de producto';
			self::log_debug( $debug_info );
			return;
		}

		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			$debug_info['error'] = 'No hay producto válido';
			self::log_debug( $debug_info );
			return;
		}

		$product_id = $product->get_id();
		$debug_info['product_id'] = $product_id;
		$debug_info['product_type'] = $product->get_type();

		// PASO 1: Mostrar el botón SIEMPRE, sin condiciones
		// Debug: verificar si tiene áreas
		$has_areas = false;
		$marking_areas = array();
		if ( class_exists( 'WPDM_Customization' ) ) {
			$marking_areas = WPDM_Customization::get_marking_areas( $product_id );
			$has_areas = ! empty( $marking_areas );
			$debug_info['has_areas'] = $has_areas;
			$debug_info['areas_count'] = count( $marking_areas );
		}

		// Log completo
		self::log_debug( $debug_info );

		// Panel de debug para administradores
		if ( current_user_can( 'manage_options' ) ) {
			$raw_meta = get_post_meta( $product_id, 'marking_areas', true );
			?>
			<div id="wpdm-debug-panel" style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin: 15px 0; border-radius: 4px; font-family: monospace; font-size: 11px; max-height: 200px; overflow-y: auto;">
				<strong style="color: #495057;">🔍 WPDM Debug Panel</strong>
				<div style="margin-top: 10px; line-height: 1.6;">
					<div><strong>Product ID:</strong> <?php echo esc_html( $product_id ); ?></div>
					<div><strong>Product Type:</strong> <?php echo esc_html( $product->get_type() ); ?></div>
					<div><strong>Hook ejecutado:</strong> ✅ Sí</div>
					<div><strong>¿Tiene 'marking_areas' meta?:</strong> <?php echo ! empty( $raw_meta ) ? '✅ SÍ (tipo: ' . esc_html( gettype( $raw_meta ) ) . ')' : '❌ NO'; ?></div>
					<div><strong>Áreas encontradas:</strong> <?php echo $has_areas ? '✅ ' . count( $marking_areas ) : '❌ 0'; ?></div>
					<div><strong>Ver consola del navegador (F12)</strong> para más detalles</div>
				</div>
			</div>
			<script>
			console.group('🔍 WPDM Customization Debug');
			console.log('Hook ejecutado:', 'add_customization_button');
			console.log('Product ID:', <?php echo esc_js( $product_id ); ?>);
			console.log('Product Type:', <?php echo esc_js( $product->get_type() ); ?>);
			console.log('Has marking areas:', <?php echo $has_areas ? 'true' : 'false'; ?>);
			console.log('Areas count:', <?php echo count( $marking_areas ); ?>);
			console.log('Raw meta exists:', <?php echo ! empty( $raw_meta ) ? 'true' : 'false'; ?>);
			console.groupEnd();
			</script>
			<?php
		}

		?>
		<div class="wpdm-customization-button-wrapper" style="margin-top: 1em;">
			<button 
				type="button" 
				class="button wpdm-add-customized-to-cart" 
				data-product-id="<?php echo esc_attr( $product_id ); ?>"
			>
				<?php esc_html_e( 'Añadir con personalización', 'woo-prices-dynamics-makito' ); ?>
			</button>
		</div>
		<script>
		console.log('WPDM: Botón de personalización renderizado. Product ID:', <?php echo esc_js( $product_id ); ?>);
		</script>
		<?php
	}

	/**
	 * Log de debug (guardar en opción temporal para revisar).
	 */
	private static function log_debug( $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$logs = get_option( 'wpdm_debug_logs', array() );
		$logs[] = $data;
		
		// Mantener solo los últimos 50 logs
		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, -50 );
		}
		
		update_option( 'wpdm_debug_logs', $logs );
		
		// También log en error_log si WP_DEBUG está activo
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WPDM Debug: ' . wp_json_encode( $data ) );
		}
	}

	/**
	 * Encolar scripts y estilos.
	 * PASO 1: Cargar siempre en productos (luego validaremos áreas)
	 */
	public static function enqueue_scripts() {
		if ( ! is_product() ) {
			return;
		}

		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$css_url = plugin_dir_url( WPDM_WOOPRICES_PLUGIN_FILE ) . 'assets/css/wpdm-customization.css';
		$js_url = plugin_dir_url( WPDM_WOOPRICES_PLUGIN_FILE ) . 'assets/js/wpdm-customization.js';

		// Encolar estilos
		wp_enqueue_style(
			'wpdm-customization',
			$css_url,
			array(),
			WPDM_WOOPRICES_VERSION
		);

		// El objeto wpdmCustomization se define ahora directamente en output_customization_modal()
		// Simplemente encolar CSS y JS
		wp_enqueue_script(
			'wpdm-customization',
			$js_url,
			array( 'jquery' ),
			WPDM_WOOPRICES_VERSION,
			true
		);
	}

	/**
	 * Output del modal de personalización.
	 * PASO 1: Mostrar siempre el modal básico (luego validaremos áreas)
	 */
	public static function output_customization_modal() {
		if ( ! is_product() ) {
			return;
		}

		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		// Preparar datos aquí mismo
		$localize_data = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wpdm_customization_nonce' ),
			'currency_symbol' => get_woocommerce_currency_symbol(),
			'currency_pos' => get_option( 'woocommerce_currency_pos', 'right' ),
			'price_decimals' => wc_get_price_decimals(),
			'price_decimal_sep' => wc_get_price_decimal_separator(),
			'price_thousand_sep' => wc_get_price_thousand_separator(),
			'i18n' => array(
				'loading' => __( 'Cargando...', 'woo-prices-dynamics-makito' ),
				'error' => __( 'Error al cargar datos.', 'woo-prices-dynamics-makito' ),
				'no_areas' => __( 'Este producto no tiene áreas de marcaje disponibles.', 'woo-prices-dynamics-makito' ),
				'select_technique' => __( 'Selecciona una técnica', 'woo-prices-dynamics-makito' ),
				'upload_image' => __( 'Subir imagen', 'woo-prices-dynamics-makito' ),
				'uploading' => __( 'Subiendo...', 'woo-prices-dynamics-makito' ),
				'upload_error' => __( 'Error al subir imagen.', 'woo-prices-dynamics-makito' ),
				'calculating' => __( 'Calculando...', 'woo-prices-dynamics-makito' ),
				'add_to_cart' => __( 'Añadir al carrito', 'woo-prices-dynamics-makito' ),
				'adding' => __( 'Añadiendo...', 'woo-prices-dynamics-makito' ),
				'success' => __( 'Producto añadido al carrito correctamente.', 'woo-prices-dynamics-makito' ),
				'error_add' => __( 'Error al añadir al carrito.', 'woo-prices-dynamics-makito' ),
				'close' => __( 'Cerrar', 'woo-prices-dynamics-makito' ),
				'cancel' => __( 'Cancelar', 'woo-prices-dynamics-makito' ),
				'position' => __( 'Posición:', 'woo-prices-dynamics-makito' ),
				'dimensions' => __( 'Dimensiones:', 'woo-prices-dynamics-makito' ),
				'max_colors' => __( 'Máximo de colores:', 'woo-prices-dynamics-makito' ),
				'technique' => __( 'Técnica:', 'woo-prices-dynamics-makito' ),
				'colors' => __( 'Colores:', 'woo-prices-dynamics-makito' ),
				'print_dimensions' => __( 'Medida de impresión:', 'woo-prices-dynamics-makito' ),
				'pantone' => __( 'O indique PANTONE', 'woo-prices-dynamics-makito' ),
				'upload_image_label' => __( 'Adjuntar imagen:', 'woo-prices-dynamics-makito' ),
				'select_file' => __( 'Seleccionar archivo...', 'woo-prices-dynamics-makito' ),
				'upload_another' => __( 'Cargar otro archivo', 'woo-prices-dynamics-makito' ),
				'observations' => __( 'Observaciones:', 'woo-prices-dynamics-makito' ),
				'cliche_repetition' => __( 'Repetición Cliché', 'woo-prices-dynamics-makito' ),
				'total_customization' => __( 'TOTAL PERSONALIZACIÓN', 'woo-prices-dynamics-makito' ),
				'base_product' => __( 'Producto base', 'woo-prices-dynamics-makito' ),
				'customization' => __( 'Personalización', 'woo-prices-dynamics-makito' ),
				'total' => __( 'Total', 'woo-prices-dynamics-makito' ),
			),
		);

		?>
		<!-- WPDM Customization: Definir objeto ANTES del modal -->
		<script type="text/javascript">
		console.log('%c=== WPDM: Definiendo wpdmCustomization ===', 'background: #0073aa; color: #fff; font-size: 14px; padding: 5px;');
		window.wpdmCustomization = <?php echo wp_json_encode( $localize_data ); ?>;
		console.log('wpdmCustomization definido:', window.wpdmCustomization);
		console.log('ajax_url:', window.wpdmCustomization.ajax_url);

		// Verificar si el archivo JS se cargó
		jQuery(document).ready(function($) {
			console.log('%c[WPDM] Verificando carga del script...', 'background: #ff9900; color: #fff; font-weight: bold;');
			
			// Si el archivo JS no se cargó, añadir el event listener aquí mismo
			if ($('.wpdm-add-customized-to-cart').length > 0) {
				console.log('[WPDM] Botón encontrado, añadiendo event listener inline');
				
				$(document).on('click', '.wpdm-add-customized-to-cart', function(e) {
					e.preventDefault();
					console.log('%c[WPDM] ¡BOTÓN CLICKEADO!', 'background: #00ff00; color: #000; font-size: 16px; padding: 5px;');
					
					var productId = $(this).data('product-id');
					console.log('[WPDM] Product ID:', productId);
					
					// Obtener las variaciones con cantidad > 0 de la tabla
					var selectedVariations = [];
					var variationsMap = {}; // Para agrupar por variación completa
					
					$('.wpdm-table-qty-input').each(function() {
						var qty = parseInt($(this).val(), 10) || 0;
						if (qty > 0) {
							var variationId = $(this).data('variation-id');
							var $input = $(this);
							var $cell = $input.closest('td');
							var $row = $cell.closest('tr');
							var $table = $row.closest('table');
							
							// Obtener el color de la fila (primera celda TD con clase wpdm-table-row-label)
							var $rowLabel = $row.find('td.wpdm-table-row-label').first();
							var colorName = '';
							if ($rowLabel.length > 0) {
								// El nombre del color está en un span con clase wpdm-color-name
								var $colorName = $rowLabel.find('.wpdm-color-name');
								if ($colorName.length > 0) {
									colorName = $colorName.text().trim();
								} else {
									// Fallback: obtener todo el texto de la celda
									colorName = $rowLabel.text().trim();
								}
							}
							
							// Obtener la talla de la columna
							// El index de la celda actual (considerando que la primera columna es el header)
							var cellIndex = $cell.index();
							var $colHeader = $table.find('thead tr th').eq(cellIndex);
							var sizeName = $colHeader.text().trim();
							
							console.log('[WPDM] Input encontrado - Variation ID:', variationId, 'Color:', colorName, 'Talla:', sizeName, 'Qty:', qty);
							
							// Crear nombre completo
							var fullName = colorName + ' - ' + sizeName;
							
							// Si ya existe esta variación, sumar la cantidad
							if (variationsMap[variationId]) {
								variationsMap[variationId].quantity += qty;
							} else {
								variationsMap[variationId] = {
									variation_id: variationId,
									color: colorName,
									size: sizeName,
									full_name: fullName,
									quantity: qty
								};
							}
						}
					});
					
					// Convertir el mapa a array
					for (var varId in variationsMap) {
						selectedVariations.push(variationsMap[varId]);
					}
					
					console.log('[WPDM] Variaciones seleccionadas:', selectedVariations);
					
					// Abrir modal
					var $modal = $('#wpdm-customization-modal');
					if ($modal.length > 0) {
						console.log('[WPDM] Abriendo modal...');
						
						// Forzar display con !important usando setProperty
						$modal[0].style.setProperty('display', 'block', 'important');
						$('body').addClass('wpdm-modal-open');
						
						console.log('[WPDM] Modal visible:', $modal.is(':visible'));
						
						// Guardar las variaciones seleccionadas en el modal para uso posterior
						$modal.data('selected-variations', selectedVariations);
						
						// Mostrar loading
						$modal.find('.wpdm-customization-loading').show();
						$modal.find('.wpdm-customization-content').hide();
						$modal.find('.wpdm-customization-modal-footer').hide();
						
						// Cargar datos de personalización via AJAX
						console.log('[WPDM] Cargando áreas de marcaje...');
						$.ajax({
							url: wpdmCustomization.ajax_url,
							type: 'POST',
							data: {
								action: 'wpdm_get_customization_data',
								nonce: wpdmCustomization.nonce,
								product_id: productId
							},
							success: function(response) {
								console.log('[WPDM] Respuesta AJAX:', response);
								
								if (response.success && response.data.areas) {
									console.log('[WPDM] Áreas encontradas:', response.data.areas.length);
									
									// Pregunta inicial: ¿Personalizar todo igual o por color?
									var html = '<div class="wpdm-customization-mode" style="margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px; border: 2px solid #0073aa;">';
									html += '<p style="margin: 0 0 15px 0; font-weight: 600; font-size: 1.1em;">¿Desea marcar todos los colores de este artículo de la misma forma?</p>';
									html += '<p style="margin: 0 0 15px 0; font-size: 0.9em; color: #666;">Elija Sí cuando quiera marcar todos los artículos por igual o No si quiere marcar cada color de forma diferente.</p>';
									html += '<div style="display: flex; gap: 20px;">';
									html += '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;"><input type="radio" name="wpdm-customization-mode" value="global" checked> <strong>Sí (Global)</strong></label>';
									html += '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;"><input type="radio" name="wpdm-customization-mode" value="per-color"> <strong>No (Por color)</strong></label>';
									html += '</div>';
									html += '</div>';
									
									// Renderizar áreas
									html += '<div class="wpdm-customization-areas">';
									response.data.areas.forEach(function(area, index) {
										html += '<div class="wpdm-area-item" data-area-index="' + index + '" style="border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 20px; overflow: hidden;">';
										html += '<div class="wpdm-area-header" style="padding: 15px 20px; background: #f5f5f5; display: flex; justify-content: space-between; align-items: center; cursor: pointer;">';
										html += '<label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0; flex: 1;">';
										html += '<input type="checkbox" class="wpdm-area-enabled" style="width: 20px; height: 20px;">';
										html += '<strong>Zona de impresión - ' + (area.position || 'Área ' + (index + 1)) + '</strong>';
										html += '</label>';
										if (area.area_img) {
											html += '<img src="' + area.area_img + '" alt="' + area.position + '" style="max-width: 80px; max-height: 80px; border-radius: 4px; border: 1px solid #ddd;">';
										}
										html += '</div>';
										html += '<div class="wpdm-area-content" style="display: none; padding: 20px; background: #fff;">';
										
										// Info del área
										html += '<div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">';
										if (area.position) {
											html += '<p style="margin: 5px 0;"><strong>Posición:</strong> ' + area.position + '</p>';
										}
										if (area.width && area.height) {
											html += '<p style="margin: 5px 0;"><strong>Dimensiones máximas:</strong> ' + area.width + ' x ' + area.height + ' mm</p>';
										}
										if (area.max_colors) {
											html += '<p style="margin: 5px 0;"><strong>Máximo de colores:</strong> ' + area.max_colors + '</p>';
										}
										html += '</div>';
										
										// Selector de técnica (ahora con todas las técnicas disponibles)
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: block; margin-bottom: 8px; font-weight: 500;">Técnica de marcación:</label>';
										html += '<select class="wpdm-area-technique" data-area-id="' + area.print_area_id + '" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
										html += '<option value="">Selecciona una técnica...</option>';
										if (area.techniques && area.techniques.length > 0) {
											area.techniques.forEach(function(technique) {
												html += '<option value="' + technique.technique_ref + '" data-technique-name="' + technique.name + '">' + technique.name + '</option>';
											});
										}
										html += '</select>';
										html += '</div>';
										
										// Selector de colores
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: block; margin-bottom: 8px; font-weight: 500;">Número de colores:</label>';
										html += '<select class="wpdm-area-colors" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
										for (var i = 1; i <= (area.max_colors || 4); i++) {
											html += '<option value="' + i + '">' + i + ' COLOR' + (i > 1 ? 'ES' : '') + '</option>';
										}
										html += '</select>';
										html += '</div>';
										
										// Dimensiones personalizadas
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: block; margin-bottom: 8px; font-weight: 500;">Medida de impresión:</label>';
										html += '<div style="display: flex; align-items: center; gap: 10px;">';
										html += '<input type="text" class="wpdm-area-width" placeholder="' + (area.width || 'Ancho') + '" value="" style="width: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
										html += '<span>x</span>';
										html += '<input type="text" class="wpdm-area-height" placeholder="' + (area.height || 'Alto') + '" value="" style="width: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
										html += '<span>mm</span>';
										html += '</div>';
										html += '</div>';
										
										// Repetición cliché
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">';
										html += '<input type="checkbox" class="wpdm-area-cliche-repetition">';
										html += '<span>Repetición Cliché</span>';
										html += '</label>';
										html += '</div>';
										
										// Observaciones
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: block; margin-bottom: 8px; font-weight: 500;">Observaciones:</label>';
										html += '<textarea class="wpdm-area-observations" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>';
										html += '</div>';
										
										html += '</div>';
										html += '</div>';
									});
									html += '</div>';
									
									$modal.find('.wpdm-customization-loading').hide();
									$modal.find('.wpdm-customization-content').html(html).show();
									$modal.find('.wpdm-customization-modal-footer').show();
									
									// Guardar las áreas originales para re-renderizar
									$modal.data('original-areas', response.data.areas);
									
									// Event listener para cambio de modo (global vs por color)
									$(document).on('change', 'input[name="wpdm-customization-mode"]', function() {
										var mode = $('input[name="wpdm-customization-mode"]:checked').val();
										var areas = $modal.data('original-areas');
										var variations = $modal.data('selected-variations');
										
										console.log('[WPDM] Cambiando modo a:', mode);
										console.log('[WPDM] Variaciones disponibles:', variations);
										
										if (mode === 'per-color' && variations && variations.length > 0) {
											// Renderizar por color
											renderByColor(areas, variations);
										} else {
											// Renderizar global
											renderGlobal(areas);
										}
									});
									
									// Función para renderizar modo global
									function renderGlobal(areas) {
										console.log('[WPDM] Renderizando modo GLOBAL');
										var html = '<div class="wpdm-customization-areas">';
										areas.forEach(function(area, index) {
											html += renderAreaItem(area, index, null);
										});
										html += '</div>';
										$('.wpdm-customization-areas').replaceWith(html);
									}
									
									// Función para renderizar por color
									function renderByColor(areas, variations) {
										console.log('[WPDM] Renderizando modo POR COLOR');
										var html = '<div class="wpdm-customization-areas">';
										
										variations.forEach(function(variation, varIndex) {
											// Acordeón por color
											html += '<div class="wpdm-color-accordion" style="border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; overflow: hidden;">';
											html += '<div class="wpdm-color-accordion-header" data-variation-index="' + varIndex + '" style="padding: 15px 20px; background: linear-gradient(135deg, #0464AC 0%, #061B46 100%); color: #fff; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">';
											html += '<strong style="font-size: 1.1em;">' + variation.full_name + ' (' + variation.quantity + ' uds)</strong>';
											html += '<span class="wpdm-accordion-toggle">▼</span>';
											html += '</div>';
											html += '<div class="wpdm-color-accordion-content" style="display: none; padding: 15px; background: #fff;">';
											
											// Renderizar todas las áreas para este color
											areas.forEach(function(area, index) {
												html += renderAreaItem(area, index, variation);
											});
											
											html += '</div>';
											html += '</div>';
										});
										
										html += '</div>';
										$('.wpdm-customization-areas').replaceWith(html);
										
										// Event listener para acordeones (solo en el header, no en elementos hijos)
										$(document).off('click', '.wpdm-color-accordion-header').on('click', '.wpdm-color-accordion-header', function(e) {
											// Evitar que se cierre si se hace clic en elementos internos como checkboxes o inputs
											if ($(e.target).is('input, label, select, textarea')) {
												return;
											}
											
											var $header = $(this);
											var $content = $header.next('.wpdm-color-accordion-content');
											var $toggle = $header.find('.wpdm-accordion-toggle');
											
											if ($content.is(':visible')) {
												$content.slideUp(300);
												$toggle.text('▼');
											} else {
												// Cerrar otros acordeones
												$('.wpdm-color-accordion-content').slideUp(300);
												$('.wpdm-accordion-toggle').text('▼');
												
												// Abrir este
												$content.slideDown(300);
												$toggle.text('▲');
											}
										});
									}
									
									// Función auxiliar para renderizar un item de área
									function renderAreaItem(area, index, variation) {
										var uniqueId = variation ? 'var-' + variation.variation_id + '-area-' + index : 'global-area-' + index;
										var html = '<div class="wpdm-area-item" data-area-index="' + index + '" data-unique-id="' + uniqueId + '" style="border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 20px; overflow: hidden;">';
										html += '<div class="wpdm-area-header" style="padding: 15px 20px; background: #f5f5f5; display: flex; justify-content: space-between; align-items: center; cursor: pointer;">';
										html += '<label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0; flex: 1;">';
										html += '<input type="checkbox" class="wpdm-area-enabled" style="width: 20px; height: 20px;">';
										html += '<strong>Zona de impresión - ' + (area.position || 'Área ' + (index + 1)) + '</strong>';
										html += '</label>';
										if (area.area_img) {
											html += '<img src="' + area.area_img + '" alt="' + area.position + '" style="max-width: 80px; max-height: 80px; border-radius: 4px; border: 1px solid #ddd;">';
										}
										html += '</div>';
										html += '<div class="wpdm-area-content" style="display: none; padding: 20px; background: #fff;">';
										
										// Info del área
										html += '<div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">';
										if (area.position) {
											html += '<p style="margin: 5px 0;"><strong>Posición:</strong> ' + area.position + '</p>';
										}
										if (area.width && area.height) {
											html += '<p style="margin: 5px 0;"><strong>Dimensiones máximas:</strong> ' + area.width + ' x ' + area.height + ' mm</p>';
										}
										if (area.max_colors) {
											html += '<p style="margin: 5px 0;"><strong>Máximo de colores:</strong> ' + area.max_colors + '</p>';
										}
										html += '</div>';
										
										// Selector de técnica
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: block; margin-bottom: 8px; font-weight: 500;">Técnica de marcación:</label>';
										html += '<select class="wpdm-area-technique" data-area-id="' + area.print_area_id + '" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
										html += '<option value="">Selecciona una técnica...</option>';
										if (area.techniques && area.techniques.length > 0) {
											area.techniques.forEach(function(technique) {
												html += '<option value="' + technique.technique_ref + '" data-technique-name="' + technique.name + '">' + technique.name + '</option>';
											});
										}
										html += '</select>';
										html += '</div>';
										
										// Selector de colores
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: block; margin-bottom: 8px; font-weight: 500;">Número de colores:</label>';
										html += '<select class="wpdm-area-colors" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
										for (var i = 1; i <= (area.max_colors || 4); i++) {
											html += '<option value="' + i + '">' + i + ' COLOR' + (i > 1 ? 'ES' : '') + '</option>';
										}
										html += '</select>';
										html += '</div>';
										
										// Dimensiones personalizadas
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: block; margin-bottom: 8px; font-weight: 500;">Medida de impresión:</label>';
										html += '<div style="display: flex; align-items: center; gap: 10px;">';
										html += '<input type="text" class="wpdm-area-width" placeholder="' + (area.width || 'Ancho') + '" value="" style="width: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
										html += '<span>x</span>';
										html += '<input type="text" class="wpdm-area-height" placeholder="' + (area.height || 'Alto') + '" value="" style="width: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
										html += '<span>mm</span>';
										html += '</div>';
										html += '</div>';
										
										// Repetición cliché
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">';
										html += '<input type="checkbox" class="wpdm-area-cliche-repetition">';
										html += '<span>Repetición Cliché</span>';
										html += '</label>';
										html += '</div>';
										
										// Observaciones
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: block; margin-bottom: 8px; font-weight: 500;">Observaciones:</label>';
										html += '<textarea class="wpdm-area-observations" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>';
										html += '</div>';
										
										html += '</div>';
										html += '</div>';
										return html;
									}
									
									// Event listener para checkboxes de áreas
									$(document).off('change', '.wpdm-area-enabled').on('change', '.wpdm-area-enabled', function(e) {
										e.stopPropagation(); // Evitar que el evento suba al acordeón
										var $areaItem = $(this).closest('.wpdm-area-item');
										var enabled = $(this).is(':checked');
										$areaItem.find('.wpdm-area-content').toggle(enabled);
									});
									
									// Evitar que los clics en el header del área cierren el acordeón
									$(document).off('click', '.wpdm-area-header').on('click', '.wpdm-area-header', function(e) {
										e.stopPropagation(); // Evitar que el evento suba al acordeón
									});
								} else {
									$modal.find('.wpdm-customization-loading').hide();
									$modal.find('.wpdm-customization-content')
										.html('<p style="padding: 20px; text-align: center; color: #d00;">⚠️ ' + (response.data.message || 'No se encontraron áreas de marcaje para este producto.') + '</p>')
										.show();
								}
							},
							error: function(xhr, status, error) {
								console.error('[WPDM] Error AJAX:', xhr, status, error);
								$modal.find('.wpdm-customization-loading').hide();
								$modal.find('.wpdm-customization-content')
									.html('<p style="padding: 20px; text-align: center; color: #d00;">⚠️ Error al cargar datos. Por favor, intenta de nuevo.</p>')
									.show();
							}
						});
					} else {
						console.error('[WPDM] Modal no encontrado en el DOM');
						alert('Error: Modal no encontrado');
					}
				});
				
				// Cerrar modal
				$(document).on('click', '.wpdm-customization-modal-close, .wpdm-customization-modal-overlay', function() {
					console.log('[WPDM] Cerrando modal');
					var $modal = $('#wpdm-customization-modal');
					$modal[0].style.display = 'none';
					$modal.hide();
					$('body').removeClass('wpdm-modal-open');
				});
			} else {
				console.error('[WPDM] Botón NO encontrado');
			}
		});
		</script>

		<style>
		/* Estilos críticos inline para asegurar visibilidad del modal */
		#wpdm-customization-modal {
			position: fixed !important;
			top: 0 !important;
			left: 0 !important;
			width: 100% !important;
			height: 100% !important;
			z-index: 999999 !important;
			display: none !important;
		}
		#wpdm-customization-modal .wpdm-customization-modal-overlay {
			position: absolute !important;
			top: 0 !important;
			left: 0 !important;
			width: 100% !important;
			height: 100% !important;
			background: rgba(0, 0, 0, 0.7) !important;
		}
		#wpdm-customization-modal .wpdm-customization-modal-content {
			position: relative !important;
			background: #fff !important;
			max-width: 900px !important;
			max-height: 90vh !important;
			margin: 5vh auto !important;
			border-radius: 8px !important;
			box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3) !important;
			z-index: 1000000 !important;
			display: flex !important;
			flex-direction: column !important;
		}
		#wpdm-customization-modal .wpdm-customization-modal-body {
			overflow-y: auto !important;
			max-height: calc(90vh - 200px) !important;
			padding: 20px !important;
		}
		body.wpdm-modal-open {
			overflow: hidden !important;
		}
		</style>

		<div id="wpdm-customization-modal" class="wpdm-customization-modal" style="display: none;">
			<div class="wpdm-customization-modal-overlay"></div>
			<div class="wpdm-customization-modal-content">
				<div class="wpdm-customization-modal-header">
					<h2><?php esc_html_e( 'Personalizar Producto', 'woo-prices-dynamics-makito' ); ?></h2>
					<button type="button" class="wpdm-customization-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'woo-prices-dynamics-makito' ); ?>">
						<span>&times;</span>
					</button>
				</div>
				<div class="wpdm-customization-modal-body">
					<div class="wpdm-customization-loading">
						<p><?php esc_html_e( 'Cargando opciones de personalización...', 'woo-prices-dynamics-makito' ); ?></p>
					</div>
					<div class="wpdm-customization-content" style="display: none;">
						<!-- Contenido se carga dinámicamente via JavaScript -->
					</div>
				</div>
				<div class="wpdm-customization-modal-footer" style="display: none;">
					<div class="wpdm-customization-summary">
						<div class="wpdm-customization-total">
							<strong><?php esc_html_e( 'TOTAL PERSONALIZACIÓN:', 'woo-prices-dynamics-makito' ); ?></strong>
							<span class="wpdm-customization-total-price">0,00 €</span>
						</div>
					</div>
					<div class="wpdm-customization-actions">
						<button type="button" class="button wpdm-customization-cancel">
							<?php esc_html_e( 'Cancelar', 'woo-prices-dynamics-makito' ); ?>
						</button>
						<button type="button" class="button button-primary wpdm-customization-add-to-cart" disabled>
							<?php esc_html_e( 'Añadir al carrito', 'woo-prices-dynamics-makito' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}

