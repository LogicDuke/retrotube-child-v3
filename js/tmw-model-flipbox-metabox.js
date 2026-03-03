/**
 * Flipbox metabox JS — media picker only.
 * Save is handled by WordPress classic metabox form POST (save_post hook).
 * No Gutenberg store interaction needed.
 */
(function ($) {
  'use strict';

  function sanitizePos(v)  { var n = parseInt(v, 10);  return isNaN(n) ? 50 : Math.max(0,  Math.min(100, n)); }
  function sanitizeZoom(v) { var n = parseFloat(v);    return isNaN(n) ? 1  : Math.max(1,  Math.min(2.5, n)); }

  function getPreview(side) { return $('#tmw-flipbox-' + side + '-preview'); }

  function applyPreview(side) {
    getPreview(side).css({
      backgroundPosition: sanitizePos($('#tmw_flip_pos_' + side).val()) + '% 50%',
      backgroundSize:     (sanitizeZoom($('#tmw_flip_zoom_' + side).val()) * 100) + '% auto'
    });
  }

  function updatePreviewImage(side, url) {
    getPreview(side)
      .css('background-image', url ? 'url(' + url + ')' : 'none')
      .toggleClass('is-active', !!url);
  }

  function updateReadout($input) {
    var sel  = $input.data('readout');
    var unit = $input.data('unit') || '';
    if (!sel) { return; }
    var v = $input.data('control') === 'zoom'
      ? sanitizeZoom($input.val()).toFixed(1)
      : sanitizePos($input.val());
    $(sel).text(v + unit);
  }

  function openMediaFrame($trigger) {
    if (typeof wp === 'undefined' || !wp.media) { return; }

    var targetId = $trigger.data('target');
    var side     = $trigger.data('side');

    var frame = wp.media({
      title:    'Select Image',
      button:   { text: 'Use image' },
      multiple: false,
      library:  { type: 'image' }
    });

    frame.on('select', function () {
      var att = frame.state().get('selection').first().toJSON();
      if (!att || !att.id) { return; }

      var url = (att.sizes && att.sizes.full && att.sizes.full.url)
        ? att.sizes.full.url
        : (att.url || '');

      // Set the hidden input — this value is submitted with the metabox form POST.
      $('#' + targetId).val(att.id);
      updatePreviewImage(side, url);
      applyPreview(side);
    });

    frame.open();
  }

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
    });

    $('#tmw_flip_pos_front,#tmw_flip_zoom_front,#tmw_flip_pos_back,#tmw_flip_zoom_back')
      .on('input change', function () {
        var $i     = $(this);
        var isZoom = $i.data('control') === 'zoom';
        var v      = isZoom ? sanitizeZoom($i.val()) : sanitizePos($i.val());
        $i.val(isZoom ? v.toFixed(1) : v);
        updateReadout($i);
        applyPreview($i.data('side'));
      });
  });

})(jQuery);
