"use client"

import { useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Switch } from "@/components/ui/switch"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Cloud, Info, Trash2 } from "lucide-react"
import * as React from "react"

export function CloudflareIntegration() {
  const [cloudflareSettings, setCloudflareSettings] = useState({
    apiEnabled: false,
    apiKey: "",
    email: "",
    domain: "",
    developmentMode: false,
  })

  const handlePurgeEverything = () => {
    // Handle purge everything action
    console.log("Purging Cloudflare cache...")
  }

  return (
    <div className="space-y-6">
      <Alert>
        <Info className="h-4 w-4" />
        <AlertDescription>
          The cache settings configured are also automatically applied for Cloudflare caching.
        </AlertDescription>
      </Alert>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center space-x-2">
            <Cloud className="h-5 w-5" />
            <span>Cloudflare Integration</span>
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="flex items-center justify-between">
            <Label htmlFor="cloudflare-api">Cloudflare API</Label>
            <Switch
              id="cloudflare-api"
              checked={cloudflareSettings.apiEnabled}
              onCheckedChange={(checked) => setCloudflareSettings((prev) => ({ ...prev, apiEnabled: checked }))}
            />
          </div>

          {cloudflareSettings.apiEnabled && (
            <div className="space-y-4 border-t pt-4">
              <div className="space-y-2">
                <Label htmlFor="api-key">Global API Key / API Token</Label>
                <Input
                  id="api-key"
                  type="password"
                  placeholder="Enter your Cloudflare API key or token"
                  value={cloudflareSettings.apiKey}
                  onChange={(e) => setCloudflareSettings((prev) => ({ ...prev, apiKey: e.target.value }))}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="email">Email Address</Label>
                <Input
                  id="email"
                  type="email"
                  placeholder="your-email@example.com"
                  value={cloudflareSettings.email}
                  onChange={(e) => setCloudflareSettings((prev) => ({ ...prev, email: e.target.value }))}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="domain">Domain</Label>
                <Input
                  id="domain"
                  placeholder="example.com"
                  value={cloudflareSettings.domain}
                  onChange={(e) => setCloudflareSettings((prev) => ({ ...prev, domain: e.target.value }))}
                />
              </div>
            </div>
          )}

          <div className="border-t pt-6 space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <Label htmlFor="development-mode">Development Mode</Label>
                <p className="text-sm text-gray-600">Temporarily bypass Cloudflare cache</p>
              </div>
              <div className="flex items-center space-x-2">
                <span className="text-sm text-gray-600">Turn OFF</span>
                <Switch
                  id="development-mode"
                  checked={cloudflareSettings.developmentMode}
                  onCheckedChange={(checked) =>
                    setCloudflareSettings((prev) => ({ ...prev, developmentMode: checked }))
                  }
                />
                <span className="text-sm text-gray-600">Turn ON</span>
              </div>
            </div>

            <div className="flex items-center justify-between">
              <div>
                <Label>Cloudflare Cache</Label>
                <p className="text-sm text-gray-600">Clear all cached content from Cloudflare</p>
              </div>
              <Button
                variant="destructive"
                onClick={handlePurgeEverything}
                className="flex items-center space-x-2"
                disabled={!cloudflareSettings.apiEnabled}
              >
                <Trash2 className="h-4 w-4" />
                <span>Purge Everything</span>
              </Button>
            </div>
          </div>

          <div className="flex justify-end pt-4 border-t">
            <Button>Save Settings</Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
