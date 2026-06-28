<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode público para mostrar áreas de marcaje del producto.
 */
class WPDM_Marking_Areas {

	/**
	 * Registrar hooks.
	 */
	public static function init() {
		add_shortcode( 'wpdm_marking_areas', array( __CLASS__, 'shortcode_marking_areas' ) );
	}

	/**
	 * Shortcode: [wpdm_marking_areas]
	 *
	 * @param array $atts Atributos del shortcode.
	 *
	 * @return string
	 */
	public static function shortcode_marking_areas( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
				'title'      => '',
			),
			$atts,
			'wpdm_marking_areas'
		);

		$product_id = absint( $atts['product_id'] );

		if ( $product_id <= 0 ) {
			global $product;
			if ( $product && is_a( $product, 'WC_Product' ) ) {
				$product_id = absint( $product->get_id() );
			}
		}

		if ( $product_id <= 0 || ! wc_get_product( $product_id ) || ! class_exists( 'WPDM_Customization' ) ) {
			return '';
		}

		$areas = WPDM_Customization::get_marking_areas( $product_id );
		if ( empty( $areas ) ) {
			return '';
		}

		$grouped_areas = self::group_marking_areas( $areas );
		if ( empty( $grouped_areas ) ) {
			return '';
		}

		$printcode = self::get_product_printcode( $product_id );

		ob_start();
		?>
		<section class="wpdm-marking-areas" aria-label="<?php esc_attr_e( 'Áreas de marcaje del producto', 'woo-prices-dynamics-makito' ); ?>">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h3 class="wpdm-marking-areas__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<div class="wpdm-marking-areas__summary">
				<div class="wpdm-marking-areas__summary-row">
					<span><?php esc_html_e( 'Nº de áreas de impresión', 'woo-prices-dynamics-makito' ); ?></span>
					<strong><?php echo esc_html( count( $grouped_areas ) ); ?></strong>
				</div>
				<?php if ( $printcode !== '' ) : ?>
					<div class="wpdm-marking-areas__summary-row">
						<span><?php esc_html_e( 'Códigos de impresión', 'woo-prices-dynamics-makito' ); ?></span>
						<strong><?php echo esc_html( $printcode ); ?></strong>
					</div>
				<?php endif; ?>
			</div>

			<div class="wpdm-marking-areas__list">
				<?php foreach ( $grouped_areas as $index => $area ) : ?>
					<?php
					$area_number = $index + 1;
					$size_text   = self::format_area_size( $area );
					$image_url   = self::get_area_image_url( $area['area_img'] );
					?>
					<article class="wpdm-marking-area">
						<div class="wpdm-marking-area__info">
							<div class="wpdm-marking-area__eyebrow">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: area number */
										__( 'AREA %d', 'woo-prices-dynamics-makito' ),
										$area_number
									)
								);
								?>
							</div>
							<div class="wpdm-marking-area__name">
								<?php echo esc_html( $area['position'] !== '' ? $area['position'] : sprintf( __( 'Area %d', 'woo-prices-dynamics-makito' ), $area_number ) ); ?>
							</div>

							<div class="wpdm-marking-area__meta">
								<span><?php esc_html_e( 'Área máxima de impresión', 'woo-prices-dynamics-makito' ); ?></span>
								<strong><?php echo esc_html( $size_text !== '' ? $size_text : __( 'Consultar', 'woo-prices-dynamics-makito' ) ); ?></strong>
							</div>

							<div class="wpdm-marking-area__techniques">
								<?php foreach ( $area['techniques'] as $technique ) : ?>
									<div class="wpdm-marking-area__technique">
										<span class="wpdm-marking-area__technique-name"><?php echo esc_html( $technique['name'] ); ?></span>
										<span class="wpdm-marking-area__technique-colors"><?php echo esc_html( $technique['colors_label'] ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<?php if ( $image_url !== '' ) : ?>
							<div class="wpdm-marking-area__image">
								<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $area['position'] ); ?>" loading="lazy">
							</div>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

			<style>
				.wpdm-marking-areas {
					--wpdm-marking-primary: var(--e-global-color-primary, #6EC1E4);
					--wpdm-marking-secondary: var(--e-global-color-secondary, #54595F);
					--wpdm-marking-text: var(--e-global-color-text, #7A7A7A);
					--wpdm-marking-bg: var(--e-global-color-5938fdc, #F1F1F1);
					--wpdm-marking-blue: var(--e-global-color-90d3021, #0464AC);
					--wpdm-marking-blue-dark: var(--e-global-color-5273eb1, #061B46);
					--wpdm-marking-surface: var(--e-global-color-1e99445, #FFFFFF);
					--wpdm-marking-font: var(--e-global-typography-text-font-family, "Montserrat");
					--wpdm-marking-heading-font: var(--e-global-typography-primary-font-family, "Montserrat");
					--wpdm-marking-heading-weight: var(--e-global-typography-primary-font-weight, 600);
					--wpdm-marking-text-weight: var(--e-global-typography-text-font-weight, 400);
					--wpdm-marking-accent-weight: var(--e-global-typography-accent-font-weight, 500);
					width: 100%;
					margin: 1.5em 0;
					border: 1px solid rgba(84, 89, 95, 0.16);
					border-radius: 8px;
					background: var(--wpdm-marking-surface);
					color: var(--wpdm-marking-text);
					font-family: var(--wpdm-marking-font), sans-serif;
					font-size: 16px;
					font-weight: var(--wpdm-marking-text-weight);
					overflow: hidden;
				}
				.wpdm-marking-areas__title {
					margin: 0;
					padding: 16px 24px;
					border-bottom: 3px solid var(--wpdm-marking-primary);
					background: var(--wpdm-marking-blue-dark);
					color: var(--wpdm-marking-surface);
					font-family: var(--wpdm-marking-heading-font), sans-serif;
					font-size: 16px;
					line-height: 1.3;
					font-weight: var(--wpdm-marking-heading-weight);
					letter-spacing: 0;
				}
				.wpdm-marking-areas__summary {
					padding: 18px 24px;
					border-bottom: 1px solid rgba(84, 89, 95, 0.16);
					background: var(--wpdm-marking-bg);
				}
			.wpdm-marking-areas__summary-row {
				display: grid;
				grid-template-columns: minmax(180px, 240px) 1fr;
				gap: 24px;
				align-items: baseline;
				margin: 0 0 16px;
			}
			.wpdm-marking-areas__summary-row:last-child {
				margin-bottom: 0;
			}
				.wpdm-marking-areas__summary-row span {
					color: var(--wpdm-marking-secondary);
					font-weight: var(--wpdm-marking-text-weight);
				}
				.wpdm-marking-areas__summary-row strong {
					color: var(--wpdm-marking-blue-dark);
					font-weight: var(--wpdm-marking-accent-weight);
				}
			.wpdm-marking-area {
				display: grid;
				grid-template-columns: minmax(0, 1fr) minmax(160px, 240px);
					gap: 28px;
					align-items: start;
					padding: 22px 24px;
					border-bottom: 1px solid rgba(84, 89, 95, 0.16);
					background: var(--wpdm-marking-surface);
				}
				.wpdm-marking-area:last-child {
					border-bottom: none;
				}
				.wpdm-marking-area__eyebrow {
					color: var(--wpdm-marking-blue);
					font-family: var(--wpdm-marking-heading-font), sans-serif;
					font-weight: var(--wpdm-marking-heading-weight);
					font-size: 14px;
					line-height: 1.25;
					text-transform: uppercase;
				}
				.wpdm-marking-area__name {
					margin-top: 4px;
					color: var(--wpdm-marking-blue-dark);
					font-family: var(--wpdm-marking-heading-font), sans-serif;
					font-size: 18px;
					line-height: 1.35;
					font-weight: var(--wpdm-marking-heading-weight);
				}
			.wpdm-marking-area__meta {
				display: grid;
				grid-template-columns: minmax(190px, 1fr) minmax(120px, 180px);
				gap: 20px;
				align-items: baseline;
				margin-top: 18px;
				padding: 12px 14px;
				border-left: 4px solid var(--wpdm-marking-primary);
				background: var(--wpdm-marking-bg);
			}
				.wpdm-marking-area__meta span {
					color: var(--wpdm-marking-secondary);
				}
				.wpdm-marking-area__meta strong {
					text-align: right;
					font-weight: var(--wpdm-marking-accent-weight);
					color: var(--wpdm-marking-blue-dark);
				}
			.wpdm-marking-area__techniques {
				margin-top: 16px;
				display: grid;
				gap: 0;
				border: 1px solid rgba(84, 89, 95, 0.16);
				border-radius: 8px;
				overflow: hidden;
			}
			.wpdm-marking-area__technique {
				display: grid;
				grid-template-columns: minmax(220px, 1fr) minmax(120px, 180px);
				gap: 20px;
				align-items: baseline;
				padding: 10px 14px;
				background: var(--wpdm-marking-surface);
				border-bottom: 1px solid rgba(84, 89, 95, 0.12);
			}
			.wpdm-marking-area__technique:last-child {
				border-bottom: none;
			}
				.wpdm-marking-area__technique-name {
					color: var(--wpdm-marking-secondary);
					font-weight: var(--wpdm-marking-accent-weight);
					text-transform: uppercase;
				}
				.wpdm-marking-area__technique-colors {
					color: var(--wpdm-marking-blue-dark);
					text-align: right;
					font-weight: var(--wpdm-marking-accent-weight);
				}
			.wpdm-marking-area__image {
				display: flex;
				justify-content: center;
				align-items: flex-start;
				min-height: 150px;
				padding: 14px;
				border: 1px solid rgba(84, 89, 95, 0.16);
				border-radius: 8px;
				background: var(--wpdm-marking-bg);
			}
			.wpdm-marking-area__image img {
				display: block;
				width: auto;
				max-width: 100%;
				max-height: 210px;
				object-fit: contain;
			}
			@media (max-width: 760px) {
				.wpdm-marking-areas__title {
					padding: 14px 18px;
				}
				.wpdm-marking-areas__summary {
					padding: 16px 18px;
				}
				.wpdm-marking-areas__summary-row,
				.wpdm-marking-area__meta,
				.wpdm-marking-area__technique {
					grid-template-columns: 1fr;
					gap: 4px;
				}
				.wpdm-marking-area {
					grid-template-columns: 1fr;
					gap: 18px;
					padding: 18px;
				}
				.wpdm-marking-area__meta strong,
				.wpdm-marking-area__technique-colors {
					text-align: left;
				}
				.wpdm-marking-area__image {
					justify-content: flex-start;
					min-height: 0;
				}
				.wpdm-marking-area__image img {
					max-height: 180px;
				}
			}
		</style>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Agrupar filas de marking_areas por área física.
	 *
	 * @param array $areas Áreas normalizadas.
	 *
	 * @return array
	 */
	private static function group_marking_areas( $areas ) {
		$groups = array();

		foreach ( $areas as $area ) {
			$key = self::get_area_group_key( $area );

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'print_area_id' => isset( $area['print_area_id'] ) ? absint( $area['print_area_id'] ) : 0,
					'position'      => isset( $area['position'] ) ? sanitize_text_field( $area['position'] ) : '',
					'width'         => isset( $area['width'] ) ? sanitize_text_field( $area['width'] ) : '',
					'height'        => isset( $area['height'] ) ? sanitize_text_field( $area['height'] ) : '',
					'area_img'      => isset( $area['area_img'] ) ? esc_url_raw( $area['area_img'] ) : '',
					'techniques'    => array(),
				);
			}

			$technique = self::build_technique_display_data( $area );
			if ( empty( $technique['ref'] ) || isset( $groups[ $key ]['techniques'][ $technique['ref'] ] ) ) {
				continue;
			}

			$groups[ $key ]['techniques'][ $technique['ref'] ] = $technique;
		}

		foreach ( $groups as $key => $group ) {
			$groups[ $key ]['techniques'] = array_values( $group['techniques'] );
		}

		return array_values( $groups );
	}

	/**
	 * Obtener clave estable para agrupar un área.
	 *
	 * @param array $area Área normalizada.
	 *
	 * @return string
	 */
	private static function get_area_group_key( $area ) {
		$print_area_id = isset( $area['print_area_id'] ) ? absint( $area['print_area_id'] ) : 0;
		$position      = isset( $area['position'] ) ? sanitize_title( $area['position'] ) : '';
		$width         = isset( $area['width'] ) ? sanitize_text_field( $area['width'] ) : '';
		$height        = isset( $area['height'] ) ? sanitize_text_field( $area['height'] ) : '';
		$area_img      = isset( $area['area_img'] ) ? esc_url_raw( $area['area_img'] ) : '';

		if ( $print_area_id > 0 ) {
			return 'id_' . $print_area_id . '_' . $position . '_' . $width . 'x' . $height . '_' . md5( $area_img );
		}

		return md5( $position . '|' . $width . '|' . $height . '|' . $area_img );
	}

	/**
	 * Construir datos visibles de una técnica.
	 *
	 * @param array $area Área normalizada.
	 *
	 * @return array
	 */
	private static function build_technique_display_data( $area ) {
		$ref  = isset( $area['technique_ref'] ) ? sanitize_text_field( $area['technique_ref'] ) : '';
		$name = $ref;

		if ( $ref !== '' ) {
			$technique_post = WPDM_Customization::get_technique_by_ref( $ref );
			if ( $technique_post ) {
				$technique_data = WPDM_Customization::get_technique_data( $technique_post );
				if ( ! empty( $technique_data['name'] ) ) {
					$name = $technique_data['name'];
				}
			}
		}

		$name = trim( wp_strip_all_tags( $name ) );
		if ( $name === '' ) {
			$name = __( 'Técnica sin nombre', 'woo-prices-dynamics-makito' );
		}

		return array(
			'ref'          => $ref,
			'name'         => self::uppercase( $name ),
			'colors_label' => self::format_colors_label( isset( $area['max_colors'] ) ? absint( $area['max_colors'] ) : 0, $name ),
		);
	}

	/**
	 * Formatear dimensiones del área.
	 *
	 * @param array $area Área agrupada.
	 *
	 * @return string
	 */
	private static function format_area_size( $area ) {
		$width  = isset( $area['width'] ) ? trim( (string) $area['width'] ) : '';
		$height = isset( $area['height'] ) ? trim( (string) $area['height'] ) : '';

		if ( $width === '' || $height === '' ) {
			return '';
		}

		return sprintf(
			/* translators: 1: width, 2: height */
			__( '%1$s x %2$s mm', 'woo-prices-dynamics-makito' ),
			$width,
			$height
		);
	}

	/**
	 * Formatear etiqueta de colores.
	 *
	 * @param int    $max_colors Máximo de colores.
	 * @param string $name Nombre de técnica.
	 *
	 * @return string
	 */
	private static function format_colors_label( $max_colors, $name ) {
		$upper_name = self::uppercase( $name );
		if ( strpos( $upper_name, 'SUBLIM' ) !== false || strpos( $upper_name, 'FULL' ) !== false || strpos( $upper_name, 'DIGITAL' ) !== false ) {
			return __( 'FULLCOLOR', 'woo-prices-dynamics-makito' );
		}

		if ( $max_colors <= 0 ) {
			return __( 'max. colores', 'woo-prices-dynamics-makito' );
		}

		if ( 1 === $max_colors ) {
			return __( 'max. 1 color', 'woo-prices-dynamics-makito' );
		}

		return sprintf(
			/* translators: %d: maximum colors */
			__( 'max. %d colores', 'woo-prices-dynamics-makito' ),
			$max_colors
		);
	}

	/**
	 * Obtener código de impresión del producto.
	 *
	 * @param int $product_id ID del producto.
	 *
	 * @return string
	 */
	private static function get_product_printcode( $product_id ) {
		$printcode = get_post_meta( $product_id, '_printcode', true );

		if ( $printcode === '' ) {
			$printcode = get_post_meta( $product_id, 'printcode', true );
		}

		return sanitize_text_field( (string) $printcode );
	}

	/**
	 * Resolver URL de imagen del área.
	 *
	 * @param string $area_img Imagen del área.
	 *
	 * @return string
	 */
	private static function get_area_image_url( $area_img ) {
		$area_img = trim( (string) $area_img );

		if ( $area_img === '' ) {
			return '';
		}

		if ( filter_var( $area_img, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $area_img );
		}

		$attachment_id = absint( $area_img );
		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'large' );
			return $url ? esc_url_raw( $url ) : '';
		}

		return esc_url_raw( $area_img );
	}

	/**
	 * Convertir texto a mayúsculas con fallback si mbstring no está activo.
	 *
	 * @param string $text Texto original.
	 *
	 * @return string
	 */
	private static function uppercase( $text ) {
		if ( function_exists( 'mb_strtoupper' ) ) {
			return mb_strtoupper( $text );
		}

		return strtoupper( $text );
	}
}
