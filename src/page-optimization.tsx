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

const fetcherMap: { [key: string]: () => Promise<any> } = {
  css: getCssSettings,
  js: getJsSettings,
  html: getHtmlSettings,
  media: getMediaSettings,
};

const updaterMap: { [key: string]: (data: any) => Promise<any> } = {
  css: updateCssSettings,
  js: updateJsSettings,
  html: updateHtmlSettings,
  media: updateMediaSettings,
};

export function PageOptimization() {
  const tabList = [
    { value: "css", label: "CSS" },
    { value: "js", label: "JS" },
    { value: "html", label: "HTML" },
    { value: "media", label: "Media" },
  ];
  const [activeTab, setActiveTab] = useState("css");
  const [saving, setSaving] = useState({
    css: false,
    js: false,
    html: false,
    media: false,
  });

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
      const updater = updaterMap[section];

      const savePromise = updater(data)
        .then((newSettings) => {
          setResources((prev) => ({
            ...prev,
            [section]: { read: () => newSettings },
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
        onSubmit={handleSave("css")}
        isSaving={saving.css}
      />
    ),
    js: (initial) => (
      <JsSettingsForm
        initial={initial}
        onSubmit={handleSave("js")}
        isSaving={saving.js}
      />
    ),
    html: (initial) => (
      <HtmlSettingsForm
        initial={initial}
        onSubmit={handleSave("html")}
        isSaving={saving.html}
      />
    ),
    media: (initial) => (
      <MediaSettingsForm
        initial={initial}
        onSubmit={handleSave("media")}
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
          <div className="relative rounded-sm overflow-x-scroll h-10 bg-muted mb-6">
            <TabsList className="absolute flex flex-row justify-stretch w-full pt-1 pl-1 pr-1 pb-0">
              {tabList.map((tab, idx) => (
                <TabsTrigger
                  className="w-full"
                  key={`tabopt_trigger_${idx}`}
                  value={tab.value}
                >
                  {tab.label}
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
                    Error loading {tab.label} settings.
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
