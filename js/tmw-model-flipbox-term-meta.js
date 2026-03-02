(function ($) {
  'use strict';

  function ensureMedia() {
    return typeof wp !== 'undefined' && wp.media;
  }

  function bindPicker(button) {
    var $button = $(button);
    $button.on('click', function (e) {
      e.preventDefault();
      if (!ensureMedia()) {
        return;
      }

      var targetId = $button.data('target');
      var previewId = $button.data('preview');
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
    });
  }

  function bindRemove(button) {
    var $button = $(button);
    $button.on('click', function (e) {
      e.preventDefault();
      var targetId = $button.data('target');
      var previewId = $button.data('preview');

      $('#' + targetId).val('');
      $('#' + previewId).attr('src', '').hide();
    });
  }

  $(function () {
    $('.tmw-flipbox-pick').each(function () {
      bindPicker(this);
    });

    $('.tmw-flipbox-remove').each(function () {
      bindRemove(this);
    });
  });
})(jQuery);
