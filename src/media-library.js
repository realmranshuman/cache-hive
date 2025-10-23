/**
 * @file
 * Handles AJAX interactions for the Cache Hive image optimizer in the WordPress admin.
 *
 * This script manages the "Optimize" and "Restore" buttons in the Media Library
 * and on attachment edit screens. It relies on a global `cacheHiveMedia` object,
 * which must be localized by WordPress to provide the AJAX URL, nonce, and
 * localized strings.
 *
 * @since 1.1.0
 */

document.addEventListener("DOMContentLoaded", () => {
  /**
   * Main entry point for the script.
   *
   * Checks for the required global object from WordPress and sets up
   * a single event listener on the document body to handle all relevant clicks.
   */
  const ajaxUrl = window.cacheHiveMedia?.ajaxUrl;
  const nonce = window.cacheHiveMedia?.nonce;
  const l10n = window.cacheHiveMedia?.l10n;

  if (!ajaxUrl || !nonce || !l10n) {
    console.error("Cache Hive: Missing required localization data.");
    return;
  }

  // Use event delegation to handle clicks on buttons now or in the future.
  document.body.addEventListener("click", function (event) {
    const optimizeButton = event.target.closest(".cache-hive-optimize-now");
    const restoreButton = event.target.closest(".cache-hive-restore-image");

    let action = null;
    let button = null;

    if (optimizeButton) {
      action = "cache_hive_optimize_image";
      button = optimizeButton;
    } else if (restoreButton) {
      action = "cache_hive_restore_image";
      button = restoreButton;
    }

    // If a relevant button was clicked, prevent default behavior and handle the action.
    if (action && button) {
      event.preventDefault();
      handleAction(action, button);
    }
  });

  /**
   * Handles the AJAX request for both optimizing and restoring images.
   *
   * @param {string}      action The AJAX action to perform (e.g., 'cache_hive_optimize_image').
   * @param {HTMLElement} button The button element that was clicked.
   */
  function handleAction(action, button) {
    const formatContainer = button.closest(".cache-hive-media-actions");
    const wrapper = button.closest(".cache-hive-optimization-wrapper");

    if (!formatContainer || !wrapper) {
      console.error("Cache Hive: Could not find required data containers.");
      return;
    }

    const format = formatContainer.dataset.format;
    const attachmentId = wrapper.dataset.id;

    if (!format || !attachmentId) {
      console.error("Cache Hive: Missing format or attachment ID.");
      return;
    }

    // Provide immediate user feedback and store original state for potential error recovery.
    const originalContent = formatContainer.innerHTML;
    formatContainer.innerHTML = `<p class="in-progress-notice">${l10n.processing}</p>`;

    const formData = new FormData();
    formData.append("action", action);
    formData.append("nonce", nonce);
    formData.append("attachment_id", attachmentId);
    formData.append("format", format);

    fetch(ajaxUrl, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((response) => {
        if (response.success && response.data.html) {
          // Create a temporary element to safely convert the HTML string into a DOM node.
          const tempDiv = document.createElement("div");
          tempDiv.innerHTML = response.data.html.trim();
          // Replace the old container with the new one to prevent nesting.
          formatContainer.replaceWith(tempDiv.firstChild);
        } else {
          // Handle errors gracefully by displaying a message and reverting the UI.
          const errorMessage = response.data?.message || l10n.error;
          formatContainer.innerHTML = `<p class="error-notice">${errorMessage}</p>`;
          setTimeout(() => {
            formatContainer.innerHTML = originalContent;
          }, 3000);
        }
      })
      .catch((error) => {
        console.error("Cache Hive AJAX Error:", error);
        formatContainer.innerHTML = `<p class="error-notice">${l10n.error}</p>`;
        setTimeout(() => {
          formatContainer.innerHTML = originalContent;
        }, 3000);
      });
  }
});
