// src/api.ts

const API_NAMESPACE = 'cache-hive/v1'; // Matches your PHP REST API namespace

/**
 * Fetches all WordPress roles for use in the exclusions form.
 */
export async function getRoles(): Promise<{ id: string; name: string }[]> {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/roles`, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
  });
  if (!response.ok) {
    throw new Error('Failed to fetch roles');
  }
  return response.json();
}

// Per-section API utilities
export async function getCacheSettings() {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/cache`, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
  });
  if (!response.ok) throw new Error('Failed to fetch cache settings');
  return response.json();
}
export async function updateCacheSettings(data: any) {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/cache`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify(data),
  });
  if (!response.ok) throw new Error('Failed to update cache settings');
  return response.json();
}
export async function getTtlSettings() {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/ttl`, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
  });
  if (!response.ok) throw new Error('Failed to fetch TTL settings');
  return response.json();
}
export async function updateTtlSettings(data: any) {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/ttl`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify(data),
  });
  if (!response.ok) throw new Error('Failed to update TTL settings');
  return response.json();
}
export async function getAutoPurgeSettings() {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/autopurge`, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
  });
  if (!response.ok) throw new Error('Failed to fetch auto purge settings');
  return response.json();
}
export async function updateAutoPurgeSettings(data: any) {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/autopurge`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify(data),
  });
  if (!response.ok) throw new Error('Failed to update auto purge settings');
  return response.json();
}
export async function getExclusionsSettings() {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/exclusions`, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
  });
  if (!response.ok) throw new Error('Failed to fetch exclusions settings');
  return response.json();
}
export async function updateExclusionsSettings(data: any) {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/exclusions`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify(data),
  });
  if (!response.ok) throw new Error('Failed to update exclusions settings');
  return response.json();
}
export async function getObjectCacheSettings() {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/object-cache`, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
  });
  if (!response.ok) throw new Error('Failed to fetch object cache settings');
  return response.json();
}
export async function updateObjectCacheSettings(data: any) {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/object-cache`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify(data),
  });
  if (!response.ok) throw new Error('Failed to update object cache settings');
  return response.json();
}
export async function getBrowserCacheSettings() {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/browser-cache`, {
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
export async function updateBrowserCacheSettings(data: any) {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/browser-cache`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify(data),
  });
  if (!response.ok) throw new Error('Failed to update browser cache settings');
  return response.json();
}

// Ensure wpApiSettings is available (WordPress localizes this)
declare global {
  interface Window {
    wpApiSettings: {
      root: string;
      nonce: string;
      [key: string]: any;
    };
  }
}
const wpApiSettings = window.wpApiSettings || { root: '/wp-json/', nonce: '' };