import { chToolbarSettings } from "./shared";

/**
 * Creates the headers object for an API call using the initial nonce.
 */
const getHeaders = () => ({
  "X-WP-Nonce": chToolbarSettings.nonce,
  "Content-Type": "application/json",
});

/**
 * Calls the API to purge all caches.
 */
export async function purgeAll(): Promise<void> {
  const response = await fetch(
    `${chToolbarSettings.root}cache-hive/v1/actions/purge-all`,
    { method: "POST", headers: getHeaders(), credentials: "include" }
  );
  if (!response.ok) throw new Error("Failed to purge all caches.");
}

/**
 * Calls the API to purge the entire disk cache.
 */
export async function purgeDiskCache(): Promise<void> {
  const response = await fetch(
    `${chToolbarSettings.root}cache-hive/v1/actions/purge-disk-cache`,
    { method: "POST", headers: getHeaders(), credentials: "include" }
  );
  if (!response.ok) throw new Error("Failed to purge disk cache.");
}

/**
 * Calls the API to purge the object cache.
 */
export async function purgeObjectCache(): Promise<void> {
  const response = await fetch(
    `${chToolbarSettings.root}cache-hive/v1/actions/purge-object-cache`,
    { method: "POST", headers: getHeaders(), credentials: "include" }
  );
  if (!response.ok) throw new Error("Failed to purge object cache.");
}

export async function purgeCloudflareCache(): Promise<void> {
  const response = await fetch(
    `${chToolbarSettings.root}cache-hive/v1/actions/purge-cloudflare`,
    { method: "POST", headers: getHeaders(), credentials: "include" }
  );
  if (!response.ok) throw new Error("Failed to purge Cloudflare cache.");
}

export async function purgeThisPage(): Promise<void> {
  if (!chToolbarSettings.page_url) {
    throw new Error(
      "Cannot purge page: URL not available. This action is only on the frontend."
    );
  }
  const response = await fetch(
    `${chToolbarSettings.root}cache-hive/v1/actions/purge-this-page`,
    {
      method: "POST",
      headers: getHeaders(),
      credentials: "include",
      body: JSON.stringify({ url: chToolbarSettings.page_url }),
    }
  );
  if (!response.ok) throw new Error("Failed to purge this page's cache.");
}

/**
 * Calls the API to purge the current user's entire private cache.
 */
export async function purgeMyPrivateCache(): Promise<void> {
  const response = await fetch(
    `${chToolbarSettings.root}cache-hive/v1/actions/purge-my-private-cache`,
    { method: "POST", headers: getHeaders(), credentials: "include" }
  );
  if (!response.ok) throw new Error("Failed to purge your private cache.");
}
