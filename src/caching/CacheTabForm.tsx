import * as React from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { NetworkAlert } from "@/components/ui/network-alert";
import { CacheFormData } from "@/api/cache";

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

  const cacheMobileValue = useWatch({
    control: form.control,
    name: "cache_mobile",
  });

  const handleTextareaChange = (
    e: React.ChangeEvent<HTMLTextAreaElement>,
    field: any
  ) => {
    field.onChange(e.target.value.split("\n"));
  };

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        <NetworkAlert isNetworkAdmin={initial.is_network_admin} />

        <FormField
          control={form.control}
          name="enable_cache"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Enable Full-Page Caching</FormLabel>
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
          name="cache_logged_users"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Cache for Logged-in Users</FormLabel>
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
          name="cache_commenters"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Cache for Commenters</FormLabel>
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
              <FormLabel>Cache REST API Requests</FormLabel>
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
              <FormLabel>Cache for Mobile Devices</FormLabel>
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
