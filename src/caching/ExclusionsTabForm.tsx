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
import { Suspense } from "react";
import { wrapPromise } from "@/utils/wrapPromise";
import { getRoles } from "../api";
import { ExclusionsRolesSkeleton } from "@/components/skeletons/exclusions-roles-skeleton";

// Schemas expect arrays from the start.
const exclusionsSchema = z.object({
  excludeUris: z.array(z.string()).optional(),
  excludeQueryStrings: z.array(z.string()).optional(),
  excludeCookies: z.array(z.string()).optional(),
  excludeRoles: z.array(z.string()).optional(),
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
      name="excludeRoles"
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
                name="excludeRoles"
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
                            : field.onChange(currentValue.filter((r) => r !== role.id));
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
      excludeUris: initial.excludeUris ?? [],
      excludeQueryStrings: initial.excludeQueryStrings ?? [],
      excludeCookies: initial.excludeCookies ?? [],
      excludeRoles: initial.excludeRoles ?? [],
    },
  });

  React.useEffect(() => {
    form.reset(initial);
  }, [initial, form]);

  const handleSubmit = (data: ExclusionsFormData) => {
    const payload = {
      ...data,
      excludeRoles: data.excludeRoles || [],
    };
    return onSubmit(payload);
  };

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="excludeUris"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>URIs to Exclude from Caching</FormLabel>
              <FormControl>
                <Textarea
                  id="exclude-uris"
                  placeholder={`Enter one URI pattern per line:\n/wp-admin/\n/my-account/.*\n/cart/\n/checkout/`}
                  rows={4}
                  value={Array.isArray(field.value) ? field.value.join('\n') : ''}
                  onChange={(e) => field.onChange(e.target.value.split('\n'))}
                  disabled={isSaving}
                  className="bg-white text-black dark:bg-gray-900 dark:text-white"
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="excludeQueryStrings"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Query Strings to Exclude from Caching</FormLabel>
              <FormControl>
                <Textarea
                  id="exclude-query-strings"
                  placeholder={`Enter one query string key per line:\npreview\nedit\n_ga\nfbclid`}
                  rows={4}
                  value={Array.isArray(field.value) ? field.value.join('\n') : ''}
                  onChange={(e) => field.onChange(e.target.value.split('\n'))}
                  disabled={isSaving}
                  className="bg-white text-black dark:bg-gray-900 dark:text-white"
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="excludeCookies"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Cookies to Exclude from Caching</FormLabel>
              <FormControl>
                <Textarea
                  id="exclude-cookies"
                  placeholder={`Enter one cookie name (or partial name) per line:\nwordpress_logged_in\ncomment_author_\nwoocommerce_cart_\nwp-postpass_`}
                  rows={4}
                  value={Array.isArray(field.value) ? field.value.join('\n') : ''}
                  onChange={(e) => field.onChange(e.target.value.split('\n'))}
                  disabled={isSaving}
                  className="bg-white text-black dark:bg-gray-900 dark:text-white"
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <Suspense fallback={<ExclusionsRolesSkeleton />}>
          <ExclusionsRolesField
            roles={rolesResource.read()}
            form={form}
            isSaving={isSaving}
          />
        </Suspense>
        <div className="flex justify-end">
          <Button type="submit" disabled={isSaving}>
            {isSaving ? "Saving..." : "Save Changes"}
          </Button>
        </div>
      </form>
    </Form>
  );
}