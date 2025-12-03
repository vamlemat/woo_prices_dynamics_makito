<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestión de personalización de productos (áreas de marcaje y técnicas).
 */
class WPDM_Customization {

	/**
	 * Directorio base para guardar imágenes de personalización.
	 */
	const UPLOAD_DIR = 'wpdm-customizations';

	/**
	 * Inicialización.
	 */
	public static function init() {
		// Crear directorio de uploads si no existe
		add_action( 'init', array( __CLASS__, 'create_upload_directory' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_wpdm_get_customization_data', array( __CLASS__, 'ajax_get_customization_data' ) );
		add_action( 'wp_ajax_nopriv_wpdm_get_customization_data', array( __CLASS__, 'ajax_get_customization_data' ) );
		add_action( 'wp_ajax_wpdm_calculate_customization_price', array( __CLASS__, 'ajax_calculate_customization_price' ) );
		add_action( 'wp_ajax_nopriv_wpdm_calculate_customization_price', array( __CLASS__, 'ajax_calculate_customization_price' ) );
		add_action( 'wp_ajax_wpdm_upload_customization_image', array( __CLASS__, 'ajax_upload_customization_image' ) );
		add_action( 'wp_ajax_nopriv_wpdm_upload_customization_image', array( __CLASS__, 'ajax_upload_customization_image' ) );
		add_action( 'wp_ajax_wpdm_add_customized_to_cart', array( __CLASS__, 'ajax_add_customized_to_cart' ) );
		add_action( 'wp_ajax_nopriv_wpdm_add_customized_to_cart', array( __CLASS__, 'ajax_add_customized_to_cart' ) );
	}

	/**
	 * Crear directorio para guardar imágenes de personalización.
	 */
	public static function create_upload_directory() {
		$upload_dir = wp_upload_dir();
		$custom_dir = $upload_dir['basedir'] . '/' . self::UPLOAD_DIR;
		
		if ( ! file_exists( $custom_dir ) ) {
			wp_mkdir_p( $custom_dir );
			
			// Crear archivo .htaccess para proteger el directorio
			$htaccess_content = "Options -Indexes\n";
			file_put_contents( $custom_dir . '/.htaccess', $htaccess_content );
		}
	}

	/**
	 * Obtener áreas de marcaje de un producto.
	 *
	 * @param int $product_id ID del producto.
	 * @return array Array de áreas de marcaje.
	 */
	public static function get_marking_areas( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 ) {
			return array();
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		// Intentar obtener repeater marking_areas (puede venir de diferentes formas según JetEngine)
		$marking_areas = get_post_meta( $product_id, 'marking_areas', true );
		
		// Si está vacío, intentar con prefijo underscore (algunos plugins lo guardan así)
		if ( empty( $marking_areas ) ) {
			$marking_areas = get_post_meta( $product_id, '_marking_areas', true );
		}

		// JetEngine puede guardar los repeaters de diferentes formas
		// Intentar también obtener directamente desde la base de datos
		if ( empty( $marking_areas ) ) {
			global $wpdb;
			$meta_value = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
				$product_id,
				'marking_areas'
			) );
			
			if ( $meta_value ) {
				$marking_areas = $meta_value;
			}
		}

		// Si está serializado, deserializar
		if ( is_string( $marking_areas ) ) {
			$decoded = maybe_unserialize( $marking_areas );
			if ( is_array( $decoded ) ) {
				$marking_areas = $decoded;
			} else {
				// Intentar decodificar JSON
				$json_decoded = json_decode( $marking_areas, true );
				if ( is_array( $json_decoded ) ) {
					$marking_areas = $json_decoded;
				}
			}
		}

		// Debug temporal - guardar en opción para mostrar en frontend
		if ( current_user_can( 'manage_options' ) ) {
			update_option( 'wpdm_debug_product_' . $product_id, array(
				'product_id' => $product_id,
				'has_marking_areas_meta' => ! empty( get_post_meta( $product_id, 'marking_areas', true ) ),
				'marking_areas_type' => gettype( $marking_areas ),
				'marking_areas_count' => is_array( $marking_areas ) ? count( $marking_areas ) : 0,
				'marking_areas_raw' => $marking_areas,
			) );
		}

		if ( ! is_array( $marking_areas ) || empty( $marking_areas ) ) {
			return array();
		}

		// Normalizar y validar áreas
		$normalized_areas = array();
		foreach ( $marking_areas as $area ) {
			if ( ! is_array( $area ) ) {
				continue;
			}

			$print_area_id = isset( $area['print_area_id'] ) ? absint( $area['print_area_id'] ) : 0;
			$technique_ref = isset( $area['technique_ref'] ) ? sanitize_text_field( $area['technique_ref'] ) : '';
			$position = isset( $area['position'] ) ? sanitize_text_field( $area['position'] ) : '';
			$max_colors = isset( $area['max_colors'] ) ? absint( $area['max_colors'] ) : 1;
			$width = isset( $area['width'] ) ? sanitize_text_field( $area['width'] ) : '';
			$height = isset( $area['height'] ) ? sanitize_text_field( $area['height'] ) : '';
			$area_img = isset( $area['area_img'] ) ? esc_url_raw( $area['area_img'] ) : '';

			// Validar que tenga al menos technique_ref (print_area_id puede ser 0 en algunos casos)
			if ( ! empty( $technique_ref ) ) {
				$normalized_areas[] = array(
					'print_area_id' => $print_area_id,
					'technique_ref' => $technique_ref,
					'position' => $position,
					'max_colors' => $max_colors > 0 ? $max_colors : 1,
					'width' => $width,
					'height' => $height,
					'area_img' => $area_img,
				);
			}
		}

		return $normalized_areas;
	}

	/**
	 * Obtener técnica de marcación por referencia.
	 *
	 * @param string $technique_ref Referencia de la técnica (ej: "100216").
	 * @return WP_Post|null Objeto de la técnica o null si no existe.
	 */
	public static function get_technique_by_ref( $technique_ref ) {
		if ( empty( $technique_ref ) ) {
			return null;
		}

		$technique_ref = sanitize_text_field( $technique_ref );

		$args = array(
			'post_type' => 'tecnicas-marcacion',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => 'technique_ref',
					'value' => $technique_ref,
					'compare' => '=',
				),
			),
		);

		$query = new WP_Query( $args );
		
		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return null;
	}

	/**
	 * Obtener datos completos de una técnica.
	 *
	 * @param int|WP_Post $technique ID o objeto de la técnica.
	 * @return array Datos de la técnica.
	 */
	public static function get_technique_data( $technique ) {
		if ( is_numeric( $technique ) ) {
			$technique = get_post( $technique );
		}

		if ( ! $technique || 'tecnicas-marcacion' !== $technique->post_type ) {
			return array();
		}

		$technique_id = $technique->ID;

		// Obtener campos simples
		$technique_ref = get_post_meta( $technique_id, 'technique_ref', true );
		$col_inc = absint( get_post_meta( $technique_id, 'col_inc', true ) );
		$cliche = floatval( get_post_meta( $technique_id, 'cliche', true ) );
		$cliche_repetition = floatval( get_post_meta( $technique_id, 'cliche_repetition', true ) );
		$min = absint( get_post_meta( $technique_id, 'min', true ) );
		$code = get_post_meta( $technique_id, 'code', true );
		$notice_txt = get_post_meta( $technique_id, 'notice_txt', true );

		// Obtener repeater precio_escalas
		$precio_escalas = get_post_meta( $technique_id, 'precio_escalas', true );
		if ( is_string( $precio_escalas ) ) {
			$precio_escalas = maybe_unserialize( $precio_escalas );
		}
		if ( ! is_array( $precio_escalas ) ) {
			$precio_escalas = array();
		}

		// Obtener traducciones
		$translations = get_post_meta( $technique_id, 'translations', true );
		if ( is_string( $translations ) ) {
			$translations = maybe_unserialize( $translations );
		}
		if ( ! is_array( $translations ) ) {
			$translations = array();
		}

		// Obtener nombre en el idioma actual
		$locale = get_locale();
		$lang_code = substr( $locale, 0, 2 );
		$technique_name = $technique->post_title;

		foreach ( $translations as $translation ) {
			if ( isset( $translation['lang_code'] ) && strtoupper( $translation['lang_code'] ) === strtoupper( $lang_code ) ) {
				if ( ! empty( $translation['name'] ) ) {
					$technique_name = $translation['name'];
					break;
				}
			}
		}

		return array(
			'id' => $technique_id,
			'ref' => $technique_ref,
			'name' => $technique_name,
			'col_inc' => $col_inc > 0 ? $col_inc : 1,
			'cliche' => $cliche,
			'cliche_repetition' => $cliche_repetition,
			'min' => $min,
			'code' => $code,
			'notice_txt' => $notice_txt,
			'precio_escalas' => $precio_escalas,
		);
	}

	/**
	 * Calcular precio de personalización para un área.
	 *
	 * @param array $area_data Datos del área personalizada.
	 * @param int   $total_quantity Cantidad total del pedido.
	 * @return array Datos de precio calculado.
	 */
	public static function calculate_area_price( $area_data, $total_quantity ) {
		$result = array(
			'technique_unit_price' => 0,
			'technique_total_price' => 0,
			'color_extra_price' => 0,
			'color_extra_total' => 0,
			'cliche_price' => 0,
			'cliche_repetition_price' => 0,
			'area_total' => 0,
			'quantity_used' => 0,
			'minimum_applied' => false,
		);

		if ( empty( $area_data['technique_ref'] ) || $total_quantity <= 0 ) {
			return $result;
		}

		// Obtener técnica
		$technique = self::get_technique_by_ref( $area_data['technique_ref'] );
		if ( ! $technique ) {
			return $result;
		}

		$technique_data = self::get_technique_data( $technique );
		if ( empty( $technique_data['precio_escalas'] ) ) {
			return $result;
		}

		// IMPORTANTE: Aplicar mínimo ANTES de calcular precios
		// Si la cantidad es menor que el mínimo, usar el mínimo para el cálculo de la técnica
		$min = $technique_data['min'];
		$quantity_for_technique = $total_quantity;
		$minimum_applied = false;
		
		if ( $min > 0 && $total_quantity < $min ) {
			$quantity_for_technique = $min;
			$minimum_applied = true;
		}

		// Buscar tramo de precio según cantidad efectiva (con mínimo aplicado)
		$selected_tier = null;
		$precio_escalas = $technique_data['precio_escalas'];

		foreach ( $precio_escalas as $tier ) {
			$section_desde = isset( $tier['section_desde'] ) ? absint( $tier['section_desde'] ) : 0;
			$section_hasta = isset( $tier['section_hasta'] ) ? absint( $tier['section_hasta'] ) : 0;

			if ( $quantity_for_technique >= $section_desde && ( 0 === $section_hasta || $quantity_for_technique <= $section_hasta ) ) {
				$selected_tier = $tier;
				break;
			}
		}

		// Si no hay match, usar el último tramo
		if ( ! $selected_tier && ! empty( $precio_escalas ) ) {
			$selected_tier = end( $precio_escalas );
		}

		if ( ! $selected_tier ) {
			return $result;
		}

		// Precio base por unidad según el tramo
		$technique_unit_price = isset( $selected_tier['price'] ) ? floatval( $selected_tier['price'] ) : 0;
		
		// IMPORTANTE: El precio total se calcula con la cantidad efectiva (puede ser el mínimo)
		$technique_total_price = $technique_unit_price * $quantity_for_technique;

		// Calcular precio por colores adicionales
		$col_inc = $technique_data['col_inc'];
		$colors_selected = isset( $area_data['colors'] ) ? absint( $area_data['colors'] ) : $col_inc;
		$colors_extra = max( 0, $colors_selected - $col_inc );

		$price_col = isset( $selected_tier['price_col'] ) ? floatval( $selected_tier['price_col'] ) : 0;
		$color_extra_price = $price_col;
		
		// Los colores extra se cobran por la cantidad REAL solicitada, no por el mínimo
		$color_extra_total = $colors_extra > 0 ? ( $price_col * $colors_extra * $total_quantity ) : 0;

		// Coste de cliché: se multiplica por el TOTAL de colores seleccionados
		// Si hay repetición de cliché marcada, se usa ese precio en lugar del cliché normal
		$cliche_unit_price = 0;
		$cliche_price = 0;
		$cliche_repetition_price = 0;
		$cliche_colors_qty = $colors_selected; // Total de colores (incluidos los incluidos)
		
		if ( ! empty( $area_data['cliche_repetition'] ) ) {
			// Si está marcada repetición, SOLO se usa el precio de repetición
			$cliche_unit_price = $technique_data['cliche_repetition'];
			$cliche_repetition_price = $cliche_unit_price * $cliche_colors_qty;
			$cliche_price = 0; // No se suma el cliché normal
		} else {
			// Si NO hay repetición, se usa el precio de cliché normal
			$cliche_unit_price = $technique_data['cliche'];
			$cliche_price = $cliche_unit_price * $cliche_colors_qty;
			$cliche_repetition_price = 0;
		}

		// Total del área
		$area_total = $technique_total_price + $color_extra_total + $cliche_price + $cliche_repetition_price;

		return array(
			'technique_name' => $technique_data['name'],
			'technique_unit_price' => $technique_unit_price,
			'technique_total_price' => $technique_total_price,
			'quantity' => $total_quantity,
			'quantity_used' => $quantity_for_technique,
			'minimum_applied' => $minimum_applied,
			'color_extra_price' => $color_extra_price,
			'color_extra_qty' => $colors_extra,
			'color_extra_total' => $color_extra_total,
			'cliche_unit_price' => $cliche_unit_price,
			'cliche_colors_qty' => $cliche_colors_qty,
			'cliche_price' => $cliche_price,
			'cliche_repetition_price' => $cliche_repetition_price,
			'area_total' => $area_total,
		);
	}

	/**
	 * Calcular precio total de personalización.
	 *
	 * @param array $customization_data Datos de personalización.
	 * @param int   $total_quantity Cantidad total del pedido.
	 * @return array Datos de precios calculados.
	 */
	public static function calculate_total_customization_price( $customization_data, $total_quantity ) {
		$total = 0;
		$areas_prices = array();

		if ( empty( $customization_data['areas'] ) || ! is_array( $customization_data['areas'] ) ) {
			return array(
				'total' => 0,
				'areas' => array(),
			);
		}

		foreach ( $customization_data['areas'] as $area_index => $area_data ) {
			if ( empty( $area_data['enabled'] ) || empty( $area_data['technique_ref'] ) ) {
				continue;
			}

			// Usar la cantidad específica del área si está definida, sino usar la cantidad total
			$area_quantity = isset( $area_data['quantity'] ) && $area_data['quantity'] > 0 
				? absint( $area_data['quantity'] ) 
				: $total_quantity;

			// Cada área de trabajo lleva su propio cliché (fotolito)
			$area_price = self::calculate_area_price( $area_data, $area_quantity );

			$areas_prices[ $area_index ] = $area_price;
			$total += $area_price['area_total'];
		}

		return array(
			'total' => $total,
			'areas' => $areas_prices,
		);
	}

	/**
	 * Guardar imagen de personalización.
	 *
	 * @param array $file Datos del archivo subido.
	 * @return array|WP_Error URL de la imagen guardada o error.
	 */
	public static function save_customization_image( $file ) {
		if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'invalid_file', __( 'Archivo no válido.', 'woo-prices-dynamics-makito' ) );
		}

		// Validar tipo de archivo
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		$file_type = wp_check_filetype( $file['name'] );
		
		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			return new WP_Error( 'invalid_type', __( 'Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, GIF, WEBP).', 'woo-prices-dynamics-makito' ) );
		}

		// Validar tamaño (máximo 5MB)
		$max_size = 5 * 1024 * 1024; // 5MB
		if ( $file['size'] > $max_size ) {
			return new WP_Error( 'file_too_large', __( 'El archivo es demasiado grande. Máximo 5MB.', 'woo-prices-dynamics-makito' ) );
		}

		// Crear estructura de directorios por año/mes
		$upload_dir = wp_upload_dir();
		$custom_dir = $upload_dir['basedir'] . '/' . self::UPLOAD_DIR;
		$year = date( 'Y' );
		$month = date( 'm' );
		$target_dir = $custom_dir . '/' . $year . '/' . $month;

		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		// Generar nombre único
		$filename = wp_unique_filename( $target_dir, $file['name'] );
		$target_path = $target_dir . '/' . $filename;

		// Mover archivo
		if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
			return new WP_Error( 'upload_failed', __( 'Error al guardar el archivo.', 'woo-prices-dynamics-makito' ) );
		}

		// Generar URL
		$url = $upload_dir['baseurl'] . '/' . self::UPLOAD_DIR . '/' . $year . '/' . $month . '/' . $filename;

		return array(
			'url' => $url,
			'path' => $target_path,
			'filename' => $filename,
		);
	}

	/**
	 * AJAX: Obtener datos de personalización de un producto.
	 */
	public static function ajax_get_customization_data() {
		check_ajax_referer( 'wpdm_customization_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( $product_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'ID de producto inválido.', 'woo-prices-dynamics-makito' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Producto no encontrado.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Obtener áreas de marcaje
		$marking_areas = self::get_marking_areas( $product_id );

		if ( empty( $marking_areas ) ) {
			wp_send_json_error( array( 'message' => __( 'Este producto no tiene áreas de marcaje disponibles.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Agrupar áreas por print_area_id para consolidar técnicas
		$grouped_areas = array();
		foreach ( $marking_areas as $area ) {
			$area_id = $area['print_area_id'];
			
			// Si el área no existe aún, crearla
			if ( ! isset( $grouped_areas[ $area_id ] ) ) {
				$grouped_areas[ $area_id ] = array(
					'print_area_id' => $area_id,
					'position' => $area['position'],
					'max_colors' => $area['max_colors'],
					'width' => $area['width'],
					'height' => $area['height'],
					'area_img' => $area['area_img'],
					'techniques' => array(),
				);
			}
			
			// Añadir técnica a esta área
			$technique = self::get_technique_by_ref( $area['technique_ref'] );
			if ( $technique ) {
				$technique_data = self::get_technique_data( $technique );
				$technique_data['technique_ref'] = $area['technique_ref'];
				$grouped_areas[ $area_id ]['techniques'][] = $technique_data;
			}
		}
		
		// Convertir a array indexado
		$areas_with_techniques = array_values( $grouped_areas );
		
		// Ordenar áreas por posición (Area 1, Area 2, etc.)
		usort( $areas_with_techniques, function( $a, $b ) {
			// Extraer el número del área (ej: "Area 8" -> 8)
			preg_match( '/\d+/', $a['position'], $matches_a );
			preg_match( '/\d+/', $b['position'], $matches_b );
			
			$num_a = isset( $matches_a[0] ) ? intval( $matches_a[0] ) : 0;
			$num_b = isset( $matches_b[0] ) ? intval( $matches_b[0] ) : 0;
			
			return $num_a - $num_b;
		} );

		wp_send_json_success( array(
			'areas' => $areas_with_techniques,
			'product_id' => $product_id,
		) );
	}

	/**
	 * AJAX: Calcular precio de personalización.
	 */
	public static function ajax_calculate_customization_price() {
		check_ajax_referer( 'wpdm_customization_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$total_quantity = isset( $_POST['total_quantity'] ) ? absint( $_POST['total_quantity'] ) : 0;
		$customization_data = isset( $_POST['customization_data'] ) ? json_decode( stripslashes( $_POST['customization_data'] ), true ) : array();

		if ( $product_id <= 0 || $total_quantity <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Datos inválidos.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Calcular precios de personalización
		$price_calculation = self::calculate_total_customization_price( $customization_data, $total_quantity );

		// Obtener precio base del producto según la cantidad
		$base_price = 0;
		$product = wc_get_product( $product_id );
		if ( $product ) {
			// Si tiene price_tiers, usar ese precio
			if ( class_exists( 'WPDM_Price_Tiers' ) ) {
				$tier_price = WPDM_Price_Tiers::get_price_from_tiers( $product_id, $total_quantity );
				if ( $tier_price > 0 ) {
					$base_price = $tier_price;
				}
			}
			
			// Si no hay tier price, usar precio regular
			if ( $base_price <= 0 ) {
				$base_price = floatval( $product->get_price() );
			}
		}

		$base_total = $base_price * $total_quantity;
		$customization_total = $price_calculation['total'];
		$grand_total = $base_total + $customization_total;

		wp_send_json_success( array(
			'base_price' => $base_price,
			'base_total' => $base_total,
			'customization_total' => $customization_total,
			'grand_total' => $grand_total,
			'areas' => $price_calculation['areas'],
			'currency_symbol' => get_woocommerce_currency_symbol(),
		) );
	}

	/**
	 * AJAX: Subir imagen de personalización.
	 */
	public static function ajax_upload_customization_image() {
		check_ajax_referer( 'wpdm_customization_nonce', 'nonce' );

		if ( ! isset( $_FILES['image'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No se recibió ningún archivo.', 'woo-prices-dynamics-makito' ) ) );
		}

		$result = self::save_customization_image( $_FILES['image'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'url' => $result['url'],
			'filename' => $result['filename'],
		) );
	}

	/**
	 * AJAX: Añadir producto personalizado al carrito.
	 */
	public static function ajax_add_customized_to_cart() {
		check_ajax_referer( 'wpdm_customization_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$customization_data = isset( $_POST['customization'] ) ? json_decode( stripslashes( $_POST['customization'] ), true ) : array();

		if ( $product_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'ID de producto inválido.', 'woo-prices-dynamics-makito' ) ) );
		}

		if ( $quantity <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Cantidad inválida.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Validar que WooCommerce esté disponible
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce no está disponible.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Validar producto
		$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Producto no encontrado.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Si es variación, validar que pertenezca al producto
		if ( $variation_id > 0 ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || $variation->get_parent_id() != $product_id ) {
				wp_send_json_error( array( 'message' => __( 'Variación no válida.', 'woo-prices-dynamics-makito' ) ) );
			}
		}

		// Calcular precio de personalización
		$customization_price = 0;
		if ( ! empty( $customization_data ) && is_array( $customization_data ) ) {
			$price_result = self::calculate_total_customization_price( $customization_data, $quantity );
			$customization_price = $price_result['total'];
		}

		// Preparar datos para añadir al carrito
		$cart_item_data = array();
		
		if ( ! empty( $customization_data ) ) {
			$cart_item_data['wpdm_customization'] = $customization_data;
			$cart_item_data['wpdm_customization_price'] = $customization_price;
		}

		// Añadir al carrito
		if ( $variation_id > 0 ) {
			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, array(), $cart_item_data );
		} else {
			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, 0, array(), $cart_item_data );
		}

		if ( ! $cart_item_key ) {
			wp_send_json_error( array( 'message' => __( 'Error al añadir al carrito.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Aplicar precio de personalización al ítem del carrito
		if ( $customization_price > 0 && isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
			$cart_item = WC()->cart->cart_contents[ $cart_item_key ];
			
			// El precio del producto ya está aplicado por WPDM_Cart_Adjustments
			// Solo guardamos el precio de personalización
			WC()->cart->cart_contents[ $cart_item_key ]['wpdm_customization_price'] = $customization_price;
		}

		// Guardar carrito en sesión
		WC()->cart->set_session();

		// Preparar respuesta con fragmentos
		$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

		wp_send_json_success( array(
			'message' => __( 'Producto añadido al carrito correctamente.', 'woo-prices-dynamics-makito' ),
			'cart_hash' => WC()->cart->get_cart_hash(),
			'fragments' => $fragments,
		) );
	}
}

