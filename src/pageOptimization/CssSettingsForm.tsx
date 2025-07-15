import * as React from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";

const cssSchema = z.object({
  css_minify: z.boolean(),
  css_combine: z.boolean(),
  css_combine_external_inline: z.boolean(),
  css_font_optimization: z.string(),
  css_excludes: z
    .array(z.string())
    .optional()
    .transform((val) => val?.filter(Boolean)), // Clean array before submission
});

export type CssFormData = z.infer<typeof cssSchema>;

interface CssSettingsFormProps {
  initial: Partial<CssFormData>;
  onSubmit: (data: CssFormData) => Promise<void>;
  isSaving: boolean;
}

export function CssSettingsForm({
  initial,
  onSubmit,
  isSaving,
}: CssSettingsFormProps) {
  const form = useForm<CssFormData>({
    resolver: zodResolver(cssSchema),
    // CORRECT: Use `defaultValues` to initialize the form.
    // This allows the form to have its own internal state for user input.
    defaultValues: {
      css_minify: initial.css_minify ?? false,
      css_combine: initial.css_combine ?? false,
      css_combine_external_inline: initial.css_combine_external_inline ?? false,
      css_font_optimization: initial.css_font_optimization ?? "default",
      css_excludes: initial.css_excludes ?? [],
    },
  });

  // CORRECT: Use `useEffect` to reset the form when the `initial` prop changes.
  // This happens after a successful save, syncing the form with the new data.
  React.useEffect(() => {
    form.reset({
      css_minify: initial.css_minify ?? false,
      css_combine: initial.css_combine ?? false,
      css_combine_external_inline: initial.css_combine_external_inline ?? false,
      css_font_optimization: initial.css_font_optimization ?? "default",
      css_excludes: initial.css_excludes ?? [],
    });
  }, [initial, form.reset]);

  const handleTextareaChange = (
    e: React.ChangeEvent<HTMLTextAreaElement>,
    field: any
  ) => {
    // Correctly update the form state with an array of strings
    field.onChange(e.target.value.split("\n"));
  };

  return (
    <Form {...form}>
      <form className="space-y-4" onSubmit={form.handleSubmit(onSubmit)}>
        <FormField
          control={form.control}
          name="css_minify"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Minify CSS</FormLabel>
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
          name="css_combine"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Combine CSS</FormLabel>
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
          name="css_combine_external_inline"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Combine External And Inline CSS</FormLabel>
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
          name="css_font_optimization"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Font Optimization</FormLabel>
              <Select
                value={field.value}
                onValueChange={field.onChange}
                disabled={isSaving}
              >
                <FormControl>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem value="default">Default</SelectItem>
                  <SelectItem value="swap">Swap</SelectItem>
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="css_excludes"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>CSS Minify/Combine Excludes</FormLabel>
              <FormControl>
                <Textarea
                  id="css-excludes"
                  placeholder={
                    "/wp-content/plugins/example-plugin/\n/wp-content/themes/example-theme/"
                  }
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
        <div className="flex justify-end">
          <Button type="submit" disabled={isSaving}>
            {isSaving ? "Saving..." : "Save Changes"}
          </Button>
        </div>
      </form>
    </Form>
  );
}
