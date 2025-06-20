import { wpApiSettings } from "./shared";

export interface BrowserCacheStatus {
  settings: {
    browserCacheEnabled: boolean;
    browserCacheTTL: number;
  };
  server: 'apache' | 'nginx' | 'litespeed' | 'unknown';
  htaccessWritable?: boolean | null;
  nginxVerified?: boolean | null;
  rules: string;
  rulesPresent?: boolean;
}

export async function getBrowserCacheSettings(): Promise<BrowserCacheStatus> {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/browser-cache`, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
  });
  if (!response.ok) throw new Error('Failed to fetch browser cache settings');
  return response.json();
}

export async function verifyNginxBrowserCache(): Promise<{ verified: boolean; message?: string }> {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/browser-cache/verify-nginx`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
  });
  if (!response.ok) throw new Error('Failed to verify Nginx browser cache');
  return response.json();
}

export async function updateBrowserCacheSettings(data: { browserCacheEnabled: boolean; browserCacheTTL: number }) {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/browser-cache`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify(data),
  });
  if (!response.ok) {
    let errorData: any = {};
    try {
      errorData = await response.json();
    } catch (e) {}
    const err: any = new Error(errorData.error || 'Failed to update browser cache settings');
    err.code = errorData.code;
    err.rules = errorData.rules;
    err.currentStatus = errorData.currentStatus;
    throw err;
  }
  return response.json();
}
