import * as React from "react";
// Import useEffect and useState
import { useEffect, useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
// No longer need wrapPromise here
import { getRoles, getExclusionsSettings, ExclusionsFormData } from "../api";
import { ExclusionsRolesSkeleton } from "@/components/skeletons/exclusions-roles-skeleton";
import { ExclusionsSettingsSkeleton } from "@/components/skeletons/exclusions-settings-skeleton";
import { NetworkAlert } from "@/components/ui/network-alert";

const exclusionsSchema = z.object({
  exclude_uris: z
    .array(z.string())
    .optional()
    .transform((val) => val?.filter(Boolean)),
  exclude_query_strings: z
    .array(z.string())
    .optional()
    .transform((val) => val?.filter(Boolean)),
  exclude_cookies: z
    .array(z.string())
    .optional()
    .transform((val) => val?.filter(Boolean)),
  exclude_roles: z.array(z.string()).optional(),
  is_network_admin: z.boolean().optional(),
});

interface ExclusionsTabFormProps {
  initial: Partial<ExclusionsFormData>; // This will be empty now
  onSubmit: (data: Partial<ExclusionsFormData>) => Promise<void>;
  isSaving: boolean;
}

function ExclusionsRolesField({
  roles,
  form,
  isSaving,
}: {
  roles: { id: string; name: string }[];
  form: ReturnType<typeof useForm<z.infer<typeof exclusionsSchema>>>;
  isSaving: boolean;
}) {
  return (
    <FormField
      control={form.control}
      name="exclude_roles"
      render={() => (
        <FormItem className="space-y-3">
          <FormLabel className="text-base font-medium">
            User Roles to Exclude from Caching
          </FormLabel>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {roles.map((role) => (
              <FormField
                key={role.id}
                control={form.control}
                name="exclude_roles"
                render={({ field }) => (
                  <FormItem className="flex items-center space-x-2 p-2 border rounded-md hover:bg-accent hover:text-accent-foreground">
                    <FormControl>
                      <Checkbox
                        id={`role-${role.id}`}
                        checked={field.value?.includes(role.id) || false}
                        onCheckedChange={(checked) => {
                          const currentValue = field.value || [];
                          return checked
                            ? field.onChange([...currentValue, role.id])
                            : field.onChange(
                                currentValue.filter((r) => r !== role.id)
                              );
                        }}
                        disabled={isSaving}
                      />
                    </FormControl>
                    <FormLabel
                      htmlFor={`role-${role.id}`}
                      className="text-sm font-normal cursor-pointer flex-grow"
                    >
                      {role.name}
                    </FormLabel>
                  </FormItem>
                )}
              />
            ))}
          </div>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

export function ExclusionsTabForm({
  onSubmit,
  isSaving,
}: ExclusionsTabFormProps) {
  // State for holding the fetched settings and roles
  const [initialData, setInitialData] =
    useState<Partial<ExclusionsFormData> | null>(null);
  const [roles, setRoles] = useState<{ id: string; name: string }[] | null>(
    null
  );

  const form = useForm<z.infer<typeof exclusionsSchema>>({
    resolver: zodResolver(exclusionsSchema),
    defaultValues: {
      exclude_uris: [],
      exclude_query_strings: [],
      exclude_cookies: [],
      exclude_roles: [],
    },
  });

  // This useEffect hook runs only ONCE when the component mounts
  useEffect(() => {
    // Fetch both settings and roles when the tab becomes visible
    Promise.all([getExclusionsSettings(), getRoles()])
      .then(([settingsData, rolesData]) => {
        setInitialData(settingsData);
        setRoles(rolesData);
        // Once data is fetched, reset the form with the correct values
        form.reset({
          exclude_uris: settingsData.exclude_uris ?? [],
          exclude_query_strings: settingsData.exclude_query_strings ?? [],
          exclude_cookies: settingsData.exclude_cookies ?? [],
          exclude_roles: settingsData.exclude_roles ?? [],
        });
      })
      .catch((error) => {
        console.error("Failed to load exclusions data:", error);
        // Handle error state if needed
      });
  }, [form.reset]); // form.reset is stable, so this still runs only once

  const handleTextareaChange = (
    e: React.ChangeEvent<HTMLTextAreaElement>,
    field: any
  ) => {
    field.onChange(e.target.value.split("\n"));
  };

  // While data is loading, show a skeleton screen
  if (!initialData || !roles) {
    return <ExclusionsSettingsSkeleton />;
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        <NetworkAlert isNetworkAdmin={initialData.is_network_admin} />

        <FormField
          control={form.control}
          name="exclude_uris"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>URIs to Exclude from Caching</FormLabel>
              <FormControl>
                <Textarea
                  placeholder={`Enter one URI pattern per line:\n/wp-admin/\n/my-account/.*\n/cart/`}
                  rows={4}
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
          name="exclude_query_strings"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Query Strings to Exclude from Caching</FormLabel>
              <FormControl>
                <Textarea
                  placeholder={`Enter one query string key per line:\npreview\nedit\n_ga`}
                  rows={4}
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
          name="exclude_cookies"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Cookies to Exclude from Caching</FormLabel>
              <FormControl>
                <Textarea
                  placeholder={`Enter one cookie name (or partial name) per line:\nwordpress_logged_in\ncomment_author_`}
                  rows={4}
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
        {/* The roles field will now only render after roles have been fetched */}
        <ExclusionsRolesField roles={roles} form={form} isSaving={isSaving} />
        <div className="flex justify-end">
          <Button type="submit" disabled={isSaving}>
            {isSaving
              ? "Saving..."
              : initialData.is_network_admin
              ? "Save Network Settings"
              : "Save Site Settings"}
          </Button>
        </div>
      </form>
    </Form>
  );
}
