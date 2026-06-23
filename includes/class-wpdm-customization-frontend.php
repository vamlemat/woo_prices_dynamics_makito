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
		
		add_action( 'init', array( __CLASS__, 'register_customization_endpoint' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'add_customization_query_var' ) );
		add_filter( 'template_include', array( __CLASS__, 'maybe_load_customization_template' ) );
		add_filter( 'body_class', array( __CLASS__, 'add_customization_body_class' ) );
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

		// No ejecutar en la página de personalización autónoma
		if ( self::is_customization_page() ) {
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
	 * Registrar endpoint para personalización en la URL del producto.
	 */
	public static function register_customization_endpoint() {
		// Rewrite rule propia: /personalizar/{product-slug}/
		// No usa add_rewrite_endpoint porque eso ancla la URL al permalink del producto
		// y WooCommerce sigue interviniendo. Con add_rewrite_rule la URL es completamente
		// independiente y WordPress la enruta directamente a nuestro template.
		add_rewrite_rule(
			'^personalizar/([^/]+)/?$',
			'index.php?wpdm_personalizar_slug=$matches[1]',
			'top'
		);
	}

	/**
	 * Flush rewrite rules una sola vez si es necesario.
	 * Se usa la versión '2' de la opción para forzar un nuevo flush tras el cambio
	 * de sistema (de endpoint a rewrite rule propia).
	 */
	public static function maybe_flush_rewrite_rules() {
		if ( get_option( 'wpdm_customization_rewrite_flushed' ) !== '2' ) {
			flush_rewrite_rules();
			update_option( 'wpdm_customization_rewrite_flushed', '2' );
		}
	}

	/**
	 * Agregar query var para personalización.
	 * El query var 'wpdm_personalizar_slug' recibe el slug del producto
	 * cuando se accede a /personalizar/{slug}/.
	 */
	public static function add_customization_query_var( $vars ) {
		$vars[] = 'wpdm_personalizar_slug';
		return $vars;
	}

	/**
	 * Verificar si estamos en la página de personalización.
	 * Detecta la presencia del query var 'wpdm_personalizar_slug' que se establece
	 * cuando WordPress procesa la rewrite rule /personalizar/{slug}/.
	 */
	public static function is_customization_page() {
		$slug = get_query_var( 'wpdm_personalizar_slug', '' );
		return ! empty( $slug );
	}

	/**
	 * Cargar plantilla personalizada cuando estamos en la página /personalizar/{slug}/.
	 * No depende de is_singular('product') porque es una virtual page propia,
	 * no un post/page de WordPress.
	 */
	public static function maybe_load_customization_template( $template ) {
		$slug = get_query_var( 'wpdm_personalizar_slug', '' );
		if ( ! empty( $slug ) ) {
			$custom_template = plugin_dir_path( __FILE__ ) . 'templates/customization-page.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}

		return $template;
	}

	/**
	 * Agregar clase al body cuando se muestra la página de personalización.
	 */
	public static function add_customization_body_class( $classes ) {
		if ( self::is_customization_page() ) {
			$classes[] = 'wpdm-customization-page';
		}

		return $classes;
	}

	/**
	 * Generar URL de personalización para el producto.
	 * Genera la URL /personalizar/{product-slug}/ usando la rewrite rule propia.
	 */
	public static function get_customization_page_url( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 ) {
			return '#';
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '#';
		}

		// Obtener el slug del producto (post_name)
		$slug = $product->get_slug();

		return trailingslashit( home_url( '/personalizar/' . $slug ) );
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

		$customization_url = self::get_customization_page_url( $product_id );

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
			<a
				href="<?php echo esc_url( $customization_url ); ?>"
				class="wpdm-btn-personalizar wpdm-add-customized-to-cart"
				data-product-id="<?php echo esc_attr( $product_id ); ?>"
				data-customization-url="<?php echo esc_url( $customization_url ); ?>"
				style="display:inline-block;padding:12px 28px;background:#0073aa;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;cursor:pointer;"
			>
				<?php esc_html_e( 'Añadir con personalización', 'woo-prices-dynamics-makito' ); ?>
			</a>
		</div>
		<script>
		console.log('WPDM: Botón de personalización renderizado. URL:', '<?php echo esc_js( $customization_url ); ?>');
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

		// La nueva plantilla autónoma maneja todo cuando ?wpdm_personalizar=1
		// No inyectar el modal antiguo para evitar que cubra el contenido de la plantilla
		if ( self::is_customization_page() ) {
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
			'product_id' => $product->get_id(),
			'is_customization_page' => self::is_customization_page(),
			'customization_page_url' => self::get_customization_page_url( $product->get_id() ),
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
		console.log('%c=== WPDM: Inicializando módulo de personalización ===', 'background: #0073aa; color: #fff; font-size: 14px; padding: 5px;');
		window.wpdmCustomization = <?php echo wp_json_encode( $localize_data ); ?>;
		console.log('wpdmCustomization definido:', window.wpdmCustomization);
		console.log('¿Es página de personalización?', window.wpdmCustomization.is_customization_page);
		
		// Forzar detección por URL
		var isCustomizationPageByURL = new URLSearchParams(window.location.search).get('wpdm_personalizar') === '1';
		console.log('¿wpdm_personalizar en URL?', isCustomizationPageByURL);

		// Verificar si el archivo JS se cargó
		jQuery(document).ready(function($) {
			console.log('%c[WPDM] jQuery ready ejecutado', 'background: #ff9900; color: #fff; font-weight: bold;');
			
			// Forzar detección y setup de página de personalización
			var isPageMode = window.wpdmCustomization && window.wpdmCustomization.is_customization_page;
			console.log('[WPDM] Modo página detectado:', isPageMode);
			
			if ( isPageMode || isCustomizationPageByURL ) {
				console.log('%c[WPDM] FORZANDO MODO PÁGINA DE PERSONALIZACIÓN', 'background: #00aa00; color: #fff; font-weight: bold;');
				$('body').addClass('wpdm-customization-page-open');
				console.log('[WPDM] Clase wpdm-customization-page-open agregada al body');
				
				var $modal = $('#wpdm-customization-modal');
				console.log('[WPDM] Modal encontrado:', $modal.length > 0);
				
				if ( $modal.length > 0 ) {
					$modal.show();
					console.log('[WPDM] Modal mostrado');
				}
			}

			// Listener único para el botón "Añadir con personalización"
			// Recoge las cantidades de la tabla de variaciones, las guarda en sessionStorage
			// y redirige a la página /personalizar/{slug}/
			$(document).on('click', '.wpdm-add-customized-to-cart', function(e) {
				e.preventDefault();

				var customizationUrl = $(this).data('customization-url');
				if ( ! customizationUrl ) {
					return; // No hay URL configurada, no hacer nada
				}

				var productId = $(this).data('product-id');

				// ── Recoger cantidades de la tabla de variaciones ──
				var variationsMap = {};
				$('.wpdm-table-qty-input').each(function() {
					var qty = parseInt($(this).val(), 10) || 0;
					if (qty <= 0) return;
					var variationId  = $(this).data('variation-id');
					var $cell        = $(this).closest('td');
					var $row         = $cell.closest('tr');
					var $table       = $row.closest('table');
					var colorName    = '';
					var $rowLabel    = $row.find('td.wpdm-table-row-label').first();
					if ($rowLabel.length) {
						var $cn = $rowLabel.find('.wpdm-color-name');
						colorName = $cn.length ? $cn.text().trim() : $rowLabel.text().trim();
					}
					var cellIndex = $cell.index();
					var sizeName  = $table.find('thead tr th').eq(cellIndex).text().trim();
					var fullName  = colorName + (sizeName ? ' - ' + sizeName : '');
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
				});

				var selectedVariations = [];
				for (var k in variationsMap) {
					selectedVariations.push(variationsMap[k]);
				}

				// ── Fallback: input qty estándar de WooCommerce ──
				if (!selectedVariations.length) {
					var stdQty = parseInt($('input.qty').val(), 10) || 1;
					selectedVariations = [{
						variation_id: 0,
						color: '',
						size: '',
						full_name: 'Unidades',
						quantity: stdQty
					}];
				}

				console.log('[WPDM] Variaciones a guardar:', selectedVariations);

				// ── Guardar en sessionStorage ──
				try {
					sessionStorage.setItem(
						'wpdm_selected_variations_' + productId,
						JSON.stringify(selectedVariations)
					);
				} catch(ex) {
					console.warn('[WPDM] sessionStorage no disponible:', ex);
				}

				// ── Redirigir ──
				console.log('[WPDM] Redirigiendo a:', customizationUrl);
				window.location.href = customizationUrl;
			});
			}); // end jQuery ready
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
		body.wpdm-customization-page-open {
			overflow: hidden !important;
		}
		body.wpdm-customization-page-open #wpdm-customization-modal {
			display: block !important;
			position: fixed !important;
			top: 0 !important;
			left: 0 !important;
			right: 0 !important;
			bottom: 0 !important;
			width: 100% !important;
			height: 100% !important;
			min-height: 100vh !important;
			background: #ffffff !important;
			padding: 0 !important;
			margin: 0 !important;
			z-index: 999999 !important;
			overflow: auto !important;
		}
		body.wpdm-customization-page-open #wpdm-customization-modal .wpdm-customization-modal-overlay {
			display: none !important;
		}
		body.wpdm-customization-page-open #wpdm-customization-modal .wpdm-customization-modal-content {
			border-radius: 0 !important;
			max-width: none !important;
			width: 100% !important;
			height: auto !important;
			min-height: 100vh !important;
			margin: 0 !important;
			box-shadow: none !important;
			padding: 20px !important;
		}
		body.wpdm-customization-page-open #wpdm-customization-modal .wpdm-customization-modal-header {
			position: sticky;
			top: 0;
			z-index: 10;
			background: #ffffff;
		}
		body.wpdm-customization-page-open #wpdm-customization-modal .wpdm-customization-modal-body {
			padding-bottom: 80px !important;
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
					<!-- Tabs para separar Áreas, Imágenes y Desglose -->
					<div class="wpdm-modal-tabs" style="display: flex; background: #e9ecef; margin: -20px 0 20px 0; padding: 8px 20px 0 20px; gap: 8px; border-bottom: 2px solid #dee2e6; border-radius: 0;">
						<button class="wpdm-modal-tab active" data-tab="areas" style="padding: 14px 30px; cursor: pointer; border: 1px solid #dee2e6; border-bottom: 2px solid #fff; background: #fff; font-size: 1em; font-weight: 700; color: #0464AC; border-radius: 8px 8px 0 0; margin-bottom: -2px; box-shadow: 0 -3px 8px rgba(0,0,0,0.08); transition: all 0.3s ease; letter-spacing: 0.3px;">
							<?php esc_html_e( 'ÁREAS', 'woo-prices-dynamics-makito' ); ?>
						</button>
						<button class="wpdm-modal-tab" data-tab="imagenes" style="padding: 14px 30px; cursor: pointer; border: 1px solid transparent; border-bottom: 2px solid transparent; background: #f8f9fa; font-size: 1em; font-weight: 700; color: #6c757d; border-radius: 8px 8px 0 0; margin-bottom: -2px; transition: all 0.3s ease; letter-spacing: 0.3px;">
							<?php esc_html_e( 'DISEÑO', 'woo-prices-dynamics-makito' ); ?>
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

					<!-- Tab Content: Diseño (Imágenes + PANTONE + Observaciones) -->
					<div class="wpdm-modal-tab-content" id="wpdm-tab-imagenes" style="display: none; max-height: 40vh; overflow-y: auto; padding: 20px 0;">
						<div class="wpdm-images-upload-container">
							<div class="wpdm-images-notice" style="background: #e7f3ff; border-left: 4px solid #0464AC; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
								<p style="margin: 0; color: #0464AC; font-weight: 500;">
									<span style="font-size: 1.2em;">🎨</span> 
									<?php esc_html_e( 'Completa los detalles de diseño para cada área de marcaje seleccionada.', 'woo-prices-dynamics-makito' ); ?>
								</p>
								<p style="margin: 5px 0 0 0; font-size: 0.9em; color: #666;">
									<?php esc_html_e( 'Sube archivos (JPG, PNG, PDF, EPS, AI, CDR - máx. 5MB), selecciona colores PANTONE si aplica, y añade observaciones.', 'woo-prices-dynamics-makito' ); ?>
								</p>
							</div>
							<div id="wpdm-images-upload-list">
								<!-- Contenido dinámico: se genera según el modo (global/per-color) -->
								<div style="text-align: center; padding: 40px; color: #999;">
									<p><?php esc_html_e( 'Selecciona áreas en la pestaña "Áreas" para completar el diseño.', 'woo-prices-dynamics-makito' ); ?></p>
								</div>
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
