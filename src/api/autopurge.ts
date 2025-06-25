import { wpApiSettings } from "./shared";

export async function getAutoPurgeSettings() {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/autopurge`, {
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
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/autopurge`, {
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
