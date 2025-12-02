# 📦 GUÍA COMPLETA: CREAR UN PRODUCTO FINAL CON TODOS LOS CAMPOS

## 🎯 OBJETIVO

Esta guía te muestra **paso a paso** cómo crear un producto completo en WooCommerce + JetEngine con **TODOS los campos** que existen en la base de datos SQL, incluyendo:
- ✅ Campos básicos del producto
- ✅ Campos técnicos (dimensiones, peso, composición)
- ✅ Información de embalaje (4 tipos: PF, PI1, PI2, PTC)
- ✅ Información de pallet
- ✅ Precios por tramos
- ✅ Áreas de marcaje
- ✅ **Si es variable**: Talla y Color (con todas sus variaciones)
- ✅ Relaciones con técnicas de marcación

---

## 📋 ÍNDICE

1. [Campos del Producto Base](#campos-base)
2. [Producto Variable (Talla y Color)](#producto-variable)
3. [Repeaters](#repeaters)
4. [Variaciones](#variaciones)
5. [Relaciones con Técnicas](#relaciones)
6. [Ejemplo Completo](#ejemplo)
7. [Checklist Final](#checklist)

---

<a name="campos-base"></a>
## 1️⃣ CAMPOS DEL PRODUCTO BASE

### 📍 **TAB 1: Información General**

Todos estos campos se crean en: **JetEngine → Post Types → product → Meta Fields → TAB 1: Información General**

| Campo JetEngine | Label | Tipo | Descripción | Origen SQL |
|----------------|-------|------|-------------|------------|
| `_producto_ref` | Referencia del Proveedor | Text | Referencia única del producto | `productos.ref` |
| `_printcode` | Print Code | Text | Código de impresión | `productos.printcode` |
| `_intrastat` | Código Intrastat | Text | Código para exportación | `productos.intrastat` |
| `_last_updated` | Última actualización | Datetime-local | Fecha de última actualización | `productos.updated_at` |

**Ejemplo de valores:**
```
_producto_ref: "1011"
_printcode: "F(1),N(8)"
_intrastat: "6117100000"
_last_updated: "2025-11-05 08:26:02"
```

---

### 📍 **TAB 2: Información Técnica**

| Campo JetEngine | Label | Tipo | Descripción | Origen SQL |
|----------------|-------|------|-------------|------------|
| `_product_type` | Tipo de Producto | Text | Tipo/categoría del producto | `/api/descriptions/{ref} → type` |
| `_composition` | Composición / Material | Textarea | Material del que está hecho | `/api/descriptions/{ref} → comp` |
| `_length` | Longitud (cm) | Text | Longitud del producto | `productos.length` |
| `_height` | Altura (cm) | Text | Altura del producto | `productos.height` |
| `_width` | Anchura (cm) | Text | Ancho del producto | `productos.width` |
| `_diameter` | Diámetro (cm) | Text | Diámetro (para productos cilíndricos) | `productos.diameter` |
| `_weight` | Peso (g) | Text | Peso del producto | `productos.weight` |
| `_additional_info` | Información Adicional | Wysiwyg | Información adicional del producto | `/api/descriptions/{ref} → info` |

**Ejemplo de valores:**
```
_product_type: "Bolsa plegable"
_composition: "Poliéster 210D"
_length: "180"
_height: "50"
_width: "" (puede estar vacío)
_diameter: "" (puede estar vacío)
_weight: "78"
_additional_info: "Bolsa plegable con funda incorporada"
```

---

### 📍 **TAB 3: Embalaje**

#### 🔄 **Repeater: `_packing_data`**

Este repeater puede tener **hasta 4 elementos** según el tipo de empaque del producto:

**Repeater Fields:**
- `packing_type` (Text): Tipo de empaque → "PF", "PI1", "PI2", "PTC"
- `units` (Number): Unidades por empaque
- `description` (Text): Descripción del empaque
- `length` (Number): Longitud en cm
- `width` (Number): Anchura en cm
- `height` (Number): Altura en cm
- `weight` (Number): Peso en gramos
- `net_weight` (Number): Peso neto en gramos (solo para PTC)

**Estructura según SQL:**

**1. Packing Final (PF):**
```json
{
  "packing_type": "PF",
  "units": 1,
  "description": "",
  "length": "",      // productos.pf_length
  "width": "",       // productos.pf_width
  "height": "",      // productos.pf_height
  "weight": "",      // productos.pf_weight
  "net_weight": null
}
```

**2. Packing Intermedio 2 (PI2):**
```json
{
  "packing_type": "PI2",
  "units": 10,       // productos.pi2_units
  "description": "",
  "length": "",      // productos.pi2_length
  "width": "",       // productos.pi2_width
  "height": "",      // productos.pi2_height
  "weight": "",      // productos.pi2_weight
  "net_weight": null
}
```

**3. Packing Intermedio 1 (PI1):**
```json
{
  "packing_type": "PI1",
  "units": 100,      // productos.pi1_units
  "description": "",
  "length": "",      // productos.pi1_length
  "width": "",       // productos.pi1_width
  "height": "",      // productos.pi1_height
  "weight": "",      // productos.pi1_weight
  "net_weight": null
}
```

**4. Packing Transporte (PTC):**
```json
{
  "packing_type": "PTC",
  "units": 100,      // productos.ptc_units
  "description": "",
  "length": "70",    // productos.ptc_length
  "width": "34",     // productos.ptc_width
  "height": "20",    // productos.ptc_height
  "weight": "9.6",   // productos.ptc_weight
  "net_weight": null // productos.ptc_net_weight (solo PTC)
}
```

#### **Campos de Pallet:**

| Campo JetEngine | Label | Tipo | Descripción | Origen SQL |
|----------------|-------|------|-------------|------------|
| `_pallet_units` | Unidades por Pallet | Number | Número de unidades que caben en un pallet | `productos.pallet_units` |
| `_pallet_bundle` | Bultos por Pallet | Number | Número de bultos por pallet | `productos.bundle_pallets` |
| `_pallet_weight` | Peso del Pallet (kg) | Number (step: 0.01) | Peso total del pallet | `productos.pallet_weight` |

**Ejemplo de valores:**
```
_pallet_units: 3600
_pallet_bundle: 36
_pallet_weight: 345.60
```

---

### 📍 **TAB 4: Observaciones**

| Campo JetEngine | Label | Tipo | Descripción | Origen SQL |
|----------------|-------|------|-------------|------------|
| `_observations` | Observaciones del Producto | Wysiwyg | Notas y observaciones | `/api/observations/{ref}/{lang}` |

---

### 📍 **TAB 5: Precios por Tramo**

#### 🔄 **Repeater: `price_tiers`**

Este repeater contiene los **tramos de precios por cantidad**.

**Repeater Fields:**
- `qty_from` (Number): Cantidad desde (ej: 1, 501, 2001)
- `qty_to` (Number): Cantidad hasta (0 = sin límite "+")
- `unit_price` (Number, step: 0.01): Precio unitario
- `currency` (Text, opcional): Código de moneda (ej: "EUR")
- `source` (Select, opcional): "panel" o "override_wp"

**Ejemplo típico (4 tramos):**
```json
[
  {
    "qty_from": 1,
    "qty_to": 500,
    "unit_price": 3.23,
    "currency": "EUR",
    "source": "panel"
  },
  {
    "qty_from": 501,
    "qty_to": 2000,
    "unit_price": 3.23,
    "currency": "EUR",
    "source": "panel"
  },
  {
    "qty_from": 2001,
    "qty_to": 5000,
    "unit_price": 3.23,
    "currency": "EUR",
    "source": "panel"
  },
  {
    "qty_from": 5001,
    "qty_to": 0,
    "unit_price": 3.23,
    "currency": "EUR",
    "source": "panel"
  }
]
```

*Nota: El último tramo tiene `qty_to: 0` que significa "sin límite" (5001+)*

---

### 📍 **TAB 6: Áreas de Marcaje**

#### 🔄 **Repeater: `marking_areas`**

Este repeater contiene las **áreas disponibles para marcaje** en el producto.

**Repeater Fields:**
- `print_area_id` (Number): ID único del área
- `technique_ref` (Text): Referencia de la técnica aplicable (ej: "100216")
- `position` (Text): Descripción de la posición (ej: "Funda cara A")
- `max_colors` (Number): Máximo de colores permitidos
- `width` (Text): Ancho del área en mm
- `height` (Text): Alto del área en mm
- `area_img` (Media/URL): URL de imagen del área

**Ejemplo:**
```json
[
  {
    "print_area_id": 579,
    "technique_ref": "100216",
    "position": "Funda cara A",
    "max_colors": 1,
    "width": "8",
    "height": "8",
    "area_img": "1011-A3.jpg"
  },
  {
    "print_area_id": 579,
    "technique_ref": "100600",
    "position": "Funda cara A",
    "max_colors": 8,
    "width": "8",
    "height": "8",
    "area_img": "1011-A3.jpg"
  }
]
```

*Origen SQL: `producto_marcajes` tabla*

---

<a name="producto-variable"></a>
## 2️⃣ PRODUCTO VARIABLE (CON TALLA Y COLOR)

Si el producto es **variable** (tiene diferentes tallas y/o colores), necesitas configurar:

### **A. Atributos de Producto**

Ve a: **Productos → Atributos**

#### **Atributo 1: Color (`pa_color`)**
```
Nombre: Color
Slug: pa_color  ← ¡IMPORTANTE! El slug debe ser exactamente "pa_color"
Enable archives: ✓ Yes
Used for variations: ✓ Yes
```

**Términos comunes:**
- BLA (Blanco)
- NEG (Negro)
- GRI (Gris)
- MAR (Marrón)
- ROSA
- AZUL
- VERDE
- etc.

#### **Atributo 2: Talla (`pa_talla`)**
```
Nombre: Talla
Slug: pa_talla  ← ¡IMPORTANTE! El slug debe ser exactamente "pa_talla"
Enable archives: ✓ Yes
Used for variations: ✓ Yes
```

**Términos comunes:**
- S/T (Sin Talla / Una talla)
- S (Small)
- M (Medium)
- L (Large)
- XL
- XXL
- etc.

#### **Atributo 3: Marca (`pa_brand`)**
```
Nombre: Marca
Slug: pa_brand  ← El slug debe ser exactamente "pa_brand"
Enable archives: ✓ Yes
Used for variations: ✗ No (es atributo global, no para variaciones)
```

**Términos:**
- Makito
- (Otros proveedores)

---

### **B. Tipo de Producto en WooCommerce**

Cuando crees el producto:

```
Productos → Añadir nuevo

Título: (nombre del producto, ej: "Betty")
Tipo: Variable Product ← ¡IMPORTANTE!
SKU: (referencia base, ej: "1011")
```

---

### **C. Configurar Atributos en el Producto**

En la pestaña **"Atributos"** del producto:

1. **Seleccionar el atributo `pa_color` (Color)**
   - ✓ Usado para variaciones
   - Seleccionar términos: BLA, NEG, GRI, MAR, NATU, ROSA, etc. (según el producto)

2. **Seleccionar el atributo `pa_talla` (Talla)**
   - ✓ Usado para variaciones
   - Seleccionar términos: S/T, S, M, L, XL, XXL, etc. (según el producto)

3. **Seleccionar el atributo `pa_brand` (Marca)**
   - ✗ NO usado para variaciones (es atributo global)
   - Valor: Makito (o la marca correspondiente)

4. **Click en "Guardar atributos"**

5. **Click en "Generar variaciones"** → Esto creará todas las combinaciones de `pa_color` y `pa_talla`

---

<a name="variaciones"></a>
## 3️⃣ VARIACIONES DEL PRODUCTO

Cada variación se crea automáticamente al hacer "Generar variaciones", pero necesitas configurar cada una:

### **Campos de cada Variación:**

Para cada variación individual:

| Campo WooCommerce | Descripción | Origen SQL |
|------------------|-------------|------------|
| `SKU` | Código único de la variación | `producto_variantes.codigo` (ej: "11011001000") |
| `Precio` | Precio de la variación | (se hereda del producto base o `price_tiers`) |
| `Stock` | Cantidad en stock | `producto_variantes.stock` |
| `Imagen` | Imagen de la variación | `producto_variantes.url_imagen` |
| `Atributos` | `pa_color` y `pa_talla` seleccionados | `producto_variantes.codigo_color` + `tallas` |

### **Meta Fields adicionales de Variación:**

| Meta Field | Descripción | Origen SQL |
|-----------|-------------|------------|
| `_makito_ref` | Referencia de la variación | `producto_variantes.ref` (ej: "1011BLAS/T") |
| `_makito_available_date` | Fecha de disponibilidad | `producto_variantes.disponibilidad_stock` |
| `_stock_status` | Estado del stock | `producto_variantes.disponibilidad_stock` ("immediately" o fecha) |

### **Ejemplo de Variación:**

```
Variación 1:
- SKU: 11011001000
- pa_color: BLA (Blanco)
- pa_talla: S/T
- Stock: 1000
- Imagen: http://makito.es/.../1011-001-P.jpg
- _makito_ref: 1011BLAS/T
- _makito_available_date: immediately
```

**Origen SQL:**
```sql
SELECT * FROM producto_variantes 
WHERE producto_id = 1 
AND codigo_color = 'BLA' 
AND talla_id = 1; -- S/T
```

---

<a name="relaciones"></a>
## 4️⃣ CREAR TÉCNICAS DE MARCADO (GUÍA COMPLETA)

### **Paso 1: Crear el CPT Técnica de Marcación**

**Ir a: JetEngine → Post Types → tecnicas-marcacion → Añadir nueva**

**Título**: Nombre de la técnica (ej: "Serigrafía F", "Bordado", "Tampografía C")

---

### **Paso 2: Llenar TODOS los Meta Fields**

Una vez creada la técnica, ve a editarla y llena TODOS estos campos:

#### **📌 Campos Simples:**

| Campo JetEngine | Label | Tipo | Ejemplo | Origen SQL |
|----------------|-------|------|---------|------------|
| `technique_ref` | Código de Técnica | Text | `100216` | `tipos_tecnica_marcacion.ref` |
| `col_inc` | Colores Incluidos | Number | `1` | `producto_tecnica_marcacion.color_incluido` |
| `notice_txt` | Texto de Aviso | Textarea | Restricciones importantes | `producto_tecnica_marcacion.texto_aviso` |
| `system` | Sistema de Aplicación | Text | Manual, Automático | `producto_tecnica_marcacion.sistema` |
| `doublepass` | Doble Pasada | Checkbox | ✓ o ✗ | `producto_tecnica_marcacion.doble_pasada` (0/1) |
| `layer` | Número de Capas | Number | `1` | `producto_tecnica_marcacion.capa` |
| `option` | Opción | Text | `F`, `A`, `B`, `C`, etc. | `producto_tecnica_marcacion.opciones` |
| `mixture` | Permite Mezcla | Checkbox | ✓ o ✗ | `producto_tecnica_marcacion.mezcla` (0/1) |
| `cliche` | Costo de Cliché | Number (step: 0.01) | `30.00` | `precios_tecnica_marcacion.cliche` |
| `cliche_repetition` | Costo Repetición Cliché | Number (step: 0.01) | `15.00` | `precios_tecnica_marcacion.cliche_repeticion` |
| `min` | Cantidad Mínima | Number | `45` | `precios_tecnica_marcacion.min` |
| `code` | Código de Variante | Text | `F`, `A`, `B`, `C`, etc. | `precios_tecnica_marcacion.codigo` |

**⚠️ IMPORTANTE:**
- `technique_ref`: Referencia única de la técnica (ej: "100216" para Serigrafía F)
- `code`: Código específico de la variante de técnica (ej: "F", "A", "B"). Este campo conecta con los precios.
- `option`: Opción de la técnica, puede ser igual o diferente a `code`

---

#### **🔄 REPEATER 1: Traducciones (`translations`)**

Este repeater contiene el nombre de la técnica en diferentes idiomas.

**Repeater Fields:**
- `lang_id` (Number): ID del idioma (1=ES, 2=EN, 3=IT, 4=FR, 5=PT, 6=DE, 7=NL)
- `lang_code` (Text): Código del idioma (ES, EN, IT, FR, PT, DE, NL)
- `name` (Text): Nombre de la técnica en ese idioma

**Ejemplo completo para Serigrafía F (technique_ref: 100216):**

```json
[
  {
    "lang_id": 1,
    "lang_code": "ES",
    "name": "SERIGRAFÍA F"
  },
  {
    "lang_id": 2,
    "lang_code": "EN",
    "name": "SILK-SCREEN PRINT F"
  },
  {
    "lang_id": 3,
    "lang_code": "IT",
    "name": "SERIGRAFIA F"
  },
  {
    "lang_id": 4,
    "lang_code": "FR",
    "name": "SÉRIGRAPHIE F"
  },
  {
    "lang_id": 5,
    "lang_code": "PT",
    "name": "SERIGRAFIA F"
  },
  {
    "lang_id": 6,
    "lang_code": "DE",
    "name": "SIEBDRUCK F"
  },
  {
    "lang_id": 7,
    "lang_code": "NL",
    "name": "ZEEFDRUK F"
  }
]
```

**Origen SQL:**
```sql
SELECT * FROM tipos_tecnica_marcacion 
WHERE ref = '100216';
```

**⚠️ IMPORTANTE:** Si no tienes todas las traducciones, añade al menos las que uses (normalmente ES y EN).

---

#### **🔄 REPEATER 2: Escalas de Precios (`precio_escalas`)**

Este repeater contiene los **precios por tramos de cantidad** para esta técnica.

**Repeater Fields:**
- `section` (Number): Cantidad hasta (ej: 500, 2000, 5000). Si es 0 = sin límite
- `price` (Number, step: 0.01): Precio base por unidad
- `price_col` (Number, step: 0.01): Precio por color adicional
- `price_cm` (Number, step: 0.01): Precio por cm² (normalmente 0.000)

**Ejemplo completo para Serigrafía F (code: F, precio_tecnica_marcacion_id: 181):**

```json
[
  {
    "section": 500,
    "price": 0.410,
    "price_col": 0.190,
    "price_cm": 0.000
  },
  {
    "section": 2000,
    "price": 0.340,
    "price_col": 0.165,
    "price_cm": 0.000
  },
  {
    "section": 5000,
    "price": 0.290,
    "price_col": 0.150,
    "price_cm": 0.000
  },
  {
    "section": 0,
    "price": 0.250,
    "price_col": 0.140,
    "price_cm": 0.000
  }
]
```

**Explicación de los tramos:**
- Tramo 1: De 1 a 500 unidades → Precio: 0.410€, Color adicional: 0.190€
- Tramo 2: De 501 a 2000 unidades → Precio: 0.340€, Color adicional: 0.165€
- Tramo 3: De 2001 a 5000 unidades → Precio: 0.290€, Color adicional: 0.150€
- Tramo 4: De 5001 en adelante (section: 0 = sin límite) → Precio: 0.250€, Color adicional: 0.140€

**Origen SQL:**
```sql
SELECT * FROM cantidades_precio_tecnica_marcacion 
WHERE precio_tecnica_marcacion_id = 181
ORDER BY cantidad_desde;
```

**⚠️ IMPORTANTE:**
- El último tramo debe tener `section: 0` para indicar "sin límite"
- Los precios deben estar en orden ascendente por `section`
- `price_cm` normalmente es 0.000, pero algunas técnicas lo usan

---

#### **🔄 REPEATER 3: Áreas de Marcaje (`areas_marcaje`)**

Este repeater contiene las **áreas donde se puede aplicar esta técnica** (opcional, normalmente las áreas vienen del producto).

**Repeater Fields:**
- `print_area_id` (Number): ID único del área
- `technique_ref` (Text): Referencia de la técnica (debe coincidir con `technique_ref` del campo simple)
- `position` (Text): Posición del área (ej: "Funda cara A", "Funda cara B")
- `max_colors` (Number): Máximo de colores permitidos en esta área
- `width` (Text): Ancho del área en mm
- `height` (Text): Alto del área en mm
- `area_img` (Media/URL): URL o imagen del área

**Ejemplo:**
```json
[
  {
    "print_area_id": 579,
    "technique_ref": "100216",
    "position": "Funda cara A",
    "max_colors": 1,
    "width": "8",
    "height": "8",
    "area_img": "1011-A3.jpg"
  }
]
```

**⚠️ NOTA:** Este repeater es opcional. Normalmente las áreas se guardan en el producto (repeater `marking_areas`), pero puedes duplicarlas aquí para referencia.

---

### **Paso 3: Ejemplo Completo PASO A PASO - Crear Técnica "Serigrafía F"**

#### **📝 Paso 3.1: Crear el CPT**

```
1. Ir a: WordPress Admin → Técnicas de Marcación → Añadir nueva
2. Título: Serigrafía F
3. Click en "Publicar"
```

---

#### **📝 Paso 3.2: Llenar Campos Simples (uno por uno)**

**CAMPO 1: technique_ref**
```
Field Name: technique_ref
Valor: 100216
```

**CAMPO 2: col_inc**
```
Field Name: col_inc
Valor: 1
```

**CAMPO 3: notice_txt**
```
Field Name: notice_txt
Valor: (dejar vacío - no tiene restricciones)
```

**CAMPO 4: system**
```
Field Name: system
Valor: (dejar vacío)
```

**CAMPO 5: doublepass**
```
Field Name: doublepass
Valor: ✗ (desmarcado - NO tiene doble pasada)
```

**CAMPO 6: layer**
```
Field Name: layer
Valor: 1
```

**CAMPO 7: option**
```
Field Name: option
Valor: F
```

**CAMPO 8: mixture**
```
Field Name: mixture
Valor: ✗ (desmarcado - NO permite mezcla)
```

**CAMPO 9: cliche**
```
Field Name: cliche
Valor: 30.00
```

**CAMPO 10: cliche_repetition**
```
Field Name: cliche_repetition
Valor: 15.00
```

**CAMPO 11: min**
```
Field Name: min
Valor: 45
```

**CAMPO 12: code**
```
Field Name: code
Valor: F
```

---

#### **📝 Paso 3.3: Llenar Repeater `translations` (7 elementos)**

**Click en "Add Row" 7 veces para crear 7 elementos:**

**ELEMENTO 1 (Español):**
```
lang_id: 1
lang_code: ES
name: SERIGRAFÍA F
```

**ELEMENTO 2 (Inglés):**
```
lang_id: 2
lang_code: EN
name: SILK-SCREEN PRINT F
```

**ELEMENTO 3 (Italiano):**
```
lang_id: 3
lang_code: IT
name: SERIGRAFIA F
```

**ELEMENTO 4 (Francés):**
```
lang_id: 4
lang_code: FR
name: SÉRIGRAPHIE F
```

**ELEMENTO 5 (Portugués):**
```
lang_id: 5
lang_code: PT
name: SERIGRAFIA F
```

**ELEMENTO 6 (Alemán):**
```
lang_id: 6
lang_code: DE
name: SIEBDRUCK F
```

**ELEMENTO 7 (Holandés):**
```
lang_id: 7
lang_code: NL
name: ZEEFDRUK F
```

---

#### **📝 Paso 3.4: Llenar Repeater `precio_escalas` (4 elementos - 4 tramos)**

**Click en "Add Row" 4 veces para crear 4 elementos:**

**ELEMENTO 1 (Tramo 1-500 unidades):**
```
section: 500
price: 0.410
price_col: 0.190
price_cm: 0.000
```

**ELEMENTO 2 (Tramo 501-2000 unidades):**
```
section: 2000
price: 0.340
price_col: 0.165
price_cm: 0.000
```

**ELEMENTO 3 (Tramo 2001-5000 unidades):**
```
section: 5000
price: 0.290
price_col: 0.150
price_cm: 0.000
```

**ELEMENTO 4 (Tramo 5001+ unidades - sin límite):**
```
section: 0
price: 0.250
price_col: 0.140
price_cm: 0.000
```

**⚠️ IMPORTANTE:** El último elemento debe tener `section: 0` (sin límite)

---

#### **📝 Paso 3.5: Repeater `areas_marcaje` (OPCIONAL)**

Este repeater es opcional. Normalmente las áreas se guardan en el producto, pero si quieres duplicarlas aquí, puedes añadirlas.

**Si quieres añadir áreas, click en "Add Row" y rellena:**
```
print_area_id: 579
technique_ref: 100216
position: Funda cara A
max_colors: 1
width: 8
height: 8
area_img: http://makito.es/WebRoot/Store/Shops/Makito/634A/944E/AEF3/312C/1A48/0A6E/0397/C0EC/1011-A3.jpg
```

*(Puedes añadir más áreas si las necesitas)*

---

#### **📝 Paso 3.6: Guardar la Técnica**

```
1. Click en "Actualizar" o "Publicar"
2. Verificar que todos los campos se hayan guardado correctamente
```

---

### **Paso 4: Ejemplo Completo PASO A PASO - Crear Técnica "Bordado"**

#### **📝 Paso 4.1: Crear el CPT**

```
1. Ir a: WordPress Admin → Técnicas de Marcación → Añadir nueva
2. Título: Bordado
3. Click en "Publicar"
```

---

#### **📝 Paso 4.2: Llenar Campos Simples (uno por uno)**

**CAMPO 1: technique_ref**
```
Field Name: technique_ref
Valor: 100600
```

**CAMPO 2: col_inc**
```
Field Name: col_inc
Valor: 1
```

**CAMPO 3: notice_txt**
```
Field Name: notice_txt
Valor: (dejar vacío)
```

**CAMPO 4: system**
```
Field Name: system
Valor: (dejar vacío)
```

**CAMPO 5: doublepass**
```
Field Name: doublepass
Valor: ✗ (desmarcado)
```

**CAMPO 6: layer**
```
Field Name: layer
Valor: 1
```

**CAMPO 7: option**
```
Field Name: option
Valor: N
```

**CAMPO 8: mixture**
```
Field Name: mixture
Valor: ✗ (desmarcado)
```

**CAMPO 9: cliche**
```
Field Name: cliche
Valor: 30.00
```

**CAMPO 10: cliche_repetition**
```
Field Name: cliche_repetition
Valor: 0.00
```

**CAMPO 11: min**
```
Field Name: min
Valor: 40
```

**CAMPO 12: code**
```
Field Name: code
Valor: N
```

---

#### **📝 Paso 4.3: Llenar Repeater `translations` (mínimo 2 elementos - ES y EN)**

**Click en "Add Row" al menos 2 veces:**

**ELEMENTO 1 (Español):**
```
lang_id: 1
lang_code: ES
name: BORDADO
```

**ELEMENTO 2 (Inglés):**
```
lang_id: 2
lang_code: EN
name: EMBROIDERY
```

*(Puedes añadir más idiomas si los necesitas)*

---

#### **📝 Paso 4.4: Llenar Repeater `precio_escalas` (4 elementos)**

**Click en "Add Row" 4 veces:**

**ELEMENTO 1 (Tramo 1-500 unidades):**
```
section: 500
price: 0.280
price_col: 0.000
price_cm: 0.000
```

**ELEMENTO 2 (Tramo 501-2000 unidades):**
```
section: 2000
price: 0.240
price_col: 0.000
price_cm: 0.000
```

**ELEMENTO 3 (Tramo 2001-5000 unidades):**
```
section: 5000
price: 0.220
price_col: 0.000
price_cm: 0.000
```

**ELEMENTO 4 (Tramo 5001+ unidades - sin límite):**
```
section: 0
price: 0.200
price_col: 0.000
price_cm: 0.000
```

**⚠️ NOTA:** Bordado normalmente no tiene precio por color adicional (`price_col: 0.000`)

---

#### **📝 Paso 4.5: Guardar la Técnica**

```
1. Click en "Actualizar" o "Publicar"
2. Verificar que todos los campos se hayan guardado correctamente
```

---

### **Paso 5: Relacionar Técnica con Producto**

Una vez creada la técnica:

1. **Ir al producto** que necesita esta técnica
2. **En el meta box "Técnicas de Marcaje Disponibles"** (creado por JetEngine Relation)
3. **Click en "Añadir Técnica"**
4. **Seleccionar la técnica** (ej: "Serigrafía F")
5. **Guardar el producto**

**Origen SQL:**
```sql
SELECT * FROM producto_tecnica_marcacion 
WHERE producto_id = 1 
AND tipo_tecnica_marcacion_ref = 100216;
```

---

### **✅ Checklist para Crear Técnica:**

- [ ] Título del CPT creado
- [ ] `technique_ref` rellenado
- [ ] `col_inc` rellenado
- [ ] `notice_txt` (si aplica)
- [ ] `system` (si aplica)
- [ ] `doublepass` (checkbox)
- [ ] `layer` rellenado
- [ ] `option` rellenado
- [ ] `mixture` (checkbox)
- [ ] `cliche` rellenado
- [ ] `cliche_repetition` rellenado
- [ ] `min` rellenado
- [ ] `code` rellenado (¡IMPORTANTE!)
- [ ] Repeater `translations` (mínimo ES y EN)
- [ ] Repeater `precio_escalas` (todos los tramos)
- [ ] Repeater `areas_marcaje` (opcional)
- [ ] Técnica relacionada con producto(s)

---

## 5️⃣ RELACIONES CON TÉCNICAS DE MARCADO

### **JetEngine Relation: `productos_to_tecnicas`**

Esta relación conecta el producto con las técnicas de marcado disponibles.

**Configuración:**
- **Tipo**: Many to Many
- **Parent**: product
- **Child**: tecnicas-marcacion

**Cómo relacionar:**

1. **Crear las técnicas primero** (ver sección anterior) con TODOS los campos
2. **En el producto**, ir al meta box **"Técnicas de Marcaje Disponibles"**
   - Click en "Añadir Técnica"
   - Seleccionar la(s) técnica(s) disponibles para este producto
   - Guardar

**Origen SQL:**
```sql
SELECT * FROM producto_tecnica_marcacion 
WHERE producto_id = 1;
```

---

<a name="ejemplo"></a>
## 5️⃣ EJEMPLO COMPLETO: PRODUCTO "BETTY" (Ref: 1011)

### **Paso 1: Crear Producto Base**

```
WordPress Admin → Productos → Añadir nuevo

Título: Betty
Tipo: Variable Product
SKU: 1011
Estado: Publicado
```

### **Paso 2: Llenar TAB 1 - Información General**

```
_producto_ref: 1011
_printcode: F(1),N(8)
_intrastat: 6117100000
_last_updated: 2025-11-05 08:26:02
```

### **Paso 3: Llenar TAB 2 - Información Técnica**

```
_product_type: Bolsa plegable
_composition: Poliéster 210D
_length: 180
_height: 50
_width: (vacío)
_diameter: (vacío)
_weight: 78
_additional_info: Bolsa plegable con funda incorporada
```

### **Paso 4: Llenar TAB 3 - Embalaje**

#### **Repeater `_packing_data`:**

**Elemento 1:**
```
packing_type: PF
units: 1
description: (vacío)
length: (vacío)
width: (vacío)
height: (vacío)
weight: (vacío)
net_weight: null
```

**Elemento 2:**
```
packing_type: PI2
units: 10
description: (vacío)
length: (vacío)
width: (vacío)
height: (vacío)
weight: (vacío)
net_weight: null
```

**Elemento 3:**
```
packing_type: PI1
units: 100
description: (vacío)
length: (vacío)
width: (vacío)
height: (vacío)
weight: (vacío)
net_weight: null
```

**Elemento 4:**
```
packing_type: PTC
units: 100
description: (vacío)
length: 70
width: 34
height: 20
weight: 9.6
net_weight: null
```

#### **Campos de Pallet:**
```
_pallet_units: 3600
_pallet_bundle: 36
_pallet_weight: 345.60
```

### **Paso 5: Llenar TAB 4 - Observaciones**

```
_observations: (vacío o según necesidad)
```

### **Paso 6: Llenar TAB 5 - Precios por Tramo**

#### **Repeater `price_tiers`:**

**Elemento 1:**
```
qty_from: 1
qty_to: 500
unit_price: 3.23
currency: EUR
source: panel
```

**Elemento 2:**
```
qty_from: 501
qty_to: 2000
unit_price: 3.23
currency: EUR
source: panel
```

**Elemento 3:**
```
qty_from: 2001
qty_to: 5000
unit_price: 3.23
currency: EUR
source: panel
```

**Elemento 4:**
```
qty_from: 5001
qty_to: 0
unit_price: 3.23
currency: EUR
source: panel
```

### **Paso 7: Llenar TAB 6 - Áreas de Marcaje**

#### **Repeater `marking_areas`:**

**Elemento 1:**
```
print_area_id: 579
technique_ref: 100216
position: Funda cara A
max_colors: 1
width: 8
height: 8
area_img: http://makito.es/WebRoot/Store/Shops/Makito/634A/944E/AEF3/312C/1A48/0A6E/0397/C0EC/1011-A3.jpg
```

**Elemento 2:**
```
print_area_id: 579
technique_ref: 100600
position: Funda cara A
max_colors: 8
width: 8
height: 8
area_img: http://makito.es/WebRoot/Store/Shops/Makito/634A/944E/AEF3/312C/1A48/0A6E/0397/C0EC/1011-A3.jpg
```

**Elemento 3:**
```
print_area_id: 3478
technique_ref: 100600
position: Funda cara B
max_colors: 8
width: 8
height: 8
area_img: http://makito.es/WebRoot/Store/Shops/Makito/634A/944E/AEF3/312C/1A48/0A6E/0397/C0EC/1011-A2.jpg
```

**Elemento 4:**
```
print_area_id: 3478
technique_ref: 100216
position: Funda cara B
max_colors: 1
width: 8
height: 8
area_img: http://makito.es/WebRoot/Store/Shops/Makito/634A/944E/AEF3/312C/1A48/0A6E/0397/C0EC/1011-A2.jpg
```

**Elemento 5:**
```
print_area_id: 3477
technique_ref: 100600
position: Funda cara A
max_colors: 8
width: 8
height: 8
area_img: http://makito.es/WebRoot/Store/Shops/Makito/634A/944E/AEF3/312C/1A48/0A6E/0397/C0EC/1011-A1.jpg
```

**Elemento 6:**
```
print_area_id: 3477
technique_ref: 100216
position: Funda cara A
max_colors: 1
width: 8
height: 8
area_img: http://makito.es/WebRoot/Store/Shops/Makito/634A/944E/AEF3/312C/1A48/0A6E/0397/C0EC/1011-A1.jpg
```

*(Origen SQL: `producto_marcajes` donde `producto_id = 1`)*

### **Paso 8: Configurar como Producto Variable**

1. **Atributos:**
   - `pa_color`: BLA, NEG, GRI, MAR, NATU, ROSA, etc.
   - `pa_talla`: S/T
   - `pa_brand`: Makito

2. **Generar variaciones**

3. **Configurar cada variación:**

**Variación 1:**
```
SKU: 11011008000
pa_color: GRI
pa_talla: S/T
Stock: 1000
Imagen: http://makito.es/WebRoot/Store/Shops/Makito/634A/944E/AEF3/312C/1A48/0A6E/0397/C0EC/1011-008-P.jpg
_makito_ref: 1011GRIS/T
_makito_available_date: immediately
```

**Variación 2:**
```
SKU: 11011006000
pa_color: MAR
pa_talla: S/T
Stock: 5400
Imagen: http://makito.es/WebRoot/Store/Shops/Makito/634A/944E/AEF3/312C/1A48/0A6E/0397/C0EC/1011-006-P.jpg
_makito_ref: 1011MARS/T
_makito_available_date: immediately
```

**Variación 3:**
```
SKU: 11011013000
pa_color: NATU
pa_talla: S/T
Stock: 1100
Imagen: http://makito.es/WebRoot/Store/Shops/Makito/634A/944E/AEF3/312C/1A48/0A6E/0397/C0EC/1011-013-P.jpg
_makito_ref: 1011NATUS/T
_makito_available_date: immediately
```

**Variación 4:**
```
SKU: 11011022000
pa_color: ROSA
pa_talla: S/T
Stock: 2100
Imagen: http://makito.es/WebRoot/Store/Shops/Makito/634A/944E/AEF3/312C/1A48/0A6E/0397/C0EC/1011-022-P.jpg
_makito_ref: 1011ROSAS/T
_makito_available_date: immediately
```

*(Continuar con todas las variaciones según el SQL)*

### **Paso 9: Relacionar con Técnicas**

1. **Crear técnica "Serigrafía F"** (si no existe):
   - `technique_ref`: 100216
   - Llenar todos los campos

2. **Crear técnica "Bordado"** (si no existe):
   - `technique_ref`: 100600
   - Llenar todos los campos

3. **En el producto**, añadir estas técnicas en el meta box "Técnicas de Marcaje Disponibles"

---

<a name="checklist"></a>
## ✅ CHECKLIST FINAL

### **Producto Base**

- [ ] Título del producto
- [ ] Tipo: Variable Product
- [ ] SKU base
- [ ] Estado: Publicado

### **TAB 1: Información General**

- [ ] `_producto_ref`
- [ ] `_printcode`
- [ ] `_intrastat`
- [ ] `_last_updated`

### **TAB 2: Información Técnica**

- [ ] `_product_type`
- [ ] `_composition`
- [ ] `_length`
- [ ] `_height`
- [ ] `_width`
- [ ] `_diameter`
- [ ] `_weight`
- [ ] `_additional_info`

### **TAB 3: Embalaje**

- [ ] Repeater `_packing_data` (hasta 4 elementos: PF, PI1, PI2, PTC)
  - [ ] packing_type
  - [ ] units
  - [ ] description
  - [ ] length, width, height, weight
  - [ ] net_weight (solo PTC)
- [ ] `_pallet_units`
- [ ] `_pallet_bundle`
- [ ] `_pallet_weight`

### **TAB 4: Observaciones**

- [ ] `_observations`

### **TAB 5: Precios por Tramo**

- [ ] Repeater `price_tiers` (múltiples tramos)
  - [ ] qty_from
  - [ ] qty_to
  - [ ] unit_price
  - [ ] currency
  - [ ] source

### **TAB 6: Áreas de Marcaje**

- [ ] Repeater `marking_areas` (múltiples áreas)
  - [ ] print_area_id
  - [ ] technique_ref
  - [ ] position
  - [ ] max_colors
  - [ ] width, height
  - [ ] area_img

### **Si es Variable:**

- [ ] Atributo Color creado (pa_color)
- [ ] Atributo Talla creado (pa_talla)
- [ ] Atributo Marca creado (pa_brand)
- [ ] Atributos configurados en el producto
- [ ] Variaciones generadas
- [ ] Cada variación configurada:
  - [ ] SKU único
  - [ ] `pa_color` y `pa_talla` seleccionados
  - [ ] Stock
  - [ ] Imagen
  - [ ] Meta `_makito_ref`
  - [ ] Meta `_makito_available_date`

### **Relaciones:**

- [ ] Técnicas de marcado creadas (CPT `tecnicas-marcacion`)
- [ ] Producto relacionado con técnicas (Relation `productos_to_tecnicas`)

---

## 📚 REFERENCIAS

### **Estructura SQL → JetEngine:**

| Tabla SQL | Campo SQL | Campo JetEngine | Tab |
|-----------|-----------|-----------------|-----|
| `productos` | `ref` | `_producto_ref` | TAB 1 |
| `productos` | `printcode` | `_printcode` | TAB 1 |
| `productos` | `intrastat` | `_intrastat` | TAB 1 |
| `productos` | `updated_at` | `_last_updated` | TAB 1 |
| `productos` | `length` | `_length` | TAB 2 |
| `productos` | `height` | `_height` | TAB 2 |
| `productos` | `width` | `_width` | TAB 2 |
| `productos` | `diameter` | `_diameter` | TAB 2 |
| `productos` | `weight` | `_weight` | TAB 2 |
| `productos` | `pf_*` | `_packing_data` (PF) | TAB 3 |
| `productos` | `pi2_*` | `_packing_data` (PI2) | TAB 3 |
| `productos` | `pi1_*` | `_packing_data` (PI1) | TAB 3 |
| `productos` | `ptc_*` | `_packing_data` (PTC) | TAB 3 |
| `productos` | `pallet_units` | `_pallet_units` | TAB 3 |
| `productos` | `bundle_pallets` | `_pallet_bundle` | TAB 3 |
| `productos` | `pallet_weight` | `_pallet_weight` | TAB 3 |
| `producto_variantes` | `codigo` | `SKU` (variación) | Variación |
| `producto_variantes` | `codigo_color` | `pa_color` | Variación |
| `producto_variantes` | `talla_id` | `pa_talla` | Variación |
| `producto_variantes` | `stock` | `Stock` | Variación |
| `producto_variantes` | `url_imagen` | `Imagen` | Variación |
| `producto_variantes` | `ref` | `_makito_ref` | Meta variación |
| `producto_variantes` | `disponibilidad_stock` | `_makito_available_date` | Meta variación |
| `producto_marcajes` | (todos) | `marking_areas` | TAB 6 |
| `precios_producto` | (tramos) | `price_tiers` | TAB 5 |

---

## 💡 NOTAS IMPORTANTES

1. **Campos vacíos**: Algunos campos pueden estar vacíos en SQL (ej: `width`, `diameter`). Esto es normal.

2. **Repeaters**: Los repeaters pueden tener múltiples elementos. Asegúrate de añadir todos los elementos necesarios.

3. **Variaciones**: Si el producto es variable, **debes crear todas las variaciones** que existen en `producto_variantes` para ese producto.

4. **Técnicas**: Las técnicas deben crearse primero en el CPT `tecnicas-marcacion` antes de relacionarlas.

5. **Precios**: Los precios por tramo normalmente vienen del panel externo, pero puedes añadirlos manualmente.

6. **Imágenes**: Las imágenes de las variaciones normalmente se importan desde la API, pero puedes añadirlas manualmente.

---

## 🎯 RESULTADO FINAL

Al completar todos estos pasos, tendrás un producto completo con:

✅ Todos los campos básicos rellenados  
✅ Información técnica completa  
✅ 4 tipos de embalaje configurados  
✅ Información de pallet  
✅ Precios por tramos  
✅ Áreas de marcaje disponibles  
✅ Si es variable: todas las variaciones (talla y color) configuradas  
✅ Relaciones con técnicas de marcado  
✅ Stock y disponibilidad por variación  

**¡El producto estará completamente funcional y listo para usar!**

---

*Guía creada basada en la estructura SQL `publicmar20251113.sql`*  
*Última actualización: Noviembre 2025*
