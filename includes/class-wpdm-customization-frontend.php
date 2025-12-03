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
						
						// Guardar las variaciones seleccionadas Y el product ID en el modal para uso posterior
						$modal.data('selected-variations', selectedVariations);
						$modal.data('product-id', productId);
						console.log('[WPDM] 💾 Guardado en modal - Product ID:', productId, 'Variaciones:', selectedVariations.length);
						
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
									
									// ===== DEFINICIÓN DE FUNCIÓN renderAreaItem (debe estar ANTES de su uso) =====
									function renderAreaItem(area, index, variation) {
										console.log('[WPDM] renderAreaItem llamado para área:', area.position, 'variation:', variation);
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
										
										// Grid de dos columnas: imagen grande a la izquierda, contenido a la derecha
										html += '<div class="wpdm-area-content-grid">';
										
										// Columna izquierda: Imagen grande
										html += '<div class="wpdm-area-image-column">';
										if (area.area_img) {
											html += '<img src="' + area.area_img + '" alt="' + area.position + '" class="wpdm-area-image-large">';
										}
										// Info del área debajo de la imagen
										html += '<div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px; font-size: 0.9em;">';
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
										html += '</div>';
										
										// Columna derecha: Formulario
										html += '<div class="wpdm-area-form-column">';
										
										// Selector de técnica
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: block; margin-bottom: 8px; font-weight: 500;">Técnica de marcación:</label>';
										html += '<select class="wpdm-area-technique" data-area-id="' + area.print_area_id + '" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
										html += '<option value="">Selecciona una técnica...</option>';
										if (area.techniques && area.techniques.length > 0) {
											area.techniques.forEach(function(technique) {
												html += '<option value="' + technique.ref + '" data-technique-name="' + technique.name + '">' + technique.name + '</option>';
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
										html += '<input type="number" class="wpdm-area-width" placeholder="' + (area.width || 'Ancho') + '" value="' + (area.width || '') + '" step="0.1" style="width: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
										html += '<span>x</span>';
										html += '<input type="number" class="wpdm-area-height" placeholder="' + (area.height || 'Alto') + '" value="' + (area.height || '') + '" step="0.1" style="width: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
										html += '<span>mm</span>';
										html += '</div>';
										html += '</div>';
										
										// Repetición cliché
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">';
										html += '<input type="checkbox" class="wpdm-area-cliche-repetition">';
										html += '<span>Repetición Cliché</span>';
										html += '</label>';
										html += '<div class="wpdm-cliche-order-number-wrapper" style="display: none; margin-top: 8px; padding-left: 28px;">';
										html += '<label style="display: block; margin-bottom: 5px; font-size: 0.9em; color: #666;">Nº de pedido anterior:</label>';
										html += '<input type="text" class="wpdm-area-cliche-order-number" placeholder="Ej: 12345" style="width: 100%; max-width: 200px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9em;">';
										html += '</div>';
										html += '</div>';
										
										// Observaciones
										html += '<div style="margin-bottom: 15px;">';
										html += '<label style="display: block; margin-bottom: 8px; font-weight: 500;">Observaciones:</label>';
										html += '<textarea class="wpdm-area-observations" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>';
										html += '</div>';
										
										html += '</div>'; // Cierre wpdm-area-form-column
										html += '</div>'; // Cierre wpdm-area-content-grid
										html += '</div>'; // Cierre wpdm-area-content
										html += '</div>'; // Cierre wpdm-area-item
										return html;
									}
									// ===== FIN DEFINICIÓN renderAreaItem =====
									
									// Renderizar áreas usando la función
									html += '<div class="wpdm-customization-areas">';
									response.data.areas.forEach(function(area, index) {
										html += renderAreaItem(area, index, null);
									});
									html += '</div>';
									
									$modal.find('.wpdm-customization-loading').hide();
									$modal.find('.wpdm-customization-content').html(html).show();
									$modal.find('.wpdm-customization-modal-footer').show();
									
									// FORZAR visibilidad correcta de tabs
									console.log('[WPDM] Forzando visibilidad de tabs...');
									$('#wpdm-tab-areas').css('display', 'block').show();
									$('#wpdm-tab-desglose').css('display', 'none').hide();
									$('.wpdm-modal-tab[data-tab="areas"]').addClass('active');
									$('.wpdm-modal-tab[data-tab="desglose"]').removeClass('active');
									
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
									
									// Función auxiliar renderAreaItem ya definida arriba
									
									// Event listener para checkboxes de áreas
									$(document).off('change', '.wpdm-area-enabled').on('change', '.wpdm-area-enabled', function(e) {
										e.stopPropagation(); // Evitar que el evento suba al acordeón
										var $areaItem = $(this).closest('.wpdm-area-item');
										var enabled = $(this).is(':checked');
										$areaItem.find('.wpdm-area-content').toggle(enabled);
										// Recalcular precios
										calculatePrices();
									});
									
									// Event listeners para cambios que afectan el precio
									$(document).off('change', '.wpdm-area-technique, .wpdm-area-colors, .wpdm-area-cliche-repetition').on('change', '.wpdm-area-technique, .wpdm-area-colors, .wpdm-area-cliche-repetition', function() {
										// Si es el checkbox de repetición cliché, mostrar/ocultar campo de nº pedido
										if ($(this).hasClass('wpdm-area-cliche-repetition')) {
											var $areaItem = $(this).closest('.wpdm-area-item');
											var isChecked = $(this).is(':checked');
											$areaItem.find('.wpdm-cliche-order-number-wrapper').toggle(isChecked);
											console.log('[WPDM] Repetición cliché:', isChecked ? 'ACTIVADA' : 'DESACTIVADA');
										}
										calculatePrices();
									});
									
									// Evitar que los clics en el header del área cierren el acordeón
									$(document).off('click', '.wpdm-area-header').on('click', '.wpdm-area-header', function(e) {
										e.stopPropagation(); // Evitar que el evento suba al acordeón
									});
									
									// Función para calcular precios en tiempo real
									function calculatePrices() {
										console.group('💰 [WPDM] Calculando precios...');
										
										var productId = $modal.data('product-id');
										var variations = $modal.data('selected-variations') || [];
										var totalQuantity = 0;
										
										console.log('Product ID:', productId);
										console.log('Variations guardadas:', variations);
										
										// Calcular cantidad total
										variations.forEach(function(v) {
											totalQuantity += v.quantity;
										});
										
										console.log('Cantidad total:', totalQuantity);
										
										if (totalQuantity <= 0) {
											console.warn('[WPDM] ⚠️ Cantidad total es 0, no se calculan precios');
											console.groupEnd();
											return;
										}
										
										// Recopilar datos de personalización
										var customizationData = {
											mode: $('input[name="wpdm-customization-mode"]:checked').val() || 'global',
											areas: []
										};
										
										console.log('Modo de personalización:', customizationData.mode);
										console.log('Total áreas en DOM:', $('.wpdm-area-item').length);
										
										$('.wpdm-area-item').each(function(index) {
											var $area = $(this);
											var enabled = $area.find('.wpdm-area-enabled').is(':checked');
											
											console.log('Área ' + index + ' - Habilitada:', enabled);
											
											if (!enabled) return;
											
											var techniqueSelect = $area.find('.wpdm-area-technique');
											var techniqueRef = techniqueSelect.val();
											
											console.log('Área ' + index + ' - Técnica seleccionada:', techniqueRef);
											
											if (!techniqueRef) {
												console.warn('Área ' + index + ' - Sin técnica seleccionada, se omite');
												return;
											}
											
											// Determinar la cantidad para esta área específica
											var areaQuantity = totalQuantity; // Por defecto, cantidad global
											
											// Si estamos en modo "por color", buscar la cantidad específica de este color
											if (customizationData.mode === 'per-color') {
												var $accordion = $area.closest('.wpdm-color-accordion');
												if ($accordion.length > 0) {
													var variationIndex = $accordion.find('.wpdm-color-accordion-header').data('variation-index');
													console.log('Área ' + index + ' - Pertenece a variación index:', variationIndex);
													
													if (variations[variationIndex]) {
														areaQuantity = variations[variationIndex].quantity;
														console.log('Área ' + index + ' - Cantidad específica del color:', areaQuantity);
													}
												}
											}
											
											var areaData = {
												enabled: true,
												technique_ref: techniqueRef,
												colors: parseInt($area.find('.wpdm-area-colors').val()) || 1,
												width: parseFloat($area.find('.wpdm-area-width').val()) || 0,
												height: parseFloat($area.find('.wpdm-area-height').val()) || 0,
												cliche_repetition: $area.find('.wpdm-area-cliche-repetition').is(':checked'),
												cliche_order_number: $area.find('.wpdm-area-cliche-order-number').val() || '',
												quantity: areaQuantity // Cantidad específica para esta área
											};
											
											console.log('Área ' + index + ' - Datos:', areaData);
											customizationData.areas.push(areaData);
										});
										
										console.log('Total áreas habilitadas con técnica:', customizationData.areas.length);
										console.log('Datos de personalización completos:', customizationData);
										
										// Hacer petición AJAX para calcular precio
										var ajaxData = {
											action: 'wpdm_calculate_customization_price',
											nonce: wpdmCustomization.nonce,
											product_id: productId,
											total_quantity: totalQuantity,
											customization_data: JSON.stringify(customizationData)
										};
										
										console.log('📤 Enviando AJAX:', ajaxData);
										
										$.ajax({
											url: wpdmCustomization.ajax_url,
											type: 'POST',
											data: ajaxData,
											success: function(response) {
												console.log('📥 Respuesta AJAX recibida:', response);
												
												if (response.success && response.data) {
													var data = response.data;
													
													console.log('✅ Cálculo exitoso:');
													console.log('  - Precio base total:', data.base_total);
													console.log('  - Total personalización:', data.customization_total);
													console.log('  - Gran total:', data.grand_total);
													console.log('  - Desglose por áreas:', data.areas);
													
													// Formatear precios (símbolo € hardcoded para evitar problemas de encoding)
													var baseTotal = parseFloat(data.base_total).toFixed(2).replace('.', ',');
													var customizationTotal = parseFloat(data.customization_total).toFixed(2).replace('.', ',');
													var grandTotal = parseFloat(data.grand_total).toFixed(2).replace('.', ',');
													
													// Actualizar UI con símbolo € directo
													$('.wpdm-base-total-price').text(baseTotal + ' €');
													$('.wpdm-customization-total-price').text(customizationTotal + ' €');
													$('.wpdm-grand-total-price').text(grandTotal + ' €'); // Tab Áreas (simple)
													$('.wpdm-grand-total-price-detail').text(grandTotal + ' €'); // Tab Desglose
													
													// Generar desglose detallado por área
													var areasDetailHtml = '';
													if (data.areas && Object.keys(data.areas).length > 0) {
														$.each(data.areas, function(areaIndex, areaPrice) {
															var areaNum = parseInt(areaIndex) + 1;
															areasDetailHtml += '<div class="wpdm-price-area" style="margin: 8px 0; padding: 8px; background: #fafafa; border-left: 3px solid #0464AC; border-radius: 4px;">';
															areasDetailHtml += '<div style="font-weight: 600; margin-bottom: 5px;">» Área ' + areaNum + '</div>';
															
															// Técnica (nombre + precio unitario × cantidad)
															if (areaPrice.technique_total_price > 0) {
																var techName = areaPrice.technique_name || 'Técnica';
																var techQuantity = areaPrice.quantity || totalQuantity;
																var techUnitPrice = parseFloat(areaPrice.technique_unit_price).toFixed(3).replace('.', ',');
																var techTotal = parseFloat(areaPrice.technique_total_price).toFixed(2).replace('.', ',');
																areasDetailHtml += '<div style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 0.85em;">';
																areasDetailHtml += '<span>' + techName + ' (' + techQuantity + ' uds × ' + techUnitPrice + ' €)</span>';
																areasDetailHtml += '<span>' + techTotal + ' €</span>';
																areasDetailHtml += '</div>';
															}
															
															// Colores extra (con detalle de cantidad y precio unitario)
															if (areaPrice.color_extra_total > 0) {
																var colorExtraQty = areaPrice.color_extra_qty || 0;
																var colorExtraUnitPrice = parseFloat(areaPrice.color_extra_price).toFixed(3).replace('.', ',');
																var colorExtraTotal = parseFloat(areaPrice.color_extra_total).toFixed(2).replace('.', ',');
																var colorExtraTotalCalc = colorExtraQty * totalQuantity;
																areasDetailHtml += '<div style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 0.85em;">';
																areasDetailHtml += '<span>Colores adicionales (' + colorExtraTotalCalc + ' uds × ' + colorExtraUnitPrice + ' €)</span>';
																areasDetailHtml += '<span>' + colorExtraTotal + ' €</span>';
																areasDetailHtml += '</div>';
															}
															
															// Mostrar advertencia si se aplicó el importe mínimo (ANTES del cliché)
															if (areaPrice.minimum_applied && areaPrice.minimum_amount > 0) {
																var minAmount = parseFloat(areaPrice.minimum_amount).toFixed(2).replace('.', ',');
																areasDetailHtml += '<div style="padding: 8px; margin: 8px 0; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; font-size: 0.85em; color: #856404;">';
																areasDetailHtml += '⚠ <strong>Importe mínimo de técnica:</strong> ' + minAmount + ' €';
																areasDetailHtml += '</div>';
															}
															
															// Cliché o Repetición cliché (solo uno de los dos) - SE SUMA DESPUÉS DEL MÍNIMO
															if (areaPrice.cliche_repetition_price > 0) {
																// Si hay repetición de cliché, se muestra SOLO este
																var clicheRepQty = areaPrice.cliche_colors_qty || 1;
																var clicheRepUnitPrice = parseFloat(areaPrice.cliche_unit_price).toFixed(2).replace('.', ',');
																var clicheRepTotal = parseFloat(areaPrice.cliche_repetition_price).toFixed(2).replace('.', ',');
																areasDetailHtml += '<div style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 0.85em;">';
																areasDetailHtml += '<span>Repetición cliché (' + clicheRepQty + ' colores × ' + clicheRepUnitPrice + ' €)</span>';
																areasDetailHtml += '<span>' + clicheRepTotal + ' €</span>';
																areasDetailHtml += '</div>';
															} else if (areaPrice.cliche_price > 0) {
																// Si NO hay repetición, se muestra el cliché normal
																var clicheQty = areaPrice.cliche_colors_qty || 1;
																var clicheUnitPrice = parseFloat(areaPrice.cliche_unit_price).toFixed(2).replace('.', ',');
																var clicheTotal = parseFloat(areaPrice.cliche_price).toFixed(2).replace('.', ',');
																areasDetailHtml += '<div style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 0.85em;">';
																areasDetailHtml += '<span>Cliché fotolito (' + clicheQty + ' colores × ' + clicheUnitPrice + ' €)</span>';
																areasDetailHtml += '<span>' + clicheTotal + ' €</span>';
																areasDetailHtml += '</div>';
															}
															
															// Total área
															var areaTotal = parseFloat(areaPrice.area_total).toFixed(2).replace('.', ',');
															areasDetailHtml += '<div style="display: flex; justify-content: space-between; padding: 5px 0 0 0; margin-top: 5px; border-top: 1px solid #ddd; font-weight: 600;">';
															areasDetailHtml += '<span>Subtotal área:</span>';
															areasDetailHtml += '<span>' + areaTotal + ' €</span>';
															areasDetailHtml += '</div>';
															
															areasDetailHtml += '</div>';
														});
													}
													$('.wpdm-price-areas-detail').html(areasDetailHtml);
													
													console.log('🎨 UI actualizada con precios y desglose detallado');
													
													// Habilitar botón de añadir al carrito si hay áreas seleccionadas
													var hasEnabledAreas = customizationData.areas.length > 0;
													$('.wpdm-customization-add-to-cart').prop('disabled', !hasEnabledAreas);
													
													console.log('Botón añadir al carrito:', hasEnabledAreas ? 'HABILITADO' : 'DESHABILITADO');
												} else {
													console.error('❌ Error en respuesta:', response.data ? response.data.message : 'Sin mensaje');
												}
												console.groupEnd();
											},
											error: function(xhr, status, error) {
												console.error('❌ Error AJAX:', {
													status: status,
													error: error,
													response: xhr.responseText
												});
												console.groupEnd();
											}
										});
									}
									
									// Calcular precios inicialmente
									setTimeout(function() {
										calculatePrices();
									}, 500);
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

				// Sistema de Tabs para Footer
				$(document).on('click', '.wpdm-modal-tab', function() {
					var tabName = $(this).data('tab');
					console.log('[WPDM] Cambiando a tab:', tabName);
					
					// Cambiar estilos de los botones de tabs (INACTIVOS)
					$('.wpdm-modal-tab').each(function() {
						$(this).removeClass('active');
						$(this).css({
							'background': '#f8f9fa',
							'color': '#6c757d',
							'border': '1px solid transparent',
							'border-bottom': '2px solid transparent',
							'box-shadow': 'none',
							'transform': 'scale(1)'
						});
					});
					
					// Aplicar estilos al tab ACTIVO
					$(this).addClass('active').css({
						'background': '#fff',
						'color': '#0464AC',
						'border': '1px solid #dee2e6',
						'border-bottom': '2px solid #fff',
						'box-shadow': '0 -3px 8px rgba(0,0,0,0.08)',
						'transform': 'scale(1)'
					});
					
					// FORZAR ocultar TODOS los tabs primero
					$('.wpdm-modal-tab-content').each(function() {
						$(this).removeClass('active').css('display', 'none').hide();
					});
					
					// FORZAR mostrar SOLO el tab activo
					var $targetTab = $('#wpdm-tab-' + tabName);
					$targetTab.addClass('active').css('display', 'block').show();
					
					console.log('[WPDM] Tab cambiado. Mostrando:', tabName);
				});
				
				// Efecto hover para tabs
				$(document).on('mouseenter', '.wpdm-modal-tab:not(.active)', function() {
					$(this).css({
						'background': '#e9ecef',
						'color': '#495057',
						'transform': 'translateY(-2px)'
					});
				});
				
				$(document).on('mouseleave', '.wpdm-modal-tab:not(.active)', function() {
					$(this).css({
						'background': '#f8f9fa',
						'color': '#6c757d',
						'transform': 'translateY(0)'
					});
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
			max-width: 1100px !important;
			max-height: 90vh !important;
			margin: 5vh auto !important;
			border-radius: 8px !important;
			box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3) !important;
			z-index: 1000000 !important;
			display: flex !important;
			flex-direction: column !important;
		}
		#wpdm-customization-modal .wpdm-customization-modal-header {
			padding: 20px 30px !important;
			border-bottom: 1px solid #e0e0e0 !important;
			display: flex !important;
			justify-content: space-between !important;
			align-items: center !important;
			background: linear-gradient(135deg, #0464AC 0%, #061B46 100%) !important;
			color: #fff !important;
			position: relative !important;
		}
		#wpdm-customization-modal .wpdm-customization-modal-header h2 {
			margin: 0 !important;
			font-size: 1.5em !important;
			font-weight: 600 !important;
			color: #fff !important;
		}
		#wpdm-customization-modal .wpdm-customization-modal-close {
			background: transparent !important;
			border: none !important;
			color: #fff !important;
			font-size: 32px !important;
			line-height: 1 !important;
			cursor: pointer !important;
			padding: 0 !important;
			width: 40px !important;
			height: 40px !important;
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
			transition: opacity 0.2s !important;
			position: absolute !important;
			right: 20px !important;
			top: 50% !important;
			transform: translateY(-50%) !important;
		}
		#wpdm-customization-modal .wpdm-customization-modal-close:hover {
			opacity: 0.7 !important;
		}
		#wpdm-customization-modal .wpdm-customization-modal-body {
			overflow-y: auto !important;
			max-height: calc(90vh - 200px) !important;
			padding: 20px !important;
		}
		.wpdm-area-content-grid {
			display: grid !important;
			grid-template-columns: 250px 1fr !important;
			gap: 30px !important;
			align-items: start !important;
		}
		.wpdm-area-image-large {
			width: 100% !important;
			border-radius: 8px !important;
			border: 2px solid #e0e0e0 !important;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
		}
		@media (max-width: 768px) {
			.wpdm-area-content-grid {
				grid-template-columns: 1fr !important;
				gap: 20px !important;
			}
			.wpdm-area-image-large {
				max-width: 200px !important;
				margin: 0 auto !important;
				display: block !important;
			}
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
					<!-- Tabs para separar Áreas y Desglose -->
					<div class="wpdm-modal-tabs" style="display: flex; background: #e9ecef; margin: -20px 0 20px 0; padding: 8px 20px 0 20px; gap: 8px; border-bottom: 2px solid #dee2e6; border-radius: 0;">
						<button class="wpdm-modal-tab active" data-tab="areas" style="padding: 14px 30px; cursor: pointer; border: 1px solid #dee2e6; border-bottom: 2px solid #fff; background: #fff; font-size: 1em; font-weight: 700; color: #0464AC; border-radius: 8px 8px 0 0; margin-bottom: -2px; box-shadow: 0 -3px 8px rgba(0,0,0,0.08); transition: all 0.3s ease; letter-spacing: 0.3px;">
							<?php esc_html_e( 'ÁREAS', 'woo-prices-dynamics-makito' ); ?>
						</button>
						<button class="wpdm-modal-tab" data-tab="desglose" style="padding: 14px 30px; cursor: pointer; border: 1px solid transparent; border-bottom: 2px solid transparent; background: #f8f9fa; font-size: 1em; font-weight: 700; color: #6c757d; border-radius: 8px 8px 0 0; margin-bottom: -2px; transition: all 0.3s ease; letter-spacing: 0.3px;">
							<?php esc_html_e( 'DESGLOSE DE PRECIOS', 'woo-prices-dynamics-makito' ); ?>
						</button>
					</div>

					<!-- Tab Content: Áreas (Total Simple) -->
					<div class="wpdm-modal-tab-content active" id="wpdm-tab-areas" style="display: block; max-height: 40vh; overflow-y: auto; padding: 20px 0;">
						<div class="wpdm-price-simple-summary" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; border: 2px solid #0464AC; padding: 30px; margin: 10px 0 20px 0; text-align: center; box-shadow: 0 4px 12px rgba(4, 100, 172, 0.1);">
							<div class="wpdm-simple-label" style="font-size: 1.1em; color: #666; font-weight: 600; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Total Personalización:', 'woo-prices-dynamics-makito' ); ?></div>
							<div class="wpdm-simple-total wpdm-grand-total-price" style="font-size: 2.2em; font-weight: 700; color: #0464AC; margin-top: 15px; text-shadow: 0 2px 4px rgba(0,0,0,0.05);">0,00 €</div>
							<div style="font-size: 0.9em; color: #999; margin-top: 10px;">
								<?php esc_html_e( 'Ver pestaña "Desglose de Precios" para más detalles', 'woo-prices-dynamics-makito' ); ?>
							</div>
						</div>
					</div>

					<!-- Tab Content: Desglose -->
					<div class="wpdm-modal-tab-content" id="wpdm-tab-desglose" style="display: none; max-height: 40vh; overflow-y: auto; padding: 20px 0;">
						<div class="wpdm-customization-summary">
							<div class="wpdm-price-breakdown">
								<div class="wpdm-price-line">
									<span><?php esc_html_e( 'Precio base producto:', 'woo-prices-dynamics-makito' ); ?></span>
									<span class="wpdm-base-total-price">0,00 €</span>
								</div>
								<div class="wpdm-price-line wpdm-price-customization-header" style="background: #f0f0f0; font-weight: 600; margin-top: 10px;">
									<span><?php esc_html_e( 'PERSONALIZACIÓN:', 'woo-prices-dynamics-makito' ); ?></span>
									<span class="wpdm-customization-total-price">0,00 €</span>
								</div>
								<div class="wpdm-price-areas-detail" style="padding-left: 20px; font-size: 0.9em;">
									<!-- Aquí se inyectará el desglose por área -->
								</div>
								<div class="wpdm-price-line wpdm-price-total">
									<strong><?php esc_html_e( 'TOTAL:', 'woo-prices-dynamics-makito' ); ?></strong>
									<strong class="wpdm-grand-total-price-detail">0,00 €</strong>
								</div>
							</div>
						</div>
					</div>

					<!-- Botones de acción (siempre visibles) -->
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

