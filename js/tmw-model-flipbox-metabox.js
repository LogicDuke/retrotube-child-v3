(function ($) {
  'use strict';

  var META_KEYS = [
    'tmw_flip_front_id',
    'tmw_flip_back_id',
    'tmw_flip_pos_front',
    'tmw_flip_pos_back',
    'tmw_flip_zoom_front',
    'tmw_flip_zoom_back'
  ];

  // ─── helpers ────────────────────────────────────────────────────────────────

  function hasMediaLibrary() {
    return typeof wp !== 'undefined' && typeof wp.media !== 'undefined';
  }

  function sanitizeInt(value, fallback) {
    var n = parseInt(value, 10);
    return isNaN(n) ? fallback : n;
  }
  function sanitizePos(value) {
    return Math.max(0, Math.min(100, sanitizeInt(value, 50)));
  }
  function sanitizeZoom(value) {
    var n = parseFloat(value);
    return isNaN(n) ? 1 : Math.max(1, Math.min(2.5, n));
  }

  function getPreview(side) {
    return $('#tmw-flipbox-' + side + '-preview');
  }

  function applyPreview(side) {
    getPreview(side).css({
      backgroundPosition: sanitizePos($('#tmw_flip_pos_' + side).val()) + '% 50%',
      backgroundSize: (sanitizeZoom($('#tmw_flip_zoom_' + side).val()) * 100) + '% auto'
    });
  }

  function updatePreviewImage(side, url) {
    var $p = getPreview(side);
    $p.attr('data-url', url || '');
    $p.css('background-image', url ? 'url(' + url + ')' : 'none');
    $p.toggleClass('is-active', !!url);
  }

  function updateReadout($input) {
    var sel = $input.data('readout');
    var unit = $input.data('unit') || '';
    var val = $input.data('control') === 'zoom'
      ? sanitizeZoom($input.val()).toFixed(1)
      : sanitizePos($input.val());
    if (sel) { $(sel).text(val + unit); }
  }

  // ─── read current DOM state ──────────────────────────────────────────────────

  function getUiState() {
    return {
      tmw_flip_front_id:  Math.max(0, sanitizeInt($('#tmw_flip_front_id').val(), 0)),
      tmw_flip_back_id:   Math.max(0, sanitizeInt($('#tmw_flip_back_id').val(), 0)),
      tmw_flip_pos_front: sanitizePos($('#tmw_flip_pos_front').val()),
      tmw_flip_pos_back:  sanitizePos($('#tmw_flip_pos_back').val()),
      tmw_flip_zoom_front: sanitizeZoom($('#tmw_flip_zoom_front').val()),
      tmw_flip_zoom_back:  sanitizeZoom($('#tmw_flip_zoom_back').val())
    };
  }

  // ─── Gutenberg store access ──────────────────────────────────────────────────
  // Classic metaboxes run inside an iframe. The 'core/editor' store only exists
  // in the PARENT frame (the real Gutenberg editor window).
  // We reach it via window.parent.wp.data.
  // We also handle the case where Gutenberg hasn't finished initialising yet
  // by retrying until the store becomes available.

  var _storeCache = null;

  function resolveEditorStore() {
    if (_storeCache) { return _storeCache; }

    var sources = [];
    try {
      if (window.parent && window.parent !== window && window.parent.wp) {
        sources.push(window.parent.wp);
      }
    } catch (e) { /* cross-origin guard */ }
    if (typeof wp !== 'undefined') { sources.push(wp); }

    for (var i = 0; i < sources.length; i++) {
      var w = sources[i];
      if (!w || !w.data || typeof w.data.dispatch !== 'function') { continue; }
      try {
        var ed  = w.data.dispatch('core/editor');
        var sel = w.data.select('core/editor');
        if (ed && sel && typeof ed.editPost === 'function') {
          _storeCache = { editor: ed, select: sel, wpData: w.data };
          return _storeCache;
        }
      } catch (e) { /* store not ready yet */ }
    }
    return null;
  }

  // Push current UI state into the Gutenberg store so it travels with the save request.
  function pushMetaToStore(store) {
    if (!store) { return; }
    try {
      var current = store.select.getEditedPostAttribute('meta') || {};
      var next = $.extend({}, current, getUiState());
      store.editor.editPost({ meta: next });
    } catch (e) { /* guard */ }
  }

  // ─── init sequence ───────────────────────────────────────────────────────────

  function initEditorBindings() {
    var store = resolveEditorStore();
    if (!store) { return false; }   // not ready yet

    var lastSaving = false;

    store.wpData.subscribe(function () {
      try {
        var isSaving = store.select.isSavingPost();

        // Moment the save is TRIGGERED: inject our meta values into the store
        // so they are included in the REST payload Gutenberg is about to send.
        if (isSaving && !lastSaving) {
          pushMetaToStore(store);
        }
        lastSaving = isSaving;
      } catch (e) { /* guard */ }
    });

    return true;
  }

  // Poll until Gutenberg store is ready (it may not be on DOMContentLoaded).
  function waitForStore() {
    if (initEditorBindings()) { return; }
    var attempts = 0;
    var timer = setInterval(function () {
      attempts++;
      if (initEditorBindings() || attempts > 40) {
        clearInterval(timer);
      }
    }, 250);
  }

  // ─── media picker ────────────────────────────────────────────────────────────

  function openMediaFrame($trigger) {
    if (!hasMediaLibrary()) { return; }

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

      var url = (att.sizes && att.sizes.full && att.sizes.full.url)
        ? att.sizes.full.url
        : (att.url || '');

      $('#' + targetId).val(att.id);
      updatePreviewImage(side, url);
      applyPreview(side);

      // Also push immediately so the store is dirty (shows "unsaved changes").
      pushMetaToStore(resolveEditorStore());
    });

    frame.open();
  }

  // ─── bootstrap ───────────────────────────────────────────────────────────────

  $(function () {
    // Render previews from PHP-rendered values (no store overwrite on load).
    ['front', 'back'].forEach(function (side) {
      updateReadout($('#tmw_flip_pos_' + side));
      updateReadout($('#tmw_flip_zoom_' + side));
      applyPreview(side);
    });

    // Media picker buttons.
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
      pushMetaToStore(resolveEditorStore());
    });

    // Sliders.
    $('#tmw_flip_pos_front, #tmw_flip_zoom_front, #tmw_flip_pos_back, #tmw_flip_zoom_back')
      .on('input change', function () {
        var $i = $(this);
        var isZoom = $i.data('control') === 'zoom';
        var val = isZoom ? sanitizeZoom($i.val()) : sanitizePos($i.val());
        $i.val(isZoom ? val.toFixed(1) : val);
        updateReadout($i);
        applyPreview($i.data('side'));
        pushMetaToStore(resolveEditorStore());
      });

    // Start polling for the parent Gutenberg store.
    waitForStore();
  });

})(jQuery);
