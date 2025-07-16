"use client";

import * as React from "react";
import { Suspense, useState, useCallback } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { CacheTabForm } from "./caching/CacheTabForm";
import { TtlTabForm } from "./caching/TtlTabForm";
import { AutoPurgeTabForm } from "./caching/AutoPurgeTabForm";
import { ExclusionsTabForm } from "./caching/ExclusionsTabForm";
import { ObjectCacheTabForm } from "./caching/ObjectCacheTabForm";
import { BrowserCacheTabForm } from "./caching/BrowserCacheTabForm";
import * as API from "./api";
import { toast as sonnerToast } from "sonner";
import { ErrorBoundary } from "@/utils/ErrorBoundary";
import { wrapPromise } from "@/utils/wrapPromise";
import { CacheSettingsSkeleton } from "@/components/skeletons/cache-settings-skeleton";
import { TtlSettingsSkeleton } from "@/components/skeletons/ttl-settings-skeleton";
import { AutoPurgeSettingsSkeleton } from "@/components/skeletons/autopurge-settings-skeleton";
import { ExclusionsSettingsSkeleton } from "@/components/skeletons/exclusions-settings-skeleton";
import { ObjectCacheSettingsSkeleton } from "@/components/skeletons/object-cache-settings-skeleton";
import { BrowserCacheSettingsSkeleton } from "@/components/skeletons/browser-cache-settings-skeleton";

function SectionSuspense({
  resource,
  children,
}: {
  resource: any;
  children: (data: any) => React.ReactNode;
}) {
  const data = resource.read();
  return children(data);
}

const fetcherMap: { [key: string]: () => Promise<any> } = {
  cache: API.getCacheSettings,
  ttl: API.getTtlSettings,
  autopurge: API.getAutoPurgeSettings,
  exclusions: API.getExclusionsSettings,
  object: API.getObjectCacheSettings,
  browser: API.getBrowserCacheSettings,
};

const updaterMap: { [key: string]: (data: any) => Promise<any> } = {
  cache: API.updateCacheSettings,
  ttl: API.updateTtlSettings,
  autopurge: API.updateAutoPurgeSettings,
  exclusions: API.updateExclusionsSettings,
  object: API.updateObjectCacheSettings,
  browser: API.updateBrowserCacheSettings,
};

export function Caching() {
  const tabList = [
    { value: "cache", label: "Cache" },
    { value: "ttl", label: "TTL" },
    { value: "autopurge", label: "Autopurge" },
    { value: "exclusions", label: "Exclusions" },
    { value: "object", label: "Object" },
    { value: "browser", label: "Browser" },
  ];
  const [activeTab, setActiveTab] = useState("cache");
  const [saving, setSaving] = useState({
    cache: false,
    ttl: false,
    autopurge: false,
    exclusions: false,
    object: false,
    browser: false,
  });
  const [browserError, setBrowserError] = useState<{
    message: string;
    rules: string;
  } | null>(null);

  const [resources, setResources] = useState<{ [key: string]: any }>(() => {
    const initialResources: { [key: string]: any } = {};
    tabList.forEach((tab) => {
      if (fetcherMap[tab.value]) {
        initialResources[tab.value] = wrapPromise(fetcherMap[tab.value]());
      }
    });
    return initialResources;
  });

  const handleSave = useCallback(
    (section: string) => async (data: any) => {
      setSaving((prev) => ({ ...prev, [section]: true }));
      if (section === "browser") setBrowserError(null);

      const updater = updaterMap[section];
      const savePromise = updater(data)
        .then((newSettings) => {
          setResources((prev) => ({
            ...prev,
            [section]: { read: () => newSettings },
          }));
          return { name: "Settings" };
        })
        .catch((err) => {
          if (section === "browser" && err.currentStatus) {
            setResources((prev) => ({
              ...prev,
              [section]: { read: () => err.currentStatus },
            }));
            setBrowserError({
              message: err.message || "Could not save.",
              rules: err.rules || "",
            });
          }
          throw err;
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
    []
  );

  const skeletonMap: { [key: string]: React.ReactNode } = {
    cache: <CacheSettingsSkeleton />,
    ttl: <TtlSettingsSkeleton />,
    autopurge: <AutoPurgeSettingsSkeleton />,
    exclusions: <ExclusionsSettingsSkeleton />,
    object: <ObjectCacheSettingsSkeleton />,
    browser: <BrowserCacheSettingsSkeleton />,
  };

  const formMap: { [key: string]: (initial: any) => React.ReactNode } = {
    cache: (initial) => (
      <CacheTabForm
        initial={initial}
        onSubmit={handleSave("cache")}
        isSaving={saving.cache}
      />
    ),
    ttl: (initial) => (
      <TtlTabForm
        initial={initial}
        onSubmit={handleSave("ttl")}
        isSaving={saving.ttl}
      />
    ),
    autopurge: (initial) => (
      <AutoPurgeTabForm
        initial={initial}
        onSubmit={handleSave("autopurge")}
        isSaving={saving.autopurge}
      />
    ),
    exclusions: (initial) => (
      <ExclusionsTabForm
        initial={initial}
        onSubmit={handleSave("exclusions")}
        isSaving={saving.exclusions}
      />
    ),
    object: (initial) => (
      <ObjectCacheTabForm
        initial={initial}
        onSubmit={handleSave("object")}
        isSaving={saving.object}
      />
    ),
    browser: (initial) => (
      <BrowserCacheTabForm
        initial={initial.settings}
        onSubmit={handleSave("browser")}
        isSaving={saving.browser}
        status={initial}
        error={browserError}
        manualRules={browserError?.rules}
      />
    ),
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Caching Settings</CardTitle>
      </CardHeader>
      <CardContent>
        <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
          <div className="relative rounded-sm overflow-x-scroll h-10 bg-muted mb-6">
            <TabsList className="absolute flex flex-row justify-stretch w-full pt-1 pl-1 pr-1 pb-0">
              {tabList.map((tab, idx) => (
                <TabsTrigger
                  key={`tabcaching_trigger_${idx}`}
                  className="w-full"
                  value={tab.value}
                >
                  {tab.label.charAt(0).toUpperCase() + tab.label.slice(1)}
                </TabsTrigger>
              ))}
            </TabsList>
          </div>
          {tabList.map((tab) => (
            <TabsContent
              key={tab.value}
              value={tab.value}
              className="mt-6"
              forceMount
              hidden={activeTab !== tab.value}
            >
              <ErrorBoundary
                fallback={
                  <div className="p-4 text-red-600">
                    Error loading settings.
                  </div>
                }
              >
                <Suspense fallback={skeletonMap[tab.value]}>
                  <SectionSuspense resource={resources[tab.value]}>
                    {(initial: any) => formMap[tab.value](initial)}
                  </SectionSuspense>
                </Suspense>
              </ErrorBoundary>
            </TabsContent>
          ))}
        </Tabs>
      </CardContent>
    </Card>
  );
}
