(function ($) {
  'use strict';

  function hasMediaLibrary() {
    return typeof wp !== 'undefined' && typeof wp.media !== 'undefined';
  }

  function applyPreview(side) {
    var pos = parseInt($('#tmw_flip_pos_' + side).val(), 10);
    var zoom = parseFloat($('#tmw_flip_zoom_' + side).val());
    var $image = $('.tmw-flipbox-preview-' + side + ' img');

    if (isNaN(pos)) {
      pos = 50;
    }

    if (isNaN(zoom)) {
      zoom = 1;
    }

    $image.css({
      transform: 'scale(' + zoom.toFixed(1) + ')',
      transformOrigin: 'center center',
      objectFit: 'cover',
      objectPosition: pos + '% 50%'
    });
  }

  function updateReadout($input) {
    var readoutSelector = $input.data('readout');
    var unit = $input.data('unit') || '';
    var value = $input.val();

    if (typeof readoutSelector === 'string' && readoutSelector.length > 0) {
      $(readoutSelector).text(value + unit);
    }
  }

  function openMediaFrame($trigger) {
    if (!hasMediaLibrary()) {
      return;
    }

    var targetId = $trigger.data('target');
    var previewId = $trigger.data('preview');
    var $target = $('#' + targetId);
    var $preview = $('#' + previewId);

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

      $target.val(attachment.id);
      if (attachment.url) {
        $preview.attr('src', attachment.url).show();
      }
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
      var previewId = $trigger.data('preview');

      $('#' + targetId).val('');
      $('#' + previewId).attr('src', '').hide();
    });

    $('#tmw_flip_pos_front, #tmw_flip_zoom_front, #tmw_flip_pos_back, #tmw_flip_zoom_back').on('input change', function () {
      var $input = $(this);
      updateReadout($input);

      if (this.id.indexOf('_front') !== -1) {
        applyPreview('front');
      }

      if (this.id.indexOf('_back') !== -1) {
        applyPreview('back');
      }
    });

    $('#tmw_flip_pos_front, #tmw_flip_zoom_front, #tmw_flip_pos_back, #tmw_flip_zoom_back').each(function () {
      updateReadout($(this));
    });

    applyPreview('front');
    applyPreview('back');
  });
})(jQuery);
