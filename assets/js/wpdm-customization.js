(function($) {
	'use strict';

	console.log('%c[WPDM] Archivo wpdm-customization.js CARGADO', 'background: #00aa00; color: #fff; font-size: 16px; padding: 8px;');

	var WPDMCustomization = {
		modal: null,
		productId: null,
		areas: [],
		customizationData: {
			areas: []
		},
		totalQuantity: 1,
		baseProductPrice: 0,

		init: function() {
			console.log('WPDM Customization: Inicializando...');
			this.bindEvents();
			console.log('WPDM Customization: Eventos enlazados');
		},

		bindEvents: function() {
			var self = this;

			// Abrir modal al hacer clic en botón "Añadir al carrito personalizado"
			$(document).on('click', '.wpdm-add-customized-to-cart', function(e) {
				e.preventDefault();
				console.log('WPDM Customization: Botón clickeado');
				self.productId = $(this).data('product-id');
				console.log('WPDM Customization: Product ID:', self.productId);
				self.openModal();
			});

			// Cerrar modal
			$(document).on('click', '.wpdm-customization-modal-close, .wpdm-customization-modal-overlay, .wpdm-customization-cancel', function() {
				self.closeModal();
			});

			// Prevenir cierre al hacer clic dentro del contenido
			$(document).on('click', '.wpdm-customization-modal-content', function(e) {
				e.stopPropagation();
			});

			// Cambios en áreas
			$(document).on('change', '.wpdm-area-enabled', function() {
				self.updateAreaState($(this).closest('.wpdm-area-item'));
				self.calculatePrices();
			});

			$(document).on('change', '.wpdm-area-technique, .wpdm-area-colors, .wpdm-area-width, .wpdm-area-height', function() {
				self.updateAreaData($(this).closest('.wpdm-area-item'));
				self.calculatePrices();
			});

			$(document).on('change', '.wpdm-area-cliche-repetition', function() {
				self.updateAreaData($(this).closest('.wpdm-area-item'));
				self.calculatePrices();
			});

			// Upload de imágenes
			$(document).on('change', '.wpdm-area-image-input', function() {
				self.uploadImage($(this));
			});

			// Eliminar imagen
			$(document).on('click', '.wpdm-area-image-remove', function() {
				var $areaItem = $(this).closest('.wpdm-area-item');
				var areaIndex = $areaItem.data('area-index');
				self.customizationData.areas[areaIndex].images = [];
				$areaItem.find('.wpdm-area-images-preview').empty();
				$areaItem.find('.wpdm-area-image-input').val('');
			});

			// Cambio de cantidad
			$(document).on('change', '.wpdm-customization-quantity', function() {
				self.totalQuantity = parseInt($(this).val(), 10) || 1;
				self.calculatePrices();
			});

			// Añadir al carrito
			$(document).on('click', '.wpdm-customization-add-to-cart', function() {
				self.addToCart();
			});
		},

		openModal: function() {
			var self = this;
			console.log('WPDM Customization: Abriendo modal...');
			this.modal = $('#wpdm-customization-modal');
			
			if (this.modal.length === 0) {
				console.error('WPDM Customization: Modal no encontrado en el DOM');
				alert('Error: El modal de personalización no se encontró. Por favor, recarga la página.');
				return;
			}
			
			console.log('WPDM Customization: Modal encontrado, mostrando...');
			this.modal.fadeIn(300);
			$('body').addClass('wpdm-modal-open');

			// Cargar datos de personalización
			this.loadCustomizationData();
		},

		closeModal: function() {
			if (this.modal) {
				this.modal.fadeOut(300);
				$('body').removeClass('wpdm-modal-open');
			}
		},

		loadCustomizationData: function() {
			var self = this;

			$('.wpdm-customization-loading').show();
			$('.wpdm-customization-content').hide();
			$('.wpdm-customization-modal-footer').hide();

			// PASO 1: Mostrar mensaje básico primero para verificar que el modal funciona
			setTimeout(function() {
				$('.wpdm-customization-loading').hide();
				$('.wpdm-customization-content').html('<p>Modal funcionando correctamente. Product ID: ' + self.productId + '</p><p>Ahora cargaremos las áreas de marcaje...</p>').show();
				$('.wpdm-customization-modal-footer').show();
				
				// Intentar cargar datos reales
				$.ajax({
					url: wpdmCustomization.ajax_url,
					type: 'POST',
					data: {
						action: 'wpdm_get_customization_data',
						nonce: wpdmCustomization.nonce,
						product_id: self.productId
					},
					success: function(response) {
						console.log('WPDM Customization Response:', response);
						if (response.success) {
							self.areas = response.data.areas;
							if (self.areas && self.areas.length > 0) {
								self.renderCustomizationForm();
							} else {
								$('.wpdm-customization-content').html('<p style="color: red;">No se encontraron áreas de marcaje para este producto.</p>');
							}
						} else {
							$('.wpdm-customization-content').html('<p style="color: red;">Error: ' + (response.data.message || wpdmCustomization.i18n.error) + '</p>');
						}
					},
					error: function(xhr, status, error) {
						console.error('WPDM Customization Error:', xhr, status, error);
						$('.wpdm-customization-content').html('<p style="color: red;">Error al cargar datos. Revisa la consola del navegador.</p>');
					}
				});
			}, 500);
		},

		renderCustomizationForm: function() {
			var self = this;
			var html = '<div class="wpdm-customization-quantity-wrapper">';
			html += '<label for="wpdm-customization-quantity">' + wpdmCustomization.i18n.base_product + ':</label>';
			html += '<input type="number" id="wpdm-customization-quantity" class="wpdm-customization-quantity" min="1" value="1" />';
			html += '</div>';

			html += '<div class="wpdm-customization-areas">';

			this.areas.forEach(function(area, index) {
				html += self.renderAreaItem(area, index);
			});

			html += '</div>';

			$('.wpdm-customization-content').html(html).show();
			$('.wpdm-customization-modal-footer').show();

			// Inicializar datos de personalización
			this.customizationData.areas = this.areas.map(function(area) {
				return {
					enabled: false,
					print_area_id: area.print_area_id || 0,
					position: area.position || '',
					technique_ref: area.technique_ref || '',
					colors: area.max_colors ? Math.min(1, area.max_colors) : 1,
					width: area.width || '',
					height: area.height || '',
					images: [],
					pantone: '',
					cliche_repetition: false,
					observations: ''
				};
			});

			// Obtener precio base del producto
			this.getBaseProductPrice();
		},

		renderAreaItem: function(area, index) {
			var html = '<div class="wpdm-area-item" data-area-index="' + index + '">';
			html += '<div class="wpdm-area-header">';
			html += '<label class="wpdm-area-checkbox-label">';
			html += '<input type="checkbox" class="wpdm-area-enabled" />';
			html += '<strong>' + (area.position || 'Área ' + (index + 1)) + '</strong>';
			html += '</label>';
			if (area.area_img) {
				html += '<img src="' + area.area_img + '" alt="' + area.position + '" class="wpdm-area-preview-img" />';
			}
			html += '</div>';

			html += '<div class="wpdm-area-content" style="display: none;">';

			// Información del área
			html += '<div class="wpdm-area-info">';
			if (area.position) {
				html += '<p><strong>' + wpdmCustomization.i18n.position + '</strong> ' + area.position + '</p>';
			}
			if (area.width && area.height) {
				html += '<p><strong>' + wpdmCustomization.i18n.dimensions + '</strong> ' + area.width + ' x ' + area.height + ' mm</p>';
			}
			if (area.max_colors) {
				html += '<p><strong>' + wpdmCustomization.i18n.max_colors + '</strong> ' + area.max_colors + '</p>';
			}
			html += '</div>';

			// Técnica
			html += '<div class="wpdm-area-field">';
			html += '<label>' + wpdmCustomization.i18n.technique + '</label>';
			html += '<select class="wpdm-area-technique">';
			html += '<option value="">' + wpdmCustomization.i18n.select_technique + '</option>';
			if (area.technique && area.technique.name) {
				html += '<option value="' + area.technique_ref + '" selected>' + area.technique.name + '</option>';
			}
			html += '</select>';
			html += '</div>';

			// Colores
			html += '<div class="wpdm-area-field">';
			html += '<label>' + wpdmCustomization.i18n.colors + '</label>';
			html += '<select class="wpdm-area-colors">';
			for (var i = 1; i <= area.max_colors; i++) {
				html += '<option value="' + i + '">' + i + '</option>';
			}
			html += '</select>';
			html += '</div>';

			// Dimensiones personalizadas
			html += '<div class="wpdm-area-field">';
			html += '<label>' + wpdmCustomization.i18n.print_dimensions + '</label>';
			html += '<div class="wpdm-area-dimensions">';
			html += '<input type="text" class="wpdm-area-width" placeholder="' + (area.width || 'Ancho') + '" value="' + (area.width || '') + '" />';
			html += ' x ';
			html += '<input type="text" class="wpdm-area-height" placeholder="' + (area.height || 'Alto') + '" value="' + (area.height || '') + '" />';
			html += ' mm';
			html += '</div>';
			html += '</div>';

			// Pantone
			html += '<div class="wpdm-area-field">';
			html += '<label>' + wpdmCustomization.i18n.pantone + '</label>';
			html += '<input type="text" class="wpdm-area-pantone" placeholder="PANTONE 286 C" />';
			html += '</div>';

			// Upload de imagen
			html += '<div class="wpdm-area-field">';
			html += '<label>' + wpdmCustomization.i18n.upload_image_label + '</label>';
			html += '<input type="file" class="wpdm-area-image-input" accept="image/*" multiple />';
			html += '<div class="wpdm-area-images-preview"></div>';
			html += '</div>';

			// Repetición cliché
			html += '<div class="wpdm-area-field">';
			html += '<label>';
			html += '<input type="checkbox" class="wpdm-area-cliche-repetition" />';
			html += ' ' + wpdmCustomization.i18n.cliche_repetition;
			html += '</label>';
			html += '</div>';

			// Observaciones
			html += '<div class="wpdm-area-field">';
			html += '<label>' + wpdmCustomization.i18n.observations + '</label>';
			html += '<textarea class="wpdm-area-observations" rows="3"></textarea>';
			html += '</div>';

			html += '</div>'; // .wpdm-area-content
			html += '</div>'; // .wpdm-area-item

			return html;
		},

		updateAreaState: function($areaItem) {
			var enabled = $areaItem.find('.wpdm-area-enabled').is(':checked');
			$areaItem.find('.wpdm-area-content').toggle(enabled);
			
			var areaIndex = $areaItem.data('area-index');
			if (this.customizationData.areas[areaIndex]) {
				this.customizationData.areas[areaIndex].enabled = enabled;
			}
		},

		updateAreaData: function($areaItem) {
			var areaIndex = $areaItem.data('area-index');
			if (!this.customizationData.areas[areaIndex]) {
				return;
			}

			var areaData = this.customizationData.areas[areaIndex];
			areaData.enabled = $areaItem.find('.wpdm-area-enabled').is(':checked');
			areaData.technique_ref = $areaItem.find('.wpdm-area-technique').val() || '';
			areaData.colors = parseInt($areaItem.find('.wpdm-area-colors').val(), 10) || 1;
			areaData.width = $areaItem.find('.wpdm-area-width').val() || '';
			areaData.height = $areaItem.find('.wpdm-area-height').val() || '';
			areaData.pantone = $areaItem.find('.wpdm-area-pantone').val() || '';
			areaData.cliche_repetition = $areaItem.find('.wpdm-area-cliche-repetition').is(':checked');
			areaData.observations = $areaItem.find('.wpdm-area-observations').val() || '';
			
			// Obtener datos del área original para guardar posición, print_area_id, etc.
			if (this.areas[areaIndex]) {
				areaData.print_area_id = this.areas[areaIndex].print_area_id;
				areaData.position = this.areas[areaIndex].position;
			}
		},

		uploadImage: function($input) {
			var self = this;
			var files = $input[0].files;
			var $areaItem = $input.closest('.wpdm-area-item');
			var areaIndex = $areaItem.data('area-index');

			if (!files || files.length === 0) {
				return;
			}

			// Subir cada archivo
			Array.from(files).forEach(function(file) {
				var formData = new FormData();
				formData.append('action', 'wpdm_upload_customization_image');
				formData.append('nonce', wpdmCustomization.nonce);
				formData.append('image', file);

				$.ajax({
					url: wpdmCustomization.ajax_url,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					beforeSend: function() {
						$input.prop('disabled', true);
					},
					success: function(response) {
						if (response.success) {
							if (!self.customizationData.areas[areaIndex].images) {
								self.customizationData.areas[areaIndex].images = [];
							}
							self.customizationData.areas[areaIndex].images.push(response.data.url);
							
							// Mostrar preview
							var $preview = $areaItem.find('.wpdm-area-images-preview');
							$preview.append('<div class="wpdm-area-image-preview"><img src="' + response.data.url + '" /><button type="button" class="wpdm-area-image-remove">×</button></div>');
						} else {
							alert(response.data.message || wpdmCustomization.i18n.upload_error);
						}
					},
					error: function() {
						alert(wpdmCustomization.i18n.upload_error);
					},
					complete: function() {
						$input.prop('disabled', false);
					}
				});
			});
		},

		getBaseProductPrice: function() {
			// Obtener precio base del producto (ya implementado en el plugin)
			// Por ahora usar precio regular, luego se puede mejorar
			var $priceElement = $('.price .amount, .woocommerce-Price-amount').first();
			if ($priceElement.length) {
				var priceText = $priceElement.text().replace(/[^\d,.-]/g, '').replace(',', '.');
				this.baseProductPrice = parseFloat(priceText) || 0;
			}
		},

		calculatePrices: function() {
			var self = this;

			// Actualizar datos de áreas
			$('.wpdm-area-item').each(function() {
				if ($(this).find('.wpdm-area-enabled').is(':checked')) {
					self.updateAreaData($(this));
				}
			});

			// Calcular precios via AJAX
			$.ajax({
				url: wpdmCustomization.ajax_url,
				type: 'POST',
				data: {
					action: 'wpdm_calculate_customization_price',
					nonce: wpdmCustomization.nonce,
					product_id: this.productId,
					total_quantity: this.totalQuantity,
					customization_data: JSON.stringify(this.customizationData)
				},
				beforeSend: function() {
					$('.wpdm-customization-total-price').text(wpdmCustomization.i18n.calculating);
				},
				success: function(response) {
					if (response.success) {
						var total = response.data.total || 0;
						self.updatePriceDisplay(total);
						
						// Habilitar/deshabilitar botón
						var hasEnabledAreas = self.customizationData.areas.some(function(area) {
							return area.enabled && area.technique_ref;
						});
						$('.wpdm-customization-add-to-cart').prop('disabled', !hasEnabledAreas || total <= 0);
					}
				},
				error: function() {
					$('.wpdm-customization-total-price').text('0,00 €');
				}
			});
		},

		updatePriceDisplay: function(total) {
			var formatted = this.formatPrice(total);
			$('.wpdm-customization-total-price').text(formatted);
		},

		formatPrice: function(price) {
			price = parseFloat(price) || 0;
			var formatted = price.toFixed(wpdmCustomization.price_decimals);
			
			if (wpdmCustomization.price_thousand_sep) {
				var parts = formatted.split('.');
				parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, wpdmCustomization.price_thousand_sep);
				formatted = parts.join(wpdmCustomization.price_decimal_sep);
			} else {
				formatted = formatted.replace('.', wpdmCustomization.price_decimal_sep);
			}
			
			switch(wpdmCustomization.currency_pos) {
				case 'left':
					return wpdmCustomization.currency_symbol + formatted;
				case 'right':
					return formatted + ' ' + wpdmCustomization.currency_symbol;
				case 'left_space':
					return wpdmCustomization.currency_symbol + ' ' + formatted;
				case 'right_space':
					return formatted + ' ' + wpdmCustomization.currency_symbol;
				default:
					return wpdmCustomization.currency_symbol + formatted;
			}
		},

		addToCart: function() {
			var self = this;

			// Actualizar datos finales
			$('.wpdm-area-item').each(function() {
				if ($(this).find('.wpdm-area-enabled').is(':checked')) {
					self.updateAreaData($(this));
				}
			});

			var $button = $('.wpdm-customization-add-to-cart');
			$button.prop('disabled', true).text(wpdmCustomization.i18n.adding);

			// Obtener variation_id si existe (para productos variables)
			var variationId = 0;
			if ($('input[name="variation_id"]').length) {
				variationId = parseInt($('input[name="variation_id"]').val(), 10) || 0;
			}

			// Usar endpoint AJAX personalizado
			$.ajax({
				url: wpdmCustomization.ajax_url,
				type: 'POST',
				data: {
					action: 'wpdm_add_customized_to_cart',
					nonce: wpdmCustomization.nonce,
					product_id: this.productId,
					variation_id: variationId,
					quantity: this.totalQuantity,
					customization: JSON.stringify(this.customizationData)
				},
				success: function(response) {
					if (response.success) {
						// Actualizar fragmentos del carrito
						if (response.data.fragments) {
							$.each(response.data.fragments, function(key, value) {
								$(key).replaceWith(value);
							});
						}

						// Disparar eventos de WooCommerce
						$('body').trigger('wc_fragment_refresh');
						$('body').trigger('added_to_cart', [response.data.fragments || {}, response.data.cart_hash || '', $button]);
						
						self.closeModal();
						
						// Mostrar notificación (si existe el sistema de notificaciones)
						if (typeof WPDMTable !== 'undefined' && WPDMTable.showSuccessMessage) {
							WPDMTable.showSuccessMessage(wpdmCustomization.i18n.success);
						} else {
							alert(wpdmCustomization.i18n.success);
						}
					} else {
						alert(response.data.message || wpdmCustomization.i18n.error_add);
						$button.prop('disabled', false).text(wpdmCustomization.i18n.add_to_cart);
					}
				},
				error: function() {
					alert(wpdmCustomization.i18n.error_add);
					$button.prop('disabled', false).text(wpdmCustomization.i18n.add_to_cart);
				}
			});
		}
	};

	$(document).ready(function() {
		console.log('WPDM Customization: Document ready, inicializando...');
		console.log('WPDM Customization: jQuery disponible:', typeof $ !== 'undefined');
		console.log('WPDM Customization: wpdmCustomization disponible:', typeof wpdmCustomization !== 'undefined');
		
		// Verificar que el modal esté en el DOM
		var $modal = $('#wpdm-customization-modal');
		console.log('WPDM Customization: Modal en DOM:', $modal.length > 0);
		
		// Verificar que haya botones de personalización
		var $buttons = $('.wpdm-add-customized-to-cart');
		console.log('WPDM Customization: Botones encontrados:', $buttons.length);
		$buttons.each(function(index) {
			console.log('WPDM Customization: Botón #' + (index + 1) + ' - Product ID:', $(this).data('product-id'));
		});
		
		if (typeof wpdmCustomization === 'undefined') {
			console.error('WPDM Customization: wpdmCustomization no está definido. El script no se ha localizado correctamente.');
			return;
		}
		
		WPDMCustomization.init();
		console.log('WPDM Customization: Inicialización completa');
	});

})(jQuery);

