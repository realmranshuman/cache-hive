import * as React from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Checkbox } from "@/components/ui/checkbox";
import { Textarea } from "@/components/ui/textarea";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";

const autoPurgeSchema = z.object({
  auto_purge_entire_site: z.boolean().optional(),
  auto_purge_front_page: z.boolean(),
  auto_purge_home_page: z.boolean(),
  auto_purge_pages: z.boolean(),
  auto_purge_author_archive: z.boolean(),
  auto_purge_post_type_archive: z.boolean(),
  auto_purge_yearly_archive: z.boolean(),
  auto_purge_monthly_archive: z.boolean(),
  auto_purge_daily_archive: z.boolean(),
  auto_purge_term_archive: z.boolean(),
  purge_on_upgrade: z.boolean().optional(),
  serve_stale: z.boolean().optional(),
  custom_purge_hooks: z
    .array(z.string())
    .optional()
    .transform((val) => val?.filter(Boolean)),
});

export type AutoPurgeFormData = z.infer<typeof autoPurgeSchema>;

interface AutoPurgeTabFormProps {
  initial: Partial<AutoPurgeFormData>;
  onSubmit: (data: AutoPurgeFormData) => Promise<void>;
  isSaving: boolean;
}

const formatLabel = (key: string) => {
  const result = key.replace("auto_purge_", "").replace(/_/g, " ");
  return result.charAt(0).toUpperCase() + result.slice(1);
};

export function AutoPurgeTabForm({
  initial,
  onSubmit,
  isSaving,
}: AutoPurgeTabFormProps) {
  const form = useForm<AutoPurgeFormData>({
    resolver: zodResolver(autoPurgeSchema),
    defaultValues: initial,
  });

  React.useEffect(() => {
    form.reset(initial);
  }, [initial, form.reset]);

  const autoPurgeRuleKeys = [
    "auto_purge_entire_site",
    "auto_purge_front_page",
    "auto_purge_home_page",
    "auto_purge_pages",
    "auto_purge_author_archive",
    "auto_purge_post_type_archive",
    "auto_purge_yearly_archive",
    "auto_purge_monthly_archive",
    "auto_purge_daily_archive",
    "auto_purge_term_archive",
  ] as const;

  const handleTextareaChange = (
    e: React.ChangeEvent<HTMLTextAreaElement>,
    field: any
  ) => {
    field.onChange(e.target.value.split("\n"));
  };

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="purge_on_upgrade"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>
                Purge All Cache on Plugin/Theme/Core Upgrade
              </FormLabel>
              <FormControl>
                <Switch
                  checked={field.value ?? false}
                  onCheckedChange={field.onChange}
                  disabled={isSaving}
                />
              </FormControl>
            </FormItem>
          )}
        />
        <div className="space-y-3 pt-4">
          <span className="text-base font-medium block border-b pb-2 mb-3">
            Auto Purge Rules for Publish/Update Actions
          </span>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {autoPurgeRuleKeys.map((key) => (
              <FormField
                key={key}
                control={form.control}
                name={key}
                render={({ field }) => (
                  <FormItem className="flex items-center space-x-2 p-2 border rounded-md hover:bg-accent hover:text-accent-foreground">
                    <FormControl>
                      <Checkbox
                        id={key}
                        checked={field.value}
                        onCheckedChange={field.onChange}
                        disabled={isSaving}
                      />
                    </FormControl>
                    <FormLabel
                      htmlFor={key}
                      className="text-sm font-normal cursor-pointer flex-grow"
                    >
                      {formatLabel(
                        key.replace("entire_site", "Entire Site Cache")
                      )}
                    </FormLabel>
                  </FormItem>
                )}
              />
            ))}
          </div>
        </div>
        <FormField
          control={form.control}
          name="custom_purge_hooks"
          render={({ field }) => (
            <FormItem className="pt-4">
              <FormLabel>Custom Purge Hooks</FormLabel>
              <FormControl>
                <Textarea
                  className="w-full min-h-[80px] font-mono text-xs"
                  placeholder="Enter one hook per line (e.g. switch_theme)"
                  value={
                    Array.isArray(field.value) ? field.value.join("\n") : ""
                  }
                  onChange={(e) => handleTextareaChange(e, field)}
                  disabled={isSaving}
                />
              </FormControl>
              <div className="text-xs text-muted-foreground mt-1">
                When any of these hooks fire, the entire cache will be purged.
              </div>
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="serve_stale"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between pt-4">
              <FormLabel>Serve Stale Cache While Regenerating</FormLabel>
              <FormControl>
                <Switch
                  checked={field.value ?? false}
                  onCheckedChange={field.onChange}
                  disabled={isSaving}
                />
              </FormControl>
            </FormItem>
          )}
        />
        <div className="flex justify-end pt-4">
          <Button type="submit" disabled={isSaving}>
            {isSaving ? "Saving..." : "Save Changes"}
          </Button>
        </div>
      </form>
    </Form>
  );
}
