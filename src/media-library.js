document.addEventListener("DOMContentLoaded", () => {
  const ajaxUrl = window.cacheHiveMedia?.ajaxUrl;
  const nonce = window.cacheHiveMedia?.nonce;

  if (!ajaxUrl || !nonce) {
    return;
  }

  document.body.addEventListener("click", function (event) {
    const optimizeButton = event.target.closest(".cache-hive-optimize-now");
    const restoreButton = event.target.closest(".cache-hive-restore-image");

    if (optimizeButton) {
      event.preventDefault();
      const format = optimizeButton.dataset.format;
      const container = optimizeButton.closest(".cache-hive-media-actions");
      if (format && container) {
        handleAction("cache_hive_optimize_image", container, format);
      }
    }

    if (restoreButton) {
      event.preventDefault();
      const format = restoreButton.dataset.format;
      const container = restoreButton.closest(".cache-hive-media-actions");
      if (format && container) {
        // **FIX**: Use the original action name.
        handleAction("cache_hive_restore_image", container, format);
      }
    }
  });

  function handleAction(action, container, format) {
    const attachmentId = container.dataset.id;
    if (!attachmentId) return;

    const originalContent = container.innerHTML;
    container.innerHTML = `<p class="in-progress-notice">${window.cacheHiveMedia.l10n.processing}</p>`;

    const formData = new FormData();
    formData.append("action", action);
    formData.append("nonce", nonce);
    formData.append("attachment_id", attachmentId);
    // This sends the format, which the PHP is now expecting.
    formData.append("format", format);

    fetch(ajaxUrl, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((response) => {
        if (response.success && response.data.html) {
          container.innerHTML = response.data.html;
        } else {
          const errorMessage =
            response.data?.message || window.cacheHiveMedia.l10n.error;
          container.innerHTML = `<p class="error-notice">${errorMessage}</p>`;
          // Revert to original state on error so the user can try again.
          setTimeout(() => {
            container.innerHTML = originalContent;
          }, 3000);
        }
      })
      .catch(() => {
        container.innerHTML = `<p class="error-notice">${window.cacheHiveMedia.l10n.error}</p>`;
        setTimeout(() => {
          container.innerHTML = originalContent;
        }, 3000);
      });
  }
});
