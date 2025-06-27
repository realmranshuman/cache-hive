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

// Schema now expects an array of strings from the start. No transformation needed.
const cacheSchema = z.object({
  enableCache: z.boolean(),
  cacheLoggedUsers: z.boolean(),
  cacheCommenters: z.boolean(),
  cacheRestApi: z.boolean(),
  cacheMobile: z.boolean(),
  mobileUserAgents: z.array(z.string()).optional(),
});

export type CacheFormData = z.infer<typeof cacheSchema>;

interface CacheTabFormProps {
  initial: Partial<CacheFormData>;
  onSubmit: (data: CacheFormData) => Promise<void>;
  isSaving: boolean;
}

export function CacheTabForm({ initial, onSubmit, isSaving }: CacheTabFormProps) {
  // The form is now typed with the final data shape.
  const form = useForm<CacheFormData>({
    resolver: zodResolver(cacheSchema),
    // `initial` data is already in the correct format from the API.
    defaultValues: {
      enableCache: initial.enableCache ?? false,
      cacheLoggedUsers: initial.cacheLoggedUsers ?? false,
      cacheCommenters: initial.cacheCommenters ?? false,
      cacheRestApi: initial.cacheRestApi ?? false,
      cacheMobile: initial.cacheMobile ?? false,
      mobileUserAgents: initial.mobileUserAgents ?? [],
    },
  });

  const cacheMobileValue = useWatch({
    control: form.control,
    name: "cacheMobile",
  });

  // useEffect now correctly resets the form with the API data structure.
  React.useEffect(() => {
    form.reset(initial);
  }, [initial, form]);

  return (
    <Form {...form}>
      {/* The `handleSubmit` call is now simple and type-safe. */}
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        {/* ... other FormFields are unchanged ... */}
        <FormField
          control={form.control}
          name="enableCache"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Enable Full-Page Caching</FormLabel>
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
              <FormLabel>Cache for Logged-in Users</FormLabel>
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
              <FormLabel>Cache for Commenters</FormLabel>
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
              <FormLabel>Cache REST API Requests</FormLabel>
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
              <FormLabel>Cache for Mobile Devices</FormLabel>
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
        {cacheMobileValue && (
          <FormField
            control={form.control}
            name="mobileUserAgents"
            render={({ field }) => (
              <FormItem className="space-y-2">
                <FormLabel>Custom Mobile User Agent List</FormLabel>
                <FormControl>
                  <Textarea
                    placeholder={`Enter one user agent per line:\nMobile\nAndroid\niPhone\niPad`}
                    rows={4}
                    // SOLID FIX: Transform the array to a string for display.
                    value={Array.isArray(field.value) ? field.value.join('\n') : ''}
                    // SOLID FIX: Transform the string back to an array on change.
                    onChange={(e) => field.onChange(e.target.value.split('\n'))}
                    disabled={isSaving}
                    className="bg-white text-black dark:bg-gray-900 dark:text-white"
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
