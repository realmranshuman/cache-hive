import * as React from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";

// Schema field names MUST match the keys in your AllCacheSettings and PHP defaults
const ttlSchema = z.object({
  publicCacheTTL: z.string().min(1, "Required").regex(/^\d+$/, "Must be a number"), // Add regex for numbers
  privateCacheTTL: z.string().min(1, "Required").regex(/^\d+$/, "Must be a number"),
  frontPageTTL: z.string().min(1, "Required").regex(/^\d+$/, "Must be a number"),
  feedTTL: z.string().min(1, "Required").regex(/^\d+$/, "Must be a number"),
  restTTL: z.string().min(1, "Required").regex(/^\d+$/, "Must be a number"),
});

export type TtlFormData = z.infer<typeof ttlSchema>;

interface TtlTabFormProps {
  initial: Partial<TtlFormData>;
  onSubmit: (data: TtlFormData) => Promise<void>;
  isSaving: boolean;
}

export function TtlTabForm({ initial, onSubmit, isSaving }: TtlTabFormProps) {
  const form = useForm<TtlFormData>({
    resolver: zodResolver(ttlSchema),
    defaultValues: {
      publicCacheTTL: initial.publicCacheTTL ?? "",
      privateCacheTTL: initial.privateCacheTTL ?? "",
      frontPageTTL: initial.frontPageTTL ?? "",
      feedTTL: initial.feedTTL ?? "",
      restTTL: initial.restTTL ?? "",
    },
  });

  React.useEffect(() => {
    form.reset(initial);
  }, [initial, form.reset]);

  async function handleSubmit(data: TtlFormData) {
    // Convert strings to numbers before sending if your backend expects numbers
    const numericData = {
        publicCacheTTL: parseInt(data.publicCacheTTL, 10),
        privateCacheTTL: parseInt(data.privateCacheTTL, 10),
        frontPageTTL: parseInt(data.frontPageTTL, 10),
        feedTTL: parseInt(data.feedTTL, 10),
        restTTL: parseInt(data.restTTL, 10),
    };
    await onSubmit(numericData as any); // Type assertion, ensure backend handles numbers
  }

  return (
    <Form {...form} children={
      <form onSubmit={form.handleSubmit(handleSubmit)} className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <FormField
          control={form.control}
          name="publicCacheTTL"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default TTL for Public Cache (seconds)</FormLabel>
              <FormControl>
                <Input {...field} id="public-cache-ttl" type="number" disabled={isSaving} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="privateCacheTTL"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default TTL for Private Cache (seconds)</FormLabel>
              <FormControl>
                <Input {...field} id="private-cache-ttl" type="number" disabled={isSaving} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="frontPageTTL"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default TTL for Front Page (seconds)</FormLabel>
              <FormControl>
                <Input {...field} id="front-page-ttl" type="number" disabled={isSaving} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="feedTTL"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default TTL for Feeds (seconds)</FormLabel>
              <FormControl>
                <Input {...field} id="feed-ttl" type="number" disabled={isSaving} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="restTTL"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default TTL for REST API (seconds)</FormLabel>
              <FormControl>
                <Input {...field} id="rest-ttl" type="number" disabled={isSaving} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="flex justify-end col-span-full">
           <Button type="submit" disabled={isSaving}>
            {isSaving ? "Saving..." : "Save Changes"}
          </Button>
        </div>
      </form>
    } />
  );
}