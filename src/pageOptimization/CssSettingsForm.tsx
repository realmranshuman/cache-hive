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
  minify: z.boolean(),
  combine: z.boolean(),
  combineExternalInline: z.boolean(),
  fontOptimization: z.string(),
  excludes: z.array(z.string()).optional(),
});

export type CssFormData = z.infer<typeof cssSchema>;

interface CssSettingsFormProps {
  initial: CssFormData;
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
    // THE FIX: Use `values` to make the form a controlled component.
    // It will now automatically update when the `initial` prop changes.
    values: {
      minify: initial.minify ?? false,
      combine: initial.combine ?? false,
      combineExternalInline: initial.combineExternalInline ?? false,
      fontOptimization: initial.fontOptimization ?? "default",
      excludes: initial.excludes ?? [],
    },
  });

  // The `useEffect` with `form.reset` is no longer needed because
  // the `values` prop keeps the form in sync.

  return (
    <Form {...form}>
      <form className="space-y-4" onSubmit={form.handleSubmit(onSubmit)}>
        <FormField
          control={form.control}
          name="minify"
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
        {/* ... other fields remain the same ... */}
        <FormField
          control={form.control}
          name="combine"
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
          name="combineExternalInline"
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
          name="fontOptimization"
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
          name="excludes"
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
