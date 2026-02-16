(function () {
  try {
    var hasTouchPointer = function () {
      try {
        return window.matchMedia('(hover: none), (pointer: coarse)').matches || 'ontouchstart' in window;
      } catch (_) {
        return 'ontouchstart' in window;
      }
    };

    if (!hasTouchPointer()) {
      return;
    }

    var cardSelector = '.tmw-flip';
    var containerSelector = '.tmw-grid, .tmwfm-grid';

    var closeAllExcept = function (activeCard) {
      var cards = document.querySelectorAll(cardSelector + '.flipped');
      cards.forEach(function (card) {
        if (card !== activeCard) {
          card.classList.remove('flipped');
        }
      });
    };

    document.addEventListener('pointerdown', function (e) {
      var cta = e.target.closest('.tmw-view');
      var tappedCard = e.target.closest(cardSelector);
      var inFlipContext = e.target.closest(containerSelector);

      if (!tappedCard || !inFlipContext) {
        closeAllExcept(null);
        return;
      }

      var isFlipped = tappedCard.classList.contains('flipped');

      if (cta && isFlipped) {
        closeAllExcept(tappedCard);
        return;
      }

      e.preventDefault();

      closeAllExcept(tappedCard);

      if (isFlipped) {
        tappedCard.classList.remove('flipped');
      } else {
        tappedCard.classList.add('flipped');
      }
    }, { capture: true, passive: false });
  } catch (_) {}
})();
