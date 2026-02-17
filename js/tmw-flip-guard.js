(function () {
  try {
    var isTouchOnly = function () {
      try {
        return window.matchMedia('(hover: none) and (pointer: coarse)').matches;
      } catch (_) {
        return false;
      }
    };

    if (!isTouchOnly()) return;

    var cardSelector = '.tmw-flip';
    var containerSelector = '.tmw-grid, .tmwfm-grid';

    var closeAllExcept = function (activeCard) {
      document.querySelectorAll(cardSelector + '.flipped').forEach(function (card) {
        if (card !== activeCard) card.classList.remove('flipped');
      });
    };

    document.addEventListener(
      'click',
      function (e) {
        var inFlipContext = e.target.closest(containerSelector);
        var tappedCard = e.target.closest(cardSelector);
        var cta = e.target.closest('.tmw-view');

        // Tap outside any flip context -> close all
        if (!inFlipContext) {
          closeAllExcept(null);
          return;
        }

        // Tap inside context but not on a card -> close all
        if (!tappedCard) {
          closeAllExcept(null);
          return;
        }

        var isFlipped = tappedCard.classList.contains('flipped');

        // If user taps CTA while already flipped -> allow navigation
        if (cta && isFlipped) {
          closeAllExcept(tappedCard);
          return;
        }

        // Otherwise: prevent navigation (e.g., wrapper link) and toggle flip
        e.preventDefault();
        closeAllExcept(tappedCard);
        tappedCard.classList.toggle('flipped');
      },
      true
    );
  } catch (_) {}
})();
