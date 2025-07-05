import { wpApiSettings } from "./shared";
import { HtmlFormData } from "@/pageOptimization/HtmlSettingsForm";

export async function getHtmlSettings(): Promise<HtmlFormData> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/html`,
    {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
    }
  );
  if (!response.ok) throw new Error("Failed to fetch HTML settings");
  return response.json();
}

export async function updateHtmlSettings(
  data: HtmlFormData
): Promise<HtmlFormData> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/html`,
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
  if (!response.ok) throw new Error("Failed to update HTML settings");
  return response.json();
}
