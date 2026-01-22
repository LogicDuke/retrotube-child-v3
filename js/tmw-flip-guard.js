(function () {
  try {
    var isTouch = function () {
      try {
        return matchMedia('(hover: none)').matches || 'ontouchstart' in window;
      } catch (_) {
        return 'ontouchstart' in window;
      }
    };

    if (!isTouch()) {
      return;
    }

    var inBackFace = function (el) {
      return !!(el && el.closest('.tmw-face.back, .tmw-back, .tmw-flip-back'));
    };

    var inCard = function (el) {
      return el ? el.closest('.tmw-flip') : null;
    };

    var onPointer = function (e) {
      var card = inCard(e.target);
      if (!card) {
        return;
      }

      var flipped = card.classList.contains('flipped');
      var targetIsBack = inBackFace(e.target);

      if (flipped || targetIsBack) {
        return;
      }

      var anchor = e.target.closest('a');

      if (anchor) {
        e.preventDefault();
        e.stopPropagation();
      }

      card.classList.add('flipped');
      card.classList.add('tmw-flip-armed');
      setTimeout(function () {
        card.classList.remove('tmw-flip-armed');
      }, 1500);
    };

    document.addEventListener('pointerdown', onPointer, { capture: true, passive: false });
  } catch (_) {}
})();
