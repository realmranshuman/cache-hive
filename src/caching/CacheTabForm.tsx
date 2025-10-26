import * as React from "@wordpress/element";
import type { ChangeEvent } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { NetworkAlert } from "@/components/ui/network-alert";
import { CacheFormData } from "@/api/cache";
import {
  HoverCard,
  HoverCardContent,
  HoverCardTrigger,
} from "@/components/ui/hover-card";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Info } from "lucide-react";

const cacheSchema = z.object({
  enable_cache: z.boolean(),
  cache_logged_users: z.boolean(),
  cache_commenters: z.boolean(),
  cache_rest_api: z.boolean(),
  cache_mobile: z.boolean(),
  mobile_user_agents: z
    .array(z.string())
    .optional()
    .transform((val) => val?.filter(Boolean)),
  is_network_admin: z.boolean().optional(),
  is_apache_like: z.boolean().optional(),
  is_logged_in_cache_override_set: z.boolean().optional(),
});

interface CacheTabFormProps {
  initial: Partial<CacheFormData>;
  onSubmit: (data: Partial<CacheFormData>) => Promise<void>;
  isSaving: boolean;
}

export function CacheTabForm({
  initial,
  onSubmit,
  isSaving,
}: CacheTabFormProps) {
  const form = useForm<z.infer<typeof cacheSchema>>({
    resolver: zodResolver(cacheSchema),
    defaultValues: {
      enable_cache: initial.enable_cache ?? false,
      cache_logged_users: initial.cache_logged_users ?? false,
      cache_commenters: initial.cache_commenters ?? false,
      cache_rest_api: initial.cache_rest_api ?? false,
      cache_mobile: initial.cache_mobile ?? false,
      mobile_user_agents: initial.mobile_user_agents ?? [],
    },
  });

  React.useEffect(() => {
    form.reset({
      enable_cache: initial.enable_cache ?? false,
      cache_logged_users: initial.cache_logged_users ?? false,
      cache_commenters: initial.cache_commenters ?? false,
      cache_rest_api: initial.cache_rest_api ?? false,
      cache_mobile: initial.cache_mobile ?? false,
      mobile_user_agents: initial.mobile_user_agents ?? [],
    });
  }, [initial, form.reset]);

  const handleTextareaChange = (
    e: ChangeEvent<HTMLTextAreaElement>,
    field: any
  ) => {
    field.onChange(e.target.value.split("\n"));
  };

  const cacheMobileValue = useWatch({
    control: form.control,
    name: "cache_mobile",
  });

  // This is the key security logic for the UI.
  // The switch should be disabled if the server is NOT Apache/Litespeed AND the user has NOT set the override constant.
  const is_logged_in_cache_disabled =
    initial.is_apache_like === false &&
    initial.is_logged_in_cache_override_set === false;

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        <NetworkAlert isNetworkAdmin={initial.is_network_admin} />

        <FormField
          control={form.control}
          name="enable_cache"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <div className="space-y-0.5">
                <FormLabel>Enable Full-Page Caching</FormLabel>
                <FormDescription>
                  Master switch to enable or disable page caching.
                </FormDescription>
              </div>
              <FormControl>
                <Switch
                  checked={field.value}
                  onCheckedChange={field.onChange}
                  disabled={isSaving}
                />
              </FormControl>
            </FormItem>
          )}
        />

        {!initial.is_apache_like && (
          <Alert variant="default">
            <Info className="h-4 w-4" />
            <AlertTitle>Nginx Server Detected</AlertTitle>
            <AlertDescription>
              To use features like Logged-in Caching or Server-Side Image
              Delivery, you must include the generated{" "}
              <code className="text-xs">cache-hive-nginx.conf</code> file in
              your server's configuration and reload Nginx. The file has been
              created in your WordPress root directory.
            </AlertDescription>
          </Alert>
        )}

        <FormField
          control={form.control}
          name="cache_logged_users"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <div className="space-y-0.5">
                <FormLabel>Cache for Logged-in Users</FormLabel>
                <FormDescription>
                  Generate separate cache files for each logged-in user.
                </FormDescription>
              </div>
              <HoverCard>
                <HoverCardTrigger asChild>
                  {/* The div wrapper is necessary for the trigger to work when the switch is disabled */}
                  <div>
                    <FormControl>
                      <Switch
                        checked={field.value}
                        onCheckedChange={field.onChange}
                        disabled={isSaving || is_logged_in_cache_disabled}
                      />
                    </FormControl>
                  </div>
                </HoverCardTrigger>
                {is_logged_in_cache_disabled && (
                  <HoverCardContent align="end" className="w-80">
                    <p className="text-sm">
                      This feature is disabled on Nginx for security. Private
                      user cache must be protected from public access.
                    </p>
                    <p className="mt-2 text-sm">
                      To enable, first add the generated Nginx rules to your
                      server, then define the following constant in your{" "}
                      <strong>wp-config.php</strong> file and reload this page:
                    </p>
                    <pre className="mt-2 w-full rounded-sm bg-muted p-2 whitespace-pre-wrap">
                      <code className="block text-xs break-words">
                        define('CACHE_HIVE_ALLOW_LOGGED_IN_CACHE_ON_NGINX',
                        true);
                      </code>
                    </pre>
                  </HoverCardContent>
                )}
              </HoverCard>
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="cache_commenters"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <div className="space-y-0.5">
                <FormLabel>Cache for Commenters</FormLabel>
                <FormDescription>
                  Serve cached pages to users who have left a comment.
                </FormDescription>
              </div>
              <FormControl>
                <Switch
                  checked={field.value}
                  onCheckedChange={field.onChange}
                  disabled={isSaving}
                />
              </FormControl>
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="cache_rest_api"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <div className="space-y-0.5">
                <FormLabel>Cache REST API Requests</FormLabel>
                <FormDescription>
                  Cache GET requests made to the WordPress REST API.
                </FormDescription>
              </div>
              <FormControl>
                <Switch
                  checked={field.value}
                  onCheckedChange={field.onChange}
                  disabled={isSaving}
                />
              </FormControl>
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="cache_mobile"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <div className="space-y-0.5">
                <FormLabel>Cache for Mobile Devices</FormLabel>
                <FormDescription>
                  Create separate cache files for mobile visitors.
                </FormDescription>
              </div>
              <FormControl>
                <Switch
                  checked={field.value}
                  onCheckedChange={field.onChange}
                  disabled={isSaving}
                />
              </FormControl>
            </FormItem>
          )}
        />
        {cacheMobileValue && (
          <FormField
            control={form.control}
            name="mobile_user_agents"
            render={({ field }) => (
              <FormItem className="space-y-2">
                <FormLabel>Custom Mobile User Agent List</FormLabel>
                <FormControl>
                  <Textarea
                    placeholder={`Enter one user agent per line:\nMobile\nAndroid\niPhone`}
                    rows={4}
                    value={
                      Array.isArray(field.value) ? field.value.join("\n") : ""
                    }
                    onChange={(e) => handleTextareaChange(e, field)}
                    disabled={isSaving}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        )}
        <div className="flex justify-end">
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
  );
}
