import * as React from "react";
import { createRoot } from "react-dom/client";
import { Toaster, toast } from "sonner";

import {
  purgeAll,
  purgeDiskCache,
  purgeObjectCache,
  purgeCloudflareCache,
  purgeThisPage,
  purgeMyPrivateCache,
} from "@/api/toolbar";

interface Action {
  apiFn: () => Promise<void>;
  loadingMessage: string;
  successMessage: string;
}

const ToolbarController = () => {
  const handleAction = React.useCallback(
    async ({ apiFn, loadingMessage, successMessage }: Action) => {
      const promise = apiFn();
      toast.promise(promise, {
        loading: loadingMessage,
        success: () => successMessage,
        error: (err: any) => err.message || "An unknown error occurred.",
      });
    },
    []
  );

  React.useEffect(() => {
    const listenersMap: { [key: string]: () => void } = {
      "#wp-admin-bar-cache-hive-private-cache": () =>
        handleAction({
          apiFn: purgeMyPrivateCache,
          loadingMessage: "Purging your private cache...",
          successMessage: "Your private cache has been purged.",
        }),
      "#wp-admin-bar-cache-hive-purge-page": () =>
        handleAction({
          apiFn: purgeThisPage,
          loadingMessage: "Purging page cache...",
          successMessage: "Cache for this page has been purged.",
        }),
      "#wp-admin-bar-cache-hive-purge-disk": () =>
        handleAction({
          apiFn: purgeDiskCache,
          loadingMessage: "Purging disk cache...",
          successMessage: "Disk cache has been purged.",
        }),
      "#wp-admin-bar-cache-hive-purge-object-cache": () =>
        handleAction({
          apiFn: purgeObjectCache,
          loadingMessage: "Purging object cache...",
          successMessage: "Object cache has been purged.",
        }),
      "#wp-admin-bar-cache-hive-purge-cloudflare": () =>
        handleAction({
          apiFn: purgeCloudflareCache,
          loadingMessage: "Purging Cloudflare cache...",
          successMessage: "Cloudflare cache purge has been initiated.",
        }),
      "#wp-admin-bar-cache-hive-purge-all": () =>
        handleAction({
          apiFn: purgeAll,
          loadingMessage: "Purging all caches...",
          successMessage: "All caches have been purged.",
        }),
    };

    const attachListeners = () => {
      let allAttached = true;
      Object.keys(listenersMap).forEach((selector) => {
        const element = document.querySelector(selector);
        if (element) {
          if (!(element as any)._chListenerAttached) {
            allAttached = false;
            element.addEventListener(
              "click",
              listenersMap[selector] as EventListener
            );
            (element as any)._chListenerAttached = true;
          }
        } else {
          allAttached = false;
        }
      });
      return allAttached;
    };

    const intervalId = setInterval(() => {
      if (attachListeners()) {
        clearInterval(intervalId);
      }
    }, 250);

    const timeoutId = setTimeout(() => {
      clearInterval(intervalId);
    }, 10000);

    return () => {
      clearInterval(intervalId);
      clearTimeout(timeoutId);
    };
  }, [handleAction]);

  return <Toaster position="bottom-center" richColors closeButton />;
};

// --- React 18 Bootstrap ---
let container = document.getElementById("cache-hive-toolbar-root");
if (!container) {
  container = document.createElement("div");
  container.id = "cache-hive-toolbar-root";
  document.body.appendChild(container);
}
const root = createRoot(container);
root.render(<ToolbarController />);
