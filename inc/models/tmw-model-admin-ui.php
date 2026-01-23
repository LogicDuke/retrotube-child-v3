<?php

/* ======================================================================
 * ACF FIELD GROUPS (now target TAXONOMY=models)
 * ====================================================================== */
add_action('acf/init', function () {
  if (!function_exists('acf_add_local_field_group')) return;

  // Content group (bio etc.) — no hero
  acf_add_local_field_group([
    'key'      => 'group_tmw_model_content_only',
    'title'    => 'Model Content',
    'location' => [[['param' => 'taxonomy', 'operator' => '==', 'value' => 'models']]],
    'position' => 'normal',
    'fields'   => [
      ['key'=>'fld_tmw_bio','label'=>'Biography','name'=>'bio','type'=>'wysiwyg','tabs'=>'all'],
      ['key'=>'fld_tmw_lines','label'=>'Read more: visible lines','name'=>'readmore_lines','type'=>'number','default_value'=>20,'min'=>5,'max'=>100,'step'=>1],
      ['key'=>'fld_tmw_live','label'=>'Live link (optional)','name'=>'live_link','type'=>'url','placeholder'=>'https://...'],
      ['key'=>'fld_tmw_feat_sc','label'=>'Featured models shortcode','name'=>'featured_models_shortcode','type'=>'text','default_value'=>'[featured_models count="4" mode="select-or-random" layout="flipbox"]'],
    ],
  ]);

  // Banner group (new key for models)
  acf_add_local_field_group([
    'key'      => 'group_tmw_model_banner_v2',
    'title'    => 'Banner (1200×350)',
    'location' => [[['param' => 'taxonomy', 'operator' => '==', 'value' => 'models']]],
    'position' => 'normal',
    'fields'   => [
      [
        'key' => 'fld_tmw_banner_source', 'label' => 'Banner source', 'name' => 'banner_source', 'type' => 'button_group',
        'choices' => ['feed'=>'From AWE feed','upload'=>'Upload','url'=>'External URL'], 'default_value'=>'feed',
      ],
      [
        'key'=>'fld_tmw_banner_height','label'=>'Banner height','name'=>'banner_height','type'=>'button_group',
        'choices'=> ['350'=>'1200×350'], 'default_value'=>'350',
      ],
      [
        'key'=>'fld_tmw_banner_url','label'=>'External banner URL','name'=>'banner_image_url','type'=>'url',
        'placeholder'=>'https://example.com/banner.jpg',
        'conditional_logic'=>[[['field'=>'fld_tmw_banner_source','operator'=>'==','value'=>'url']]],
      ],
      [
        'key'=>'fld_tmw_banner_upload','label'=>'Upload banner','name'=>'banner_image','type'=>'image',
        'return_format'=>'array','preview_size'=>'large',
        'conditional_logic'=>[[['field'=>'fld_tmw_banner_source','operator'=>'==','value'=>'upload']]],
      ],
      ['key'=>'fld_tmw_banner_x','label'=>'Position X','name'=>'banner_offset_x','type'=>'range','default_value'=>0,'min'=>-100,'max'=>100,'step'=>1,'append'=>'%'],
      ['key'=>'fld_tmw_banner_y','label'=>'Position Y','name'=>'banner_offset_y','type'=>'range','default_value'=>0,'min'=>-350,'max'=>350,'step'=>1,'append'=>'px'],
    ],
  ]);
});

/* Hide default description & any field literally named "Short Bio" on models */
add_action('admin_head-term.php', function () {
  $screen = get_current_screen();
  if (!$screen || $screen->taxonomy !== 'models') return;
  echo '<style>.term-description-wrap, .form-field.term-description-wrap { display:none !important; }</style>';
});
add_action('admin_footer', function () {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->base !== 'term' || $screen->taxonomy !== 'models') return;
  ?>
  <script>
  jQuery(function($){
    $('.acf-label label').each(function(){
      var t = $(this).text().trim().toLowerCase();
      if(t === 'short bio'){ $(this).closest('.acf-field').hide(); }
    });
  });
  </script>
  <?php
});

/* 3) Admin: two-column layout (right sidebar) */
add_action('admin_enqueue_scripts', function ($hook) {
  if ($hook !== 'term.php') return;
  $screen = get_current_screen();
  if (!$screen || $screen->taxonomy !== 'models') return;

  add_action('admin_head', function () {
    echo '<style>
      .tmw-term-two-col{display:flex; gap:24px; align-items:flex-start}
      .tmw-term-two-col .tmw-term-right{width:330px; flex:0 0 330px}
      .tmw-term-two-col .tmw-term-main{flex:1 1 auto; min-width:0}
      @media(max-width:1024px){ .tmw-term-two-col{display:block} .tmw-term-right{width:auto} }
      /* Full-width banner preview */
      #tmw-banner-preview{margin:12px 0 0;}
      #tmw-banner-note{margin:6px 0 0; color:#666}
    </style>';
  });

  add_action('admin_footer', function () {
    ?>
    <script>
    jQuery(function($){
      // Build columns (form left, SEO box right)
      var $wrap = $('#wpbody-content .wrap').first();
      var $form = $wrap.find('form#edittag, form#addtag').first();
      if(!$form.length) return;

      var $main = $('<div class="tmw-term-main"></div>');
      var $right = $('<div class="tmw-term-right"></div>');
      var $cols = $('<div class="tmw-term-two-col"></div>').append($main).append($right);

      $form.appendTo($main);
      $wrap.append($cols);

      var $rank = $('#rank_math_metabox, .rank-math-metabox, #wpseo_meta');
      if ($rank.length){ $rank.appendTo($right).show(); }

      // Insert banner preview UNDER the whole form for full width
      if (!$('#tmw-banner-preview').length){
        $main.append(
          '<div id="tmw-banner-preview" class="tmw-banner-preview">'+
            '<div class="tmw-banner-container">'+
              '<div class="tmw-banner-frame backend" data-banner-height="350">'+
                '<img class="tmw-banner-preview-img" src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="Banner preview" style="display:none;" />'+
              '</div>'+
            '</div>'+
            '<div class="ph">Banner preview (1039×350)</div>'+
          '</div>'+
          '<div id="tmw-banner-note">Tip: drag the sliders to fine-tune the banner offset. Preview matches the front-end.</div>'
        );
      }

      var $preview = $('#tmw-banner-preview');
      var $frame = $preview.find('.tmw-banner-frame');
      var $ph = $preview.find('.ph');
      function tmwOffsetToFocalPercent(pxValue, baseHeight) {
        var height = Number(baseHeight) || 350;
        if (height <= 0) {
          height = 350;
        }
        var numeric = parseInt(pxValue, 10);
        if (isNaN(numeric)) {
          numeric = 0;
        }
        var clamped = Math.max(-height, Math.min(height, numeric));
        var percent = 50 - (clamped / height * 50);
        return Math.max(0, Math.min(100, percent));
      }

      function tmwApplyBannerFocus(preview, value, posX, baseHeight) {
        var focus = tmwOffsetToFocalPercent(value, baseHeight);
        var x = (typeof posX === 'number' && !isNaN(posX)) ? posX : 50;
        x = Math.max(0, Math.min(100, x));
        var target = preview;
        if (target && target.classList && !target.classList.contains('tmw-banner-frame')) {
          target = target.querySelector ? target.querySelector('.tmw-banner-frame') : null;
        }
        if (!target) {
          target = document.querySelector('#tmw-banner-preview .tmw-banner-frame, #tmwBannerPreview .tmw-banner-frame');
        }
        if (!target) {
          return focus;
        }
        var img = target.querySelector('img');
        if (img && img.style) {
          img.style.objectPosition = x + '% ' + focus + '%';
        }
        return focus;
      }
      // ===== TMW: Dock Height / X / Y controls directly above the preview =====
(function(){
  // small toolbar styling
  if(!document.getElementById('tmw-controls-dock-css')){
    $('head').append(
      '<style id="tmw-controls-dock-css">\
        #tmw-controls-dock{background:#fff;border:1px solid #e5e5e5;border-radius:6px;margin:10px 0 8px;padding:10px 12px;box-shadow:0 1px 2px rgba(0,0,0,.04)}\
        #tmw-controls-dock .acf-field{margin:6px 0;padding:0;border:0}\
        #tmw-controls-dock .acf-label{min-width:160px}\
        #tmw-controls-dock .acf-input input[type=range]{width:220px}\
        #tmw-controls-dock .acf-input input[type=number]{max-width:90px}\
        @media (max-width: 782px){#tmw-controls-dock .acf-label{min-width:auto}}\
      </style>'
    );
  }

  // helper to find an ACF field by key/name (you already use this selector pattern)
  function fieldSel(key,name){
    var $f = $('.acf-field[data-key="'+key+'"]');
    if(!$f.length && name) $f = $('.acf-field[data-name="'+name+'"]');
    return $f;
  }

  // create the dock and move fields into it (keeps original inputs, so saving still works)
  if(!$('#tmw-controls-dock').length){
    var $dock = $('<div id="tmw-controls-dock" class="tmw-controls-dock"></div>').insertBefore($preview);

    // Detach the existing ACF fields
    var $h = fieldSel('fld_tmw_banner_height','banner_height').detach();  // Height (radio group)
    var $x = fieldSel('fld_tmw_banner_x','banner_offset_x').detach();     // Position X (range)
    var $y = fieldSel('fld_tmw_banner_y','banner_offset_y').detach();     // Position Y (range)

    // Order: Height, X, Y
    if($h.length) $dock.append($h);
    if($x.length) $dock.append($x);
    if($y.length) $dock.append($y);

    // Optional: if you also want the Source switch here, uncomment next line:
    // fieldSel('fld_tmw_banner_source','banner_source').detach().prependTo($dock);
  }
})();


      function fieldSel(key,name){
        var $f = $('.acf-field[data-key="'+key+'"]');
        if(!$f.length && name) $f = $('.acf-field[data-name="'+name+'"]');
        return $f;
      }
      function readSrc(){
        var $s = fieldSel('fld_tmw_banner_source','banner_source');
        var v = $s.find('input[type="radio"]:checked').val();
        if(!v) v = $s.find('input[type="hidden"]').val();
        return (v||'feed').trim();
      }
      function readHeight(){
        return 350;
      }
      function readURL(){
        var $u = fieldSel('fld_tmw_banner_url','banner_image_url');
        return ($u.find('input[type="url"], input[type="text"]').val() || '').trim();
      }
      function readUpload(){
        var $up = fieldSel('fld_tmw_banner_upload','banner_image');
        var src = $up.find('.image-wrap img').attr('src');
        return (src || '').trim();
      }
      function readX(){ var $x=fieldSel('fld_tmw_banner_x','banner_offset_x'); return parseFloat($x.find('input').val()||0); }
      function readY(){ var $y=fieldSel('fld_tmw_banner_y','banner_offset_y'); return parseFloat($y.find('input').val()||0); }

      function apply(url){
        var x = readX(), y = readY(), h = readHeight();
        var posX = Math.max(0, Math.min(100, 50 + x));
        var yPx = Math.max(-1000, Math.min(1000, y));
      $frame = $('#tmw-banner-preview .tmw-banner-frame');
      $frame.attr('data-banner-height', h);
      var $img = $frame.find('img.tmw-banner-preview-img');
      $('#tmw-banner-preview .ph').text('Banner preview (1039×'+h+')');
      if(url){
        var safeUrl = String(url).replace(/"/g, '\\"');
        if ($img.length){
          $img.attr('src', safeUrl).attr('aria-hidden', 'false').show();
        }
        if ($frame.length) {
          tmwApplyBannerFocus($frame[0], yPx, posX, h);
        }
        $ph.hide();
      }else{
        if ($img.length){
          $img.removeAttr('src').attr('aria-hidden', 'true').hide();
        }
        if ($frame.length) {
          tmwApplyBannerFocus($frame[0], 0, 50, h);
        }
        $ph.show();
      }
      }

      function refresh(){
        var s = readSrc();
        if(s === 'upload'){ apply(readUpload()); }
        else if(s === 'url'){ apply(readURL()); }
        else {
          // from feed
          $.get(ajaxurl, {action:'tmw_preview_banner', term_id: ($('#tag_ID').val()||0)})
           .done(function(r){ apply(r && r.success && r.data ? r.data.url : ''); })
           .fail(function(){ apply(''); });
        }
      }

      // Initial draw
      refresh();

      // Live updates
      $(document).on('input change keyup blur',
        '.acf-field[data-key="fld_tmw_banner_source"] input,'+
        '.acf-field[data-key="fld_tmw_banner_height"] input,'+
        '.acf-field[data-key="fld_tmw_banner_url"] input,'+
        '.acf-field[data-key="fld_tmw_banner_upload"] input,'+
        '.acf-field[data-key="fld_tmw_banner_x"] input,'+
        '.acf-field[data-key="fld_tmw_banner_y"] input', refresh);

      // After picking image
      $(document).on('click', '.acf-field[data-key="fld_tmw_banner_upload"] .acf-button, .media-modal .media-button-select', function(){
        setTimeout(refresh, 400);
      });
    });
    </script>
    <?php
  });
}, 11);

/* 4) Banner helpers */
if (!function_exists('tmw_pick_banner_from_feed_row')) {
  /**
   * Select a banner image URL from an AWE feed row.
   *
   * @param array $row Feed row data.
   * @return string Banner URL or empty string.
   */
  function tmw_pick_banner_from_feed_row($row) {
    if (!is_array($row)) return '';
    $urls = [];
    $walk = function($v) use (&$walk,&$urls){
      if (is_string($v) && preg_match('~https?://[^\s"]+\.(?:jpe?g|png|webp)(?:\?[^\s"]*)?$~i',$v)) $urls[]=$v;
      elseif (is_array($v)) foreach($v as $vv) $walk($vv);
    };
    $walk($row);
    $urls = array_values(array_unique($urls));
    if (!$urls) return '';
    $score = function($u){
      $s = 0;
      if (strpos($u,'800x600')!==false || strpos($u,'896x504')!==false) $s += 6;   // landscape
      if (strpos($u,'600x800')!==false || strpos($u,'504x896')!==false) $s -= 4;   // portrait
      if (preg_match('~(\d{3,4})x(\d{3,4})~',$u,$m)) $s += max((int)$m[1],(int)$m[2])/1200;
      return $s;
    };
    usort($urls, function($a,$b) use($score){ return $score($b) <=> $score($a); });
    return $urls[0];
  }
}
add_action('wp_ajax_tmw_preview_banner', function(){
  if (!current_user_can('manage_categories')) wp_send_json_error('forbidden', 403);
  $term_id = (int)($_GET['term_id'] ?? 0);
  if (!$term_id) wp_send_json_error('no term', 400);
  $url = tmw_resolve_model_banner_url(0, $term_id);
  wp_send_json_success(['url'=>$url]);
});
