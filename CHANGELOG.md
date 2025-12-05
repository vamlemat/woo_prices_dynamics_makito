# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

## [3.4.3] - 2025-12-04

### 🐛 Fix - Detalles no visibles aunque estén en el DOM

**Problema:**
- El contenido de detalles está en el DOM (visible en inspector)
- Pero no se muestra visualmente al hacer click
- El contenedor padre tiene `overflow: hidden` que corta el contenido

**Solución:**
- ✅ Cambiado `overflow: hidden` a `overflow: visible` en el contenedor principal
- ✅ Sistema de clases CSS (`.wpdm-details-hidden` / `.wpdm-details-visible`) en lugar de estilos inline
- ✅ Asegurar que contenedores padres no tengan `overflow: hidden`
- ✅ CSS con `!important` para forzar visibilidad cuando está activo

**Mejoras técnicas:**
- Clases CSS dedicadas para estado visible/oculto
- Mejor control sobre overflow de contenedores
- Logs mejorados para debug

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Contenedor sin overflow hidden (línea ~1637)
  - Sistema de clases CSS para detalles (línea ~1740)
  - Toggle usando clases en lugar de estilos inline (línea ~1494)
  - CSS con clases dedicadas (línea ~2020)
- `woo-prices-dynamics-makito.php` (v3.4.3)
- `CHANGELOG.md`

---

## [3.4.2] - 2025-12-04

### 🐛 Fixes críticos

**Problema 1: Botón "Ver detalles" no funciona**
- ❌ `slideDown()` no funcionaba porque el CSS tenía `display: none !important`
- ✅ Cambiado a usar `.css('display', 'block')` directamente
- ✅ Añadidos estilos `visibility` y `opacity` para asegurar visibilidad
- ✅ Mejorado el manejo de ocultar/mostrar

**Problema 2: Cantidades no se muestran**
- ❌ El código estaba quitando el texto "Cantidad fija" pero también el número
- ✅ Corregido para mantener el número de cantidad visible
- ✅ Si no hay cantidad visible, se crea un span con el número
- ✅ Mejorado el selector para encontrar el valor de cantidad

**Problema 3: Botón eliminar solo elimina una variación**
- ❌ Las eliminaciones en paralelo causaban problemas
- ✅ Cambiado a eliminación secuencial con delays
- ✅ Mejor logging para debug
- ✅ Busca correctamente los items originales (incluso si están ocultos)
- ✅ Recarga automática después de eliminar todas

**Mejoras técnicas:**
- Uso directo de `.css()` en lugar de animaciones jQuery
- Eliminación secuencial con `setTimeout` para evitar conflictos
- Mejor manejo de valores de cantidad
- Logs más detallados para debug

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Toggle de detalles usando `.css()` directamente (línea ~1496)
  - Cantidad mejorada para mantener el número (línea ~1703)
  - Eliminación secuencial corregida (línea ~1822)
- `woo-prices-dynamics-makito.php` (v3.4.2)
- `CHANGELOG.md`

---

## [3.4.1] - 2025-12-04

### 🎨 Mejoras visuales y fixes

**Mejoras visuales:**
- ✅ **Quitado "🔒 Cantidad fija (personalizado)"** de cada ficha individual
- ✅ **Añadido después de "Personalización GLOBAL"** - ahora solo aparece una vez
- ✅ **Fuente más grande** para precio y cantidad en las fichas (0.95em)
- ✅ **Altura reducida** de cada variación (padding: 6px 8px, antes 8px 10px)
- ✅ Cards más compactas y elegantes

**Fixes funcionales:**
- ✅ **Botón "Ver detalles"** - añadidos logs extensivos para debug
- ✅ **Botón eliminar** corregido - ahora elimina TODAS las variaciones
- ✅ **Recarga automática** después de eliminar - actualiza la visualización
- ✅ Mejor manejo de errores en eliminación

**Mejoras técnicas:**
- Logs detallados en consola para debug del toggle
- Eliminación en paralelo de todas las variaciones
- Verificación de que se encontraron items antes de eliminar
- Recarga automática después de eliminar todas las variaciones

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Quitado texto "Cantidad fija" de cards (línea ~1649)
  - Añadido después de "Personalización GLOBAL" (línea ~1708)
  - Fuente más grande para precio/cantidad (línea ~1665)
  - Padding reducido en cards (línea ~1649)
  - Logs extensivos en toggle (línea ~1469)
  - Eliminación corregida (línea ~1787)
- `woo-prices-dynamics-makito.php` (v3.4.1)
- `CHANGELOG.md`

---

## [3.4.0] - 2025-12-04

### ✨ Mejoras visuales y funcionales

**Cambios visuales:**
- ✅ **3 columnas** en lugar de 2 para mejor aprovechamiento del espacio
- ✅ **Eliminadas las X** de cada card de variación
- ✅ **Importe total** mostrado en lugar de las X (destacado en azul)
- ✅ **Botón "Eliminar ✕"** en el header junto al nombre del producto
- ✅ Mejor organización visual con más variaciones visibles

**Funcionalidad:**
- ✅ **Botón "Eliminar ✕"** elimina TODAS las variaciones del producto de una vez
- ✅ **Botón "Ver detalles"** corregido - ahora funciona correctamente
- ✅ Detalles se cargan siempre via AJAX para asegurar que funcionen
- ✅ Mejor manejo de errores si los detalles no se pueden cargar

**Mejoras técnicas:**
- Grid de 3 columnas: `grid-template-columns: repeat(3, 1fr)`
- Botón eliminar todo con confirmación
- Toggle de detalles mejorado con mejor logging
- AJAX siempre intenta cargar detalles, con fallback al HTML si falla

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Grid de 3 columnas (línea ~1600)
  - Botón eliminar en header (línea ~1574)
  - Importe en lugar de X (línea ~1648)
  - Toggle de detalles mejorado (línea ~1440)
  - AJAX siempre carga detalles (línea ~1708)
- `woo-prices-dynamics-makito.php` (v3.4.0)
- `CHANGELOG.md`

---

## [3.3.9] - 2025-12-04

### 🐛 Fix - Precio y detalles + Layout optimizado

**Problema 1: Precio de personalización aparece como 0,00€**
- ❌ El precio no se obtenía correctamente del fee "Personalización GLOBAL"
- ✅ Ahora busca primero en los fees del carrito, luego en el item

**Problema 2: Detalles no se muestran**
- ❌ Los detalles no se encontraban en el HTML
- ✅ Ahora busca en múltiples ubicaciones: HTML oculto, item_data, nombre del producto

**Problema 3: Padding excesivo**
- ❌ El padding era demasiado grande, causando scroll infinito
- ✅ Reducido padding de 15-20px a 8-10px
- ✅ Márgenes reducidos para mejor agrupación

**Problema 4: Layout de una columna**
- ❌ Las variaciones ocupaban demasiado espacio vertical
- ✅ **Layout de dos columnas** para mostrar más variaciones en menos espacio
- ✅ Cards compactas con información esencial
- ✅ Diseño responsive y elegante

**Mejoras visuales:**
- Header del producto: padding reducido (10px 15px)
- Variaciones: grid de 2 columnas con gap de 8px
- Cards de variación: padding 8px 10px, bordes sutiles
- Thumbnails: 40x40px (antes 50x50px)
- Personalización: padding reducido (10px 15px)
- Botones: tamaño reducido para mejor proporción

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Mejorada obtención de precio desde fees (línea ~1537)
  - Mejorada obtención de detalles desde múltiples fuentes (línea ~1556)
  - Layout de dos columnas para variaciones (línea ~1574)
  - Padding y márgenes reducidos en todo el componente
- `woo-prices-dynamics-makito.php` (v3.3.9)
- `CHANGELOG.md`

---

## [3.3.8] - 2025-12-04

### ✨ Rediseño completo - Agrupación visual mejorada

**Nueva estructura visual para productos personalizados en modo global:**

```
┌─────────────────────────────────────────┐
│ Seiyo                                    │ ← Título del producto
├─────────────────────────────────────────┤
│ [Imagen] Variación 1 | Precio | Qty | Total | [X] │
│ [Imagen] Variación 2 | Precio | Qty | Total | [X] │
│ [Imagen] Variación 3 | Precio | Qty | Total | [X] │
├─────────────────────────────────────────┤
│ Personalización GLOBAL: 165,00 € [Ver detalles ▼] │
│ └─ [Detalles expandibles al hacer click]          │
└─────────────────────────────────────────┘
```

**Características:**
- ✅ **Título del producto** como header azul destacado
- ✅ **Tabla de variaciones** con todas las variaciones listadas
- ✅ **Una sola línea de personalización** con importe total
- ✅ **Botón "Ver detalles"** que despliega toda la información
- ✅ **Eliminación de grupo completo** al hacer click en X
- ✅ **Diseño responsive** y moderno

**Mejoras técnicas:**
- JavaScript reorganiza automáticamente los items del carrito
- Obtiene correctamente el nombre del producto y precio de personalización
- Extrae detalles de personalización del primer item
- Funciona con cualquier template (Elementor, WooCommerce Blocks, etc.)

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Función `reorganizeCartItems()` completamente rediseñada (línea ~1499)
  - Estructura HTML mejorada con header, tabla de variaciones y personalización
  - JavaScript mejorado para interceptar eliminación en grupos reorganizados
- `woo-prices-dynamics-makito.php` (v3.3.8)
- `CHANGELOG.md`

---

## [3.3.7] - 2025-12-04

### 🐛 Fix - Personalización no visible + Eliminación de grupo completo

**Problema 1: Personalización no aparece en carrito (Elementor)**
- ❌ El filtro `woocommerce_get_item_data` no funciona con templates de Elementor
- ❌ No aparece el botón "Ver detalles" ni la información de personalización

**Problema 2: Agrupamiento visual no se ve**
- ❌ Aunque las clases CSS están aplicadas, el efecto visual no es visible

**Problema 3: Eliminación parcial**
- ❌ Al eliminar una variación, solo se elimina esa variación
- ✅ Debe eliminar TODO el grupo de variaciones en modo global

**Solución:**

1. **Hook alternativo para Elementor:**
   - Añadido `woocommerce_cart_item_name` para inyectar personalización directamente en el nombre del producto
   - Funciona con cualquier template (Elementor, WooCommerce Blocks, etc.)
   - La personalización aparece después del nombre del producto

2. **CSS mejorado para agrupamiento:**
   - Bordes azules más visibles (3px en todos los lados)
   - Fondo azul claro (#f8f9ff) para destacar el grupo
   - Sombras sutiles para efecto de elevación
   - Espaciado mejorado entre grupos

3. **Eliminación de grupo completo:**
   - JavaScript intercepta el click en botón de eliminar
   - Detecta si hay múltiples variaciones del mismo producto
   - Muestra confirmación: "¿Deseas eliminar todas las variaciones del grupo?"
   - Si confirma, elimina TODAS las variaciones del grupo
   - Recarga el carrito automáticamente

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Añadido hook `woocommerce_cart_item_name` (línea ~36)
  - Función `add_customization_to_cart_item_name()` (línea ~1270)
  - CSS mejorado para agrupamiento (línea ~1520)
  - JavaScript para eliminación de grupo (línea ~1500)
- `woo-prices-dynamics-makito.php` (v3.3.7)
- `CHANGELOG.md`

---

## [3.3.6] - 2025-12-04

### ✨ Mejora - Agrupación visual de variaciones en modo global

**Problema:**
En modo "global" con múltiples variaciones, aparecían 3 productos separados, cada uno con "Personalización: ✓ Sí | 150,00 €", lo que era confuso.

**Solución:**
- ✅ Agrupación visual: Los items del mismo producto en modo global se agrupan visualmente con bordes azules
- ✅ Personalización única: Solo se muestra la personalización en la PRIMERA variación del grupo
- ✅ Las demás variaciones no muestran la línea de personalización (pero están agrupadas visualmente)
- ✅ CSS añadido para crear un "bloque conjunto" visual

**Características:**
- Primera variación: borde superior azul grueso, esquinas redondeadas arriba
- Variaciones siguientes: sin borde superior, agrupadas
- Última variación: borde inferior azul grueso, esquinas redondeadas abajo
- Personalización solo visible en la primera variación con botón "Ver detalles"

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Función `is_first_customized_item_in_group()` para detectar primera variación (línea ~1212)
  - Función `add_cart_item_class()` para añadir clases CSS (línea ~1250)
  - CSS para agrupación visual (línea ~1340)
  - Modificado `display_customization_in_cart()` para ocultar personalización en variaciones siguientes (línea ~1164)
- `woo-prices-dynamics-makito.php` (v3.3.6)
- `CHANGELOG.md`

---

## [3.3.5] - 2025-12-04

### 🐛 Fix CRÍTICO - Subtotal incorrecto + Fee duplicado en modo global

**Problema 1: Subtotal del producto incorrecto**
- ❌ Mostraba: 1,32€ × 10 + 150€ = 163,20€
- ✅ Debe mostrar: 1,32€ × 10 = 13,20€
- El precio de personalización NO debe sumarse al subtotal del producto (es un fee separado)

**Problema 2: Fees duplicados en modo global**
- ❌ Aparecían 3 fees: "Personalización Seiyo - GRI-GRIS, XXL (Gris): 150,00 €" × 3
- ✅ Debe aparecer: "Personalización GLOBAL: 150,00 €" (único)

**Solución:**

1. **Subtotal del producto:**
   - Eliminada la suma de `$customization_price` en `display_cart_item_subtotal()`
   - El subtotal ahora es solo: `precio_unitario × cantidad`
   - El precio de personalización se muestra como fee separado

2. **Nombre del fee en modo global:**
   - Si hay múltiples variaciones: "Personalización GLOBAL"
   - Si hay una sola variación: "Personalización [Nombre Producto]"
   - Solo se añade UN fee por producto en modo global

**Archivos modificados:**
- `includes/class-wpdm-cart-adjustments.php`:
  - Eliminada suma de customization_price del subtotal (línea ~347)
- `includes/class-wpdm-customization.php`:
  - Nombre del fee cambiado a "Personalización GLOBAL" cuando hay múltiples variaciones (línea ~1518)
- `woo-prices-dynamics-makito.php` (v3.3.5)
- `CHANGELOG.md`

---

## [3.3.4] - 2025-12-04

### 🐛 Fix CRÍTICO - Precio duplicado en modo "global" con múltiples variaciones

**Problema:**
Al añadir 3 variaciones en modo "global" con personalización de 103,69€:
- ❌ El precio se multiplicaba por 3: 103,69€ × 3 = 311,07€
- ❌ Se añadían 3 fees de personalización al carrito

**Causa:**
1. En `ajax_add_customized_to_cart`, se calculaba el precio para cada variación por separado
2. Se sumaban todos los precios: `$total_customization_price += $customization_price;`
3. En `add_customization_fees_to_cart`, se añadía un fee por cada item del carrito

**Solución:**
1. **En modo "global":**
   - Calcular el precio UNA VEZ usando la cantidad total de todas las variaciones
   - Guardar ese precio único en todas las variaciones
   - NO sumar el precio en el bucle (solo la primera vez)

2. **En `add_customization_fees_to_cart`:**
   - Agrupar items por producto y modo
   - En modo "global", añadir solo UN fee por producto (no por variación)
   - En modo "per-color", mantener un fee por variación

**Ejemplo:**
```
Antes:
- 3 variaciones × 103,69€ = 311,07€ ❌

Ahora:
- 1 cálculo global (103,69€) → 1 fee único = 103,69€ ✅
```

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Cálculo único en modo global (línea ~813)
  - Agrupación de fees por producto (línea ~1416)
- `woo-prices-dynamics-makito.php` (v3.3.4)
- `CHANGELOG.md`

---

## [3.3.3] - 2025-12-04

### 🗑️ Eliminado - Botón "Descargar todas las imágenes (ZIP)"

**Cambio:**
- ❌ Eliminado botón "📥 Descargar todas las imágenes (ZIP)" del metabox de pedidos
- ❌ Eliminado JavaScript relacionado (no funcionaba)
- ✅ Mantenido botón "📋 Copiar toda la información"
- ✅ Mantenidos botones individuales de descarga por imagen

**Razón:**
El botón no funcionaba correctamente y no era necesario, ya que cada imagen tiene su propio botón de descarga.

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Eliminado botón HTML (línea ~1616)
  - Eliminado JavaScript (línea ~1864)
- `woo-prices-dynamics-makito.php` (v3.3.3)
- `CHANGELOG.md`

---

## [3.3.2] - 2025-12-04

### 🐛 Fix - Título "Observaciones" eliminado + Campo "Nº de pedido" corregido

**1. Eliminado título "Observaciones:" de pestaña "Áreas":**
- ❌ Antes: Aparecía el título "Observaciones:" sin campo
- ✅ Ahora: Sección completa eliminada (título + campo)
- Las observaciones solo están en la pestaña "Diseño"

**2. Corregido selector del campo "Nº de pedido" (Repetición Cliché):**
- ❌ Antes: Selector inconsistente (`.wpdm-cliche-order-number` vs `.wpdm-area-cliche-order-number`)
- ✅ Ahora: Selector unificado (`.wpdm-area-cliche-order-number`)
- El campo se muestra/oculta correctamente al marcar/desmarcar "Repetición Cliché"
- El valor se guarda correctamente en el pedido

**Archivos modificados:**
- `includes/class-wpdm-customization-frontend.php`:
  - Eliminada sección completa de observaciones (línea ~592)
  - Corregido selector de cliché order number (líneas ~1497, ~1708)
- `woo-prices-dynamics-makito.php` (v3.3.2)
- `CHANGELOG.md`

---

## [3.3.1] - 2025-12-04

### 🐛 Fix - PANTONE, medidas y observaciones

**1. PANTONE ahora muestra código completo:**
- ❌ Antes: "Naranja"
- ✅ Ahora: "Orange 021 C"
- La paleta de colores ahora incluye códigos PANTONE completos
- Cuando se selecciona un color de la paleta, se guarda el código PANTONE (ej: "Orange 021 C")
- Si el usuario escribe un PANTONE personalizado, se guarda tal cual

**Paleta actualizada con códigos PANTONE:**
- Negro → Black C
- Naranja → Orange 021 C
- Azul Oscuro → Blue 286 C
- Verde → Green C
- etc.

**2. Medidas de impresión ahora se guardan:**
- ✅ Width y height ahora se incluyen en `areaData` cuando se añade al carrito
- ✅ Se muestran en el metabox del pedido si fueron modificadas
- ✅ Formato: "100,0 x 50,0 mm"

**3. Eliminado campo de observaciones de pestaña "Áreas":**
- ❌ Eliminado: Campo `.wpdm-area-observations` de la pestaña "Áreas"
- ✅ Mantenido: Campo `.wpdm-observations-input` de la pestaña "Diseño"
- Las observaciones solo se recopilan desde la pestaña "Diseño"

**Archivos modificados:**
- `includes/class-wpdm-customization-frontend.php`:
  - Paleta de colores con códigos PANTONE (línea ~973)
  - Guardar código PANTONE en lugar de nombre (línea ~1351)
  - Añadir width/height a areaData (línea ~1508)
  - Eliminar campo observaciones de pestaña "Áreas" (línea ~595)
- `woo-prices-dynamics-makito.php` (v3.3.1)
- `CHANGELOG.md`

---

## [3.3.0] - 2025-12-04

### ✨ Nueva funcionalidad - Panel de administración de imágenes

**Panel de gestión de imágenes de personalización:**
- Nuevo menú en WooCommerce: **"Imágenes Personalización"**
- Vista tipo biblioteca de medios con todas las imágenes subidas por clientes
- Estadísticas: total de imágenes y espacio utilizado
- Acciones masivas: seleccionar todas, eliminar seleccionadas
- Eliminación individual con confirmación
- Preview de imágenes (JPG, PNG, GIF)
- Información de cada archivo: nombre, tamaño, fecha de modificación
- Botones de acción: Ver, Descargar, Eliminar
- Diseño responsive y moderno

**Archivos creados:**
- `includes/class-wpdm-customization-images-admin.php` - Clase principal del panel
- `assets/css/wpdm-customization-images-admin.css` - Estilos del panel
- `assets/js/wpdm-customization-images-admin.js` - Funcionalidad JavaScript

**Características:**
- ✅ Vista en grid responsive
- ✅ Selección múltiple con checkboxes
- ✅ Eliminación masiva con confirmación
- ✅ Validación de seguridad (solo archivos del directorio permitido)
- ✅ Logging de acciones (WPDM Logger)
- ✅ Permisos: requiere `manage_woocommerce`

**Uso:**
1. Ve a **WooCommerce → Imágenes Personalización**
2. Verás todas las imágenes subidas por clientes
3. Selecciona las que quieras eliminar
4. Click en "Eliminar seleccionadas"

---

### 🐛 Fix - Metabox completo con TODA la información

**Problema:**
El metabox solo mostraba técnica, colores y observaciones, faltaba:
- ❌ Medidas (width/height) si fueron modificadas
- ❌ PANTONE real (código completo)
- ❌ Imágenes con preview

**Solución:**
- ✅ Añadido campo "Medidas de impresión" (si fueron modificadas)
- ✅ PANTONE ahora muestra el código completo (ej: "PANTONE 286 C")
- ✅ Imágenes con preview visual (si es JPG/PNG/GIF)
- ✅ Mejorado diseño de botones de descarga
- ✅ Información de archivo más clara

**Ejemplo de visualización:**
```
📐 Area 9
├─ Técnica: TAMPOGRAFÍA F
├─ Colores: 2
├─ Medidas: 100,0 x 50,0 mm  ← NUEVO
├─ PANTONE: PANTONE 286 C, PANTONE 123 C  ← MEJORADO
├─ 📸 Archivo: [Ver] [Descargar] + Preview  ← MEJORADO
└─ Observaciones: ...
```

**Archivos modificados:**
- `includes/class-wpdm-customization.php` (líneas ~1663-1714)

---

## [3.2.5] - 2025-12-04

### 🐛 Fix CRÍTICO - Metabox vacío (deserialización de JSON)

**Problema:**
El metabox aparecía vacío mostrando solo el nombre del producto, sin los detalles de personalización.

**Causa raíz:**
Los logs revelaron que los datos se guardaban como **JSON string** en lugar de **array PHP**:

```json
"customization_type": "string",  ❌
"has_areas": false,
"areas_count": 0
```

Pero los datos SÍ estaban ahí:
```json
"customization_structure": "{\"mode\":\"global\",\"areas\":[...]}"
```

**Por qué pasaba:**
WooCommerce serializa automáticamente los datos complejos. Al leerlos con `get_meta()`, devuelve el string JSON original sin deserializar.

**Solución:**
Añadida deserialización automática en `render_order_customization_metabox()`:

```php
$customization = $item->get_meta( '_wpdm_customization', true );

// CRÍTICO: Deserializar si es string JSON
if ( is_string( $customization ) && ! empty( $customization ) ) {
    $decoded = json_decode( $customization, true );
    if ( json_last_error() === JSON_ERROR_NONE ) {
        $customization = $decoded;
    }
}
```

**Resultado:**
- ✅ El metabox ahora muestra TODOS los detalles
- ✅ Áreas, técnicas, PANTONE, imágenes, observaciones
- ✅ Botones de descarga funcionan
- ✅ Botón copiar texto funciona

**Logs mejorados:**
Ahora el debug info incluye:
- `customization_is_array` - tipo de dato
- `has_areas` - si tiene áreas
- `areas_count` - cuántas áreas
- Ya NO muestra `customization_structure` (demasiado grande)

```json
[INFO] render_order_customization_metabox
JSON deserializado correctamente
{
  "item_id": 273,
  "areas_found": 1
}

[DEBUG] render_order_customization_metabox
Buscando personalizaciones en pedido
{
  "items_with_customization": 1,  ← Ahora encuentra datos
  "debug_info": {
    "273": {
      "has_customization": true,
      "customization_is_array": true,  ← Ahora es array
      "has_areas": true,
      "areas_count": 1
    }
  }
}
```

**Archivos modificados:**
- `includes/class-wpdm-customization.php` (deserialización automática, línea ~1550)
- `woo-prices-dynamics-makito.php` (v3.2.5)
- `CHANGELOG.md`

**Testing:**
1. Recarga un pedido con personalización
2. El metabox ahora debería mostrar toda la info
3. Prueba botón "Copiar toda la información"
4. Prueba botón "Descargar ZIP"

---

## [3.2.4] - 2025-12-04

### 🔍 Debug mejorado - Más información en logs

---

## [3.2.3] - 2025-12-04

### 🐛 Fix - Bloqueo AGRESIVO de cantidad + UI completamente rediseñada

**Cambios en UI del carrito:**

**Estructura nueva (más limpia):**
```
Personalización: ✓ Sí | 105,00 €
[Ver detalles ▼]

Cantidad: ┌─────────────┐
          │      1      │  ← Fijo, no editable
          │ 🔒 Fijo     │
          └─────────────┘
```

**Bloqueo de cantidad mejorado:**

El código ahora **reemplaza completamente** el selector de cantidad por un div fijo:
```html
<div class="wpdm-qty-fixed">
  <div>1</div>
  <div>🔒 Fijo (personalizado)</div>
</div>
```

**Beneficios:**
- ✅ No hay input que modificar
- ✅ No hay botones +/−
- ✅ Visualmente claro que es fijo
- ✅ Imposible cambiar la cantidad

**Si el wrapper no se encuentra:**
- Fallback: deshabilita el input + elimina botones
- Añade atributos: `disabled`, `readonly`
- CSS: `pointer-events: none`

---

**Detalles responsive:**
- En móvil el botón "Ver detalles" ocupa todo el ancho
- Tabla de detalles optimizada para pantallas pequeñas
- Font-size reducido automáticamente

---

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Bloqueo de cantidad con reemplazo completo (línea ~1256)
  - UI simplificada (línea ~1140)
  - CSS responsive (línea ~1294)
- `woo-prices-dynamics-makito.php` (v3.2.3)
- `CHANGELOG.md`

---

## [3.2.2] - 2025-12-04

### 🐛 Fix - Bloqueo de cantidad mejorado + UI más limpia

**Mejoras implementadas:**

**1. Bloqueo de cantidad más robusto:**
- Input de cantidad ahora disabled + readonly
- Botones +/− deshabilitados visualmente
- Event listener que previene cualquier cambio
- Alert si intenta cambiar: "No se puede cambiar la cantidad..."

**2. UI más limpia y organizada en carrito:**

**ANTES:**
```
Personalización: ✓ Sí [Ver detalles]
Total personalización: 105,00 €
[Detalles expandidos abajo]
```

**AHORA:**
```
Personalización: ✓ Sí | 105,00 €
[Ver detalles ▼]

(Al hacer click se expande con diseño mejorado)
```

**3. Detalles con diseño tipo tabla:**
- Información más compacta y legible
- Tabla con dos columnas (label | valor)
- Cada área en un card blanco separado
- Bordes azules y estilos consistentes

**4. Clase CSS renombrada:**
- `.wpdm-toggle-details` → `.wpdm-toggle-details-btn` (más específico)
- `.wpdm-customization-details` → `.wpdm-customization-details-content`
- Evita conflictos con otros plugins

**5. Logs mejorados:**
- Añadido `has_areas_detail` en cálculo de variación
- Más info de debug para identificar problemas

---

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Función `prevent_quantity_update_for_customized()` (línea ~1384)
  - Hook `woocommerce_update_cart_validation` (línea ~41)
  - UI mejorada en `display_customization_in_cart()` (línea ~1140)
  - Detalles con tabla en `render_customization_details()` (línea ~1170)
  - Script de bloqueo de inputs (línea ~1256)
  - CSS actualizado (línea ~1280)
- `woo-prices-dynamics-makito.php` (v3.2.2)
- `CHANGELOG.md`

---

## [3.2.1] - 2025-12-04

### 🐛 Fix crítico - Precio de personalización NO debe multiplicarse + Cantidad bloqueada

**Problema:**
```
Modal calcula: 5 unidades → Personalización: 105,00 € (para 5 unidades)
Carrito inicial: 1 × 105,00 € = 105,00 € ✅

Cliente cambia cantidad a 2:
Carrito: 2 × 105,00 € = 210,00 € ❌❌❌ INCORRECTO!
```

**Causa:**
El precio de personalización YA está calculado para todas las unidades del pedido (incluye técnicas × cantidad). NO debe multiplicarse de nuevo por la cantidad del carrito.

**Solución implementada:**

**1. Fee de personalización es FIJO (no se multiplica):**
```php
// ANTES:
$fee_amount = $customization_price × $quantity;  ❌

// AHORA:
$fee_amount = $customization_price;  ✅
```

El fee es un **monto único** que ya incluye todas las unidades.

**2. Cantidad bloqueada en carrito:**

Productos personalizados YA NO permiten cambiar cantidad en el carrito:

**ANTES:**
```
Cantidad: [−] 5 [+]  ← Se podía cambiar ❌
```

**AHORA:**
```
Cantidad: 5 (personalizado)  ← Solo lectura ✅
```

**Funciones añadidas:**
- `disable_quantity_change_for_customized()` → Reemplaza selector por texto fijo
- `mark_customized_as_sold_individually()` → Previene cambios desde otros lugares

**Razón:**
La personalización está calculada para una cantidad específica. Si se cambia la cantidad:
- Habría que recalcular técnicas y precios
- Podría cambiar el tier de precios
- Las imágenes y observaciones no coincidirían

**Si el cliente quiere más/menos unidades:**
- Debe **eliminar el producto** del carrito
- Volver al modal
- Seleccionar la cantidad correcta desde el inicio
- Volver a personalizar

---

**Logs mejorados:**
```
[DEBUG] add_customization_fees_to_cart
Fee de personalización añadido (precio fijo)
{
  "customization_price": 105.00,
  "quantity_in_cart": 5,  ← Informativo
  "fee_amount": 105.00,  ← NO multiplicado
  "note": "El precio NO se multiplica por cantidad (ya está calculado)"
}
```

---

**Comportamiento esperado en carrito:**

```
┌──────────────────────────────────────────┐
│ Tanely (AZUL)                            │
│ 2,27 € × 5 = 11,35 €                    │
│                                          │
│ Personalización: ✓ Sí [Ver detalles ▼] │
│ Total personalización: 105,00 €         │
│ Cantidad: 5 (personalizado) ← Fijo      │
│ [Eliminar artículo]                      │
└──────────────────────────────────────────┘

TOTALES:
Subtotal:                        11,35 €
Personalización Tanely (AZUL):  105,00 €  ← Fijo
──────────────────────────────────────────
Total estimado:                 116,35 €
```

---

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Removido `× $quantity` del fee (línea ~1328)
  - `disable_quantity_change_for_customized()` (línea ~1346)
  - `mark_customized_as_sold_individually()` (línea ~1357)
  - Hooks agregados (línea ~39-40)
- `woo-prices-dynamics-makito.php` (v3.2.1)
- `CHANGELOG.md`

---

## [3.2.0] - 2025-12-04

### ✨ Cambio importante - Personalización como FEE separado + Toggle funcional

**Nuevo comportamiento del carrito:**

**ANTES:**
```
Producto: Tanely             172,27 € × 5 = 861,35 €
Personalización: ✓ Sí [Ver detalles]
```
(El precio ya incluía personalización pero era confuso)

**AHORA:**
```
Producto: Tanely               2,27 € × 5 = 11,35 €
Personalización: ✓ Sí [Ver detalles ▼]
Total personalización:                   170,00 €
─────────────────────────────────────────────────────
En el resumen del carrito:
Subtotal productos:                       11,35 €
Personalización: Tanely (AZUL):         170,00 €  ← FEE
Total estimado:                          181,35 €
```

**Ventajas:**
- ✅ Precio base del producto visible y claro
- ✅ Personalización separada y transparente
- ✅ Más fácil de entender para el cliente
- ✅ Coincide exactamente con el cálculo del modal

---

#### **Cambio 1: Personalización como FEE (cargo adicional)**

**Hook utilizado:**
```php
add_action( 'woocommerce_cart_calculate_fees', 'add_customization_fees_to_cart' )
```

**Cómo funciona:**
1. Por cada producto con personalización en el carrito
2. Se añade un FEE con nombre descriptivo:
   - "Personalización Tanely (AZUL)" 
   - "Personalización Tanely (BLANCO)"
3. El fee es la suma: `customization_price × quantity`
4. Se suma automáticamente al total del carrito

**Logs generados:**
```
[DEBUG] add_customization_fees_to_cart
Fee de personalización añadido
{
  "product_name": "Tanely",
  "color": "AZUL-AZUL",
  "customization_price": 85.00,
  "quantity": 2,
  "fee_amount": 170.00
}
```

---

#### **Cambio 2: Display mejorado en carrito**

**Líneas mostradas en cada producto:**

1. **Personalización: ✓ Sí [Ver detalles ▼]** - Botón funcional
2. **Total personalización: 170,00 €** - Monto claro y visible
3. **(Oculto por defecto)** Detalles completos - Se abre con el botón

**Logs al mostrar:**
```
[DEBUG] display_customization_in_cart
Mostrando personalización en carrito
{
  "customization_price": 85.00,
  "areas_count": 2,
  "has_price_breakdown": true
}
```

---

#### **Cambio 3: Campos requeridos añadidos**

**Frontend ahora envía:**
```javascript
{
  enabled: true,  // ✅ CRÍTICO - requerido por backend
  colors: 2,  // ✅ CRÍTICO - esperado por calculate_area_price
  colors_selected: 2,  // Para mostrar en admin
  technique_ref: "104001",
  ...
}
```

**Logs de cálculo:**
```
[DEBUG] calculate_total_customization_price
Calculando precios
{
  "areas_count": 2,
  "areas_data": [...]  // Con todos los campos
}

[DEBUG] Procesando área 0
{
  "enabled": true,
  "technique_ref": "104001",
  "area_data_keys": ["enabled", "technique_ref", "colors", ...]
}

[DEBUG] Precio de área calculado
{
  "area_total": 42.50
}

[INFO] Cálculo completado
{
  "total_price": 85.00,
  "areas_processed": 2
}
```

---

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Cambiado hook a `woocommerce_cart_calculate_fees` (línea ~36)
  - Función renombrada a `add_customization_fees_to_cart()` (línea ~1293)
  - `display_customization_in_cart()` ahora muestra 3 líneas (línea ~1119)
  - Logs detallados en cálculo de precios (línea ~407-466)
- `includes/class-wpdm-customization-frontend.php`:
  - Añadido `enabled: true` (línea ~1507)
  - Añadido campo `colors` (línea ~1513)
  - Logs de diseño (línea ~1539)
- `woo-prices-dynamics-makito.php` (v3.2.0)
- `CHANGELOG.md`

---

## [3.1.2] - 2025-12-04

### 🐛 Fix crítico - Campos faltantes causaban precio 0 + Toggle mejorado

**Problema 1: customization_price siempre era 0** ❌

Los logs mostraban:
```
"customization_price": 0,
"new_price": 2.27
```

**Causa:**
El frontend NO enviaba los campos requeridos por `calculate_total_customization_price()`:
- ❌ Faltaba: `enabled: true`
- ❌ Faltaba: `colors` (enviaba `colors_selected` pero la función espera `colors`)

**Solución Frontend:**
```javascript
var areaData = {
  enabled: true,  // ✅ Añadido
  colors: numColors,  // ✅ Añadido (antes solo colors_selected)
  colors_selected: numColors,  // Para mostrar en metabox
  technique_ref: ...,
  ...
};
```

**Logs añadidos para debug:**
Ahora se registra cada paso del cálculo:
1. Inicio del cálculo con áreas recibidas
2. Procesamiento de cada área (con todos sus campos)
3. Si una área se omite, se registra el motivo
4. Precio calculado por cada área
5. Total final

**Resultado esperado:**
Los logs ahora mostrarán:
```
[DEBUG] calculate_total_customization_price
Calculando precios
{
  "areas_count": 2,
  "areas_data": [
    {"enabled": true, "technique_ref": "104001", "colors": 1, ...},
    {"enabled": true, "technique_ref": "100116", "colors": 2, ...}
  ]
}

[DEBUG] Precio de área calculado
{
  "area_index": 0,
  "area_quantity": 5,
  "area_total": 32.50
}

[INFO] Cálculo completado
{
  "total_price": 85.00,
  "areas_processed": 2
}
```

---

**Problema 2: Toggle "Ver detalles" no funcionaba** ❌

El botón aparecía pero los detalles estaban siempre visibles.

**Causa:**
- WooCommerce sanitiza HTML y puede eliminar scripts inline
- Los eventos no se re-inicializaban al actualizar el carrito

**Solución:**
1. **Removido script inline** del HTML generado
2. **Script global mejorado** con múltiples puntos de inicialización:
   - `$(document).ready` con delay de 100ms
   - Eventos WooCommerce: `updated_cart_totals`, `updated_checkout`, `wc_fragments_refreshed`
   - Evento adicional: `updated_wc_div` (para cambios de cantidad)
3. **CSS robusto:**
   ```css
   .wpdm-customization-details.wpdm-hidden,
   .wpdm-customization-details {
     display: none !important;
   }
   ```
4. **Clase adicional** `wpdm-hidden` para control extra
5. **Data attribute** `data-wpdm-initialized` para evitar inicializar múltiples veces

**Funcionamiento:**
- Al cargar: todos los detalles se ocultan automáticamente
- Click en "Ver detalles ▼" → slideDown + texto cambia a "Ocultar detalles ▲"
- Click en "Ocultar detalles ▲" → slideUp + texto cambia a "Ver detalles ▼"
- Logs en consola para debug

---

**Archivos modificados:**
- `includes/class-wpdm-customization-frontend.php`:
  - Añadido `enabled: true` al enviar áreas (línea ~1507)
  - Añadido campo `colors` además de `colors_selected` (línea ~1513)
  - Logs de diseño añadido (línea ~1539)
- `includes/class-wpdm-customization.php`:
  - Logs detallados en `calculate_total_customization_price()` (línea ~407-463)
  - Script de toggle mejorado (línea ~1215-1265)
  - HTML simplificado sin script inline (línea ~1119-1131)
- `woo-prices-dynamics-makito.php` (v3.1.2)
- `CHANGELOG.md`

---

## [3.1.1] - 2025-12-04

### 🐛 Fix crítico - Precio de personalización no se aplicaba + Metabox vacío

**Problemas corregidos:**

**1. El precio de personalización NO se sumaba al carrito** ❌
- Los productos entraban con precio base sin personalización
- El cliente veía en el modal: "Total: 192,70 €"
- Pero en el carrito aparecía: "2,27 €" (solo precio base)
- El precio de personalización se perdía

**Solución:**
Añadido hook `woocommerce_before_calculate_totals` con prioridad 20:
```php
apply_customization_price_to_cart()
```

**Cómo funciona:**
- Lee `wpdm_customization_price` de cada item
- Lo suma al precio base del producto
- Aplica el nuevo precio antes de calcular totales
- Se ejecuta DESPUÉS del sistema de tiers (prioridad 20 vs 10)

**Resultado:**
```
Precio base:         2,27 € (con tier aplicado)
+ Personalización:  85,00 €
= Precio final:     87,27 € por unidad ✅
```

**Logs agregados:**
```
[DEBUG] apply_customization_price_to_cart
Precio ajustado en carrito
{
  "base_price": 2.27,
  "customization_price": 85.00,
  "new_price": 87.27,
  "quantity": 2
}
```

---

**2. El metabox aparecía vacío** ❌
- El metabox se mostraba correctamente
- Pero decía "Este pedido no tiene productos personalizados"
- Los datos SÍ estaban guardados pero no se encontraban

**Solución:**
Añadido sistema de debug en el metabox:
- Logs cuando se renderiza el metabox
- Muestra todos los meta_keys encontrados en cada item
- Panel de debug expandible en el metabox si no encuentra datos

**Logs agregados:**
```
[DEBUG] render_order_customization_metabox
Buscando personalizaciones en pedido
{
  "order_id": 1234,
  "total_items": 4,
  "items_with_customization": 4,
  "debug_info": {
    "item_123": {
      "product_name": "Tanely",
      "has_customization": true,
      "meta_keys": ["_wpdm_customization", "_wpdm_customization_price", ...]
    }
  }
}
```

**Panel de debug en metabox:**
Si no encuentra datos, ahora muestra un accordion "🔍 Ver información de debug" que lista:
- Todos los items del pedido
- Qué meta_keys tiene cada item
- Si tiene o no personalización

Esto permite identificar rápidamente si:
- Los datos no se guardaron
- Se guardaron con otra clave
- Hay problema de permisos/compatibilidad

---

**Logs adicionales en guardado:**
También añadido log en `save_customization_to_order()`:
```
[INFO] save_customization_to_order
Guardando personalización en pedido
{
  "order_id": 1234,
  "item_id": 567,
  "areas_count": 2,
  "customization_price": 85.00
}
```

---

#### **Testing:**

**1. Precio en carrito:**
- Añade producto con personalización
- Modal muestra: "Total: 192,70 €"
- Ve al carrito
- Debería mostrar precio con personalización incluida
- Ejemplo: "87,27 €" × cantidad

**2. Metabox en pedido:**
- Completa un pedido con personalización
- Ve al admin del pedido
- Busca metabox "🎨 Detalles de Personalización"
- Debería mostrar TODOS los datos
- Si está vacío, expande "🔍 Ver información de debug"
- Revisa WPDM Logs para ver qué meta_keys se guardaron

---

**Archivos modificados:**
- `includes/class-wpdm-customization.php`:
  - Nuevo hook y función `apply_customization_price_to_cart()` (línea ~1285)
  - Logs en `save_customization_to_order()` (línea ~1268)
  - Debug en `render_order_customization_metabox()` (línea ~1322)
- `woo-prices-dynamics-makito.php` (v3.1.1)
- `CHANGELOG.md`

---

## [3.1.0] - 2025-12-04

### ✨ Nueva funcionalidad - Metabox de personalización en admin del pedido

**¡Toda la información de personalización ahora accesible en el admin!**

Esta versión añade un metabox dedicado en la pantalla de edición del pedido que muestra TODOS los detalles de personalización de forma organizada y profesional.

---

#### **Características del Metabox:**

**1. Header con acciones rápidas:**
- 📋 **Copiar toda la información** - Copia al portapapeles en formato texto
- 📥 **Descargar todas las imágenes (ZIP)** - Genera ZIP con todos los archivos
- Contador de productos personalizados

**2. Vista detallada por producto:**

Cada producto personalizado muestra un panel con:

**Por cada área de marcaje:**
```
┌──────────────────────────────────────┐
│ 📐 Área 1                            │
├──────────────────────────────────────┤
│ Técnica de marcación: DIGITAL 360    │
│ Número de colores: 1                 │
│ 🎨 Colores PANTONE: Rojo             │
│ 📸 Archivo adjunto:                  │
│   [Ver archivo] [📥 Descargar]       │
│   logo-vamlemat.jpeg                 │
│ 📝 Observaciones:                    │
│   ┌────────────────────────────────┐ │
│   │ Logo centrado en área           │ │
│   └────────────────────────────────┘ │
│ Repetición Cliché: ✓ Sí              │
│   (Nº pedido: ABC123)                │
└──────────────────────────────────────┘
```

**3. Resumen de precios:**
```
┌──────────────────────────────────────┐
│ 💰 Resumen de Precios                │
├──────────────────────────────────────┤
│ Precio base producto:      22,70 €   │
│ Personalización:          170,00 €   │
│ ─────────────────────────────────────│
│ TOTAL:                    192,70 €   │
└──────────────────────────────────────┘
```

---

#### **Funcionalidad de botones:**

**📋 Copiar toda la información:**
- Genera texto formateado con toda la info
- Incluye: áreas, técnicas, PANTONE, observaciones
- Se copia automáticamente al portapapeles
- Listo para pegar en email o documento

**Formato del texto copiado:**
```
============================================================
Tanely - AZUL-AZUL, S/T
============================================================

📐 Área 1
----------------------------------------
Técnica de marcación: DIGITAL 360 WR1 -5cm
Número de colores: 1
🎨 Colores PANTONE: Rojo
📸 Archivo adjunto: logo-vamlemat.jpeg
📝 Observaciones: Logo centrado

📐 Área 9
----------------------------------------
Técnica de marcación: TAMPOGRAFÍA F
Número de colores: 2
🎨 Colores PANTONE: Rojo, Naranja
📸 Archivo adjunto: diseño.jpeg
📝 Observaciones: Dos colores en área 9
```

**📥 Descargar ZIP:**
- Genera archivo ZIP con todas las imágenes del pedido
- Nombres de archivo: `Area_1_logo.jpg`, `Area_9_diseño.jpg`
- Nombre del ZIP: `pedido-1234-personalizacion-2025-12-04-102030.zip`
- Se descarga automáticamente

---

#### **Ubicación del metabox:**

**Dónde aparece:**
- WooCommerce > Pedidos > [Editar pedido]
- En la columna principal, posición alta
- Solo aparece si el pedido tiene productos personalizados

**Compatible con:**
- ✅ WooCommerce tradicional (shop_order post type)
- ✅ HPOS (High-Performance Order Storage) WooCommerce 8.0+

---

#### **Diseño visual:**

**Colores corporativos:**
- Azul corporativo: #0464AC
- Verde éxito: #28a745
- Amarillo observaciones: #ffc107
- Gris suave: #f9f9f9

**Elementos visuales:**
- Gradiente en header
- Bordes coloreados por sección
- Iconos descriptivos (📐 🎨 📸 📝)
- Hover effects en botones
- Tabla responsive

**UX optimizada:**
- Información clara y escaneable
- Botones de acción prominentes
- Links de descarga directos
- Sin necesidad de clicks extras para ver info

---

#### **Detalles técnicos:**

**Funciones añadidas:**
- `add_order_customization_metabox()` - Registra el metabox
- `render_order_customization_metabox()` - Renderiza contenido
- `ajax_download_all_images_zip()` - Genera y descarga ZIP

**Hooks utilizados:**
- `add_meta_boxes` - Para shop_order y woocommerce_page_wc-orders
- `wp_ajax_wpdm_download_all_images_zip` - Endpoint de descarga

**Seguridad:**
- Nonce verification para descargas
- Capability check: `edit_shop_orders`
- Validación de existencia de archivos
- Sanitización de nombres de archivo

**Almacenamiento:**
- ZIP temporal en `sys_get_temp_dir()`
- Se elimina automáticamente después de descarga
- No consume espacio en disco

---

#### **Archivos modificados:**

- `includes/class-wpdm-customization.php`:
  - Hooks de metabox (línea ~38-43)
  - Función `add_order_customization_metabox()` (línea ~1241)
  - Función `render_order_customization_metabox()` (línea ~1265)
  - Función `ajax_download_all_images_zip()` (línea ~1415)
  - Script inline de copiar texto (línea ~1391)
- `woo-prices-dynamics-makito.php` (v3.1.0)
- `CHANGELOG.md`

---

#### **Próximos pasos sugeridos:**

Esta versión completa el ciclo de personalización end-to-end. Posibles mejoras futuras:
- Email automático con archivos adjuntos al proveedor
- Exportación a PDF de la personalización
- Integración con sistema de producción externo
- Panel de gestión de personalizaciones pendientes

---

## [3.0.6] - 2025-12-04

### 🐛 Fix - Botón "Ver detalles" siempre expandido en carrito

**Problema:**
- El botón "Ver detalles" aparecía pero los detalles estaban siempre visibles
- No funcionaba el toggle para abrir/cerrar
- El acordeón no se comportaba como esperado

**Causa:**
- El script de toggle no se ejecutaba a tiempo
- WooCommerce carga el carrito dinámicamente varias veces
- El CSS `display: none` se sobrescribía

**Solución:**

**1. Script inline por cada item:**
Ahora cada elemento del carrito tiene su propio script inline que se ejecuta inmediatamente, asegurando que:
- El div de detalles se oculta al cargar
- El event listener se registra específicamente para ese botón
- Usa namespace único para evitar conflictos: `click.wpdm-{uniqueId}`

**2. CSS mejorado:**
```css
display: none !important;  /* Fuerza que esté oculto inicialmente */
```

**3. Script global de respaldo:**
- Se mantiene el script global en footer
- Se re-ejecuta cuando el carrito se actualiza
- Eventos: `updated_cart_totals`, `updated_checkout`, `wc_fragments_refreshed`
- Logs de debug para verificar cuántos botones encuentra

**4. Mejoras de UX:**
- Efecto hover mejorado con translateY
- Efecto active al hacer click
- Transiciones suaves
- Console logs para debug

**Estructura del HTML generado:**
```html
<div class="wpdm-cart-customization-wrapper">
  <span>✓ Sí</span>
  <button class="wpdm-toggle-details">Ver detalles ▼</button>
  <div id="wpdm-details-..." style="display: none !important;">
    [contenido de detalles]
  </div>
  <script>
    // Script inline específico para este item
  </script>
</div>
```

**Testing recomendado:**
1. Añadir producto al carrito
2. Verificar que detalles están OCULTOS por defecto
3. Click en "Ver detalles ▼"
4. Verificar que se ABRE con animación slideDown
5. Click en "Ocultar detalles ▲"
6. Verificar que se CIERRA con animación slideUp
7. Actualizar cantidades en carrito
8. Verificar que el toggle sigue funcionando

**Archivos modificados:**
- `includes/class-wpdm-customization.php` (líneas ~1073-1105, ~1150-1204)
- `woo-prices-dynamics-makito.php` (v3.0.6)
- `CHANGELOG.md`

---

## [3.0.5] - 2025-12-04

### 🐛 Fix crítico - Estructura incorrecta de $_FILES

**Problema:**
- Error 500 "Internal Server Error" al añadir al carrito
- Los logs mostraban estructura anidada incorrecta en $_FILES
- Cada valor estaba envuelto en un objeto con clave "file"

**Estructura incorrecta recibida:**
```php
$_FILES['images']['name'][0] = ['file' => 'logo.jpg']  // ❌ INCORRECTO
```

**Estructura esperada:**
```php
$_FILES['images']['name'][0] = 'logo.jpg'  // ✅ CORRECTO
```

**Causa raíz:**
En el frontend, se enviaban archivos como:
```javascript
formData.append('images[0][file]', data.image);  // ❌ Creaba anidamiento
```

**Solución:**

**Frontend (`includes/class-wpdm-customization-frontend.php`):**
```javascript
// ANTES:
formData.append('images[0][file]', data.image);
formData.append('images[0][area_id]', ...);

// AHORA:
formData.append('images[]', data.image);  // ✅ Array simple
formData.append('images_meta[0][area_id]', ...);  // ✅ Metadata separada
```

**Backend (`includes/class-wpdm-customization.php`):**
- Reescrito procesamiento completo de `$_FILES['images']`
- Ahora maneja correctamente arrays PHP estándar
- Metadata se lee desde `$_POST['images_meta'][]`
- Logs mejorados en cada paso del proceso

**Logs agregados:**
1. Conteo de archivos recibidos
2. Procesamiento de cada archivo individual con su metadata
3. Asociación exitosa de imagen con área
4. Warnings si algún archivo falla (sin abortar los demás)
5. Resumen final con áreas que tienen imágenes

**Archivos modificados:**
- `includes/class-wpdm-customization-frontend.php` (líneas ~1538-1566)
- `includes/class-wpdm-customization.php` (líneas ~670-745)
- `woo-prices-dynamics-makito.php` (v3.0.5)
- `CHANGELOG.md`

---

## [3.0.4] - 2025-12-04

### 🐛 Fix crítico - Error 500 al subir múltiples archivos

**Problema:**
- Internal Server Error 500 al añadir al carrito
- Error en consola: "❌ Error AJAX: Internal Server Error"
- Los logs mostraban estructura de archivos incorrecta

**Causa:**
La función `upload_single_customization_image()` no manejaba correctamente el formato de `$_FILES` cuando se suben múltiples archivos. PHP envía los archivos en un array anidado:

```php
$_FILES['images'] = [
    'name' => [0 => 'file1.jpg', 1 => 'file2.jpg'],
    'type' => [0 => 'image/jpeg', 1 => 'image/jpeg'],
    'tmp_name' => [...],
    ...
]
```

La función esperaba un archivo individual, causando error fatal.

**Solución:**
Reescrito el procesamiento de archivos en `ajax_add_customized_to_cart()`:

1. **Detección de tipo de estructura:**
   - Detecta si `$file_data['name']` es un array (múltiples archivos)
   - O si es un string (archivo único)

2. **Procesamiento de arrays:**
   - Itera sobre cada archivo en el array
   - Reconstruye la estructura de archivo individual:
     ```php
     $single_file = [
         'name' => $file_data['name'][$index],
         'type' => $file_data['type'][$index],
         'tmp_name' => $file_data['tmp_name'][$index],
         'error' => $file_data['error'][$index],
         'size' => $file_data['size'][$index]
     ];
     ```
   - Extrae metadata (area_id, area_index, variation_id) correctamente

3. **Manejo de errores mejorado:**
   - Logs cuando un archivo falla
   - Continúa procesando otros archivos si uno falla
   - No interrumpe todo el proceso

4. **Logs adicionales:**
   - Log de estructura de `$_FILES` recibida para debug
   - Warning si algún archivo específico falla

**Archivos modificados:**
- `includes/class-wpdm-customization.php` (líneas ~670-750)
- `woo-prices-dynamics-makito.php` (v3.0.4)
- `CHANGELOG.md`

**Testing recomendado:**
1. Añadir personalización con 1 archivo → debería funcionar
2. Añadir personalización con múltiples archivos → ahora funciona
3. Revisar WPDM Logs → ver uploads exitosos

---

## [3.0.3] - 2025-12-04

### 🐛 Fix crítico - AJAX URL undefined (typo)

**Problema:**
- `wpdmCustomization.ajaxUrl` estaba `undefined`
- El servidor devolvía HTML en lugar de JSON
- Error: "Se recibió HTML en lugar de JSON"
- Causa: **Inconsistencia en nombre de propiedad**

**El bug:**
```javascript
// Objeto definido con guión bajo:
wpdmCustomization = {
  ajax_url: 'https://...'  // ✅ Correcto
}

// Pero el código usaba camelCase:
$.ajax({
  url: wpdmCustomization.ajaxUrl  // ❌ undefined!
})
```

**Solución:**
Corregido en 2 lugares del código de "añadir al carrito":
- Línea ~1567: `ajaxUrl` → `ajax_url` ✅
- Línea ~1741: `ajaxUrl` → `ajax_url` ✅

Ahora usa consistentemente `ajax_url` (con guión bajo) como el resto del código.

**Por qué no funcionaba el logging:**
Como el AJAX URL estaba undefined, la petición ni siquiera llegaba al servidor PHP, por eso no se generaban logs. Ahora con esto corregido, los logs SÍ se guardarán.

**Archivos modificados:**
- `includes/class-wpdm-customization-frontend.php` (2 correcciones)
- `woo-prices-dynamics-makito.php` (v3.0.3)
- `CHANGELOG.md`

**Testing:**
Después de actualizar, en consola del navegador ejecuta:
```javascript
console.log('AJAX URL:', wpdmCustomization.ajax_url);
```
Ahora debería mostrar la URL completa en lugar de `undefined`.

---

## [3.0.2] - 2025-12-04

### 🔧 Mejora - Sistema de logging integrado

**Cambio principal:**
Ahora utiliza el sistema de logging del plugin (`WPDM_Logger`) en lugar de `error_log()` directamente.

**Beneficios:**
1. **Logs centralizados** en WooCommerce > WPDM Logs
2. **Interfaz visual** para revisar logs sin acceder al servidor
3. **Filtros por nivel** (debug, info, warning, error)
4. **Datos estructurados** en JSON fáciles de leer
5. **Retención configurable** (horas/días)
6. **Limpieza automática** de logs antiguos

**Cómo usarlo:**
1. Ve a **WooCommerce > WPDM Logs**
2. Activa "Habilitar Logging"
3. Configura retención (ej: 24 horas)
4. Guarda configuración
5. Intenta añadir al carrito
6. Recarga la página de logs
7. Verás todos los pasos detallados

**Logs registrados:**
- `info` → Inicio del proceso, productos añadidos
- `debug` → Datos recibidos, imágenes procesadas, variaciones añadidas
- `warning` → Validaciones fallidas, archivos rechazados
- `error` → Excepciones, errores críticos con stack trace completo

**Contextos:**
- `ajax_add_customized_to_cart` → Proceso principal
- `upload_single_customization_image` → Subida de archivos

**Ejemplo de log:**
```
[INFO] ajax_add_customized_to_cart
Iniciando proceso de añadir al carrito

[DEBUG] ajax_add_customized_to_cart  
Datos recibidos
{
  "product_id": 15535,
  "mode": "global",
  "variations_count": 2,
  "areas_count": 1
}

[DEBUG] upload_single_customization_image
Iniciando subida de archivo
{
  "filename": "logo.png",
  "size": 245678,
  "type": "image/png"
}

[INFO] upload_single_customization_image
Archivo subido exitosamente
{
  "filename": "logo-abc123.png",
  "url": "https://..."
}
```

**Archivos modificados:**
- `includes/class-wpdm-customization.php` (todos los logs reemplazados)
- `woo-prices-dynamics-makito.php` (v3.0.2)
- `CHANGELOG.md`

**Nota:** El logger también envía a `error_log` de PHP si `WP_DEBUG` está activado, así que tendrás los logs en ambos lugares durante desarrollo.

---

## [3.0.1] - 2025-12-04

### 🐛 Fix crítico - Manejo de errores mejorado

**Problema:**
- El servidor devolvía HTML en lugar de JSON cuando había un error PHP
- Mensaje de error: "Cannot read properties of undefined (reading 'message')"
- El botón se quedaba en "Procesando..." sin respuesta

**Solución:**

**Backend (`includes/class-wpdm-customization.php`):**
- Añadido `try-catch` completo en `ajax_add_customized_to_cart()`
- Logs de error detallados en cada paso crítico
- Si hay una excepción, se captura y se devuelve JSON válido con el error
- Error logs incluyen: mensaje + stack trace completo

**Frontend (`includes/class-wpdm-customization-frontend.php`):**
- Validación de respuesta antes de procesarla
- Detección de HTML en lugar de JSON
- Mensaje de error específico según el tipo de fallo
- Botón se rehabilita correctamente si hay error
- Instrucciones al usuario para revisar logs

**Para depurar:**
1. Si ves el error en consola, revisa el error_log de PHP
2. Los logs ahora muestran exactamente dónde falla
3. Busca líneas que empiecen con `[WPDM]` en el error_log

**Archivos modificados:**
- `includes/class-wpdm-customization.php` (try-catch + logs)
- `includes/class-wpdm-customization-frontend.php` (validación de respuesta)
- `woo-prices-dynamics-makito.php` (v3.0.1)
- `CHANGELOG.md`

---

## [3.0.0] - 2025-12-04

### 🎉 FASE 7 COMPLETA - Añadir al carrito con personalización

**¡Funcionalidad completa del sistema de personalización!** 

Esta versión marca un hito importante: ahora los productos personalizados se pueden añadir al carrito con todos sus datos (técnicas, colores PANTONE, imágenes, observaciones) y se muestran correctamente en el carrito, checkout y pedido.

---

#### **7.1 Frontend - Recopilación y envío de datos** ✅

**Event listener del botón "Añadir al carrito":**
- Recopila todos los datos de personalización del modal
- Incluye datos de áreas habilitadas con técnicas, colores, cantidades
- Recopila datos de diseño (PANTONE, observaciones, imágenes)
- Maneja correctamente modo global y per-color
- Muestra loading mientras procesa
- Usa FormData para enviar archivos

**Datos enviados al servidor:**
```javascript
- product_id
- mode (global|per-color)
- variations (array con todas las variaciones seleccionadas)
- customization_data (áreas con técnicas, colores, precios)
- images (archivos con metadata: area_id, area_index, variation_id)
```

---

#### **7.2 Backend - Endpoint AJAX y subida de imágenes** ✅

**Endpoint:** `wpdm_add_customized_to_cart`

**Procesamiento:**
1. Validación de nonce y datos básicos
2. Subida de imágenes al servidor:
   - Directorio: `wp-content/uploads/wpdm-customization/`
   - Validación de tipos: JPG, PNG, PDF, EPS, AI, CDR
   - Validación de tamaño: máx. 5MB
   - Generación de URLs permanentes
3. Asociación de imágenes con áreas y variaciones
4. Añadir productos al carrito con WooCommerce

**Nueva función:** `upload_single_customization_image()`
- Maneja upload individual con validaciones
- Retorna URL, path y filename
- Control de errores con WP_Error

---

#### **7.3 Guardar meta data en carrito** ✅

**Estructura de datos guardada en cada item del carrito:**

```php
'wpdm_customization' => [
    'mode' => 'global|per-color',
    'areas' => [
        [
            'area_id' => int,
            'area_position' => string,
            'technique_name' => string,
            'colors_selected' => int,
            'pantones' => [
                ['colorNum' => 1, 'value' => 'Rojo'],
                ...
            ],
            'image_url' => string,
            'image_filename' => string,
            'observations' => string,
            'cliche_repetition' => bool,
            'cliche_order_number' => string
        ],
        ...
    ],
    'price_breakdown' => array,
    'base_price' => float,
    'customization_price' => float,
    'grand_total' => float
],
'wpdm_customization_price' => float,
'wpdm_variation_info' => [
    'color' => string,
    'size' => string,
    'full_name' => string
]
```

---

#### **7.4 Mostrar en carrito con botón "Ver detalles"** ✅

**Vista en carrito - Opción A (Simple + Desplegable):**

```
Personalización: ✓ Sí [Ver detalles ▼]
```

**Al hacer click en "Ver detalles":**

Despliega con animación slideDown mostrando:

📐 **Por cada área:**
- Nombre del área (ej: "Área 1")
- Técnica de marcación
- Número de colores
- 🎨 Colores PANTONE seleccionados
- 📸 Link para ver archivo subido
- 📝 Observaciones

💰 **Resumen de precios:**
- Precio base del producto
- Precio de personalización
- Total

**Características:**
- Botón toggle cambia texto: "Ver detalles ▼" ↔ "Ocultar detalles ▲"
- Animación suave con slideUp/slideDown
- Diseño limpio con colores corporativos (#0464AC)
- Hover effect en botón
- Responsive y accesible

---

#### **7.5 Mostrar en checkout y pedido** ✅

**En checkout:**
- Se muestra igual que en carrito
- Datos visibles para revisión antes de confirmar

**En el pedido (orden):**
- Metadata guardada con `_wpdm_customization`
- Meta key formateada: "Personalización: ✓ Sí"
- Todos los detalles accesibles en el admin del pedido

**En email de confirmación:**
- Se incluye indicador de personalización
- Links a archivos subidos funcionan correctamente

---

#### **Feedback al usuario** ✅

**Durante el proceso:**
- Botón cambia a "Procesando..." y se deshabilita
- Console logs detallados para debug

**Después de añadir:**
- Alert de confirmación: "✅ Producto personalizado añadido al carrito correctamente"
- Modal se cierra automáticamente
- Contador del carrito se actualiza (trigger `wc_fragment_refresh`)
- Scroll automático al top de la página

**Manejo de errores:**
- Mensajes claros de error si falla
- Botón se vuelve a habilitar para reintentar
- Console logs de errores para debug

---

#### **Archivos modificados:**

**Frontend:**
- `includes/class-wpdm-customization-frontend.php`:
  - Event listener completo del botón "Añadir al carrito" (línea ~1442)
  - Recopilación de datos de todas las fuentes
  - Preparación de FormData con archivos
  - Manejo de respuesta AJAX y feedback

**Backend:**
- `includes/class-wpdm-customization.php`:
  - Actualizado `ajax_add_customized_to_cart()` (línea ~626)
  - Nueva función `upload_single_customization_image()` (línea ~773)
  - Función `display_customization_in_cart()` (línea ~933)
  - Función `render_customization_details()` (línea ~961)
  - Función `enqueue_cart_toggle_script()` (línea ~1009)
  - Función `save_customization_to_order()` (línea ~1035)
  - Función `format_order_item_meta()` (línea ~1044)

**Plugin:**
- `woo-prices-dynamics-makito.php` (v3.0.0)
- `CHANGELOG.md`

---

#### **Testing checklist:**

- ✅ Añadir producto con personalización (modo global)
- ✅ Añadir producto con personalización (modo per-color)
- ✅ Subir imágenes (JPG, PNG, PDF, EPS, AI, CDR)
- ✅ Guardar colores PANTONE
- ✅ Guardar observaciones
- ✅ Ver producto en carrito
- ✅ Botón "Ver detalles" funciona
- ✅ Datos completos en el desplegable
- ✅ Checkout muestra personalización
- ✅ Pedido guarda todos los datos
- ✅ Admin puede ver personalización en el pedido

---

**Nota importante:** Esta es la versión 3.0.0 porque marca la funcionalidad completa del sistema de personalización. Todas las fases previas (1-6) se integraron y ahora el flujo completo está operativo end-to-end.

---

## [2.12.0] - 2025-12-04

### ✨ Mejora UX - Selector visual de colores PANTONE + Más formatos de archivo

**Selector visual de colores PANTONE estilo Makito:**

En lugar de un campo de texto libre, ahora se muestra un selector visual con paleta de colores predefinida:

1. **Interfaz visual:**
   - Icono de gota/balde de pintura (🎨) clickeable
   - Dropdown con grid de 16 colores predefinidos
   - Colores en forma de gota (teardrop) rotados 45°
   - Efecto hover con escala y sombra
   - Color seleccionado se muestra en el preview

2. **Paleta de colores incluida:**
   - Negro, Gris Oscuro, Blanco, Rojo
   - Rosa Fucsia, Granate, Azul, Naranja
   - Azul Oscuro, Amarillo, Naranja Rojizo, Verde
   - Verde Oscuro, Marrón, Marrón Claro, Gris Claro

3. **Opción personalizada:**
   - Campo de texto en la parte inferior del dropdown
   - Permite introducir PANTONE personalizado si no está en la paleta
   - Se guarda igual que los colores predefinidos

4. **Funcionalidad:**
   - Click en preview abre/cierra dropdown
   - Click fuera cierra todos los dropdowns abiertos
   - Color seleccionado se guarda automáticamente
   - El nombre del color se muestra al lado del preview

**Formatos de archivo ampliados:**

Ahora se aceptan formatos profesionales de diseño:
- ✅ JPG, JPEG, PNG (imágenes)
- ✅ PDF (documentos)
- ✅ EPS (Adobe Encapsulated PostScript)
- ✅ AI (Adobe Illustrator)
- ✅ CDR (CorelDRAW) ← NUEVO

**Validación mejorada:**
- Validación por extensión y tipo MIME
- Mensaje de error actualizado con todos los formatos
- Tooltip informativo en el icono ℹ️
- Banner informativo actualizado

**Cambios técnicos:**

- **`updateImagesTab()`** (línea ~967):
  - Nueva función `generateColorSelector()` para crear selector visual
  - Paleta `colorPalette` con 16 colores predefinidos
  - Estructura HTML del dropdown con grid 4x4
  - Estilos inline para gotas rotadas

- **Event listeners nuevos** (línea ~1315):
  - Click en `.wpdm-color-preview` → abrir/cerrar dropdown
  - Click en `.wpdm-color-option` → seleccionar color
  - Input en `.wpdm-custom-pantone` → PANTONE personalizado
  - Click fuera → cerrar dropdowns
  - Hover en colores → efecto de escala

- **Validación de archivos** (línea ~1225):
  - Array `validExtensions`: ['.jpg', '.jpeg', '.png', '.pdf', '.eps', '.ai', '.cdr']
  - Validación combinada por extensión y tipo MIME
  - Mensaje de error actualizado

- **Accept de input file**:
  - Actualizado para incluir: `application/postscript`, `application/illustrator`, `.eps`, `.ai`, `.cdr`

**Archivos modificados:**
- `includes/class-wpdm-customization-frontend.php`:
  - Función `generateColorSelector()` (línea ~984)
  - Paleta de colores (línea ~975)
  - Event listeners colores (línea ~1315)
  - Validación archivos (línea ~1225)
  - Accept input file (líneas ~1077, ~1139)
  - Banner informativo (línea ~1566)
- `woo-prices-dynamics-makito.php` (v2.12.0)
- `CHANGELOG.md`

**Resultado visual:**
- Selector de colores profesional tipo Makito ✅
- Colores en forma de gota con hover effects ✅
- Soporte completo para archivos de diseño profesional ✅
- UX mejorada y más intuitiva ✅

---

## [2.11.1] - 2025-12-04

### 🐛 Fix crítico - Tab DISEÑO ahora funciona en modo per-color

**Problemas corregidos:**

1. **"undefined" en nombre de área** ✅
   - Añadido `data-area-id` y `data-area-position` al crear `.wpdm-area-item`
   - Añadido `data-variation-id` cuando está en modo per-color
   - Ahora se muestra correctamente: "📐 Área 1", "📐 Área 2", etc.

2. **Tab DISEÑO no funcionaba en modo per-color** ✅
   - Corregida búsqueda de `.wpdm-variation-accordion` → `.wpdm-color-accordion`
   - Añadido `data-variation-id`, `data-color` y `data-size` al acordeón de variaciones
   - Ahora genera bloques correctamente para cada combinación área + color + talla
   - Información de variación se obtiene desde data attributes del acordeón

**Cambios técnicos:**

- **`renderAreaItem()`** (línea ~508):
  - Añadidos data attributes: `data-area-id`, `data-area-position`, `data-variation-id` (condicional)
  
- **`renderByColor()`** (línea ~667):
  - Añadidos al `.wpdm-color-accordion`: `data-variation-id`, `data-color`, `data-size`
  
- **`updateImagesTab()`** (línea ~1037):
  - Cambiada búsqueda de `.wpdm-variation-accordion` a `.wpdm-color-accordion`
  - Obtención de color/talla desde data attributes: `$accordion.data('color')`, `$accordion.data('size')`

**Resultado:**
- ✅ Modo GLOBAL: Funciona perfecto
- ✅ Modo POR COLOR: Ahora también funciona perfecto
- ✅ Nombres de áreas se muestran correctamente
- ✅ Información de color/talla visible en modo per-color

**Archivos modificados:**
- `includes/class-wpdm-customization-frontend.php`
- `woo-prices-dynamics-makito.php` (v2.11.1)
- `CHANGELOG.md`

---

## [2.11.0] - 2025-12-04

### ✨ Mejora significativa - Tab "DISEÑO" completo (PANTONE + Imágenes + Observaciones)

**Tab renombrado de "IMÁGENES" a "DISEÑO"** para reflejar mejor su contenido completo.

**Nueva estructura por área de marcaje:**

Cada área habilitada ahora muestra un bloque completo con:

1. **🎨 Colores PANTONE** (dinámico según número de colores seleccionados)
   - Campos individuales por cada color (Color 1, Color 2, Color 3, etc.)
   - Placeholder: "O indique PANTONE"
   - Se generan automáticamente según el valor seleccionado en el dropdown de colores
   - Almacenamiento en tiempo real de valores

2. **📸 Adjuntar imagen**
   - Upload de archivos (JPG, PNG, PDF - máx. 5MB)
   - Preview en tiempo real para imágenes
   - Indicador visual para PDFs
   - Botón "Eliminar" para quitar archivo
   - Validación de tipo y tamaño

3. **📝 Observaciones**
   - Textarea multi-línea por área
   - Placeholder descriptivo
   - Almacenamiento automático de cambios

**Funcionamiento según modo:**

- **MODO GLOBAL:** 
  - Un bloque completo por cada área habilitada
  - Los datos se aplican a todos los colores/tallas del pedido
  
- **MODO POR COLOR:**
  - Un bloque por cada combinación área + color + talla
  - Identificador visual: "🔴 Color: Rojo | Talla: M"
  - Permite diseños diferentes por cada variación

**Sistema de almacenamiento unificado:**

- Nuevo objeto `designData` reemplaza a `uploadedImages`
- Estructura por bloque:
  ```javascript
  {
    areaId: number,
    areaIndex: number,
    variationId: number|null,
    mode: 'global'|'per-color',
    pantones: [{colorNum: 1, value: "PANTONE 185C"}, ...],
    image: File|null,
    observations: string
  }
  ```
- Clave única: `area-{index}` (global) o `area-{index}-var-{variationId}` (per-color)
- Almacenamiento en `$modal.data('design-data')`
- Event listeners para cambios en tiempo real

**Mejoras UI:**

- Diseño tipo "card" por área con bordes azules y sombras
- Headers con iconos descriptivos (📐 🎨 📸 📝)
- Separadores visuales entre secciones
- Preview de imagen mejorado (200x200px, bordes redondeados)
- Campos de texto con estilos consistentes
- Responsive y con scroll independiente

**Actualización automática:**

- Se regenera al habilitar/deshabilitar áreas
- Se actualiza al cambiar número de colores
- Se actualiza al cambiar entre modo global/per-color
- Mantiene valores ingresados durante la sesión

**Preparación para siguiente fase:**

- Estructura completa lista para envío al servidor
- Datos organizados por área/variación
- Fácil integración con endpoint de guardado
- Compatible con sistema de carrito existente

**Archivos modificados:**

- `includes/class-wpdm-customization-frontend.php`:
  - Tab renombrado a "DISEÑO" (línea ~1359)
  - Función `updateImagesTab()` completamente rediseñada (línea ~964)
  - Nuevo sistema de almacenamiento `designData` (línea ~1053)
  - Event listeners para PANTONE y observaciones (línea ~1170)
  - Función auxiliar `getDesignKey()` (línea ~1058)
  - Función `saveDesignData()` (línea ~1067)
- `woo-prices-dynamics-makito.php` (v2.11.0)
- `CHANGELOG.md`

**Notas:**

Esta versión replica fielmente el comportamiento de Makito en cuanto a campos de diseño por área. La siguiente fase será enviar estos datos al servidor cuando se añada al carrito (Paso 7).

---

## [2.10.0] - 2025-12-04

### ✨ Nueva funcionalidad - Tab de IMÁGENES

**Implementación de subida de imágenes por área de marcaje:**

**Características principales:**
- ✅ Tercer tab "IMÁGENES" añadido al modal de personalización
- ✅ Interfaz dinámica que se adapta al modo de personalización:
  - **Modo GLOBAL:** Una imagen por cada área habilitada (se aplica a todos los colores)
  - **Modo POR COLOR:** Una imagen por cada combinación de área + color/talla
- ✅ Preview de imágenes en tiempo real (JPG, PNG)
- ✅ Soporte para archivos PDF con indicador visual
- ✅ Validaciones:
  - Tipos de archivo: JPG, PNG, PDF
  - Tamaño máximo: 5MB por archivo
- ✅ Botón "Eliminar" para quitar imágenes subidas
- ✅ Actualización automática del contenido al:
  - Habilitar/deshabilitar áreas
  - Cambiar técnica de marcaje
  - Cambiar entre modo global y por color
- ✅ Almacenamiento temporal de archivos con claves únicas
- ✅ Interfaz responsive con scroll independiente

**Detalles técnicos:**
- Función `updateImagesTab()` regenera dinámicamente la lista de uploads
- Event listeners para `change` en checkboxes de áreas y radio buttons de modo
- Objeto `uploadedImages` almacena archivos con claves: `area-{id}` o `area-{id}-var-{variationId}`
- Preview usando FileReader API para imágenes
- Validación client-side antes de almacenar archivos

**Interfaz de usuario:**
- Diseño coherente con el estilo del modal existente
- Información contextual por cada upload (área, técnica, color/talla)
- Mensajes informativos cuando no hay áreas seleccionadas
- Transiciones suaves y feedback visual
- Iconos y badges para mejor UX

**Preparación para siguiente fase:**
- Estructura lista para enviar archivos al servidor
- Datos almacenados en `$modal.data('uploaded-images')`
- Fácil integración con endpoint AJAX de guardado

**Archivos modificados:**
- `includes/class-wpdm-customization-frontend.php`:
  - Añadido tercer tab en HTML (línea ~1175)
  - Función `updateImagesTab()` (línea ~957)
  - Event listeners para upload y preview (línea ~1053)
  - Actualización automática en cambios de modo/áreas
- `woo-prices-dynamics-makito.php` (v2.10.0)
- `CHANGELOG.md`

**Notas:**
Esta versión implementa la interfaz completa de subida de imágenes. La siguiente fase será enviar las imágenes al servidor y asociarlas con el pedido cuando se añada al carrito.

## [2.9.3] - 2025-12-03

### 🐛 Fix - Desbordamiento de barra de tabs

**Problema:**
- La barra de fondo de los tabs sobresalía del popup por ambos lados
- Margen negativo `-30px` causaba que se extendiera fuera del contenedor

**Solución:**
- Cambiado `margin: -30px -30px 20px -30px` a `margin: -20px 0 20px 0`
- Ajustado padding interno para mantener espaciado
- Tabs ahora contenidas perfectamente dentro del modal

**Archivos modificados:**
- `includes/class-wpdm-customization-frontend.php`
- `woo-prices-dynamics-makito.php` (v2.9.3)

## [2.9.2] - 2025-12-03

### 🎨 Mejora UI - Diseño profesional de tabs

**Cambios visuales:**

**Tab activo:**
- Fondo blanco puro con sombra elevada
- Texto azul corporativo en MAYÚSCULAS con espaciado
- Borde inferior conectado al contenido
- Font-weight 700 para mayor énfasis

**Tab inactivo:**
- Fondo gris muy claro (#f8f9fa)
- Texto gris medio (#6c757d)
- Sin sombra ni bordes visibles

**Efectos interactivos:**
- Hover: Fondo se oscurece y el tab sube ligeramente (translateY -2px)
- Transiciones suaves (0.3s ease) en todos los cambios
- Cambio visual claro entre estados

**Resultado:**
- Pestañas con aspecto más moderno y profesional
- Mejor feedback visual para el usuario
- Separación clara entre tab activo e inactivo

**Archivos modificados:**
- `includes/class-wpdm-customization-frontend.php`
- `woo-prices-dynamics-makito.php` (v2.9.2)

## [2.9.0 - 2.9.1] - 2025-12-03

### ✨ Implementación completa - Sistema de Tabs funcional

**Problema inicial:**
- El sistema de tabs no funcionaba por caché de CSS
- Los estilos externos no se aplicaban correctamente
- Necesidad de forzar la aplicación de estilos

**Solución implementada:**
- Estilos críticos aplicados **inline** directamente en el HTML
- JavaScript mejorado para forzar la visibilidad correcta
- Actualización de versión para forzar recarga de assets

**Características finales:**

1. **Tab "Áreas"** (por defecto):
   - Resumen visual grande con gradiente
   - Total de personalización destacado (2.2em, azul)
   - Scroll automático si el contenido crece
   - Mensaje invitando a ver desglose detallado

2. **Tab "Desglose de Precios"**:
   - Desglose completo por área
   - Scroll independiente (max-height: 40vh)
   - Toda la información detallada de costos

3. **Scrollbars personalizados:**
   - Ambos tabs con scroll azul corporativo
   - Ancho 8px para mejor visibilidad

**JavaScript mejorado:**
- Forzado de display con CSS inline
- Manejo correcto de clases active
- Aplicación de estilos visuales al cambiar tabs
- Console.log para debugging

**Archivos modificados:**
- `includes/class-wpdm-customization-frontend.php`
- `assets/css/wpdm-customization.css`
- `woo-prices-dynamics-makito.php` (v2.9.0, 2.9.1)

## [2.8.0] - 2025-12-03

### ✨ Nueva funcionalidad - Sistema de Tabs en Footer (Concepto inicial)

**Problema identificado:**
- El desglose de precios ocupaba demasiado espacio
- Impedía ver y añadir nuevas áreas cuando había múltiples personalizaciones
- Los scrolls independientes no resolvían completamente el problema de UX
- Usuario solicitó separación clara entre configuración y visualización

**Solución implementada: Sistema de Tabs (Pestañas)**

El footer del modal ahora tiene **dos pestañas independientes**:

### **Tab 1: "Áreas"**
- Pestaña activa por defecto
- Muestra un **resumen simple** del total de personalización
- Permite trabajar con las áreas sin distracciones
- Vista limpia con total destacado en grande
- Mensaje: "Ver pestaña 'Desglose de Precios' para más detalles"

**Contenido:**
```
┌────────────────────────────────────┐
│ Total Personalización:              │
│     260,00 €                        │
│                                     │
│ Ver pestaña "Desglose de Precios"  │
│ para más detalles                   │
└────────────────────────────────────┘
```

### **Tab 2: "Desglose de Precios"**
- Muestra el **desglose completo y detallado** de todos los costos
- Precio base del producto
- Personalización por cada área:
  - Técnica (unidades × precio)
  - Colores adicionales
  - Cliché / Repetición cliché
  - Importe mínimo (si aplica)
  - Subtotal por área
- Total general

**Contenido:**
```
┌────────────────────────────────────┐
│ Precio base producto:     34,05 €  │
│ PERSONALIZACIÓN:         260,00 €  │
│                                     │
│   » Área 1                          │
│   DIGITAL 360 (15 uds × 0,400 €)   │
│   ⚠ Importe mínimo: 35,00 €        │
│   Cliché fotolito: 30,00 €         │
│   Subtotal área: 65,00 €           │
│                                     │
│   » Área 2...                       │
│   » Área 3...                       │
│                                     │
│ TOTAL:                    294,05 €  │
└────────────────────────────────────┘
```

**Beneficios:**

✅ **Siempre visible:** Las áreas ya no se ocultan por el desglose  
✅ **Flujo claro:** Configurar áreas en una pestaña, ver detalle en otra  
✅ **Sin scrolls confusos:** Cada pestaña con su propio espacio  
✅ **UX mejorada:** Separación clara entre acción y revisión  
✅ **Responsive:** Funciona perfectamente en móviles  
✅ **Total siempre visible:** En ambas pestañas  

**Cambios técnicos:**

1. **CSS:**
   - `.wpdm-modal-tabs`: Contenedor de pestañas con borde inferior
   - `.wpdm-modal-tab`: Estilos para cada pestaña (inactiva/activa)
   - `.wpdm-modal-tab-content`: Contenido de cada pestaña
   - `.wpdm-price-simple-summary`: Resumen visual grande en pestaña Áreas

2. **HTML:**
   - Footer dividido en dos tabs con contenido independiente
   - Tab "Áreas": Total simple destacado
   - Tab "Desglose": Desglose completo como antes
   - Botones de acción (Cancelar/Añadir) siempre visibles

3. **JavaScript:**
   - Event listener para cambio de tabs
   - Actualización de totales en ambas pestañas simultáneamente
   - Log de console para debugging

**Archivos modificados:**
- `assets/css/wpdm-customization.css`: Nuevos estilos para tabs
- `includes/class-wpdm-customization-frontend.php`: Estructura HTML y JavaScript
- `woo-prices-dynamics-makito.php`: Versión actualizada a 2.8.0

**Navegación:**
- Clic en "Áreas" → Ver total simple y trabajar con áreas
- Clic en "Desglose de Precios" → Ver desglose completo
- Tab activo destacado con borde azul inferior

## [2.7.1] - 2025-12-03

### 🐛 Fix crítico - Scrolls responsivos ajustados

**Problema identificado:**
- En v2.7.0, el desglose de precios seguía creciendo sin control
- El footer ocupaba todo el espacio disponible
- Las áreas quedaban ocultas y no se podían añadir más
- No era responsive

**Causa:**
- Estilos inline en PHP sobrescribían los CSS
- Footer sin límite estricto de altura
- Desglose interno de áreas sin límite

**Solución implementada:**

1. **Body (Áreas) - MÁS ESPACIO:**
   - `max-height: 50vh` (antes 40vh)
   - `min-height: 350px` (antes 250px)
   - `!important` para forzar sobre inline styles

2. **Footer (Resumen de precios) - LIMITADO:**
   - `max-height: 35vh` (antes 40vh)
   - `min-height: 200px` (nuevo)
   - `flex: 0 0 auto` para que NO crezca
   - `!important` en todas las propiedades

3. **Desglose interno - MUY LIMITADO:**
   - `max-height: 200px` (antes 300px)
   - Con scroll propio si hay muchas áreas

**Distribución de espacio ahora:**
```
┌────────────────────────────┐
│ Header (fixed)              │ ~10vh
├────────────────────────────┤
│ 📜 ÁREAS (scroll 50vh)      │ 50vh ⭐ MÁS ESPACIO
│ ▢ Área 1                    │
│ ▢ Área 2                    │
│ ▢ Área 3                    │
│ ▢ ...                       │
│ [Siempre visible]           │
├────────────────────────────┤
│ 💰 FOOTER (scroll 35vh)     │ 35vh ⭐ LIMITADO
│   Base: XX €                │
│   ┌──────────────────────┐ │
│   │ Desglose (200px max) │ │ ⭐ MUY LIMITADO
│   │ » Área 1: XX €       │ │
│   │ » Área 2: XX €       │ │
│   │ (scroll interno)     │ │
│   └──────────────────────┘ │
│   TOTAL: XXX €              │
│   [Botones]                 │
└────────────────────────────┘
```

**Resultado:**
- ✅ Body ocupa 50vh → Más espacio para ver/añadir áreas
- ✅ Footer limitado a 35vh → No crece sin control
- ✅ Desglose limitado a 200px → Scroll interno si hay muchas áreas
- ✅ Siempre se pueden añadir nuevas áreas
- ✅ Responsive en móviles

**Archivos modificados:**
- `assets/css/wpdm-customization.css`
- `woo-prices-dynamics-makito.php` (v2.7.1)

## [2.7.0] - 2025-12-03

### 🎨 Mejora UI - Scrolls independientes para áreas y desglose de precios

**Problema identificado:**
- Cuando se añaden múltiples áreas, el desglose de precios en el footer crece mucho
- Esto impide ver y añadir más áreas, ya que el footer tapa el contenido superior
- No había forma de navegar entre las áreas cuando el desglose era extenso

**Solución implementada: Tres scrolls independientes**

1. **Scroll superior (Áreas de marcaje):**
   - Zona donde se configuran las áreas
   - `max-height: 40vh`
   - Scrollbar personalizado gris

2. **Scroll medio (Footer general):**
   - Contenedor principal del footer
   - `max-height: 40vh`
   - Scrollbar azul corporativo

3. **Scroll interno (Desglose de áreas):**
   - Solo para el desglose detallado de precios por área
   - `max-height: 300px`
   - Scrollbar azul corporativo más delgado

**Beneficios:**
- ✅ Siempre se pueden ver y añadir nuevas áreas
- ✅ El desglose de precios no tapa las áreas
- ✅ Navegación fluida incluso con 5+ áreas personalizadas
- ✅ Scrollbars personalizados para mejor UX
- ✅ Colores diferenciados: gris para áreas, azul para precios

**Ejemplo de uso:**
```
┌─────────────────────────────────────┐
│ Header                               │
├─────────────────────────────────────┤
│ 📜 SCROLL 1: Áreas (40vh)           │
│ ▢ Área 1 [expandir/colapsar]        │
│ ▢ Área 2 [expandir/colapsar]        │
│ ▢ Área 3 [expandir/colapsar]        │
│ ▢ ... (scroll gris)                 │
├─────────────────────────────────────┤
│ 💰 Footer (40vh max)                │
│   Base: 36,32 €                     │
│   ┌───────────────────────────────┐ │
│   │ 📜 SCROLL 3: Desglose (300px) │ │
│   │ » Área 1: 75,00 €             │ │
│   │ » Área 2: 90,00 €             │ │
│   │ » Área 3: 65,00 €             │ │
│   │ ... (scroll azul)             │ │
│   └───────────────────────────────┘ │
│   TOTAL: 266,32 €                   │
│   [Cancelar] [Añadir al carrito]    │
└─────────────────────────────────────┘
```

**Archivos modificados:**
- `assets/css/wpdm-customization.css`: Nuevos estilos para scrolls independientes
- `woo-prices-dynamics-makito.php`: Versión actualizada a 2.7.0

**Responsive:**
- Los scrolls se adaptan en móviles manteniendo la funcionalidad

## [2.6.5] - 2025-12-03

### 🎨 Mejora UI - Simplificación de badge de importe mínimo

**Cambio:**
- Eliminado el comentario "El cliché se suma aparte" del badge amarillo de importe mínimo
- El badge ahora solo muestra: "⚠ Importe mínimo de técnica: X,XX €"

**Resultado:**
```
┌───────────────────────────────────┐
│ ⚠ Importe mínimo de técnica: 45,00 € │
└───────────────────────────────────┘
```

Más limpio y directo. El desglose visual ya deja claro que el cliché se suma después.

**Archivos modificados:**
- `includes/class-wpdm-customization-frontend.php` (línea 907)
- `woo-prices-dynamics-makito.php` (v2.6.5)

## [2.6.4] - 2025-12-03

### 🐛 Corrección CRÍTICA - Importe mínimo solo para técnica

**Problema identificado:**
- En v2.6.3, el importe mínimo se aplicaba al total (técnica + colores + cliché)
- **Incorrecto:** Si (técnica + colores + cliché) < mínimo, entonces total = mínimo

**Lógica correcta:**
- El importe mínimo se aplica SOLO a (técnica + colores extra)
- El cliché se suma DESPUÉS de aplicar el mínimo
- **Correcto:** Si (técnica + colores) < mínimo, entonces (técnica + colores) = mínimo, luego + cliché

**Ejemplo corregido:**

```
Cálculo v2.6.3 (❌ INCORRECTO):
1 ud × 0,625€ = 0,625€
Cliché 30€
Total calculado: 30,625€
Mínimo: 35€
Total final: 35€ ❌ (no suma correctamente)

Cálculo v2.6.4 (✅ CORRECTO):
1 ud × 0,625€ = 0,625€
⚠ Importe mínimo de técnica: 35€ ✅
+ Cliché 30€
Total final: 65€ ✅
```

**Cambios implementados:**

1. **Backend (`calculate_area_price()`):**
   ```php
   // Calcular técnica + colores
   $technique_and_colors_total = $technique_total_price + $color_extra_total;
   
   // Aplicar mínimo SOLO a técnica + colores
   if ($min > 0 && $technique_and_colors_total < $min) {
       $technique_and_colors_total = $min;
       $minimum_applied = true;
   }
   
   // Sumar cliché DESPUÉS
   $area_total = $technique_and_colors_total + $cliche_price + $cliche_repetition_price;
   ```

2. **Frontend (desglose visual):**
   - Técnica
   - Colores adicionales
   - ⚠ **Badge amarillo: "Importe mínimo de técnica: X €"** (si se aplica)
   - Nota aclaratoria: "El cliché se suma aparte"
   - Cliché fotolito / Repetición cliché
   - Subtotal área

**Orden del desglose ahora:**
```
» Área 1
DIGITAL 360 (1 uds × 0,625 €)         0,62 €
┌──────────────────────────────────────────┐
│ ⚠ Importe mínimo de técnica: 35,00 €    │
│ El cliché se suma aparte                 │
└──────────────────────────────────────────┘
Cliché fotolito (1 colores × 30,00 €)  30,00 €
────────────────────────────────────────────
Subtotal área:                         65,00 €
```

**Archivos modificados:**
- `includes/class-wpdm-customization.php`: Refactorización de cálculo (líneas 265-375)
- `includes/class-wpdm-customization-frontend.php`: Reordenamiento del desglose (líneas 872-905)
- `woo-prices-dynamics-makito.php`: Versión actualizada a 2.6.4

## [2.6.3] - 2025-12-03

### 🐛 Corrección CRÍTICA - Importe Mínimo por Técnica

**Problema identificado:**
- El campo `min` de la técnica se estaba interpretando incorrectamente como **cantidad mínima de unidades**
- En realidad, `min` es un **IMPORTE MÍNIMO en euros**, no una cantidad

**Error en versión 2.6.2:**
```php
// ❌ INCORRECTO: Se aplicaba como cantidad de unidades
if ($min > 0 && $total_quantity < $min) {
    $quantity_for_technique = $min; // Tratando 35€ como 35 unidades
}
```

**Lógica correcta implementada:**
```php
// ✅ CORRECTO: Se aplica como importe mínimo
$area_total = $technique_total_price + $color_extra_total + $cliche_price + $cliche_repetition_price;

if ($min > 0 && $area_total < $min) {
    $area_total = $min; // Si el total es 13€ y el mínimo es 35€, se cobra 35€
    $minimum_applied = true;
}
```

**Ejemplo corregido:**
- **Cálculo real:** 1 ud × 0,625€ + Cliché 30€ = **30,625€**
- **Mínimo técnica:** 35,00€
- **Total a cobrar:** **35,00€** (se aplica el importe mínimo)
- **Indicador visual:** Se muestra un badge amarillo "⚠ Importe mínimo aplicado: 35,00 €"

**Cambios realizados:**
- `calculate_area_price()`: El mínimo se verifica AL FINAL, comparando el total del área vs el importe mínimo
- Nuevo campo: `minimum_amount` (importe mínimo configurado)
- Frontend: Badge amarillo con el mensaje "⚠ Importe mínimo aplicado: X,XX €"
- Los precios unitarios se mantienen igual, solo se ajusta el total final del área

**Archivos modificados:**
- `includes/class-wpdm-customization.php` (líneas 265-375)
- `includes/class-wpdm-customization-frontend.php` (líneas 860-915)
- `woo-prices-dynamics-makito.php` (v2.6.3)

## [2.6.2] - 2025-12-03

### 🐛 Correcciones Críticas

#### **Fix: Cálculo de cantidad mínima por técnica**

**Problema reportado:**
- El precio unitario de la técnica cambiaba incorrectamente cuando se activaba la repetición de cliché
- Ejemplo: Con cliché normal (30€) el precio era 0,625€, pero con repetición de cliché se convertía en 2,50€
- La cantidad mínima de la técnica no se estaba aplicando correctamente

**Causa raíz:**
- La lógica de cantidad mínima se aplicaba AL FINAL del cálculo, ajustando retroactivamente el precio unitario
- Esto causaba inconsistencias al dividir el ajuste entre la cantidad real en lugar de usar el mínimo desde el principio

**Solución implementada:**
1. **Aplicación temprana del mínimo:** El mínimo ahora se aplica ANTES de calcular precios
2. **Cantidad efectiva:** Si `total_quantity < min`, se usa `min` como `quantity_for_technique`
3. **Precio unitario consistente:** El precio unitario ya no se ajusta retroactivamente
4. **Indicador visual:** Se muestra "⚠ Mínimo" en el desglose cuando se aplica la cantidad mínima

**Cambios técnicos:**
- `calculate_area_price()` ahora determina `quantity_for_technique = max(total_quantity, min)`
- El precio de la técnica se calcula con `quantity_for_technique` (respetando el mínimo)
- Los colores extra se cobran por la cantidad REAL solicitada, no por el mínimo
- Nuevos campos en respuesta: `quantity_used` (cantidad usada para el cálculo) y `minimum_applied` (boolean)
- El frontend muestra un indicador visual "⚠ Mínimo" cuando `minimum_applied === true`

**Resultado:**
- El precio unitario de la técnica ahora es **consistente** independientemente de si hay cliché o repetición
- La cantidad mínima se aplica correctamente, garantizando que se cobra al menos el mínimo configurado
- Los clientes ven claramente cuándo se está aplicando una cantidad mínima en el desglose de precios

**Archivos modificados:**
- `includes/class-wpdm-customization.php`: Refactorización de `calculate_area_price()` (líneas 265-375)
- `includes/class-wpdm-customization-frontend.php`: Actualización del desglose de precios (líneas 860-877)
- `woo-prices-dynamics-makito.php`: Versión actualizada a 2.6.2

**Testing recomendado:**
- [ ] Verificar precio con cantidad < mínimo (debe aplicarse el mínimo)
- [ ] Verificar precio con cantidad > mínimo (debe usar la cantidad real)
- [ ] Comparar precio unitario con cliché normal vs repetición (debe ser igual)
- [ ] Verificar indicador "⚠ Mínimo" en el desglose

## [2.3.4] - 2025-01-02

### 🎉 Versión Mayor - Sistema de Personalización de Productos (Fase 1 Completa)

Esta versión introduce la **Fase 1** del sistema de personalización de productos con áreas de marcaje y técnicas de marcación.

### ✨ Nuevas Características Implementadas

- **Sistema de botones de personalización:**
  - Dos botones lado a lado en la tabla de variaciones: "Añadir sin personalizar" y "Añadir con personalización"
  - Botones con estilo consistente (clase `button alt`)
  - Habilitación/deshabilitación automática según cantidades seleccionadas
  - Posicionamiento responsive con flexbox

- **Modal interactivo de personalización:**
  - Modal con overlay oscuro y animación de apertura/cierre
  - Header con título "Personalizar Producto" y botón de cerrar (X)
  - Body con scroll automático para contenido largo
  - Footer con total de personalización y botones de acción
  - Estilos críticos inline con `!important` para garantizar visibilidad
  - Compatible con Elementor y otros page builders

- **Sistema de áreas de marcaje:**
  - Carga de áreas desde el meta `marking_areas` del producto (repeater de JetEngine)
  - Agrupación automática de áreas por `print_area_id` (evita duplicados)
  - Ordenamiento numérico de áreas (Area 1, Area 2, ..., Area 9)
  - Cada área muestra su posición, dimensiones máximas, máximo de colores e imagen
  - Checkboxes para activar/desactivar áreas
  - Expansión/colapso del formulario de cada área

- **Selector de técnicas de marcación:**
  - Dropdown con todas las técnicas disponibles para cada área
  - Carga desde el CPT `tecnicas-marcacion` usando `technique_ref`
  - Soporte para múltiples técnicas por área (ej: Area 8 con SERIGRAFIA y DIGITAL 360)
  - Opción "Selecciona una técnica..." como placeholder

- **Campos de personalización por área:**
  - **Técnica de marcación:** Dropdown con todas las opciones disponibles
  - **Número de colores:** Selector de 1 a N colores (respetando `max_colors`)
  - **Medida de impresión:** Inputs para ancho x alto en mm
  - **Repetición Cliché:** Checkbox para indicar repetición
  - **Observaciones:** Textarea para comentarios adicionales

- **Modo de personalización: Global vs Por Color:**
  - Pregunta inicial: "¿Desea marcar todos los colores de este artículo de la misma forma?"
  - Opción "Sí (Global)": Muestra las áreas una sola vez para todas las variaciones
  - Opción "No (Por color)": Crea un acordeón por cada variación seleccionada en la tabla
  - Detección automática de variaciones con cantidad > 0 (color + talla)
  - Acordeones colapsables con header azul mostrando "Color - Talla (cantidad uds)"
  - Solo un acordeón abierto a la vez para facilitar navegación
  - Event handling correcto: clics en elementos internos no cierran el acordeón

- **Integración con tabla de variaciones:**
  - Detección de variaciones seleccionadas desde la tabla (color + talla + cantidad)
  - Extracción de nombres de color desde `td.wpdm-table-row-label .wpdm-color-name`
  - Extracción de tallas desde headers de columnas (`thead th`)
  - Agrupación de variaciones por `variation_id` con suma de cantidades

### 🔧 Mejoras Técnicas

- **Arquitectura de clases:**
  - `WPDM_Customization`: Lógica de backend (AJAX, cálculos, datos)
  - `WPDM_Customization_Frontend`: Lógica de frontend (modal, UI, eventos)
  - Separación clara de responsabilidades

- **Endpoints AJAX:**
  - `wpdm_get_customization_data`: Obtiene áreas y técnicas del producto
  - `wpdm_calculate_customization_price`: Calcula precios (pendiente implementar)
  - `wpdm_upload_customization_image`: Upload de imágenes (pendiente implementar)
  - `wpdm_add_customized_to_cart`: Añade al carrito con personalización (pendiente implementar)

- **JavaScript inline:**
  - Todo el código JS está inline en el modal para evitar problemas de carga
  - Event listeners con `$(document).on()` para elementos dinámicos
  - `$(document).off()` antes de re-enlazar eventos para evitar duplicados
  - Uso de `$modal.data()` para almacenar estado (áreas, variaciones seleccionadas)
  - Funciones auxiliares: `renderGlobal()`, `renderByColor()`, `renderAreaItem()`

- **Manejo de datos:**
  - Agrupación de áreas por `print_area_id` en PHP usando `usort()`
  - Ordenamiento numérico con regex: `/\d+/` para extraer números de "Area X"
  - Almacenamiento de técnicas como array en cada área agrupada
  - Detección robusta de variaciones con fallbacks múltiples

### 🐛 Correcciones

- Corregido: Modal no visible (faltaba `display: block !important`)
- Corregido: Scroll no funcionaba en modal (añadido `overflow-y: auto`)
- Corregido: Áreas duplicadas cuando tienen múltiples técnicas (agrupación por `print_area_id`)
- Corregido: Campo Pantone eliminado (no corresponde en este flujo)
- Corregido: Áreas desordenadas (implementado ordenamiento numérico)
- Corregido: Color vacío en modo por color (selector incorrecto, ahora usa `.wpdm-color-name`)
- Corregido: Acordeones se cierran al hacer clic dentro (añadido `e.stopPropagation()`)
- Corregido: Función `hideNotification` no definida en tabla de variaciones (añadido `var self = this`)

### 📋 Pendiente para Fase 2

- Upload de imágenes por área
- Cálculo de precios en tiempo real (técnica, cliché, colores adicionales)
- Validación de campos obligatorios
- Añadir al carrito con datos de personalización
- Guardar personalización en meta del pedido
- Mostrar personalización en el carrito y en el pedido

### 🔄 Versiones de desarrollo (2.0.0 - 2.3.4)

Durante el desarrollo se crearon múltiples versiones para debugging:
- 2.0.0-2.0.9: Implementación inicial del modal y botones
- 2.1.0: Mejoras en estilos y posicionamiento de botones
- 2.2.0-2.2.2: Implementación de campos completos y agrupación de áreas
- 2.3.0-2.3.4: Implementación de modo por color con acordeones
  - Visualización de información de personalización en el carrito

- **Integración con pedidos:**
  - Datos completos de personalización guardados en el pedido
  - Metadatos detallados por área (técnica, colores, dimensiones, imágenes)
  - Resumen de personalización para fácil visualización en admin
  - Precio de personalización guardado por separado

### 🔧 Mejoras Técnicas

- **Nuevas clases:**
  - `WPDM_Customization`: Gestión de personalización (obtener áreas, técnicas, calcular precios)
  - `WPDM_Customization_Frontend`: Frontend y modal de personalización

- **Nuevos archivos:**
  - `assets/js/wpdm-customization.js`: JavaScript del modal y lógica de personalización
  - `assets/css/wpdm-customization.css`: Estilos del modal y formulario

- **Endpoints AJAX:**
  - `wpdm_get_customization_data`: Obtener áreas y técnicas disponibles
  - `wpdm_calculate_customization_price`: Calcular precio de personalización
  - `wpdm_upload_customization_image`: Subir imágenes de personalización
  - `wpdm_add_customized_to_cart`: Añadir producto personalizado al carrito

- **Modificaciones en clases existentes:**
  - `WPDM_Cart_Adjustments`: Aplicación de precios de personalización en carrito
  - `WPDM_Order_Meta`: Guardado de personalización en pedidos

### 📦 Estructura de Datos

- **Áreas de marcaje:** Repeater `marking_areas` en producto con campos:
  - `print_area_id`, `technique_ref`, `position`, `max_colors`, `width`, `height`, `area_img`
  
- **Técnicas de marcación:** CPT `tecnicas-marcacion` con:
  - Campos: `technique_ref`, `col_inc`, `cliche`, `cliche_repetition`, `min`, `code`
  - Repeater `precio_escalas`: `section_desde`, `section_hasta`, `price`, `price_col`, `price_cm`

### 🎨 Mejoras de UX

- Modal responsive y moderno
- Cálculo de precios en tiempo real
- Validación de campos antes de añadir al carrito
- Notificaciones de éxito/error
- Vista previa de imágenes subidas
- Interfaz intuitiva y clara

### 📝 Notas

- El coste de cliché se aplica por cada área de trabajo (cada área lleva su fotolito)
- Las imágenes se guardan en carpeta independiente para facilitar limpieza periódica
- Compatible con productos simples y variables
- No interfiere con la tabla de variaciones existente

---

## [1.4.1] - 2025-01-XX

### 🐛 Correcciones

- **Ocultación del formulario estándar de WooCommerce:**
  - El formulario estándar de variaciones de WooCommerce ahora se oculta automáticamente cuando la tabla personalizada está activa
  - Evita confusión al tener dos formas de añadir productos al carrito
  - Implementado con CSS y JavaScript para máxima compatibilidad
  - Elementos ocultados: `.single_variation_wrap`, `.variations_button`, `.woocommerce-variation-add-to-cart`

---

## [1.4.0] - 2025-01-XX

### ✨ Nuevas Características

- **Panel de configuración para umbrales y colores de stock:**
  - Nueva sección en el panel de administración para personalizar la visualización de stock
  - **Umbral de stock bajo:** Configurable desde 1 a 1000 unidades (por defecto: 50)
  - **Color para stock alto:** Selector de color personalizable (por defecto: #28a745 - verde)
  - **Color para stock bajo:** Selector de color personalizable (por defecto: #ff8c00 - naranja)
  - **Color para sin stock:** Selector de color personalizable (por defecto: #dc3545 - rojo)
  - Cada campo incluye un selector de color visual y un campo de texto para valores hexadecimales
  - Validación de colores en formato hexadecimal (#RRGGBB)

### 🔧 Mejoras Técnicas

- **Nuevas constantes de opciones:**
  - `OPTION_STOCK_THRESHOLD`: Umbral de stock bajo
  - `OPTION_STOCK_HIGH_COLOR`: Color para stock alto
  - `OPTION_STOCK_LOW_COLOR`: Color para stock bajo
  - `OPTION_STOCK_NONE_COLOR`: Color para sin stock

- **Funciones de sanitización:**
  - `sanitize_stock_threshold()`: Valida y limita el umbral entre 1 y 1000
  - `sanitize_color()`: Valida formato hexadecimal de colores

- **Integración dinámica:**
  - Los colores se aplican dinámicamente desde las opciones de configuración
  - El umbral se lee desde la configuración en tiempo de ejecución
  - Valores por defecto si no están configurados

---

## [1.3.9] - 2025-01-XX

### 🎨 Mejoras de Diseño

- **Sistema de colores para indicar nivel de stock:**
  - **Verde** (#28a745): Para mucho stock (>50 unidades) - indica disponibilidad alta
  - **Naranja** (#ff8c00): Para poco stock (≤50 unidades) - indica disponibilidad limitada
  - **Rojo** (#dc3545): Para sin stock (0 unidades) - muestra "NO" en lugar de "Stock: 0"
  - Umbral configurable: 50 unidades (puede ajustarse en el código)

### 🔧 Mejoras Técnicas

- **Mejora en la visualización de stock:**
  - Cuando no hay stock, muestra "NO" en lugar de "Stock: 0"
  - Clases CSS dinámicas según el nivel de stock: `wpdm-stock-high`, `wpdm-stock-low`, `wpdm-stock-none`
  - Texto más visible con font-weight ajustado según el estado
  - Mejor feedback visual para el cliente sobre la disponibilidad

---

## [1.3.8] - 2025-01-XX

### ✨ Nuevas Características

- **Visualización de stock en la tabla de variaciones:**
  - Muestra el stock disponible de cada variación debajo del input de cantidad
  - Formato: "Stock: xxxx" en texto pequeño y centrado
  - Maneja diferentes estados de stock:
    - Stock gestionado: muestra la cantidad exacta
    - Stock ilimitado: muestra "Stock: ∞"
    - Sin stock: muestra "Stock: 0"
  - Información visible para que el cliente sepa cuánto stock hay disponible en cada momento

### 🎨 Mejoras de Diseño

- **Mejora en la presentación de celdas:**
  - Layout vertical mejorado con el input y el stock apilados
  - Texto de stock en tamaño 0.65em para discreción
  - Centrado y alineado correctamente

---

## [1.3.7] - 2025-01-XX

### 🎨 Mejoras de Diseño

- **Mejora significativa en el mapeo y visualización de colores:**
  - Limpieza mejorada de nombres de colores con prefijos y modificadores
  - Soporte para colores compuestos: "azul claro", "gris oscuro", "marino oscuro", "verde botella", etc.
  - Capitalización correcta de nombres de colores (primera letra mayúscula, resto según corresponda)
  - Manejo de colores combinados con barra (ej: "naranja/azul")

### 🔧 Mejoras Técnicas

- **Nueva función `capitalize_color_name()`:**
  - Capitaliza correctamente nombres de colores simples y compuestos
  - Maneja colores con barras (ej: "Naranja/Azul")
  - Capitaliza cada palabra correctamente

- **Función `clean_color_name()` mejorada:**
  - Detecta y limpia patrones complejos: "AZC-AZUL CLARO" → "Azul Claro"
  - Maneja: "GROS-GRIS OSCURO" → "Gris Oscuro"
  - Maneja: "MROS-MARINO OSCURO" → "Marino Oscuro"
  - Maneja: "VEB-VERDE BOTELLA" → "Verde Botella"
  - Maneja: "NARA-NARANJA/AZUL" → "Naranja/Azul"

- **Mapeo de colores expandido:**
  - Añadidos colores compuestos: "gris oscuro", "marino oscuro", "verde botella", "dorado"
  - Soporte para colores combinados: "naranja/azul"
  - Búsqueda mejorada priorizando colores compuestos sobre simples

---

## [1.3.6] - 2025-01-XX

### 🎨 Mejoras de Diseño

- **Tamaño de fuente del nombre del color reducido:**
  - Tamaño de fuente reducido de 0.85em a 0.70em para mejor proporción visual
  - El nombre del color ahora es más discreto, dando más protagonismo a la imagen/swatch

### 🔧 Mejoras Técnicas

- **Limpieza automática de nombres de colores:**
  - Nueva función `clean_color_name()` que elimina prefijos y duplicados
  - Los nombres de colores ahora se muestran limpios:
    - "azul-azul" → "azul"
    - "bla-blanco" → "blanco"
    - "neg-negro" → "negro"
    - "ro-rojo" → "rojo"
  - Detecta y limpia múltiples patrones: prefijo-color, color-color, etc.
  - Primera letra en mayúscula para mejor presentación

---

## [1.3.5] - 2025-01-XX

### ✨ Nuevas Características

- **Configuración del tamaño del círculo de color:**
  - Nueva opción en el menú de configuración para personalizar el tamaño del círculo de color/imagen
  - Rango configurable: 20px a 100px
  - Valor por defecto: 36px (reducido desde 48px)
  - El tamaño se aplica tanto a imágenes como a swatches de color

### 🎨 Mejoras de Diseño

- **Tamaño por defecto reducido:**
  - Tamaño del círculo de color reducido de 48px a 36px por defecto
  - Mejor proporción visual en la tabla de variaciones
  - Más espacio para el texto del nombre del color

---

## [1.3.4] - 2025-01-XX

### 🔄 Cambios Estructurales

- **Inversión de estructura de la tabla de variaciones:**
  - Los colores ahora se muestran en las **filas** (vertical) en lugar de las columnas
  - Las tallas ahora se muestran en las **columnas** (horizontal)
  - Esto permite manejar productos con muchos colores (ej: 40 colores) sin que la tabla sea demasiado ancha
  - Las imágenes de colores se muestran ahora en las filas junto al nombre del color
  - Mejor experiencia de usuario para productos con muchas variaciones de color

### 🎨 Mejoras de Diseño

- **Ajustes de estilo para la nueva estructura:**
  - Imágenes de colores en filas con layout horizontal (imagen + texto)
  - Ancho mínimo aumentado para las filas de colores (180px)
  - Mejor alineación y espaciado en las filas de colores

---

## [1.3.3] - 2025-01-XX

### 🎨 Mejoras de Diseño

- **Mejora en la visualización de imágenes y colores:**
  - Imágenes de colores aumentadas de 32px a 48px para mayor visibilidad
  - Texto del nombre del color reducido a 0.65em para dar más prioridad a la imagen
  - Mejores sombras y bordes en imágenes y swatches de color
  - Efectos hover suaves en imágenes y swatches
  - Mejor espaciado y padding en los headers de colores
  - Jerarquía visual mejorada: imagen más prominente, texto más discreto

---

## [1.3.2] - 2025-01-XX

### 🎨 Mejoras de Diseño

- **Mejora en la visualización de colores en la tabla de variaciones:**
  - Detección automática de `pa_color` como atributo de columna
  - Imágenes de variaciones mostradas en las columnas de colores (no en las filas de tallas)
  - Mejora en la búsqueda de imágenes: prioriza imagen de variación, luego galería, luego producto padre
  - Color swatch genérico mejorado cuando no hay imagen disponible

### 🔧 Mejoras Técnicas

- **Detección mejorada de colores:**
  - Búsqueda de nombres de colores dentro de slugs con prefijos/sufijos (ej: "bl-blanco-br" detecta "blanco")
  - Priorización de coincidencias más largas y específicas en el mapeo de colores
  - Extracción inteligente del nombre del color desde slugs complejos

- **Búsqueda de imágenes optimizada:**
  - Búsqueda específica en variaciones con el color correspondiente
  - Verificación de que el atributo de columna sea `pa_color` antes de buscar imágenes
  - Fallback a imagen del producto padre si la variación no tiene imagen

---

## [1.3.1] - 2025-01-XX

### 🎨 Mejoras de Diseño

- **Rediseño completo de la tabla de variaciones:**
  - Diseño más moderno y elegante con gradientes sutiles
  - Tipografía más ligera y legible
  - Columnas con ancho mínimo para evitar desalineaciones
  - Efectos hover y transiciones suaves
  - Diseño responsive mejorado para móviles
  - Integración con colores globales de Elementor/WordPress

- **Integración con colores del tema:**
  - Uso de variables CSS globales de Elementor
  - Compatibilidad automática con colores del tema
  - Fallbacks para temas sin variables CSS
  - Consistencia visual con el diseño del sitio

### 🐛 Correcciones

- **Corregido problema del símbolo de moneda:**
  - El símbolo € ya no se muestra como `&euro;` cuando cambia de color/variación
  - Cambio de `.text()` a `.html()` para renderizar correctamente el símbolo
  - Formato de moneda correcto en todas las actualizaciones dinámicas

### 🔧 Mejoras Técnicas

- Mejorado CSS con variables CSS para fácil personalización
- Optimización de estilos para mejor rendimiento
- Mejor estructura de clases CSS para mantenimiento

---

## [1.3.0] - 2025-01-XX

### ✨ Nuevas Características

- **Sistema de caché para tramos de precio (deshabilitado temporalmente):**
  - Implementado caché usando transients de WordPress para mejorar el rendimiento
  - Expiración automática del caché cuando se actualiza un producto o sus meta fields
  - Reducción significativa de consultas a la base de datos
  - Función para limpiar todo el caché de tramos si es necesario
  - **Nota:** El caché está deshabilitado temporalmente debido a problemas con la selección de tramos. Se reactivará en una versión futura una vez resuelto.

- **Internacionalización mejorada:**
  - Formato de moneda ahora usa la configuración de WooCommerce
  - Soporte para diferentes posiciones del símbolo de moneda (left, right, left_space, right_space)
  - Soporte para separadores decimales y de miles personalizados
  - Eliminado formato hardcodeado de moneda en JavaScript

### 🔧 Mejoras

- **Optimización del carrito:**
  - Caché en memoria para precios calculados por grupo de producto
  - Evita recálculos innecesarios cuando el precio ya está aplicado correctamente
  - Verificación inteligente de cambios antes de actualizar productos en el carrito

- **Manejo de formato numérico:**
  - Soporte mejorado para números con coma como separador decimal (formato europeo: 2,27)
  - Conversión automática de coma a punto para cálculos internos
  - Compatibilidad con ambos formatos (coma y punto)

- **Validación de tramos:**
  - Ordenamiento automático de tramos por cantidad ascendente
  - Validación mejorada de datos de tramos

### 🐛 Correcciones

- Corregido problema con selección de tramos que causaba que siempre se aplicara el mismo precio
- Corregida lógica de selección de tramos para elegir correctamente el tramo más específico
- Mejorado manejo de tramos con formato numérico europeo (coma como separador decimal)

### 📝 Notas Técnicas

- El caché se limpia automáticamente cuando se actualiza un producto o sus meta fields
- Los precios se normalizan correctamente independientemente del formato de entrada
- Compatibilidad total con formatos numéricos europeos y americanos

---

## [1.2.2] - 2025-01-XX

### ✨ Nuevas Características

- **Notificación visual de éxito al añadir al carrito:**
  - Notificación tipo toast que aparece en la esquina superior derecha
  - Muestra mensaje de confirmación cuando se añaden productos
  - Incluye enlace directo a "Ver carrito"
  - Se auto-oculta después de 5 segundos
  - Botón para cerrar manualmente
  - Diseño responsive para móviles
  - Animación suave de entrada y salida

### 🎨 Mejoras de UX

- El usuario ahora recibe feedback visual claro cuando se añaden productos
- Notificación no intrusiva que no bloquea la interacción
- Enlace rápido al carrito para continuar comprando

---

## [1.2.1] - 2025-01-XX

### 🐛 Correcciones

- **Corregido error en evento `added_to_cart`:**
  - Eliminado error "Cannot use 'in' operator to search for 'length'"
  - Corregido formato del evento para que WooCommerce lo procese correctamente
  - El evento ahora se dispara con un objeto en lugar de parámetros individuales

### 🔧 Mejoras Técnicas

- Mejor manejo de eventos de WooCommerce
- Timeout aumentado para asegurar que el carrito se actualice

---

## [1.2.0] - 2025-01-XX

### 🔄 Cambio de Metodología - Añadir al Carrito

**Cambio importante en cómo se añaden las variaciones al carrito:**

- **Nueva metodología implementada:**
  - Ahora se usa un endpoint AJAX personalizado en lugar del endpoint de WooCommerce
  - Todos los items se envían de una vez al servidor
  - El precio se calcula en PHP basado en la suma total de todas las variaciones
  - El precio calculado se aplica directamente a cada variación al añadirla al carrito
  - Se guarda el precio en los datos del carrito para que persista

- **Ventajas de la nueva metodología:**
  - Más confiable: no depende del endpoint AJAX de WooCommerce que puede tener problemas
  - Control total sobre el precio: se aplica directamente al añadir
  - Más rápido: una sola petición en lugar de múltiples
  - El precio se guarda correctamente en el carrito desde el inicio

### 🐛 Correcciones

- Eliminado problema con el endpoint AJAX de WooCommerce
- Eliminado problema con atributos y nonces
- El precio ahora se aplica correctamente desde el momento de añadir al carrito

### 🔧 Mejoras Técnicas

- Endpoint AJAX personalizado `wpdm_add_table_to_cart`
- Uso directo de `WC()->cart->add_to_cart()` desde PHP
- Aplicación directa del precio en los datos del carrito
- Validación mejorada de variaciones antes de añadir

---

## [1.1.4] - 2025-01-XX

### 🐛 Correcciones Críticas

- **Corregido doble prefijo en atributos:**
  - Eliminado problema de `attribute_attribute_pa_color` → ahora es `attribute_pa_color`
  - Verificación si el atributo ya tiene el prefijo antes de añadirlo
  - `get_variation_attributes()` devuelve atributos sin prefijo, ahora se añade correctamente

- **Corregido problema del nonce:**
  - El nonce ahora se genera desde PHP usando `wp_create_nonce('woocommerce-add-to-cart')`
  - Se pasa directamente en los datos de la tabla
  - Fallback a `wc_add_to_cart_params` si está disponible
  - Error claro si no se encuentra el nonce

### 🔧 Mejoras Técnicas

- Generación del nonce desde PHP para mayor confiabilidad
- Mejor validación del formato de atributos
- Manejo de errores mejorado cuando falta el nonce

---

## [1.1.3] - 2025-01-XX

### 🐛 Correcciones

- **Corregido error al añadir variaciones al carrito:**
  - Mejorado obtención de atributos de variación usando `get_variation_attributes()`
  - Añadida validación de campos requeridos antes de enviar
  - Mejorado manejo de nonce de seguridad
  - Añadida validación de valores de atributos (no vacíos)
  - Mejor logging para identificar problemas con atributos

### 🔧 Mejoras Técnicas

- Uso de `get_variation_attributes()` en lugar de solo `get_attributes()` para obtener atributos en formato correcto
- Validación de que todos los campos requeridos estén presentes antes de enviar AJAX
- Mejor manejo de errores cuando faltan datos

---

## [1.1.2] - 2025-01-XX

### 🐛 Correcciones

- **Mejorado manejo de respuesta AJAX "TRUE":**
  - Ahora detecta correctamente cuando WooCommerce devuelve "TRUE" como respuesta exitosa
  - Mejorado parsing de respuestas (string, boolean, objeto)
  - Añadida validación de status HTTP 200
  - Mejor logging para debugging en consola
  - Eliminado mensaje "TRUE" que aparecía en el navegador

### 🔧 Mejoras Técnicas

- Mejor manejo de diferentes formatos de respuesta de WooCommerce
- Validación más robusta de respuestas AJAX
- Logging detallado en consola para facilitar debugging
- Verificación de status HTTP antes de considerar éxito

---

## [1.1.1] - 2025-01-XX

### 🐛 Correcciones

- **Corregido problema al añadir al carrito desde tabla de variaciones:**
  - Mejorado manejo de atributos de variación (ahora se obtienen directamente desde PHP)
  - Añadido mejor manejo de errores con mensajes descriptivos
  - Añadido timeout de 15 segundos para evitar cuelgues
  - Añadida pausa entre añadidos para evitar problemas de concurrencia
  - Mejorado logging en consola para debugging
  - Los atributos ahora se formatean correctamente según el formato que WooCommerce espera

### 🔧 Mejoras Técnicas

- Los atributos de variación se obtienen y formatean en PHP antes de enviarlos a JavaScript
- Mejor validación de respuestas AJAX de WooCommerce
- Manejo mejorado de errores con información más detallada

---

## [1.1.0] - 2025-01-XX

### ✨ Nuevas Características

- ✅ **Tabla de variaciones interactiva (colores x tallas)**
  - Visualización en formato tabla para seleccionar cantidades de múltiples variaciones
  - Columnas = colores (o segundo atributo), Filas = tallas (o primer atributo)
  - Inputs numéricos en cada celda para seleccionar cantidades
  - Totales por fila, columna y total general
  - Cálculo automático de precios según suma total de todas las variaciones

- ✅ **Cálculo de precios por suma total**
  - El precio se calcula basándose en la suma total de todas las variaciones seleccionadas
  - Ejemplo: 100 azules + 100 verdes + 400 amarillos = 600 unidades → precio del tramo de 600
  - El mismo precio unitario se aplica a todas las variaciones del mismo producto padre
  - Integración completa con la lógica de tramos existente

- ✅ **Shortcode `[wpdm_variation_table]`**
  - Permite insertar la tabla de variaciones manualmente en cualquier lugar
  - Uso: `[wpdm_variation_table]` o `[wpdm_variation_table product_id="123"]`
  - Funciona independientemente de la opción automática
  - Compatible con widgets, tabs, plantillas y editores de página

- ✅ **Opción en administración**
  - Nueva opción en WooCommerce → Precios Makito para activar/desactivar tabla de variaciones
  - Control independiente de la tabla de tramos de precios

### 🔧 Mejoras

- **Lógica de carrito mejorada:**
  - Agrupación automática de variaciones por producto padre
  - Cálculo de precios basado en suma total del grupo
  - Compatibilidad mejorada con múltiples variaciones del mismo producto

- **Integración con frontend:**
  - Script JavaScript optimizado que se carga solo cuando es necesario
  - Soporte para múltiples tablas en la misma página
  - Detección automática de shortcodes para cargar scripts

### 📦 Nuevos Archivos

- `includes/class-wpdm-variation-table.php` - Nueva clase para gestión de tabla de variaciones

### 🔄 Archivos Modificados

- `woo-prices-dynamics-makito.php` - Añadida carga e inicialización de WPDM_Variation_Table
- `includes/class-wpdm-cart-adjustments.php` - Lógica mejorada para agrupar variaciones y calcular precios por suma total
- `includes/class-wpdm-admin-settings.php` - Añadida opción para activar/desactivar tabla de variaciones

---

## [1.0.0] - 2025-01-XX

### 🎉 Primera Versión Estable

Esta es la primera versión estable del plugin después de completar todas las funcionalidades principales y limpiar el código para producción.

### ✨ Características Principales

- ✅ Sistema completo de precios por tramos (price_tiers) para productos WooCommerce
- ✅ Soporte para productos simples y variables
- ✅ Actualización dinámica de precios en ficha de producto según cantidad
- ✅ Aplicación automática de precios por tramos en el carrito
- ✅ Compatibilidad con WooCommerce Blocks y carrito tradicional
- ✅ Persistencia de precios en sesión del carrito
- ✅ Guardado de metadatos de tramos en pedidos (order meta)
- ✅ Tabla de precios por cantidad en ficha de producto (opcional)
- ✅ Shortcode `[wpdm_price_tiers_table]` para mostrar tabla de tramos
- ✅ Compatibilidad con HPOS (High-Performance Order Storage)
- ✅ Sistema de logging deshabilitado por defecto en producción

### 🔧 Cambios Técnicos

- **Limpieza de código para producción:**
  - Eliminadas todas las llamadas a logs en código PHP
  - Deshabilitado sistema de logging JavaScript (WPDMLogger y WPDMCartLogger)
  - Logger deshabilitado por defecto (puede activarse desde admin si es necesario)
  - Eliminado log de inicialización del plugin

- **Correcciones:**
  - Corregido error de variable no definida `$target_product_id` en `class-wpdm-price-tiers.php`
  - Optimizado código eliminando logs innecesarios

### 📦 Estructura del Plugin

- `woo-prices-dynamics-makito.php` - Archivo principal
- `includes/class-wpdm-logger.php` - Sistema de logging (deshabilitado por defecto)
- `includes/class-wpdm-price-tiers.php` - Gestión de tramos de precio
- `includes/class-wpdm-cart-adjustments.php` - Ajustes de precios en carrito
- `includes/class-wpdm-frontend.php` - Scripts frontend y visualización
- `includes/class-wpdm-order-meta.php` - Metadatos en pedidos
- `includes/class-wpdm-admin-settings.php` - Configuración de administración

### 🎯 Funcionalidades Verificadas

- ✅ Detección correcta de cambios de cantidad en ficha de producto
- ✅ Actualización de precios en tiempo real según tramos
- ✅ Aplicación correcta de precios en carrito
- ✅ Funcionamiento correcto en checkout
- ✅ Guardado correcto de precios en pedidos finalizados
- ✅ Visualización correcta de precios en admin de pedidos

---

## [0.3.3] - 2025-01-XX

### ✨ Nuevas Características

- Sistema de logging completo para debugging
- Página de administración para visualizar logs
- Configuración de retención de logs
- Soporte para productos variables con tramos en variaciones
- Mejoras en detección de cambios de cantidad en carrito

### 🔧 Mejoras

- Mejorado sistema de logging con niveles (debug, info, warning, error)
- Optimización de consultas de tramos de precio
- Mejoras en compatibilidad con WooCommerce Blocks

### 🐛 Correcciones

- Corregido problema con variaciones que no tenían tramos propios
- Mejorada detección de cambios de cantidad en carrito tradicional y Blocks

---

## [0.3.2] - 2025-01-XX

### ✨ Nuevas Características

- Soporte para WooCommerce Blocks en carrito y checkout
- Detección mejorada de selectores de precio en frontend
- Sistema de eventos para actualización de precios

### 🔧 Mejoras

- Mejorada compatibilidad con diferentes temas de WooCommerce
- Optimización de scripts JavaScript
- Mejoras en persistencia de precios en sesión

---

## [0.3.1] - 2025-01-XX

### ✨ Nuevas Características

- Tabla de precios por cantidad en ficha de producto
- Shortcode para mostrar tabla de tramos
- Configuración para mostrar/ocultar tabla automáticamente

### 🔧 Mejoras

- Mejorado formato de visualización de precios
- Estilos CSS para tabla de tramos
- Mejoras en actualización dinámica de precios

---

## [0.3.0] - 2025-01-XX

### ✨ Nuevas Características

- Sistema completo de aplicación de precios por tramos en carrito
- Actualización dinámica de precios en ficha de producto
- Soporte para productos simples y variables
- Guardado de metadatos de tramos en pedidos

### 🔧 Mejoras

- Optimización de cálculo de precios
- Mejoras en manejo de sesión del carrito
- Compatibilidad con HPOS

---

## [0.2.0] - 2025-01-XX

### ✨ Nuevas Características

- Clase `WPDM_Price_Tiers` para gestión de tramos
- Normalización de datos de tramos desde meta fields
- Soporte para diferentes formatos de datos (serializado, JSON)
- Búsqueda de tramos en producto padre para variaciones

### 🔧 Mejoras

- Validación mejorada de datos de tramos
- Ordenamiento automático de tramos por cantidad
- Mejoras en búsqueda de mejor tramo para cantidad dada

---

## [0.1.0] - 2025-01-XX

### 🎉 Versión Inicial

- Estructura básica del plugin
- Integración con WooCommerce
- Sistema de clases base
- Verificación de requisitos (WooCommerce activo)
- Declaración de compatibilidad con HPOS
- Carga de text domain para traducciones

---

## Tipos de Cambios

- `✨ Nuevas Características` - Para nuevas funcionalidades
- `🔧 Mejoras` - Para cambios en funcionalidades existentes
- `🐛 Correcciones` - Para corrección de bugs
- `🔒 Seguridad` - Para vulnerabilidades de seguridad
- `📦 Dependencias` - Para actualizaciones de dependencias
- `🗑️ Eliminado` - Para funcionalidades eliminadas
- `📝 Documentación` - Para cambios en documentación

---

## Notas de Versión

### Versión 1.0.0 - Primera Versión Estable

Esta versión marca el hito de la primera versión estable del plugin. Todas las funcionalidades principales han sido implementadas y probadas:

- ✅ Precios por tramos funcionando correctamente
- ✅ Integración completa con carrito y checkout
- ✅ Persistencia de datos en pedidos
- ✅ Código limpio y optimizado para producción
- ✅ Sistema de logging disponible pero deshabilitado por defecto

**Recomendación:** Esta versión está lista para producción. El sistema de logging puede activarse desde el panel de administración si se necesita debugging.

---

## Próximas Versiones

Las futuras versiones seguirán este formato de changelog para mantener un historial claro de todos los cambios realizados en el plugin.



