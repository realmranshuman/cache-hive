document.addEventListener("DOMContentLoaded", () => {
  const ajaxUrl = window.cacheHiveMedia?.ajaxUrl;
  const nonce = window.cacheHiveMedia?.nonce;

  if (!ajaxUrl || !nonce) {
    return;
  }

  // Use event delegation to handle clicks, as elements can be added dynamically.
  document.body.addEventListener("click", function (event) {
    const target = event.target;

    if (target.matches(".cache-hive-optimize-now")) {
      event.preventDefault();
      const actionsContainer = target.closest(".cache-hive-media-actions");
      if (actionsContainer) {
        handleAction("cache_hive_optimize_image", actionsContainer);
      }
    }

    if (target.matches(".cache-hive-restore-image")) {
      event.preventDefault();
      const actionsContainer = target.closest(".cache-hive-media-actions");
      if (actionsContainer) {
        handleAction("cache_hive_restore_image", actionsContainer);
      }
    }
  });

  function handleAction(action, container) {
    const attachmentId = container.dataset.id;
    if (!attachmentId) return;

    // Show loading state
    container.innerHTML = `<p class="in-progress-notice">${window.cacheHiveMedia.l10n.processing}</p>`;

    const formData = new FormData();
    formData.append("action", action);
    formData.append("nonce", nonce);
    formData.append("attachment_id", attachmentId);

    fetch(ajaxUrl, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((response) => {
        if (response.success && response.data.html) {
          container.innerHTML = response.data.html;
        } else {
          container.innerHTML = `<p class="error-notice">${
            response.data.message || window.cacheHiveMedia.l10n.error
          }</p>`;
        }
      })
      .catch(() => {
        container.innerHTML = `<p class="error-notice">${window.cacheHiveMedia.l10n.error}</p>`;
      });
  }
});
