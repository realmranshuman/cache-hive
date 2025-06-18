"use client";

import * as React from "react";
import { Suspense, useState, useCallback, startTransition } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { CacheTabForm, CacheFormData } from "./caching/CacheTabForm";
import { TtlTabForm, TtlFormData } from "./caching/TtlTabForm";
import { CacheSettingsSkeleton } from "@/components/skeletons/cache-settings-skeleton";
import { AutoPurgeTabForm, AutoPurgeFormData } from "./caching/AutoPurgeTabForm";
import { ExclusionsTabForm, ExclusionsFormData } from "./caching/ExclusionsTabForm";
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

function SectionSuspense({ resource, children, fallback }: { resource: any; children: (data: any) => React.ReactNode; fallback: React.ReactNode }) {
  const data = resource.read();
  return children(data);
}

export function Caching() {
  // Per-section saving state
  const [saving, setSaving] = useState({
    cache: false,
    ttl: false,
    autopurge: false,
    exclusions: false,
    object: false,
    browser: false,
  });

  // Per-section resources
  const [resources, setResources] = useState({
    cache: wrapPromise(getCacheSettings()),
    ttl: wrapPromise(getTtlSettings()),
    autopurge: wrapPromise(getAutoPurgeSettings()),
    exclusions: wrapPromise(getExclusionsSettings()),
    object: wrapPromise(getObjectCacheSettings()),
    browser: wrapPromise(getBrowserCacheSettings()),
  });

  // Helper to refresh a section after save
  const refreshSection = useCallback((section: keyof typeof resources) => {
    setResources((prev) => ({
      ...prev,
      [section]: wrapPromise(
        section === "cache" ? getCacheSettings() :
        section === "ttl" ? getTtlSettings() :
        section === "autopurge" ? getAutoPurgeSettings() :
        section === "exclusions" ? getExclusionsSettings() :
        section === "object" ? getObjectCacheSettings() :
        getBrowserCacheSettings()
      ),
    }));
  }, []);

  // Helper to handle save for each section
  const handleSave = useCallback((section: keyof typeof resources, updater: (data: any) => Promise<any>) => async (data: any) => {
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
  }, [refreshSection]);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Caching Settings</CardTitle>
      </CardHeader>
      <CardContent>
        <Tabs defaultValue="cache" className="w-full">
          <TabsList className="grid w-full grid-cols-4 sm:grid-cols-8">
            <TabsTrigger value="cache">Cache</TabsTrigger>
            <TabsTrigger value="ttl">TTL</TabsTrigger>
            <TabsTrigger value="autopurge">Auto Purge</TabsTrigger>
            <TabsTrigger value="exclusions">Exclusions</TabsTrigger>
            <TabsTrigger value="object">Object Cache</TabsTrigger>
            <TabsTrigger value="browser">Browser Cache</TabsTrigger>
          </TabsList>

          <TabsContent value="cache" className="space-y-6 mt-6">
            <ErrorBoundary fallback={<div className="p-4 text-red-600">Error loading cache settings.</div>}>
              <Suspense fallback={<CacheSettingsSkeleton />}>
                <SectionSuspense resource={resources.cache} fallback={<CacheSettingsSkeleton />}>
                  {(initial: CacheFormData) => (
                    <CacheTabForm
                      initial={initial}
                      onSubmit={handleSave("cache", updateCacheSettings)}
                      isSaving={saving.cache}
                    />
                  )}
                </SectionSuspense>
              </Suspense>
            </ErrorBoundary>
          </TabsContent>

          <TabsContent value="ttl" className="space-y-6 mt-6">
            <ErrorBoundary fallback={<div className="p-4 text-red-600">Error loading TTL settings.</div>}>
              <Suspense fallback={<TtlSettingsSkeleton />}>
                <SectionSuspense resource={resources.ttl} fallback={<TtlSettingsSkeleton />}>
                  {(initial: TtlFormData) => (
                    <TtlTabForm
                      initial={initial}
                      onSubmit={handleSave("ttl", updateTtlSettings)}
                      isSaving={saving.ttl}
                    />
                  )}
                </SectionSuspense>
              </Suspense>
            </ErrorBoundary>
          </TabsContent>

          <TabsContent value="autopurge" className="space-y-6 mt-6">
            <ErrorBoundary fallback={<div className="p-4 text-red-600">Error loading auto purge settings.</div>}>
              <Suspense fallback={<AutoPurgeSettingsSkeleton />}>
                <SectionSuspense resource={resources.autopurge} fallback={<AutoPurgeSettingsSkeleton />}>
                  {(initial: AutoPurgeFormData) => (
                    <AutoPurgeTabForm
                      initial={initial}
                      onSubmit={handleSave("autopurge", updateAutoPurgeSettings)}
                      isSaving={saving.autopurge}
                    />
                  )}
                </SectionSuspense>
              </Suspense>
            </ErrorBoundary>
          </TabsContent>

          <TabsContent value="exclusions" className="space-y-6 mt-6">
            <ErrorBoundary fallback={<div className="p-4 text-red-600">Error loading exclusions settings.</div>}>
              <Suspense fallback={<ExclusionsSettingsSkeleton />}>
                <SectionSuspense resource={resources.exclusions} fallback={<ExclusionsSettingsSkeleton />}>
                  {(initial: ExclusionsFormData) => (
                    <ExclusionsTabForm
                      initial={initial}
                      onSubmit={handleSave("exclusions", updateExclusionsSettings)}
                      isSaving={saving.exclusions}
                    />
                  )}
                </SectionSuspense>
              </Suspense>
            </ErrorBoundary>
          </TabsContent>

          <TabsContent value="object" className="space-y-6 mt-6">
            <ErrorBoundary fallback={<div className="p-4 text-red-600">Error loading object cache settings.</div>}>
              <Suspense fallback={<ObjectCacheSettingsSkeleton />}>
                <SectionSuspense resource={resources.object} fallback={<ObjectCacheSettingsSkeleton />}>
                  {(initial: any) => (
                    <ObjectCacheTabForm
                      initial={initial}
                      onSubmit={handleSave("object", updateObjectCacheSettings)}
                      isSaving={saving.object}
                    />
                  )}
                </SectionSuspense>
              </Suspense>
            </ErrorBoundary>
          </TabsContent>

          <TabsContent value="browser" className="space-y-6 mt-6">
            <ErrorBoundary fallback={<div className="p-4 text-red-600">Error loading browser cache settings.</div>}>
              <Suspense fallback={<BrowserCacheSettingsSkeleton />}>
                <SectionSuspense resource={resources.browser} fallback={<BrowserCacheSettingsSkeleton />}>
                  {(initial: any) => (
                    <BrowserCacheTabForm
                      initial={initial}
                      onSubmit={handleSave("browser", updateBrowserCacheSettings)}
                      isSaving={saving.browser}
                    />
                  )}
                </SectionSuspense>
              </Suspense>
            </ErrorBoundary>
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  );
}