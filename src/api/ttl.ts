import { wpApiSettings } from "./shared";

export interface TtlFormData {
  public_cache_ttl: number;
  private_cache_ttl: number;
  front_page_ttl: number;
  feed_ttl: number;
  rest_ttl: number;
  is_network_admin?: boolean;
}

export async function getTtlSettings(): Promise<TtlFormData> {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/ttl`, {
    method: "GET",
    headers: {
      "X-WP-Nonce": wpApiSettings.nonce,
      "Content-Type": "application/json",
    },
    credentials: "include",
  });
  if (!response.ok) throw new Error("Failed to fetch TTL settings");
  return response.json();
}

export async function updateTtlSettings(
  data: Partial<TtlFormData>
): Promise<TtlFormData> {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/ttl`, {
    method: "POST",
    headers: {
      "X-WP-Nonce": wpApiSettings.nonce,
      "Content-Type": "application/json",
    },
    credentials: "include",
    body: JSON.stringify(data),
  });
  if (!response.ok) throw new Error("Failed to update TTL settings");
  return response.json();
}
