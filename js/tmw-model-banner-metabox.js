/**
 * Model banner metabox JS.
 * Keeps the classic metabox hidden fields updated and mirrors the same values
 * into the block editor meta store so Gutenberg REST saves persist them.
 */
(function ($) {
  'use strict';

  function editorApi() {
    if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.select) {
      return wp;
    }
    if (window.parent && window.parent !== window && window.parent.wp && window.parent.wp.data && window.parent.wp.data.dispatch && window.parent.wp.data.select) {
      return window.parent.wp;
    }
    return null;
  }

  function mediaApi() {
    if (typeof wp !== 'undefined' && wp.media) {
      return wp;
    }
    if (window.parent && window.parent !== window && window.parent.wp && window.parent.wp.media) {
      return window.parent.wp;
    }
    return null;
  }

  function sanitizeFocus(value) {
    var n = parseInt(value, 10);
    return isNaN(n) ? 50 : Math.max(0, Math.min(100, n));
  }

  function metaboxRoot() {
    var $root = $('#tmw-banner-metabox-root');
    if ($root.length) {
      return $root.first();
    }

    var $slider = $('#tmwBannerSlider').first();
    if ($slider.length) {
      var $fallback = $slider.closest('#model_banner_position, .postbox, .components-panel__body, .meta-box-sortables');
      if ($fallback.length) {
        return $fallback.first();
      }
    }

    return $(document.body);
  }

  function scoped(selector) {
    return metaboxRoot().find(selector).first();
  }

  function getBannerId() {
    return parseInt(scoped('[id="tmw_banner_image_id"]').val(), 10) || 0;
  }

  function currentMeta() {
    return {
      tmw_banner_image_id: getBannerId(),
      banner_image: getBannerId(),
      _banner_focal_y: sanitizeFocus(scoped('#tmwBannerSlider').val())
    };
  }

  function syncMetaToEditor() {
    var api = editorApi();
    if (!api) { return; }

    try {
      api.data.dispatch('core/editor').editPost({ meta: currentMeta() });
    } catch (err) {
      // Classic editor or unavailable editor store.
    }
  }

  function stripDuplicateBannerUis() {
    var $root = metaboxRoot();

    $('[id="tmw_banner_image_id"]').each(function () {
      var $field = $(this);
      if ($root.has($field).length) {
        return;
      }
      $field.prop('disabled', true);
    });

    $('.tmw-banner-picker').each(function () {
      var $picker = $(this);
      if ($root.has($picker).length) {
        return;
      }

      var $container = $picker.closest('.postbox, .components-panel__body, .components-base-control, .editor-post-taxonomies__hierarchical-terms-list');
      if ($container.length) {
        $container.hide();
      } else {
        $picker.hide();
      }
    });
  }

  function ensurePreviewFrame() {
    var $wrap = scoped('#tmw-banner-preview');
    if (!$wrap.length) {
      return $();
    }

    var $frame = $wrap.find('.tmw-banner-frame').first();
    if (!$frame.length) {
      $frame = $('<div class="tmw-banner-frame backend"></div>').appendTo($wrap);
    }

    var $img = $frame.find('img').first();
    if (!$img.length) {
      $img = $('<img alt="Banner preview" />').appendTo($frame);
    }

    return $frame;
  }

  function updatePickerLabel(url) {
    var $label = scoped('#tmw-banner-picker-label');
    var $remove = scoped('#tmw-banner-remove');
    var hasUrl = !!url;
    if ($label.length) {
      $label.text(hasUrl ? 'Image selected' : 'No image selected');
    }
    if ($remove.length) {
      $remove.prop('disabled', !hasUrl).toggle(hasUrl);
    }
  }

  function applyPreview(url) {
    var $frame = ensurePreviewFrame();
    if (!$frame.length) {
      updatePickerLabel(url);
      return;
    }

    var $img = $frame.find('img').first();
    if (url) {
      $img.attr('src', url).show();
    } else {
      $img.attr('src', '').hide();
    }

    updatePickerLabel(url);
    applyFocus(scoped('#tmwBannerSlider').val());
  }

  function applyFocus(value) {
    var $frame = ensurePreviewFrame();
    var $img = $frame.find('img').first();
    var focus = sanitizeFocus(value);
    var $slider = scoped('#tmwBannerSlider');
    var $readout = scoped('#tmwBannerValue');

    if ($slider.length) {
      $slider.val(focus);
    }
    if ($readout.length) {
      $readout.text(focus);
    }

    if ($img.length && $img.attr('src')) {
      $img.css('object-position', '50% ' + focus + '%');
    }

    syncMetaToEditor();
  }

  function currentPreviewUrl() {
    return scoped('[id="tmw_banner_image_url"]').val() || '';
  }

  function setBanner(id, url) {
    var $id = scoped('[id="tmw_banner_image_id"]');
    var $url = scoped('[id="tmw_banner_image_url"]');
    if ($id.length) {
      $id.val(id > 0 ? id : 0);
    }
    if ($url.length) {
      $url.val(url || '');
    }
    applyPreview(url || '');
    syncMetaToEditor();
  }

  function openMediaFrame() {
    var media = mediaApi();
    if (!media) { return; }

    var frame = media.media({
      title: 'Select Banner Image',
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

      setBanner(parseInt(att.id, 10) || 0, url);
    });

    frame.open();
  }

  $(function () {
    stripDuplicateBannerUis();

    scoped('#tmw-banner-pick').on('click', function (e) {
      e.preventDefault();
      openMediaFrame();
    });

    scoped('#tmw-banner-remove').on('click', function (e) {
      e.preventDefault();
      setBanner(0, '');
    });

    scoped('#tmwBannerSlider').on('input change', function () {
      applyFocus($(this).val());
    });

    applyPreview(currentPreviewUrl());
    applyFocus(scoped('#tmwBannerSlider').val());
    syncMetaToEditor();
  });
})(jQuery);
