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
		add_action( 'wp_ajax_wpdm_get_cart_item_customization', array( __CLASS__, 'ajax_get_cart_item_customization' ) );
		add_action( 'wp_ajax_nopriv_wpdm_get_cart_item_customization', array( __CLASS__, 'ajax_get_cart_item_customization' ) );
		
		// Hooks para mostrar personalización en carrito
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_customization_in_cart' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_class', array( __CLASS__, 'add_cart_item_class' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'add_customization_to_cart_item_name' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_customization_to_order' ), 10, 4 );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( __CLASS__, 'format_order_item_meta' ), 10, 2 );
		
		// Añadir personalización como fee (cargo adicional separado)
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'add_customization_fees_to_cart' ), 20, 1 );
		
		// Deshabilitar cambio de cantidad para productos personalizados
		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'disable_quantity_change_for_customized' ), 10, 3 );
		add_filter( 'woocommerce_is_sold_individually', array( __CLASS__, 'mark_customized_as_sold_individually' ), 10, 2 );
		add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'prevent_quantity_update_for_customized' ), 10, 4 );
		
		// Metabox en admin del pedido
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_order_customization_metabox' ) );
		
		// AJAX para descargar imágenes
		add_action( 'wp_ajax_wpdm_download_customization_image', array( __CLASS__, 'ajax_download_customization_image' ) );
		add_action( 'wp_ajax_wpdm_download_all_images_zip', array( __CLASS__, 'ajax_download_all_images_zip' ) );
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

		// Buscar tramo de precio según cantidad total
		$selected_tier = null;
		$precio_escalas = $technique_data['precio_escalas'];

		foreach ( $precio_escalas as $tier ) {
			$section_desde = isset( $tier['section_desde'] ) ? absint( $tier['section_desde'] ) : 0;
			$section_hasta = isset( $tier['section_hasta'] ) ? absint( $tier['section_hasta'] ) : 0;

			if ( $total_quantity >= $section_desde && ( 0 === $section_hasta || $total_quantity <= $section_hasta ) ) {
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
		$technique_total_price = $technique_unit_price * $total_quantity;

		// Calcular precio por colores adicionales
		$col_inc = $technique_data['col_inc'];
		$colors_selected = isset( $area_data['colors'] ) ? absint( $area_data['colors'] ) : $col_inc;
		$colors_extra = max( 0, $colors_selected - $col_inc );

		$price_col = isset( $selected_tier['price_col'] ) ? floatval( $selected_tier['price_col'] ) : 0;
		$color_extra_price = $price_col;
		$color_extra_total = $colors_extra > 0 ? ( $price_col * $colors_extra * $total_quantity ) : 0;

		// IMPORTANTE: El "min" es un IMPORTE MÍNIMO que se aplica SOLO a la técnica + colores extra
		// El cliché se suma DESPUÉS de aplicar el mínimo
		$min = $technique_data['min'];
		$minimum_applied = false;
		$technique_and_colors_total = $technique_total_price + $color_extra_total;
		
		if ( $min > 0 && $technique_and_colors_total < $min ) {
			$technique_and_colors_total = $min;
			$minimum_applied = true;
		}

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

		// Total del área = (Técnica + Colores [con mínimo aplicado]) + Cliché
		$area_total = $technique_and_colors_total + $cliche_price + $cliche_repetition_price;

		return array(
			'technique_name' => $technique_data['name'],
			'technique_unit_price' => $technique_unit_price,
			'technique_total_price' => $technique_total_price,
			'quantity' => $total_quantity,
			'minimum_applied' => $minimum_applied,
			'minimum_amount' => $min,
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
			WPDM_Logger::warning( 'calculate_total_customization_price', 'No hay áreas para calcular', array(
				'customization_data' => $customization_data
			) );
			return array(
				'total' => 0,
				'areas' => array(),
			);
		}
		
		WPDM_Logger::debug( 'calculate_total_customization_price', 'Calculando precios', array(
			'total_quantity' => $total_quantity,
			'areas_count' => count( $customization_data['areas'] ),
			'areas_data' => $customization_data['areas']
		) );

		foreach ( $customization_data['areas'] as $area_index => $area_data ) {
			WPDM_Logger::debug( 'calculate_total_customization_price', 'Procesando área ' . $area_index, array(
				'enabled' => isset( $area_data['enabled'] ) ? $area_data['enabled'] : 'NOT SET',
				'technique_ref' => isset( $area_data['technique_ref'] ) ? $area_data['technique_ref'] : 'NOT SET',
				'area_data_keys' => array_keys( $area_data )
			) );
			
			if ( empty( $area_data['enabled'] ) || empty( $area_data['technique_ref'] ) ) {
				WPDM_Logger::warning( 'calculate_total_customization_price', 'Área omitida (sin enabled o technique_ref)', array(
					'area_index' => $area_index,
					'area_data' => $area_data
				) );
				continue;
			}

			// Usar la cantidad específica del área si está definida, sino usar la cantidad total
			$area_quantity = isset( $area_data['quantity'] ) && $area_data['quantity'] > 0 
				? absint( $area_data['quantity'] ) 
				: $total_quantity;

			// Cada área de trabajo lleva su propio cliché (fotolito)
			$area_price = self::calculate_area_price( $area_data, $area_quantity );
			
			WPDM_Logger::debug( 'calculate_total_customization_price', 'Precio de área calculado', array(
				'area_index' => $area_index,
				'area_quantity' => $area_quantity,
				'area_total' => $area_price['area_total']
			) );

			$areas_prices[ $area_index ] = $area_price;
			$total += $area_price['area_total'];
		}
		
		WPDM_Logger::info( 'calculate_total_customization_price', 'Cálculo completado', array(
			'total_price' => $total,
			'areas_processed' => count( $areas_prices )
		) );

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
				$technique_data['max_colors'] = isset( $area['max_colors'] ) ? absint( $area['max_colors'] ) : 1;
				$grouped_areas[ $area_id ]['techniques'][] = $technique_data;
				$grouped_areas[ $area_id ]['max_colors'] = max(
					absint( $grouped_areas[ $area_id ]['max_colors'] ),
					absint( $technique_data['max_colors'] )
				);
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
		WPDM_Logger::info( 'ajax_add_customized_to_cart', 'Iniciando proceso de añadir al carrito' );
		
		try {
			check_ajax_referer( 'wpdm_customization_nonce', 'nonce' );

			// Validar que WooCommerce esté disponible
			if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
				WPDM_Logger::error( 'ajax_add_customized_to_cart', 'WooCommerce no está disponible' );
				wp_send_json_error( array( 'message' => __( 'WooCommerce no está disponible.', 'woo-prices-dynamics-makito' ) ) );
			}

			// Obtener datos enviados
			$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			$mode = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'global';
			$variations = isset( $_POST['variations'] ) ? json_decode( stripslashes( $_POST['variations'] ), true ) : array();
			$customization_data = isset( $_POST['customization_data'] ) ? json_decode( stripslashes( $_POST['customization_data'] ), true ) : array();
			
			WPDM_Logger::debug( 'ajax_add_customized_to_cart', 'Datos recibidos', array(
				'product_id' => $product_id,
				'mode' => $mode,
				'variations_count' => count( $variations ),
				'areas_count' => isset( $customization_data['areas'] ) ? count( $customization_data['areas'] ) : 0
			) );

		// Validaciones básicas
		if ( $product_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'ID de producto inválido.', 'woo-prices-dynamics-makito' ) ) );
		}

		if ( empty( $variations ) ) {
			wp_send_json_error( array( 'message' => __( 'No se han seleccionado variaciones.', 'woo-prices-dynamics-makito' ) ) );
		}

		if ( empty( $customization_data['areas'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No se ha configurado personalización.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Validar producto padre
		$parent_product = wc_get_product( $product_id );
		if ( ! $parent_product ) {
			wp_send_json_error( array( 'message' => __( 'Producto no encontrado.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Procesar subida de imágenes
		$uploaded_images = array();
		
		if ( class_exists( 'WPDM_Logger' ) ) {
			WPDM_Logger::info( 'ajax_add_customized_to_cart', '🔍 VERIFICANDO ARCHIVOS RECIBIDOS', array(
				'has_files' => isset( $_FILES['images'] ),
				'files_not_empty' => isset( $_FILES['images'] ) && ! empty( $_FILES['images']['name'] ),
				'files_structure' => isset( $_FILES['images'] ) ? array_keys( $_FILES['images'] ) : array(),
				'files_name_count' => isset( $_FILES['images']['name'] ) ? ( is_array( $_FILES['images']['name'] ) ? count( $_FILES['images']['name'] ) : 1 ) : 0,
				'post_keys' => array_keys( $_POST ),
				'post_keys_images_meta' => array_filter( array_keys( $_POST ), function( $key ) { return strpos( $key, 'images_meta' ) !== false; } )
			) );
		}
		
		if ( isset( $_FILES['images'] ) && ! empty( $_FILES['images']['name'] ) ) {
			if ( class_exists( 'WPDM_Logger' ) ) {
				WPDM_Logger::info( 'ajax_add_customized_to_cart', '✅ ARCHIVOS DETECTADOS, PROCESANDO...', array(
					'files_count' => is_array( $_FILES['images']['name'] ) ? count( $_FILES['images']['name'] ) : 1,
					'post_meta_keys' => array_keys( $_POST )
				) );
			}
			
			$files = $_FILES['images'];
			
			// Si es un array de archivos
			if ( is_array( $files['name'] ) ) {
				$file_count = count( $files['name'] );
				
				for ( $i = 0; $i < $file_count; $i++ ) {
					// Verificar que el archivo no esté vacío
					if ( empty( $files['name'][ $i ] ) || $files['error'][ $i ] !== 0 ) {
						continue;
					}
					
					// Reconstruir estructura de archivo individual
					$single_file = array(
						'name'     => $files['name'][ $i ],
						'type'     => $files['type'][ $i ],
						'tmp_name' => $files['tmp_name'][ $i ],
						'error'    => $files['error'][ $i ],
						'size'     => $files['size'][ $i ]
					);
					
					// Obtener metadata de este archivo
					// El frontend envía: images_meta[0][area_id], images_meta[0][area_index], etc.
					$area_id = isset( $_POST['images_meta'][ $i ]['area_id'] ) ? absint( $_POST['images_meta'][ $i ]['area_id'] ) : 0;
					$area_index = isset( $_POST['images_meta'][ $i ]['area_index'] ) ? absint( $_POST['images_meta'][ $i ]['area_index'] ) : 0;
					$variation_id = isset( $_POST['images_meta'][ $i ]['variation_id'] ) ? absint( $_POST['images_meta'][ $i ]['variation_id'] ) : 0;
					
					// Fallback: intentar con formato antiguo si no se encuentra
					if ( $area_index === 0 && isset( $_POST[ 'images_meta[' . $i . '][area_index]' ] ) ) {
						$area_index = absint( $_POST[ 'images_meta[' . $i . '][area_index]' ] );
						$area_id = isset( $_POST[ 'images_meta[' . $i . '][area_id]' ] ) ? absint( $_POST[ 'images_meta[' . $i . '][area_id]' ] ) : 0;
						$variation_id = isset( $_POST[ 'images_meta[' . $i . '][variation_id]' ] ) ? absint( $_POST[ 'images_meta[' . $i . '][variation_id]' ] ) : 0;
					}
					
					if ( class_exists( 'WPDM_Logger' ) ) {
						WPDM_Logger::info( 'ajax_add_customized_to_cart', '📋 METADATOS DEL ARCHIVO', array(
							'index' => $i,
							'filename' => $single_file['name'],
							'area_id' => $area_id,
							'area_index' => $area_index,
							'variation_id' => $variation_id,
							'post_images_meta_structure' => isset( $_POST['images_meta'] ) ? ( is_array( $_POST['images_meta'] ) ? array_keys( $_POST['images_meta'] ) : 'NO ES ARRAY' ) : 'NO EXISTE',
							'post_images_meta_full' => isset( $_POST['images_meta'][ $i ] ) ? $_POST['images_meta'][ $i ] : 'NO EXISTE'
						) );
					}
					
					// CRÍTICO: area_index puede ser 0 (primera área), así que solo validamos que sea numérico
					if ( ! is_numeric( $area_index ) || $area_index < 0 ) {
						if ( class_exists( 'WPDM_Logger' ) ) {
							WPDM_Logger::warning( 'ajax_add_customized_to_cart', '⚠️ area_index inválido para archivo', array(
								'index' => $i,
								'filename' => $single_file['name'],
								'area_index' => $area_index,
								'area_index_type' => gettype( $area_index )
							) );
						}
						continue;
					}
					
					// Subir archivo
					if ( class_exists( 'WPDM_Logger' ) ) {
						WPDM_Logger::info( 'ajax_add_customized_to_cart', '📤 SUBIENDO ARCHIVO', array(
							'file_index' => $i,
							'filename' => $single_file['name'],
							'area_index' => $area_index,
							'variation_id' => $variation_id,
							'area_id' => $area_id
						) );
					}
					
					$uploaded = self::upload_single_customization_image( $single_file );
					
					if ( $uploaded && ! is_wp_error( $uploaded ) ) {
						if ( class_exists( 'WPDM_Logger' ) ) {
							WPDM_Logger::info( 'ajax_add_customized_to_cart', '✅ ARCHIVO SUBIDO EXITOSAMENTE', array(
								'file_index' => $i,
								'filename' => $uploaded['filename'] ?? 'N/A',
								'url' => $uploaded['url'] ?? 'N/A',
								'area_index' => $area_index,
								'variation_id' => $variation_id
							) );
						}
						
						// CRÍTICO: SIEMPRE asociar por variación (area_index + variation_id)
						// En modo global, si no hay variation_id en los metadatos, se copiará a todas las variaciones después
						$mode = isset( $customization_data['mode'] ) ? $customization_data['mode'] : 'global';
						
						if ( $variation_id > 0 ) {
							// Tenemos variation_id: asociar directamente a esta variación
							$storage_key = "area-{$area_index}-var-{$variation_id}";
							$uploaded_images[ $storage_key ] = $uploaded;
							
							if ( class_exists( 'WPDM_Logger' ) ) {
								WPDM_Logger::info( 'ajax_add_customized_to_cart', '✅ Imagen asociada a variación específica', array(
									'storage_key' => $storage_key,
									'filename' => $uploaded['filename'] ?? 'N/A',
									'url' => $uploaded['url'] ?? 'N/A',
									'area_index' => $area_index,
									'variation_id' => $variation_id,
									'file_index' => $i
								) );
							}
						} else {
							// No hay variation_id: guardar temporalmente para copiar a todas las variaciones después
							// Usar un storage_key temporal que luego se copiará
							$temp_key = "area-{$area_index}-temp";
							$uploaded_images[ $temp_key ] = $uploaded;
							
							if ( class_exists( 'WPDM_Logger' ) ) {
								WPDM_Logger::info( 'ajax_add_customized_to_cart', '⏳ Imagen guardada temporalmente (sin variation_id), se copiará a todas las variaciones', array(
									'temp_key' => $temp_key,
									'filename' => $uploaded['filename'] ?? 'N/A',
									'url' => $uploaded['url'] ?? 'N/A',
									'area_index' => $area_index,
									'file_index' => $i,
									'mode' => $mode
								) );
							}
						}
					} else {
						if ( class_exists( 'WPDM_Logger' ) ) {
							WPDM_Logger::error( 'ajax_add_customized_to_cart', '❌ ERROR AL SUBIR ARCHIVO', array(
								'file_index' => $i,
								'filename' => $single_file['name'],
								'error' => is_wp_error( $uploaded ) ? $uploaded->get_error_message() : 'Unknown error',
								'area_index' => $area_index,
								'variation_id' => $variation_id
							) );
						}
					}
				}
			} else {
				// Archivo único
				if ( ! empty( $files['name'] ) && $files['error'] === 0 ) {
					$uploaded = self::upload_single_customization_image( $files );
					if ( $uploaded && ! is_wp_error( $uploaded ) ) {
						// En caso de archivo único, intentar obtener metadata
						$area_index = isset( $_POST['images_meta'][0]['area_index'] ) ? absint( $_POST['images_meta'][0]['area_index'] ) : 0;
						$variation_id = isset( $_POST['images_meta'][0]['variation_id'] ) ? absint( $_POST['images_meta'][0]['variation_id'] ) : 0;
						
						// CRÍTICO: SIEMPRE asociar por variación (area_index + variation_id)
						if ( $variation_id > 0 ) {
							// Tenemos variation_id: asociar directamente
							$storage_key = "area-{$area_index}-var-{$variation_id}";
							$uploaded_images[ $storage_key ] = $uploaded;
						} else {
							// No hay variation_id: guardar temporalmente para copiar después
							$temp_key = "area-{$area_index}-temp";
							$uploaded_images[ $temp_key ] = $uploaded;
						}
					}
				}
			}
		}

		// NOTA: Las imágenes ahora se asocian directamente a cada variación cuando se añaden al carrito
		// Esta sección ya no es necesaria porque la asociación se hace por variación más abajo
		// Mantenemos solo el log de imágenes subidas
		if ( class_exists( 'WPDM_Logger' ) ) {
			WPDM_Logger::info( 'ajax_add_customized_to_cart', '📊 RESUMEN DE IMÁGENES SUBIDAS', array(
				'images_uploaded' => count( $uploaded_images ),
				'storage_keys' => array_keys( $uploaded_images ),
				'total_areas' => count( $customization_data['areas'] )
			) );
		}

		// CRÍTICO: En modo "global", calcular precio UNA VEZ para todas las variaciones
		$total_quantity_all_variations = 0;
		foreach ( $variations as $variation_data ) {
			$total_quantity_all_variations += absint( $variation_data['quantity'] );
		}
		
		// Calcular precio de personalización UNA VEZ (modo global) o por variación (modo per-color)
		$global_customization_price = 0;
		$global_price_result = null;
		$global_areas_detail = array();
		
		if ( $mode === 'global' && $total_quantity_all_variations > 0 ) {
			// Modo global: calcular precio UNA VEZ para todas las variaciones juntas
			$price_data = array(
				'mode' => $mode,
				'areas' => $customization_data['areas']
			);
			$global_price_result = self::calculate_total_customization_price( $price_data, $total_quantity_all_variations );
			$global_customization_price = $global_price_result['total'];
			$global_areas_detail = isset( $global_price_result['areas'] ) ? $global_price_result['areas'] : array();
			
			WPDM_Logger::info( 'ajax_add_customized_to_cart', 'Precio global calculado (modo global)', array(
				'total_quantity_all_variations' => $total_quantity_all_variations,
				'global_customization_price' => $global_customization_price,
				'variations_count' => count( $variations )
			) );
		}

		// CRÍTICO: Copiar imágenes temporales (sin variation_id) a todas las variaciones
		// Esto asegura que cada variación tenga su propia copia de las imágenes
		// IMPORTANTE: Hacer una copia del array antes de iterar para evitar problemas al modificar durante la iteración
		$temp_images_to_copy = array();
		foreach ( $uploaded_images as $storage_key => $image_data ) {
			if ( strpos( $storage_key, '-temp' ) !== false ) {
				// Es una imagen temporal: extraer area_index
				if ( preg_match( '/^area-(\d+)-temp$/', $storage_key, $matches ) ) {
					$temp_area_index = absint( $matches[1] );
					$temp_images_to_copy[ $storage_key ] = array(
						'area_index' => $temp_area_index,
						'image_data' => $image_data
					);
				}
			}
		}
		
		// Ahora copiar las imágenes temporales a todas las variaciones
		WPDM_Logger::info( 'ajax_add_customized_to_cart', '🔄 COPIANDO IMÁGENES TEMPORALES A VARIACIONES', array(
			'temp_images_encontradas' => count( $temp_images_to_copy ),
			'total_variaciones' => count( $variations ),
			'temp_images_detalle' => array_map( function( $temp ) {
				return array(
					'area_index' => $temp['area_index'],
					'filename' => $temp['image_data']['filename'] ?? 'N/A',
					'url' => $temp['image_data']['url'] ?? 'N/A'
				);
			}, $temp_images_to_copy )
		) );
		
		foreach ( $temp_images_to_copy as $temp_key => $temp_data ) {
			$temp_area_index = $temp_data['area_index'];
			$image_data = $temp_data['image_data'];
			
			WPDM_Logger::debug( 'ajax_add_customized_to_cart', 'Copiando imagen temporal', array(
				'temp_key' => $temp_key,
				'area_index' => $temp_area_index,
				'filename' => $image_data['filename'] ?? 'N/A',
				'url' => $image_data['url'] ?? 'N/A'
			) );
			
			// Copiar a todas las variaciones
			$copied_count = 0;
			foreach ( $variations as $variation_data ) {
				$variation_id = absint( $variation_data['variation_id'] );
				if ( $variation_id > 0 ) {
					$new_storage_key = "area-{$temp_area_index}-var-{$variation_id}";
					// Solo copiar si no existe ya
					if ( ! isset( $uploaded_images[ $new_storage_key ] ) ) {
						$uploaded_images[ $new_storage_key ] = $image_data;
						$copied_count++;
						
						WPDM_Logger::info( 'ajax_add_customized_to_cart', '✅ Imagen temporal copiada a variación', array(
							'temp_key' => $temp_key,
							'new_storage_key' => $new_storage_key,
							'variation_id' => $variation_id,
							'area_index' => $temp_area_index,
							'filename' => $image_data['filename'] ?? 'N/A',
							'url' => $image_data['url'] ?? 'N/A'
						) );
					} else {
						WPDM_Logger::debug( 'ajax_add_customized_to_cart', 'Imagen ya existe para esta variación, saltando', array(
							'new_storage_key' => $new_storage_key,
							'variation_id' => $variation_id
						) );
					}
				}
			}
			
			WPDM_Logger::info( 'ajax_add_customized_to_cart', 'Imagen temporal procesada', array(
				'temp_key' => $temp_key,
				'area_index' => $temp_area_index,
				'copied_to_variations' => $copied_count
			) );
			
			// Eliminar la imagen temporal
			unset( $uploaded_images[ $temp_key ] );
		}
		
		WPDM_Logger::info( 'ajax_add_customized_to_cart', '✅ IMÁGENES TEMPORALES PROCESADAS', array(
			'temp_images_found' => count( $temp_images_to_copy ),
			'total_images_after_copy' => count( $uploaded_images ),
			'storage_keys_finales' => array_keys( $uploaded_images )
		) );
		
		// Añadir cada variación al carrito
		$added_items = array();
		$total_customization_price = 0;

		foreach ( $variations as $variation_data ) {
			$variation_id = absint( $variation_data['variation_id'] );
			$quantity = absint( $variation_data['quantity'] );

			if ( $variation_id <= 0 || $quantity <= 0 ) {
				continue;
			}

			// Validar variación
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || $variation->get_parent_id() != $product_id ) {
				continue;
			}

			// Filtrar áreas de personalización para esta variación y asociar imágenes
			$variation_areas = array();
			if ( $mode === 'per-color' ) {
				// Solo áreas específicas de esta variación
				foreach ( $customization_data['areas'] as $area ) {
					if ( isset( $area['variation_id'] ) && absint( $area['variation_id'] ) === $variation_id ) {
						$variation_areas[] = $area;
					}
				}
			} else {
				// Modo global: todas las áreas, pero asociar imágenes por variación
				foreach ( $customization_data['areas'] as $area ) {
					$area_copy = $area;
					// Asegurar que el variation_id esté en el área
					$area_copy['variation_id'] = $variation_id;
					$variation_areas[] = $area_copy;
				}
			}
			
			// CRÍTICO: Asociar imágenes a las áreas de esta variación
			if ( class_exists( 'WPDM_Logger' ) ) {
				WPDM_Logger::info( 'ajax_add_customized_to_cart', '🔍 ASOCIANDO IMÁGENES A VARIACIÓN', array(
					'variation_id' => $variation_id,
					'total_areas' => count( $variation_areas ),
					'total_images_available' => count( $uploaded_images ),
					'storage_keys_available' => array_keys( $uploaded_images )
				) );
			}
			
			foreach ( $variation_areas as &$area ) {
				$area_index = isset( $area['area_index'] ) ? absint( $area['area_index'] ) : 0;
				$area_position = $area['area_position'] ?? 'N/A';
				
				if ( class_exists( 'WPDM_Logger' ) ) {
					WPDM_Logger::debug( 'ajax_add_customized_to_cart', 'Procesando área para asociar imagen', array(
						'variation_id' => $variation_id,
						'area_index' => $area_index,
						'area_position' => $area_position,
						'area_has_image_url' => isset( $area['image_url'] ),
						'area_current_image_url' => $area['image_url'] ?? 'NO EXISTE'
					) );
				}
				
				// CRÍTICO: area_index puede ser 0 (primera área), así que verificamos que sea numérico y >= 0
				if ( is_numeric( $area_index ) && $area_index >= 0 ) {
					$storage_key = "area-{$area_index}-var-{$variation_id}";
					
					if ( class_exists( 'WPDM_Logger' ) ) {
						WPDM_Logger::debug( 'ajax_add_customized_to_cart', 'Buscando imagen con storage_key', array(
							'storage_key' => $storage_key,
							'exists_in_uploaded_images' => isset( $uploaded_images[ $storage_key ] ),
							'image_data' => isset( $uploaded_images[ $storage_key ] ) ? $uploaded_images[ $storage_key ] : 'NO ENCONTRADA',
							'todas_las_claves' => array_keys( $uploaded_images )
						) );
					}
					
					if ( isset( $uploaded_images[ $storage_key ] ) ) {
						$area['image_url'] = $uploaded_images[ $storage_key ]['url'];
						$area['image_filename'] = $uploaded_images[ $storage_key ]['filename'];
						
						if ( class_exists( 'WPDM_Logger' ) ) {
							WPDM_Logger::info( 'ajax_add_customized_to_cart', '✅ IMAGEN ASOCIADA EXITOSAMENTE', array(
								'variation_id' => $variation_id,
								'area_index' => $area_index,
								'area_position' => $area_position,
								'storage_key' => $storage_key,
								'image_url' => $area['image_url'],
								'image_filename' => $area['image_filename']
							) );
						}
					} else {
						// Intentar también con area_index sin el guion si no se encontró
						// Esto puede ayudar si hay algún problema con el formato del storage_key
						$storage_key_alt = "area-{$area_index}-var-{$variation_id}";
						
						if ( class_exists( 'WPDM_Logger' ) ) {
							WPDM_Logger::warning( 'ajax_add_customized_to_cart', '❌ NO SE ENCONTRÓ IMAGEN PARA ÁREA', array(
								'variation_id' => $variation_id,
								'area_index' => $area_index,
								'area_position' => $area_position,
								'storage_key_buscado' => $storage_key,
								'todas_las_claves_disponibles' => array_keys( $uploaded_images ),
								'area_data_keys' => array_keys( $area )
							) );
						}
					}
				} else {
					if ( class_exists( 'WPDM_Logger' ) ) {
						WPDM_Logger::warning( 'ajax_add_customized_to_cart', '⚠️ area_index inválido en área', array(
							'variation_id' => $variation_id,
							'area_index' => $area_index,
							'area_index_type' => gettype( $area_index ),
							'area_position' => $area_position,
							'area_data' => $area
						) );
					}
				}
			}
			unset( $area );
			
			// Log final de áreas con imágenes
			$areas_with_images = array();
			$areas_without_images = array();
			foreach ( $variation_areas as $area ) {
				if ( isset( $area['image_url'] ) && ! empty( $area['image_url'] ) ) {
					$areas_with_images[] = array(
						'area_index' => $area['area_index'] ?? 'N/A',
						'area_position' => $area['area_position'] ?? 'N/A',
						'image_url' => $area['image_url']
					);
				} else {
					$areas_without_images[] = array(
						'area_index' => $area['area_index'] ?? 'N/A',
						'area_position' => $area['area_position'] ?? 'N/A'
					);
				}
			}
			
			WPDM_Logger::info( 'ajax_add_customized_to_cart', '📊 RESUMEN DE IMÁGENES POR VARIACIÓN', array(
				'variation_id' => $variation_id,
				'total_areas' => count( $variation_areas ),
				'areas_con_imagen' => count( $areas_with_images ),
				'areas_sin_imagen' => count( $areas_without_images ),
				'areas_con_imagen_detalle' => $areas_with_images,
				'areas_sin_imagen_detalle' => $areas_without_images
			) );

			if ( empty( $variation_areas ) ) {
				continue;
			}

			// Calcular precio de personalización
			if ( $mode === 'global' && $global_price_result !== null ) {
				// Usar precio global calculado anteriormente
				$customization_price = $global_customization_price;
				$price_result = $global_price_result;
				$areas_detail = $global_areas_detail;
			} else {
				// Modo per-color: calcular precio por variación
				$price_data = array(
					'mode' => $mode,
					'areas' => $variation_areas
				);
				$price_result = self::calculate_total_customization_price( $price_data, $quantity );
				$customization_price = $price_result['total'];
				$areas_detail = isset( $price_result['areas'] ) ? $price_result['areas'] : array();
			}
			
			if ( class_exists( 'WPDM_Logger' ) ) {
				WPDM_Logger::debug( 'ajax_add_customized_to_cart', 'Precio para variación', array(
					'variation_id' => $variation_id,
					'quantity' => $quantity,
					'mode' => $mode,
					'customization_price' => $customization_price,
					'is_global_price' => ( $mode === 'global' && $global_price_result !== null )
				) );
			}
			
			// Log final de áreas antes de guardar en carrito
			$final_areas_log = array();
			foreach ( $variation_areas as $area ) {
				$final_areas_log[] = array(
					'area_index' => $area['area_index'] ?? 'N/A',
					'area_position' => $area['area_position'] ?? 'N/A',
					'has_image_url' => isset( $area['image_url'] ) && ! empty( $area['image_url'] ),
					'image_url' => $area['image_url'] ?? 'NO EXISTE',
					'image_filename' => $area['image_filename'] ?? 'NO EXISTE',
					'variation_id_en_area' => $area['variation_id'] ?? 'NO EXISTE'
				);
			}
			
			if ( class_exists( 'WPDM_Logger' ) ) {
				WPDM_Logger::info( 'ajax_add_customized_to_cart', '💾 GUARDANDO VARIACIÓN EN CARRITO', array(
					'variation_id' => $variation_id,
					'quantity' => $quantity,
					'total_areas' => count( $variation_areas ),
					'areas_con_imagen' => count( array_filter( $final_areas_log, function( $a ) { return $a['has_image_url']; } ) ),
					'areas_sin_imagen' => count( array_filter( $final_areas_log, function( $a ) { return ! $a['has_image_url']; } ) ),
					'areas_detalle' => $final_areas_log
				) );
			}

			// Preparar datos para el carrito
			$cart_item_data = array(
				'wpdm_customization' => array(
					'mode' => $mode,
					'areas' => $variation_areas,
					'price_breakdown' => $areas_detail,
					'base_price' => $price_result['base_price'] ?? 0,
					'customization_price' => $customization_price,
					'grand_total' => $price_result['grand_total'] ?? ( $customization_price + ( $price_result['base_price'] ?? 0 ) )
				),
				'wpdm_customization_price' => $customization_price,
				'wpdm_variation_info' => array(
					'color' => $variation_data['color'] ?? '',
					'size' => $variation_data['size'] ?? '',
					'full_name' => $variation_data['full_name'] ?? ''
				),
				'_wpdm_cart_item_key' => md5( json_encode( array( $variation_id, $variation_areas ) ) . time() )
			);

			// Añadir al carrito
			$cart_item_key = WC()->cart->add_to_cart( 
				$product_id, 
				$quantity, 
				$variation_id, 
				array(), 
				$cart_item_data 
			);

			if ( $cart_item_key ) {
				$added_items[] = $cart_item_key;
				// CRÍTICO: En modo global, NO sumar el precio (ya está calculado una vez)
				if ( $mode === 'global' ) {
					// Solo sumar una vez (la primera variación)
					if ( $total_customization_price == 0 ) {
						$total_customization_price = $customization_price;
					}
				} else {
					// Modo per-color: sumar cada precio
					$total_customization_price += $customization_price;
				}
				
				WPDM_Logger::debug( 'ajax_add_customized_to_cart', 'Variación añadida al carrito', array(
					'variation_id' => $variation_id,
					'quantity' => $quantity,
					'cart_item_key' => $cart_item_key,
					'customization_price' => $customization_price,
					'total_customization_price_accumulated' => $total_customization_price
				) );
			} else {
				WPDM_Logger::warning( 'ajax_add_customized_to_cart', 'No se pudo añadir variación al carrito', array(
					'variation_id' => $variation_id,
					'quantity' => $quantity
				) );
			}
		}

		if ( empty( $added_items ) ) {
			wp_send_json_error( array( 'message' => __( 'Error al añadir al carrito.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Guardar carrito en sesión
		WC()->cart->set_session();

		// Preparar respuesta con fragmentos
		$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

			WPDM_Logger::info( 'ajax_add_customized_to_cart', 'Productos añadidos exitosamente', array(
				'items_added' => count( $added_items ),
				'total_customization_price' => $total_customization_price
			) );
			
			wp_send_json_success( array(
				'message' => __( 'Producto personalizado añadido al carrito correctamente.', 'woo-prices-dynamics-makito' ),
				'cart_hash' => WC()->cart->get_cart_hash(),
				'fragments' => $fragments,
				'items_added' => count( $added_items ),
				'total_customization_price' => $total_customization_price
			) );
			
		} catch ( Exception $e ) {
			WPDM_Logger::error( 'ajax_add_customized_to_cart', 'Error al procesar solicitud', array(
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'trace' => $e->getTraceAsString()
			) );
			
			wp_send_json_error( array( 
				'message' => __( 'Error al procesar: ', 'woo-prices-dynamics-makito' ) . $e->getMessage()
			) );
		}
	}

	/**
	 * Subir una imagen individual de personalización
	 */
	private static function upload_single_customization_image( $file ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		WPDM_Logger::debug( 'upload_single_customization_image', 'Iniciando subida de archivo', array(
			'filename' => $file['name'],
			'size' => $file['size'],
			'type' => $file['type']
		) );

		// Validar tipo de archivo
		$allowed_types = array( 'jpg', 'jpeg', 'png', 'pdf', 'eps', 'ai', 'cdr' );
		$file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		
		if ( ! in_array( $file_ext, $allowed_types ) ) {
			WPDM_Logger::warning( 'upload_single_customization_image', 'Tipo de archivo no permitido', array(
				'filename' => $file['name'],
				'extension' => $file_ext
			) );
			return new WP_Error( 'invalid_type', 'Tipo de archivo no permitido' );
		}

		// Validar tamaño (5MB máximo)
		if ( $file['size'] > 5 * 1024 * 1024 ) {
			WPDM_Logger::warning( 'upload_single_customization_image', 'Archivo demasiado grande', array(
				'filename' => $file['name'],
				'size' => $file['size'],
				'max_size' => 5 * 1024 * 1024
			) );
			return new WP_Error( 'file_too_large', 'El archivo es demasiado grande' );
		}

		// Configurar upload
		$upload_overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'  => 'image/png',
				'pdf'  => 'application/pdf',
				'eps'  => 'application/postscript',
				'ai'   => 'application/illustrator',
				'cdr'  => 'application/coreldraw'
			)
		);

		// Cambiar directorio de subida
		add_filter( 'upload_dir', array( __CLASS__, 'custom_upload_dir' ) );
		
		$movefile = wp_handle_upload( $file, $upload_overrides );
		
		remove_filter( 'upload_dir', array( __CLASS__, 'custom_upload_dir' ) );

		if ( $movefile && ! isset( $movefile['error'] ) ) {
			WPDM_Logger::info( 'upload_single_customization_image', 'Archivo subido exitosamente', array(
				'filename' => basename( $movefile['file'] ),
				'url' => $movefile['url']
			) );
			
			return array(
				'file' => $movefile['file'],
				'url'  => $movefile['url'],
				'type' => $movefile['type'],
				'filename' => basename( $movefile['file'] )
			);
		}

		WPDM_Logger::error( 'upload_single_customization_image', 'Error al subir archivo', array(
			'filename' => $file['name'],
			'error' => isset( $movefile['error'] ) ? $movefile['error'] : 'Unknown error'
		) );
		
		return new WP_Error( 'upload_failed', 'Error al subir archivo' );
	}

	/**
	 * Subir imágenes de personalización
	 */
	private static function upload_customization_images( $files ) {
		$uploaded = array();

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		// Crear directorio si no existe
		$upload_dir = wp_upload_dir();
		$custom_dir = $upload_dir['basedir'] . '/wpdm-customization';
		
		if ( ! file_exists( $custom_dir ) ) {
			wp_mkdir_p( $custom_dir );
		}

		foreach ( $files['name'] as $key => $filename ) {
			if ( empty( $filename ) ) {
				continue;
			}

			// Preparar archivo para upload
			$file = array(
				'name'     => $files['name'][ $key ],
				'type'     => $files['type'][ $key ],
				'tmp_name' => $files['tmp_name'][ $key ],
				'error'    => $files['error'][ $key ],
				'size'     => $files['size'][ $key ]
			);

			// Validar tipo de archivo
			$allowed_types = array( 'jpg', 'jpeg', 'png', 'pdf', 'eps', 'ai', 'cdr' );
			$file_ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			
			if ( ! in_array( $file_ext, $allowed_types ) ) {
				continue;
			}

			// Validar tamaño (5MB máximo)
			if ( $file['size'] > 5 * 1024 * 1024 ) {
				continue;
			}

			// Subir archivo
			$upload_overrides = array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'  => 'image/png',
					'pdf'  => 'application/pdf',
					'eps'  => 'application/postscript',
					'ai'   => 'application/illustrator',
					'cdr'  => 'application/coreldraw'
				)
			);

			// Cambiar directorio de subida
			add_filter( 'upload_dir', array( __CLASS__, 'custom_upload_dir' ) );
			
			$movefile = wp_handle_upload( $file, $upload_overrides );
			
			remove_filter( 'upload_dir', array( __CLASS__, 'custom_upload_dir' ) );

			if ( $movefile && ! isset( $movefile['error'] ) ) {
				$uploaded[ $key ] = array(
					'file' => $movefile['file'],
					'url'  => $movefile['url'],
					'type' => $movefile['type']
				);
			}
		}

		return $uploaded;
	}

	/**
	 * Filtro para cambiar directorio de subida
	 */
	public static function custom_upload_dir( $dir ) {
		return array(
			'path'   => $dir['basedir'] . '/wpdm-customization',
			'url'    => $dir['baseurl'] . '/wpdm-customization',
			'subdir' => '/wpdm-customization',
		) + $dir;
	}

	/**
	 * Mostrar personalización en el carrito con botón "Ver detalles"
	 * En modo "global", solo mostrar personalización en la primera variación del grupo
	 */
	public static function display_customization_in_cart( $item_data, $cart_item ) {
		if ( empty( $cart_item['wpdm_customization'] ) ) {
			return $item_data;
		}

		$customization = $cart_item['wpdm_customization'];
		$mode = isset( $customization['mode'] ) ? $customization['mode'] : 'global';
		$product_id = $cart_item['product_id'];
		$customization_price = isset( $cart_item['wpdm_customization_price'] ) ? floatval( $cart_item['wpdm_customization_price'] ) : 0;
		
		// En modo "global", verificar si es la primera variación del grupo
		if ( $mode === 'global' && WC()->cart ) {
			$is_first_in_group = self::is_first_customized_item_in_group( $cart_item, $product_id, $mode );
			
			if ( ! $is_first_in_group ) {
				// No mostrar personalización en las demás variaciones del grupo
				return $item_data;
			}
		}
		
		$unique_id = 'wpdm-details-' . md5( serialize( $cart_item ) );
		
		WPDM_Logger::debug( 'display_customization_in_cart', 'Mostrando personalización en carrito', array(
			'customization_price' => $customization_price,
			'areas_count' => isset( $customization['areas'] ) ? count( $customization['areas'] ) : 0,
			'has_price_breakdown' => ! empty( $customization['price_breakdown'] ),
			'mode' => $mode,
			'is_first_in_group' => isset( $is_first_in_group ) ? $is_first_in_group : true
		) );

		// Línea 1: Indicador + Total de personalización
		$value_html = '<div class="wpdm-personalization-info">';
		$value_html .= '<span style="color: #0464AC; font-weight: 600;">✓ Sí</span>';
		
		if ( $customization_price > 0 ) {
			$value_html .= ' <span style="color: #666;">|</span> ';
			$value_html .= '<strong style="color: #0464AC; font-size: 1.05em;">' . wc_price( $customization_price ) . '</strong>';
		}
		
		$value_html .= '<br><button type="button" class="wpdm-toggle-details-btn" data-target="' . esc_attr( $unique_id ) . '" style="background: #0464AC; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 0.85em; margin-top: 5px;">Ver detalles ▼</button>';
		$value_html .= '</div>';
		
		$item_data[] = array(
			'key'     => __( 'Personalización', 'woo-prices-dynamics-makito' ),
			'value'   => $value_html,
			'display' => ''
		);
		
		// Detalles (ocultos por defecto) - en línea separada
		$item_data[] = array(
			'key'     => '',
			'value'   => '<div id="' . esc_attr( $unique_id ) . '" class="wpdm-customization-details-content" style="display: none; margin-top: 10px; padding: 15px; background: #f9f9f9; border-left: 3px solid #0464AC; border-radius: 4px;">' .
						 self::render_customization_details( $customization ) .
						 '</div>',
			'display' => ''
		);

		// Añadir script global
		add_action( 'wp_footer', array( __CLASS__, 'enqueue_cart_toggle_script' ), 999 );

		return $item_data;
	}
	
	/**
	 * Cache estático para rastrear qué items ya mostraron personalización
	 */
	private static $customization_displayed_for_groups = array();
	
	/**
	 * Verificar si es el primer item personalizado del grupo (modo global)
	 */
	private static function is_first_customized_item_in_group( $current_item, $product_id, $mode ) {
		if ( ! WC()->cart || $mode !== 'global' ) {
			return true;
		}
		
		// Crear clave única para el grupo (producto + modo)
		$group_key = $product_id . '_' . $mode;
		
		// Si ya mostramos personalización para este grupo, no mostrar de nuevo
		if ( isset( self::$customization_displayed_for_groups[ $group_key ] ) ) {
			return false;
		}
		
		// Verificar que realmente sea el primer item del grupo en el carrito
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item['wpdm_customization'] ) ) {
				$item_customization = $cart_item['wpdm_customization'];
				$item_mode = isset( $item_customization['mode'] ) ? $item_customization['mode'] : 'global';
				$item_product_id = $cart_item['product_id'];
				
				// Si es del mismo producto y modo global
				if ( $item_product_id == $product_id && $item_mode === 'global' ) {
					// Comparar si es el mismo item (por variation_id y quantity)
					$current_variation_id = isset( $current_item['variation_id'] ) ? $current_item['variation_id'] : 0;
					$current_quantity = isset( $current_item['quantity'] ) ? $current_item['quantity'] : 0;
					$item_variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
					$item_quantity = isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 0;
					
					// Si encontramos el item actual, es el primero
					if ( $current_variation_id == $item_variation_id && 
						 $current_quantity == $item_quantity &&
						 isset( $current_item['wpdm_customization'] ) ) {
						// Marcar que ya mostramos personalización para este grupo
						self::$customization_displayed_for_groups[ $group_key ] = true;
						return true;
					}
					// Si encontramos otro item antes, este no es el primero
					return false;
				}
			}
		}
		
		// Por defecto, mostrar si no encontramos otros
		self::$customization_displayed_for_groups[ $group_key ] = true;
		return true;
	}
	
	/**
	 * Añadir clases CSS a items del carrito para agrupar visualmente
	 */
	public static function add_cart_item_class( $classes, $cart_item, $cart_item_key ) {
		if ( ! empty( $cart_item['wpdm_customization'] ) ) {
			$customization = $cart_item['wpdm_customization'];
			$mode = isset( $customization['mode'] ) ? $customization['mode'] : 'global';
			$product_id = $cart_item['product_id'];
			
			$classes .= ' wpdm-customized-item';
			$classes .= ' wpdm-customized-' . esc_attr( $mode );
			$classes .= ' wpdm-product-group-' . esc_attr( $product_id );
			
			// En modo global, añadir clase para agrupar
			if ( $mode === 'global' && WC()->cart ) {
				$is_first = self::is_first_customized_item_in_group( $cart_item, $product_id, $mode );
				if ( $is_first ) {
					$classes .= ' wpdm-customized-group-first';
				} else {
					$classes .= ' wpdm-customized-group-item';
				}
			}
		}
		
		return $classes;
	}
	
	/**
	 * Añadir personalización al nombre del producto en el carrito (para Elementor y otros templates)
	 */
	public static function add_customization_to_cart_item_name( $product_name, $cart_item, $cart_item_key ) {
		if ( empty( $cart_item['wpdm_customization'] ) ) {
			return $product_name;
		}

		$customization = $cart_item['wpdm_customization'];
		$mode = isset( $customization['mode'] ) ? $customization['mode'] : 'global';
		$product_id = $cart_item['product_id'];
		$customization_price = isset( $cart_item['wpdm_customization_price'] ) ? floatval( $cart_item['wpdm_customization_price'] ) : 0;
		
		// En modo "global", solo mostrar personalización en la primera variación del grupo
		if ( $mode === 'global' && WC()->cart ) {
			$is_first_in_group = self::is_first_customized_item_in_group( $cart_item, $product_id, $mode );
			
			if ( ! $is_first_in_group ) {
				// No mostrar personalización en las demás variaciones del grupo
				return $product_name;
			}
		}
		
		$unique_id = 'wpdm-details-' . md5( $cart_item_key );
		
		// Añadir personalización después del nombre del producto
		$customization_html = '<div class="wpdm-cart-customization-info" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e0e0e0;">';
		$customization_html .= '<div class="wpdm-personalization-info">';
		$customization_html .= '<span style="color: #0464AC; font-weight: 600;">✓ Sí</span>';
		
		if ( $customization_price > 0 ) {
			$customization_html .= ' <span style="color: #666;">|</span> ';
			$customization_html .= '<strong style="color: #0464AC; font-size: 1.05em;">' . wc_price( $customization_price ) . '</strong>';
		}
		
		$customization_html .= '<br><button type="button" class="wpdm-toggle-details-btn" data-target="' . esc_attr( $unique_id ) . '" style="background: #0464AC; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 0.85em; margin-top: 5px;">Ver detalles ▼</button>';
		$customization_html .= '</div>';
		
		// Detalles (ocultos por defecto)
		$customization_html .= '<div id="' . esc_attr( $unique_id ) . '" class="wpdm-customization-details-content" style="display: none; margin-top: 10px; padding: 15px; background: #f9f9f9; border-left: 3px solid #0464AC; border-radius: 4px;">';
		$customization_html .= self::render_customization_details( $customization );
		$customization_html .= '</div>';
		$customization_html .= '</div>';
		
		// Añadir script global
		add_action( 'wp_footer', array( __CLASS__, 'enqueue_cart_toggle_script' ), 999 );
		
		return $product_name . $customization_html;
	}

	/**
	 * Renderizar detalles de personalización para carrito
	 */
	private static function render_customization_details( $customization ) {
		$html = '';
		
		// Validar que customization sea un array
		if ( ! is_array( $customization ) ) {
			if ( class_exists( 'WPDM_Logger' ) ) {
				WPDM_Logger::error( 'render_customization_details', '❌ ERROR: customization no es un array', array(
					'customization_type' => gettype( $customization ),
					'customization_value' => $customization
				) );
			}
			return '<p style="color: #d63638;">Error: Datos de personalización inválidos</p>';
		}
		
		if ( class_exists( 'WPDM_Logger' ) ) {
			WPDM_Logger::info( 'render_customization_details', '🎨 INICIANDO RENDERIZADO DE DETALLES', array(
				'customization_keys' => is_array( $customization ) ? array_keys( $customization ) : array(),
				'has_areas' => ! empty( $customization['areas'] ) && is_array( $customization['areas'] ),
				'total_areas' => ! empty( $customization['areas'] ) && is_array( $customization['areas'] ) ? count( $customization['areas'] ) : 0
			) );
		}

		if ( ! empty( $customization['areas'] ) && is_array( $customization['areas'] ) ) {
			foreach ( $customization['areas'] as $area_index => $area ) {
				// Validar que area sea un array
				if ( ! is_array( $area ) ) {
					if ( class_exists( 'WPDM_Logger' ) ) {
						WPDM_Logger::warning( 'render_customization_details', '⚠️ Área no es un array, saltando', array(
							'area_index' => $area_index,
							'area_type' => gettype( $area )
						) );
					}
					continue;
				}
				
				$area_position = isset( $area['area_position'] ) ? $area['area_position'] : 'Área ' . ( is_numeric( $area_index ) ? ( $area_index + 1 ) : '' );
				$has_image_url = isset( $area['image_url'] ) && ! empty( $area['image_url'] );
				$image_url = isset( $area['image_url'] ) ? $area['image_url'] : 'NO EXISTE';
				
				if ( class_exists( 'WPDM_Logger' ) ) {
					WPDM_Logger::debug( 'render_customization_details', 'Procesando área para renderizar', array(
						'area_index' => $area_index,
						'area_position' => $area_position,
						'has_image_url' => $has_image_url,
						'image_url' => $image_url,
						'image_filename' => isset( $area['image_filename'] ) ? $area['image_filename'] : 'NO EXISTE',
						'area_keys' => is_array( $area ) ? array_keys( $area ) : array()
					) );
				}
				
				$html .= '<div style="margin-bottom: 12px; padding: 10px; background: #fff; border-radius: 4px; border-left: 3px solid #0464AC;">';
				
				// Nombre del área
				$html .= '<div style="font-weight: 600; color: #0464AC; margin-bottom: 8px;">📐 ' . esc_html( $area_position ) . '</div>';
				
				$html .= '<table style="width: 100%; font-size: 0.85em;">';
				$html .= '<tbody>';
				
				// Técnica
				if ( ! empty( $area['technique_name'] ) ) {
					$html .= '<tr><td style="color: #666; padding: 3px 0; width: 35%;">Técnica:</td><td style="color: #333; padding: 3px 0;"><strong>' . esc_html( trim( $area['technique_name'] ) ) . '</strong></td></tr>';
				}
				
				// Número de colores
				if ( isset( $area['colors_selected'] ) && $area['colors_selected'] > 0 ) {
					$html .= '<tr><td style="color: #666; padding: 3px 0;">Colores:</td><td style="color: #333; padding: 3px 0;"><strong>' . intval( $area['colors_selected'] ) . '</strong></td></tr>';
				}
				
				// Colores PANTONE
				if ( ! empty( $area['pantones'] ) ) {
					$pantone_names = array_column( $area['pantones'], 'value' );
					$html .= '<tr><td style="color: #666; padding: 3px 0;">🎨 PANTONE:</td><td style="color: #333; padding: 3px 0;"><strong>' . esc_html( implode( ', ', $pantone_names ) ) . '</strong></td></tr>';
				}
				
				// Imagen
				if ( $has_image_url ) {
					$html .= '<tr><td style="color: #666; padding: 3px 0;">📸 Imagen:</td><td style="color: #333; padding: 3px 0;"><a href="' . esc_url( $image_url ) . '" target="_blank" style="color: #0464AC; text-decoration: none;">Ver archivo →</a></td></tr>';
					
					if ( class_exists( 'WPDM_Logger' ) ) {
						WPDM_Logger::info( 'render_customization_details', '✅ IMAGEN AÑADIDA AL HTML', array(
							'area_index' => $area_index,
							'area_position' => $area_position,
							'image_url' => $image_url
						) );
					}
				} else {
					if ( class_exists( 'WPDM_Logger' ) ) {
						WPDM_Logger::warning( 'render_customization_details', '❌ NO HAY IMAGEN PARA ÁREA', array(
							'area_index' => $area_index,
							'area_position' => $area_position,
							'area_data_completo' => $area
						) );
					}
				}
				
				// Observaciones
				if ( ! empty( $area['observations'] ) ) {
					$html .= '<tr><td style="color: #666; padding: 3px 0; vertical-align: top;">📝 Observaciones:</td><td style="color: #666; padding: 3px 0;"><em>' . esc_html( $area['observations'] ) . '</em></td></tr>';
				}
				
				$html .= '</tbody></table>';
				$html .= '</div>';
			}
		} else {
			if ( class_exists( 'WPDM_Logger' ) ) {
				WPDM_Logger::warning( 'render_customization_details', '⚠️ NO HAY ÁREAS EN CUSTOMIZATION', array(
					'customization' => $customization
				) );
			}
		}
		
		if ( class_exists( 'WPDM_Logger' ) ) {
			WPDM_Logger::info( 'render_customization_details', '✅ RENDERIZADO COMPLETADO', array(
				'html_length' => strlen( $html ),
				'html_preview' => substr( $html, 0, 200 ) . '...'
			) );
		}

		return $html;
	}

	/**
	 * AJAX: Obtener detalles de personalización de un item del carrito
	 */
	public static function ajax_get_cart_item_customization() {
		// Verificar que WPDM_Logger existe antes de usarlo
		if ( class_exists( 'WPDM_Logger' ) ) {
			WPDM_Logger::info( 'ajax_get_cart_item_customization', '📥 SOLICITUD DE DETALLES DE PERSONALIZACIÓN', array(
				'cart_item_key' => isset( $_POST['cart_item_key'] ) ? sanitize_text_field( $_POST['cart_item_key'] ) : 'NO PROPORCIONADO'
			) );
		}
		
		if ( ! WC()->cart ) {
			wp_send_json_error( array( 'message' => 'Carrito no disponible' ) );
		}
		
		$cart_item_key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( $_POST['cart_item_key'] ) : '';
		
		if ( empty( $cart_item_key ) ) {
			wp_send_json_error( array( 'message' => 'Cart item key requerido' ) );
		}
		
		$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		
		if ( class_exists( 'WPDM_Logger' ) ) {
			WPDM_Logger::info( 'ajax_get_cart_item_customization', '🔍 CART ITEM RECUPERADO', array(
				'cart_item_key' => $cart_item_key,
				'cart_item_exists' => ! empty( $cart_item ),
				'has_customization' => ! empty( $cart_item ) && isset( $cart_item['wpdm_customization'] ) && ! empty( $cart_item['wpdm_customization'] ),
				'cart_item_keys' => ! empty( $cart_item ) ? array_keys( $cart_item ) : array()
			) );
		}
		
		if ( ! $cart_item || empty( $cart_item['wpdm_customization'] ) ) {
			if ( class_exists( 'WPDM_Logger' ) ) {
				WPDM_Logger::warning( 'ajax_get_cart_item_customization', '❌ CART ITEM NO ENCONTRADO O SIN PERSONALIZACIÓN', array(
					'cart_item_key' => $cart_item_key,
					'cart_item_is_array' => is_array( $cart_item ),
					'cart_item_has_customization_key' => ! empty( $cart_item ) && isset( $cart_item['wpdm_customization'] )
				) );
			}
			wp_send_json_error( array( 'message' => 'Item no encontrado o sin personalización' ) );
		}
		
		$customization = $cart_item['wpdm_customization'];
		
		// Validar que customization sea un array
		if ( ! is_array( $customization ) ) {
			if ( class_exists( 'WPDM_Logger' ) ) {
				WPDM_Logger::error( 'ajax_get_cart_item_customization', '❌ ERROR: customization no es un array', array(
					'cart_item_key' => $cart_item_key,
					'customization_type' => gettype( $customization ),
					'customization_value' => $customization
				) );
			}
			wp_send_json_error( array( 'message' => 'Datos de personalización inválidos' ) );
		}
		
		// Log detallado de las áreas y sus imágenes
		$areas_log = array();
		if ( ! empty( $customization['areas'] ) && is_array( $customization['areas'] ) ) {
			foreach ( $customization['areas'] as $idx => $area ) {
				if ( is_array( $area ) ) {
					$areas_log[] = array(
						'area_index' => isset( $area['area_index'] ) ? $area['area_index'] : 'N/A',
						'area_position' => isset( $area['area_position'] ) ? $area['area_position'] : 'N/A',
						'has_image_url' => isset( $area['image_url'] ) && ! empty( $area['image_url'] ),
						'image_url' => isset( $area['image_url'] ) ? $area['image_url'] : 'NO EXISTE',
						'image_filename' => isset( $area['image_filename'] ) ? $area['image_filename'] : 'NO EXISTE',
						'variation_id' => isset( $area['variation_id'] ) ? $area['variation_id'] : 'NO EXISTE',
						'area_keys' => array_keys( $area )
					);
				}
			}
		}
		
		$variation_id_log = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : ( isset( $cart_item['data'] ) && is_a( $cart_item['data'], 'WC_Product_Variation' ) ? $cart_item['data']->get_id() : 'N/A' );
		
		$variation_id_log = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : ( isset( $cart_item['data'] ) && is_a( $cart_item['data'], 'WC_Product_Variation' ) ? $cart_item['data']->get_id() : 'N/A' );
		
		if ( class_exists( 'WPDM_Logger' ) ) {
			WPDM_Logger::info( 'ajax_get_cart_item_customization', '📋 DATOS DE PERSONALIZACIÓN RECUPERADOS', array(
				'cart_item_key' => $cart_item_key,
				'variation_id' => $variation_id_log,
				'total_areas' => ! empty( $customization['areas'] ) && is_array( $customization['areas'] ) ? count( $customization['areas'] ) : 0,
				'areas_con_imagen' => count( array_filter( $areas_log, function( $a ) { return isset( $a['has_image_url'] ) && $a['has_image_url']; } ) ),
				'areas_sin_imagen' => count( array_filter( $areas_log, function( $a ) { return isset( $a['has_image_url'] ) && ! $a['has_image_url']; } ) ),
				'areas_detalle' => $areas_log
			) );
		}
		
		$details_html = self::render_customization_details( $customization );
		
		if ( class_exists( 'WPDM_Logger' ) ) {
			WPDM_Logger::info( 'ajax_get_cart_item_customization', '✅ DETALLES RENDERIZADOS', array(
				'cart_item_key' => $cart_item_key,
				'html_length' => strlen( $details_html ),
				'html_contains_image' => strpos( $details_html, 'Ver archivo' ) !== false,
				'html_preview' => substr( $details_html, 0, 300 ) . '...'
			) );
		}
		
		wp_send_json_success( array(
			'details' => $details_html,
			'price' => isset( $cart_item['wpdm_customization_price'] ) ? floatval( $cart_item['wpdm_customization_price'] ) : 0,
			'customization' => $customization // Incluir datos de personalización en crudo para comparación
		) );
	}

	/**
	 * Script para toggle de detalles en carrito
	 */
	public static function enqueue_cart_toggle_script() {
		static $script_added = false;
		
		if ( $script_added ) {
			return;
		}
		
		$script_added = true;
		?>
		<script>
		(function($) {
			'use strict';
			
			// Función para inicializar los toggles
			function initWPDMToggles() {
				console.log('[WPDM Cart] Inicializando toggles de personalización');
				
				// Ocultar todos los detalles con fuerza (usando clases CSS)
				$('.wpdm-customization-details-content').each(function() {
					$(this).addClass('wpdm-details-hidden').removeClass('wpdm-details-visible');
				});
				
				// Event listener delegado (funciona con contenido dinámico)
				$(document).off('click.wpdm-toggle-btn').on('click.wpdm-toggle-btn', '.wpdm-toggle-details-btn', function(e) {
					e.preventDefault();
					e.stopPropagation();
					
					var $button = $(this);
					var targetId = $button.data('target');
					
					console.log('[WPDM Cart] Toggle clickeado. Target ID:', targetId);
					console.log('[WPDM Cart] Buscando elemento con ID:', targetId);
					
					var $details = $('#' + targetId);
					
					console.log('[WPDM Cart] Elemento encontrado:', $details.length, 'Visible:', $details.is(':visible'));
					console.log('[WPDM Cart] HTML del elemento:', $details.length > 0 ? $details.html().substring(0, 100) : 'NO ENCONTRADO');
					
					if ($details.length === 0) {
						console.error('[WPDM Cart] ERROR: No se encontró el elemento de detalles con ID:', targetId);
						console.log('[WPDM Cart] Todos los elementos .wpdm-customization-details-content:', $('.wpdm-customization-details-content').length);
						$('.wpdm-customization-details-content').each(function(i) {
							console.log('[WPDM Cart] Elemento', i, 'ID:', $(this).attr('id'), 'Visible:', $(this).is(':visible'));
						});
						alert('Error: No se pudieron cargar los detalles. Por favor, recarga la página.');
						return;
					}
					
					// Usar clases CSS en lugar de estilos inline para mejor control
					var isVisible = $details.hasClass('wpdm-details-visible') || 
									($details.is(':visible') && $details.css('display') !== 'none' && 
									 $details.css('visibility') !== 'hidden' && 
									 $details.css('opacity') !== '0');
					
					if (isVisible) {
						console.log('[WPDM Cart] Ocultando detalles');
						$details.removeClass('wpdm-details-visible').addClass('wpdm-details-hidden');
						$button.html('Ver detalles ▼');
					} else {
						console.log('[WPDM Cart] Mostrando detalles');
						$details.removeClass('wpdm-details-hidden').addClass('wpdm-details-visible');
						// Asegurar que contenedores padres no tengan overflow hidden
						$details.parent().css('overflow', 'visible');
						$details.closest('.wpdm-product-group-wrapper').css('overflow', 'visible');
						$details.closest('.wpdm-group-customization').css('overflow', 'visible');
						// Forzar visibilidad con estilos inline también
						$details.css({
							'display': 'block',
							'visibility': 'visible',
							'opacity': '1',
							'height': 'auto',
							'overflow': 'visible'
						});
						$button.html('Ocultar detalles ▲');
					}
				});
				
				// BLOQUEO AGRESIVO de cantidad para productos personalizados
				$('.wpdm-personalization-info').each(function() {
					var $cartItem = $(this).closest('tr, .cart_item, .woocommerce-cart-form__cart-item');
					var $qtyInput = $cartItem.find('input.qty, input[type="number"][name*="cart"]');
					var $qtyButtons = $cartItem.find('.quantity button, .quantity .plus, .quantity .minus, button.plus, button.minus');
					
					if ($qtyInput.length > 0) {
						var originalQty = $qtyInput.val();
						console.log('[WPDM Cart] Bloqueando cantidad para producto personalizado. Qty:', originalQty);
						
						// Opción 1: Reemplazar por texto fijo
						var $qtyWrapper = $qtyInput.closest('.quantity, .product-quantity');
						if ($qtyWrapper.length > 0) {
							$qtyWrapper.html(
								'<div class="wpdm-qty-fixed" style="text-align: center; padding: 8px 12px; background: #f5f5f5; border-radius: 4px; border: 2px solid #ddd;">' +
								'<div style="font-weight: 600; font-size: 1.1em; color: #333;">' + originalQty + '</div>' +
								'<div style="font-size: 0.7em; color: #999; margin-top: 2px;">🔒 Fijo (personalizado)</div>' +
								'</div>'
							);
						} else {
							// Opción 2: Bloquear input directamente
							$qtyInput.prop('disabled', true).prop('readonly', true);
							$qtyInput.attr('disabled', 'disabled').attr('readonly', 'readonly');
							$qtyInput.css({
								'background': '#f0f0f0 !important',
								'cursor': 'not-allowed !important',
								'pointer-events': 'none !important',
								'opacity': '0.6'
							});
							
							$qtyButtons.remove();
						}
						
						console.log('[WPDM Cart] Cantidad bloqueada exitosamente');
					}
				});
				
				var buttonsFound = $('.wpdm-toggle-details-btn').length;
				var detailsFound = $('.wpdm-customization-details-content').length;
				console.log('[WPDM Cart] Toggles listos. Botones:', buttonsFound, 'Detalles:', detailsFound);
			}
			
			// Reorganizar visualmente los items del carrito (SIEMPRE para productos variables)
			function reorganizeCartItems() {
				console.log('[WPDM Cart] Iniciando reorganización del carrito...');
				
				// Limpiar grupos anteriores
				$('.wpdm-product-group-container').remove();
				// Mostrar todos los items del carrito (no solo personalizados)
				$('tbody tr.cart_item, .cart_item').show();
				
				// Marcar que estamos reorganizando para evitar bucles
				$('.cart_item').data('wpdm-reorganized', false);
				
				// Función para obtener el cart_item_key de un item
				function getCartItemKey($row) {
					var $removeLink = $row.find('a[href*="remove_item"]');
					if ($removeLink.length) {
						var href = $removeLink.attr('href') || '';
						var match = href.match(/remove_item=([^&]+)/);
						if (match) {
							return decodeURIComponent(match[1]);
						}
					}
					return null;
				}
				
				// Función para obtener la firma de personalización basada en el cart_item_key del primer item
				// Esto identifica diferentes "sesiones" de añadir al carrito
				function getCustomizationSignature($row) {
					var signature = '';
					
					// Verificar si tiene personalización y el modo
					var rowClasses = $row.attr('class') || '';
					var hasCustomization = rowClasses.indexOf('wpdm-customized-item') !== -1 || 
					                      rowClasses.indexOf('wpdm-customized-global') !== -1 || 
					                      rowClasses.indexOf('wpdm-customized-per-color') !== -1;
					
					var isGlobalMode = rowClasses.indexOf('wpdm-customized-global') !== -1;
					var isPerColorMode = rowClasses.indexOf('wpdm-customized-per-color') !== -1;
					
					if (hasCustomization) {
						// Para TODOS los modos de personalización, usar el cart_item_key como identificador
						// Esto agrupa todas las variaciones añadidas en la misma "sesión" (misma personalización)
						var cartItemKey = getCartItemKey($row);
						if (cartItemKey) {
							// Usar los primeros 16 caracteres del cart_item_key como firma
							// Items con el mismo prefijo pertenecen a la misma "sesión" de personalización
							if (isGlobalMode) {
								signature = 'global-' + cartItemKey.substring(0, 16);
							} else if (isPerColorMode) {
								signature = 'per-color-' + cartItemKey.substring(0, 16);
							} else {
								signature = 'custom-' + cartItemKey.substring(0, 16);
							}
						} else {
							// Fallback si no se encuentra cart_item_key
							if (isGlobalMode) {
								signature = 'global-default';
							} else if (isPerColorMode) {
								signature = 'per-color-default';
							} else {
								signature = 'custom-default';
							}
						}
					}
					
					return signature || 'no-customization';
				}
				
				// Agrupar items por producto + personalización
				// Para productos personalizados, diferentes personalizaciones = grupos diferentes
				var productGroups = {};
				
				// ESTRATEGIA 1: Buscar TODOS los items del carrito y agruparlos por producto padre + personalización
				var totalItemsFound = 0;
				$('tbody tr.cart_item, .cart_item').each(function() {
					var $row = $(this);
					
					// Omitir si ya está reorganizado o es un contenedor de grupo
					if ($row.hasClass('wpdm-product-group-container') || $row.data('wpdm-reorganized') === true) {
						return;
					}
					
					totalItemsFound++;
					var productId = null;
					
					// PRIORIDAD 1: Buscar data-product_id en el botón de eliminar (funciona para todos los productos)
					var $removeLink = $row.find('a[href*="remove_item"]');
					if ($removeLink.length) {
						var dataProductId = $removeLink.data('product_id');
						if (dataProductId) {
							productId = String(dataProductId); // Convertir a string para consistencia
							console.log('[WPDM Cart] ✅ productId encontrado via data-product_id:', productId);
						}
					}
					
					// PRIORIDAD 2: Buscar la clase wpdm-product-group-{id} (para productos personalizados)
					if (!productId) {
						var allClasses = $row.attr('class') || '';
						var match = allClasses.match(/wpdm-product-group-(\d+)/);
						if (match && match[1]) {
							productId = match[1];
							console.log('[WPDM Cart] ✅ productId encontrado via clase wpdm-product-group:', productId);
						}
					}
					
					// PRIORIDAD 3: Intentar obtener el product_id del link del producto
					if (!productId) {
						var $productLink = $row.find('.product-name a').first();
						if ($productLink.length) {
							var href = $productLink.attr('href') || '';
							// Buscar product_id en la URL (puede ser producto padre o variación)
							var productMatch = href.match(/product[\/=](\d+)/);
							if (productMatch && productMatch[1]) {
								productId = productMatch[1];
								console.log('[WPDM Cart] ✅ productId encontrado via URL del producto:', productId);
							}
						}
					}
					
					if (productId) {
						// Verificar si tiene personalización
						var rowClasses = $row.attr('class') || '';
						var hasCustomization = rowClasses.indexOf('wpdm-customized-item') !== -1 || 
						                      rowClasses.indexOf('wpdm-customized-global') !== -1 || 
						                      rowClasses.indexOf('wpdm-customized-per-color') !== -1;
						
						// ESTRATEGIA SIMPLIFICADA:
						// - Para productos CON personalización: Agrupar SOLO por productId (todas las variaciones juntas)
						// - Para productos SIN personalización: Agrupar SOLO por productId
						// - Solo separar si realmente hay diferentes personalizaciones (esto se detectará después si es necesario)
						
						var groupKey = productId; // Usar solo productId para agrupar
						
						// Buscar si ya existe un grupo para este productId
						if (!productGroups[groupKey]) {
							productGroups[groupKey] = [];
						}
						
						// Evitar duplicados
						var alreadyAdded = false;
						for (var i = 0; i < productGroups[groupKey].length; i++) {
							if (productGroups[groupKey][i][0] === $row[0]) {
								alreadyAdded = true;
								break;
							}
						}
						
						if (!alreadyAdded) {
							productGroups[groupKey].push($row);
							console.log('[WPDM Cart] Item añadido al grupo', groupKey, '- Total items:', productGroups[groupKey].length, '- Tiene personalización:', hasCustomization);
						}
					} else {
						console.warn('[WPDM Cart] ⚠️ No se pudo determinar productId para item:', $row);
						console.warn('[WPDM Cart] Clases del item:', $row.attr('class'));
						console.warn('[WPDM Cart] HTML del item:', $row.html().substring(0, 200));
					}
				});
				
				console.log('[WPDM Cart] 📊 Total items encontrados:', totalItemsFound);
				console.log('[WPDM Cart] 📊 Productos agrupados:', Object.keys(productGroups).length);
				console.log('[WPDM Cart] 📊 Detalle de grupos:', productGroups);
				
				// Separar productos con y sin personalización
				var customizedGroups = {};
				var nonCustomizedGroups = {};
				
				// Función para crear un hash único de los datos de personalización
				function createCustomizationHash(customizationData) {
					if (!customizationData) {
						console.log('[WPDM Cart] ⚠️ createCustomizationHash: No hay datos de personalización');
						return 'no-customization';
					}
					
					// Los datos pueden venir en diferentes formatos
					var areas = customizationData.areas || [];
					
					if (!areas || areas.length === 0) {
						console.log('[WPDM Cart] ⚠️ createCustomizationHash: No hay áreas en los datos');
						return 'no-areas';
					}
					
					console.log('[WPDM Cart] 🔍 createCustomizationHash: Procesando', areas.length, 'áreas');
					
					// Crear un hash basado en áreas, imágenes, colores, etc.
					var hashParts = [];
					
					// Ordenar áreas por índice para consistencia
					var sortedAreas = areas.slice().sort(function(a, b) {
						var aIndex = parseInt(a.index) || parseInt(a.area_index) || 0;
						var bIndex = parseInt(b.index) || parseInt(b.area_index) || 0;
						return aIndex - bIndex;
					});
					
					sortedAreas.forEach(function(area, idx) {
						var areaHash = [];
						var areaIndex = area.index || area.area_index || idx;
						var areaName = area.name || area.area_name || '';
						
						areaHash.push('area-' + areaIndex);
						areaHash.push('name-' + areaName);
						
						// Incluir información de imagen si existe (puede venir como image_url, image_filename, etc.)
						var imageUrl = area.image_url || area.image || '';
						var imageFilename = area.image_filename || '';
						
						if (imageUrl) {
							// Usar solo el nombre del archivo, no la URL completa (puede variar)
							var filename = imageUrl.split('/').pop();
							areaHash.push('img-' + filename);
						} else if (imageFilename) {
							areaHash.push('img-' + imageFilename);
						}
						
						// Incluir información de técnica y colores
						var technique = area.technique || area.technique_name || '';
						if (technique) {
							areaHash.push('tech-' + technique);
						}
						
						var colors = area.colors || [];
						if (colors && colors.length > 0) {
							var colorNames = colors.map(function(c) {
								if (typeof c === 'string') {
									return c;
								}
								return c.name || c.code || c;
							}).sort().join(',');
							areaHash.push('colors-' + colorNames);
						}
						
						var areaHashStr = areaHash.join('|');
						hashParts.push(areaHashStr);
						console.log('[WPDM Cart] 🔍 Área', areaIndex, 'hash:', areaHashStr);
					});
					
					var finalHash = hashParts.join('||');
					console.log('[WPDM Cart] 🔑 Hash final de personalización:', finalHash);
					
					return finalHash;
				}
				
				// Procesar cada grupo y obtener datos de personalización para separar correctamente
				var processingPromises = [];
				
				$.each(productGroups, function(groupKey, items) {
					var productId = groupKey;
					
					// Verificar si el primer item tiene personalización
					var $firstItem = items[0];
					var $firstItemRow = $firstItem.closest('tr, .cart_item');
					var firstItemClasses = $firstItemRow.attr('class') || '';
					var hasCustomization = firstItemClasses.indexOf('wpdm-customized-item') !== -1 || 
					                      firstItemClasses.indexOf('wpdm-customized-global') !== -1 || 
					                      firstItemClasses.indexOf('wpdm-customized-per-color') !== -1;
					
					if (!hasCustomization) {
						// Producto sin personalización: agrupar todos juntos
						if (!nonCustomizedGroups[productId]) {
							nonCustomizedGroups[productId] = [];
						}
						nonCustomizedGroups[productId] = nonCustomizedGroups[productId].concat(items);
						console.log('[WPDM Cart] 📦 Grupo', groupKey, 'añadido a sin personalizar (productId:', productId + ')');
						return; // Continuar con el siguiente grupo
					}
					
					// Producto con personalización: obtener datos de personalización de cada item
					console.log('[WPDM Cart] 🔍 Analizando grupo personalizado:', groupKey, '- Product ID:', productId, '- Items:', items.length);
					
					// Obtener datos de personalización de todos los items en paralelo
					var ajaxPromises = [];
					
					items.forEach(function($item, index) {
						var $itemRow = $item.closest('tr, .cart_item');
						var cartItemKey = getCartItemKey($itemRow);
						
						if (cartItemKey) {
							var ajaxUrl = (typeof wpdmCustomization !== 'undefined' && wpdmCustomization.ajax_url) 
								? wpdmCustomization.ajax_url 
								: (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.ajax_url)
									? wc_add_to_cart_params.ajax_url
									: '/wp-admin/admin-ajax.php';
							
							var promise = $.ajax({
								url: ajaxUrl,
								type: 'POST',
								data: {
									action: 'wpdm_get_cart_item_customization',
									cart_item_key: cartItemKey
								}
							}).done(function(response) {
								console.log('[WPDM Cart] 📥 Respuesta AJAX para', cartItemKey, ':', response);
								if (response.success && response.data) {
									// Verificar si hay datos de personalización
									if (response.data.customization) {
										// Almacenar datos en el item usando data()
										$itemRow.data('wpdm-customization-data', response.data.customization);
										console.log('[WPDM Cart] ✅ Datos de personalización almacenados para', cartItemKey, '- Áreas:', response.data.customization.areas ? response.data.customization.areas.length : 0);
									} else {
										console.warn('[WPDM Cart] ⚠️ No se encontraron datos de personalización en la respuesta para', cartItemKey, '- Estructura de data:', Object.keys(response.data || {}));
									}
								} else {
									console.warn('[WPDM Cart] ⚠️ Respuesta AJAX no exitosa para', cartItemKey, '- Response:', response);
								}
							}).fail(function(xhr, status, error) {
								console.error('[WPDM Cart] ❌ Error al obtener datos de personalización para', cartItemKey, ':', status, error);
							});
							
							ajaxPromises.push(promise);
						} else {
							console.warn('[WPDM Cart] ⚠️ No se pudo obtener cart_item_key para item', index);
						}
					});
					
					// Esperar a que todas las peticiones AJAX terminen
					if (ajaxPromises.length > 0) {
						processingPromises.push(
							$.when.apply($, ajaxPromises).then(function() {
								console.log('[WPDM Cart] ✅ Todas las peticiones AJAX completadas para grupo', groupKey);
								
								// Agrupar items por hash de personalización
								var customizationSubGroups = {};
								
								items.forEach(function($item) {
									var $itemRow = $item.closest('tr, .cart_item');
									var cartItemKey = getCartItemKey($itemRow);
									var customizationData = $itemRow.data('wpdm-customization-data');
									
									console.log('[WPDM Cart] 🔍 Procesando item', cartItemKey, '- Datos de personalización:', customizationData);
									
									var customizationHash = createCustomizationHash(customizationData);
									console.log('[WPDM Cart] 🔑 Hash de personalización para', cartItemKey, ':', customizationHash);
									
									var subGroupKey = productId + '_' + customizationHash;
									
									if (!customizationSubGroups[subGroupKey]) {
										customizationSubGroups[subGroupKey] = [];
									}
									
									customizationSubGroups[subGroupKey].push($item);
								});
								
								console.log('[WPDM Cart] 📊 Subgrupos creados para', groupKey, ':', Object.keys(customizationSubGroups));
								
								// Añadir cada subgrupo a customizedGroups
								$.each(customizationSubGroups, function(subGroupKey, subItems) {
									customizedGroups[subGroupKey] = subItems;
									console.log('[WPDM Cart] ✅ Subgrupo personalizado creado:', subGroupKey, '- Items:', subItems.length, '- Hash:', subGroupKey.split('_').slice(1).join('_'));
								});
							}).fail(function() {
								console.error('[WPDM Cart] ❌ Error en peticiones AJAX para grupo', groupKey);
								// Fallback: agrupar todos juntos
								customizedGroups[groupKey] = items;
							})
						);
					} else {
						// Si no hay peticiones AJAX, agrupar todos juntos
						console.warn('[WPDM Cart] ⚠️ No se pudieron crear peticiones AJAX para grupo', groupKey);
						customizedGroups[groupKey] = items;
					}
				});
				
				// Esperar a que todas las peticiones AJAX terminen antes de continuar
				$.when.apply($, processingPromises).then(function() {
					console.log('[WPDM Cart] ✅ Todos los datos de personalización obtenidos');
					console.log('[WPDM Cart] Productos personalizados:', Object.keys(customizedGroups).length);
					console.log('[WPDM Cart] Productos sin personalizar:', Object.keys(nonCustomizedGroups).length);
					
					// Continuar con el procesamiento de grupos personalizados
					processCustomizedGroups();
				}).fail(function() {
					console.error('[WPDM Cart] Error al obtener datos de personalización, usando agrupación simple');
					// Fallback: agrupar por productId sin separar por personalización
					$.each(productGroups, function(groupKey, items) {
						var $firstItem = items[0];
						var $firstItemRow = $firstItem.closest('tr, .cart_item');
						var firstItemClasses = $firstItemRow.attr('class') || '';
						var hasCustomization = firstItemClasses.indexOf('wpdm-customized-item') !== -1 || 
						                      firstItemClasses.indexOf('wpdm-customized-global') !== -1 || 
						                      firstItemClasses.indexOf('wpdm-customized-per-color') !== -1;
						
						if (hasCustomization) {
							customizedGroups[groupKey] = items;
						} else {
							if (!nonCustomizedGroups[groupKey]) {
								nonCustomizedGroups[groupKey] = [];
							}
							nonCustomizedGroups[groupKey] = nonCustomizedGroups[groupKey].concat(items);
						}
					});
					
					processCustomizedGroups();
				});
				
				// Función para procesar grupos personalizados (se llamará después de obtener los datos)
				function processCustomizedGroups() {
					console.log('[WPDM Cart] 🎯 Iniciando procesamiento de grupos personalizados');
					console.log('[WPDM Cart] Productos personalizados:', Object.keys(customizedGroups).length);
					console.log('[WPDM Cart] Productos sin personalizar:', Object.keys(nonCustomizedGroups).length);
					
					// Reorganizar cada grupo de productos PERSONALIZADOS (visualización agrupada)
					// NOTA: groupKey ahora incluye el hash de personalización (productId_hash)
					$.each(customizedGroups, function(groupKey, items) {
						// Extraer productId de la clave del grupo (puede ser "productId_hash" o solo "productId")
						var productId = groupKey;
						if (groupKey.indexOf('_') !== -1) {
							productId = groupKey.split('_')[0];
						}
					
					// Procesar TODOS los grupos, incluso con 1 item (visualización estrella siempre visible)
					if (items.length >= 1) {
						var $firstItem = items[0];
						var $parentTable = $firstItem.closest('table, tbody');
						
						// Obtener nombre del producto (del primer item)
						var productName = $firstItem.find('.product-name a').first().text().trim();
						// Extraer solo el nombre del producto (antes del guión)
						var match = productName.match(/^([^-]+)/);
						if (match) {
							productName = match[1].trim();
						}
						
						// CRÍTICO: Detectar el modo de personalización (global, per-color, o sin personalización)
						var isGlobalMode = false;
						var isPerColorMode = false;
						var hasCustomization = false;
						
						// Verificar clases del primer item para determinar el modo
						var $firstItemRow = $firstItem.closest('tr, .cart_item');
						var firstItemClasses = $firstItemRow.attr('class') || '';
						
						if (firstItemClasses.indexOf('wpdm-customized-global') !== -1) {
							isGlobalMode = true;
							hasCustomization = true;
						} else if (firstItemClasses.indexOf('wpdm-customized-per-color') !== -1) {
							isPerColorMode = true;
							hasCustomization = true;
						} else if (firstItemClasses.indexOf('wpdm-customized-item') !== -1) {
							hasCustomization = true;
						}
						
						console.log('[WPDM Cart] Modo detectado para grupo', groupKey, '(productId:', productId, ') - Global:', isGlobalMode, 'Per-color:', isPerColorMode, 'Tiene personalización:', hasCustomization);
						
						// Obtener precio de personalización según el modo
						var customizationPrice = '0,00 €';
						var totalCustomizationPrice = 0; // Para modo per-color, sumar todas las variaciones
						
						// ESTRATEGIA 1: Buscar en el HTML del primer item (que está oculto pero tiene los datos)
						var $removeLink = $firstItemRow.find('a[href*="remove_item"]');
						if ($removeLink.length) {
							var ariaLabel = $removeLink.attr('aria-label') || '';
							// El aria-label puede contener "✓ Sí | 150,00 €" o similar
							var priceMatch = ariaLabel.match(/([\d,\.]+)\s*€/);
							if (priceMatch) {
								customizationPrice = priceMatch[0];
								console.log('[WPDM Cart] Precio encontrado en aria-label:', customizationPrice);
							}
						}
						
						// ESTRATEGIA 2: Buscar en el HTML del item (clase wpdm-personalization-info)
						if (customizationPrice === '0,00 €') {
							var $firstCustomization = $firstItemRow.find('.wpdm-personalization-info');
							if ($firstCustomization.length) {
								var priceText = $firstCustomization.find('strong').text().trim();
								if (!priceText) {
									var fullText = $firstCustomization.text();
									var priceMatch = fullText.match(/([\d,\.]+)\s*€/);
									if (priceMatch) {
										priceText = priceMatch[0];
									}
								}
								if (priceText) {
									customizationPrice = priceText;
									console.log('[WPDM Cart] Precio encontrado en wpdm-personalization-info:', customizationPrice);
								}
							}
						}
						
						// ESTRATEGIA 3: Si aún no se encontró, usar AJAX para obtener el precio del cart_item_key
						if (customizationPrice === '0,00 €' && $removeLink.length) {
							var href = $removeLink.attr('href') || '';
							var cartItemKeyMatch = href.match(/remove_item=([^&]+)/);
							if (cartItemKeyMatch && cartItemKeyMatch[1]) {
								var cartItemKey = decodeURIComponent(cartItemKeyMatch[1]);
								console.log('[WPDM Cart] Obteniendo precio via AJAX para cart_item_key:', cartItemKey);
								
								// Hacer petición AJAX para obtener el precio de personalización
								var ajaxUrl = (typeof wpdmCustomization !== 'undefined' && wpdmCustomization.ajax_url) 
									? wpdmCustomization.ajax_url 
									: (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.ajax_url)
										? wc_add_to_cart_params.ajax_url
										: '/wp-admin/admin-ajax.php';
								
								$.ajax({
									url: ajaxUrl,
									type: 'POST',
									data: {
										action: 'wpdm_get_cart_item_customization',
										cart_item_key: cartItemKey
									},
									async: false, // Síncrono para obtener el precio antes de continuar
									success: function(response) {
										if (response.success && response.data) {
											// El endpoint devuelve 'price', no 'customization_price'
											var price = parseFloat(response.data.price || response.data.customization_price || 0) || 0;
											if (price > 0) {
												// Formatear precio (ej: 150.00 -> "150,00 €")
												customizationPrice = price.toFixed(2).replace('.', ',') + ' €';
												console.log('[WPDM Cart] Precio obtenido via AJAX:', customizationPrice);
											}
										}
									}
								});
							}
						}
						
						// Si aún no se encontró, el producto no tiene personalización
						if (customizationPrice === '0,00 €') {
							console.log('[WPDM Cart] No se encontró precio de personalización para producto', productId, '- probablemente no tiene personalización');
						}
						
						// Obtener detalles de personalización del primer item
						var detailsHtml = '';
						
						// Intentar obtener del HTML oculto del item
						var $firstDetails = $firstItem.find('.wpdm-customization-details-content');
						if ($firstDetails.length && $firstDetails.html().trim()) {
							detailsHtml = $firstDetails.html();
						} else {
							// Intentar obtener del item_data (si está en el DOM)
							var $itemData = $firstItem.nextUntil(':not([class*="wpdm"])').filter('.wpdm-customization-details-content');
							if ($itemData.length) {
								detailsHtml = $itemData.html();
							} else {
								// Buscar en toda la fila del item
								var $rowDetails = $firstItem.closest('tr, .cart_item').find('.wpdm-customization-details-content');
								if ($rowDetails.length) {
									detailsHtml = $rowDetails.html();
								} else {
									// Si no hay detalles, intentar obtenerlos del nombre del producto (donde se inyecta)
									var $nameDetails = $firstItem.find('.wpdm-cart-customization-info .wpdm-customization-details-content');
									if ($nameDetails.length) {
										detailsHtml = $nameDetails.html();
									} else {
										detailsHtml = '<p style="color: #666; font-style: italic;">Detalles de personalización no disponibles</p>';
									}
								}
							}
						}
						
						// Crear contenedor de grupo
						var $groupContainer = $('<tr class="wpdm-product-group-container" data-product-id="' + productId + '"></tr>');
						var $groupCell = $('<td colspan="6" style="padding: 0 !important; border: none !important;"></td>');
						
						// Contenedor interno (sin overflow hidden para que los detalles se vean)
						var $groupInner = $('<div class="wpdm-product-group-wrapper" style="border: 3px solid #0464AC; border-radius: 8px; margin: 15px 0; background: #f8f9ff; overflow: visible; box-shadow: 0 4px 12px rgba(4, 100, 172, 0.15);"></div>');
						
						// Header con nombre del producto y botón eliminar
						var $groupHeader = $('<div class="wpdm-group-header" style="background: linear-gradient(135deg, #0464AC 0%, #053a70 100%); color: #fff; padding: 10px 15px; font-weight: 600; font-size: 1.1em; display: flex; align-items: center; justify-content: space-between;"></div>');
						$groupHeader.append('<span>' + productName + '</span>');
						
						// Botón eliminar todo el grupo
						var $deleteAllBtn = $('<button type="button" class="wpdm-delete-all-variations" data-product-id="' + productId + '" style="background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.3); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.85em; font-weight: 500; transition: all 0.2s;">Eliminar ✕</button>');
						$deleteAllBtn.hover(
							function() { $(this).css({'background': 'rgba(255,255,255,0.3)', 'border-color': 'rgba(255,255,255,0.5)'}); },
							function() { $(this).css({'background': 'rgba(255,255,255,0.2)', 'border-color': 'rgba(255,255,255,0.3)'}); }
						);
						$groupHeader.append($deleteAllBtn);
						
						// Contenedor de variaciones (layout de tres columnas)
						var $variationsContainer = $('<div class="wpdm-variations-list" style="background: #fff; padding: 8px;"></div>');
						
						// Crear grid de tres columnas
						var $variationsGrid = $('<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;"></div>');
						
						// Mover todas las variaciones al contenedor (tres columnas)
						items.forEach(function($item, index) {
							var $itemRow = $item.closest('tr, .cart_item');
							var $variationCard = $('<div class="wpdm-variation-card" style="display: flex; align-items: center; padding: 6px 8px; border: 1px solid #e0e0e0; border-radius: 4px; background: #fff; transition: all 0.2s; gap: 8px;"></div>');
							
							// Hover effect
							$variationCard.hover(
								function() { $(this).css({'background': '#f9f9f9', 'border-color': '#0464AC'}); },
								function() { $(this).css({'background': '#fff', 'border-color': '#e0e0e0'}); }
							);
							
							// Thumbnail (más pequeño) - extraer solo el contenido, no el TD
							var $thumbCell = $item.find('.product-thumbnail');
							var $thumbContent = $thumbCell.length ? $thumbCell.html() : '';
							if ($thumbContent) {
								var $thumbWrapper = $('<div style="flex-shrink: 0;"></div>');
								$thumbWrapper.html($thumbContent);
								$thumbWrapper.find('img').css({'width': '40px', 'height': '40px', 'object-fit': 'cover', 'border-radius': '3px'});
								$variationCard.append($thumbWrapper);
							}
							
							// Contenedor de información
							var $infoContainer = $('<div style="flex: 1; min-width: 0;"></div>');
							
							// Nombre de variación (más compacto) - limpiar enlaces repetidos
							var variationName = $item.find('.product-name a').text().trim();
							// Limpiar el nombre: extraer solo la parte antes de "Ver archivo" o eliminar todos los enlaces
							// Primero intentar extraer solo hasta "Ver archivo"
							var nameMatch = variationName.match(/^(.+?)(?:Ver\s*archivo|→)/i);
							if (nameMatch && nameMatch[1]) {
								variationName = nameMatch[1].trim();
							} else {
								// Si no funciona, eliminar todos los enlaces
								variationName = variationName.replace(/Ver\s*archivo\s*→/gi, ''); // Eliminar "Ver archivo →" (con o sin espacios)
								variationName = variationName.replace(/Ver\s*archivo/gi, ''); // Eliminar "Ver archivo" sin flecha
								variationName = variationName.replace(/→/g, ''); // Eliminar cualquier flecha restante
							}
							variationName = variationName.replace(/\s+/g, ' ').trim(); // Eliminar espacios múltiples
							$infoContainer.append($('<div style="font-weight: 500; color: #333; font-size: 0.9em; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="' + variationName + '">').text(variationName));
							
							// Precio y cantidad en línea (fuente más grande)
							var $priceQtyRow = $('<div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.95em;"></div>');
							
							// Precio (más grande)
							var price = $item.find('.product-price').text().trim();
							$priceQtyRow.append($('<span style="color: #666; font-weight: 500;">').text(price));
							
							// Cantidad - mostrar editable si NO tiene personalización, fija si tiene personalización
							var $qtyCell = $item.find('.product-quantity');
							var qtyValue = null;
							var $qtyInput = $qtyCell.find('input.qty, input[type="number"]');
							var $qtySpan = $qtyCell.find('.wpdm-fixed-quantity, span.wpdm-fixed-quantity');
							
							// Verificar si esta variación específica tiene personalización
							var $itemRow = $item.closest('tr, .cart_item');
							var itemClasses = $itemRow.attr('class') || '';
							var itemHasCustomization = itemClasses.indexOf('wpdm-customized-item') !== -1 || 
							                            itemClasses.indexOf('wpdm-customized-global') !== -1 || 
							                            itemClasses.indexOf('wpdm-customized-per-color') !== -1;
							
							if ($qtyInput.length) {
								qtyValue = $qtyInput.val();
							} else if ($qtySpan.length) {
								qtyValue = $qtySpan.text().trim();
							} else {
								// Intentar extraer del texto completo
								var qtyText = $qtyCell.text();
								var qtyMatch = qtyText.match(/\d+/);
								if (qtyMatch) {
									qtyValue = qtyMatch[0];
								}
							}
							
							// Mostrar cantidad: editable si NO tiene personalización, fija si tiene
							if (qtyValue) {
								if (itemHasCustomization || hasCustomization) {
									// Producto personalizado: cantidad fija
									var $qtyDisplay = $('<span class="wpdm-fixed-quantity" style="display: inline-block; padding: 4px 8px; background: #f0f0f0; border-radius: 4px; font-weight: 600; color: #333; font-size: 1em;">' + qtyValue + '</span>');
									$priceQtyRow.append($('<div style="flex-shrink: 0;">').append($qtyDisplay));
								} else {
									// Producto sin personalización: cantidad editable
									// Obtener el input original o crear uno nuevo
									var $originalInput = $qtyCell.find('input.qty, input[type="number"]');
									if ($originalInput.length) {
										// Clonar el input original para mantener funcionalidad
										var $editableQty = $originalInput.clone();
										$editableQty.css({
											'width': '50px',
											'padding': '4px 6px',
											'text-align': 'center',
											'border': '1px solid #ddd',
											'border-radius': '4px',
											'font-size': '1em',
											'font-weight': '600'
										});
										$priceQtyRow.append($('<div style="flex-shrink: 0;">').append($editableQty));
									} else {
										// Crear input nuevo si no existe
										var $editableQty = $('<input type="number" class="qty" value="' + qtyValue + '" min="1" style="width: 50px; padding: 4px 6px; text-align: center; border: 1px solid #ddd; border-radius: 4px; font-size: 1em; font-weight: 600;">');
										// Obtener cart_item_key para asociar el input
										var $itemRemoveLink = $itemRow.find('a[href*="remove_item"]');
										if ($itemRemoveLink.length) {
											var href = $itemRemoveLink.attr('href') || '';
											var match = href.match(/remove_item=([^&]+)/);
											if (match) {
												var cartItemKey = decodeURIComponent(match[1]);
												$editableQty.attr('data-cart-item-key', cartItemKey);
												$editableQty.attr('name', 'cart[' + cartItemKey + '][qty]');
											}
										}
										$priceQtyRow.append($('<div style="flex-shrink: 0;">').append($editableQty));
									}
								}
							}
							
							$infoContainer.append($priceQtyRow);
							
							// MODO PER-COLOR: Obtener precio de personalización de esta variación específica
							var variationCustomizationPrice = '0,00 €';
							if (isPerColorMode) {
								// Buscar precio de personalización en el item de esta variación
								var $itemRemoveLink = $itemRow.find('a[href*="remove_item"]');
								if ($itemRemoveLink.length) {
									var itemAriaLabel = $itemRemoveLink.attr('aria-label') || '';
									var itemPriceMatch = itemAriaLabel.match(/([\d,\.]+)\s*€/);
									if (itemPriceMatch) {
										variationCustomizationPrice = itemPriceMatch[0];
									} else {
										// Intentar obtener del HTML del item
										var $itemCustomization = $itemRow.find('.wpdm-personalization-info');
										if ($itemCustomization.length) {
											var itemPriceText = $itemCustomization.find('strong').text().trim();
											if (!itemPriceText) {
												var itemFullText = $itemCustomization.text();
												var itemPriceMatch2 = itemFullText.match(/([\d,\.]+)\s*€/);
												if (itemPriceMatch2) {
													itemPriceText = itemPriceMatch2[0];
												}
											}
											if (itemPriceText) {
												variationCustomizationPrice = itemPriceText;
											}
										}
									}
									
									// Sumar al total de personalización
									var priceValue = parseFloat(variationCustomizationPrice.replace(/[^\d,.-]/g, '').replace(',', '.'));
									if (!isNaN(priceValue) && priceValue > 0) {
										totalCustomizationPrice += priceValue;
									}
								}
								
								// Mostrar precio de personalización en la tarjeta si existe
								if (variationCustomizationPrice !== '0,00 €' && parseFloat(variationCustomizationPrice.replace(/[^\d,.-]/g, '').replace(',', '.')) > 0) {
									$infoContainer.append($('<div style="font-size: 0.8em; color: #0464AC; margin-top: 2px; font-weight: 500;">Personalización: ' + variationCustomizationPrice + '</div>'));
								}
							}
							
							// Total (más grande)
							var total = $item.find('.product-subtotal').text().trim();
							$infoContainer.append($('<div style="font-weight: 600; color: #0464AC; font-size: 1em; margin-top: 3px; text-align: right; padding-top: 3px; border-top: 1px solid #e0e0e0;">').text(total));
							
							$variationCard.append($infoContainer);
							
							$variationsGrid.append($variationCard);
						});
						
						$variationsContainer.append($variationsGrid);
						
						// Línea de personalización (global o per-color)
						var $customizationRow = null;
						// Usar groupKey para crear un ID único por cada grupo de personalización diferente
						var uniqueId = 'wpdm-group-details-' + groupKey.replace(/[^a-zA-Z0-9]/g, '-');
						
						// Determinar precio y texto según el modo
						var finalCustomizationPrice = '0,00 €';
						var customizationLabel = 'Personalización GLOBAL';
						
						if (isPerColorMode) {
							// Modo per-color: usar el total sumado de todas las variaciones
							if (totalCustomizationPrice > 0) {
								finalCustomizationPrice = totalCustomizationPrice.toFixed(2).replace('.', ',') + ' €';
								customizationLabel = 'Personalización TOTAL (por variación)';
							}
						} else if (isGlobalMode) {
							// Modo global: usar el precio único
							finalCustomizationPrice = customizationPrice;
							customizationLabel = 'Personalización GLOBAL';
						}
						
						// Solo crear la sección de personalización si hay personalización (precio > 0 Y hasCustomization)
						var priceValue = parseFloat(finalCustomizationPrice.replace(/[^\d,.-]/g, '').replace(',', '.'));
						if (hasCustomization && !isNaN(priceValue) && priceValue > 0) {
							$customizationRow = $('<div class="wpdm-group-customization" style="padding: 10px 15px; background: #fff; border-top: 2px solid #0464AC;"></div>');
							var $customizationHeader = $('<div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 6px;"></div>');
							$customizationHeader.append('<span style="font-weight: 600; color: #0464AC; font-size: 1em;">' + customizationLabel + ': <strong style="color: #0464AC;">' + finalCustomizationPrice + '</strong></span>');
							$customizationHeader.append('<button type="button" class="wpdm-toggle-details-btn" data-target="' + uniqueId + '" style="background: #0464AC; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.85em; font-weight: 500; transition: background 0.2s; white-space: nowrap;">Ver detalles ▼</button>');
							
							// Añadir texto "Cantidad fija (personalizado)" después de personalización GLOBAL
							var $fixedQtyText = $('<div style="font-size: 0.85em; color: #666; margin-top: 4px;"><span style="margin-right: 4px;">🔒</span>Cantidad fija (personalizado)</div>');
							// Detalles de personalización (ocultos) - usar clase CSS
							var $detailsContainer = $('<div id="' + uniqueId + '" class="wpdm-customization-details-content wpdm-details-hidden" style="padding: 12px 15px; background: #f9f9f9; border-top: 1px solid #e0e0e0; margin-top: 8px;"></div>');
							
							// Siempre intentar obtener detalles via AJAX para asegurar que funcionen
							$detailsContainer.html('<p style="color: #666; font-style: italic;">Cargando detalles...</p>');
							
							var ajaxUrl = (typeof wpdmCustomization !== 'undefined' && wpdmCustomization.ajax_url) 
								? wpdmCustomization.ajax_url 
								: (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.ajax_url)
									? wc_add_to_cart_params.ajax_url
									: '/wp-admin/admin-ajax.php';
							
							// MODO PER-COLOR: Obtener detalles de TODAS las variaciones
							if (isPerColorMode) {
								var allDetailsHtml = [];
								var detailsCount = 0;
								var totalItems = items.length;
								
								// Obtener cart_item_key de cada variación
								items.forEach(function($item, index) {
									var $itemRow = $item.closest('tr, .cart_item');
									var $removeLink = $itemRow.find('a[href*="remove_item"]');
									if ($removeLink.length) {
										var href = $removeLink.attr('href');
										var match = href.match(/remove_item=([^&]+)/);
										if (match) {
											var cartItemKey = decodeURIComponent(match[1]);
											var variationName = $item.find('.product-name a').text().trim();
											
											// Limpiar el nombre: extraer solo la parte antes de "Ver archivo" o eliminar todos los enlaces
											// Primero intentar extraer solo hasta "Ver archivo"
											var nameMatch = variationName.match(/^(.+?)(?:Ver\s*archivo|→)/i);
											if (nameMatch && nameMatch[1]) {
												variationName = nameMatch[1].trim();
											} else {
												// Si no funciona, eliminar todos los enlaces
												variationName = variationName.replace(/Ver\s*archivo\s*→/gi, ''); // Eliminar "Ver archivo →" (con o sin espacios)
												variationName = variationName.replace(/Ver\s*archivo/gi, ''); // Eliminar "Ver archivo" sin flecha
												variationName = variationName.replace(/→/g, ''); // Eliminar cualquier flecha restante
											}
											variationName = variationName.replace(/\s+/g, ' ').trim(); // Eliminar espacios múltiples
											
											// Obtener detalles de esta variación
											$.ajax({
												url: ajaxUrl,
												type: 'POST',
												data: {
													action: 'wpdm_get_cart_item_customization',
													cart_item_key: cartItemKey
												},
												success: function(response) {
													detailsCount++;
													var variationDetails = '';
													
													if (response.success && response.data && response.data.details) {
														// Limpiar el nombre de variación: extraer solo la parte antes de "Ver archivo"
														// Primero intentar extraer solo hasta "Ver archivo"
														var nameMatch = variationName.match(/^(.+?)(?:Ver\s*archivo|→)/i);
														var cleanVariationName;
														if (nameMatch && nameMatch[1]) {
															cleanVariationName = nameMatch[1].trim();
														} else {
															// Si no funciona, eliminar todos los enlaces
															cleanVariationName = variationName.replace(/Ver\s*archivo\s*→/gi, ''); // Eliminar "Ver archivo →" (con o sin espacios)
															cleanVariationName = cleanVariationName.replace(/Ver\s*archivo/gi, ''); // Eliminar "Ver archivo" sin flecha
															cleanVariationName = cleanVariationName.replace(/→/g, ''); // Eliminar cualquier flecha restante
														}
														cleanVariationName = cleanVariationName.replace(/\s+/g, ' ').trim(); // Eliminar espacios múltiples
														
														// Añadir encabezado con nombre de variación limpio
														var variationHeader = '<div style="margin-bottom: 15px; padding: 8px; background: #f0f0f0; border-radius: 4px; border-left: 4px solid #0464AC;"><strong style="color: #0464AC; font-size: 0.95em;">' + cleanVariationName + '</strong></div>';
														variationDetails = variationHeader + response.data.details;
													}
													
													// Guardar detalles en el índice correcto
													allDetailsHtml[index] = variationDetails;
													
													// Cuando todas las peticiones terminen, combinar los detalles
													if (detailsCount === totalItems) {
														var combinedDetails = '';
														for (var i = 0; i < allDetailsHtml.length; i++) {
															if (allDetailsHtml[i] && allDetailsHtml[i].length > 0) {
																combinedDetails += allDetailsHtml[i];
															}
														}
														
														if (combinedDetails.length > 0) {
															$detailsContainer.html(combinedDetails);
														} else {
															$detailsContainer.html('<p style="color: #666; font-style: italic;">Detalles de personalización no disponibles</p>');
														}
													}
												},
												error: function() {
													detailsCount++;
													allDetailsHtml[index] = '';
													
													// Cuando todas las peticiones terminen
													if (detailsCount === totalItems) {
														var combinedDetails = '';
														for (var i = 0; i < allDetailsHtml.length; i++) {
															if (allDetailsHtml[i] && allDetailsHtml[i].length > 0) {
																combinedDetails += allDetailsHtml[i];
															}
														}
														
														if (combinedDetails.length > 0) {
															$detailsContainer.html(combinedDetails);
														} else {
															$detailsContainer.html('<p style="color: #666; font-style: italic;">Error al cargar detalles</p>');
														}
													}
												}
											});
										}
									} else {
										// Si no hay remove link, contar como completado
										detailsCount++;
										allDetailsHtml[index] = '';
										
										if (detailsCount === totalItems) {
											var combinedDetails = '';
											for (var i = 0; i < allDetailsHtml.length; i++) {
												if (allDetailsHtml[i] && allDetailsHtml[i].length > 0) {
													combinedDetails += allDetailsHtml[i];
												}
											}
											
											if (combinedDetails.length > 0) {
												$detailsContainer.html(combinedDetails);
											} else {
												$detailsContainer.html('<p style="color: #666; font-style: italic;">Detalles de personalización no disponibles</p>');
											}
										}
									}
								});
							} else {
								// MODO GLOBAL: Obtener detalles del primer item
								var $firstItemRow = $firstItem.closest('tr, .cart_item');
								var cartItemKey = null;
								
								// Intentar obtener de data attributes o del DOM
								if ($firstItemRow.length) {
									// Buscar en los links de eliminar (aunque estén ocultos)
									var $removeLink = $firstItemRow.find('a[href*="remove_item"]');
									if ($removeLink.length) {
										var href = $removeLink.attr('href');
										var match = href.match(/remove_item=([^&]+)/);
										if (match) {
											cartItemKey = decodeURIComponent(match[1]);
										}
									}
								}
								
								// Si tenemos el cart_item_key, hacer petición AJAX
								if (cartItemKey) {
									$.ajax({
										url: ajaxUrl,
										type: 'POST',
										data: {
											action: 'wpdm_get_cart_item_customization',
											cart_item_key: cartItemKey
										},
										success: function(response) {
											if (response.success && response.data && response.data.details) {
												$detailsContainer.html(response.data.details);
											} else {
												// Si AJAX falla, intentar usar los detalles del HTML
												if (detailsHtml && detailsHtml.indexOf('no disponibles') === -1) {
													$detailsContainer.html(detailsHtml);
												} else {
													$detailsContainer.html('<p style="color: #666; font-style: italic;">Detalles de personalización no disponibles</p>');
												}
											}
										},
										error: function() {
											// Si AJAX falla, intentar usar los detalles del HTML
											if (detailsHtml && detailsHtml.indexOf('no disponibles') === -1) {
												$detailsContainer.html(detailsHtml);
											} else {
												$detailsContainer.html('<p style="color: #666; font-style: italic;">Error al cargar detalles</p>');
											}
										}
									});
								} else {
									// Si no tenemos cart_item_key, usar los detalles del HTML si existen
									if (detailsHtml && detailsHtml.indexOf('no disponibles') === -1) {
										$detailsContainer.html(detailsHtml);
									} else {
										$detailsContainer.html('<p style="color: #666; font-style: italic;">Detalles de personalización no disponibles</p>');
									}
								}
							}
							
							// Añadir elementos en el orden correcto
							$customizationRow.append($customizationHeader);
							$customizationRow.append($fixedQtyText);
							$customizationRow.append($detailsContainer);
						}
						
						// Ensamblar grupo
						$groupInner.append($groupHeader);
						$groupInner.append($variationsContainer);
						// Solo añadir sección de personalización si existe
						if ($customizationRow) {
							$groupInner.append($customizationRow);
						}
						$groupCell.append($groupInner);
						$groupContainer.append($groupCell);
						
						// Insertar antes del primer item y ocultar items originales
						$firstItem.before($groupContainer);
						items.forEach(function($item) {
							// Ocultar el elemento completo (tr o div)
							var $row = $item.closest('tr, .cart_item');
							if ($row.length) {
								$row.hide();
							} else {
								$item.hide();
							}
							// Marcar como reorganizado para evitar que MutationObserver lo detecte
							$item.data('wpdm-reorganized', true);
							if ($row.length && $row[0] !== $item[0]) {
								$row.data('wpdm-reorganized', true);
							}
						});
						
						console.log('[WPDM Cart] Grupo creado para groupKey:', groupKey, '(productId:', productId, ') con', items.length, 'variaciones');
						
						// Marcar el contenedor de grupo como reorganizado
						$groupContainer.data('wpdm-reorganized', true);
					}
				});
				
					// Procesar productos sin personalizar con la MISMA visualización agrupada
				console.log('[WPDM Cart] 🎯 INICIANDO procesamiento de productos sin personalizar. Total grupos:', Object.keys(nonCustomizedGroups).length);
				$.each(nonCustomizedGroups, function(productId, items) {
					console.log('[WPDM Cart] 🎯 Procesando grupo sin personalizar - Product ID:', productId, 'Items:', items.length);
					if (items.length >= 1) {
						var $firstItem = items[0];
						var $parentTable = $firstItem.closest('table, tbody');
						
						// Obtener nombre del producto (del primer item)
						var productName = $firstItem.find('.product-name a').first().text().trim();
						// Extraer solo el nombre del producto (antes del guión)
						var match = productName.match(/^([^-]+)/);
						if (match) {
							productName = match[1].trim();
						}
						
						// Crear contenedor de grupo (MISMA estructura que productos personalizados)
						var $groupContainer = $('<tr class="wpdm-product-group-container" data-product-id="' + productId + '"></tr>');
						var $groupCell = $('<td colspan="6" style="padding: 0 !important; border: none !important;"></td>');
						
						// Contenedor interno (borde verde para diferenciar)
						var $groupInner = $('<div class="wpdm-product-group-wrapper" style="border: 3px solid #28a745; border-radius: 8px; margin: 15px 0; background: #f8fff9; overflow: visible; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);"></div>');
						
						// Header con nombre del producto y botón eliminar (mismo estilo)
						var $groupHeader = $('<div class="wpdm-group-header" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); color: #fff; padding: 10px 15px; font-weight: 600; font-size: 1.1em; display: flex; align-items: center; justify-content: space-between;"></div>');
						$groupHeader.append('<span>📦 ' + productName + '</span>');
						
						// Botón eliminar todo el grupo
						var $deleteAllBtn = $('<button type="button" class="wpdm-delete-all-variations" data-product-id="' + productId + '" style="background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.3); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.85em; font-weight: 500; transition: all 0.2s;">Eliminar ✕</button>');
						$deleteAllBtn.hover(
							function() { $(this).css({'background': 'rgba(255,255,255,0.3)', 'border-color': 'rgba(255,255,255,0.5)'}); },
							function() { $(this).css({'background': 'rgba(255,255,255,0.2)', 'border-color': 'rgba(255,255,255,0.3)'}); }
						);
						$groupHeader.append($deleteAllBtn);
						
						// Contenedor de variaciones (layout de tres columnas - MISMO que personalizados)
						var $variationsContainer = $('<div class="wpdm-variations-list" style="background: #fff; padding: 8px;"></div>');
						var $variationsGrid = $('<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;"></div>');
						
						// Mover todas las variaciones al contenedor (tres columnas)
						items.forEach(function($item, index) {
							var $itemRow = $item.closest('tr, .cart_item');
							var $variationCard = $('<div class="wpdm-variation-card" style="display: flex; align-items: center; padding: 6px 8px; border: 1px solid #e0e0e0; border-radius: 4px; background: #fff; transition: all 0.2s; gap: 8px;"></div>');
							
							// Hover effect
							$variationCard.hover(
								function() { $(this).css({'background': '#f9f9f9', 'border-color': '#28a745'}); },
								function() { $(this).css({'background': '#fff', 'border-color': '#e0e0e0'}); }
							);
							
							// Thumbnail
							var $thumbCell = $item.find('.product-thumbnail');
							var $thumbContent = $thumbCell.length ? $thumbCell.html() : '';
							if ($thumbContent) {
								var $thumbWrapper = $('<div style="flex-shrink: 0;"></div>');
								$thumbWrapper.html($thumbContent);
								$thumbWrapper.find('img').css({'width': '40px', 'height': '40px', 'object-fit': 'cover', 'border-radius': '3px'});
								$variationCard.append($thumbWrapper);
							}
							
							// Contenedor de información
							var $infoContainer = $('<div style="flex: 1; min-width: 0;"></div>');
							
							// Nombre de variación (limpiar enlaces)
							var variationName = $item.find('.product-name a').text().trim();
							variationName = variationName.replace(/Ver\s*archivo\s*→/gi, '');
							variationName = variationName.replace(/Ver\s*archivo/gi, '');
							variationName = variationName.replace(/→/g, '');
							variationName = variationName.replace(/\s+/g, ' ').trim();
							$infoContainer.append($('<div style="font-weight: 500; color: #333; font-size: 0.9em; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="' + variationName + '">').text(variationName));
							
							// Precio y cantidad en línea
							var $priceQtyRow = $('<div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.95em;"></div>');
							
							// Precio
							var price = $item.find('.product-price').text().trim();
							$priceQtyRow.append($('<span style="color: #666; font-weight: 500;">').text(price));
							
							// Cantidad SOLO LECTURA (productos sin personalizar - deshabilitada)
							var $qtyCell = $item.find('.product-quantity');
							var $qtyInput = $qtyCell.find('input.qty, input[type="number"]');
							if ($qtyInput.length) {
								var qtyValue = $qtyInput.val();
								
								// Mostrar cantidad como texto (no editable)
								var $qtyDisplay = $('<span style="color: #666; font-weight: 600; font-size: 1em; padding: 4px 8px; background: #f5f5f5; border-radius: 4px; display: inline-block; min-width: 40px; text-align: center;">' + qtyValue + '</span>');
								$priceQtyRow.append($('<div style="flex-shrink: 0;">').append($qtyDisplay));
							}
							
							$infoContainer.append($priceQtyRow);
							
							// Total
							var total = $item.find('.product-subtotal').text().trim();
							$infoContainer.append($('<div style="font-weight: 600; color: #28a745; font-size: 1em; margin-top: 3px; text-align: right; padding-top: 3px; border-top: 1px solid #e0e0e0;">').text(total));
							
							$variationCard.append($infoContainer);
							$variationsGrid.append($variationCard);
						});
						
						$variationsContainer.append($variationsGrid);
						
						// Ensamblar grupo (SIN sección de personalización)
						$groupInner.append($groupHeader);
						$groupInner.append($variationsContainer);
						$groupCell.append($groupInner);
						$groupContainer.append($groupCell);
						
						// Insertar antes del primer item y ocultar items originales
						$firstItem.before($groupContainer);
						items.forEach(function($item) {
							var $row = $item.closest('tr, .cart_item');
							if ($row.length) {
								$row.hide();
							} else {
								$item.hide();
							}
							$item.data('wpdm-reorganized', true);
							if ($row.length && $row[0] !== $item[0]) {
								$row.data('wpdm-reorganized', true);
							}
						});
						
						console.log('[WPDM Cart] Grupo sin personalizar creado para producto ID:', productId, 'con', items.length, 'variaciones');
						$groupContainer.data('wpdm-reorganized', true);
					}
				});
				} // Fin de processCustomizedGroups()
			}
			
			// NOTA: La edición de cantidad está deshabilitada para productos sin personalizar.
			// Si el cliente desea cambiar la cantidad, debe eliminar el producto y volver a añadirlo.
			
			// Botón eliminar todas las variaciones del grupo
			$(document).on('click', '.wpdm-delete-all-variations', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var $button = $(this);
				var productId = $button.data('product-id');
				
				// Buscar los items originales del carrito (pueden estar ocultos)
				var groupItems = $('.wpdm-product-group-' + productId);
				
				console.log('[WPDM Cart] Eliminar todas las variaciones. Product ID:', productId, 'Items encontrados:', groupItems.length);
				
				if (groupItems.length > 0) {
					// Confirmar eliminación de todo el grupo
					if (!confirm('¿Deseas eliminar todas las variaciones de este producto del carrito?')) {
						return false;
					}
					
					// Obtener todos los cart_item_keys de los items originales (incluso si están ocultos)
					var removeUrls = [];
					groupItems.each(function() {
						var $item = $(this);
						// Buscar el link de eliminar en el item original
						var $itemRemoveLink = $item.find('.remove, a[href*="remove_item"]').first();
						
						if ($itemRemoveLink.length) {
							var removeUrl = $itemRemoveLink.attr('href');
							if (removeUrl) {
								removeUrls.push(removeUrl);
								console.log('[WPDM Cart] URL de eliminación encontrada:', removeUrl);
							}
						} else {
							console.warn('[WPDM Cart] No se encontró link de eliminar en item:', $item);
						}
					});
					
					console.log('[WPDM Cart] URLs de eliminación encontradas:', removeUrls.length, removeUrls);
					
					if (removeUrls.length === 0) {
						console.error('[WPDM Cart] No se encontraron URLs de eliminación');
						alert('Error: No se pudieron encontrar las variaciones para eliminar.');
						return false;
					}
					
					// Eliminar todas las URLs secuencialmente (no en paralelo para evitar problemas)
					var removed = 0;
					var totalItems = removeUrls.length;
					
					function removeNext() {
						if (removed >= totalItems) {
							console.log('[WPDM Cart] Todas las variaciones eliminadas. Recargando...');
							window.location.reload();
							return;
						}
						
						var removeUrl = removeUrls[removed];
						console.log('[WPDM Cart] Eliminando item', removed + 1, 'de', totalItems, ':', removeUrl);
						
						$.get(removeUrl).done(function() {
							removed++;
							console.log('[WPDM Cart] Item eliminado exitosamente:', removed, 'de', totalItems);
							// Continuar con el siguiente
							setTimeout(removeNext, 100);
						}).fail(function(xhr, status, error) {
							console.error('[WPDM Cart] Error al eliminar item:', removeUrl, status, error);
							removed++;
							// Continuar de todas formas
							setTimeout(removeNext, 100);
						});
					}
					
					// Iniciar eliminación
					removeNext();
				} else {
					console.error('[WPDM Cart] No se encontraron items del grupo para eliminar');
					alert('Error: No se encontraron variaciones para eliminar.');
				}
				
				return false;
			});
			
			// Interceptar eliminación de items en modo global (mantener para compatibilidad)
			$(document).on('click', '.wpdm-customized-global .remove, .wpdm-customized-global a[href*="remove_item"], .wpdm-product-group-wrapper .remove, .wpdm-product-group-wrapper a[href*="remove_item"]', function(e) {
				var $removeLink = $(this);
				var $groupContainer = $removeLink.closest('.wpdm-product-group-wrapper, .wpdm-product-group-container');
				var $cartItem = $removeLink.closest('.wpdm-customized-item, .cart_item, tr, .wpdm-variation-row');
				
				if ($groupContainer.length) {
					// Estamos en un grupo reorganizado
					var productId = $groupContainer.closest('.wpdm-product-group-container').data('product-id');
					var groupItems = $('.wpdm-product-group-' + productId);
					
					if (groupItems.length > 1) {
						// Confirmar eliminación de todo el grupo
						if (!confirm('Este producto tiene múltiples variaciones con personalización global. ¿Deseas eliminar todas las variaciones del grupo?')) {
							e.preventDefault();
							e.stopPropagation();
							return false;
						}
						
						// Eliminar todas las variaciones del grupo
						var removed = 0;
						var totalItems = groupItems.length;
						
						groupItems.each(function() {
							var $item = $(this);
							var $itemRemoveLink = $item.find('.remove, a[href*="remove_item"]').first();
							
							if ($itemRemoveLink.length) {
								var removeUrl = $itemRemoveLink.attr('href');
								if (removeUrl) {
									// Hacer petición para eliminar cada item
									$.get(removeUrl).done(function() {
										removed++;
										if (removed === totalItems) {
											// Recargar carrito después de eliminar todos
											window.location.reload();
										}
									});
								}
							}
						});
						
						e.preventDefault();
						e.stopPropagation();
						return false;
					}
				} else {
					// Comportamiento normal para items no agrupados
					var productGroup = $cartItem.attr('class').match(/wpdm-product-group-(\d+)/);
					
					if (productGroup && productGroup[1]) {
						var productId = productGroup[1];
						var groupItems = $('.wpdm-product-group-' + productId);
						
						if (groupItems.length > 1) {
							// Confirmar eliminación de todo el grupo
							if (!confirm('Este producto tiene múltiples variaciones con personalización global. ¿Deseas eliminar todas las variaciones del grupo?')) {
								e.preventDefault();
								e.stopPropagation();
								return false;
							}
							
							// Eliminar todas las variaciones del grupo
							var removed = 0;
							var totalItems = groupItems.length;
							
							groupItems.each(function() {
								var $item = $(this);
								var $itemRemoveLink = $item.find('.remove, a[href*="remove_item"]').first();
								
								if ($itemRemoveLink.length) {
									var removeUrl = $itemRemoveLink.attr('href');
									if (removeUrl) {
										// Hacer petición para eliminar cada item
										$.get(removeUrl).done(function() {
											removed++;
											if (removed === totalItems) {
												// Recargar carrito después de eliminar todos
												window.location.reload();
											}
										});
									}
								}
							});
							
							e.preventDefault();
							e.stopPropagation();
							return false;
						}
					}
				}
			});
			
			// Flag para prevenir bucles infinitos
			var isReorganizing = false;
			var lastReorganizationTime = 0;
			var reorganizationCooldown = 2000; // 2 segundos entre reorganizaciones
			
			// Función para inicializar todo
			function initializeWPDMCart() {
				// Prevenir ejecución múltiple
				var now = Date.now();
				if (isReorganizing || (now - lastReorganizationTime < reorganizationCooldown)) {
					console.log('[WPDM Cart] Reorganización ya en curso o muy reciente, saltando...');
					return;
				}
				
				isReorganizing = true;
				lastReorganizationTime = now;
				
				console.log('[WPDM Cart] Inicializando reorganización del carrito');
				
				try {
					initWPDMToggles();
					reorganizeCartItems();
				} catch (error) {
					console.error('[WPDM Cart] Error durante reorganización:', error);
				} finally {
					// Liberar flag después de un delay
					setTimeout(function() {
						isReorganizing = false;
					}, 1000);
				}
			}
			
			// Inicializar al cargar
			$(document).ready(function() {
				setTimeout(function() {
					initializeWPDMCart();
				}, 100);
			});
			
			// Re-inicializar cuando se actualiza el carrito (múltiples eventos)
			$(document.body).on('updated_cart_totals updated_checkout wc_fragments_refreshed updated_wc_div added_to_cart removed_from_cart', function() {
				console.log('[WPDM Cart] Carrito actualizado, re-inicializando');
				setTimeout(function() {
					initializeWPDMCart();
				}, 500);
			});
			
			// También escuchar eventos de AJAX de WooCommerce
			$(document).ajaxComplete(function(event, xhr, settings) {
				// Detectar si fue una petición relacionada con el carrito
				if (settings.url && (
					settings.url.indexOf('add-to-cart') !== -1 ||
					settings.url.indexOf('update-cart') !== -1 ||
					settings.url.indexOf('remove-cart-item') !== -1 ||
					settings.url.indexOf('wc-ajax') !== -1
				)) {
					console.log('[WPDM Cart] Detectada petición AJAX del carrito, re-inicializando');
					setTimeout(function() {
						initializeWPDMCart();
					}, 800);
				}
			});
			
			// Usar MutationObserver para detectar cambios en el DOM del carrito
			if (typeof MutationObserver !== 'undefined') {
				var cartObserver = new MutationObserver(function(mutations) {
					// Ignorar si ya estamos reorganizando
					if (isReorganizing) {
						return;
					}
					
					var shouldReorganize = false;
					
					mutations.forEach(function(mutation) {
						// Ignorar cambios en elementos que nosotros mismos creamos
						if (mutation.target && (
							$(mutation.target).hasClass('wpdm-product-group-container') ||
							$(mutation.target).hasClass('wpdm-product-group-wrapper') ||
							$(mutation.target).closest('.wpdm-product-group-container').length > 0 ||
							$(mutation.target).data('wpdm-reorganized') === true
						)) {
							return; // Ignorar cambios en nuestros propios elementos
						}
						
						// Detectar si se añadieron nuevos items al carrito (que no sean nuestros)
						if (mutation.addedNodes && mutation.addedNodes.length > 0) {
							for (var i = 0; i < mutation.addedNodes.length; i++) {
								var node = mutation.addedNodes[i];
								if (node.nodeType === 1) { // Element node
									var $node = $(node);
									// Ignorar si es un elemento que nosotros creamos
									if ($node.hasClass('wpdm-product-group-container') || 
										$node.closest('.wpdm-product-group-container').length > 0 ||
										$node.data('wpdm-reorganized') === true) {
										continue;
									}
									
									// Verificar si es un item del carrito nuevo (no reorganizado)
									if (($node.hasClass('cart_item') || 
										$node.hasClass('wpdm-customized-item') ||
										$node.find('.cart_item:not([data-wpdm-reorganized]), .wpdm-customized-item:not([data-wpdm-reorganized])').length > 0) &&
										!$node.data('wpdm-reorganized')) {
										shouldReorganize = true;
										break;
									}
								}
							}
						}
						
						// También detectar si se modificaron clases de items existentes (que no sean nuestros)
						if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
							var $target = $(mutation.target);
							if (!$target.data('wpdm-reorganized') && 
								($target.hasClass('cart_item') || $target.hasClass('wpdm-customized-item'))) {
								shouldReorganize = true;
							}
						}
					});
					
					if (shouldReorganize) {
						console.log('[WPDM Cart] MutationObserver detectó cambios en el carrito');
						setTimeout(function() {
							initializeWPDMCart();
						}, 500);
					}
				});
				
				// Observar cambios en el body (para capturar todos los cambios del carrito)
				$(document).ready(function() {
					setTimeout(function() {
						var $cartContainer = $('.cart, .woocommerce-cart-form, .shop_table.cart, tbody');
						if ($cartContainer.length) {
							cartObserver.observe($cartContainer[0], {
								childList: true,
								subtree: true,
								attributes: true,
								attributeFilter: ['class']
							});
							console.log('[WPDM Cart] MutationObserver iniciado');
						}
					}, 500);
				});
			}
			
		})(jQuery);
		</script>
		<style>
		/* Ocultar detalles por defecto */
		.wpdm-customization-details-content.wpdm-details-hidden {
			display: none !important;
			visibility: hidden !important;
			opacity: 0 !important;
			height: 0 !important;
			overflow: hidden !important;
			margin: 0 !important;
			padding: 0 !important;
		}
		.wpdm-customization-details-content.wpdm-details-visible {
			display: block !important;
			visibility: visible !important;
			opacity: 1 !important;
			height: auto !important;
			overflow: visible !important;
		}
		
		/* Botón Ver detalles */
		.wpdm-toggle-details-btn {
			transition: background 0.2s, transform 0.1s;
			font-weight: 500 !important;
			white-space: nowrap;
		}
		.wpdm-toggle-details-btn:hover {
			background: #053a70 !important;
			transform: translateY(-1px);
			box-shadow: 0 2px 4px rgba(0,0,0,0.2);
		}
		.wpdm-toggle-details-btn:active {
			transform: translateY(0);
		}
		
		/* Agrupación visual de items personalizados en modo global */
		.wpdm-customized-group-first {
			border-top: 3px solid #0464AC !important;
			border-left: 3px solid #0464AC !important;
			border-right: 3px solid #0464AC !important;
			border-radius: 8px 8px 0 0 !important;
			margin-top: 15px !important;
			background: #f8f9ff !important;
			box-shadow: 0 2px 8px rgba(4, 100, 172, 0.1) !important;
		}
		.wpdm-customized-group-item {
			border-left: 3px solid #0464AC !important;
			border-right: 3px solid #0464AC !important;
			margin-top: 0 !important;
			background: #f8f9ff !important;
		}
		.wpdm-customized-group-item:last-of-type {
			border-bottom: 3px solid #0464AC !important;
			border-radius: 0 0 8px 8px !important;
			margin-bottom: 15px !important;
			box-shadow: 0 2px 8px rgba(4, 100, 172, 0.1) !important;
		}
		/* Agrupar items relacionados visualmente */
		.wpdm-customized-group-first + .wpdm-customized-group-item {
			border-top: none !important;
		}
		/* Espaciado entre grupos */
		.wpdm-customized-group-first:not(:first-child) {
			margin-top: 20px !important;
		}
		/* Info de personalización en el carrito */
		.wpdm-cart-customization-info {
			clear: both;
		}
		
		/* Info de personalización */
		.wpdm-personalization-info {
			line-height: 1.6;
		}
		
		/* Detalles expandidos */
		.wpdm-customization-details-content table {
			width: 100%;
			border-collapse: collapse;
		}
		.wpdm-customization-details-content table td {
			padding: 5px 8px;
			vertical-align: top;
		}
		
		/* Cantidad fija */
		.wpdm-fixed-quantity-wrapper {
			text-align: center;
		}
		.wpdm-qty-fixed {
			display: inline-block !important;
			min-width: 60px;
		}
		
		/* Responsive */
		@media (max-width: 768px) {
			.wpdm-toggle-details-btn {
				display: block !important;
				width: 100%;
				margin-top: 8px !important;
				margin-left: 0 !important;
			}
			.wpdm-customization-details-content {
				font-size: 0.9em;
			}
		}
		</style>
		<?php
	}

	/**
	 * Guardar personalización en el pedido
	 */
	public static function save_customization_to_order( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['wpdm_customization'] ) ) {
			$item->add_meta_data( '_wpdm_customization', $values['wpdm_customization'], true );
			$item->add_meta_data( '_wpdm_customization_price', $values['wpdm_customization_price'], true );
			
			WPDM_Logger::info( 'save_customization_to_order', 'Guardando personalización en pedido', array(
				'order_id' => $order->get_id(),
				'item_id' => $item->get_id(),
				'areas_count' => isset( $values['wpdm_customization']['areas'] ) ? count( $values['wpdm_customization']['areas'] ) : 0,
				'customization_price' => $values['wpdm_customization_price']
			) );
		}
	}

	/**
	 * Formatear meta data del pedido para mostrar correctamente
	 */
	public static function format_order_item_meta( $formatted_meta, $item ) {
		foreach ( $formatted_meta as $key => $meta ) {
			if ( $meta->key === '_wpdm_customization' ) {
				$customization = maybe_unserialize( $meta->value );
				$formatted_meta[ $key ]->display_key = __( 'Personalización', 'woo-prices-dynamics-makito' );
				$formatted_meta[ $key ]->display_value = '<span style="color: #0464AC;">✓ Sí (con diseño personalizado)</span>';
			}
		}
		
		return $formatted_meta;
	}

	/**
	 * Añadir fees de personalización al carrito
	 * 
	 * IMPORTANTE: El precio de personalización es FIJO (no se multiplica por cantidad)
	 * porque ya está calculado para todas las unidades del pedido.
	 */
	public static function add_customization_fees_to_cart( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Agrupar items por producto y modo para evitar duplicar fees en modo "global"
		$fees_by_product = array();
		
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item['wpdm_customization'] ) ) {
				$customization = $cart_item['wpdm_customization'];
				$customization_price = isset( $cart_item['wpdm_customization_price'] ) ? floatval( $cart_item['wpdm_customization_price'] ) : 0;
				$product_id = $cart_item['product_id'];
				$mode = isset( $customization['mode'] ) ? $customization['mode'] : 'global';
				
				if ( $customization_price > 0 ) {
					// Crear clave única por producto y modo
					$fee_key = $product_id . '_' . $mode;
					
					// En modo "global", solo añadir el fee UNA VEZ por producto
					// En modo "per-color", añadir un fee por variación
					if ( $mode === 'global' ) {
						if ( ! isset( $fees_by_product[ $fee_key ] ) ) {
							$fees_by_product[ $fee_key ] = array(
								'price' => $customization_price,
								'product_name' => $cart_item['data']->get_name(),
								'variations' => array()
							);
						}
						// Añadir info de variación para el nombre del fee
						$variation_info = isset( $cart_item['wpdm_variation_info'] ) ? $cart_item['wpdm_variation_info'] : array();
						if ( ! empty( $variation_info['color'] ) ) {
							$fees_by_product[ $fee_key ]['variations'][] = $variation_info['color'];
						}
					} else {
						// Modo per-color: añadir fee individual
						$product_name = $cart_item['data']->get_name();
						$variation_info = isset( $cart_item['wpdm_variation_info'] ) ? $cart_item['wpdm_variation_info'] : array();
						$color = ! empty( $variation_info['color'] ) ? $variation_info['color'] : '';
						
						$fee_name = $color ? 
							sprintf( __( 'Personalización %s (%s)', 'woo-prices-dynamics-makito' ), $product_name, $color ) :
							sprintf( __( 'Personalización %s', 'woo-prices-dynamics-makito' ), $product_name );
						
						$cart->add_fee( $fee_name, $customization_price, true );
						
						WPDM_Logger::debug( 'add_customization_fees_to_cart', 'Fee de personalización añadido (per-color)', array(
							'cart_item_key' => $cart_item_key,
							'product_name' => $product_name,
							'color' => $color,
							'customization_price' => $customization_price
						) );
					}
				}
			}
		}
		
		// CRÍTICO: Sumar todos los fees de personalización en un solo fee "Personalización GLOBAL"
		// Esto asegura que se muestre correctamente en los totales del carrito
		$total_customization_price = 0;
		$total_products_with_customization = 0;
		
		foreach ( $fees_by_product as $fee_key => $fee_data ) {
			$total_customization_price += $fee_data['price'];
			$total_products_with_customization++;
		}
		
		// Solo añadir el fee si hay personalización
		if ( $total_customization_price > 0 ) {
			$fee_name = __( 'Personalización GLOBAL', 'woo-prices-dynamics-makito' );
			$cart->add_fee( $fee_name, $total_customization_price, true );
			
			if ( class_exists( 'WPDM_Logger' ) ) {
				WPDM_Logger::info( 'add_customization_fees_to_cart', 'Fee de personalización GLOBAL añadido (suma total)', array(
					'total_customization_price' => $total_customization_price,
					'total_products' => $total_products_with_customization,
					'fee_name' => $fee_name,
					'fees_by_product' => $fees_by_product
				) );
			}
		}
	}

	/**
	 * Deshabilitar selector de cantidad para productos personalizados
	 */
	public static function disable_quantity_change_for_customized( $product_quantity, $cart_item_key, $cart_item ) {
		if ( ! empty( $cart_item['wpdm_customization'] ) ) {
			// Mostrar cantidad fija sin selector + aviso
			$quantity = $cart_item['quantity'];
			return '<div class="wpdm-fixed-quantity-wrapper" style="position: relative;">' .
				   '<span class="wpdm-fixed-quantity" style="display: inline-block; padding: 8px 12px; background: #f0f0f0; border-radius: 4px; font-weight: 600; color: #333;">' . $quantity . '</span>' .
				   '<span style="display: block; font-size: 0.75em; color: #999; margin-top: 3px;">🔒 Cantidad fija (personalizado)</span>' .
				   '</div>';
		}
		
		return $product_quantity;
	}

	/**
	 * Marcar productos personalizados como "vendidos individualmente"
	 * Esto previene que se cambien las cantidades desde otras partes
	 */
	public static function mark_customized_as_sold_individually( $sold_individually, $product ) {
		// Este filtro se aplicará cuando el producto ya esté en el carrito
		// Verificar si algún item en el carrito es este producto con personalización
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( ! empty( $cart_item['wpdm_customization'] ) && 
					 $cart_item['product_id'] == $product->get_id() ) {
					return true; // Marcar como vendido individualmente
				}
			}
		}
		
		return $sold_individually;
	}
	
	/**
	 * Prevenir cambios de cantidad via AJAX
	 */
	public static function prevent_quantity_update_for_customized( $valid, $cart_item_key, $values, $quantity ) {
		if ( ! empty( $values['wpdm_customization'] ) && $values['quantity'] != $quantity ) {
			wc_add_notice( 
				__( '⚠️ No se puede cambiar la cantidad de productos personalizados. Si desea una cantidad diferente, elimine el producto y vuélvalo a añadir con la cantidad correcta.', 'woo-prices-dynamics-makito' ), 
				'error' 
			);
			return false;
		}
		return $valid;
	}

	/**
	 * Añadir metabox al admin del pedido
	 */
	public static function add_order_customization_metabox() {
		// Para WooCommerce 3.0+
		add_meta_box(
			'wpdm_order_customization',
			'<span style="color: #0464AC;">🎨 ' . __( 'Detalles de Personalización', 'woo-prices-dynamics-makito' ) . '</span>',
			array( __CLASS__, 'render_order_customization_metabox' ),
			'shop_order',
			'normal',
			'high'
		);
		
		// Para HPOS (High-Performance Order Storage) WooCommerce 8.0+
		add_meta_box(
			'wpdm_order_customization',
			'<span style="color: #0464AC;">🎨 ' . __( 'Detalles de Personalización', 'woo-prices-dynamics-makito' ) . '</span>',
			array( __CLASS__, 'render_order_customization_metabox' ),
			'woocommerce_page_wc-orders',
			'normal',
			'high'
		);
	}

	/**
	 * Renderizar contenido del metabox
	 */
	public static function render_order_customization_metabox( $post_or_order_object ) {
		// Obtener el pedido
		$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
		
		if ( ! $order ) {
			echo '<p>' . esc_html__( 'No se pudo cargar el pedido.', 'woo-prices-dynamics-makito' ) . '</p>';
			return;
		}

		// Buscar items con personalización
		$items_with_customization = array();
		$debug_info = array();
		
		foreach ( $order->get_items() as $item_id => $item ) {
			$customization = $item->get_meta( '_wpdm_customization', true );
			$customization_price = $item->get_meta( '_wpdm_customization_price', true );
			$all_meta = $item->get_meta_data();
			
			// CRÍTICO: Si es string JSON, deserializar
			if ( is_string( $customization ) && ! empty( $customization ) ) {
				$decoded = json_decode( $customization, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$customization = $decoded;
					WPDM_Logger::info( 'render_order_customization_metabox', 'JSON deserializado correctamente', array(
						'item_id' => $item_id,
						'areas_found' => isset( $customization['areas'] ) ? count( $customization['areas'] ) : 0
					) );
				}
			}
			
			$meta_values = array();
			foreach ( $all_meta as $meta ) {
				$meta_values[ $meta->key ] = $meta->value;
			}
			
			$debug_info[ $item_id ] = array(
				'product_name' => $item->get_name(),
				'has_customization' => ! empty( $customization ),
				'customization_is_array' => is_array( $customization ),
				'customization_type' => gettype( $customization ),
				'has_areas' => isset( $customization['areas'] ) && ! empty( $customization['areas'] ),
				'areas_count' => isset( $customization['areas'] ) ? count( $customization['areas'] ) : 0,
				'customization_price' => $customization_price,
				'meta_keys' => array_keys( $meta_values )
			);
			
			if ( ! empty( $customization ) && is_array( $customization ) ) {
				$items_with_customization[ $item_id ] = array(
					'item' => $item,
					'customization' => $customization
				);
			}
		}
		
		WPDM_Logger::debug( 'render_order_customization_metabox', 'Buscando personalizaciones en pedido', array(
			'order_id' => $order->get_id(),
			'total_items' => count( $order->get_items() ),
			'items_with_customization' => count( $items_with_customization ),
			'debug_info' => $debug_info
		) );

		if ( empty( $items_with_customization ) ) {
			?>
			<p style="padding: 20px; text-align: center; color: #999;">
				<?php esc_html_e( 'Este pedido no tiene productos personalizados.', 'woo-prices-dynamics-makito' ); ?>
			</p>
			<details style="margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 4px;">
				<summary style="cursor: pointer; font-weight: 600; color: #666;">🔍 Ver información de debug</summary>
				<pre style="margin-top: 10px; padding: 10px; background: #fff; border-radius: 4px; overflow: auto; font-size: 0.85em;"><?php echo esc_html( print_r( $debug_info, true ) ); ?></pre>
			</details>
			<?php
			return;
		}

		?>
		<div class="wpdm-order-customization-container">
			<!-- Botones de acción generales -->
			<div class="wpdm-actions-header" style="background: linear-gradient(135deg, #0464AC 0%, #053a70 100%); padding: 15px 20px; margin: -12px -12px 20px -12px; border-radius: 4px 4px 0 0;">
				<div style="display: flex; gap: 10px; flex-wrap: wrap;">
					<button type="button" class="button button-primary" id="wpdm-copy-all-text" style="background: #fff; color: #0464AC; border: none;">
						📋 <?php esc_html_e( 'Copiar toda la información', 'woo-prices-dynamics-makito' ); ?>
					</button>
					<span style="color: #fff; font-size: 0.9em; align-self: center; margin-left: auto;">
						<?php printf( esc_html__( '%d producto(s) personalizado(s)', 'woo-prices-dynamics-makito' ), count( $items_with_customization ) ); ?>
					</span>
				</div>
			</div>

			<!-- Contenido oculto para copiar como texto -->
			<textarea id="wpdm-hidden-text-content" style="position: absolute; left: -9999px;"></textarea>

			<?php foreach ( $items_with_customization as $item_id => $data ) : ?>
				<?php
				$item = $data['item'];
				$customization = $data['customization'];
				$product_name = $item->get_name();
				?>
				
				<div class="wpdm-customization-item" style="border: 2px solid #0464AC; border-radius: 8px; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">
					<!-- Header del producto -->
					<h3 style="margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #dee2e6; color: #0464AC;">
						<?php echo esc_html( $product_name ); ?>
					</h3>

					<?php if ( ! empty( $customization['areas'] ) ) : ?>
						<?php foreach ( $customization['areas'] as $area_index => $area ) : ?>
							<div class="wpdm-area-detail" style="background: #fff; padding: 20px; margin-bottom: 15px; border-radius: 6px; border-left: 4px solid #0464AC;">
								<!-- Área header -->
								<h4 style="margin: 0 0 15px 0; color: #0464AC; font-size: 1.1em;">
									📐 <?php echo esc_html( $area['area_position'] ?? 'Área ' . ( $area_index + 1 ) ); ?>
								</h4>

								<table class="wpdm-detail-table" style="width: 100%; border-collapse: collapse;">
									<tbody>
										<!-- Técnica -->
										<?php if ( ! empty( $area['technique_name'] ) ) : ?>
										<tr style="border-bottom: 1px solid #f0f0f0;">
											<td style="padding: 8px 0; width: 30%; color: #666; font-weight: 500;">
												Técnica de marcación:
											</td>
											<td style="padding: 8px 0; color: #333;">
												<strong><?php echo esc_html( $area['technique_name'] ); ?></strong>
											</td>
										</tr>
										<?php endif; ?>

										<!-- Número de colores -->
										<?php if ( isset( $area['colors_selected'] ) && $area['colors_selected'] > 0 ) : ?>
										<tr style="border-bottom: 1px solid #f0f0f0;">
											<td style="padding: 8px 0; color: #666; font-weight: 500;">
												Número de colores:
											</td>
											<td style="padding: 8px 0; color: #333;">
												<strong><?php echo intval( $area['colors_selected'] ); ?></strong>
											</td>
										</tr>
										<?php endif; ?>

										<!-- Medidas (si fueron modificadas) -->
										<?php if ( ( isset( $area['width'] ) && ! empty( $area['width'] ) ) || ( isset( $area['height'] ) && ! empty( $area['height'] ) ) ) : ?>
										<tr style="border-bottom: 1px solid #f0f0f0;">
											<td style="padding: 8px 0; color: #666; font-weight: 500;">
												📏 Medidas de impresión:
											</td>
											<td style="padding: 8px 0; color: #333;">
												<strong>
													<?php 
													$width = isset( $area['width'] ) ? floatval( $area['width'] ) : 0;
													$height = isset( $area['height'] ) ? floatval( $area['height'] ) : 0;
													echo esc_html( number_format( $width, 1, ',', '.' ) . ' x ' . number_format( $height, 1, ',', '.' ) . ' mm' );
													?>
												</strong>
											</td>
										</tr>
										<?php endif; ?>

										<!-- PANTONE -->
										<?php if ( ! empty( $area['pantones'] ) && is_array( $area['pantones'] ) ) : ?>
										<tr style="border-bottom: 1px solid #f0f0f0;">
											<td style="padding: 8px 0; color: #666; font-weight: 500;">
												🎨 Colores PANTONE:
											</td>
											<td style="padding: 8px 0; color: #333;">
												<?php
												$pantone_values = array();
												foreach ( $area['pantones'] as $pantone ) {
													if ( ! empty( $pantone['value'] ) ) {
														// Mostrar el código PANTONE real (el value puede ser "PANTONE 286 C" o solo el nombre)
														$pantone_code = esc_html( $pantone['value'] );
														// Si no empieza con "PANTONE", añadirlo para claridad
														if ( stripos( $pantone_code, 'PANTONE' ) === false && stripos( $pantone_code, 'PMS' ) === false ) {
															// Es un nombre descriptivo, buscar si hay un código en el mismo campo
															$pantone_code = $pantone_code;
														}
														$pantone_values[] = '<strong>' . $pantone_code . '</strong>';
													}
												}
												?>
												<?php echo implode( ', ', $pantone_values ); ?>
											</td>
										</tr>
										<?php endif; ?>

										<!-- Imagen -->
										<?php if ( ! empty( $area['image_url'] ) ) : ?>
										<tr style="border-bottom: 1px solid #f0f0f0;">
											<td style="padding: 8px 0; color: #666; font-weight: 500; vertical-align: top;">
												📸 Archivo adjunto:
											</td>
											<td style="padding: 8px 0; color: #333;">
												<div style="display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap;">
													<a href="<?php echo esc_url( $area['image_url'] ); ?>" target="_blank" class="button button-small" style="background: #0464AC; color: #fff; border: none; text-decoration: none;">
														👁️ Ver archivo
													</a>
													<a href="<?php echo esc_url( $area['image_url'] ); ?>" download class="button button-small" style="background: #28a745; color: #fff; border: none; text-decoration: none;">
														📥 Descargar
													</a>
												</div>
												<div style="margin-top: 8px;">
													<span style="font-size: 0.85em; color: #666; font-weight: 500;">Archivo:</span>
													<span style="font-size: 0.85em; color: #999; margin-left: 5px;">
														<?php echo esc_html( $area['image_filename'] ?? basename( $area['image_url'] ) ); ?>
													</span>
												</div>
												<?php 
												// Mostrar preview si es imagen
												$image_ext = strtolower( pathinfo( $area['image_url'], PATHINFO_EXTENSION ) );
												if ( in_array( $image_ext, array( 'jpg', 'jpeg', 'png', 'gif' ) ) ) :
												?>
												<div style="margin-top: 10px;">
													<img src="<?php echo esc_url( $area['image_url'] ); ?>" alt="Preview" style="max-width: 200px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; background: #f9f9f9;">
												</div>
												<?php endif; ?>
											</td>
										</tr>
										<?php endif; ?>

										<!-- Observaciones -->
										<?php if ( ! empty( $area['observations'] ) ) : ?>
										<tr>
											<td style="padding: 8px 0; color: #666; font-weight: 500; vertical-align: top;">
												📝 Observaciones:
											</td>
											<td style="padding: 8px 0; color: #333;">
												<div style="background: #fffbea; padding: 10px; border-left: 3px solid #ffc107; border-radius: 4px;">
													<em><?php echo nl2br( esc_html( $area['observations'] ) ); ?></em>
												</div>
											</td>
										</tr>
										<?php endif; ?>

										<!-- Repetición Cliché -->
										<?php if ( ! empty( $area['cliche_repetition'] ) ) : ?>
										<tr style="border-bottom: 1px solid #f0f0f0;">
											<td style="padding: 8px 0; color: #666; font-weight: 500;">
												Repetición Cliché:
											</td>
											<td style="padding: 8px 0; color: #333;">
												<strong>✓ Sí</strong>
												<?php if ( ! empty( $area['cliche_order_number'] ) ) : ?>
													<span style="margin-left: 10px; color: #666;">
														(Nº pedido: <strong><?php echo esc_html( $area['cliche_order_number'] ); ?></strong>)
													</span>
												<?php endif; ?>
											</td>
										</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						<?php endforeach; ?>

						<!-- Resumen de precios -->
						<div class="wpdm-price-summary" style="background: #fff; padding: 20px; border-radius: 6px; border: 2px solid #28a745;">
							<h4 style="margin: 0 0 15px 0; color: #28a745;">💰 Resumen de Precios</h4>
							<table style="width: 100%;">
								<tbody>
									<tr>
										<td style="padding: 5px 0; color: #666;">Precio base producto:</td>
										<td style="padding: 5px 0; text-align: right; font-weight: 600;">
											<?php echo wc_price( $customization['base_price'] ?? 0 ); ?>
										</td>
									</tr>
									<tr>
										<td style="padding: 5px 0; color: #666;">Personalización:</td>
										<td style="padding: 5px 0; text-align: right; font-weight: 600; color: #0464AC;">
											<?php echo wc_price( $customization['customization_price'] ?? 0 ); ?>
										</td>
									</tr>
									<tr style="border-top: 2px solid #dee2e6;">
										<td style="padding: 10px 0 5px 0; color: #333; font-weight: 700; font-size: 1.1em;">
											TOTAL:
										</td>
										<td style="padding: 10px 0 5px 0; text-align: right; font-weight: 700; font-size: 1.1em; color: #28a745;">
											<?php echo wc_price( $customization['grand_total'] ?? 0 ); ?>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Copiar toda la información como texto
			$('#wpdm-copy-all-text').on('click', function() {
				var text = '';
				
				$('.wpdm-customization-item').each(function() {
					var $item = $(this);
					var productName = $item.find('h3').first().text().trim();
					
					text += '='.repeat(60) + '\n';
					text += productName + '\n';
					text += '='.repeat(60) + '\n\n';
					
					$item.find('.wpdm-area-detail').each(function() {
						var $area = $(this);
						var areaName = $area.find('h4').text().trim();
						
						text += areaName + '\n';
						text += '-'.repeat(40) + '\n';
						
						$area.find('table tr').each(function() {
							var label = $(this).find('td:first').text().trim();
							var value = $(this).find('td:last').text().trim();
							if (label && value) {
								text += label + ' ' + value + '\n';
							}
						});
						
						text += '\n';
					});
					
					text += '\n\n';
				});
				
				$('#wpdm-hidden-text-content').val(text);
				$('#wpdm-hidden-text-content').select();
				document.execCommand('copy');
				
				alert('✅ Información copiada al portapapeles');
			});

			// ELIMINADO: Botón de descarga ZIP (no funcionaba)
		});
		</script>

		<style>
		.wpdm-order-customization-container {
			max-width: 100%;
		}
		.wpdm-detail-table tr:hover {
			background: #f5f5f5;
		}
		.wpdm-actions-header button:hover {
			opacity: 0.9;
			transform: translateY(-1px);
		}
		</style>
		<?php
	}

	/**
	 * AJAX: Descargar todas las imágenes como ZIP
	 */
	public static function ajax_download_all_images_zip() {
		check_ajax_referer( 'wpdm_download_zip', 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( 'No tienes permisos para realizar esta acción.' );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_die( 'Pedido no encontrado.' );
		}

		// Recopilar todas las imágenes
		$images = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$customization = $item->get_meta( '_wpdm_customization', true );
			if ( ! empty( $customization['areas'] ) ) {
				foreach ( $customization['areas'] as $area ) {
					if ( ! empty( $area['image_url'] ) ) {
						$image_path = str_replace( wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $area['image_url'] );
						if ( file_exists( $image_path ) ) {
							$images[] = array(
								'path' => $image_path,
								'name' => ( $area['area_position'] ?? 'Area' ) . '_' . basename( $image_path )
							);
						}
					}
				}
			}
		}

		if ( empty( $images ) ) {
			wp_die( 'No hay imágenes para descargar.' );
		}

		// Crear ZIP
		$zip_filename = 'pedido-' . $order_id . '-personalizacion-' . date( 'Y-m-d-His' ) . '.zip';
		$zip_path = sys_get_temp_dir() . '/' . $zip_filename;

		$zip = new ZipArchive();
		if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			wp_die( 'Error al crear archivo ZIP.' );
		}

		foreach ( $images as $image ) {
			$zip->addFile( $image['path'], $image['name'] );
		}

		$zip->close();

		// Enviar archivo
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $zip_filename . '"' );
		header( 'Content-Length: ' . filesize( $zip_path ) );
		readfile( $zip_path );

		// Eliminar ZIP temporal
		unlink( $zip_path );

		exit;
	}
}
