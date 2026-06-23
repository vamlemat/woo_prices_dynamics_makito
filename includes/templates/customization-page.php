<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
global $product;
$wpdm_slug = get_query_var( 'wpdm_personalizar_slug', '' );
if ( $wpdm_slug && ( ! $product || ! is_a( $product, 'WC_Product' ) ) ) {
    $wpdm_post = get_page_by_path( $wpdm_slug, OBJECT, 'product' );
    $product   = $wpdm_post ? wc_get_product( $wpdm_post->ID ) : null;
}
if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { $product = null; }
wp_enqueue_script( 'jquery' );
wp_enqueue_style( 'wpdm-customization', plugin_dir_url( WPDM_WOOPRICES_PLUGIN_FILE ) . 'assets/css/wpdm-customization.css', array(), WPDM_WOOPRICES_VERSION );
wp_enqueue_script( 'wpdm-customization', plugin_dir_url( WPDM_WOOPRICES_PLUGIN_FILE ) . 'assets/js/wpdm-customization.js', array( 'jquery' ), WPDM_WOOPRICES_VERSION, true );
get_header();
?>
<style>
*{box-sizing:border-box}
.wc-page{max-width:1280px;margin:0 auto;padding:24px 16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.wc-back{display:inline-flex;align-items:center;gap:6px;color:#555;text-decoration:none;font-size:.9rem;margin-bottom:16px}
.wc-back:hover{color:#0464AC}
.wc-title{font-size:1.6rem;font-weight:700;margin:0 0 20px;color:#111}
.wc-layout{display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start}
@media(max-width:900px){.wc-layout{grid-template-columns:1fr}}
/* LEFT */
.wc-left{}
.wc-notice{background:#f0f7ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 20px;margin-bottom:18px}
.wc-notice h3{margin:0 0 6px;font-size:1rem;color:#0464AC}
.wc-notice p{margin:0;font-size:.85rem;color:#555}
.wc-mode{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 20px;margin-bottom:18px}
.wc-mode p{margin:0 0 8px;font-weight:600;font-size:.95rem}
.wc-mode .hint{font-size:.82rem;color:#777;margin:0 0 12px}
.wc-mode label{display:inline-flex;align-items:center;gap:8px;cursor:pointer;margin-right:20px;font-weight:600}
/* Area card */
.wc-area{background:#fff;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:14px;overflow:hidden}
.wc-area-head{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;padding:14px 18px;cursor:pointer}
.wc-area-head label{display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:700;font-size:1rem;margin:0}
.wc-area-head label input{width:18px;height:18px;flex-shrink:0}
.wc-area-price-badge{background:#0464AC;color:#fff;border-radius:20px;padding:4px 12px;font-size:.85rem;font-weight:700;white-space:nowrap}
.wc-area-body{display:none;border-top:1px solid #e5e7eb}
.wc-area-body.open{display:block}
.wc-area-inner{display:grid;grid-template-columns:1fr 200px;gap:0}
.wc-area-form{padding:18px}
.wc-area-img{padding:14px;display:flex;flex-direction:column;align-items:center;gap:10px;background:#fafafa;border-left:1px solid #e5e7eb}
.wc-area-img img{max-width:160px;border-radius:6px;border:1px solid #ddd}
.wc-field{margin-bottom:13px}
.wc-field label{display:block;font-size:.82rem;font-weight:600;color:#374151;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px}
.wc-field select,.wc-field input[type=number],.wc-field input[type=text],.wc-field textarea{width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:.9rem;font-family:inherit;background:#fff}
.wc-field textarea{resize:vertical;min-height:72px}
.wc-dims{display:grid;grid-template-columns:1fr auto 1fr auto;gap:6px;align-items:center}
.wc-dims span{color:#888;font-size:.85rem;text-align:center}
.wc-cliche-wrap{display:none;margin-top:8px;padding-left:28px}
.wc-field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.wc-price-info{background:#f9fafb;border-radius:6px;padding:10px 14px;font-size:.82rem;color:#555;margin-top:8px}
.wc-price-info .pu{font-size:1.1rem;font-weight:700;color:#0464AC}
.wc-min-warn{background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;padding:8px 12px;font-size:.8rem;color:#856404;margin-top:8px}
/* PANTONE */
.wc-pantone-section{border-top:1px solid #e5e7eb;padding:14px 18px;display:none}
.wc-pantone-section.show{display:block}
.wc-palette{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:10px}
.wc-swatch{width:32px;height:32px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:transform .15s,border-color .15s;flex-shrink:0}
.wc-swatch:hover,.wc-swatch.sel{transform:scale(1.2);border-color:#0464AC;box-shadow:0 2px 6px rgba(4,100,172,.3)}
.wc-pantone-row{display:flex;align-items:center;gap:8px;margin-bottom:7px;padding:2px;border-radius:6px}
.wc-pantone-row.active{background:rgba(4,100,172,.08);box-shadow:0 0 0 1px rgba(4,100,172,.2)}
.wc-pantone-preview{width:26px;height:26px;border-radius:4px;border:2px solid #ddd;background:#fff;flex-shrink:0}
.wc-pantone-row input{flex:1;padding:7px;border:1px solid #d1d5db;border-radius:4px;font-size:.82rem}
/* Image upload */
.wc-img-section{border-top:1px solid #e5e7eb;padding:14px 18px;display:none}
.wc-img-section.show{display:block}
.wc-img-preview{margin-top:8px;display:none}
.wc-img-preview img{max-width:160px;border-radius:6px;border:1px solid #ddd}
.wc-rm-img{margin-top:6px;padding:4px 10px;background:#dc3545;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:.8rem}
/* RIGHT */
.wc-right{}
.wc-quote{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;position:sticky;top:20px}
.wc-quote-head{background:linear-gradient(135deg,#0464AC,#061B46);color:#fff;padding:16px 20px}
.wc-quote-head h3{margin:0;font-size:1rem;font-weight:700}
.wc-quote-body{padding:18px}
.wc-quote-product{font-weight:700;font-size:.95rem;margin-bottom:12px;color:#111}
.wc-quote-table{width:100%;border-collapse:collapse;font-size:.82rem;margin-bottom:14px}
.wc-quote-table th{text-align:left;padding:5px 6px;border-bottom:2px solid #e5e7eb;color:#6b7280;font-weight:600;font-size:.75rem;text-transform:uppercase}
.wc-quote-table td{padding:5px 6px;border-bottom:1px solid #f3f4f6;vertical-align:top}
.wc-quote-area-row td{background:#f8f9fa;font-weight:600;color:#374151}
.wc-quote-sub{color:#6b7280;font-size:.78rem}
.wc-quote-total-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:2px solid #0464AC;margin-top:8px;font-weight:700;font-size:1rem;color:#0464AC}
.wc-quote-grand{background:#f0f7ff;border-radius:8px;padding:10px 14px;text-align:center;margin-bottom:14px}
.wc-quote-grand .label{font-size:.8rem;color:#555;text-transform:uppercase;letter-spacing:.5px}
.wc-quote-grand .amount{font-size:2rem;font-weight:800;color:#0464AC;line-height:1.1}
.wc-btn-cart{display:block;width:100%;padding:14px;font-size:1rem;font-weight:700;background:#0464AC;color:#fff;border:none;border-radius:8px;cursor:pointer;transition:background .2s}
.wc-btn-cart:hover{background:#061B46}
.wc-btn-cart:disabled{opacity:.4;cursor:not-allowed}
.wc-btn-back{display:block;width:100%;padding:12px;font-size:.9rem;font-weight:600;background:#f3f4f6;color:#374151;border:1px solid #d1d5db;border-radius:8px;cursor:pointer;margin-bottom:10px;text-decoration:none;text-align:center}
.wc-loading-quote{text-align:center;padding:20px;color:#9ca3af;font-size:.9rem}
.wc-no-areas-msg{text-align:center;padding:30px;color:#9ca3af;font-size:.85rem}
.wc-cart-notice{display:none;align-items:center;gap:14px;margin:0 0 18px;padding:14px 18px;border-radius:4px;background:#46b450;color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.12)}
.wc-cart-notice.show{display:flex}
.wc-cart-notice.error{background:#dc3232}
.wc-cart-notice-icon{width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:700}
.wc-cart-notice-message{flex:1;font-size:.95rem;line-height:1.35}
.wc-cart-notice a{color:#fff;font-weight:700;text-decoration:underline;text-underline-offset:2px;white-space:nowrap}
.wc-cart-notice-close{border:0;background:transparent;color:#fff;font-size:24px;line-height:1;cursor:pointer;padding:0;margin-left:4px}
@media(max-width:640px){.wc-cart-notice{align-items:flex-start}.wc-cart-notice a{white-space:normal}}
</style>

<div class="wc-page">
<div id="wc-cart-notice" class="wc-cart-notice" role="status" aria-live="polite">
  <span class="wc-cart-notice-icon">✓</span>
  <span class="wc-cart-notice-message"></span>
  <a class="wc-cart-notice-link" href="<?php echo esc_url(wc_get_cart_url());?>"><?php esc_html_e( 'Ver carrito', 'woo-prices-dynamics-makito' ); ?></a>
  <button type="button" class="wc-cart-notice-close" aria-label="<?php esc_attr_e( 'Cerrar aviso', 'woo-prices-dynamics-makito' ); ?>">×</button>
</div>
<?php if($product): ?>
<a href="<?php echo esc_url(get_permalink($product->get_id())); ?>" class="wc-back">← Volver al producto</a>
<h1 class="wc-title"><?php echo esc_html($product->get_name()); ?> — Personalización</h1>
<?php endif; ?>

<!-- estados -->
<div id="wc-loading" style="text-align:center;padding:80px 20px;color:#6b7280"><div style="font-size:2.5em">⏳</div><p>Cargando opciones...</p></div>
<div id="wc-error" style="display:none;text-align:center;padding:80px 20px;color:#dc2626"><div style="font-size:2.5em">❌</div><p id="wc-error-msg">Error.</p></div>
<div id="wc-no-areas" style="display:none;text-align:center;padding:80px 20px;color:#6b7280"><div style="font-size:2.5em">ℹ️</div><p>Este producto no tiene áreas de marcaje configuradas.</p></div>

<div id="wc-main" style="display:none">
  <div class="wc-layout">
    <!-- COLUMNA IZQUIERDA -->
    <div class="wc-left">
      <div class="wc-notice">
        <h3>📋 Área de marcaje</h3>
        <p>Manipulación y envasado incluidos en el precio de impresión. Los pedidos con impresión se pueden suministrar con una variación del 5% de la cantidad solicitada.</p>
      </div>
      <div class="wc-mode" id="wc-mode-wrap" style="display:none">
        <p>¿Desea marcar todos los colores de este artículo de la misma forma?</p>
        <p class="hint">Elija Sí cuando quiera marcar todos los artículos por igual o No si quiere marcar cada color de forma diferente.</p>
        <label><input type="radio" name="wc-mode" value="global" checked> Sí</label>
        <label><input type="radio" name="wc-mode" value="per-color"> No</label>
      </div>
      <div id="wc-areas-container"></div>
    </div>
    <!-- COLUMNA DERECHA: COTIZACIÓN -->
    <div class="wc-right">
      <div class="wc-quote">
        <div class="wc-quote-head"><h3>💶 Cotización</h3></div>
        <div class="wc-quote-body">
          <div class="wc-quote-product"><?php echo $product ? esc_html($product->get_name()) : ''; ?></div>
          <div id="wc-quote-no-sel" class="wc-no-areas-msg">Activa y configura una zona de impresión para ver el precio.</div>
          <div id="wc-quote-content" style="display:none">
            <div id="wc-loading-quote" class="wc-loading-quote">Calculando...</div>
            <div id="wc-quote-detail" style="display:none">
              <table class="wc-quote-table">
                <thead><tr><th>Descripción</th><th>Uds</th><th>€/u</th><th>Sub</th></tr></thead>
                <tbody id="wc-quote-rows"></tbody>
              </table>
              <div class="wc-quote-total-row"><span>Total personalización</span><span id="wc-cust-total">0,00 €</span></div>
            </div>
            <div class="wc-quote-grand" style="margin-top:12px">
              <div class="label">Total (impuestos no incluidos)</div>
              <div class="amount" id="wc-grand-total">0,00 €</div>
            </div>
          </div>
          <?php if($product): ?>
          <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>" class="wc-btn-back">← Atrás</a>
          <?php endif; ?>
          <button type="button" id="wc-btn-cart" class="wc-btn-cart" disabled>🛒 Finalizar pedido</button>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<script type="text/javascript">
(function($){
'use strict';
var productId=<?php echo $product?intval($product->get_id()):0;?>;
var ajaxUrl='<?php echo esc_js(admin_url('admin-ajax.php'));?>';
var nonce='<?php echo esc_js(wp_create_nonce('wpdm_customization_nonce'));?>';
var cartUrl='<?php echo esc_js(wc_get_cart_url());?>';
var allAreas=[];
var selectedVariations=[];
try{var s=sessionStorage.getItem('wpdm_selected_variations_'+productId);if(s)selectedVariations=JSON.parse(s);}catch(e){}

var palette=[
  {name:'Negro',hex:'#000000',p:'Black C'},{name:'Blanco',hex:'#FFFFFF',p:'White C'},
  {name:'Gris Osc.',hex:'#666666',p:'Cool Gray 11 C'},{name:'Gris Cl.',hex:'#D3D3D3',p:'Cool Gray 3 C'},
  {name:'Rojo',hex:'#FF0000',p:'Red 032 C'},{name:'Rosa',hex:'#FF1493',p:'Pink C'},
  {name:'Granate',hex:'#8B0000',p:'Rhodamine Red C'},{name:'Naranja',hex:'#FF8C00',p:'Orange 021 C'},
  {name:'Amarillo',hex:'#FFD700',p:'Yellow C'},{name:'Verde',hex:'#008000',p:'Green C'},
  {name:'Verde Osc.',hex:'#006400',p:'Green 356 C'},{name:'Azul',hex:'#0000FF',p:'Blue 072 C'},
  {name:'Azul Osc.',hex:'#00008B',p:'Blue 286 C'},{name:'Marrón',hex:'#8B4513',p:'Brown 478 C'}
];

function showLoading(){$('#wc-loading').show();$('#wc-error,#wc-no-areas,#wc-main').hide();}
function showError(m){$('#wc-error-msg').text(m);$('#wc-error').show();$('#wc-loading,#wc-no-areas,#wc-main').hide();}
function showNoAreas(){$('#wc-no-areas').show();$('#wc-loading,#wc-error,#wc-main').hide();}
function showMain(){$('#wc-main').show();$('#wc-loading,#wc-error,#wc-no-areas').hide();}
function showCartNotice(message,isError){
  var $notice=$('#wc-cart-notice');
  $notice.toggleClass('error',!!isError).addClass('show');
  $notice.find('.wc-cart-notice-icon').text(isError?'!':'✓');
  $notice.find('.wc-cart-notice-message').text(message);
  $notice.find('.wc-cart-notice-link').toggle(!isError);
  $('html,body').animate({scrollTop:$notice.offset().top-20},250);
}

function buildPaletteRow(colorNum){
  var h='<div class="wc-pantone-row" data-cn="'+colorNum+'">';
  h+='<div class="wc-pantone-preview"></div>';
  h+='<input type="text" class="wc-pantone-val" data-cn="'+colorNum+'" placeholder="Color '+colorNum+': selecciona o escribe PANTONE...">';
  h+='</div>';
  return h;
}

function buildPaletteHtml(){
  var h='<div class="wc-palette">';
  palette.forEach(function(c){
    var border=c.hex==='#FFFFFF'?'border-color:#ccc;':'';
    h+='<div class="wc-swatch" title="'+c.name+' ('+c.p+')" data-hex="'+c.hex+'" data-pantone="'+c.p+'" style="background:'+c.hex+';'+border+'"></div>';
  });
  h+='</div>';
  return h;
}

function buildAreaCard(area, idx, variation){
  var uid=variation?'var-'+variation.variation_id+'-area-'+idx:'global-area-'+idx;
  var varAttr=variation?' data-variation-id="'+variation.variation_id+'"':'';
  var h='<div class="wc-area" data-area-index="'+idx+'" data-area-id="'+area.print_area_id
       +'" data-area-position="'+(area.position||'Área '+(idx+1))+'" data-uid="'+uid+'"'+varAttr+'>';

  /* HEAD */
  h+='<div class="wc-area-head">';
  h+='<label><input type="checkbox" class="wc-area-enabled"> '+(area.position||'AREA '+(idx+1))+'</label>';
  if(variation) h+='<span style="font-size:.82rem;color:#666;padding:0 8px">'+variation.full_name+' ('+variation.quantity+' uds)</span>';
  else h+='<span></span>';
  h+='<span class="wc-area-price-badge" style="display:none">0,00 €</span>';
  h+='</div>';

  /* BODY */
  h+='<div class="wc-area-body">';
  h+='<div class="wc-area-inner">';

  /* FORM */
  h+='<div class="wc-area-form">';

  /* Técnica */
  h+='<div class="wc-field"><label>Técnica de marcación</label>';
  h+='<select class="wc-technique"><option value="">Selecciona una técnica...</option>';
  if(area.techniques) area.techniques.forEach(function(t){
    h+='<option value="'+t.ref+'" data-name="'+t.name+'" data-max-colors="'+(parseInt(t.max_colors,10)||area.max_colors||1)+'">'+t.name+'</option>';
  });
  h+='</select></div>';

  /* Colores + dimensiones en fila */
  h+='<div class="wc-field-row">';
  h+='<div class="wc-field"><label>Nº colores</label><select class="wc-colors" data-area-max-colors="'+(area.max_colors||1)+'">';
  for(var i=1;i<=(area.max_colors||4);i++) h+='<option value="'+i+'">'+i+' COLOR'+(i>1?'ES':'')+'</option>';
  h+='</select></div>';
  h+='<div class="wc-field"><label>Medida de impresión (mm)</label>';
  h+='<div class="wc-dims"><input type="number" class="wc-width" value="'+(area.width||'')+'" placeholder="Ancho" step="0.1"><span>×</span><input type="number" class="wc-height" value="'+(area.height||'')+'" placeholder="Alto" step="0.1"><span>mm</span></div>';
  h+='</div>';
  h+='</div>';

  /* Precio info */
  h+='<div class="wc-price-info" style="display:none"><div style="display:flex;justify-content:space-between"><span>Precio unidad:</span><span class="wc-unit-price pu">—</span></div><div style="display:flex;justify-content:space-between"><span>Precio total área:</span><span class="wc-area-total-label pu">—</span></div></div>';
  h+='<div class="wc-min-warn" style="display:none">⚠ El precio se ha ajustado al mínimo establecido</div>';

  /* Cliché */
  h+='<div class="wc-field" style="margin-top:4px"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;text-transform:none;letter-spacing:0;font-size:.9rem"><input type="checkbox" class="wc-cliche"> Repetición Cliché</label>';
  h+='<div class="wc-cliche-wrap"><input type="text" class="wc-cliche-order" placeholder="Nº pedido anterior" style="margin-top:6px;max-width:220px"></div></div>';

  h+='</div>'; /* /wc-area-form */

  /* IMG COLUMN */
  h+='<div class="wc-area-img">';
  if(area.area_img){
    h+='<img src="'+area.area_img+'" alt="'+(area.position||'')+'">';
  }
  h+='<div style="font-size:.78rem;color:#888;text-align:center">';
  if(area.width&&area.height) h+='Máx: '+area.width+'×'+area.height+' mm<br>';
  if(area.max_colors) h+='<span class="wc-max-colors-text">Máx colores: '+area.max_colors+'</span>';
  h+='</div>';
  h+='</div>';

  h+='</div>'; /* /wc-area-inner */

  /* PANTONE SECTION */
  h+='<div class="wc-pantone-section" data-uid="'+uid+'">';
  h+='<div style="font-size:.85rem;font-weight:600;color:#374151;margin-bottom:8px">🎨 Colores PANTONE</div>';
  h+=buildPaletteHtml();
  h+='<div class="wc-pantone-rows"></div>';
  h+='</div>';

  /* IMAGE + OBS SECTION */
  h+='<div class="wc-img-section" data-uid="'+uid+'">';
  h+='<div class="wc-field-row">';
  h+='<div class="wc-field"><label>📸 Adjuntar imagen (JPG/PNG/PDF/EPS)</label>';
  h+='<input type="file" class="wc-img-upload" data-uid="'+uid+'" accept="image/*,.pdf,.eps,.ai,.cdr" style="padding:7px;border:1px solid #d1d5db;border-radius:6px;width:100%">';
  h+='<div class="wc-img-preview"><img src=""><button type="button" class="wc-rm-img">🗑 Eliminar</button></div></div>';
  h+='<div class="wc-field"><label>📝 Observaciones</label><textarea class="wc-obs" placeholder="Detalle adicional..."></textarea></div>';
  h+='</div>';
  h+='</div>';

  h+='</div>'; /* /wc-area-body */
  h+='</div>'; /* /wc-area */
  return h;
}

function renderAreas(){
  var mode=$('input[name="wc-mode"]:checked').val()||'global';
  var h='';
  if(mode==='global'){
    allAreas.forEach(function(a,i){h+=buildAreaCard(a,i,null);});
  } else {
    if(!selectedVariations.length){allAreas.forEach(function(a,i){h+=buildAreaCard(a,i,null);});}
    else {
      selectedVariations.forEach(function(v){
        allAreas.forEach(function(a,i){h+=buildAreaCard(a,i,v);});
      });
    }
  }
  $('#wc-areas-container').html(h);
}

function updatePantoneRows($card){
  var n=parseInt($card.find('.wc-colors').val())||1;
  var $sec=$card.find('.wc-pantone-section');
  var $rows=$sec.find('.wc-pantone-rows');
  var current=$rows.find('.wc-pantone-row').length;
  if(n>current){
    for(var i=current+1;i<=n;i++) $rows.append(buildPaletteRow(i));
  } else {
    $rows.find('.wc-pantone-row').slice(n).remove();
  }
}

function setActivePantoneRow($row){
  var $card=$row.closest('.wc-area');
  $card.find('.wc-pantone-row').removeClass('active');
  $row.addClass('active');
  $card.data('active-pantone-cn',$row.data('cn'));
}

function getSelectedTechniqueMaxColors($card){
  var $opt=$card.find('.wc-technique option:selected');
  return parseInt($opt.data('max-colors'),10)||parseInt($card.find('.wc-colors').data('area-max-colors'),10)||1;
}

function updateColorOptionsForTechnique($card){
  var max=getSelectedTechniqueMaxColors($card);
  var $colors=$card.find('.wc-colors');
  var current=parseInt($colors.val(),10)||1;
  var html='';
  for(var i=1;i<=max;i++) html+='<option value="'+i+'">'+i+' COLOR'+(i>1?'ES':'')+'</option>';
  $colors.html(html).val(Math.min(current,max));
  $card.find('.wc-max-colors-text').text('Máx colores: '+max);
  if($card.find('.wc-area-enabled').is(':checked')&&$card.find('.wc-technique').val()){
    updatePantoneRows($card);
  }
}

function showHideDesignSections($card){
  var enabled=$card.find('.wc-area-enabled').is(':checked');
  var tech=$card.find('.wc-technique').val();
  if(enabled&&tech){
    $card.find('.wc-pantone-section,.wc-img-section').addClass('show');
    updatePantoneRows($card);
  } else {
    $card.find('.wc-pantone-section,.wc-img-section').removeClass('show');
  }
}

function calcPrice(){
  var mode=$('input[name="wc-mode"]:checked').val()||'global';
  var totalQty=0;
  selectedVariations.forEach(function(v){totalQty+=v.quantity;});
  if(totalQty<=0) totalQty=1;

  var areas=[];
  $('.wc-area').each(function(){
    var $a=$(this);
    if(!$a.find('.wc-area-enabled').is(':checked')) return;
    var techRef=$a.find('.wc-technique').val(); if(!techRef) return;
    var vid=$a.data('variation-id')||null;
    var areaQty=totalQty;
    if(mode==='per-color'&&vid){
      selectedVariations.forEach(function(v){if(v.variation_id==vid) areaQty=v.quantity;});
    }
    areas.push({
      enabled:true,
      area_id:$a.data('area-id'),
      area_index:$a.data('area-index'),
      area_position:$a.data('area-position'),
      variation_id:vid,
      technique_ref:techRef,
      technique_name:$a.find('.wc-technique option:selected').text(),
      colors:parseInt($a.find('.wc-colors').val())||1,
      width:parseFloat($a.find('.wc-width').val())||0,
      height:parseFloat($a.find('.wc-height').val())||0,
      cliche_repetition:$a.find('.wc-cliche').is(':checked'),
      cliche_order_number:$a.find('.wc-cliche-order').val()||'',
      quantity:areaQty
    });
  });

  var hasAreas=areas.length>0;
  $('#wc-btn-cart').prop('disabled',!hasAreas);
  if(!hasAreas){$('#wc-quote-no-sel').show();$('#wc-quote-content').hide();return;}
  $('#wc-quote-no-sel').hide();$('#wc-quote-content').show();
  $('#wc-loading-quote').show();$('#wc-quote-detail').hide();

  var cusData={mode:mode,areas:areas};
  $.ajax({url:ajaxUrl,type:'POST',data:{
    action:'wpdm_calculate_customization_price',nonce:nonce,
    product_id:productId,total_quantity:totalQty,
    customization_data:JSON.stringify(cusData)
  },success:function(r){
    $('#wc-loading-quote').hide();
    if(!r.success||!r.data){return;}
    var d=r.data;
    var rows='';
    if(d.areas){
      $.each(d.areas,function(i,ap){
        var aIdx=parseInt(i);
        var matchedArea=areas[aIdx]||{};
        var aPos=matchedArea.area_position||('Área '+(aIdx+1));
        var techName=ap.technique_name||matchedArea.technique_name||'Técnica';
        var qty=ap.quantity||totalQty;
        var unitPriceStr=parseFloat(ap.technique_unit_price||0).toFixed(3).replace('.',',');
        var techTotal=parseFloat(ap.technique_total_price||0).toFixed(2).replace('.',',');
        var areaTotal=parseFloat(ap.area_total||0).toFixed(2).replace('.',',');

        rows+='<tr class="wc-quote-area-row"><td colspan="4">» '+aPos+'</td></tr>';
        if(ap.technique_total_price>0){
          rows+='<tr><td>'+techName+'</td><td>'+qty+'</td><td>'+unitPriceStr+' €</td><td>'+techTotal+' €</td></tr>';
        }
        if(ap.minimum_applied){
          rows+='<tr><td colspan="4" style="font-size:.78rem;color:#856404">⚠ Mínimo de técnica aplicado: '+parseFloat(ap.minimum_amount).toFixed(2).replace('.',',')+'€</td></tr>';
          /* update card warning */
          $('.wc-area[data-area-index="'+aIdx+'"] .wc-min-warn').show();
        } else {
          $('.wc-area[data-area-index="'+aIdx+'"] .wc-min-warn').hide();
        }
        if(ap.cliche_repetition_price>0){
          rows+='<tr><td>Repetición Cliché</td><td>'+(ap.cliche_colors_qty||1)+'</td><td>'+parseFloat(ap.cliche_unit_price||0).toFixed(2).replace('.',',')+'€</td><td>'+parseFloat(ap.cliche_repetition_price).toFixed(2).replace('.',',')+'€</td></tr>';
        } else if(ap.cliche_price>0){
          rows+='<tr><td>Cliché fotolito</td><td>'+(ap.cliche_colors_qty||1)+'</td><td>'+parseFloat(ap.cliche_unit_price||0).toFixed(2).replace('.',',')+'€</td><td>'+parseFloat(ap.cliche_price).toFixed(2).replace('.',',')+'€</td></tr>';
        }
        if(ap.color_extra_total>0){
          rows+='<tr><td>Colores adicionales</td><td></td><td></td><td>'+parseFloat(ap.color_extra_total).toFixed(2).replace('.',',')+'€</td></tr>';
        }
        /* update badge and price info on card */
        var $card=$('.wc-area[data-area-index="'+aIdx+'"]');
        $card.find('.wc-area-price-badge').text(areaTotal+' €').show();
        $card.find('.wc-unit-price').text(unitPriceStr+' €');
        $card.find('.wc-area-total-label').text(areaTotal+' €');
        $card.find('.wc-price-info').show();
      });
    }
    var grandTotal=parseFloat(d.grand_total||0).toFixed(2).replace('.',',');
    var custTotal=parseFloat(d.customization_total||0).toFixed(2).replace('.',',');
    $('#wc-quote-rows').html(rows);
    $('#wc-cust-total').text(custTotal+' €');
    $('#wc-grand-total').text(grandTotal+' €');
    $('#wc-quote-detail').show();
    $('#wc-btn-cart').data('cus-data',cusData).data('total-qty',totalQty);
  },error:function(){$('#wc-loading-quote').hide();}});
}

function doAddToCart(){
  var $btn=$('#wc-btn-cart');
  var cusData=$btn.data('cus-data');
  var totalQty=$btn.data('total-qty')||1;
  if(!cusData){alert('Configura al menos un área de marcaje.');return;}
  var mode=$('input[name="wc-mode"]:checked').val()||'global';

  /* merge design data */
  $('.wc-area').each(function(){
    var $a=$(this);
    if(!$a.find('.wc-area-enabled').is(':checked')) return;
    var aIdx=$a.data('area-index'), vid=$a.data('variation-id')||null;
    var pantones=[];
    $a.find('.wc-pantone-row').each(function(){
      var v=$(this).find('.wc-pantone-val').val().trim();
      if(v) pantones.push({colorNum:$(this).data('cn'),value:v});
    });
    var obs=$a.find('.wc-obs').val()||'';
    cusData.areas.forEach(function(ar){
      if(ar.area_index==aIdx&&(!vid||ar.variation_id==vid)){
        ar.pantones=pantones; ar.observations=obs;
      }
    });
  });

  $btn.prop('disabled',true).text('Procesando...');
  var fd=new FormData();
  fd.append('action','wpdm_add_customized_to_cart');
  fd.append('nonce',nonce);
  fd.append('product_id',productId);
  fd.append('mode',mode);
  fd.append('variations',JSON.stringify(selectedVariations));
  fd.append('customization_data',JSON.stringify(cusData));

  var fi=0;
  $('.wc-area').each(function(){
    var $a=$(this);
    if(!$a.find('.wc-area-enabled').is(':checked')) return;
    var uid=$a.data('uid');
    var aIdx=$a.data('area-index'), vid=$a.data('variation-id')||'';
    var f=$a.find('.wc-img-upload')[0];
    if(f&&f.files&&f.files[0]){
      fd.append('images[]',f.files[0]);
      fd.append('images_meta['+fi+'][area_id]',$a.data('area-id'));
      fd.append('images_meta['+fi+'][area_index]',aIdx);
      fd.append('images_meta['+fi+'][variation_id]',vid);
      fi++;
    }
    var pantones=[];
    $a.find('.wc-pantone-row').each(function(){
      var v=$(this).find('.wc-pantone-val').val().trim();
      if(v) pantones.push({colorNum:$(this).data('cn'),value:v});
    });
    fd.append('design['+uid+']',JSON.stringify({areaId:$a.data('area-id'),areaIndex:aIdx,variationId:vid,pantones:pantones,observations:$a.find('.wc-obs').val()||''}));
  });

	  $.ajax({url:ajaxUrl,type:'POST',data:fd,processData:false,contentType:false,
	    success:function(r){
	      if(r.success){
	        $('body').trigger('wc_fragment_refresh');
	        showCartNotice((r.data&&r.data.message)?r.data.message:'Producto añadido al carrito correctamente.',false);
	        $btn.prop('disabled',false).text('🛒 Finalizar pedido');
	      } else {
	        showCartNotice((r.data&&r.data.message?r.data.message:'Error al añadir al carrito.'),true);
	        $btn.prop('disabled',false).text('🛒 Finalizar pedido');
	      }
	    },
	    error:function(){
	      showCartNotice('Error de conexión.',true);
	      $btn.prop('disabled',false).text('🛒 Finalizar pedido');
	    }
	  });
	}

$(document).ready(function(){
  $(document).on('click','.wc-cart-notice-close',function(){
    $('#wc-cart-notice').removeClass('show error');
  });

  if(!productId){showError('No se pudo determinar el producto.');return;}
  showLoading();
  $.ajax({url:ajaxUrl,type:'POST',data:{action:'wpdm_get_customization_data',nonce:nonce,product_id:productId},
    success:function(r){
      if(!r.success||!r.data||!r.data.areas||!r.data.areas.length){showNoAreas();return;}
      allAreas=r.data.areas;
      if(selectedVariations.length) $('#wc-mode-wrap').show();
      renderAreas();
      showMain();
    },error:function(){showError('Error de conexión.');}
  });

  /* Modo */
  $(document).on('change','input[name="wc-mode"]',function(){renderAreas();calcPrice();});

  /* Activar/desactivar área */
  $(document).on('change','.wc-area-enabled',function(){
    var $card=$(this).closest('.wc-area');
    $card.find('.wc-area-body').toggleClass('open',$(this).is(':checked'));
    showHideDesignSections($card);
    calcPrice();
  });

  /* Cambio de técnica */
  $(document).on('change','.wc-technique',function(){
    var $card=$(this).closest('.wc-area');
    updateColorOptionsForTechnique($card);
    showHideDesignSections($card);
    calcPrice();
  });

  /* Cambio de colores */
  $(document).on('change','.wc-colors',function(){
    var $card=$(this).closest('.wc-area');
    if($card.find('.wc-area-enabled').is(':checked')&&$card.find('.wc-technique').val()){
      updatePantoneRows($card);
    }
    calcPrice();
  });

  /* Dimensiones / cliché */
  $(document).on('change','.wc-width,.wc-height,.wc-cliche',function(){
    var $card=$(this).closest('.wc-area');
    if($(this).hasClass('wc-cliche')){
      $card.find('.wc-cliche-wrap').toggle($(this).is(':checked'));
    }
    calcPrice();
  });

  /* Selección de fila PANTONE */
  $(document).on('focus click','.wc-pantone-val,.wc-pantone-preview',function(){
    setActivePantoneRow($(this).closest('.wc-pantone-row'));
  });

  /* Swatch PANTONE */
  $(document).on('click','.wc-swatch',function(){
    var $card=$(this).closest('.wc-area');
    var hex=$(this).data('hex'), pantone=$(this).data('pantone');
    var activeCn=$card.data('active-pantone-cn');
    var $target=activeCn?$card.find('.wc-pantone-row[data-cn="'+activeCn+'"] .wc-pantone-val'):$();
    if(!$target.length){
      $target=$card.find('.wc-pantone-val').filter(function(){return !$(this).val().trim();}).first();
    }
    if(!$target.length) $target=$card.find('.wc-pantone-val').first();
    $target.val(pantone);
    var cn=$target.data('cn');
    setActivePantoneRow($target.closest('.wc-pantone-row'));
    $card.find('.wc-pantone-row[data-cn="'+cn+'"] .wc-pantone-preview').css('background',hex);
    /* highlight swatch */
    $card.find('.wc-swatch').removeClass('sel');
    $(this).addClass('sel');
  });

  $(document).on('input','.wc-pantone-val',function(){
    setActivePantoneRow($(this).closest('.wc-pantone-row'));
    /* clear swatch highlight when typing manually */
  });

  /* Image upload */
  $(document).on('change','.wc-img-upload',function(){
    var f=this.files[0]; if(!f) return;
    if(f.size>5*1024*1024){alert('Máximo 5MB.');$(this).val('');return;}
    var $card=$(this).closest('.wc-area');
    var $prev=$card.find('.wc-img-preview');
    if(f.type.startsWith('image/')){
      var reader=new FileReader();
      reader.onload=function(e){$prev.find('img').attr('src',e.target.result);$prev.show();};
      reader.readAsDataURL(f);
    } else {$prev.find('img').attr('src','');$prev.show();}
  });

  $(document).on('click','.wc-rm-img',function(){
    var $card=$(this).closest('.wc-area');
    $card.find('.wc-img-upload').val('');
    $card.find('.wc-img-preview').hide().find('img').attr('src','');
  });

  /* Add to cart */
  $(document).on('click','#wc-btn-cart',function(){doAddToCart();});
});
})(jQuery);
</script>
<?php get_footer(); ?>
