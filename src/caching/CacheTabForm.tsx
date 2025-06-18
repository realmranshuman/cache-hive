import * as React from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form"; // Import useWatch
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

// Schema field names MUST match the keys in your AllCacheSettings and PHP defaults
const cacheSchema = z.object({
  enableCache: z.boolean(),
  cacheLoggedUsers: z.boolean(),
  cacheCommenters: z.boolean(),
  cacheRestApi: z.boolean(),
  cacheMobile: z.boolean(),
  mobileUserAgents: z.string().optional(),
});

export type CacheFormData = z.infer<typeof cacheSchema>;

interface CacheTabFormProps {
  initial: Partial<CacheFormData>; // Allow partial for initial load before all settings are fetched
  onSubmit: (data: CacheFormData) => Promise<void>; // Make it async
  isSaving: boolean;
}

export function CacheTabForm({
  initial,
  onSubmit,
  isSaving,
}: CacheTabFormProps) {
  const form = useForm<CacheFormData>({
    resolver: zodResolver(cacheSchema),
    defaultValues: {
      enableCache: initial.enableCache ?? false,
      cacheLoggedUsers: initial.cacheLoggedUsers ?? false,
      cacheCommenters: initial.cacheCommenters ?? false,
      cacheRestApi: initial.cacheRestApi ?? false,
      cacheMobile: initial.cacheMobile ?? false,
      mobileUserAgents: initial.mobileUserAgents ?? "",
    },
  });

  // Watch the cacheMobile field to conditionally show user agents
  const cacheMobileValue = useWatch({
    control: form.control,
    name: "cacheMobile",
  });

  React.useEffect(() => {
    form.reset(initial); // Reset form with fetched initial values
  }, [initial, form.reset]);

  async function handleSubmit(data: CacheFormData) {
    await onSubmit(data);
    // Optionally, reset the form if the backend returns the full updated settings
    // and your parent component updates the `initial` prop.
    // form.reset(data); // Or form.reset(updatedSettingsFromParent)
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="enableCache"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Enable Full-Page Caching</FormLabel>{" "}
              {/* Updated Label */}
              <FormControl>
                <Switch
                  checked={field.value}
                  onCheckedChange={field.onChange}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="cacheLoggedUsers"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Cache for Logged-in Users</FormLabel>{" "}
              {/* Updated Label */}
              <FormControl>
                <Switch
                  checked={field.value}
                  onCheckedChange={field.onChange}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="cacheCommenters"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Cache for Commenters</FormLabel> {/* Updated Label */}
              <FormControl>
                <Switch
                  checked={field.value}
                  onCheckedChange={field.onChange}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="cacheRestApi"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Cache REST API Requests</FormLabel>{" "}
              {/* Updated Label */}
              <FormControl>
                <Switch
                  checked={field.value}
                  onCheckedChange={field.onChange}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="cacheMobile"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Cache for Mobile Devices</FormLabel>{" "}
              {/* Updated Label */}
              <FormControl>
                <Switch
                  checked={field.value}
                  onCheckedChange={field.onChange}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        {/* Conditionally render mobileUserAgents based on cacheMobileValue */}
        {cacheMobileValue && (
          <FormField
            control={form.control}
            name="mobileUserAgents"
            render={({ field }) => (
              <FormItem className="space-y-2">
                <FormLabel>Custom Mobile User Agent List</FormLabel>
                <FormControl>
                  <Textarea
                    placeholder={`Enter one user agent per line:
Mobile
Android
iPhone
iPad`}
                    rows={4}
                    {...field}
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
            {isSaving ? "Saving..." : "Save Changes"}
          </Button>
        </div>
      </form>
    </Form>
  );
}
