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
  media_lazyload_images: z.boolean(),
  media_lazyload_iframes: z.boolean(),
  media_image_excludes: z
    .array(z.string())
    .optional()
    .transform((val) => val?.filter(Boolean)),
  media_iframe_excludes: z
    .array(z.string())
    .optional()
    .transform((val) => val?.filter(Boolean)),
  media_add_missing_sizes: z.boolean(),
  media_responsive_placeholder: z.boolean(),
  // Removed: media_optimize_uploads, media_optimization_quality, media_auto_resize_uploads, media_resize_width, media_resize_height
});

export type MediaFormData = z.infer<typeof mediaSchema>;

interface MediaSettingsFormProps {
  initial: Partial<MediaFormData>;
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
    // CORRECT: Use `defaultValues` to initialize the form.
    defaultValues: {
      media_lazyload_images: initial.media_lazyload_images ?? false,
      media_lazyload_iframes: initial.media_lazyload_iframes ?? false,
      media_image_excludes: initial.media_image_excludes ?? [],
      media_iframe_excludes: initial.media_iframe_excludes ?? [],
      media_add_missing_sizes: initial.media_add_missing_sizes ?? false,
      media_responsive_placeholder:
        initial.media_responsive_placeholder ?? false,
  // Removed: media_optimize_uploads, media_optimization_quality, media_auto_resize_uploads, media_resize_width, media_resize_height
    },
  });

  // CORRECT: Use `useEffect` to reset the form when the `initial` prop changes.
  React.useEffect(() => {
    form.reset({
      media_lazyload_images: initial.media_lazyload_images ?? false,
      media_lazyload_iframes: initial.media_lazyload_iframes ?? false,
      media_image_excludes: initial.media_image_excludes ?? [],
      media_iframe_excludes: initial.media_iframe_excludes ?? [],
      media_add_missing_sizes: initial.media_add_missing_sizes ?? false,
      media_responsive_placeholder:
        initial.media_responsive_placeholder ?? false,
      // Removed: media_optimize_uploads, media_optimization_quality, media_auto_resize_uploads, media_resize_width, media_resize_height
    });
  }, [initial, form.reset]);

  const handleTextareaChange = (
    e: React.ChangeEvent<HTMLTextAreaElement>,
    field: any
  ) => {
    field.onChange(e.target.value.split("\n"));
  };

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
          name="media_lazyload_images"
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
          name="media_image_excludes"
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
                  onChange={(e) => handleTextareaChange(e, field)}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="media_lazyload_iframes"
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
          name="media_iframe_excludes"
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
                  onChange={(e) => handleTextareaChange(e, field)}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="media_add_missing_sizes"
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
          name="media_responsive_placeholder"
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
        {/* Removed: Optimize Newly Uploaded Images, Image Optimization Quality, Auto-resize Newly Uploaded Images, Resize Max Width, Resize Max Height */}
        <div className="flex justify-end">
          <Button type="submit" disabled={isSaving}>
            {isSaving ? "Saving..." : "Save Changes"}
          </Button>
        </div>
      </form>
    </Form>
  );
}
