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

// Helper function to check if a string is a valid absolute or protocol-relative URL.
// Accepts http, https, and protocol-relative URLs (//).
const isValidUrl = (url: string) => {
  if (typeof url !== "string" || !url.trim()) return false;

  // Allow protocol-relative URLs
  if (url.startsWith("//")) {
    url = "http:" + url;
  }

  // Explicitly require '//' after protocol for absolute URLs
  if (
    (url.startsWith("http:") || url.startsWith("https:")) &&
    !url.startsWith("http://") &&
    !url.startsWith("https://")
  ) {
    return false;
  }

  try {
    const parsed = new URL(url);

    // Only allow http(s) protocols
    if (!["http:", "https:"].includes(parsed.protocol)) return false;

    // Hostname must be present and contain at least one dot (not localhost)
    if (
      !parsed.hostname ||
      !/^[a-zA-Z0-9.-]+$/.test(parsed.hostname) ||
      !parsed.hostname.includes(".")
    ) {
      return false;
    }

    // Disallow spaces or control chars anywhere
    if (/\s/.test(url)) return false;

    // Disallow fragments
    if (parsed.hash) return false;

    // Disallow empty host or protocol
    if (!parsed.protocol || !parsed.hostname) return false;

    return true;
  } catch {
    return false;
  }
};

// The schema is updated to attach the validation error to the parent field.
const htmlSchema = z.object({
  minify: z.boolean(),
  dnsPrefetch: z
    .array(z.string())
    .optional()
    .superRefine((urls, ctx) => {
      if (urls) {
        for (const url of urls) {
          // We only validate non-empty lines.
          if (url && !isValidUrl(url)) {
            ctx.addIssue({
              code: z.ZodIssueCode.custom,
              message: "One or more entries is not a valid URL.",
              // By OMITTING the `path` property, the error is attached
              // to `dnsPrefetch` itself, which <FormMessage /> can read.
            });
            // We only need one error to invalidate the whole field, so we stop.
            break;
          }
        }
      }
    }),
  dnsPreconnect: z
    .array(z.string())
    .optional()
    .superRefine((urls, ctx) => {
      if (urls) {
        for (const url of urls) {
          if (url && !isValidUrl(url)) {
            ctx.addIssue({
              code: z.ZodIssueCode.custom,
              message: "One or more entries is not a valid URL.",
            });
            break;
          }
        }
      }
    }),
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

  const handleTextareaChange = (
    e: React.ChangeEvent<HTMLTextAreaElement>,
    field: any // The field object from react-hook-form's render prop
  ) => {
    field.onChange(e.target.value.split("\n"));
  };

  return (
    <Form {...form}>
      <form className="space-y-6" onSubmit={form.handleSubmit(onSubmit)}>
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
            <FormItem>
              <FormLabel>DNS Prefetch</FormLabel>
              <FormControl>
                <Textarea
                  id="dns-prefetch"
                  placeholder={
                    "https://fonts.googleapis.com\n//cdn.example.com"
                  }
                  rows={3}
                  value={
                    Array.isArray(field.value) ? field.value.join("\n") : ""
                  }
                  onChange={(e) => handleTextareaChange(e, field)}
                  disabled={isSaving}
                  className="font-mono text-sm"
                />
              </FormControl>
              <FormDescription>
                Enter one full URL per line (e.g., https://example.com or
                //example.com).
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="dnsPreconnect"
          render={({ field }) => (
            <FormItem>
              <FormLabel>DNS Preconnect</FormLabel>
              <FormControl>
                <Textarea
                  id="dns-preconnect"
                  placeholder={"https://fonts.gstatic.com\n//cdn.example.com"}
                  rows={3}
                  value={
                    Array.isArray(field.value) ? field.value.join("\n") : ""
                  }
                  onChange={(e) => handleTextareaChange(e, field)}
                  disabled={isSaving}
                  className="font-mono text-sm"
                />
              </FormControl>
              <FormDescription>
                Enter one full URL per line. Use for critical, third-party
                domains.
              </FormDescription>
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
