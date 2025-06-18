"use client"

import { useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import * as React from "react"
import { CssSettingsForm } from "./pageOptimization/CssSettingsForm"
import { JsSettingsForm } from "./pageOptimization/JsSettingsForm"
import { HtmlSettingsForm } from "./pageOptimization/HtmlSettingsForm"
import { MediaSettingsForm } from "./pageOptimization/MediaSettingsForm"

export function PageOptimization() {
  // CSS Settings State
  const [cssSettings, setCssSettings] = useState({
    minify: true,
    combine: false,
    combineExternalInline: false,
    fontOptimization: "default",
    excludes: ""
  })

  // JS Settings State
  const [jsSettings, setJsSettings] = useState({
    minify: true,
    combine: false,
    combineExternalInline: false,
    deferMode: "off",
    excludes: "",
    deferExcludes: ""
  })

  // HTML Settings State
  const [htmlSettings, setHtmlSettings] = useState({
    minify: true,
    dnsPrefetch: "",
    dnsPreconnect: "",
    autoDnsPrefetch: false,
    googleFontsAsync: false,
    keepComments: false,
    removeEmoji: true,
    removeNoscript: false
  })

  // Media Settings State
  const [mediaSettings, setMediaSettings] = useState({
    lazyloadImages: true,
    addMissingSizes: false,
    responsivePlaceholder: false,
    lazyloadIframes: false,
    optimizeUploads: false,
    optimizationQuality: "82",
    autoResizeUploads: false,
    resizeWidth: "",
    resizeHeight: ""
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle>Page Optimization</CardTitle>
      </CardHeader>
      <CardContent>
        <Tabs defaultValue="css" className="w-full">
          <TabsList className="grid w-full grid-cols-4">
            <TabsTrigger value="css">CSS Settings</TabsTrigger>
            <TabsTrigger value="js">JS Settings</TabsTrigger>
            <TabsTrigger value="html">HTML Settings</TabsTrigger>
            <TabsTrigger value="media">Media Settings</TabsTrigger>
          </TabsList>

          {/* CSS Settings Tab */}
          <TabsContent value="css" className="space-y-6 mt-6">
            <CssSettingsForm
              initial={cssSettings}
              onSubmit={(data) => setCssSettings(prev => ({ ...prev, ...data }))}
            />
          </TabsContent>

          {/* JS Settings Tab */}
          <TabsContent value="js" className="space-y-6 mt-6">
            <JsSettingsForm
              initial={jsSettings}
              onSubmit={(data) => setJsSettings(prev => ({ ...prev, ...data }))}
            />
          </TabsContent>

          {/* HTML Settings Tab */}
          <TabsContent value="html" className="space-y-6 mt-6">
            <HtmlSettingsForm
              initial={htmlSettings}
              onSubmit={(data) => setHtmlSettings(prev => ({ ...prev, ...data }))}
            />
          </TabsContent>

          {/* Media Settings Tab */}
          <TabsContent value="media" className="space-y-6 mt-6">
            <MediaSettingsForm
              initial={mediaSettings}
              onSubmit={(data) => setMediaSettings(prev => ({ ...prev, ...data }))}
            />
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  )
}
