import { wpApiSettings } from "./shared";
export interface AutoPurgeFormData {
  auto_purge_entire_site?: boolean;
  auto_purge_front_page: boolean;
  auto_purge_home_page: boolean;
  auto_purge_pages: boolean;
  auto_purge_author_archive: boolean;
  auto_purge_post_type_archive: boolean;
  auto_purge_yearly_archive: boolean;
  auto_purge_monthly_archive: boolean;
  auto_purge_daily_archive: boolean;
  auto_purge_term_archive: boolean;
  purge_on_upgrade?: boolean;
  serve_stale?: boolean;
  custom_purge_hooks?: string[];
  is_network_admin?: boolean;
}

export async function getAutoPurgeSettings(): Promise<AutoPurgeFormData> {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/autopurge`, {
    method: "GET",
    headers: {
      "X-WP-Nonce": wpApiSettings.nonce,
      "Content-Type": "application/json",
    },
    credentials: "include",
  });
  if (!response.ok) throw new Error("Failed to fetch auto purge settings");
  return response.json();
}

export async function updateAutoPurgeSettings(
  data: Partial<AutoPurgeFormData>
): Promise<AutoPurgeFormData> {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/autopurge`, {
    method: "POST",
    headers: {
      "X-WP-Nonce": wpApiSettings.nonce,
      "Content-Type": "application/json",
    },
    credentials: "include",
    body: JSON.stringify(data),
  });
  if (!response.ok) throw new Error("Failed to update auto purge settings");
  return response.json();
}
