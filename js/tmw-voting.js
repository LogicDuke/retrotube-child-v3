/* global tmwVoting */
(() => {
  const config = window.tmwVoting || {};

  const updateCounts = (container, data) => {
    if (!container || !data) {
      return;
    }

    const likesEl = container.querySelector('.likes_count');
    const dislikesEl = container.querySelector('.dislikes_count');
    if (likesEl) {
      likesEl.textContent = String(data.likes);
    }
    if (dislikesEl) {
      dislikesEl.textContent = String(data.dislikes);
    }

    const percentageEl = container.querySelector('.percentage');
    const meterEl = container.querySelector('.rating-bar-meter');
    if (percentageEl) {
      percentageEl.textContent = `${data.percent}%`;
    }
    if (meterEl) {
      meterEl.style.width = `${data.percent}%`;
    }

    const ratingEl = container.querySelector('#rating');
    if (ratingEl) {
      if (data.likes + data.dislikes > 0) {
        ratingEl.classList.remove('not-rated-yet');
      } else {
        ratingEl.classList.add('not-rated-yet');
      }
    }
  };

  const sendVote = (postId, voteType, container) => {
    if (!postId || !voteType || !config.ajaxUrl) {
      return;
    }

    const payload = new URLSearchParams({
      action: 'tmw_vote',
      nonce: config.nonce || '',
      post_id: String(postId),
      vote_type: voteType,
    });

    fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      body: payload.toString(),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data && data.success) {
          updateCounts(container, data.data);
        }
      })
      .catch(() => {
        // Intentionally silent: voting should never block UI.
      });
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.tmw-vote-button');
    if (!button) {
      return;
    }

    event.preventDefault();
    const wrapper = button.closest('.tmw-vote-buttons');
    const container = button.closest('article') || document;
    const postId = (wrapper && wrapper.dataset.postId) || button.dataset.postId || config.postId;
    const voteType = button.dataset.voteType;

    sendVote(postId, voteType, container);
  });
})();
