(function ($) {
  'use strict';

  function hasMediaLibrary() {
    return typeof wp !== 'undefined' && typeof wp.media !== 'undefined';
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
  });
})(jQuery);
