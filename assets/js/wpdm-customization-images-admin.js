jQuery(document).ready(function($) {
	'use strict';

	// Seleccionar/deseleccionar todas
	$('#wpdm-select-all').on('click', function() {
		$('.wpdm-image-checkbox').prop('checked', true);
		$('.wpdm-image-card').addClass('selected');
	});

	$('#wpdm-deselect-all').on('click', function() {
		$('.wpdm-image-checkbox').prop('checked', false);
		$('.wpdm-image-card').removeClass('selected');
	});

	// Toggle selección individual
	$(document).on('change', '.wpdm-image-checkbox', function() {
		var $card = $(this).closest('.wpdm-image-card');
		if ($(this).is(':checked')) {
			$card.addClass('selected');
		} else {
			$card.removeClass('selected');
		}
	});

	// Eliminar imagen individual
	$(document).on('click', '.wpdm-delete-single', function(e) {
		e.preventDefault();
		
		if (!confirm(wpdmImagesAdmin.strings.confirm_delete)) {
			return;
		}

		var $button = $(this);
		var imagePath = $button.data('image-path');
		var $card = $button.closest('.wpdm-image-card');

		$button.prop('disabled', true).text(wpdmImagesAdmin.strings.deleting);

		$.ajax({
			url: wpdmImagesAdmin.ajax_url,
			type: 'POST',
			data: {
				action: 'wpdm_delete_customization_image',
				nonce: wpdmImagesAdmin.nonce,
				image_path: imagePath
			},
			success: function(response) {
				if (response.success) {
					$card.fadeOut(300, function() {
						$(this).remove();
						// Actualizar estadísticas si es necesario
						location.reload();
					});
				} else {
					alert(response.data.message || wpdmImagesAdmin.strings.error);
					$button.prop('disabled', false).html('🗑️ Eliminar');
				}
			},
			error: function() {
				alert(wpdmImagesAdmin.strings.error);
				$button.prop('disabled', false).html('🗑️ Eliminar');
			}
		});
	});

	// Eliminación masiva
	$('#wpdm-bulk-delete').on('click', function() {
		var selected = $('.wpdm-image-checkbox:checked').map(function() {
			return $(this).val();
		}).get();

		if (selected.length === 0) {
			alert(wpdmImagesAdmin.strings.select_at_least_one);
			return;
		}

		if (!confirm(wpdmImagesAdmin.strings.confirm_bulk_delete)) {
			return;
		}

		var $button = $(this);
		$button.prop('disabled', true).text(wpdmImagesAdmin.strings.deleting);

		$.ajax({
			url: wpdmImagesAdmin.ajax_url,
			type: 'POST',
			data: {
				action: 'wpdm_bulk_delete_customization_images',
				nonce: wpdmImagesAdmin.nonce,
				image_paths: selected
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message || wpdmImagesAdmin.strings.error);
					$button.prop('disabled', false).html('🗑️ Eliminar seleccionadas');
				}
			},
			error: function() {
				alert(wpdmImagesAdmin.strings.error);
				$button.prop('disabled', false).html('🗑️ Eliminar seleccionadas');
			}
		});
	});
});





