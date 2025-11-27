<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper para gestionar los tramos de precio (price_tiers).
 */
class WPDM_Price_Tiers {

	/**
	 * Inicialización (por ahora solo dejado para futuro uso si es necesario).
	 */
	public static function init() {
		// Aquí podríamos registrar hooks relacionados exclusivamente con los tramos.
	}

	/**
	 * Obtener y normalizar los tramos de precio (price_tiers) de un producto.
	 *
	 * @param int $product_id
	 *
	 * @return array
	 */
	public static function get_price_tiers( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return array();
		}

		// Validar que el post exista y sea un producto de WooCommerce.
		$post = get_post( $product_id );
		$post_type = get_post_type( $product_id );
		$is_variation = ( 'product_variation' === $post_type );
		
		if ( ! $post || ( 'product' !== $post_type && ! $is_variation ) ) {
			return array();
		}

		// Buscar tramos en el producto/variación actual
		$raw_tiers = get_post_meta( $product_id, 'price_tiers', true );
		
		// Si es una variación y no tiene tramos, buscar en el producto padre
		if ( $is_variation && ( empty( $raw_tiers ) || ( is_string( $raw_tiers ) && trim( $raw_tiers ) === '' ) ) ) {
			$parent_id = wp_get_post_parent_id( $product_id );
			if ( $parent_id > 0 ) {
				$raw_tiers = get_post_meta( $parent_id, 'price_tiers', true );
			}
		}
		
		// Si es un producto simple y no tiene tramos, también intentar con prefijo underscore
		if ( empty( $raw_tiers ) || ( is_string( $raw_tiers ) && trim( $raw_tiers ) === '' ) ) {
			$raw_tiers = get_post_meta( $product_id, '_price_tiers', true );
		}
		
		// También intentar obtener con el prefijo _ (puede que JetEngine lo guarde así)
		if ( empty( $raw_tiers ) ) {
			$raw_tiers = get_post_meta( $product_id, '_price_tiers', true );
		}

		// Si está vacío, null o false, no hay datos
		if ( empty( $raw_tiers ) && $raw_tiers !== '0' && $raw_tiers !== 0 ) {
			return array();
		}

		// Si es string, intentar deserializar (puede venir serializado de JetEngine)
		if ( is_string( $raw_tiers ) ) {
			$decoded = maybe_unserialize( $raw_tiers );
			if ( is_array( $decoded ) ) {
				$raw_tiers = $decoded;
			} else {
				// Intentar decodificar JSON
				$json_decoded = json_decode( $raw_tiers, true );
				if ( is_array( $json_decoded ) ) {
					$raw_tiers = $json_decoded;
				} else {
					return array();
				}
			}
		}

		if ( ! is_array( $raw_tiers ) ) {
			return array();
		}

		$tiers = array();

		foreach ( $raw_tiers as $index => $tier ) {
			// Verificar que sea un array
			if ( ! is_array( $tier ) ) {
				continue;
			}

			// Verificar campos requeridos - JetEngine puede usar diferentes nombres
			// Intentar diferentes variaciones de nombres de campos
			$qty_from = null;
			$qty_to = null;
			$unit_price = null;
			
			// Buscar qty_from con diferentes variaciones
			if ( isset( $tier['qty_from'] ) ) {
				$qty_from = $tier['qty_from'];
			} elseif ( isset( $tier['qty_from_value'] ) ) {
				$qty_from = $tier['qty_from_value'];
			} elseif ( isset( $tier['quantity_from'] ) ) {
				$qty_from = $tier['quantity_from'];
			}
			
			// Buscar qty_to con diferentes variaciones
			if ( isset( $tier['qty_to'] ) ) {
				$qty_to = $tier['qty_to'];
			} elseif ( isset( $tier['qty_to_value'] ) ) {
				$qty_to = $tier['qty_to_value'];
			} elseif ( isset( $tier['quantity_to'] ) ) {
				$qty_to = $tier['quantity_to'];
			}
			
			// Buscar unit_price con diferentes variaciones
			if ( isset( $tier['unit_price'] ) ) {
				$unit_price = $tier['unit_price'];
			} elseif ( isset( $tier['unit_price_value'] ) ) {
				$unit_price = $tier['unit_price_value'];
			} elseif ( isset( $tier['price'] ) ) {
				$unit_price = $tier['price'];
			} elseif ( isset( $tier['price_value'] ) ) {
				$unit_price = $tier['price_value'];
			}

			// Verificar que tengamos los campos mínimos requeridos
			if ( null === $qty_from || null === $unit_price ) {
				continue;
			}

			// Validar y sanitizar datos del tramo (ya tenemos los valores de arriba)
			$qty_from   = absint( $qty_from );
			$qty_to     = ( $qty_to !== null ) ? absint( $qty_to ) : 0; // 0 = sin límite
			$unit_price = floatval( $unit_price );
			$currency   = isset( $tier['currency'] ) ? sanitize_text_field( substr( (string) $tier['currency'], 0, 10 ) ) : '';
			$source     = isset( $tier['source'] ) ? sanitize_text_field( substr( (string) $tier['source'], 0, 50 ) ) : '';

			// Validar que los valores sean razonables.
			if ( $qty_from < 0 || $qty_to < 0 || $unit_price < 0 ) {
				continue;
			}

			// Validar que qty_to sea mayor o igual a qty_from (o 0 para sin límite).
			if ( $qty_to > 0 && $qty_to < $qty_from ) {
				continue;
			}

			$tiers[] = array(
				'qty_from'   => $qty_from,
				'qty_to'     => $qty_to,
				'unit_price' => $unit_price,
				'currency'   => $currency,
				'source'     => $source,
			);
		}

		if ( empty( $tiers ) ) {
			return array();
		}

		// Ordenar por qty_from ascendente.
			usort(
				$tiers,
				static function ( $a, $b ) {
					return $a['qty_from'] <=> $b['qty_from'];
				}
			);

			return $tiers;
	}

	/**
	 * Dada una cantidad, encontrar el tramo aplicable y devolver el precio unitario.
	 *
	 * @param int $product_id
	 * @param int $quantity
	 *
	 * @return float|null Devuelve null si no hay tramos.
	 */
	public static function get_price_from_tiers( $product_id, $quantity ) {
		$tiers = self::get_price_tiers( $product_id );

		if ( empty( $tiers ) ) {
			return null;
		}

		$quantity = max( 1, (int) $quantity );
		$selected = null;
		$best_match = null;
		$best_from = 0;

		// Buscar el tramo que coincida con la cantidad.
		// Si hay múltiples coincidencias, elegir el que tenga el qty_from más alto (más específico).
		foreach ( $tiers as $tier ) {
			$from = isset( $tier['qty_from'] ) ? (int) $tier['qty_from'] : 0;
			$to   = isset( $tier['qty_to'] ) ? (int) $tier['qty_to'] : 0; // 0 = sin límite

			// Verificar si la cantidad está dentro del rango del tramo.
			if ( $quantity >= $from && ( 0 === $to || $quantity <= $to ) ) {
				// Si este tramo tiene un qty_from más alto que el mejor match anterior, es más específico.
				if ( $from >= $best_from ) {
					$best_match = $tier;
					$best_from = $from;
				}
			}
		}

		// Si encontramos un match, usarlo.
		if ( null !== $best_match ) {
			$selected = $best_match;
		} elseif ( ! empty( $tiers ) ) {
			// Si no encaja en ningún tramo, usar el último tramo (el de mayor cantidad) como fallback.
			// Esto es útil si la cantidad es mayor que todos los tramos definidos.
			$selected = end( $tiers );
		}

		if ( null === $selected || ! isset( $selected['unit_price'] ) ) {
			return null;
		}

		$unit_price = (float) $selected['unit_price'];
		
		// Validar que el precio sea válido.
		if ( $unit_price <= 0 ) {
			return null;
		}

		return $unit_price;
	}
}



