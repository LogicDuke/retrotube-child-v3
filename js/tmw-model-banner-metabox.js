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

  function getBannerId() {
    return parseInt($('#tmw_banner_image_id').val(), 10) || 0;
  }

  function currentMeta() {
    return {
      tmw_banner_image_id: getBannerId(),
      banner_image: getBannerId(),
      _banner_focal_y: sanitizeFocus($('#tmwBannerSlider').val())
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

  function ensurePreviewFrame() {
    var $wrap = $('#tmw-banner-preview');
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
    var $label = $('#tmw-banner-picker-label');
    var hasUrl = !!url;
    $label.text(hasUrl ? 'Image selected' : 'No image selected');
    $('#tmw-banner-remove').prop('disabled', !hasUrl).toggle(hasUrl);
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
    applyFocus($('#tmwBannerSlider').val());
  }

  function applyFocus(value) {
    var $frame = ensurePreviewFrame();
    var $img = $frame.find('img').first();
    var focus = sanitizeFocus(value);

    $('#tmwBannerSlider').val(focus);
    $('#tmwBannerValue').text(focus);

    if ($img.length && $img.attr('src')) {
      $img.css('object-position', '50% ' + focus + '%');
    }

    syncMetaToEditor();
  }

  function currentPreviewUrl() {
    return $('#tmw_banner_image_url').val() || '';
  }

  function setBanner(id, url) {
    $('#tmw_banner_image_id').val(id > 0 ? id : 0);
    $('#tmw_banner_image_url').val(url || '');
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
    $('#tmw-banner-pick').on('click', function (e) {
      e.preventDefault();
      openMediaFrame();
    });

    $('#tmw-banner-remove').on('click', function (e) {
      e.preventDefault();
      setBanner(0, '');
    });

    $('#tmwBannerSlider').on('input change', function () {
      applyFocus($(this).val());
    });

    applyPreview(currentPreviewUrl());
    applyFocus($('#tmwBannerSlider').val());
    syncMetaToEditor();
  });
})(jQuery);
