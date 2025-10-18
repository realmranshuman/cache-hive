import * as React from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { ObjectCacheSettings } from "@/api/object-cache";
import {
  HoverCard,
  HoverCardContent,
  HoverCardTrigger,
} from "@/components/ui/hover-card";
import { NetworkAlert } from "@/components/ui/network-alert";

const objectCacheSchema = z.object({
  object_cache_enabled: z.boolean().default(false),
  object_cache_method: z.string().default("redis"),
  object_cache_client: z.string().optional(),
  object_cache_host: z.string().default("127.0.0.1"),
  object_cache_port: z.coerce.number().min(0).default(6379),
  object_cache_username: z.string().optional(),
  object_cache_password: z.string().optional(),
  object_cache_database: z.coerce.number().min(0).optional(),
  object_cache_timeout: z.coerce.number().min(0).optional(),
  object_cache_lifetime: z.coerce.number().min(1).default(3600),
  object_cache_global_groups: z.any(),
  object_cache_no_cache_groups: z.any(),
  object_cache_persistent_connection: z.boolean().optional().default(false),
});

type ObjectCacheFormData = z.infer<typeof objectCacheSchema>;

function StatusPanel({
  status,
  capabilities,
}: {
  status: ObjectCacheSettings["live_status"];
  capabilities: ObjectCacheSettings["server_capabilities"];
}) {
  const isConnected = status?.status === "Connected";
  const StatusItem = ({
    label,
    value,
    isBadge = false,
    variant = "secondary",
  }: any) => {
    if (value === undefined || value === null || value === "") return null;
    return (
      <div className="flex justify-between items-center text-sm py-1">
        <span className="text-muted-foreground">{label}</span>
        {isBadge ? (
          <Badge variant={variant}>{String(value)}</Badge>
        ) : (
          <span className="font-mono text-xs text-right">{String(value)}</span>
        )}
      </div>
    );
  };

  const bestClient = React.useMemo(() => {
    if (!capabilities?.clients) return "N/A";
    if (capabilities.clients.phpredis) return "PhpRedis";
    if (capabilities.clients.predis) return "Predis";
    if (capabilities.clients.credis) return "Credis";
    return "N/A";
  }, [capabilities]);

  return (
    <Card className="sticky top-6">
      <CardHeader>
        <CardTitle>Live Status</CardTitle>
        <CardDescription>
          Read-only information about the current connection.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-2">
        <StatusItem
          label="Status"
          value={status?.status ?? "Disabled"}
          isBadge
          variant={isConnected ? "default" : "destructive"}
        />
        <hr />
        <StatusItem label="Client In Use" value={status?.client} />
        <StatusItem label="Serializer" value={status?.serializer} />
        <StatusItem label="Compression" value={status?.compression} />
        <StatusItem
          label="Persistent"
          value={status?.persistent ? "Yes" : "No"}
        />
        <StatusItem label="Prefetch" value={status?.prefetch ? "Yes" : "No"} />
        <hr />
        <p className="text-sm font-medium text-muted-foreground pt-2">
          Server Capabilities
        </p>
        <StatusItem
          label="Best Redis Client"
          value={bestClient}
          isBadge
          variant="secondary"
        />
        <StatusItem
          label="PhpRedis"
          value={capabilities?.clients?.phpredis ? "Available" : "Not Found"}
        />
        <StatusItem
          label="Predis"
          value={capabilities?.clients?.predis ? "Available" : "Not Found"}
        />
        <StatusItem
          label="Credis"
          value={capabilities?.clients?.credis ? "Available" : "Not Found"}
        />
        <StatusItem
          label="Memcached"
          value={capabilities?.clients?.memcached ? "Available" : "Not Found"}
        />
      </CardContent>
    </Card>
  );
}

export function ObjectCacheTabForm({
  initial,
  onSubmit,
  isSaving,
}: {
  initial: ObjectCacheSettings;
  onSubmit: (data: Partial<ObjectCacheFormData>) => Promise<void>;
  isSaving: boolean;
}) {
  const toTextarea = (val?: string[]) =>
    Array.isArray(val) ? val.join("\n") : "";
  const fromTextarea = (val: string) =>
    val
      .split(/\r?\n/)
      .map((v) => v.trim())
      .filter(Boolean);

  const form = useForm<ObjectCacheFormData>({
    resolver: zodResolver(objectCacheSchema),
    defaultValues: {
      object_cache_enabled: initial.object_cache_enabled ?? false,
      object_cache_method: initial.object_cache_method ?? "redis",
      object_cache_client: initial.object_cache_client ?? "phpredis",
      object_cache_host: initial.object_cache_host ?? "127.0.0.1",
      object_cache_port: initial.object_cache_port ?? 6379,
      object_cache_username: initial.object_cache_username ?? "",
      object_cache_password: initial.object_cache_password ?? "",
      object_cache_database: initial.object_cache_database ?? 0,
      object_cache_timeout: initial.object_cache_timeout ?? 2.0,
      object_cache_lifetime: initial.object_cache_lifetime ?? 3600,
      object_cache_global_groups: toTextarea(
        initial.object_cache_global_groups
      ),
      object_cache_no_cache_groups: toTextarea(
        initial.object_cache_no_cache_groups
      ),
      object_cache_persistent_connection:
        initial.object_cache_persistent_connection ?? false,
    },
  });

  React.useEffect(() => {
    form.reset({
      object_cache_enabled: initial.object_cache_enabled ?? false,
      object_cache_method: initial.object_cache_method ?? "redis",
      object_cache_client: initial.object_cache_client ?? "phpredis",
      object_cache_host: initial.object_cache_host ?? "127.0.0.1",
      object_cache_port: initial.object_cache_port ?? 6379,
      object_cache_username: initial.object_cache_username ?? "",
      object_cache_password: initial.object_cache_password ?? "",
      object_cache_database: initial.object_cache_database ?? 0,
      object_cache_timeout: initial.object_cache_timeout ?? 2.0,
      object_cache_lifetime: initial.object_cache_lifetime ?? 3600,
      object_cache_global_groups: toTextarea(
        initial.object_cache_global_groups
      ),
      object_cache_no_cache_groups: toTextarea(
        initial.object_cache_no_cache_groups
      ),
      object_cache_persistent_connection:
        initial.object_cache_persistent_connection ?? false,
    });
  }, [initial, form.reset]);

  const objectCacheMethod = form.watch("object_cache_method");

  async function handleSubmit(data: ObjectCacheFormData) {
    const payload = {
      ...data,
      object_cache_global_groups: fromTextarea(
        data.object_cache_global_groups as string
      ),
      object_cache_no_cache_groups: fromTextarea(
        data.object_cache_no_cache_groups as string
      ),
    };
    await onSubmit(payload);
  }

  const isRedis = objectCacheMethod === "redis";
  const disabled = (field: keyof ObjectCacheSettings) =>
    isSaving || !!initial.wp_config_overrides?.[field];

  const withWpConfigHoverCard = (
    fieldKey: keyof ObjectCacheSettings,
    children: React.ReactNode
  ) => {
    if (disabled(fieldKey)) {
      return (
        <HoverCard>
          <HoverCardTrigger asChild>
            <div>{children}</div>
          </HoverCardTrigger>
          <HoverCardContent>
            <p>
              This setting is locked by a constant in your wp-config.php file.
            </p>
          </HoverCardContent>
        </HoverCard>
      );
    }
    return children;
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
      <div className="lg:col-span-3">
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(handleSubmit)}
            className="space-y-6"
          >
            <NetworkAlert isNetworkAdmin={initial.is_network_admin} />
            <FormField
              control={form.control}
              name="object_cache_enabled"
              render={({ field }) => (
                <FormItem className="flex items-center justify-between rounded-lg border p-4">
                  <div className="space-y-0.5">
                    <FormLabel className="text-base">
                      Enable Object Cache
                    </FormLabel>
                    <CardDescription>
                      Turn on persistent object caching.
                    </CardDescription>
                  </div>
                  {withWpConfigHoverCard(
                    "object_cache_enabled",
                    <FormControl>
                      <Switch
                        checked={field.value}
                        onCheckedChange={field.onChange}
                        disabled={disabled("object_cache_enabled")}
                      />
                    </FormControl>
                  )}
                </FormItem>
              )}
            />
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="object_cache_method"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Method</FormLabel>
                    {withWpConfigHoverCard(
                      "object_cache_method",
                      <Select
                        value={field.value}
                        onValueChange={field.onChange}
                        disabled={disabled("object_cache_method")}
                      >
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          <SelectItem value="redis">Redis</SelectItem>
                          {initial.server_capabilities?.clients.memcached && (
                            <SelectItem value="memcached">Memcached</SelectItem>
                          )}
                        </SelectContent>
                      </Select>
                    )}
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="object_cache_client"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Redis Client</FormLabel>
                    {withWpConfigHoverCard(
                      "object_cache_client",
                      <Select
                        value={field.value}
                        onValueChange={field.onChange}
                        disabled={!isRedis || disabled("object_cache_client")}
                      >
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {initial.server_capabilities?.clients.phpredis && (
                            <SelectItem value="phpredis">PhpRedis</SelectItem>
                          )}
                          {initial.server_capabilities?.clients.predis && (
                            <SelectItem value="predis">Predis</SelectItem>
                          )}
                          {initial.server_capabilities?.clients.credis && (
                            <SelectItem value="credis">Credis</SelectItem>
                          )}
                        </SelectContent>
                      </Select>
                    )}
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="object_cache_host"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Host</FormLabel>
                    {withWpConfigHoverCard(
                      "object_cache_host",
                      <FormControl>
                        <Input
                          {...field}
                          disabled={disabled("object_cache_host")}
                        />
                      </FormControl>
                    )}
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="object_cache_port"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Port</FormLabel>
                    {withWpConfigHoverCard(
                      "object_cache_port",
                      <FormControl>
                        <Input
                          {...field}
                          type="number"
                          disabled={disabled("object_cache_port")}
                        />
                      </FormControl>
                    )}
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="object_cache_username"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Username</FormLabel>
                    {withWpConfigHoverCard(
                      "object_cache_username",
                      <FormControl>
                        <Input
                          {...field}
                          disabled={disabled("object_cache_username")}
                          autoComplete="off"
                        />
                      </FormControl>
                    )}
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="object_cache_password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Password</FormLabel>
                    {withWpConfigHoverCard(
                      "object_cache_password",
                      <FormControl>
                        <Input
                          {...field}
                          disabled={disabled("object_cache_password")}
                          type="password"
                          autoComplete="new-password"
                        />
                      </FormControl>
                    )}
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="object_cache_database"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Database</FormLabel>
                    {withWpConfigHoverCard(
                      "object_cache_database",
                      <FormControl>
                        <Input
                          {...field}
                          type="number"
                          disabled={
                            !isRedis || disabled("object_cache_database")
                          }
                        />
                      </FormControl>
                    )}
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="object_cache_timeout"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Timeout (seconds)</FormLabel>
                    {withWpConfigHoverCard(
                      "object_cache_timeout",
                      <FormControl>
                        <Input
                          {...field}
                          type="number"
                          step="0.1"
                          disabled={disabled("object_cache_timeout")}
                        />
                      </FormControl>
                    )}
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
            <FormField
              control={form.control}
              name="object_cache_lifetime"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Default Object Lifetime (seconds)</FormLabel>
                  {withWpConfigHoverCard(
                    "object_cache_lifetime",
                    <FormControl>
                      <Input
                        {...field}
                        type="number"
                        disabled={disabled("object_cache_lifetime")}
                      />
                    </FormControl>
                  )}
                  <FormMessage />
                </FormItem>
              )}
            />
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <FormField
                control={form.control}
                name="object_cache_global_groups"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Global Groups</FormLabel>
                    <FormControl>
                      <Textarea {...field} rows={8} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="object_cache_no_cache_groups"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Do Not Cache Groups</FormLabel>
                    <FormControl>
                      <Textarea {...field} rows={8} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
            <FormField
              control={form.control}
              name="object_cache_persistent_connection"
              render={({ field }) => (
                <FormItem className="flex flex-row items-center justify-between rounded-lg border p-4">
                  <div className="space-y-0.5">
                    <FormLabel>Persistent Connection</FormLabel>
                    <CardDescription>
                      Reduces latency by reusing connections.
                    </CardDescription>
                  </div>
                  {withWpConfigHoverCard(
                    "object_cache_persistent_connection",
                    <FormControl>
                      <Switch
                        checked={field.value}
                        onCheckedChange={field.onChange}
                        disabled={disabled(
                          "object_cache_persistent_connection"
                        )}
                      />
                    </FormControl>
                  )}
                </FormItem>
              )}
            />
            <div className="flex justify-end pt-4">
              <Button type="submit" disabled={isSaving}>
                {isSaving
                  ? "Saving..."
                  : initial.is_network_admin
                  ? "Save Network Settings"
                  : "Save Site Settings"}
              </Button>
            </div>
          </form>
        </Form>
      </div>
      <div className="lg:col-span-1">
        <StatusPanel
          status={initial.live_status}
          capabilities={initial.server_capabilities}
        />
      </div>
    </div>
  );
}
