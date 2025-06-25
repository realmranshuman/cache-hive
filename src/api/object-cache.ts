import { wpApiSettings } from "./shared";

// Define a type for our settings data for better type safety
export interface ObjectCacheSettings {
  objectCacheEnabled: boolean;
  objectCacheMethod: 'redis' | 'memcached';
  objectCacheHost: string;
  objectCachePort: number;
  objectCacheLifetime: number;
  objectCacheUsername?: string;
  objectCachePassword?: string;
  objectCacheGlobalGroups?: string[];
  objectCacheNoCacheGroups?: string[];
  objectCachePersistentConnection?: boolean;
  liveStatus?: {
    status: string;
    client: string;
    compression?: string;
    serializer?: string;
    [key: string]: any;
  };
  serverCapabilities?: {
    clients: {
      phpredis: boolean;
      predis: boolean;
      credis: boolean;
      memcached: boolean;
    };
    best_client: string | null;
    [key: string]: any;
  }
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

export async function updateObjectCacheSettings(data: any): Promise<ObjectCacheSettings> {
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