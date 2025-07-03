import * as React from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";

const mediaSchema = z.object({
  lazyloadImages: z.boolean(),
  lazyloadIframes: z.boolean(),
  imageExcludes: z.array(z.string()).optional(),
  iframeExcludes: z.array(z.string()).optional(),
  addMissingSizes: z.boolean(),
  responsivePlaceholder: z.boolean(),
  optimizeUploads: z.boolean(),
  optimizationQuality: z.coerce.number().min(1).max(100),
  autoResizeUploads: z.boolean(),
  resizeWidth: z.coerce.number().optional(),
  resizeHeight: z.coerce.number().optional(),
});

export type MediaFormData = z.infer<typeof mediaSchema>;

interface MediaSettingsFormProps {
  initial: MediaFormData;
  onSubmit: (data: MediaFormData) => Promise<void>;
  isSaving: boolean;
}

export function MediaSettingsForm({
  initial,
  onSubmit,
  isSaving,
}: MediaSettingsFormProps) {
  const form = useForm<MediaFormData>({
    resolver: zodResolver(mediaSchema),
    // THE FIX: Use `values` to make the form a controlled component.
    values: {
      lazyloadImages: initial.lazyloadImages ?? false,
      lazyloadIframes: initial.lazyloadIframes ?? false,
      imageExcludes: initial.imageExcludes ?? [],
      iframeExcludes: initial.iframeExcludes ?? [],
      addMissingSizes: initial.addMissingSizes ?? false,
      responsivePlaceholder: initial.responsivePlaceholder ?? false,
      optimizeUploads: initial.optimizeUploads ?? false,
      optimizationQuality: initial.optimizationQuality ?? 82,
      autoResizeUploads: initial.autoResizeUploads ?? false,
      resizeWidth: initial.resizeWidth ?? undefined,
      resizeHeight: initial.resizeHeight ?? undefined,
    },
  });

  return (
    <Form {...form}>
      <form className="space-y-4" onSubmit={form.handleSubmit(onSubmit)}>
        <Alert>
          <AlertDescription>
            If you are using any other plugins to optimize and/or deliver
            images, then do not enable image related settings here. We recommend
            using{" "}
            <a
              href="https://wordpress.org/plugins/ewww-image-optimizer/"
              className="underline text-blue-600"
              target="_blank"
              rel="noopener noreferrer"
            >
              EWWW Image Optimizer
            </a>
            .
          </AlertDescription>
        </Alert>

        <FormField
          control={form.control}
          name="lazyloadImages"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Lazyload Images</FormLabel>
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
          name="imageExcludes"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Lazyload Image Excludes</FormLabel>
              <FormControl>
                <Textarea
                  placeholder="Enter one image filename or path per line to exclude from lazyload."
                  rows={3}
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
          name="lazyloadIframes"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Lazyload iframes</FormLabel>
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
          name="iframeExcludes"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Lazyload Iframe Excludes</FormLabel>
              <FormControl>
                <Textarea
                  placeholder="Enter one iframe src or keyword per line to exclude from lazyload."
                  rows={3}
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
          name="addMissingSizes"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Add Missing Image Sizes</FormLabel>
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
          name="responsivePlaceholder"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Responsive Image Placeholder</FormLabel>
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
