import { wpApiSettings } from "./shared";

// UPDATED: The interface now matches the backend's settings structure precisely.
export interface ObjectCacheSettings {
  objectCacheEnabled: boolean;
  objectCacheMethod: 'redis' | 'memcached';
  objectCacheClient: 'phpredis' | 'predis' | 'credis' | 'memcached'; // NEW
  objectCacheHost: string;
  objectCachePort: number;
  objectCacheUsername?: string;
  objectCachePassword?: string;
  objectCacheDatabase?: number; // NEW
  objectCacheTimeout?: number; // NEW
  objectCacheLifetime: number;
  objectCacheKey?: string; // read-only
  objectCacheGlobalGroups?: string[];
  objectCacheNoCacheGroups?: string[];
  objectCachePersistentConnection?: boolean;
  objectCacheTlsOptions?: { // NEW
    ca_cert?: string;
    verify_peer?: boolean;
  };
  wpConfigOverrides?: { [key: string]: boolean }; // read-only
  liveStatus?: {
    status: string;
    client: string;
    compression?: string;
    serializer?: string;
    persistent?: boolean;
    prefetch?: boolean;
    // ...other properties from backend clients
    [key: string]: any;
  };
  serverCapabilities?: {
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
}

export async function getObjectCacheSettings(): Promise<ObjectCacheSettings> {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/object-cache`, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
  });
  if (!response.ok) throw new Error('Failed to fetch object cache settings');
  return response.json();
}

export async function updateObjectCacheSettings(data: Partial<ObjectCacheSettings>): Promise<ObjectCacheSettings> {
  const response = await fetch(`${wpApiSettings.root}cache-hive/v1/object-cache`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce,
      'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify(data),
  });
  
  const responseBody = await response.json();

  if (!response.ok) {
    // Throw a custom error with the message from the backend
    const error = new Error(responseBody.message || 'Failed to update object cache settings');
    // Attach the full body for more context if needed
    (error as any).data = responseBody; 
    throw error;
  }
  
  return responseBody;
}