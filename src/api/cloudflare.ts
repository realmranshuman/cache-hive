import { wpApiSettings } from "./shared";

export interface CloudflareSettings {
  cloudflare_enabled: boolean;
  cloudflare_api_method: "token" | "key";
  cloudflare_api_key: string;
  cloudflare_api_token: string;
  cloudflare_email: string;
  cloudflare_domain: string;
  cloudflare_zone_id: string;
  is_network_admin?: boolean;
}

export async function getCloudflareSettings(): Promise<CloudflareSettings> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/cloudflare`,
    {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
    }
  );
  if (!response.ok) throw new Error("Failed to fetch Cloudflare settings");
  return response.json();
}

export async function updateCloudflareSettings(
  data: Partial<CloudflareSettings>
): Promise<CloudflareSettings> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/cloudflare`,
    {
      method: "POST",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
      body: JSON.stringify(data),
    }
  );
  if (!response.ok) {
    const errorData = await response.json();
    throw new Error(
      errorData.message || "Failed to update Cloudflare settings"
    );
  }
  return response.json();
}

export async function purgeCloudflareCache(): Promise<{ message: string }> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/actions/purge_all`,
    {
      method: "POST",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
    }
  );
  if (!response.ok) {
    const errorData = await response.json();
    throw new Error(errorData.message || "Failed to purge Cloudflare cache");
  }
  return response.json();
}
