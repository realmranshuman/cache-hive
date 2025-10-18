import { wpApiSettings } from "./shared";
export interface CacheFormData {
  enable_cache: boolean;
  cache_logged_users: boolean;
  cache_commenters: boolean;
  cache_rest_api: boolean;
  cache_mobile: boolean;
  mobile_user_agents?: string[];
  is_network_admin?: boolean;
}

export async function getCacheSettings(): Promise<CacheFormData> {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/cache`, {
    method: "GET",
    headers: {
      "X-WP-Nonce": wpApiSettings.nonce,
      "Content-Type": "application/json",
    },
    credentials: "include",
  });
  if (!response.ok) throw new Error("Failed to fetch cache settings");
  return response.json();
}

export async function updateCacheSettings(
  data: Partial<CacheFormData>
): Promise<CacheFormData> {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/cache`, {
    method: "POST",
    headers: {
      "X-WP-Nonce": wpApiSettings.nonce,
      "Content-Type": "application/json",
    },
    credentials: "include",
    body: JSON.stringify(data),
  });
  if (!response.ok) throw new Error("Failed to update cache settings");
  return response.json();
}
