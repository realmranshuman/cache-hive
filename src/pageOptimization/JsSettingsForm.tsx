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
  minify: z.boolean(),
  combine: z.boolean(),
  combineExternalInline: z.boolean(),
  deferMode: z.string(),
  excludes: z.array(z.string()).optional(),
  deferExcludes: z.array(z.string()).optional(),
});

export type JsFormData = z.infer<typeof jsSchema>;

interface JsSettingsFormProps {
  initial: JsFormData;
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
    // THE FIX: Use `values` to make the form a controlled component.
    values: {
      minify: initial.minify ?? false,
      combine: initial.combine ?? false,
      combineExternalInline: initial.combineExternalInline ?? false,
      deferMode: initial.deferMode ?? "off",
      excludes: initial.excludes ?? [],
      deferExcludes: initial.deferExcludes ?? [],
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
          name="combine"
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
          name="combineExternalInline"
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
          name="deferMode"
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
          name="excludes"
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
          name="deferExcludes"
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
                  onChange={(e) => field.onChange(e.target.value.split("\n"))}
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
