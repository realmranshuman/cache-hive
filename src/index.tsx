import * as React from "react";
import { createRoot } from "react-dom/client";
import { Header } from "./header";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Dashboard } from "./dashboard";
import { Caching } from "./caching";
import { PageOptimization } from "./page-optimization";
import { ImageOptimizationSettings } from "./image-optimization";
import { CloudflareIntegration } from "./cloudflare-integration";
import { Toaster } from "@/components/ui/sonner";
import "./index.css";
import { ThemeProvider } from "./components/theme-provider";

function getTabFromUrl() {
  const searchParams = new URLSearchParams(window.location.search);
  const page = searchParams.get('page');
  switch (page) {
    case 'cache-hive-cloudflare':
      return 'cloudflare';
    case 'cache-hive-optimization':
      return 'optimization';
    case 'cache-hive-image-optimization':
      return 'image-optimization';
    case 'cache-hive-caching':
      return 'caching';
    case 'cache-hive':
    default:
      return 'dashboard';
  }
}

function setUrlForTab(tab: string) {
  let slug = 'cache-hive';
  if (tab === 'caching') {
    slug = 'cache-hive-caching';
  } else if (tab === 'optimization') {
    slug = 'cache-hive-optimization';
  } else if (tab === 'image-optimization') {
    slug = 'cache-hive-image-optimization';
  } else if (tab === 'cloudflare') {
    slug = 'cache-hive-cloudflare';
  }
  const url = new URL(window.location.href);
  if (url.searchParams.get('page') !== slug) {
    url.searchParams.set('page', slug);
    window.history.pushState({ path: url.toString() }, '', url.toString());
  }
}

function CacheHiveApp() {
  const [activeTab, setActiveTab] = React.useState(getTabFromUrl());
  const tabs = [
    { value: "dashboard", label: "Dashboard" },
    { value: "caching", label: "Caching" },
    { value: "optimization", label: "Page Optimization" },
    { value: "image-optimization", label: "Image Optimization" },
    { value: "cloudflare", label: "Cloudflare Integration" },
  ];

  React.useEffect(() => {
    setUrlForTab(activeTab);
  }, [activeTab]);

  React.useEffect(() => {
    const handlePopState = () => {
      setActiveTab(getTabFromUrl());
    };
    window.addEventListener('popstate', handlePopState);
    return () => {
      window.removeEventListener('popstate', handlePopState);
    };
  }, []);
  return (
    <ThemeProvider defaultTheme="dark" storageKey="vite-ui-theme">
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
        <Header />
        <div className="container mx-auto px-4 py-6">
          <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
            {/* Responsive Scrollable Tabs */}
            <div className="relative rounded-sm overflow-x-scroll h-10 bg-muted mb-6">
              <TabsList className="absolute flex flex-row justify-stretch w-full pt-1 pl-1 pr-1 pb-0">
                {tabs.map((tab, idx) => (
                  <TabsTrigger
                    className="w-full"
                    key={`tab_trigger_${idx}`}
                    value={tab.value}
                  >
                    {tab.label}
                  </TabsTrigger>
                ))}
            </TabsList>
        </div>
            <TabsContent value="dashboard">
              <Dashboard />
            </TabsContent>
            <TabsContent value="caching">
              <Caching />
            </TabsContent>
            <TabsContent value="optimization">
              <PageOptimization />
            </TabsContent>
            <TabsContent value="image-optimization">
              <ImageOptimizationSettings />
            </TabsContent>
            <TabsContent value="cloudflare">
              <CloudflareIntegration />
            </TabsContent>
          </Tabs>
        </div>
        <Toaster position="bottom-center" richColors closeButton/>
      </div>
  </ThemeProvider>
  );
}

const rootEl = document.getElementById("cache-hive-root");
if (rootEl) {
  createRoot(rootEl).render(<CacheHiveApp />);
} else {
  document.addEventListener("DOMContentLoaded", () => {
    const el = document.getElementById("cache-hive-root");
    if (el) {
      createRoot(el).render(<CacheHiveApp />);
    }
  });
}