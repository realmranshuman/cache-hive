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

// Schema field names MUST match the keys in your AllCacheSettings and PHP defaults
const exclusionsSchema = z.object({
  excludeUris: z.string().optional(), // Renamed
  excludeQueryStrings: z.string().optional(), // Renamed
  excludeCookies: z.string().optional(), // Renamed
  excludeRoles: z.array(z.string()).optional(), // Renamed
});

export type ExclusionsFormData = z.infer<typeof exclusionsSchema>;

interface ExclusionsTabFormProps {
  initial: Partial<ExclusionsFormData>;
  onSubmit: (data: ExclusionsFormData) => Promise<void>;
  isSaving: boolean;
}

// Suspense resource for roles
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
                          if (checked) {
                            field.onChange([...currentValue, role.id]);
                          } else {
                            field.onChange(
                              currentValue.filter((r: string) => r !== role.id)
                            );
                          }
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
      excludeUris: initial.excludeUris ?? "",
      excludeQueryStrings: initial.excludeQueryStrings ?? "",
      excludeCookies: initial.excludeCookies ?? "",
      excludeRoles: initial.excludeRoles ?? [],
    },
  });

  React.useEffect(() => {
    // Reset form with possibly new initial values (e.g., after fetching)
    // Ensure that array fields are initialized correctly if `initial.excludeRoles` is undefined
    form.reset({
      excludeUris: initial.excludeUris || "",
      excludeQueryStrings: initial.excludeQueryStrings || "",
      excludeCookies: initial.excludeCookies || "",
      excludeRoles: initial.excludeRoles || [],
    });
  }, [initial, form.reset]);

  async function handleSubmit(data: ExclusionsFormData) {
    // Ensure excludeRoles is always an array, even if empty
    const payload = {
      ...data,
      excludeRoles: data.excludeRoles || [],
    };
    await onSubmit(payload);
  }

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
                  placeholder={`Enter one URI pattern per line:
/wp-admin/
/my-account/.*
/cart/
/checkout/`}
                  rows={4}
                  {...field}
                  disabled={isSaving}
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
                  placeholder={`Enter one query string key per line:
preview
edit
_ga
fbclid`}
                  rows={4}
                  {...field}
                  disabled={isSaving}
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
                  placeholder={`Enter one cookie name (or partial name) per line:
wordpress_logged_in
comment_author_
woocommerce_cart_
wp-postpass_`}
                  rows={4}
                  {...field}
                  disabled={isSaving}
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
