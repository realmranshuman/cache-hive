"use client";

import * as React from "react";
import { Suspense, useState, useCallback } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { CssSettingsForm } from "./pageOptimization/CssSettingsForm";
import { JsSettingsForm } from "./pageOptimization/JsSettingsForm";
import { HtmlSettingsForm } from "./pageOptimization/HtmlSettingsForm";
import { MediaSettingsForm } from "./pageOptimization/MediaSettingsForm";
import {
  getCssSettings,
  updateCssSettings,
  getJsSettings,
  updateJsSettings,
  getHtmlSettings,
  updateHtmlSettings,
  getMediaSettings,
  updateMediaSettings,
} from "./api";
import { toast as sonnerToast } from "sonner";
import { ErrorBoundary } from "@/utils/ErrorBoundary";
import { wrapPromise } from "@/utils/wrapPromise";
import { CssSettingsSkeleton } from "@/components/skeletons/css-settings-skeleton";
import { JsSettingsSkeleton } from "@/components/skeletons/js-settings-skeleton";
import { HtmlSettingsSkeleton } from "@/components/skeletons/html-settings-skeleton";
import { MediaSettingsSkeleton } from "@/components/skeletons/media-settings-skeleton";

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

export function PageOptimization() {
  const tabList = ["css", "js", "html", "media"];
  const [activeTab, setActiveTab] = useState("css");
  const [saving, setSaving] = useState({
    css: false,
    js: false,
    html: false,
    media: false,
  });
  const [resources, setResources] = useState<{ [key: string]: any }>({});

  const ensureResource = useCallback(
    (tab: string) => {
      if (resources[tab]) return;
      const fetcherMap: { [key: string]: () => Promise<any> } = {
        css: getCssSettings,
        js: getJsSettings,
        html: getHtmlSettings,
        media: getMediaSettings,
      };
      if (fetcherMap[tab]) {
        setResources((prev) => ({
          ...prev,
          [tab]: wrapPromise(fetcherMap[tab]()),
        }));
      }
    },
    [resources]
  );

  React.useEffect(() => {
    ensureResource(activeTab);
  }, [activeTab, ensureResource]);

  const handleSave = useCallback(
    (section: string, updater: (data: any) => Promise<any>) =>
      async (data: any) => {
        setSaving((prev) => ({ ...prev, [section]: true }));

        const savePromise = updater(data)
          .then((newSettings) => {
            setResources((prev) => ({
              ...prev,
              [section]: wrapPromise(Promise.resolve(newSettings)),
            }));
            return { name: `${section.toUpperCase()} Settings` };
          })
          .catch((err) => {
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
    css: <CssSettingsSkeleton />,
    js: <JsSettingsSkeleton />,
    html: <HtmlSettingsSkeleton />,
    media: <MediaSettingsSkeleton />,
  };

  const formMap: { [key: string]: (initial: any) => React.ReactNode } = {
    css: (initial) => (
      <CssSettingsForm
        initial={initial}
        onSubmit={handleSave("css", updateCssSettings)}
        isSaving={saving.css}
      />
    ),
    js: (initial) => (
      <JsSettingsForm
        initial={initial}
        onSubmit={handleSave("js", updateJsSettings)}
        isSaving={saving.js}
      />
    ),
    html: (initial) => (
      <HtmlSettingsForm
        initial={initial}
        onSubmit={handleSave("html", updateHtmlSettings)}
        isSaving={saving.html}
      />
    ),
    media: (initial) => (
      <MediaSettingsForm
        initial={initial}
        onSubmit={handleSave("media", updateMediaSettings)}
        isSaving={saving.media}
      />
    ),
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Page Optimization</CardTitle>
      </CardHeader>
      <CardContent>
        <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
          <TabsList className="grid w-full grid-cols-4">
            <TabsTrigger value="css">CSS</TabsTrigger>
            <TabsTrigger value="js">JS</TabsTrigger>
            <TabsTrigger value="html">HTML</TabsTrigger>
            <TabsTrigger value="media">Media</TabsTrigger>
          </TabsList>
          {tabList.map((tab) => (
            <TabsContent key={tab} value={tab} className="mt-6">
              {/*
                THE FIX: By removing the '{activeTab === tab && ...}' check,
                React will keep all TabContent components mounted. The Tabs component
                will handle showing/hiding them with CSS, preserving the form state.
              */}
              <ErrorBoundary
                fallback={
                  <div className="p-4 text-red-600">
                    Error loading {tab} settings.
                  </div>
                }
              >
                <Suspense fallback={skeletonMap[tab]}>
                  {resources[tab] && (
                    <SectionSuspense resource={resources[tab]}>
                      {(initial: any) => formMap[tab](initial)}
                    </SectionSuspense>
                  )}
                </Suspense>
              </ErrorBoundary>
            </TabsContent>
          ))}
        </Tabs>
      </CardContent>
    </Card>
  );
}
