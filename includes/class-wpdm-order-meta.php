<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestión de metadatos de tramo en los ítems de pedido.
 */
class WPDM_Order_Meta {

	/**
	 * Registrar hooks relacionados con pedidos.
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_tier_meta_to_order_item' ), 20, 4 );
	}

	/**
	 * Añadir información del tramo aplicado al ítem de pedido.
	 *
	 * @param WC_Order_Item_Product $item
	 * @param string                $cart_item_key
	 * @param array                 $values
	 * @param WC_Order              $order
	 */
	public static function add_tier_meta_to_order_item( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['data'] ) || ! is_a( $values['data'], 'WC_Product' ) ) {
			return;
		}

		$product    = $values['data'];
		$product_id = absint( $product->get_id() );
		$quantity   = absint( $item->get_quantity() );

		if ( $product_id <= 0 || $quantity <= 0 ) {
			return;
		}

		// Validar que el producto sea válido.
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$tiers = WPDM_Price_Tiers::get_price_tiers( $product_id );

		if ( empty( $tiers ) ) {
			return;
		}

		// Encontrar el tramo para la cantidad del pedido.
		$selected = null;

		foreach ( $tiers as $tier ) {
			$from = isset( $tier['qty_from'] ) ? (int) $tier['qty_from'] : 0;
			$to   = isset( $tier['qty_to'] ) ? (int) $tier['qty_to'] : 0;

			if ( $quantity >= $from && ( 0 === $to || $quantity <= $to ) ) {
				$selected = $tier;
			}
		}

		if ( null === $selected ) {
			$selected = $tiers[0];
		}

		// Guardar metadatos del tramo en el ítem de pedido (sanitizados).
		$item->add_meta_data( '_wpdm_tier_qty_from', isset( $selected['qty_from'] ) ? absint( $selected['qty_from'] ) : 0, true );
		$item->add_meta_data( '_wpdm_tier_qty_to', isset( $selected['qty_to'] ) ? absint( $selected['qty_to'] ) : 0, true );
		$item->add_meta_data( '_wpdm_tier_unit_price', isset( $selected['unit_price'] ) ? floatval( $selected['unit_price'] ) : 0.0, true );

		if ( ! empty( $selected['currency'] ) ) {
			$currency = sanitize_text_field( substr( (string) $selected['currency'], 0, 10 ) );
			if ( ! empty( $currency ) ) {
				$item->add_meta_data( '_wpdm_tier_currency', $currency, true );
			}
		}

		if ( ! empty( $selected['source'] ) ) {
			$source = sanitize_text_field( substr( (string) $selected['source'], 0, 50 ) );
			if ( ! empty( $source ) ) {
				$item->add_meta_data( '_wpdm_tier_source', $source, true );
			}
		}
	}
}



