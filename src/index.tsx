import * as React from "react";
import { createRoot } from "react-dom/client";
import { Header } from "./header";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Dashboard } from "./dashboard";
import { Caching } from "./caching";
import { PageOptimization } from "./page-optimization";
import { CloudflareIntegration } from "./cloudflare-integration";
import { Toaster } from "@/components/ui/sonner";
import "./index.css";
import { ThemeProvider } from "./components/theme-provider";

function getTabFromUrl() {
  // URLSearchParams is the modern, correct way to read query strings.
  const searchParams = new URLSearchParams(window.location.search);
  const page = searchParams.get('page');

  switch (page) {
    case 'cache-hive-cloudflare':
      return 'cloudflare';
    case 'cache-hive-optimization':
      return 'optimization';
    case 'cache-hive-caching':
      return 'caching';
    case 'cache-hive': // This is the main dashboard page
    default:
      return 'dashboard';
  }
}

/**
 * Updates the URL's 'page' query parameter to match the active tab
 * without reloading the page.
 */
function setUrlForTab(tab: string) {
  let slug = 'cache-hive'; // Default to dashboard
  if (tab === 'caching') {
    slug = 'cache-hive-caching';
  } else if (tab === 'optimization') {
    slug = 'cache-hive-optimization';
  } else if (tab === 'cloudflare') {
    slug = 'cache-hive-cloudflare';
  }
  
  const url = new URL(window.location.href);
  // Only update if the URL is actually different, to prevent unnecessary history entries.
  if (url.searchParams.get('page') !== slug) {
    url.searchParams.set('page', slug);
    // Use pushState so the browser's back/forward buttons work as expected.
    window.history.pushState({ path: url.toString() }, '', url.toString());
  }
}

// --- END: CORRECTED AND ROBUST URL HANDLING ---


function CacheHiveApp() {
  const [activeTab, setActiveTab] = React.useState(getTabFromUrl());

  // This effect syncs the URL when the user clicks a tab.
  React.useEffect(() => {
    setUrlForTab(activeTab);
  }, [activeTab]);

  // This effect listens for browser back/forward buttons to update the active tab.
  React.useEffect(() => {
    const handlePopState = () => {
      setActiveTab(getTabFromUrl());
    };

    window.addEventListener('popstate', handlePopState);
    return () => {
      window.removeEventListener('popstate', handlePopState);
    };
  }, []); // Run only once on component mount.

  return (
    <ThemeProvider defaultTheme="dark" storageKey="vite-ui-theme">
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
        <Header />
        <div className="container mx-auto px-4 py-6">
          <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
            <TabsList className="grid w-full grid-cols-4 mb-6">
              <TabsTrigger value="dashboard">Dashboard</TabsTrigger>
              <TabsTrigger value="caching">Caching</TabsTrigger>
              <TabsTrigger value="optimization">Page Optimization</TabsTrigger>
              <TabsTrigger value="cloudflare">Cloudflare Integration</TabsTrigger>
            </TabsList>
            <TabsContent value="dashboard">
              <Dashboard />
            </TabsContent>
            <TabsContent value="caching">
              <Caching />
            </TabsContent>
            <TabsContent value="optimization">
              <PageOptimization />
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