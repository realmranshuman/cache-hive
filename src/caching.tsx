"use client";

import * as React from "react";
import { useState, useCallback, Suspense, startTransition } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { CacheTabForm, CacheFormData } from "./caching/CacheTabForm";
import { TtlTabForm, TtlFormData } from "./caching/TtlTabForm";
import { CacheSettingsSkeleton } from "@/components/skeletons/cache-settings-skeleton";
import {
  AutoPurgeTabForm,
  AutoPurgeFormData,
} from "./caching/AutoPurgeTabForm";
import {
  ExclusionsTabForm,
  ExclusionsFormData,
} from "./caching/ExclusionsTabForm";
import { getSettings, updateSettings } from "./api";
import { toast as sonnerToast } from "sonner";
import { createSettingsResource, normalizeSettingsData, SettingsResource } from "@/resources/cacheSettingsResource";
import { ErrorBoundary } from "@/utils/ErrorBoundary";
import { wrapPromise } from "@/utils/wrapPromise";

interface ObjectCacheFormData {
  objectCacheEnabled?: boolean;
  objectCacheMethod?: string;
  objectCacheHost?: string;
  objectCachePort?: string;
  objectCacheLifetime?: string;
  objectCacheUsername?: string;
  objectCachePassword?: string;
}
interface BrowserCacheFormData {
  browserCacheEnabled?: boolean;
  browserCacheTTL?: string;
}

export type AllCacheSettings = CacheFormData &
  TtlFormData &
  AutoPurgeFormData &
  ExclusionsFormData &
  ObjectCacheFormData &
  BrowserCacheFormData;

export const initialSettingsState: AllCacheSettings = {
  enableCache: true,
  cacheLoggedUsers: false,
  cacheCommenters: true,
  cacheRestApi: false,
  cacheMobile: true,
  mobileUserAgents: "Mobile\nAndroid\nSilk/\nKindle\nBlackBerry\nOpera Mini\nOpera Mobi",
  publicCacheTTL: "604800",
  privateCacheTTL: "1800",
  frontPageTTL: "604800",
  feedTTL: "604800",
  restTTL: "604800",
  autoPurgeAllPages: true,
  autoPurgeFrontPage: true,
  autoPurgeHomePage: false,
  autoPurgePages: true,
  autoPurgeAuthorArchive: false,
  autoPurgePostTypeArchive: true,
  autoPurgeYearlyArchive: false,
  autoPurgeMonthlyArchive: false,
  autoPurgeDailyArchive: false,
  autoPurgeTermArchive: true,
  purgeOnUpgrade: true,
  serveStale: false,
  excludeUris: "/wp-admin/\n/wp-login.php\n/cart/\n/checkout/",
  excludeQueryStrings: "utm_source\nutm_medium\nutm_campaign\nfbclid",
  excludeCookies: "wordpress_logged_in\nwp-postpass\nwoocommerce_cart_hash",
  excludeRoles: [],
  browserCacheEnabled: true,
  browserCacheTTL: "7",
  objectCacheEnabled: false,
  objectCacheMethod: "memcached",
  objectCacheHost: "localhost",
  objectCachePort: "11211",
  objectCacheLifetime: "3600",
  objectCacheUsername: "",
  objectCachePassword: "",
};

// Create the initial resource once.
const initialResource = createSettingsResource();

// --- CacheSettingsContent Component (the actual UI that consumes the fetched data) ---
interface CacheSettingsContentProps {
  resource: SettingsResource;
  onSettingsUpdate: (updatedData: AllCacheSettings) => void;
}

function CacheSettingsContent({ resource, onSettingsUpdate }: CacheSettingsContentProps) {
  // Read data from the resource. This will suspend if the data isn't ready.
  const initialFetchedSettings = resource.read();

  const [settings, setSettings] = useState<AllCacheSettings>(initialFetchedSettings);
  const [isSaving, setIsSaving] = useState(false);

  // Update local settings if the resource changes (e.g., after a save and refresh).
  React.useEffect(() => {
    setSettings(initialFetchedSettings);
  }, [initialFetchedSettings]);

  const handleSettingsSubmit = useCallback(
    async (data: Partial<AllCacheSettings>) => {
      setIsSaving(true);
      const payload = { ...settings, ...data }; // Overlay changed data onto current settings.

      try {
        const updatedSettingsFromServer = await updateSettings(payload);
        // Normalize the updated settings received from the server.
        const normalizedUpdatedSettings = normalizeSettingsData(updatedSettingsFromServer);
        
        setSettings(normalizedUpdatedSettings); // Update local state
        onSettingsUpdate(normalizedUpdatedSettings); // Notify parent to update the resource

        sonnerToast({
          title: "Success",
          description: "Settings saved successfully.",
        });
      } catch (error: any) {
        console.error("Failed to save settings:", error);
        sonnerToast({
          title: "Error",
          description: error.message || "Could not save settings.",
          variant: "destructive",
        });
      } finally {
        setIsSaving(false);
      }
    },
    [settings, onSettingsUpdate] // Add settings and onSettingsUpdate to dependency array
  );

  // --- Slice settings for each form (uses local `settings` state) ---
  const cacheTabInitial: CacheFormData = {
    enableCache: settings.enableCache,
    cacheLoggedUsers: settings.cacheLoggedUsers,
    cacheCommenters: settings.cacheCommenters,
    cacheRestApi: settings.cacheRestApi,
    cacheMobile: settings.cacheMobile,
    mobileUserAgents: settings.mobileUserAgents,
  };

  const ttlTabInitial: TtlFormData = {
    publicCacheTTL: settings.publicCacheTTL,
    privateCacheTTL: settings.privateCacheTTL,
    frontPageTTL: settings.frontPageTTL,
    feedTTL: settings.feedTTL,
    restTTL: settings.restTTL,
  };

  const autoPurgeTabInitial: AutoPurgeFormData = {
    autoPurgeAllPages: settings.autoPurgeAllPages,
    autoPurgeFrontPage: settings.autoPurgeFrontPage,
    autoPurgeHomePage: settings.autoPurgeHomePage,
    autoPurgePages: settings.autoPurgePages,
    autoPurgeAuthorArchive: settings.autoPurgeAuthorArchive,
    autoPurgePostTypeArchive: settings.autoPurgePostTypeArchive,
    autoPurgeYearlyArchive: settings.autoPurgeYearlyArchive,
    autoPurgeMonthlyArchive: settings.autoPurgeMonthlyArchive,
    autoPurgeDailyArchive: settings.autoPurgeDailyArchive,
    autoPurgeTermArchive: settings.autoPurgeTermArchive,
    purgeOnUpgrade: settings.purgeOnUpgrade,
    serveStale: settings.serveStale,
  };

  const exclusionsTabInitial: ExclusionsFormData = {
    excludeUris: settings.excludeUris,
    excludeQueryStrings: settings.excludeQueryStrings,
    excludeCookies: settings.excludeCookies,
    excludeRoles: settings.excludeRoles,
  };
    // Placeholder initial data for forms not yet fully implemented
  const objectCacheTabInitial: ObjectCacheFormData = {
    objectCacheEnabled: settings.objectCacheEnabled,
    objectCacheMethod: settings.objectCacheMethod,
    objectCacheHost: settings.objectCacheHost,
    objectCachePort: settings.objectCachePort,
    objectCacheLifetime: settings.objectCacheLifetime,
    objectCacheUsername: settings.objectCacheUsername,
    objectCachePassword: settings.objectCachePassword,
  };

  const browserCacheTabInitial: BrowserCacheFormData = {
    browserCacheEnabled: settings.browserCacheEnabled,
    browserCacheTTL: settings.browserCacheTTL, // Already a string from normalization
  };

  return (
      <Card>
        <CardHeader>
          <CardTitle>Caching Settings</CardTitle>
        </CardHeader>
        <CardContent>
          <Tabs defaultValue="cache" className="w-full">
            <TabsList className="grid w-full grid-cols-4 sm:grid-cols-6">
              <TabsTrigger value="cache">Cache</TabsTrigger>
              <TabsTrigger value="ttl">TTL</TabsTrigger>
              <TabsTrigger value="autopurge">Auto Purge</TabsTrigger>
              <TabsTrigger value="exclusions">Exclusions</TabsTrigger>
              {/* <TabsTrigger value="object">Object Cache</TabsTrigger> */}
              {/* <TabsTrigger value="browser">Browser Cache</TabsTrigger> */}
            </TabsList>

            <TabsContent value="cache" className="space-y-6 mt-6">
              <CacheTabForm
                initial={cacheTabInitial}
                onSubmit={handleSettingsSubmit}
                isSaving={isSaving}
              />
            </TabsContent>

            <TabsContent value="ttl" className="space-y-6 mt-6">
              <TtlTabForm
                initial={ttlTabInitial}
                onSubmit={handleSettingsSubmit}
                isSaving={isSaving}
              />
            </TabsContent>

            <TabsContent value="autopurge" className="space-y-6 mt-6">
              <AutoPurgeTabForm
                initial={autoPurgeTabInitial}
                onSubmit={handleSettingsSubmit}
                isSaving={isSaving}
              />
            </TabsContent>

            <TabsContent value="exclusions" className="space-y-6 mt-6">
              <ExclusionsTabForm
                initial={exclusionsTabInitial}
                onSubmit={handleSettingsSubmit}
                isSaving={isSaving}
              />
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
  );
}

// --- Main Caching Component ---
export function Caching() {
  // The settingsResource state holds the current resource. Updating it can trigger a re-fetch/re-render.
  const [settingsResource, setSettingsResource] = useState<SettingsResource>(initialResource);

  // Callback to refresh the resource after settings are successfully saved.
  const handleResourceRefresh = useCallback((updatedData: AllCacheSettings) => {
    // Create a new resource that resolves immediately with the already normalized updated data.
    // This assumes updateSettings returns the full updated object.
    const newResource = wrapPromise(Promise.resolve(updatedData));
    
    // Use startTransition if this update can be slightly deprioritized for rendering.
    startTransition(() => {
      setSettingsResource(newResource);
    });
  }, []);

  return (
    <ErrorBoundary fallback={<div className="p-4 text-red-600">Error: Could not load settings. Please try refreshing the page.</div>}>
      <Suspense fallback={<CacheSettingsSkeleton />}>
        <CacheSettingsContent resource={settingsResource} onSettingsUpdate={handleResourceRefresh} />
      </Suspense>
    </ErrorBoundary>
  );
}