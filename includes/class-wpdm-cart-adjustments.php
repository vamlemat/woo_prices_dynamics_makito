<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajustes de carrito para aplicar los tramos de precio.
 */
class WPDM_Cart_Adjustments {

	/**
	 * Flag para prevenir loops infinitos.
	 *
	 * @var bool
	 */
	private static $is_calculating = false;

	/**
	 * Cache de precios calculados por grupo de producto.
	 * Estructura: [parent_id][total_quantity] => unit_price
	 *
	 * @var array
	 */
	private static $price_cache = array();

	/**
	 * Registrar hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_price_tiers_to_cart' ), 10, 1 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'store_tier_price_in_cart_item' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'get_cart_item_from_session' ), 10, 3 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( __CLASS__, 'update_price_on_quantity_change' ), 10, 4 );
		add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'display_cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( __CLASS__, 'display_cart_item_subtotal' ), 10, 3 );
	}

	/**
	 * Aplicar los tramos de precio al carrito de WooCommerce.
	 * 
	 * Para productos variables: calcula el precio basado en la suma total
	 * de todas las variaciones del mismo producto padre.
	 *
	 * @param WC_Cart $cart
	 */
	public static function apply_price_tiers_to_cart( $cart ) {
		// Evitar que se ejecute en admin (salvo AJAX del carrito) o sin carrito.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
			return;
		}

		// Prevenir loops infinitos.
		if ( self::$is_calculating ) {
			return;
		}

		self::$is_calculating = true;
		self::$price_cache = array(); // Limpiar caché al inicio

		// Agrupar ítems por producto padre (para variaciones) o por producto (para simples)
		$grouped_items = array();
		
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) {
				continue;
			}

			/** @var WC_Product $product */
			$product = $cart_item['data'];
			$quantity = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;

			// Validar que la cantidad sea razonable (evitar valores extremos).
			if ( $quantity <= 0 || $quantity > 999999 ) {
				continue;
			}

			// Determinar el ID del producto padre para agrupar
			$parent_id = 0;
			if ( $product->is_type( 'variation' ) ) {
				$parent_id = absint( $product->get_parent_id() );
			} else {
				$parent_id = absint( $product->get_id() );
			}

			if ( $parent_id <= 0 ) {
				continue;
			}

			// Agrupar por producto padre
			if ( ! isset( $grouped_items[ $parent_id ] ) ) {
				$grouped_items[ $parent_id ] = array();
			}

			$grouped_items[ $parent_id ][] = array(
				'cart_item_key' => $cart_item_key,
				'cart_item' => $cart_item,
				'product' => $product,
				'quantity' => $quantity,
			);
		}

		// Procesar cada grupo
		foreach ( $grouped_items as $parent_id => $items ) {
			// Calcular cantidad total del grupo
			$total_quantity = 0;
			foreach ( $items as $item_data ) {
				$total_quantity += $item_data['quantity'];
			}

			if ( $total_quantity <= 0 ) {
				continue;
			}

			// Verificar si ya tenemos el precio en caché
			$unit_price = null;
			if ( isset( self::$price_cache[ $parent_id ][ $total_quantity ] ) ) {
				$unit_price = self::$price_cache[ $parent_id ][ $total_quantity ];
			} else {
				// Obtener precio unitario basado en la suma total
				// Para variaciones, usar el parent_id; para simples, usar su propio ID
				$unit_price = WPDM_Price_Tiers::get_price_from_tiers( $parent_id, $total_quantity );
				
				// Guardar en caché
				if ( ! isset( self::$price_cache[ $parent_id ] ) ) {
					self::$price_cache[ $parent_id ] = array();
				}
				self::$price_cache[ $parent_id ][ $total_quantity ] = $unit_price;
			}

			if ( null === $unit_price || $unit_price <= 0 ) {
				// Si no hay tramos, continuar con el siguiente grupo
				continue;
			}

			// Aplicar el mismo precio unitario a todos los ítems del grupo
			foreach ( $items as $item_data ) {
				$cart_item_key = $item_data['cart_item_key'];
				$cart_item = $item_data['cart_item'];
				$product = $item_data['product'];
				$quantity = $item_data['quantity'];

				// Verificar si el precio ya está aplicado y es el correcto
				$current_tier_price = isset( $cart_item['wpdm_tier_price'] ) ? floatval( $cart_item['wpdm_tier_price'] ) : 0;
				$current_tier_qty = isset( $cart_item['wpdm_tier_qty'] ) ? absint( $cart_item['wpdm_tier_qty'] ) : 0;

				// Solo actualizar si el precio o la cantidad total han cambiado
				if ( abs( $current_tier_price - $unit_price ) > 0.01 || $current_tier_qty !== $total_quantity ) {
					// Aplicar el precio unitario calculado
					$product->set_price( $unit_price );
					$product->set_regular_price( $unit_price );
					
					// También establecer el precio de venta si existe
					if ( method_exists( $product, 'set_sale_price' ) ) {
						$product->set_sale_price( '' ); // Limpiar precio de venta para usar el precio regular
					}

					// Guardar información del tramo aplicado en el ítem del carrito
					$cart->cart_contents[ $cart_item_key ]['wpdm_tier_price'] = $unit_price;
					$cart->cart_contents[ $cart_item_key ]['wpdm_tier_qty'] = $total_quantity; // Guardar cantidad total para referencia
					$cart->cart_contents[ $cart_item_key ]['wpdm_tier_total_qty'] = $total_quantity; // Cantidad total del grupo
					
					// Recalcular precio de personalización si existe
					if ( isset( $cart->cart_contents[ $cart_item_key ]['wpdm_customization'] ) ) {
						$customization_data = $cart->cart_contents[ $cart_item_key ]['wpdm_customization'];
						$customization_price = WPDM_Customization::calculate_total_customization_price( $customization_data, $total_quantity );
						$cart->cart_contents[ $cart_item_key ]['wpdm_customization_price'] = $customization_price['total'];
					}
					
					// Actualizar el objeto del producto en el carrito
					$cart->cart_contents[ $cart_item_key ]['data'] = $product;
				}
			}
		}

		self::$is_calculating = false;
	}

	/**
	 * Restaurar precio del tramo desde la sesión del carrito.
	 *
	 * @param array $cart_item
	 * @param array $values
	 * @param string $cart_item_key
	 *
	 * @return array
	 */
	public static function get_cart_item_from_session( $cart_item, $values, $cart_item_key ) {
		// Siempre recalcular el precio basado en la cantidad actual del carrito
		// Esto asegura que si la cantidad cambió, el precio se actualice
		if ( isset( $cart_item['data'] ) && is_a( $cart_item['data'], 'WC_Product' ) ) {
			$product = $cart_item['data'];
			$product_id = absint( $product->get_id() );
			$quantity = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;
			
			// Si tenemos un precio guardado y la cantidad no ha cambiado, usarlo
			if ( isset( $values['wpdm_tier_price'] ) && isset( $values['wpdm_tier_qty'] ) && 
			     $values['wpdm_tier_qty'] == $quantity && $values['wpdm_tier_price'] > 0 ) {
				$unit_price = floatval( $values['wpdm_tier_price'] );
			} else {
				// Recalcular basado en la cantidad actual
				$unit_price = WPDM_Price_Tiers::get_price_from_tiers( $product_id, $quantity );
				
				if ( null === $unit_price || $unit_price <= 0 ) {
					return $cart_item; // No modificar si no podemos calcular
				}
			}
			
			// Aplicar el precio al producto
			$product->set_price( $unit_price );
			$product->set_regular_price( $unit_price );
			
			// Guardar en el ítem del carrito
			$cart_item['wpdm_tier_price'] = $unit_price;
			$cart_item['wpdm_tier_qty']   = $quantity;
			$cart_item['data'] = $product;
		}

		return $cart_item;
	}

	/**
	 * Guardar precio del tramo en los datos del ítem del carrito.
	 *
	 * @param array $cart_item_data
	 * @param int   $product_id
	 * @param int   $variation_id
	 *
	 * @return array
	 */
	public static function store_tier_price_in_cart_item( $cart_item_data, $product_id, $variation_id ) {
		$target_product_id = $variation_id > 0 ? $variation_id : $product_id;
		
		// Obtener cantidad del request - puede venir de diferentes lugares
		$quantity = 1;
		if ( isset( $_REQUEST['quantity'] ) ) {
			$quantity = absint( $_REQUEST['quantity'] );
		} elseif ( isset( $_POST['quantity'] ) ) {
			$quantity = absint( $_POST['quantity'] );
		} elseif ( isset( $_GET['quantity'] ) ) {
			$quantity = absint( $_GET['quantity'] );
		}
		
		// Si no hay cantidad, usar 1 por defecto
		if ( $quantity <= 0 ) {
			$quantity = 1;
		}
		
		$unit_price = WPDM_Price_Tiers::get_price_from_tiers( $target_product_id, $quantity );

		if ( null !== $unit_price && $unit_price > 0 ) {
			$cart_item_data['wpdm_tier_price'] = $unit_price;
			$cart_item_data['wpdm_tier_qty']   = $quantity;
		}

		// Guardar datos de personalización si existen
		if ( isset( $_POST['wpdm_customization'] ) || isset( $_REQUEST['wpdm_customization'] ) ) {
			$customization_json = isset( $_POST['wpdm_customization'] ) ? $_POST['wpdm_customization'] : $_REQUEST['wpdm_customization'];
			$customization_data = json_decode( stripslashes( $customization_json ), true );
			
			if ( is_array( $customization_data ) && ! empty( $customization_data ) ) {
				// Calcular precio de personalización
				$customization_price = WPDM_Customization::calculate_total_customization_price( $customization_data, $quantity );
				
				$cart_item_data['wpdm_customization'] = $customization_data;
				$cart_item_data['wpdm_customization_price'] = $customization_price['total'];
			}
		}

		return $cart_item_data;
	}

	/**
	 * Actualizar precio cuando cambia la cantidad en el carrito.
	 *
	 * @param string $cart_item_key
	 * @param int    $quantity
	 * @param int    $old_quantity
	 * @param object $cart
	 */
	public static function update_price_on_quantity_change( $cart_item_key, $quantity, $old_quantity, $cart ) {
		if ( ! isset( $cart->cart_contents[ $cart_item_key ] ) ) {
			return;
		}

		$cart_item = $cart->cart_contents[ $cart_item_key ];
		
		if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) {
			return;
		}

		$product  = $cart_item['data'];
		$product_id = absint( $product->get_id() );

		if ( $product_id <= 0 ) {
			return;
		}

		$unit_price = WPDM_Price_Tiers::get_price_from_tiers( $product_id, $quantity );

		if ( null !== $unit_price && $unit_price > 0 ) {
			$product->set_price( $unit_price );
			$product->set_regular_price( $unit_price );
			
			$cart->cart_contents[ $cart_item_key ]['wpdm_tier_price'] = $unit_price;
			$cart->cart_contents[ $cart_item_key ]['wpdm_tier_qty']   = $quantity;
			$cart->cart_contents[ $cart_item_key ]['data'] = $product;
		}
	}

	/**
	 * Mostrar el precio correcto del ítem en el carrito.
	 *
	 * @param string $price_html
	 * @param array  $cart_item
	 * @param string $cart_item_key
	 *
	 * @return string
	 */
	public static function display_cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		if ( isset( $cart_item['wpdm_tier_price'] ) && $cart_item['wpdm_tier_price'] > 0 ) {
			$price_html = wc_price( $cart_item['wpdm_tier_price'] );
		}

		return $price_html;
	}

	/**
	 * Mostrar el subtotal correcto del ítem en el carrito.
	 *
	 * @param string $subtotal_html
	 * @param array  $cart_item
	 * @param string $cart_item_key
	 *
	 * @return string
	 */
	public static function display_cart_item_subtotal( $subtotal_html, $cart_item, $cart_item_key ) {
		$tier_price = isset( $cart_item['wpdm_tier_price'] ) ? floatval( $cart_item['wpdm_tier_price'] ) : 0;
		$quantity   = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 0;
		$customization_price = isset( $cart_item['wpdm_customization_price'] ) ? floatval( $cart_item['wpdm_customization_price'] ) : 0;
		
		if ( $tier_price > 0 && $quantity > 0 ) {
			$subtotal = ( $tier_price * $quantity ) + $customization_price;
			$subtotal_html = wc_price( $subtotal );
		}

		return $subtotal_html;
	}
}


