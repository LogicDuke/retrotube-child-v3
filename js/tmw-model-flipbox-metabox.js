(function ($) {
  'use strict';

  function hasMediaLibrary() {
    return typeof wp !== 'undefined' && typeof wp.media !== 'undefined';
  }

  function getPreview(side) {
    return $('#tmw_flip_' + side + '_preview');
  }

  function updatePreviewImage(side, imageUrl) {
    var $preview = getPreview(side);
    var url = imageUrl || '';

    $preview.attr('data-url', url);
    if (url) {
      $preview.css('background-image', 'url(' + url + ')').addClass('is-active');
    } else {
      $preview.css('background-image', 'none').removeClass('is-active');
    }
  }

  function applyPreview(side) {
    var pos = parseInt($('#tmw_flip_pos_' + side).val(), 10);
    var zoom = parseFloat($('#tmw_flip_zoom_' + side).val());
    var $preview = getPreview(side);

    if (isNaN(pos)) {
      pos = 50;
    }

    if (isNaN(zoom)) {
      zoom = 1;
    }

    $preview.css({
      backgroundPosition: pos + '% 50%',
      backgroundSize: (zoom * 100).toFixed(1) + '% auto'
    });
  }

  function updateReadout($input) {
    var readoutSelector = $input.data('readout');
    var unit = $input.data('unit') || '';
    var value = $input.val();

    if ($input.data('control') === 'zoom') {
      value = parseFloat(value).toFixed(1);
    }

    if (typeof readoutSelector === 'string' && readoutSelector.length > 0) {
      $(readoutSelector).text(value + unit);
    }
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
    });

    frame.open();
  }

  $(function () {
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
      updatePreviewImage(side, '');
      applyPreview(side);
    });

    $('#tmw_flip_pos_front, #tmw_flip_zoom_front, #tmw_flip_pos_back, #tmw_flip_zoom_back').on('input change', function () {
      var $input = $(this);
      var side = $input.data('side');

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
    });
  });
})(jQuery);
