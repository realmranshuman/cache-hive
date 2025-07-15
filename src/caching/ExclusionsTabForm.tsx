import * as React from "react";
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
import { wrapPromise } from "@/utils/wrapPromise";
import { getRoles } from "../api";
import { ExclusionsRolesSkeleton } from "@/components/skeletons/exclusions-roles-skeleton";

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
});

export type ExclusionsFormData = z.infer<typeof exclusionsSchema>;

interface ExclusionsTabFormProps {
  initial: Partial<ExclusionsFormData>;
  onSubmit: (data: ExclusionsFormData) => Promise<void>;
  isSaving: boolean;
}

const rolesResource = wrapPromise(getRoles());

function ExclusionsRolesField({
  roles,
  form,
  isSaving,
}: {
  roles: { id: string; name: string }[];
  form: ReturnType<typeof useForm<ExclusionsFormData>>;
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
  initial,
  onSubmit,
  isSaving,
}: ExclusionsTabFormProps) {
  const form = useForm<ExclusionsFormData>({
    resolver: zodResolver(exclusionsSchema),
    defaultValues: {
      exclude_uris: initial.exclude_uris ?? [],
      exclude_query_strings: initial.exclude_query_strings ?? [],
      exclude_cookies: initial.exclude_cookies ?? [],
      exclude_roles: initial.exclude_roles ?? [],
    },
  });

  React.useEffect(() => {
    form.reset({
      exclude_uris: initial.exclude_uris ?? [],
      exclude_query_strings: initial.exclude_query_strings ?? [],
      exclude_cookies: initial.exclude_cookies ?? [],
      exclude_roles: initial.exclude_roles ?? [],
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
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
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
        <React.Suspense fallback={<ExclusionsRolesSkeleton />}>
          <ExclusionsRolesField
            roles={rolesResource.read()}
            form={form}
            isSaving={isSaving}
          />
        </React.Suspense>
        <div className="flex justify-end">
          <Button type="submit" disabled={isSaving}>
            {isSaving ? "Saving..." : "Save Changes"}
          </Button>
        </div>
      </form>
    </Form>
  );
}
