// src/api.ts

const API_NAMESPACE = 'cache-hive/v1'; // Matches your PHP REST API namespace

/**
 * Fetches the current settings from the backend.
 */
export async function getSettings(): Promise<any> {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/settings`, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
  });
  if (!response.ok) {
    throw new Error('Failed to fetch settings');
  }
  return response.json();
}

/**
 * Updates the settings on the backend.
 * @param data The settings data to update.
 */
export async function updateSettings(data: any): Promise<any> {
  const response = await fetch(`${wpApiSettings.root}${API_NAMESPACE}/settings`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify(data),
  });
  if (!response.ok) {
    const errorData = await response.json();
    throw new Error(errorData.message || 'Failed to update settings');
  }
  return response.json();
}

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