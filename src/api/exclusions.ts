import { wpApiSettings } from "./shared";

export interface ExclusionsFormData {
  exclude_uris?: string[];
  exclude_query_strings?: string[];
  exclude_cookies?: string[];
  exclude_roles?: string[];
  is_network_admin?: boolean;
}

export async function getExclusionsSettings(): Promise<ExclusionsFormData> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/exclusions`,
    {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
    }
  );
  if (!response.ok) throw new Error("Failed to fetch exclusions settings");
  return response.json();
}

export async function updateExclusionsSettings(
  data: Partial<ExclusionsFormData>
): Promise<ExclusionsFormData> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/exclusions`,
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
  if (!response.ok) throw new Error("Failed to update exclusions settings");
  return response.json();
}
