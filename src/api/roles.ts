import { wpApiSettings } from "./shared";

export async function getRoles(): Promise<{ id: string; name: string }[]> {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/roles`, {
    method: "GET",
    headers: {
      "X-WP-Nonce": wpApiSettings.nonce,
      "Content-Type": "application/json",
    },
    credentials: "include",
  });
  if (!response.ok) {
    throw new Error("Failed to fetch roles");
  }
  return response.json();
}
