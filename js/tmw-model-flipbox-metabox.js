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

  function hasMediaLibrary() {
    return typeof wp !== 'undefined' && typeof wp.media !== 'undefined';
  }

  /**
   * Classic metaboxes in Gutenberg run inside an iframe.
   * The local window.wp.data does NOT have 'core/editor' registered —
   * that store only exists in the parent frame (the actual Gutenberg editor).
   * We must reach window.parent.wp.data to dispatch editPost().
   */
  function getEditorStore() {
    var candidates = [];

    // Parent frame first — this is where 'core/editor' lives in Gutenberg.
    if (window.parent && window.parent !== window && window.parent.wp) {
      candidates.push(window.parent.wp);
    }

    // Local frame as fallback (works if Classic Editor plugin is active, no iframe).
    if (typeof wp !== 'undefined') {
      candidates.push(wp);
    }

    for (var i = 0; i < candidates.length; i++) {
      var candidate = candidates[i];
      if (!candidate || !candidate.data || typeof candidate.data.dispatch !== 'function') {
        continue;
      }

      var editor = candidate.data.dispatch('core/editor');
      var select = candidate.data.select('core/editor');

      if (editor && select && typeof editor.editPost === 'function') {
        return { editor: editor, select: select, wpData: candidate.data };
      }
    }

    return null;
  }

  function getPreview(side) {
    return $('#tmw-flipbox-' + side + '-preview');
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
    if (isNaN(n)) {
      n = 1;
    }

    return Math.max(1, Math.min(2.5, n));
  }

  function getCurrentMeta() {
    var store = getEditorStore();
    if (!store) {
      return null; // null = store not reachable, preserve DOM values
    }

    var meta = store.select.getEditedPostAttribute('meta') || {};
    return meta && typeof meta === 'object' ? meta : null;
  }

  function getUiState() {
    return {
      tmw_flip_front_id: Math.max(0, sanitizeInt($('#tmw_flip_front_id').val(), 0)),
      tmw_flip_back_id: Math.max(0, sanitizeInt($('#tmw_flip_back_id').val(), 0)),
      tmw_flip_pos_front: sanitizePos($('#tmw_flip_pos_front').val()),
      tmw_flip_pos_back: sanitizePos($('#tmw_flip_pos_back').val()),
      tmw_flip_zoom_front: sanitizeZoom($('#tmw_flip_zoom_front').val()),
      tmw_flip_zoom_back: sanitizeZoom($('#tmw_flip_zoom_back').val())
    };
  }

  function persistAllMeta() {
    var store = getEditorStore();
    if (!store) {
      return;
    }

    // Merge with current store meta so we don't wipe unrelated keys.
    var currentMeta = store.select.getEditedPostAttribute('meta') || {};
    var nextMeta = $.extend({}, currentMeta, getUiState());
    store.editor.editPost({ meta: nextMeta });
  }

  function updateReadout($input) {
    var readoutSelector = $input.data('readout');
    var unit = $input.data('unit') || '';
    var value = $input.data('control') === 'zoom' ? sanitizeZoom($input.val()).toFixed(1) : sanitizePos($input.val());

    if (readoutSelector) {
      $(readoutSelector).text(value + unit);
    }
  }

  function applyPreview(side) {
    var pos = sanitizePos($('#tmw_flip_pos_' + side).val());
    var zoom = sanitizeZoom($('#tmw_flip_zoom_' + side).val());

    getPreview(side).css({
      backgroundPosition: pos + '% 50%',
      backgroundSize: (zoom * 100) + '% auto'
    });
  }

  function updatePreviewImage(side, url) {
    var imageUrl = url || '';
    var $preview = getPreview(side);

    $preview.attr('data-url', imageUrl);
    $preview.css('background-image', imageUrl ? 'url(' + imageUrl + ')' : 'none');
    $preview.toggleClass('is-active', !!imageUrl);
  }

  /**
   * Sync the DOM inputs from the Gutenberg store.
   * Only runs if the store is reachable AND has a non-zero value —
   * a store value of 0 (the registered default) should not overwrite a
   * PHP-rendered value that came from post_meta or term_meta.
   */
  function hydrateFromMeta() {
    var meta = getCurrentMeta();

    // Store not reachable (Classic Editor, or iframe issue) — keep PHP-rendered values.
    if (!meta) {
      ['front', 'back'].forEach(function (side) {
        updateReadout($('#tmw_flip_pos_' + side));
        updateReadout($('#tmw_flip_zoom_' + side));
        applyPreview(side);
      });
      return;
    }

    // For image IDs: only overwrite DOM if store has a positive (real) value.
    // A store value of 0 means "never saved via REST" — trust the PHP-rendered value.
    ['front', 'back'].forEach(function (side) {
      var idKey = 'tmw_flip_' + side + '_id';
      var storeId = sanitizeInt(meta[idKey], -1);
      if (storeId > 0) {
        $('#tmw_flip_' + side + '_id').val(storeId);
      }
      // If storeId is 0 or -1, leave the PHP-rendered hidden input value alone.
    });

    // For pos/zoom: store has meaningful defaults (50 / 1.0), prefer store value if present.
    ['front', 'back'].forEach(function (side) {
      var posKey = 'tmw_flip_pos_' + side;
      var zoomKey = 'tmw_flip_zoom_' + side;

      if (typeof meta[posKey] !== 'undefined') {
        $('#tmw_flip_pos_' + side).val(sanitizePos(meta[posKey]));
      }
      if (typeof meta[zoomKey] !== 'undefined') {
        $('#tmw_flip_zoom_' + side).val(sanitizeZoom(meta[zoomKey]).toFixed(1));
      }

      updateReadout($('#tmw_flip_pos_' + side));
      updateReadout($('#tmw_flip_zoom_' + side));
      applyPreview(side);
    });
  }

  function openMediaFrame($trigger) {
    if (!hasMediaLibrary()) {
      return;
    }

    var targetId = $trigger.data('target');
    var side = $trigger.data('side');

    var frame = wp.media({
      title: 'Select Image',
      button: { text: 'Use image' },
      multiple: false,
      library: { type: 'image' }
    });

    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      if (!attachment || !attachment.id) {
        return;
      }

      var attachmentUrl = (attachment.sizes && attachment.sizes.full && attachment.sizes.full.url) ? attachment.sizes.full.url : (attachment.url || '');
      $('#' + targetId).val(attachment.id);
      updatePreviewImage(side, attachmentUrl);
      applyPreview(side);
      persistAllMeta();
    });

    frame.open();
  }

  $(function () {
    hydrateFromMeta();

    $('.tmw-flipbox-pick').on('click', function (event) {
      event.preventDefault();
      openMediaFrame($(this));
    });

    $('.tmw-flipbox-remove').on('click', function (event) {
      event.preventDefault();
      var $trigger = $(this);
      var side = $trigger.data('side');
      var targetId = $trigger.data('target');

      $('#' + targetId).val('0');
      updatePreviewImage(side, '');
      applyPreview(side);
      persistAllMeta();
    });

    $('#tmw_flip_pos_front, #tmw_flip_zoom_front, #tmw_flip_pos_back, #tmw_flip_zoom_back').on('input change', function () {
      var $input = $(this);
      var isZoom = $input.data('control') === 'zoom';
      var value = isZoom ? sanitizeZoom($input.val()) : sanitizePos($input.val());

      $input.val(isZoom ? value.toFixed(1) : value);
      updateReadout($input);
      applyPreview($input.data('side'));
      persistAllMeta();
    });

    var store = getEditorStore();
    if (store) {
      var lastMeta = '';
      store.wpData.subscribe(function () {
        var meta = store.select.getEditedPostAttribute('meta') || {};
        var snapshot = {};
        META_KEYS.forEach(function (key) {
          snapshot[key] = meta[key];
        });

        var serialized = JSON.stringify(snapshot);
        if (serialized === lastMeta) {
          return;
        }

        lastMeta = serialized;
        hydrateFromMeta();
      });
    }
  });
})(jQuery);
