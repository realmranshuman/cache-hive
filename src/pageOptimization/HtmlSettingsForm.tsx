import * as React from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
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
  FormDescription,
} from "@/components/ui/form";

const htmlSchema = z.object({
  minify: z.boolean(),
  dnsPrefetch: z.array(z.string()).optional(),
  dnsPreconnect: z.array(z.string()).optional(),
  autoDnsPrefetch: z.boolean(),
  googleFontsAsync: z.boolean(),
  keepComments: z.boolean(),
  removeEmoji: z.boolean(),
  removeNoscript: z.boolean(),
});

export type HtmlFormData = z.infer<typeof htmlSchema>;

interface HtmlSettingsFormProps {
  initial: HtmlFormData;
  onSubmit: (data: HtmlFormData) => Promise<void>;
  isSaving: boolean;
}

export function HtmlSettingsForm({
  initial,
  onSubmit,
  isSaving,
}: HtmlSettingsFormProps) {
  const form = useForm<HtmlFormData>({
    resolver: zodResolver(htmlSchema),
    // THE FIX: Use `values` to make the form a controlled component.
    values: {
      minify: initial.minify ?? false,
      dnsPrefetch: initial.dnsPrefetch ?? [],
      dnsPreconnect: initial.dnsPreconnect ?? [],
      autoDnsPrefetch: initial.autoDnsPrefetch ?? false,
      googleFontsAsync: initial.googleFontsAsync ?? false,
      keepComments: initial.keepComments ?? false,
      removeEmoji: initial.removeEmoji ?? false,
      removeNoscript: initial.removeNoscript ?? false,
    },
  });

  return (
    <Form {...form}>
      <form className="space-y-4" onSubmit={form.handleSubmit(onSubmit)}>
        {/* ... form fields remain the same ... */}
        <FormField
          control={form.control}
          name="minify"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Minify HTML</FormLabel>
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
          name="dnsPrefetch"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>DNS Prefetch</FormLabel>
              <FormControl>
                <Textarea
                  id="dns-prefetch"
                  placeholder={"//fonts.googleapis.com\n//cdn.example.com"}
                  rows={2}
                  value={
                    Array.isArray(field.value) ? field.value.join("\n") : ""
                  }
                  onChange={(e) => field.onChange(e.target.value.split("\n"))}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="dnsPreconnect"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>DNS Preconnect</FormLabel>
              <FormControl>
                <Textarea
                  id="dns-preconnect"
                  placeholder={"//fonts.gstatic.com\n//cdn.example.com"}
                  rows={2}
                  value={
                    Array.isArray(field.value) ? field.value.join("\n") : ""
                  }
                  onChange={(e) => field.onChange(e.target.value.split("\n"))}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="autoDnsPrefetch"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <div className="space-y-0.5">
                <FormLabel>Automatic DNS Prefetching</FormLabel>
                <FormDescription>
                  Automatically add DNS prefetch for all external domains.
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
          name="googleFontsAsync"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Load Google Fonts Asynchronously</FormLabel>
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
          name="keepComments"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Keep HTML Comments</FormLabel>
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
          name="removeEmoji"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Remove WordPress Emoji Scripts</FormLabel>
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
          name="removeNoscript"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Remove noscript Tags</FormLabel>
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
        <div className="flex justify-end">
          <Button type="submit" disabled={isSaving}>
            {isSaving ? "Saving..." : "Save Changes"}
          </Button>
        </div>
      </form>
    </Form>
  );
}
