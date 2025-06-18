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

const MIN_TTL = 300; // 5 minutes
const MAX_TTL = 63072000; // 2 years

const ttlSchema = z.object({
  publicCacheTTL: z
    .number()
    .min(MIN_TTL, `Must be at least ${MIN_TTL} seconds`)
    .max(MAX_TTL, `Must be less than ${MAX_TTL} seconds`),
  privateCacheTTL: z
    .number()
    .min(MIN_TTL, `Must be at least ${MIN_TTL} seconds`)
    .max(MAX_TTL, `Must be less than ${MAX_TTL} seconds`),
  frontPageTTL: z
    .number()
    .min(MIN_TTL, `Must be at least ${MIN_TTL} seconds`)
    .max(MAX_TTL, `Must be less than ${MAX_TTL} seconds`),
  feedTTL: z
    .number()
    .min(MIN_TTL, `Must be at least ${MIN_TTL} seconds`)
    .max(MAX_TTL, `Must be less than ${MAX_TTL} seconds`),
  restTTL: z
    .number()
    .min(MIN_TTL, `Must be at least ${MIN_TTL} seconds`)
    .max(MAX_TTL, `Must be less than ${MAX_TTL} seconds`),
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
      publicCacheTTL: Number(initial.publicCacheTTL) || MIN_TTL,
      privateCacheTTL: Number(initial.privateCacheTTL) || MIN_TTL,
      frontPageTTL: Number(initial.frontPageTTL) || MIN_TTL,
      feedTTL: Number(initial.feedTTL) || MIN_TTL,
      restTTL: Number(initial.restTTL) || MIN_TTL,
    },
  });

  React.useEffect(() => {
    form.reset({
      publicCacheTTL: Number(initial.publicCacheTTL) || MIN_TTL,
      privateCacheTTL: Number(initial.privateCacheTTL) || MIN_TTL,
      frontPageTTL: Number(initial.frontPageTTL) || MIN_TTL,
      feedTTL: Number(initial.feedTTL) || MIN_TTL,
      restTTL: Number(initial.restTTL) || MIN_TTL,
    });
  }, [initial, form.reset]);

  async function handleSubmit(data: TtlFormData) {
    await onSubmit(data);
  }

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit(handleSubmit)}
        className="grid grid-cols-1 md:grid-cols-2 gap-4"
      >
        <FormField
          control={form.control}
          name="publicCacheTTL"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default TTL for Public Cache (seconds)</FormLabel>
              <FormControl>
                <Input
                  {...field}
                  id="public-cache-ttl"
                  type="number"
                  min={MIN_TTL}
                  max={MAX_TTL}
                  disabled={isSaving}
                  onChange={(e) => field.onChange(Number(e.target.value))}
                />
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
                <Input
                  {...field}
                  id="private-cache-ttl"
                  type="number"
                  min={MIN_TTL}
                  max={MAX_TTL}
                  disabled={isSaving}
                  onChange={(e) => field.onChange(Number(e.target.value))}
                />
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
                <Input
                  {...field}
                  id="front-page-ttl"
                  type="number"
                  min={MIN_TTL}
                  max={MAX_TTL}
                  disabled={isSaving}
                  onChange={(e) => field.onChange(Number(e.target.value))}
                />
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
                <Input
                  {...field}
                  id="feed-ttl"
                  type="number"
                  min={MIN_TTL}
                  max={MAX_TTL}
                  disabled={isSaving}
                  onChange={(e) => field.onChange(Number(e.target.value))}
                />
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
                <Input
                  {...field}
                  id="rest-ttl"
                  type="number"
                  min={MIN_TTL}
                  max={MAX_TTL}
                  disabled={isSaving}
                  onChange={(e) => field.onChange(Number(e.target.value))}
                />
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
    </Form>
  );
}