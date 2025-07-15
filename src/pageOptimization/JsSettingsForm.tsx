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

const jsSchema = z.object({
  js_minify: z.boolean(),
  js_combine: z.boolean(),
  js_combine_external_inline: z.boolean(),
  js_defer_mode: z.string(),
  js_excludes: z
    .array(z.string())
    .optional()
    .transform((val) => val?.filter(Boolean)),
  js_defer_excludes: z
    .array(z.string())
    .optional()
    .transform((val) => val?.filter(Boolean)),
});

export type JsFormData = z.infer<typeof jsSchema>;

interface JsSettingsFormProps {
  initial: Partial<JsFormData>;
  onSubmit: (data: JsFormData) => Promise<void>;
  isSaving: boolean;
}

export function JsSettingsForm({
  initial,
  onSubmit,
  isSaving,
}: JsSettingsFormProps) {
  const form = useForm<JsFormData>({
    resolver: zodResolver(jsSchema),
    // CORRECT: Use `defaultValues` to initialize the form.
    defaultValues: {
      js_minify: initial.js_minify ?? false,
      js_combine: initial.js_combine ?? false,
      js_combine_external_inline: initial.js_combine_external_inline ?? false,
      js_defer_mode: initial.js_defer_mode ?? "off",
      js_excludes: initial.js_excludes ?? [],
      js_defer_excludes: initial.js_defer_excludes ?? [],
    },
  });

  // CORRECT: Use `useEffect` to reset the form when the `initial` prop changes.
  React.useEffect(() => {
    form.reset({
      js_minify: initial.js_minify ?? false,
      js_combine: initial.js_combine ?? false,
      js_combine_external_inline: initial.js_combine_external_inline ?? false,
      js_defer_mode: initial.js_defer_mode ?? "off",
      js_excludes: initial.js_excludes ?? [],
      js_defer_excludes: initial.js_defer_excludes ?? [],
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
        <FormField
          control={form.control}
          name="js_minify"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Minify JS</FormLabel>
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
          name="js_combine"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Combine JS</FormLabel>
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
          name="js_combine_external_inline"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between rounded-lg border p-4">
              <FormLabel>Combine External And Inline JS</FormLabel>
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
          name="js_defer_mode"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>JS Deferred Loading</FormLabel>
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
                  <SelectItem value="off">Off</SelectItem>
                  <SelectItem value="deferred">Deferred</SelectItem>
                  <SelectItem value="delayed">Delayed</SelectItem>
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="js_excludes"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>JS Minify/Combine Excludes</FormLabel>
              <FormControl>
                <Textarea
                  id="js-excludes"
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
        <FormField
          control={form.control}
          name="js_defer_excludes"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>JS Deferred/Delayed Excludes</FormLabel>
              <FormControl>
                <Textarea
                  id="js-defer-excludes"
                  placeholder={"jquery.js\n/wp-content/plugins/another-plugin/"}
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
