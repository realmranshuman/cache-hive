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
    method: 'POST', // Or 'PUT' depending on your REST API setup, POST is common for updates
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
  });
  if (!response.ok) {
    const errorData = await response.json();
    throw new Error(errorData.message || 'Failed to update settings');
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