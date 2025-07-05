import { wpApiSettings } from "./shared";

export interface BrowserCacheStatus {
  settings: {
    browser_cache_enabled: boolean;
    browser_cache_ttl: number;
  };
  server: "apache" | "nginx" | "litespeed" | "unknown";
  htaccess_writable?: boolean | null;
  nginx_verified?: boolean | null;
  rules: string;
  rules_present?: boolean;
}

export async function getBrowserCacheSettings(): Promise<BrowserCacheStatus> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/browser-cache`,
    {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
    }
  );
  if (!response.ok) throw new Error("Failed to fetch browser cache settings");
  return response.json();
}

export async function verifyNginxBrowserCache(): Promise<{
  verified: boolean;
  message?: string;
}> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/browser-cache/verify-nginx`,
    {
      method: "POST",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
    }
  );
  if (!response.ok) throw new Error("Failed to verify Nginx browser cache");
  return response.json();
}

export async function updateBrowserCacheSettings(data: {
  browser_cache_enabled: boolean;
  browser_cache_ttl: number;
}): Promise<BrowserCacheStatus> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/browser-cache`,
    {
      method: "POST",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
      body: JSON.stringify(data),
    }
  );
  if (!response.ok) {
    let errorData: any = {};
    try {
      errorData = await response.json();
    } catch (e) {
      /* ignore */
    }
    const err: any = new Error(
      errorData.error || "Failed to update browser cache settings"
    );
    err.code = errorData.code;
    err.rules = errorData.rules;
    err.currentStatus = errorData.currentStatus;
    throw err;
  }
  return response.json();
}
