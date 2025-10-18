import { wpApiSettings } from "./shared";

export interface ObjectCacheSettings {
  object_cache_enabled: boolean;
  object_cache_method: "redis" | "memcached";
  object_cache_client: "phpredis" | "predis" | "credis" | "memcached";
  object_cache_host: string;
  object_cache_port: number;
  object_cache_username?: string;
  object_cache_password?: string;
  object_cache_database?: number;
  object_cache_timeout?: number;
  object_cache_lifetime: number;
  object_cache_key?: string;
  object_cache_global_groups?: string[];
  object_cache_no_cache_groups?: string[];
  object_cache_persistent_connection?: boolean;
  object_cache_tls_options?: {
    ca_cert?: string;
    verify_peer?: boolean;
  };
  wp_config_overrides?: { [key: string]: boolean };
  live_status?: {
    status: string;
    client: string;
    [key: string]: any;
  };
  server_capabilities?: {
    clients: {
      phpredis: boolean;
      predis: boolean;
      credis: boolean;
      memcached: boolean;
    };
    serializers: {
      igbinary: boolean;
      php: boolean;
    };
    compression: {
      zstd: boolean;
      lz4: boolean;
      lzf: boolean;
    };
  };
  is_network_admin?: boolean;
}

export async function getObjectCacheSettings(): Promise<ObjectCacheSettings> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/object-cache`,
    {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
      credentials: "include",
    }
  );
  if (!response.ok) throw new Error("Failed to fetch object cache settings");
  return response.json();
}

export async function updateObjectCacheSettings(
  data: Partial<ObjectCacheSettings>
): Promise<ObjectCacheSettings> {
  const response = await fetch(
    `${wpApiSettings.root}cache-hive/v1/object-cache`,
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

  const responseBody = await response.json();
  if (!response.ok) {
    const error = new Error(
      responseBody.message || "Failed to update object cache settings"
    );
    (error as any).data = responseBody;
    throw error;
  }
  return responseBody;
}
