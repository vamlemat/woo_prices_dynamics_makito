# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

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



