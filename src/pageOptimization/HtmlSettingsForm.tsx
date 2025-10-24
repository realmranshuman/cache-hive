import * as React from "@wordpress/element";
import type { ChangeEvent } from "react";
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

const isValidUrl = (url: string) => {
  if (typeof url !== "string" || !url.trim()) return false;
  let effectiveUrl = url;
  if (effectiveUrl.startsWith("//")) {
    effectiveUrl = "http:" + effectiveUrl;
  }
  try {
    new URL(effectiveUrl);
    return true;
  } catch {
    return false;
  }
};

const htmlSchema = z.object({
  html_minify: z.boolean(),
  html_dns_prefetch: z
    .array(z.string().trim())
    .optional()
    .transform((val) => val?.filter(Boolean))
    .superRefine((urls, ctx) => {
      if (urls?.some((url) => url && !isValidUrl(url))) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "One or more prefetch entries is not a valid URL.",
        });
      }
    }),
  html_dns_preconnect: z
    .array(z.string().trim())
    .optional()
    .transform((val) => val?.filter(Boolean))
    .superRefine((urls, ctx) => {
      if (urls?.some((url) => url && !isValidUrl(url))) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "One or more preconnect entries is not a valid URL.",
        });
      }
    }),
  auto_dns_prefetch: z.boolean(),
  google_fonts_async: z.boolean(),
  html_keep_comments: z.boolean(),
  remove_emoji_scripts: z.boolean(),
  html_remove_noscript: z.boolean(),
});

export type HtmlFormData = z.infer<typeof htmlSchema>;

interface HtmlSettingsFormProps {
  initial: Partial<HtmlFormData>;
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
    // CORRECT: Use `defaultValues` to initialize the form.
    defaultValues: {
      html_minify: initial.html_minify ?? false,
      html_dns_prefetch: initial.html_dns_prefetch ?? [],
      html_dns_preconnect: initial.html_dns_preconnect ?? [],
      auto_dns_prefetch: initial.auto_dns_prefetch ?? false,
      google_fonts_async: initial.google_fonts_async ?? false,
      html_keep_comments: initial.html_keep_comments ?? false,
      remove_emoji_scripts: initial.remove_emoji_scripts ?? false,
      html_remove_noscript: initial.html_remove_noscript ?? false,
    },
  });

  // CORRECT: Use `useEffect` to reset the form when the `initial` prop changes.
  React.useEffect(() => {
    form.reset({
      html_minify: initial.html_minify ?? false,
      html_dns_prefetch: initial.html_dns_prefetch ?? [],
      html_dns_preconnect: initial.html_dns_preconnect ?? [],
      auto_dns_prefetch: initial.auto_dns_prefetch ?? false,
      google_fonts_async: initial.google_fonts_async ?? false,
      html_keep_comments: initial.html_keep_comments ?? false,
      remove_emoji_scripts: initial.remove_emoji_scripts ?? false,
      html_remove_noscript: initial.html_remove_noscript ?? false,
    });
  }, [initial, form.reset]);

  const handleTextareaChange = (
    e: ChangeEvent<HTMLTextAreaElement>,
    field: any
  ) => {
    field.onChange(e.target.value.split("\n"));
  };

  return (
    <Form {...form}>
      <form className="space-y-6" onSubmit={form.handleSubmit(onSubmit)}>
        <FormField
          control={form.control}
          name="html_minify"
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
          name="html_dns_prefetch"
          render={({ field }) => (
            <FormItem>
              <FormLabel>DNS Prefetch</FormLabel>
              <FormControl>
                <Textarea
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
          name="html_dns_preconnect"
          render={({ field }) => (
            <FormItem>
              <FormLabel>DNS Preconnect</FormLabel>
              <FormControl>
                <Textarea
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
          name="auto_dns_prefetch"
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
          name="google_fonts_async"
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
          name="html_keep_comments"
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
          name="remove_emoji_scripts"
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
          name="html_remove_noscript"
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
