import { wpApiSettings } from "./shared";

export async function getObjectCacheSettings() {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/object-cache`, {
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
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/object-cache`, {
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
