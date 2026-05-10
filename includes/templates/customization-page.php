<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
	$product = wc_get_product( get_the_ID() );
}

// Asegurar que los scripts están encolados
wp_enqueue_script( 'jquery' );
wp_enqueue_style(
	'wpdm-customization',
	plugin_dir_url( WPDM_WOOPRICES_PLUGIN_FILE ) . 'assets/css/wpdm-customization.css',
	array(),
	WPDM_WOOPRICES_VERSION
);
wp_enqueue_script(
	'wpdm-customization',
	plugin_dir_url( WPDM_WOOPRICES_PLUGIN_FILE ) . 'assets/js/wpdm-customization.js',
	array( 'jquery' ),
	WPDM_WOOPRICES_VERSION,
	true
);

get_header();
?>

<div class="wpdm-customization-page-wrapper" style="padding: 40px 15px; max-width: 1400px; margin: 0 auto;">
	<div style="margin-bottom: 24px; font-size: 0.95rem; color: #6c757d;">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Inicio</a>
		<span style="margin: 0 8px;">/</span>
		<a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Tienda</a>
		<?php if ( $product ) : ?>
			<span style="margin: 0 8px;">/</span>
			<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
		<?php endif; ?>
		<span style="margin: 0 8px;">/</span>
		<strong>Personalizar</strong>
	</div>

	<?php if ( $product ) : ?>
	<div style="margin-bottom: 32px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
		<h1 style="font-size: 2rem; margin: 0; color: #1f2937;"><?php echo esc_html( $product->get_name() ); ?> — Personalización</h1>
		<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" class="button" style="background:#f3f4f6;color:#111827;border:1px solid #d1d5db;padding:10px 20px;text-decoration:none;border-radius:8px;">
			← Volver al producto
		</a>
	</div>
	<?php endif; ?>

	<!-- Zona principal de personalización -->
	<div id="wpdm-page-customization-area" style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:32px;box-shadow:0 4px 20px rgba(0,0,0,0.05);">

		<!-- Estado: cargando -->
		<div id="wpdm-page-loading" style="text-align:center;padding:60px 20px;color:#6b7280;">
			<div style="font-size:2em;margin-bottom:12px;">⏳</div>
			<p style="font-size:1.1em;margin:0;">Cargando opciones de personalización...</p>
		</div>

		<!-- Estado: error -->
		<div id="wpdm-page-error" style="display:none;text-align:center;padding:60px 20px;color:#dc2626;">
			<div style="font-size:2em;margin-bottom:12px;">❌</div>
			<p id="wpdm-page-error-msg" style="font-size:1.1em;margin:0;">Error al cargar datos.</p>
		</div>

		<!-- Estado: sin áreas -->
		<div id="wpdm-page-no-areas" style="display:none;text-align:center;padding:60px 20px;color:#6b7280;">
			<div style="font-size:2em;margin-bottom:12px;">ℹ️</div>
			<p style="font-size:1.1em;margin:0;">Este producto no tiene áreas de marcaje configuradas.</p>
		</div>

		<!-- Contenido principal (dinámico) -->
		<div id="wpdm-page-content" style="display:none;">
			<!-- Pregunta: ¿global o por color? -->
			<div id="wpdm-page-mode-selector" style="margin-bottom:28px;padding:20px;background:#f0f7ff;border-radius:10px;border:2px solid #0464AC;">
				<p style="margin:0 0 12px;font-weight:600;font-size:1.05em;">¿Desea marcar todos los artículos de la misma forma?</p>
				<p style="margin:0 0 14px;font-size:0.9em;color:#555;">Elija <strong>Sí</strong> para marcar todos igual, o <strong>No</strong> para marcar cada color de forma diferente.</p>
				<div style="display:flex;gap:24px;">
					<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
						<input type="radio" name="wpdm-page-mode" value="global" checked>
						<strong>Sí (Global)</strong>
					</label>
					<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
						<input type="radio" name="wpdm-page-mode" value="per-color">
						<strong>No (Por color)</strong>
					</label>
				</div>
			</div>

			<!-- Áreas -->
			<div id="wpdm-page-areas-container"></div>

			<!-- Resumen de precio -->
			<div id="wpdm-page-price-summary" style="margin-top:28px;padding:20px;background:linear-gradient(135deg,#f8f9fa,#e9ecef);border-radius:12px;border:2px solid #0464AC;text-align:center;">
				<div style="font-size:0.95em;color:#666;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Total Personalización:</div>
				<div id="wpdm-page-total-price" style="font-size:2.4em;font-weight:700;color:#0464AC;margin-top:8px;">0,00 €</div>
			</div>

			<!-- Botón añadir al carrito -->
			<div style="margin-top:24px;text-align:right;">
				<button type="button" id="wpdm-page-add-to-cart" class="button button-primary" disabled
					style="padding:14px 36px;font-size:1.1em;background:#0464AC;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:700;opacity:0.5;transition:all 0.2s;">
					🛒 Añadir al carrito
				</button>
			</div>
		</div>

	</div>
</div>

<script type="text/javascript">
(function($) {
	'use strict';

	var productId = <?php echo $product ? intval( $product->get_id() ) : 0; ?>;
	var ajaxUrl   = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var nonce     = '<?php echo esc_js( wp_create_nonce( 'wpdm_customization_nonce' ) ); ?>';
	var allAreas  = [];
	var selectedVariations = []; // Se leerán de sessionStorage si existen

	// ── Recuperar variaciones guardadas en sessionStorage ──
	try {
		var stored = sessionStorage.getItem('wpdm_selected_variations_' + productId);
		if (stored) {
			selectedVariations = JSON.parse(stored);
			console.log('[WPDM Page] Variaciones recuperadas:', selectedVariations);
		}
	} catch(e) {}

	// ── Helpers ──
	function showLoading()  { $('#wpdm-page-loading').show();  $('#wpdm-page-error').hide(); $('#wpdm-page-content').hide(); $('#wpdm-page-no-areas').hide(); }
	function showError(msg) { $('#wpdm-page-error-msg').text(msg || 'Error al cargar datos.'); $('#wpdm-page-error').show(); $('#wpdm-page-loading').hide(); }
	function showContent()  { $('#wpdm-page-loading').hide(); $('#wpdm-page-error').hide(); $('#wpdm-page-content').show(); }
	function showNoAreas()  { $('#wpdm-page-no-areas').show(); $('#wpdm-page-loading').hide(); }

	// ── Render de un área individual ──
	function renderAreaItem(area, index, variation) {
		var uid = variation ? 'var-' + variation.variation_id + '-area-' + index : 'global-area-' + index;
		var varAttr = variation ? ' data-variation-id="' + variation.variation_id + '"' : '';
		var html = '<div class="wpdm-area-item" data-area-index="' + index + '" data-area-id="' + area.print_area_id + '" data-area-position="' + (area.position || 'Área ' + (index+1)) + '" data-unique-id="' + uid + '"' + varAttr + ' style="border:1px solid #e0e0e0;border-radius:8px;margin-bottom:16px;overflow:hidden;">';
		html += '<div class="wpdm-area-header" style="padding:14px 18px;background:#f5f5f5;display:flex;justify-content:space-between;align-items:center;cursor:pointer;">';
		html += '<label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin:0;flex:1;">';
		html += '<input type="checkbox" class="wpdm-area-enabled" style="width:18px;height:18px;">';
		html += '<strong>Zona de impresión — ' + (area.position || 'Área ' + (index+1)) + '</strong>';
		html += '</label>';
		if (area.area_img) html += '<img src="' + area.area_img + '" style="max-width:70px;max-height:70px;border-radius:4px;border:1px solid #ddd;">';
		html += '</div>';

		html += '<div class="wpdm-area-content" style="display:none;padding:18px;background:#fff;">';

		// Técnica
		html += '<div style="margin-bottom:14px;"><label style="display:block;margin-bottom:6px;font-weight:500;">Técnica de marcación:</label>';
		html += '<select class="wpdm-area-technique" style="width:100%;padding:9px;border:1px solid #ddd;border-radius:4px;">';
		html += '<option value="">Selecciona una técnica...</option>';
		if (area.techniques && area.techniques.length) {
			area.techniques.forEach(function(t) {
				html += '<option value="' + t.ref + '" data-technique-name="' + t.name + '">' + t.name + '</option>';
			});
		}
		html += '</select></div>';

		// Colores
		html += '<div style="margin-bottom:14px;"><label style="display:block;margin-bottom:6px;font-weight:500;">Número de colores:</label>';
		html += '<select class="wpdm-area-colors" style="width:100%;padding:9px;border:1px solid #ddd;border-radius:4px;">';
		for (var i = 1; i <= (area.max_colors || 4); i++) {
			html += '<option value="' + i + '">' + i + ' COLOR' + (i > 1 ? 'ES' : '') + '</option>';
		}
		html += '</select></div>';

		// Dimensiones
		html += '<div style="margin-bottom:14px;"><label style="display:block;margin-bottom:6px;font-weight:500;">Medida de impresión:</label>';
		html += '<div style="display:flex;align-items:center;gap:8px;">';
		html += '<input type="number" class="wpdm-area-width" value="' + (area.width || '') + '" placeholder="Ancho" step="0.1" style="width:90px;padding:9px;border:1px solid #ddd;border-radius:4px;">';
		html += '<span>×</span>';
		html += '<input type="number" class="wpdm-area-height" value="' + (area.height || '') + '" placeholder="Alto" step="0.1" style="width:90px;padding:9px;border:1px solid #ddd;border-radius:4px;">';
		html += '<span>mm</span></div></div>';

		// Cliché
		html += '<div style="margin-bottom:14px;"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;">';
		html += '<input type="checkbox" class="wpdm-area-cliche-repetition"> <span>Repetición Cliché</span></label>';
		html += '<div class="wpdm-cliche-order-number-wrapper" style="display:none;margin-top:8px;padding-left:28px;">';
		html += '<input type="text" class="wpdm-area-cliche-order-number" placeholder="Nº pedido anterior" style="width:200px;padding:8px;border:1px solid #ddd;border-radius:4px;">';
		html += '</div></div>';

		html += '</div></div>';
		return html;
	}

	// ── Render global ──
	function renderGlobal(areas) {
		var html = '<div id="wpdm-areas-list">';
		areas.forEach(function(area, i) { html += renderAreaItem(area, i, null); });
		html += '</div>';
		$('#wpdm-page-areas-container').html(html);
	}

	// ── Render por color ──
	function renderByColor(areas, variations) {
		var html = '<div id="wpdm-areas-list">';
		variations.forEach(function(variation, vi) {
			html += '<div style="border:1px solid #ddd;border-radius:8px;margin-bottom:14px;overflow:hidden;">';
			html += '<div class="wpdm-color-accordion-header" data-variation-index="' + vi + '" style="padding:14px 18px;background:linear-gradient(135deg,#0464AC,#061B46);color:#fff;cursor:pointer;display:flex;justify-content:space-between;align-items:center;">';
			html += '<strong>' + variation.full_name + ' (' + variation.quantity + ' uds)</strong>';
			html += '<span class="wpdm-accordion-toggle">▼</span></div>';
			html += '<div class="wpdm-color-accordion-content" style="display:none;padding:14px;background:#fff;">';
			areas.forEach(function(area, i) { html += renderAreaItem(area, i, variation); });
			html += '</div></div>';
		});
		html += '</div>';
		$('#wpdm-page-areas-container').html(html);

		$(document).off('click.wpdm-page-accordion').on('click.wpdm-page-accordion', '.wpdm-color-accordion-header', function(e) {
			if ($(e.target).is('input,label,select,textarea')) return;
			var $content = $(this).next('.wpdm-color-accordion-content');
			var $toggle  = $(this).find('.wpdm-accordion-toggle');
			if ($content.is(':visible')) { $content.slideUp(250); $toggle.text('▼'); }
			else { $('.wpdm-color-accordion-content').slideUp(250); $('.wpdm-accordion-toggle').text('▼'); $content.slideDown(250); $toggle.text('▲'); }
		});
	}

	// ── Calcular precio ──
	function calculatePrice() {
		var mode = $('input[name="wpdm-page-mode"]:checked').val() || 'global';
		var variations = selectedVariations;
		var totalQty = 0;
		variations.forEach(function(v) { totalQty += v.quantity; });
		if (totalQty <= 0) totalQty = 1;

		var cusData = { mode: mode, areas: [] };
		$('.wpdm-area-item').each(function() {
			var $a = $(this);
			if (!$a.find('.wpdm-area-enabled').is(':checked')) return;
			var techRef = $a.find('.wpdm-area-technique').val();
			if (!techRef) return;
			var areaQty = totalQty;
			if (mode === 'per-color') {
				var $acc = $a.closest('[data-variation-index]');
				if ($acc.length) {
					var vi = $acc.find('.wpdm-color-accordion-header').data('variation-index');
					if (variations[vi]) areaQty = variations[vi].quantity;
				}
			}
			cusData.areas.push({
				enabled: true,
				technique_ref: techRef,
				colors: parseInt($a.find('.wpdm-area-colors').val()) || 1,
				width: parseFloat($a.find('.wpdm-area-width').val()) || 0,
				height: parseFloat($a.find('.wpdm-area-height').val()) || 0,
				cliche_repetition: $a.find('.wpdm-area-cliche-repetition').is(':checked'),
				cliche_order_number: $a.find('.wpdm-area-cliche-order-number').val() || '',
				quantity: areaQty
			});
		});

		var hasAreas = cusData.areas.length > 0;
		$('#wpdm-page-add-to-cart').prop('disabled', !hasAreas).css('opacity', hasAreas ? '1' : '0.5');

		if (!hasAreas) { $('#wpdm-page-total-price').text('0,00 €'); return; }

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpdm_calculate_customization_price',
				nonce: nonce,
				product_id: productId,
				total_quantity: totalQty,
				customization_data: JSON.stringify(cusData)
			},
			success: function(r) {
				if (r.success && r.data) {
					var g = parseFloat(r.data.grand_total).toFixed(2).replace('.', ',');
					$('#wpdm-page-total-price').text(g + ' €');
					$('#wpdm-page-add-to-cart').data('customization', cusData).data('total-qty', totalQty);
				}
			}
		});
	}

	// ── Añadir al carrito ──
	function addToCart() {
		var $btn = $('#wpdm-page-add-to-cart');
		var cusData = $btn.data('customization');
		var totalQty = $btn.data('total-qty') || 1;
		if (!cusData) { alert('Por favor configura al menos un área de marcaje.'); return; }

		$btn.prop('disabled', true).text('Añadiendo...');

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpdm_add_customized_to_cart',
				nonce: nonce,
				product_id: productId,
				variation_id: 0,
				quantity: totalQty,
				customization: JSON.stringify(cusData)
			},
			success: function(r) {
				if (r.success) {
					$('body').trigger('wc_fragment_refresh');
					alert('✅ Producto añadido al carrito correctamente.');
					window.location.href = '<?php echo esc_js( wc_get_cart_url() ); ?>';
				} else {
					alert('Error: ' + (r.data && r.data.message ? r.data.message : 'No se pudo añadir al carrito.'));
					$btn.prop('disabled', false).text('🛒 Añadir al carrito');
				}
			},
			error: function() {
				alert('Error de conexión. Inténtalo de nuevo.');
				$btn.prop('disabled', false).text('🛒 Añadir al carrito');
			}
		});
	}

	// ── Init ──
	$(document).ready(function() {
		if (!productId) { showError('No se pudo determinar el producto.'); return; }
		showLoading();

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpdm_get_customization_data',
				nonce: nonce,
				product_id: productId
			},
			success: function(r) {
				if (!r.success || !r.data || !r.data.areas || !r.data.areas.length) {
					showNoAreas();
					return;
				}
				allAreas = r.data.areas;

				// Si no hay variaciones guardadas y hay modo per-color disponible, ocultar el selector
				if (!selectedVariations.length) {
					$('#wpdm-page-mode-selector').hide();
				}

				renderGlobal(allAreas);
				showContent();
				console.log('[WPDM Page] Áreas cargadas:', allAreas.length);
			},
			error: function() { showError('Error de conexión al cargar los datos.'); }
		});

		// Cambio de modo
		$(document).on('change', 'input[name="wpdm-page-mode"]', function() {
			var mode = $(this).val();
			if (mode === 'per-color' && selectedVariations.length) {
				renderByColor(allAreas, selectedVariations);
			} else {
				renderGlobal(allAreas);
			}
		});

		// Toggle área habilitada
		$(document).on('change', '.wpdm-area-enabled', function() {
			$(this).closest('.wpdm-area-item').find('.wpdm-area-content').toggle($(this).is(':checked'));
			calculatePrice();
		});

		// Toggle cliché
		$(document).on('change', '.wpdm-area-cliche-repetition', function() {
			$(this).closest('.wpdm-area-item').find('.wpdm-cliche-order-number-wrapper').toggle($(this).is(':checked'));
			calculatePrice();
		});

		// Cambios que afectan precio
		$(document).on('change', '.wpdm-area-technique, .wpdm-area-colors, .wpdm-area-width, .wpdm-area-height', function() {
			calculatePrice();
		});

		// Añadir al carrito
		$(document).on('click', '#wpdm-page-add-to-cart', function() {
			addToCart();
		});
	});

})(jQuery);
</script>

<?php get_footer(); ?>
