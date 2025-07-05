import { wpApiSettings } from "./shared";
import { CacheFormData } from "@/caching/CacheTabForm";

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
  data: CacheFormData
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
