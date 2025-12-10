<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Panel de administración para gestionar imágenes de personalización
 */
class WPDM_Customization_Images_Admin {

	const UPLOAD_DIR = 'wpdm-customization';

	/**
	 * Inicializar hooks
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_wpdm_delete_customization_image', array( __CLASS__, 'ajax_delete_image' ) );
		add_action( 'wp_ajax_wpdm_bulk_delete_customization_images', array( __CLASS__, 'ajax_bulk_delete_images' ) );
	}

	/**
	 * Añadir menú en admin
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Imágenes de Personalización', 'woo-prices-dynamics-makito' ),
			__( 'Imágenes Personalización', 'woo-prices-dynamics-makito' ),
			'manage_woocommerce',
			'wpdm-customization-images',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Cargar scripts y estilos
	 */
	public static function enqueue_admin_scripts( $hook ) {
		if ( $hook !== 'woocommerce_page_wpdm-customization-images' ) {
			return;
		}

		wp_enqueue_style( 'wpdm-customization-images-admin', plugins_url( '../assets/css/wpdm-customization-images-admin.css', __FILE__ ), array(), WPDM_WOOPRICES_VERSION );
		wp_enqueue_script( 'wpdm-customization-images-admin', plugins_url( '../assets/js/wpdm-customization-images-admin.js', __FILE__ ), array( 'jquery' ), WPDM_WOOPRICES_VERSION, true );
		
		wp_localize_script( 'wpdm-customization-images-admin', 'wpdmImagesAdmin', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wpdm_customization_images_admin' ),
			'strings' => array(
				'confirm_delete' => __( '¿Estás seguro de que quieres eliminar esta imagen?', 'woo-prices-dynamics-makito' ),
				'confirm_bulk_delete' => __( '¿Estás seguro de que quieres eliminar las imágenes seleccionadas?', 'woo-prices-dynamics-makito' ),
				'deleting' => __( 'Eliminando...', 'woo-prices-dynamics-makito' ),
				'deleted' => __( 'Imagen eliminada correctamente', 'woo-prices-dynamics-makito' ),
				'error' => __( 'Error al eliminar la imagen', 'woo-prices-dynamics-makito' ),
				'select_at_least_one' => __( 'Selecciona al menos una imagen', 'woo-prices-dynamics-makito' )
			)
		) );
	}

	/**
	 * Renderizar página de administración
	 */
	public static function render_admin_page() {
		$upload_dir = wp_upload_dir();
		$custom_dir = $upload_dir['basedir'] . '/' . self::UPLOAD_DIR;
		$custom_url = $upload_dir['baseurl'] . '/' . self::UPLOAD_DIR;

		// Obtener todas las imágenes
		$images = self::get_all_customization_images( $custom_dir, $custom_url );

		// Estadísticas
		$total_images = count( $images );
		$total_size = array_sum( array_column( $images, 'size' ) );
		$total_size_mb = round( $total_size / ( 1024 * 1024 ), 2 );

		?>
		<div class="wrap wpdm-customization-images-admin">
			<h1 class="wp-heading-inline">
				<span style="font-size: 1.3em; margin-right: 10px;">🖼️</span>
				<?php esc_html_e( 'Imágenes de Personalización', 'woo-prices-dynamics-makito' ); ?>
			</h1>
			<hr class="wp-header-end">

			<!-- Estadísticas -->
			<div class="wpdm-stats-box" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
				<div style="display: flex; gap: 30px; flex-wrap: wrap;">
					<div>
						<div style="font-size: 2em; font-weight: 600; color: #0464AC;"><?php echo number_format( $total_images, 0, ',', '.' ); ?></div>
						<div style="color: #666; font-size: 0.9em;"><?php esc_html_e( 'Imágenes totales', 'woo-prices-dynamics-makito' ); ?></div>
					</div>
					<div>
						<div style="font-size: 2em; font-weight: 600; color: #28a745;"><?php echo number_format( $total_size_mb, 2, ',', '.' ); ?> MB</div>
						<div style="color: #666; font-size: 0.9em;"><?php esc_html_e( 'Espacio utilizado', 'woo-prices-dynamics-makito' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Acciones masivas -->
			<div style="margin: 20px 0; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
				<button type="button" class="button" id="wpdm-select-all">
					<?php esc_html_e( 'Seleccionar todas', 'woo-prices-dynamics-makito' ); ?>
				</button>
				<button type="button" class="button" id="wpdm-deselect-all">
					<?php esc_html_e( 'Deseleccionar todas', 'woo-prices-dynamics-makito' ); ?>
				</button>
				<button type="button" class="button button-danger" id="wpdm-bulk-delete" style="background: #dc3545; border-color: #dc3545; color: #fff;">
					🗑️ <?php esc_html_e( 'Eliminar seleccionadas', 'woo-prices-dynamics-makito' ); ?>
				</button>
			</div>

			<?php if ( empty( $images ) ) : ?>
				<div class="notice notice-info" style="padding: 20px;">
					<p><?php esc_html_e( 'No hay imágenes de personalización almacenadas.', 'woo-prices-dynamics-makito' ); ?></p>
				</div>
			<?php else : ?>
				<div class="wpdm-images-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
					<?php foreach ( $images as $image ) : ?>
						<div class="wpdm-image-card" data-image-path="<?php echo esc_attr( $image['path'] ); ?>" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s;">
							<div style="position: relative;">
								<label style="position: absolute; top: 10px; left: 10px; z-index: 10;">
									<input type="checkbox" class="wpdm-image-checkbox" value="<?php echo esc_attr( $image['path'] ); ?>" style="width: 20px; height: 20px; cursor: pointer;">
								</label>
								
								<?php if ( $image['is_image'] ) : ?>
									<img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['filename'] ); ?>" style="width: 100%; height: 150px; object-fit: contain; background: #f9f9f9; border-radius: 4px; margin-bottom: 10px;">
								<?php else : ?>
									<div style="width: 100%; height: 150px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; font-size: 3em;">
										📄
									</div>
								<?php endif; ?>
							</div>

							<div style="margin-top: 10px;">
								<div style="font-weight: 600; color: #333; margin-bottom: 5px; word-break: break-all; font-size: 0.9em;">
									<?php echo esc_html( $image['filename'] ); ?>
								</div>
								<div style="font-size: 0.85em; color: #666; margin-bottom: 5px;">
									<?php echo esc_html( size_format( $image['size'], 2 ) ); ?>
								</div>
								<div style="font-size: 0.85em; color: #999;">
									<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $image['modified'] ) ); ?>
								</div>
							</div>

							<div style="margin-top: 10px; display: flex; gap: 5px; flex-wrap: wrap;">
								<a href="<?php echo esc_url( $image['url'] ); ?>" target="_blank" class="button button-small" style="flex: 1; text-align: center; text-decoration: none;">
									👁️ <?php esc_html_e( 'Ver', 'woo-prices-dynamics-makito' ); ?>
								</a>
								<a href="<?php echo esc_url( $image['url'] ); ?>" download class="button button-small" style="flex: 1; text-align: center; text-decoration: none;">
									📥 <?php esc_html_e( 'Descargar', 'woo-prices-dynamics-makito' ); ?>
								</a>
								<button type="button" class="button button-small wpdm-delete-single" data-image-path="<?php echo esc_attr( $image['path'] ); ?>" style="flex: 1; background: #dc3545; border-color: #dc3545; color: #fff;">
									🗑️ <?php esc_html_e( 'Eliminar', 'woo-prices-dynamics-makito' ); ?>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Obtener todas las imágenes de personalización
	 */
	private static function get_all_customization_images( $dir, $url ) {
		$images = array();

		if ( ! is_dir( $dir ) ) {
			return $images;
		}

		$files = glob( $dir . '/*' );

		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				$filename = basename( $file );
				$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
				$is_image = in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ) );

				$images[] = array(
					'filename' => $filename,
					'path' => $file,
					'url' => $url . '/' . $filename,
					'size' => filesize( $file ),
					'modified' => filemtime( $file ),
					'is_image' => $is_image,
					'extension' => $ext
				);
			}
		}

		// Ordenar por fecha de modificación (más recientes primero)
		usort( $images, function( $a, $b ) {
			return $b['modified'] - $a['modified'];
		} );

		return $images;
	}

	/**
	 * AJAX: Eliminar imagen individual
	 */
	public static function ajax_delete_image() {
		check_ajax_referer( 'wpdm_customization_images_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para realizar esta acción.', 'woo-prices-dynamics-makito' ) ) );
		}

		$image_path = isset( $_POST['image_path'] ) ? sanitize_text_field( $_POST['image_path'] ) : '';

		if ( empty( $image_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Ruta de imagen no válida.', 'woo-prices-dynamics-makito' ) ) );
		}

		// Validar que la ruta está dentro del directorio permitido
		$upload_dir = wp_upload_dir();
		$allowed_dir = $upload_dir['basedir'] . '/' . self::UPLOAD_DIR;
		$real_path = realpath( $image_path );

		if ( ! $real_path || strpos( $real_path, realpath( $allowed_dir ) ) !== 0 ) {
			wp_send_json_error( array( 'message' => __( 'Ruta de imagen no válida.', 'woo-prices-dynamics-makito' ) ) );
		}

		if ( file_exists( $real_path ) && is_file( $real_path ) ) {
			if ( unlink( $real_path ) ) {
				WPDM_Logger::info( 'wpdm_customization_images_admin', 'Imagen eliminada', array(
					'filename' => basename( $real_path )
				) );
				wp_send_json_success( array( 'message' => __( 'Imagen eliminada correctamente.', 'woo-prices-dynamics-makito' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Error al eliminar la imagen.', 'woo-prices-dynamics-makito' ) ) );
			}
		} else {
			wp_send_json_error( array( 'message' => __( 'La imagen no existe.', 'woo-prices-dynamics-makito' ) ) );
		}
	}

	/**
	 * AJAX: Eliminar imágenes en lote
	 */
	public static function ajax_bulk_delete_images() {
		check_ajax_referer( 'wpdm_customization_images_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para realizar esta acción.', 'woo-prices-dynamics-makito' ) ) );
		}

		$image_paths = isset( $_POST['image_paths'] ) ? (array) $_POST['image_paths'] : array();

		if ( empty( $image_paths ) ) {
			wp_send_json_error( array( 'message' => __( 'No se seleccionaron imágenes.', 'woo-prices-dynamics-makito' ) ) );
		}

		$upload_dir = wp_upload_dir();
		$allowed_dir = $upload_dir['basedir'] . '/' . self::UPLOAD_DIR;
		$deleted = 0;
		$errors = 0;

		foreach ( $image_paths as $image_path ) {
			$image_path = sanitize_text_field( $image_path );
			$real_path = realpath( $image_path );

			if ( ! $real_path || strpos( $real_path, realpath( $allowed_dir ) ) !== 0 ) {
				$errors++;
				continue;
			}

			if ( file_exists( $real_path ) && is_file( $real_path ) ) {
				if ( unlink( $real_path ) ) {
					$deleted++;
				} else {
					$errors++;
				}
			}
		}

		WPDM_Logger::info( 'wpdm_customization_images_admin', 'Eliminación masiva de imágenes', array(
			'deleted' => $deleted,
			'errors' => $errors,
			'total' => count( $image_paths )
		) );

		if ( $deleted > 0 ) {
			wp_send_json_success( array(
				'message' => sprintf(
					_n( '%d imagen eliminada correctamente.', '%d imágenes eliminadas correctamente.', $deleted, 'woo-prices-dynamics-makito' ),
					$deleted
				),
				'deleted' => $deleted,
				'errors' => $errors
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'No se pudo eliminar ninguna imagen.', 'woo-prices-dynamics-makito' ) ) );
		}
	}
}




