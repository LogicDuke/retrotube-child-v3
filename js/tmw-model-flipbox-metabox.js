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

  function hasEditorStore() {
    return typeof wp !== 'undefined' && wp.data && wp.data.select && wp.data.dispatch;
  }

  function getPreview(side) {
    return $('#tmw_flip_' + side + '_preview');
  }

  function getMetaState() {
    if (!hasEditorStore()) {
      return {};
    }

    var meta = wp.data.select('core/editor').getEditedPostAttribute('meta');
    return meta && typeof meta === 'object' ? meta : {};
  }

  function sanitizeInt(value, fallback) {
    var n = parseInt(value, 10);
    return isNaN(n) ? fallback : n;
  }

  function sanitizePos(value) {
    var n = sanitizeInt(value, 50);
    return Math.max(0, Math.min(100, n));
  }

  function sanitizeZoom(value) {
    var n = parseFloat(value);
    if (isNaN(n)) {
      n = 1;
    }
    return Math.max(1, Math.min(2.5, n));
  }

  function normalizeMetaPatch(metaPatch) {
    var out = {};

    Object.keys(metaPatch).forEach(function (key) {
      var value = metaPatch[key];
      if (key.indexOf('_id') !== -1) {
        out[key] = Math.max(0, sanitizeInt(value, 0));
      } else if (key.indexOf('pos') !== -1) {
        out[key] = sanitizePos(value);
      } else if (key.indexOf('zoom') !== -1) {
        out[key] = sanitizeZoom(value);
      } else {
        out[key] = value;
      }
    });

    return out;
  }

  function persistMeta(metaPatch) {
    if (!hasEditorStore()) {
      return;
    }

    var meta = getMetaState();
    wp.data.dispatch('core/editor').editPost({
      meta: $.extend({}, meta, normalizeMetaPatch(metaPatch))
    });
  }

  function updatePreviewImage(side, imageUrl) {
    var $preview = getPreview(side);
    var url = imageUrl || '';

    $preview.attr('data-url', url);
    $preview.css('background-image', url ? 'url(' + url + ')' : 'none');
    $preview.toggleClass('is-active', !!url);
  }

  function updateReadout($input) {
    var readoutSelector = $input.data('readout');
    var unit = $input.data('unit') || '';
    var value = $input.val();

    if ($input.data('control') === 'zoom') {
      value = sanitizeZoom(value).toFixed(1);
    }

    if (typeof readoutSelector === 'string' && readoutSelector.length > 0) {
      $(readoutSelector).text(value + unit);
    }
  }

  function applyPreview(side) {
    var pos = sanitizePos($('#tmw_flip_pos_' + side).val());
    var zoom = sanitizeZoom($('#tmw_flip_zoom_' + side).val());
    var $preview = getPreview(side);

    $preview.css({
      backgroundPosition: pos + '% 50%',
      backgroundSize: (zoom * 100).toFixed(1) + '% auto'
    });
  }

  function applyUiFromMeta(meta) {
    var nextMeta = meta || getMetaState();

    META_KEYS.forEach(function (key) {
      var $field = $('#' + key);
      if (!$field.length || typeof nextMeta[key] === 'undefined' || nextMeta[key] === null || nextMeta[key] === '') {
        return;
      }

      if (key.indexOf('zoom') !== -1) {
        $field.val(sanitizeZoom(nextMeta[key]).toFixed(1));
      } else if (key.indexOf('pos') !== -1) {
        $field.val(sanitizePos(nextMeta[key]));
      } else {
        $field.val(Math.max(0, sanitizeInt(nextMeta[key], 0)));
      }
    });

    ['front', 'back'].forEach(function (side) {
      var id = Math.max(0, sanitizeInt($('#tmw_flip_' + side + '_id').val(), 0));
      var existingUrl = getPreview(side).attr('data-url') || '';
      if (!id) {
        updatePreviewImage(side, '');
      } else if (existingUrl) {
        updatePreviewImage(side, existingUrl);
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
    var $target = $('#' + targetId);

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

      var attachmentUrl = '';
      if (attachment.sizes && attachment.sizes.full && attachment.sizes.full.url) {
        attachmentUrl = attachment.sizes.full.url;
      } else if (attachment.url) {
        attachmentUrl = attachment.url;
      }

      $target.val(attachment.id);
      updatePreviewImage(side, attachmentUrl);
      applyPreview(side);

      persistMeta((function () {
        var out = {};
        out[targetId] = attachment.id;
        return out;
      })());
    });

    frame.open();
  }

  $(function () {
    applyUiFromMeta();

    $('.tmw-flipbox-pick').on('click', function (event) {
      event.preventDefault();
      openMediaFrame($(this));
    });

    $('.tmw-flipbox-remove').on('click', function (event) {
      event.preventDefault();

      var $trigger = $(this);
      var targetId = $trigger.data('target');
      var side = $trigger.data('side');

      $('#' + targetId).val('0');
      updatePreviewImage(side, '');
      applyPreview(side);

      persistMeta((function () {
        var out = {};
        out[targetId] = 0;
        return out;
      })());
    });

    $('#tmw_flip_pos_front, #tmw_flip_zoom_front, #tmw_flip_pos_back, #tmw_flip_zoom_back').on('input change', function () {
      var $input = $(this);
      var side = $input.data('side');
      var fieldId = $input.attr('id');
      var isZoom = $input.data('control') === 'zoom';
      var value = isZoom ? sanitizeZoom($input.val()) : sanitizePos($input.val());

      $input.val(isZoom ? value.toFixed(1) : value);
      updateReadout($input);
      applyPreview(side);

      persistMeta((function () {
        var out = {};
        out[fieldId] = value;
        return out;
      })());
    });

    if (hasEditorStore()) {
      var lastMetaSerialized = '';

      wp.data.subscribe(function () {
        var meta = getMetaState();
        var subset = {};

        META_KEYS.forEach(function (key) {
          subset[key] = meta[key];
        });

        var serialized = JSON.stringify(subset);
        if (serialized === lastMetaSerialized) {
          return;
        }

        lastMetaSerialized = serialized;
        applyUiFromMeta(meta);
      });
    }
  });
})(jQuery);
