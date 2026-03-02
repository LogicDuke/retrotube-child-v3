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

  function getEditorStore() {
    if (typeof wp === 'undefined' || !wp.data || !wp.data.dispatch || !wp.data.select) {
      return null;
    }

    return {
      editor: wp.data.dispatch('core/editor'),
      select: wp.data.select('core/editor')
    };
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
      return {};
    }

    var meta = store.select.getEditedPostAttribute('meta') || {};
    return meta && typeof meta === 'object' ? meta : {};
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

    var nextMeta = $.extend({}, getCurrentMeta(), getUiState());
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

  function hydrateFromMeta() {
    var meta = getCurrentMeta();

    $('#tmw_flip_front_id').val(Math.max(0, sanitizeInt(meta.tmw_flip_front_id, sanitizeInt($('#tmw_flip_front_id').val(), 0))));
    $('#tmw_flip_back_id').val(Math.max(0, sanitizeInt(meta.tmw_flip_back_id, sanitizeInt($('#tmw_flip_back_id').val(), 0))));
    $('#tmw_flip_pos_front').val(sanitizePos(typeof meta.tmw_flip_pos_front !== 'undefined' ? meta.tmw_flip_pos_front : $('#tmw_flip_pos_front').val()));
    $('#tmw_flip_pos_back').val(sanitizePos(typeof meta.tmw_flip_pos_back !== 'undefined' ? meta.tmw_flip_pos_back : $('#tmw_flip_pos_back').val()));
    $('#tmw_flip_zoom_front').val(sanitizeZoom(typeof meta.tmw_flip_zoom_front !== 'undefined' ? meta.tmw_flip_zoom_front : $('#tmw_flip_zoom_front').val()).toFixed(1));
    $('#tmw_flip_zoom_back').val(sanitizeZoom(typeof meta.tmw_flip_zoom_back !== 'undefined' ? meta.tmw_flip_zoom_back : $('#tmw_flip_zoom_back').val()).toFixed(1));

    ['front', 'back'].forEach(function (side) {
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
      wp.data.subscribe(function () {
        var meta = getCurrentMeta();
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
