import { wpApiSettings } from "./shared";
import { MediaFormData } from "@/pageOptimization/MediaSettingsForm";

export async function getMediaSettings(): Promise<MediaFormData> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/media`,
    {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
    }
  );
  if (!response.ok) throw new Error("Failed to fetch Media settings");
  return response.json();
}

export async function updateMediaSettings(
  data: MediaFormData
): Promise<MediaFormData> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/media`,
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
  if (!response.ok) throw new Error("Failed to update Media settings");
  return response.json();
}
