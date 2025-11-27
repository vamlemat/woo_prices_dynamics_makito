<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper para gestionar los tramos de precio (price_tiers).
 */
class WPDM_Price_Tiers {

	/**
	 * Tiempo de expiración del caché en segundos (1 hora por defecto).
	 */
	const CACHE_EXPIRATION = 3600;

	/**
	 * Inicialización.
	 */
	public static function init() {
		// Limpiar caché cuando se actualiza un producto
		add_action( 'woocommerce_update_product', array( __CLASS__, 'clear_cache' ), 10, 1 );
		add_action( 'woocommerce_update_product_variation', array( __CLASS__, 'clear_cache' ), 10, 1 );
		add_action( 'updated_post_meta', array( __CLASS__, 'maybe_clear_cache_on_meta_update' ), 10, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'maybe_clear_cache_on_meta_update' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'maybe_clear_cache_on_meta_update' ), 10, 4 );
	}

	/**
	 * Limpiar caché de tramos para un producto.
	 *
	 * @param int $product_id ID del producto.
	 */
	public static function clear_cache( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 ) {
			return;
		}

		// Limpiar caché del producto
		delete_transient( 'wpdm_price_tiers_' . $product_id );

		// Si es una variación, también limpiar el caché del padre
		$post_type = get_post_type( $product_id );
		if ( 'product_variation' === $post_type ) {
			$parent_id = wp_get_post_parent_id( $product_id );
			if ( $parent_id > 0 ) {
				delete_transient( 'wpdm_price_tiers_' . $parent_id );
			}
		} else {
			// Si es un producto padre, limpiar caché de todas sus variaciones
			$variations = get_posts( array(
				'post_parent' => $product_id,
				'post_type'   => 'product_variation',
				'numberposts' => -1,
				'fields'      => 'ids',
			) );

			foreach ( $variations as $variation_id ) {
				delete_transient( 'wpdm_price_tiers_' . $variation_id );
			}
		}
	}

	/**
	 * Limpiar caché cuando se actualiza el meta 'price_tiers'.
	 *
	 * @param int    $meta_id    ID del meta.
	 * @param int    $object_id  ID del objeto.
	 * @param string $meta_key   Clave del meta.
	 * @param mixed  $meta_value Valor del meta.
	 */
	public static function maybe_clear_cache_on_meta_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( 'price_tiers' === $meta_key || '_price_tiers' === $meta_key ) {
			self::clear_cache( $object_id );
		}
	}

	/**
	 * Limpiar todo el caché de tramos de precio.
	 * Útil para debugging o cuando se cambian muchos productos.
	 */
	public static function clear_all_cache() {
		global $wpdb;
		
		// Eliminar todos los transients con el prefijo wpdm_price_tiers_
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_wpdm_price_tiers_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_wpdm_price_tiers_' ) . '%'
			)
		);
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

		// Intentar obtener del caché primero (deshabilitado temporalmente - causa problemas)
		// TODO: Investigar por qué el caché causa problemas con la selección de tramos
		/*
		$cache_key = 'wpdm_price_tiers_' . $product_id;
		$cached_tiers = get_transient( $cache_key );
		
		if ( false !== $cached_tiers && is_array( $cached_tiers ) ) {
			return $cached_tiers;
		}
		*/

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
		
		// Si no tiene tramos, intentar con prefijo underscore (puede que JetEngine lo guarde así)
		if ( empty( $raw_tiers ) || ( is_string( $raw_tiers ) && trim( $raw_tiers ) === '' ) ) {
			$raw_tiers = get_post_meta( $product_id, '_price_tiers', true );
			
			// Si es variación y aún no hay tramos, buscar en el padre con prefijo
			if ( $is_variation && ( empty( $raw_tiers ) || ( is_string( $raw_tiers ) && trim( $raw_tiers ) === '' ) ) ) {
				$parent_id = wp_get_post_parent_id( $product_id );
				if ( $parent_id > 0 ) {
					$raw_tiers = get_post_meta( $parent_id, '_price_tiers', true );
				}
			}
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
			
			// Convertir precio: puede venir con coma como separador decimal (formato europeo)
			$unit_price_str = (string) $unit_price;
			$unit_price_str = str_replace( ',', '.', $unit_price_str ); // Reemplazar coma por punto
			$unit_price = floatval( $unit_price_str );
			
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
			// Guardar array vacío en caché para evitar consultas repetidas (deshabilitado)
			// set_transient( $cache_key, array(), self::CACHE_EXPIRATION );
			return array();
		}

		// Validar tramos superpuestos y ordenar
		$tiers = self::validate_and_sort_tiers( $tiers );

		// Guardar en caché (deshabilitado temporalmente - causa problemas)
		// set_transient( $cache_key, $tiers, self::CACHE_EXPIRATION );

		return $tiers;
	}

	/**
	 * Validar y ordenar tramos de precio.
	 * Ordena los tramos por qty_from ascendente.
	 * 
	 * Nota: No eliminamos tramos superpuestos porque la lógica de selección
	 * en get_price_from_tiers() ya maneja correctamente los casos de superposición
	 * eligiendo el tramo más específico (mayor qty_from).
	 *
	 * @param array $tiers Array de tramos sin validar.
	 * @return array Array de tramos validados y ordenados.
	 */
	private static function validate_and_sort_tiers( $tiers ) {
		if ( empty( $tiers ) || ! is_array( $tiers ) ) {
			return array();
		}

		// Ordenar por qty_from ascendente
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

		// El precio ya está convertido a float cuando se normalizó en get_price_tiers()
		// pero por si acaso, asegurarnos de que sea float
		$unit_price = (float) $selected['unit_price'];
		
		// Validar que el precio sea válido.
		if ( $unit_price <= 0 ) {
			return null;
		}

		return $unit_price;
	}
}



