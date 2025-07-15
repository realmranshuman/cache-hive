import { wpApiSettings } from "./shared";
import { CssFormData } from "@/pageOptimization/CssSettingsForm";

export async function getCssSettings(): Promise<CssFormData> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/css`,
    {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
    }
  );
  if (!response.ok) throw new Error("Failed to fetch CSS settings");
  return response.json();
}

export async function updateCssSettings(
  data: CssFormData
): Promise<CssFormData> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/css`,
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
  if (!response.ok) throw new Error("Failed to update CSS settings");
  return response.json();
}
