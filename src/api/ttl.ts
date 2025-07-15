import { wpApiSettings } from "./shared";
import { TtlFormData } from "@/caching/TtlTabForm";

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
  data: TtlFormData
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
