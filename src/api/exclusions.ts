import { wpApiSettings } from "./shared";

export async function getExclusionsSettings() {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/exclusions`, {
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
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/exclusions`, {
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
