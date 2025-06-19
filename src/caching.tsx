"use client";

import * as React from "react";
import { Suspense, useState, useCallback } from "react";
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
import { ObjectCacheTabForm } from "./caching/ObjectCacheTabForm";
import { BrowserCacheTabForm } from "./caching/BrowserCacheTabForm";
import {
  getCacheSettings,
  updateCacheSettings,
  getTtlSettings,
  updateTtlSettings,
  getAutoPurgeSettings,
  updateAutoPurgeSettings,
  getExclusionsSettings,
  updateExclusionsSettings,
  getObjectCacheSettings,
  updateObjectCacheSettings,
  getBrowserCacheSettings,
  updateBrowserCacheSettings,
} from "./api";
import { toast as sonnerToast } from "sonner";
import { ErrorBoundary } from "@/utils/ErrorBoundary";
import { wrapPromise } from "@/utils/wrapPromise";
import { TtlSettingsSkeleton } from "@/components/skeletons/ttl-settings-skeleton";
import { AutoPurgeSettingsSkeleton } from "@/components/skeletons/autopurge-settings-skeleton";
import { ExclusionsSettingsSkeleton } from "@/components/skeletons/exclusions-settings-skeleton";
import { ObjectCacheSettingsSkeleton } from "@/components/skeletons/object-cache-settings-skeleton";
import { BrowserCacheSettingsSkeleton } from "@/components/skeletons/browser-cache-settings-skeleton";

function SectionSuspense({
  resource,
  children,
  fallback,
}: {
  resource: any;
  children: (data: any) => React.ReactNode;
  fallback: React.ReactNode;
}) {
  const data = resource.read();
  return children(data);
}

export function Caching() {
  // Tabs
  const tabList = [
    "cache",
    "ttl",
    "autopurge",
    "exclusions",
    "object",
    "browser",
  ];
  const [activeTab, setActiveTab] = useState("cache");

  // Per-section saving state
  const [saving, setSaving] = useState({
    cache: false,
    ttl: false,
    autopurge: false,
    exclusions: false,
    object: false,
    browser: false,
  });

  // Per-section resources, only fetch on demand
  const [resources, setResources] = useState<{ [key: string]: any }>({});

  // Fetch data for a tab if not already fetched
  const ensureResource = useCallback(
    (tab: string) => {
      if (!resources[tab]) {
        let fetcher: () => Promise<any>;
        switch (tab) {
          case "cache":
            fetcher = getCacheSettings;
            break;
          case "ttl":
            fetcher = getTtlSettings;
            break;
          case "autopurge":
            fetcher = getAutoPurgeSettings;
            break;
          case "exclusions":
            fetcher = getExclusionsSettings;
            break;
          case "object":
            fetcher = getObjectCacheSettings;
            break;
          case "browser":
            fetcher = getBrowserCacheSettings;
            break;
          default:
            return;
        }
        setResources((prev) => ({ ...prev, [tab]: wrapPromise(fetcher()) }));
      }
    },
    [resources]
  );

  // Fetch on tab change
  React.useEffect(() => {
    ensureResource(activeTab);
  }, [activeTab, ensureResource]);

  // Helper to refresh a section after save
  const refreshSection = useCallback((section: string) => {
    let fetcher: () => Promise<any>;
    switch (section) {
      case "cache":
        fetcher = getCacheSettings;
        break;
      case "ttl":
        fetcher = getTtlSettings;
        break;
      case "autopurge":
        fetcher = getAutoPurgeSettings;
        break;
      case "exclusions":
        fetcher = getExclusionsSettings;
        break;
      case "object":
        fetcher = getObjectCacheSettings;
        break;
      case "browser":
        fetcher = getBrowserCacheSettings;
        break;
      default:
        return;
    }
    setResources((prev) => ({ ...prev, [section]: wrapPromise(fetcher()) }));
  }, []);

  // Helper to handle save for each section
  const handleSave = useCallback(
    (section: string, updater: (data: any) => Promise<any>) =>
      async (data: any) => {
        setSaving((prev) => ({ ...prev, [section]: true }));
        const savePromise = updater(data).then(() => {
          refreshSection(section);
          return { name: "Settings" };
        });
        sonnerToast.promise(savePromise, {
          loading: "Saving...",
          success: (data) => `${data.name} saved successfully.`,
          error: (err) => err.message || "Could not save settings.",
        });
        try {
          await savePromise;
        } finally {
          setSaving((prev) => ({ ...prev, [section]: false }));
        }
      },
    [refreshSection]
  );

  return (
    <Card>
      <CardHeader>
        <CardTitle>Caching Settings</CardTitle>
      </CardHeader>
      <CardContent>
        <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
          <TabsList className="grid w-full grid-cols-4 sm:grid-cols-8">
            <TabsTrigger value="cache">Cache</TabsTrigger>
            <TabsTrigger value="ttl">TTL</TabsTrigger>
            <TabsTrigger value="autopurge">Auto Purge</TabsTrigger>
            <TabsTrigger value="exclusions">Exclusions</TabsTrigger>
            <TabsTrigger value="object">Object Cache</TabsTrigger>
            <TabsTrigger value="browser">Browser Cache</TabsTrigger>
          </TabsList>

          {/* Only render the active tab's content and fetch data for it */}
          {tabList.map((tab) => (
            <TabsContent key={tab} value={tab} className="space-y-6 mt-6">
              {activeTab === tab && (
                <ErrorBoundary
                  fallback={
                    <div className="p-4 text-red-600">
                      Error loading {tab} settings.
                    </div>
                  }
                >
                  {resources[tab] ? (
                    <Suspense
                      fallback={
                        tab === "cache" ? (
                          <CacheSettingsSkeleton />
                        ) : tab === "ttl" ? (
                          <TtlSettingsSkeleton />
                        ) : tab === "autopurge" ? (
                          <AutoPurgeSettingsSkeleton />
                        ) : tab === "exclusions" ? (
                          <ExclusionsSettingsSkeleton />
                        ) : tab === "object" ? (
                          <ObjectCacheSettingsSkeleton />
                        ) : (
                          <BrowserCacheSettingsSkeleton />
                        )
                      }
                    >
                      <SectionSuspense
                        resource={resources[tab]}
                        fallback={null}
                      >
                        {(initial: any) =>
                          tab === "cache" ? (
                            <CacheTabForm
                              initial={initial}
                              onSubmit={handleSave(
                                "cache",
                                updateCacheSettings
                              )}
                              isSaving={saving.cache}
                            />
                          ) : tab === "ttl" ? (
                            <TtlTabForm
                              initial={initial}
                              onSubmit={handleSave("ttl", updateTtlSettings)}
                              isSaving={saving.ttl}
                            />
                          ) : tab === "autopurge" ? (
                            <AutoPurgeTabForm
                              initial={initial}
                              onSubmit={handleSave(
                                "autopurge",
                                updateAutoPurgeSettings
                              )}
                              isSaving={saving.autopurge}
                            />
                          ) : tab === "exclusions" ? (
                            <ExclusionsTabForm
                              initial={initial}
                              onSubmit={handleSave(
                                "exclusions",
                                updateExclusionsSettings
                              )}
                              isSaving={saving.exclusions}
                            />
                          ) : tab === "object" ? (
                            <ObjectCacheTabForm
                              initial={initial}
                              onSubmit={handleSave(
                                "object",
                                updateObjectCacheSettings
                              )}
                              isSaving={saving.object}
                            />
                          ) : (
                            <BrowserCacheTabForm
                              initial={initial.settings}
                              onSubmit={handleSave(
                                "browser",
                                updateBrowserCacheSettings
                              )}
                              isSaving={saving.browser}
                              status={initial}
                            />
                          )
                        }
                      </SectionSuspense>
                    </Suspense>
                  ) : tab === "cache" ? (
                    <CacheSettingsSkeleton />
                  ) : tab === "ttl" ? (
                    <TtlSettingsSkeleton />
                  ) : tab === "autopurge" ? (
                    <AutoPurgeSettingsSkeleton />
                  ) : tab === "exclusions" ? (
                    <ExclusionsSettingsSkeleton />
                  ) : tab === "object" ? (
                    <ObjectCacheSettingsSkeleton />
                  ) : (
                    <BrowserCacheSettingsSkeleton />
                  )}
                </ErrorBoundary>
              )}
            </TabsContent>
          ))}
        </Tabs>
      </CardContent>
    </Card>
  );
}