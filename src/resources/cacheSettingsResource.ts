import { getSettings } from "@/api";
import { initialSettingsState, AllCacheSettings } from "../caching";
import { wrapPromise } from "../utils/wrapPromise";
import { toast as sonnerToast } from "sonner";

export function normalizeSettingsData(fetchedSettings?: Partial<AllCacheSettings>): AllCacheSettings {
  const merged = { ...initialSettingsState, ...(fetchedSettings || {}) };
  return {
    ...merged,
    publicCacheTTL: String(merged.publicCacheTTL),
    privateCacheTTL: String(merged.privateCacheTTL),
    frontPageTTL: String(merged.frontPageTTL),
    feedTTL: String(merged.feedTTL),
    restTTL: String(merged.restTTL),
    browserCacheTTL: String(merged.browserCacheTTL),
    objectCacheLifetime: String(merged.objectCacheLifetime),
    objectCachePort: String(merged.objectCachePort),
  };
}

export type SettingsResource = { read: () => AllCacheSettings };

export function createSettingsResource(): SettingsResource {
  const promise = getSettings()
    .then(data => normalizeSettingsData(data))
    .catch(error => {
      console.error("Failed to fetch settings for resource:", error);
      sonnerToast({
        title: "Error Loading Settings",
        description: "Could not load settings. Displaying default values.",
        variant: "destructive",
      });
      return normalizeSettingsData(undefined);
    });
  return wrapPromise(promise);
}
