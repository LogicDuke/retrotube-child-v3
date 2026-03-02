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

  function getAuditConfig() {
    if (typeof window === 'undefined' || typeof window.tmwFlipboxAudit === 'undefined') {
      return { enabled: false, metaKeys: [], metaRegisteredInRest: {} };
    }

    return window.tmwFlipboxAudit;
  }

  function isAuditEnabled() {
    return !!getAuditConfig().enabled;
  }

  function auditLogPreview(message, payload) {
    if (!isAuditEnabled()) {
      return;
    }
    console.log('[TMW-FLIPBOX-AUDIT-PREVIEW] ' + message, payload || {});
  }

  function auditLogSave(message, payload) {
    if (!isAuditEnabled()) {
      return;
    }
    console.log('[TMW-FLIPBOX-AUDIT-SAVE] ' + message, payload || {});
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

  function persistMeta(metaPatch) {
    if (!hasEditorStore()) {
      return;
    }

    var meta = getMetaState();
    wp.data.dispatch('core/editor').editPost({
      meta: $.extend({}, meta, metaPatch)
    });
  }

  function sanitizePos(value) {
    var n = parseInt(value, 10);
    if (isNaN(n)) {
      n = 50;
    }
    return Math.max(0, Math.min(100, n));
  }

  function sanitizeZoom(value) {
    var n = parseFloat(value);
    if (isNaN(n)) {
      n = 1;
    }
    return Math.max(1, Math.min(2.5, n));
  }

  function updatePreviewImage(side, imageUrl) {
    var $preview = getPreview(side);
    var url = imageUrl || '';

    $preview.attr('data-url', url);
    if (url) {
      $preview.css('background-image', 'url(' + url + ')').addClass('is-active');
      auditLogPreview('Image applied to preview element via background-image', {
        side: side,
        targetElement: $preview.attr('id'),
        mode: 'background-image',
        url: url
      });
    } else {
      $preview.css('background-image', 'none').removeClass('is-active');
      auditLogPreview('Image cleared from preview element', {
        side: side,
        targetElement: $preview.attr('id'),
        mode: 'background-image',
        url: ''
      });
    }
  }

  function auditPreviewComputedStyles(side, origin) {
    if (!isAuditEnabled()) {
      return;
    }

    var element = getPreview(side).get(0);
    if (!element) {
      auditLogPreview('Preview element not found', { side: side, origin: origin });
      return;
    }

    var styles = window.getComputedStyle(element);
    var payload = {
      side: side,
      origin: origin,
      tagName: element.tagName,
      clientWidth: element.clientWidth,
      clientHeight: element.clientHeight,
      aspectRatio: styles.getPropertyValue('aspect-ratio'),
      width: styles.getPropertyValue('width'),
      height: styles.getPropertyValue('height'),
      objectFit: styles.getPropertyValue('object-fit'),
      objectPosition: styles.getPropertyValue('object-position'),
      backgroundSize: styles.getPropertyValue('background-size'),
      backgroundPosition: styles.getPropertyValue('background-position'),
      overflow: styles.getPropertyValue('overflow'),
      backgroundImage: styles.getPropertyValue('background-image')
    };

    auditLogPreview('Computed preview styles', payload);
  }

  function auditGutenbergMeta(origin) {
    if (!isAuditEnabled() || !hasEditorStore()) {
      return;
    }

    var config = getAuditConfig();
    var meta = getMetaState();
    var snapshot = {};
    (config.metaKeys || []).forEach(function (key) {
      snapshot[key] = typeof meta[key] === 'undefined' ? null : meta[key];
    });

    auditLogSave('Gutenberg meta snapshot', {
      origin: origin,
      metaRegisteredInRest: config.metaRegisteredInRest || {},
      keys: snapshot,
      isSavingPost: wp.data.select('core/editor').isSavingPost(),
      isAutosavingPost: wp.data.select('core/editor').isAutosavingPost()
    });
  }

  function applyPreview(side) {
    var pos = sanitizePos($('#tmw_flip_pos_' + side).val());
    var zoom = sanitizeZoom($('#tmw_flip_zoom_' + side).val());
    var $preview = getPreview(side);

    $preview.css({
      backgroundPosition: pos + '% 50%',
      backgroundSize: 'calc(' + zoom.toFixed(1) + ' * 100%) auto'
    });
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

  function hydrateFromMeta() {
    var meta = getMetaState();

    META_KEYS.forEach(function (key) {
      var $field = $('#' + key);
      if (!$field.length || typeof meta[key] === 'undefined' || meta[key] === null || meta[key] === '') {
        return;
      }

      if (key.indexOf('zoom') !== -1) {
        $field.val(sanitizeZoom(meta[key]).toFixed(1));
      } else {
        $field.val(meta[key]);
      }
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
      persistMeta((function () {
        var out = {};
        out[targetId] = parseInt(attachment.id, 10) || 0;
        return out;
      })());

      updatePreviewImage(side, attachmentUrl);
      applyPreview(side);
      auditPreviewComputedStyles(side, 'after-media-select');
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
      var targetId = $trigger.data('target');
      var side = $trigger.data('side');

      $('#' + targetId).val('');
      persistMeta((function () {
        var out = {};
        out[targetId] = 0;
        return out;
      })());
      updatePreviewImage(side, '');
      applyPreview(side);
    });

    $('#tmw_flip_pos_front, #tmw_flip_zoom_front, #tmw_flip_pos_back, #tmw_flip_zoom_back').on('input change', function () {
      var $input = $(this);
      var side = $input.data('side');
      var fieldId = $input.attr('id');
      var value = $input.data('control') === 'zoom' ? sanitizeZoom($input.val()) : sanitizePos($input.val());

      $input.val($input.data('control') === 'zoom' ? value.toFixed(1) : value);
      persistMeta((function () {
        var out = {};
        out[fieldId] = value;
        return out;
      })());
      updateReadout($input);
      applyPreview(side);
    });

    $('#tmw_flip_pos_front, #tmw_flip_zoom_front, #tmw_flip_pos_back, #tmw_flip_zoom_back').each(function () {
      updateReadout($(this));
    });

    ['front', 'back'].forEach(function (side) {
      var $preview = getPreview(side);
      updatePreviewImage(side, $preview.attr('data-url') || '');
      applyPreview(side);
      auditPreviewComputedStyles(side, 'dom-ready');
    });

    if (isAuditEnabled() && hasEditorStore()) {
      var lastSerialized = '';
      wp.data.subscribe(function () {
        var meta = getMetaState();
        var currentSerialized = JSON.stringify(meta || {});
        var isSavingPost = wp.data.select('core/editor').isSavingPost();

        if (currentSerialized !== lastSerialized) {
          auditGutenbergMeta('meta-change');
          lastSerialized = currentSerialized;
        }

        if (isSavingPost) {
          auditGutenbergMeta('before-or-during-save');
        }
      });
    }
  });
})(jQuery);
