"use client";

import * as React from "@wordpress/element";
import { Suspense, useState, useCallback } from "@wordpress/element";
import type { ReactNode } from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Cloud, Info, Trash2 } from "lucide-react";
import {
  getCloudflareSettings,
  updateCloudflareSettings,
  purgeCloudflareCache,
  CloudflareSettings,
} from "./api/cloudflare";
import { toast as sonnerToast } from "sonner";
import { wrapPromise } from "@/utils/wrapPromise";
import { ErrorBoundary } from "@/utils/ErrorBoundary";
import { NetworkAlert } from "@/components/ui/network-alert";
import { CloudflareSettingsSkeleton } from "@/components/skeletons/cloudflare-settings-skeleton";

function createResource() {
  return wrapPromise(getCloudflareSettings());
}

const initialResource = createResource();

function SectionSuspense({
  resource,
  children,
}: {
  resource: { read: () => CloudflareSettings };
  children: (data: CloudflareSettings) => ReactNode;
}) {
  const data = resource.read();
  return children(data);
}

function CloudflareForm({
  initial,
  onSaved,
}: {
  initial: CloudflareSettings;
  onSaved: () => void;
}) {
  const [settings, setSettings] = useState(initial);
  const [isSaving, setIsSaving] = useState(false);

  React.useEffect(() => {
    // This syncs the form state if the data is refreshed from the server
    setSettings(initial);
  }, [initial]);

  const handleSave = async () => {
    setIsSaving(true);
    // Don't send the placeholder back to the server
    const payload = { ...settings };
    if (payload.cloudflare_api_token === "********") {
      delete payload.cloudflare_api_token;
    }

    const savePromise = updateCloudflareSettings(payload)
      .then((newSettings) => {
        setSettings(newSettings); // Update local state with the saved data (which includes the '********' placeholder)
        onSaved(); // This will trigger a re-fetch in the parent
        return { name: "Cloudflare Settings" };
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
      setIsSaving(false);
    }
  };

  const handlePurge = () => {
    const purgePromise = purgeCloudflareCache();
    sonnerToast.promise(purgePromise, {
      loading: "Purging Cloudflare cache...",
      success: (data) => data.message,
      error: (err) => err.message || "Could not purge Cloudflare cache.",
    });
  };

  return (
    <div className="space-y-6">
      <NetworkAlert isNetworkAdmin={initial.is_network_admin} />
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center space-x-2">
            <Cloud className="h-5 w-5" />
            <span>Cloudflare Integration</span>
          </CardTitle>
          <CardDescription>
            Connect your Cloudflare account to automatically purge the cache.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="flex items-center justify-between rounded-lg border p-4">
            <Label htmlFor="cloudflare-api">
              Enable Cloudflare Integration
            </Label>
            <Switch
              id="cloudflare-api"
              checked={settings.cloudflare_enabled}
              onCheckedChange={(checked) =>
                setSettings((prev) => ({
                  ...prev,
                  cloudflare_enabled: checked,
                }))
              }
            />
          </div>

          {settings.cloudflare_enabled && (
            <div className="space-y-4 border-t pt-4">
              <div className="space-y-2">
                <Label htmlFor="api-token">API Token</Label>
                <Input
                  id="api-token"
                  type="password"
                  placeholder="Enter your Cloudflare API token"
                  value={settings.cloudflare_api_token}
                  onChange={(e) =>
                    setSettings((prev) => ({
                      ...prev,
                      cloudflare_api_token: e.target.value,
                    }))
                  }
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="zone-id">Zone ID</Label>
                <Input
                  id="zone-id"
                  placeholder="Enter your Cloudflare Zone ID"
                  value={settings.cloudflare_zone_id}
                  onChange={(e) =>
                    setSettings((prev) => ({
                      ...prev,
                      cloudflare_zone_id: e.target.value,
                    }))
                  }
                />
              </div>
            </div>
          )}

          <div className="border-t pt-6 space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <Label>Cloudflare Cache</Label>
                <p className="text-sm text-gray-600">
                  Clear all cached content from Cloudflare for this zone.
                </p>
              </div>
              <Button
                variant="destructive"
                onClick={handlePurge}
                className="flex items-center space-x-2"
                disabled={!settings.cloudflare_enabled || isSaving}
              >
                <Trash2 className="h-4 w-4" />
                <span>Purge Everything</span>
              </Button>
            </div>
          </div>

          <div className="flex justify-end pt-4 border-t">
            <Button onClick={handleSave} disabled={isSaving}>
              {isSaving
                ? "Saving..."
                : initial.is_network_admin
                ? "Save Network Settings"
                : "Save Site Settings"}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

export function CloudflareIntegration() {
  const [resource, setResource] = useState(initialResource);
  const refresh = useCallback(() => {
    setResource(createResource());
  }, []);

  return (
    <ErrorBoundary
      fallback={
        <Alert variant="destructive">
          <AlertTitle>Error</AlertTitle>
          <AlertDescription>
            Could not load Cloudflare settings. Please try again.
          </AlertDescription>
        </Alert>
      }
    >
      <Suspense fallback={<CloudflareSettingsSkeleton />}>
        <SectionSuspense resource={resource}>
          {(initialData) => (
            <CloudflareForm initial={initialData} onSaved={refresh} />
          )}
        </SectionSuspense>
      </Suspense>
    </ErrorBoundary>
  );
}
