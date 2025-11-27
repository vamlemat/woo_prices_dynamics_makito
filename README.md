## Woo Prices Dynamics Makito

Plugin de soporte para **precios por tramos** en WooCommerce, pensado para integrarse con la estructura descrita en `publicmar-estructura` y un **panel externo** que sincroniza los tramos (`price_tiers`) en cada producto.

---

### 🎯 Objetivo

- Leer los tramos de precio definidos en el campo meta `price_tiers` (Meta Box de JetEngine).
- Aplicar esos tramos como **precio real en el carrito y checkout** de WooCommerce.
- Actualizar el precio mostrado en la **ficha de producto** cuando el usuario cambia la cantidad.

---

### 🧱 Requisitos

- WordPress + WooCommerce.
- Estructura de producto con JetEngine tal como se describe en:
  - `GUIA_ESTRUCTURA_FRONTEND.md` → Meta Box `Datos del Producto` → repeater `price_tiers`.
- Un panel externo (o script) que rellene el repeater `price_tiers` con la estructura:
  - `qty_from` (int)
  - `qty_to` (int, 0 = sin límite)
  - `unit_price` (float)
  - `currency` (string, opcional)
  - `source` (string, opcional)

---

### ⚙️ Estructura del plugin

- `woo-prices-dynamics-makito.php`  
  Archivo principal, define constantes, carga clases y comprueba que WooCommerce esté activo.

- `includes/class-wpdm-price-tiers.php`  
  - `WPDM_Price_Tiers::get_price_tiers( $product_id )`  
    Devuelve los tramos normalizados y ordenados por `qty_from`.
  - `WPDM_Price_Tiers::get_price_from_tiers( $product_id, $quantity )`  
    Devuelve el **precio unitario** aplicable para una cantidad dada.

- `includes/class-wpdm-cart-adjustments.php`  
  - Hook `woocommerce_before_calculate_totals` → recalcula los precios de los ítems del carrito según `price_tiers`.

- `includes/class-wpdm-frontend.php`  
  - Hook `wp_footer` → imprime un script JS que actualiza el precio de la ficha de producto según la cantidad.
  - Hook `woocommerce_single_product_summary` → muestra opcionalmente la tabla de tramos debajo del precio (si está activada en ajustes).
  - Shortcode `[wpdm_price_tiers_table]` → permite mostrar la tabla de tramos en cualquier lugar (tabs, widgets, plantillas, etc.).

- `includes/class-wpdm-order-meta.php`  
  - Hook `woocommerce_checkout_create_order_line_item` → guarda en cada ítem de pedido los datos del tramo aplicado (qty_from, qty_to, unit_price, currency, source).

- `includes/class-wpdm-admin-settings.php`  
  - Añade una página de ajustes bajo **WooCommerce → Precios Makito**.
  - Opción: **“Mostrar tabla de tramos en ficha de producto”** (activa/desactiva la tabla automática).

---

### 🔐 Notas de seguridad y buenas prácticas

- Se bloquea el acceso directo a los archivos (`if ( ! defined( 'ABSPATH' ) ) exit;`).
- Solo se ejecuta la lógica principal si WooCommerce está activo (`class_exists( 'WooCommerce' )`).
- Los datos de `price_tiers` se consideran **internos** (provenientes del panel y de JetEngine), pero se:
  - Normalizan y se hace cast de tipos (`(int)`, `(float)`).
  - Se sanitan textos con `sanitize_text_field` para `currency` y `source`.
- No se ejecutan consultas SQL directas ni se procesan datos de usuario sin sanitizar.
- La tabla de tramos usa `wc_price()` y funciones de escape (`esc_html`, `wp_kses_post`) para evitar problemas de XSS en HTML.

---

### 🚀 Instalación

1. Copiar la carpeta `woo_prices_dynamics_makito` dentro de `wp-content/plugins/` en tu instalación de WordPress.
2. Activar el plugin desde **Plugins → Plugins instalados**.
3. Asegurarse de que los productos que deben usar tramos tienen el repeater `price_tiers` relleno (panel externo).

---

### ✅ Próximos pasos posibles

- Guardar en los ítems de pedido información del tramo aplicado (para verla en el backoffice).
- Añadir shortcodes o bloques para mostrar una **tabla de tramos** en la ficha de producto.
- Integrar más lógica de negocio del panel externo (sincronización, logs, etc.) dentro de este mismo plugin.


