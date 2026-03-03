/**
 * Flipbox metabox — saves via direct AJAX, completely independent of Gutenberg.
 * No wp.data, no REST store, no iframe tricks needed.
 */
(function ($) {
  'use strict';

  var cfg = window.tmwFlipbox || {};   // localised by PHP
  var ajaxUrl  = cfg.ajaxUrl  || (window.ajaxurl || '');
  var postId   = parseInt(cfg.postId, 10) || 0;
  var nonce    = cfg.nonce    || '';

  // ─── helpers ────────────────────────────────────────────────────────────────

  function sanitizeInt(v, fb) { var n = parseInt(v, 10); return isNaN(n) ? fb : n; }
  function sanitizePos(v)  { return Math.max(0, Math.min(100, sanitizeInt(v, 50))); }
  function sanitizeZoom(v) { var n = parseFloat(v); return isNaN(n) ? 1 : Math.max(1, Math.min(2.5, n)); }

  function getPreview(side) { return $('#tmw-flipbox-' + side + '-preview'); }

  function applyPreview(side) {
    getPreview(side).css({
      backgroundPosition: sanitizePos($('#tmw_flip_pos_' + side).val()) + '% 50%',
      backgroundSize: (sanitizeZoom($('#tmw_flip_zoom_' + side).val()) * 100) + '% auto'
    });
  }

  function updatePreviewImage(side, url) {
    var $p = getPreview(side);
    $p.css('background-image', url ? 'url(' + url + ')' : 'none');
    $p.toggleClass('is-active', !!url);
  }

  function updateReadout($input) {
    var sel  = $input.data('readout');
    var unit = $input.data('unit') || '';
    var v    = $input.data('control') === 'zoom'
      ? sanitizeZoom($input.val()).toFixed(1)
      : sanitizePos($input.val());
    if (sel) { $(sel).text(v + unit); }
  }

  // ─── AJAX save — the ONLY save mechanism ────────────────────────────────────

  var saveTimer = null;

  function saveNow() {
    if (!postId || !nonce || !ajaxUrl) {
      return;
    }

    $.post(ajaxUrl, {
      action:               'tmw_flipbox_save',
      nonce:                nonce,
      post_id:              postId,
      tmw_flip_front_id:    sanitizeInt($('#tmw_flip_front_id').val(), 0),
      tmw_flip_back_id:     sanitizeInt($('#tmw_flip_back_id').val(), 0),
      tmw_flip_pos_front:   sanitizePos($('#tmw_flip_pos_front').val()),
      tmw_flip_pos_back:    sanitizePos($('#tmw_flip_pos_back').val()),
      tmw_flip_zoom_front:  sanitizeZoom($('#tmw_flip_zoom_front').val()),
      tmw_flip_zoom_back:   sanitizeZoom($('#tmw_flip_zoom_back').val())
    });
  }

  function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveNow, 400);
  }

  // ─── media picker ────────────────────────────────────────────────────────────

  function openMediaFrame($trigger) {
    if (typeof wp === 'undefined' || !wp.media) { return; }

    var targetId = $trigger.data('target');
    var side     = $trigger.data('side');

    var frame = wp.media({
      title: 'Select Image',
      button: { text: 'Use image' },
      multiple: false,
      library: { type: 'image' }
    });

    frame.on('select', function () {
      var att = frame.state().get('selection').first().toJSON();
      if (!att || !att.id) { return; }
      var url = (att.sizes && att.sizes.full) ? att.sizes.full.url : (att.url || '');
      $('#' + targetId).val(att.id);
      updatePreviewImage(side, url);
      applyPreview(side);
      saveNow();   // save immediately on pick
    });

    frame.open();
  }

  // ─── bootstrap ───────────────────────────────────────────────────────────────

  $(function () {
    ['front', 'back'].forEach(function (side) {
      updateReadout($('#tmw_flip_pos_' + side));
      updateReadout($('#tmw_flip_zoom_' + side));
      applyPreview(side);
    });

    $('.tmw-flipbox-pick').on('click', function (e) {
      e.preventDefault();
      openMediaFrame($(this));
    });

    $('.tmw-flipbox-remove').on('click', function (e) {
      e.preventDefault();
      var side = $(this).data('side');
      var tid  = $(this).data('target');
      $('#' + tid).val('0');
      updatePreviewImage(side, '');
      applyPreview(side);
      saveNow();
    });

    $('#tmw_flip_pos_front, #tmw_flip_zoom_front, #tmw_flip_pos_back, #tmw_flip_zoom_back')
      .on('input change', function () {
        var $i = $(this);
        var isZoom = $i.data('control') === 'zoom';
        var v = isZoom ? sanitizeZoom($i.val()) : sanitizePos($i.val());
        $i.val(isZoom ? v.toFixed(1) : v);
        updateReadout($i);
        applyPreview($i.data('side'));
        scheduleSave();
      });
  });

})(jQuery);
