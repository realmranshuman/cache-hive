import { wpApiSettings } from "./shared";
export interface ImageOptimizationSettings {
  image_optimization_library: "gd" | "imagemagick";
  image_optimize_losslessly: boolean;
  image_optimize_original: boolean;
  image_next_gen_format: "default" | "webp" | "avif";
  image_quality: number;
  image_delivery_method: "rewrite" | "picture";
  image_remove_exif: boolean;
  image_auto_resize: boolean;
  image_max_width: number;
  image_max_height: number;
  image_cron_optimization: boolean; // RENAMED
  image_exclude_images: string;
  image_exclude_picture_rewrite: string;
  image_selected_thumbnails: string[];
  image_disable_png_gif: boolean;
}

export interface ThumbnailSize {
  id: string;
  name: string;
  size: string;
}

export interface ServerCapabilities {
  gd_support: boolean;
  gd_webp_support: boolean;
  gd_avif_support: boolean;
  imagick_support: boolean;
  imagick_version: string | null;
  is_imagick_old: boolean;
  imagick_webp_support: boolean;
  imagick_avif_support: boolean;
  thumbnail_sizes: ThumbnailSize[];
}

export interface ImageStats {
  total_images: number;
  optimized_images: number;
  unoptimized_images: number;
  optimization_percent: number;
}

export interface SyncState {
  is_running: boolean;
  is_finished: boolean;
  total_to_optimize: number;
  processed: number;
}

export interface ImageOptimizationApiResponse {
  settings: ImageOptimizationSettings;
  server_capabilities: ServerCapabilities;
  stats: ImageStats;
  sync_state: SyncState | false;
}

export async function getImageOptimizationSettings(): Promise<ImageOptimizationApiResponse> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/image`,
    {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
    }
  );
  if (!response.ok)
    throw new Error("Failed to fetch image optimization settings");
  return response.json();
}

export async function updateImageOptimizationSettings(
  data: Partial<ImageOptimizationSettings>
): Promise<ImageOptimizationApiResponse> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/image`,
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
  if (!response.ok)
    throw new Error("Failed to update image optimization settings");
  return response.json();
}

export async function destroyAllImageOptimizationData(): Promise<{
  message: string;
  stats: ImageStats;
}> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/image/all-data`,
    {
      method: "DELETE",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
      },
      credentials: "include",
    }
  );
  if (!response.ok) {
    const errorData = await response.json();
    throw new Error(errorData.message || "Failed to destroy optimization data");
  }
  return response.json();
}

// API functions for manual sync
export async function startImageSync(): Promise<SyncState> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/image/sync`,
    {
      method: "POST",
      headers: { "X-WP-Nonce": wpApiSettings.nonce },
      credentials: "include",
    }
  );
  if (!response.ok) throw new Error("Failed to start sync");
  return response.json();
}

export async function getImageSyncStatus(): Promise<SyncState> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/image/sync`,
    {
      method: "GET",
      headers: { "X-WP-Nonce": wpApiSettings.nonce },
      credentials: "include",
    }
  );
  if (!response.ok) throw new Error("Failed to get sync status");
  return response.json();
}

export async function cancelImageSync(): Promise<{ message: string }> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/optimizers/image/sync`,
    {
      method: "DELETE",
      headers: { "X-WP-Nonce": wpApiSettings.nonce },
      credentials: "include",
    }
  );
  if (!response.ok) throw new Error("Failed to cancel sync");
  return response.json();
}
