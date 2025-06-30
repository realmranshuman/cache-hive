import * as React from "react"
import { zodResolver } from "@hookform/resolvers/zod"
import { useForm } from "react-hook-form"
import { z } from "zod"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Switch } from "@/components/ui/switch"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { ObjectCacheSettings } from "@/api/object-cache" // UPDATED: Import the new interface

// UPDATED: The Zod schema now includes all the new fields and uses the full camelCase names.
const objectCacheSchema = z.object({
  objectCacheEnabled: z.boolean().default(false),
  objectCacheMethod: z.string().default('redis'),
  objectCacheClient: z.string().optional(), // NEW
  objectCacheHost: z.string().default('127.0.0.1'),
  objectCachePort: z.coerce.number().min(0, "Port cannot be negative").default(6379),
  objectCacheUsername: z.string().optional(),
  objectCachePassword: z.string().optional(),
  objectCacheDatabase: z.coerce.number().min(0, "Database must be a positive integer").optional(), // NEW
  objectCacheTimeout: z.coerce.number().min(0, "Timeout cannot be negative").optional(), // NEW
  objectCacheLifetime: z.coerce.number().min(60, "Lifetime must be at least 60 seconds").default(3600),
  objectCacheGlobalGroups: z.any(),
  objectCacheNoCacheGroups: z.any(),
  objectCachePersistentConnection: z.boolean().optional().default(false),
});

type ObjectCacheFormData = z.infer<typeof objectCacheSchema>

const DEFAULT_GLOBAL_GROUPS = [
  'blog-details', 'blog-lookup', 'global-posts', 'networks', 'rss',
  'sites', 'site-details', 'site-lookup', 'site-options', 'site-transient',
  'users', 'useremail', 'userlogins', 'usermeta', 'user_meta', 'userslugs',
  'blog_meta', 'image_editor', 'network-queries', 'site-queries',
  'theme_files', 'translation_files', 'user-queries',
];

const DEFAULT_NO_CACHE_GROUPS = [
  'comment', 'plugins', 'theme_json', 'themes', 'wc_session_id'
];


function StatusPanel({ status, capabilities }: { status: ObjectCacheSettings['liveStatus'], capabilities: ObjectCacheSettings['serverCapabilities'] }) {
  const isConnected = status?.status === 'Connected';
  const StatusItem = ({ label, value, isBadge = false, variant = 'secondary' }: { label: string, value?: string | boolean | null, isBadge?: boolean, variant?: "default" | "secondary" | "destructive" | "outline" | null | undefined }) => {
    if (value === undefined || value === null || value === '') return null;
    return (
      <div className="flex justify-between items-center text-sm">
        <span className="text-muted-foreground">{label}</span>
        {isBadge ? (<Badge variant={variant}>{String(value)}</Badge>) : (<span className="font-mono text-xs text-right">{String(value)}</span>)}
      </div>
    );
  };

  const bestClient = React.useMemo(() => {
    if (!capabilities?.clients) return 'N/A';
    if (capabilities.clients.phpredis) return 'PhpRedis';
    if (capabilities.clients.predis) return 'Predis';
    if (capabilities.clients.credis) return 'Credis';
    return 'N/A';
  }, [capabilities]);

  return (
    <Card className="sticky top-6">
      <CardHeader>
        <CardTitle>Live Status</CardTitle>
        <CardDescription>Read-only information about the current connection.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-3">
        <StatusItem label="Status" value={status?.status ?? 'Disabled'} isBadge variant={isConnected ? 'default' : 'destructive'} />
        <hr />
        <StatusItem label="Client In Use" value={status?.client} />
        <StatusItem label="Serializer" value={status?.serializer} />
        <StatusItem label="Compression" value={status?.compression} />
        <StatusItem label="Persistent" value={status?.persistent ? 'Yes' : 'No'} />
        <StatusItem label="Prefetch" value={status?.prefetch ? 'Yes' : 'No'} />
        <hr />
        <p className="text-sm font-medium text-muted-foreground pt-2">Server Capabilities</p>
        <StatusItem label="Best Redis Client" value={bestClient} isBadge variant="secondary" />
        <StatusItem label="PhpRedis" value={capabilities?.clients?.phpredis ? 'Available' : 'Not Found'} />
        <StatusItem label="Predis" value={capabilities?.clients?.predis ? 'Available' : 'Not Found'} />
        <StatusItem label="Credis" value={capabilities?.clients?.credis ? 'Available' : 'Not Found'} />
        <StatusItem label="Memcached" value={capabilities?.clients?.memcached ? 'Available' : 'Not Found'} />
      </CardContent>
    </Card>
  )
}


export function ObjectCacheTabForm({ initial, onSubmit, isSaving }: { initial: ObjectCacheSettings, onSubmit: (data: ObjectCacheFormData) => Promise<void>, isSaving: boolean }) {
  function toTextarea(val?: string[] | string) {
    if (Array.isArray(val) && val.length > 0) return val.join("\n");
    return "";
  }
  function fromTextarea(val: string) {
    return val.split(/\r?\n/).map(v => v.trim()).filter(Boolean);
  }

  // UPDATED: All default values now use the full camelCase keys from the initial props.
  const form = useForm<ObjectCacheFormData>({
    resolver: zodResolver(objectCacheSchema),
    defaultValues: {
      objectCacheEnabled: initial.objectCacheEnabled ?? false,
      objectCacheMethod: initial.objectCacheMethod ?? 'redis',
      objectCacheClient: initial.objectCacheClient ?? 'phpredis',
      objectCacheHost: initial.objectCacheHost ?? '127.0.0.1',
      objectCachePort: initial.objectCachePort ?? 6379,
      objectCacheUsername: initial.objectCacheUsername ?? '',
      objectCachePassword: initial.objectCachePassword ?? '',
      objectCacheDatabase: initial.objectCacheDatabase ?? 0,
      objectCacheTimeout: initial.objectCacheTimeout ?? 2.0,
      objectCacheLifetime: initial.objectCacheLifetime ?? 3600,
      objectCacheGlobalGroups: toTextarea(initial.objectCacheGlobalGroups),
      objectCacheNoCacheGroups: toTextarea(initial.objectCacheNoCacheGroups),
      objectCachePersistentConnection: initial.objectCachePersistentConnection ?? false,
    },
  })

  const objectCacheMethod = form.watch('objectCacheMethod');

  React.useEffect(() => {
    form.reset({
      ...initial,
      objectCacheGlobalGroups: toTextarea(initial.objectCacheGlobalGroups),
      objectCacheNoCacheGroups: toTextarea(initial.objectCacheNoCacheGroups),
    });
  }, [initial, form.reset]);

  async function handleSubmit(data: ObjectCacheFormData) {
    const payload = {
      ...data,
      objectCacheGlobalGroups: fromTextarea(data.objectCacheGlobalGroups as string),
      objectCacheNoCacheGroups: fromTextarea(data.objectCacheNoCacheGroups as string),
    };
    await onSubmit(payload);
  }

  const isRedis = objectCacheMethod === 'redis';
  const disabled = (field: keyof ObjectCacheSettings) => isSaving || initial.wpConfigOverrides?.[field];

  return (
    <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
      <div className="lg:col-span-3">
        <Form {...form}>
          <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-6">
            {/* UPDATED: All FormField names are now the full camelCase version */}
            <FormField control={form.control} name="objectCacheEnabled" render={({ field }) => (
              <FormItem className="flex items-center justify-between rounded-lg border p-4">
                <div className="space-y-0.5"><FormLabel className="text-base">Enable Object Cache</FormLabel><CardDescription>Turn on persistent object caching for your site.</CardDescription></div>
                <FormControl><Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled('objectCacheEnabled')} /></FormControl>
              </FormItem>
            )} />

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField control={form.control} name="objectCacheMethod" render={({ field }) => (
                <FormItem><FormLabel>Method</FormLabel><Select value={field.value} onValueChange={field.onChange} disabled={disabled('objectCacheMethod')}>
                  <FormControl><SelectTrigger><SelectValue /></SelectTrigger></FormControl>
                  <SelectContent><SelectItem value="redis">Redis</SelectItem><SelectItem value="memcached">Memcached</SelectItem></SelectContent>
                </Select><FormMessage />
                </FormItem>
              )} />
              {/* NEW: Field for selecting the Redis client */}
              {isRedis && (
                <FormField control={form.control} name="objectCacheClient" render={({ field }) => (
                  <FormItem><FormLabel>Redis Client</FormLabel><Select value={field.value} onValueChange={field.onChange} disabled={disabled('objectCacheClient')}>
                    <FormControl><SelectTrigger><SelectValue /></SelectTrigger></FormControl>
                    <SelectContent>
                      <SelectItem value="phpredis">PhpRedis</SelectItem>
                      <SelectItem value="predis">Predis</SelectItem>
                      <SelectItem value="credis">Credis</SelectItem>
                    </SelectContent>
                  </Select><FormMessage />
                  </FormItem>
                )} />
              )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField control={form.control} name="objectCacheHost" render={({ field }) => (
                <FormItem><FormLabel>Host</FormLabel><FormControl><Input {...field} disabled={disabled('objectCacheHost')} placeholder="127.0.0.1 or tls://..." className="bg-white text-black dark:bg-gray-900 dark:text-white" /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="objectCachePort" render={({ field }) => (
                <FormItem><FormLabel>Port</FormLabel>
                  <FormControl><Input {...field} type="text" disabled={disabled('objectCachePort')} inputMode="numeric" pattern="[0-9]*" value={field.value ?? ''} className="bg-white text-black dark:bg-gray-900 dark:text-white" /></FormControl>
                  <FormMessage /></FormItem>
              )} />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField control={form.control} name="objectCacheUsername" render={({ field }) => (
                <FormItem><FormLabel>Username</FormLabel><FormControl><Input {...field} disabled={disabled('objectCacheUsername')} autoComplete="off" className="bg-white text-black dark:bg-gray-900 dark:text-white" /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="objectCachePassword" render={({ field }) => (
                <FormItem><FormLabel>Password</FormLabel><FormControl><Input {...field} disabled={disabled('objectCachePassword')} type="password" autoComplete="new-password" className="bg-white text-black dark:bg-gray-900 dark:text-white" /></FormControl><FormMessage /></FormItem>
              )} />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* NEW: Field for Redis Database */}
              {isRedis && (
                <FormField control={form.control} name="objectCacheDatabase" render={({ field }) => (
                  <FormItem><FormLabel>Database</FormLabel>
                    <FormControl><Input {...field} type="text" disabled={disabled('objectCacheDatabase')} inputMode="numeric" pattern="[0-9]*" value={field.value ?? ''} className="bg-white text-black dark:bg-gray-900 dark:text-white" /></FormControl>
                    <FormMessage /></FormItem>
                )} />
              )}
              {/* NEW: Field for Connection Timeout */}
              <FormField control={form.control} name="objectCacheTimeout" render={({ field }) => (
                <FormItem><FormLabel>Timeout (seconds)</FormLabel>
                  <FormControl><Input {...field} type="text" disabled={disabled('objectCacheTimeout')} inputMode="decimal" value={field.value ?? ''} className="bg-white text-black dark:bg-gray-900 dark:text-white" /></FormControl>
                  <FormMessage /></FormItem>
              )} />
            </div>

            <FormField control={form.control} name="objectCacheLifetime" render={({ field }) => (
              <FormItem><FormLabel>Default Object Lifetime (seconds)</FormLabel>
                <FormControl><Input {...field} type="text" disabled={disabled('objectCacheLifetime')} inputMode="numeric" pattern="[0-9]*" value={field.value ?? ''} className="bg-white text-black dark:bg-gray-900 dark:text-white" /></FormControl>
                <FormMessage /></FormItem>
            )} />

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <FormField control={form.control} name="objectCacheGlobalGroups" render={({ field }) => (
                <FormItem><FormLabel>Global Groups</FormLabel><FormControl><Textarea {...field} rows={8} placeholder={DEFAULT_GLOBAL_GROUPS.join("\n")}
                  className="bg-white text-black dark:bg-gray-900 dark:text-white" /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="objectCacheNoCacheGroups" render={({ field }) => (
                <FormItem><FormLabel>Do Not Cache Groups</FormLabel><FormControl><Textarea {...field} rows={8} placeholder={DEFAULT_NO_CACHE_GROUPS.join("\n")}
                  className="bg-white text-black dark:bg-gray-900 dark:text-white" /></FormControl><FormMessage /></FormItem>
              )} />
            </div>

            <FormField control={form.control} name="objectCachePersistentConnection" render={({ field }) => (
              <FormItem className="flex flex-row items-center justify-between rounded-lg border p-4">
                <div className="space-y-0.5"><FormLabel className="text-base">Persistent Connection</FormLabel><CardDescription>Reduces latency by reusing connections. Recommended if supported.</CardDescription></div>
                <FormControl><Switch checked={!!field.value} onCheckedChange={field.onChange} disabled={disabled('objectCachePersistentConnection')} /></FormControl>
              </FormItem>
            )} />

            <div className="flex justify-end pt-4">
              <Button type="submit" disabled={isSaving}>{isSaving ? "Saving..." : "Save Changes"}</Button>
            </div>
          </form>
        </Form>
      </div>

      <div className="lg:col-span-1">
        <StatusPanel status={initial.liveStatus} capabilities={initial.serverCapabilities} />
      </div>
    </div>
  )
}