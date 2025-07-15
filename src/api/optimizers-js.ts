import { wpApiSettings } from "./shared";
import { JsFormData } from "@/pageOptimization/JsSettingsForm";

export async function getJsSettings(): Promise<JsFormData> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/js`,
    {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
    }
  );
  if (!response.ok) throw new Error("Failed to fetch JS settings");
  return response.json();
}

export async function updateJsSettings(data: JsFormData): Promise<JsFormData> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/js`,
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
  if (!response.ok) throw new Error("Failed to update JS settings");
  return response.json();
}
