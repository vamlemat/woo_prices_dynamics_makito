## Woo Prices Dynamics Makito

Versión estable: **3.10.4**

Plugin para WooCommerce que añade precios por tramos, tabla rápida de variaciones y flujo de personalización para productos sincronizados desde Makito u otro panel externo.

---

### Objetivo

- Leer tramos de precio desde el meta `price_tiers`.
- Aplicar el precio unitario correcto en producto, carrito y checkout.
- Mostrar una tabla de cantidades por color/talla para productos variables.
- Permitir añadir productos con o sin personalización.
- Enviar la personalización a una página dedicada: `/personalizar/{slug}/`.
- Guardar datos de precio, tramo y personalización en carrito y pedido.

---

### Requisitos

- WordPress 5.0 o superior.
- WooCommerce 3.0 o superior.
- PHP 7.2 o superior.
- Productos WooCommerce simples o variables.
- Campos sincronizados mediante JetEngine, panel externo o importador propio.

Campos principales esperados:

- `price_tiers`: tramos de precio por cantidad.
- `marking_areas`: áreas disponibles para marcaje.
- Técnicas de marcación en el CPT correspondiente, enlazadas por `technique_ref`.
- Para productos variables: atributos `pa_color` y `pa_talla`.

La estructura completa de producto está documentada en `includes/GUIA_CREAR_PRODUCTO_FINAL_COMPLETO.md`.

---

### Funcionalidades

#### Precios por tramos

- Normaliza y ordena los tramos definidos en `price_tiers`.
- Calcula el precio unitario aplicable según la cantidad total.
- Aplica el precio real en carrito y checkout.
- Muestra una banda responsive de precios por cantidad en la ficha de producto.
- Shortcode disponible: `[wpdm_price_tiers_table]`.

#### Tabla de variaciones

- Muestra una matriz de cantidades por color y talla.
- Calcula el precio usando la suma total de todas las variaciones seleccionadas.
- Permite añadir al carrito sin personalizar.
- Permite continuar hacia personalización con las cantidades seleccionadas.
- Shortcode disponible: `[wpdm_variation_table]`.

#### Áreas de marcaje

- Muestra las áreas de impresión definidas en `marking_areas`.
- Agrupa varias técnicas bajo la misma área física.
- Incluye número de áreas, códigos de impresión, posición, medida máxima, técnicas, límite de colores e imagen.
- Diseño responsive inspirado en catálogos B2B de producto.
- Shortcode disponible: `[wpdm_marking_areas]`.

#### Personalización

- Botón “Añadir con personalización” en la ficha de producto.
- URL dedicada: `/personalizar/{slug}/`.
- Transferencia de cantidades mediante `sessionStorage`.
- Soporta modo global y modo por color.
- Permite seleccionar áreas, técnica, número de colores, medidas, PANTONE, archivo y observaciones.
- Calcula la cotización de personalización en tiempo real.
- Añade fees de personalización al carrito sin alterar el subtotal base del producto.

#### Carrito y pedido

- Agrupa visualmente variaciones del mismo producto.
- Separa productos con distintas personalizaciones.
- Bloquea cambios de cantidad en productos personalizados para evitar inconsistencias.
- Guarda los metadatos de tramo y personalización en los ítems de pedido.
- Incluye metabox de administración para consultar la personalización e imágenes subidas.

#### Administración

Apartado propio en el backend: **Makito**.

Subpáginas disponibles:

- **Makito → Ajustes**
- **Makito → Shortcodes**
- **Makito → Imágenes Personalización**
- **Makito → Logs**

La página **Makito → Ajustes** incluye opciones para:

- Mostrar u ocultar la tabla de tramos.
- Activar la tabla de variaciones.
- Configurar tamaño de swatches de color.
- Ajustar umbral y colores visuales de stock.

---

### Estructura principal

- `woo-prices-dynamics-makito.php`: archivo principal, versión, carga de clases, requisitos y activación.
- `includes/class-wpdm-price-tiers.php`: lectura, normalización y cálculo de tramos.
- `includes/class-wpdm-cart-adjustments.php`: aplicación de precios por tramo en carrito.
- `includes/class-wpdm-frontend.php`: precio dinámico y banda visual de tramos.
- `includes/class-wpdm-variation-table.php`: tabla de color/talla y AJAX de carrito.
- `includes/class-wpdm-marking-areas.php`: shortcode visual de áreas de marcaje.
- `includes/class-wpdm-customization-frontend.php`: botón, rutas y carga de la página `/personalizar/{slug}/`.
- `includes/class-wpdm-customization.php`: cálculo, subida de imágenes, fees, carrito y pedido.
- `includes/templates/customization-page.php`: interfaz dedicada de personalización.
- `includes/class-wpdm-admin-settings.php`: ajustes de administración.
- `includes/class-wpdm-customization-images-admin.php`: gestión de imágenes subidas.
- `includes/class-wpdm-order-meta.php`: metadatos de tramos en pedido.
- `includes/class-wpdm-logger.php`: logging opcional.

---

### Instalación

1. Copiar la carpeta del plugin en `wp-content/plugins/`.
2. Activar **Woo Prices Dynamics Makito** desde el panel de WordPress.
3. Ir a **Makito → Ajustes** y revisar las opciones.
4. Guardar enlaces permanentes si la URL `/personalizar/{slug}/` no responde tras la activación.
5. Confirmar que los productos tengan `price_tiers`, variaciones y `marking_areas` cuando corresponda.

---

### Shortcodes

```text
[wpdm_price_tiers_table]
[wpdm_price_tiers_table product_id="123"]

[wpdm_variation_table]
[wpdm_variation_table product_id="123"]

[wpdm_marking_areas]
[wpdm_marking_areas product_id="123"]
[wpdm_marking_areas title="Marcaje"]
```

Los shortcodes pueden usarse en plantillas, tabs, widgets o constructores visuales. Si las opciones automáticas están activadas, no hace falta insertarlos manualmente en la ficha de producto.

---

### Seguridad

- Bloqueo de acceso directo en archivos PHP.
- Inicialización condicionada a WooCommerce activo.
- Compatibilidad declarada con HPOS.
- Sanitización y casteo de datos internos.
- Escapado de HTML en salidas públicas.
- Validación de nonces en acciones AJAX.
- Restricción de tipos/tamaño en subida de archivos de personalización.

---

### Estado

La versión **3.10.4** se considera estable según las pruebas funcionales actuales y lista para subir a GitHub. Se recomienda una revisión más profunda en entorno real antes de una release final de producción.
