import * as React from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";

// Schema field names MUST match the keys in your AllCacheSettings and PHP defaults
const autoPurgeSchema = z.object({
  // Auto Purge Rules
  autoPurgeAllPages: z.boolean(), // Renamed
  autoPurgeFrontPage: z.boolean(),
  autoPurgeHomePage: z.boolean(),
  autoPurgePages: z.boolean(),
  autoPurgeAuthorArchive: z.boolean(),
  autoPurgePostTypeArchive: z.boolean(),
  autoPurgeYearlyArchive: z.boolean(),
  autoPurgeMonthlyArchive: z.boolean(),
  autoPurgeDailyArchive: z.boolean(),
  autoPurgeTermArchive: z.boolean(),
  // Global Settings
  purgeOnUpgrade: z.boolean().optional(),
  serveStale: z.boolean().optional(), // Renamed
});

export type AutoPurgeFormData = z.infer<typeof autoPurgeSchema>;

interface AutoPurgeTabFormProps {
  initial: Partial<AutoPurgeFormData>;
  onSubmit: (data: AutoPurgeFormData) => Promise<void>;
  isSaving: boolean;
}

// Helper to generate labels from camelCase
const formatLabel = (key: string) => {
  const result = key.replace('autoPurge', '').replace(/([A-Z])/g, " $1");
  return result.charAt(0).toUpperCase() + result.slice(1).trim();
};

export function AutoPurgeTabForm({ initial, onSubmit, isSaving }: AutoPurgeTabFormProps) {
  const form = useForm<AutoPurgeFormData>({
    resolver: zodResolver(autoPurgeSchema),
    defaultValues: {
      autoPurgeAllPages: initial.autoPurgeAllPages ?? false,
      autoPurgeFrontPage: initial.autoPurgeFrontPage ?? false,
      autoPurgeHomePage: initial.autoPurgeHomePage ?? false,
      autoPurgePages: initial.autoPurgePages ?? false,
      autoPurgeAuthorArchive: initial.autoPurgeAuthorArchive ?? false,
      autoPurgePostTypeArchive: initial.autoPurgePostTypeArchive ?? false,
      autoPurgeYearlyArchive: initial.autoPurgeYearlyArchive ?? false,
      autoPurgeMonthlyArchive: initial.autoPurgeMonthlyArchive ?? false,
      autoPurgeDailyArchive: initial.autoPurgeDailyArchive ?? false,
      autoPurgeTermArchive: initial.autoPurgeTermArchive ?? false,
      purgeOnUpgrade: initial.purgeOnUpgrade ?? false,
      serveStale: initial.serveStale ?? false,
    },
  });

  React.useEffect(() => {
    form.reset(initial);
  }, [initial, form.reset]);

  async function handleSubmit(data: AutoPurgeFormData) {
    await onSubmit(data);
  }

  // Define which keys are part of the "Auto Purge Rules For Publish/Update" group
  const autoPurgeRuleKeys = [
    "autoPurgeAllPages", "autoPurgeFrontPage", "autoPurgeHomePage", "autoPurgePages",
    "autoPurgeAuthorArchive", "autoPurgePostTypeArchive", "autoPurgeYearlyArchive",
    "autoPurgeMonthlyArchive", "autoPurgeDailyArchive", "autoPurgeTermArchive"
  ] as const;


  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="purgeOnUpgrade"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Purge All Cache on Plugin/Theme/Core Upgrade</FormLabel> {/* Updated Label */}
              <FormControl>
                <Switch checked={field.value || false} onCheckedChange={field.onChange} disabled={isSaving}/>
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        
        <div className="space-y-3 pt-4">
          <span className="text-base font-medium block border-b pb-2 mb-3">Auto Purge Rules for Publish/Update Actions</span>
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
                        checked={field.value as boolean}
                        onCheckedChange={field.onChange}
                        disabled={isSaving}
                      />
                    </FormControl>
                    <FormLabel htmlFor={key} className="text-sm font-normal cursor-pointer flex-grow">
                      {formatLabel(key)} {/* Use helper for label */}
                    </FormLabel>
                    <FormMessage />
                  </FormItem>
                )}
              />
            ))}
          </div>
        </div>

        <FormField
          control={form.control}
          name="serveStale" // Corrected name
          render={({ field }) => (
            <FormItem className="flex items-center justify-between pt-4">
              <FormLabel>Serve Stale Cache While Regenerating</FormLabel> {/* Updated Label */}
              <FormControl>
                <Switch checked={field.value || false} onCheckedChange={field.onChange} disabled={isSaving} />
              </FormControl>
              <FormMessage />
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