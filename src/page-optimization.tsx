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
      if (!resources[tab]) {
        let fetcher: () => Promise<any>;
        switch (tab) {
          case "css":
            fetcher = getCssSettings;
            break;
          case "js":
            fetcher = getJsSettings;
            break;
          case "html":
            fetcher = getHtmlSettings;
            break;
          case "media":
            fetcher = getMediaSettings;
            break;
          default:
            return;
        }
        setResources((prev) => ({ ...prev, [tab]: wrapPromise(fetcher()) }));
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
    [setResources]
  );

  // Helper to get the correct skeleton component
  const getSkeleton = (tab: string) => {
    const skeletons: { [key: string]: React.ReactNode } = {
      css: <CssSettingsSkeleton />,
      js: <JsSettingsSkeleton />,
      html: <HtmlSettingsSkeleton />,
      media: <MediaSettingsSkeleton />,
    };
    return skeletons[tab] || <CssSettingsSkeleton />;
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
                    <Suspense fallback={getSkeleton(tab)}>
                      <SectionSuspense resource={resources[tab]}>
                        {(initial: any) => {
                          switch (tab) {
                            case "css":
                              return (
                                <CssSettingsForm
                                  initial={initial}
                                  onSubmit={handleSave("css", updateCssSettings)}
                                  isSaving={saving.css}
                                />
                              );
                            case "js":
                              return (
                                <JsSettingsForm
                                  initial={initial}
                                  onSubmit={handleSave("js", updateJsSettings)}
                                  isSaving={saving.js}
                                />
                              );
                            case "html":
                              return (
                                <HtmlSettingsForm
                                  initial={initial}
                                  onSubmit={handleSave(
                                    "html",
                                    updateHtmlSettings
                                  )}
                                  isSaving={saving.html}
                                />
                              );
                            case "media":
                              return (
                                <MediaSettingsForm
                                  initial={initial}
                                  onSubmit={handleSave(
                                    "media",
                                    updateMediaSettings
                                  )}
                                  isSaving={saving.media}
                                />
                              );
                            default:
                              return null;
                          }
                        }}
                      </SectionSuspense>
                    </Suspense>
                  ) : (
                    getSkeleton(tab)
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
