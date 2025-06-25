import { wpApiSettings } from "./shared";

export async function getTtlSettings() {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/ttl`, {
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
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/ttl`, {
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
