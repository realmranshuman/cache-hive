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

const MIN_TTL = 300;
const MAX_TTL = 63072000;

const ttlSchema = z.object({
  public_cache_ttl: z.coerce.number().min(MIN_TTL).max(MAX_TTL),
  private_cache_ttl: z.coerce.number().min(MIN_TTL).max(MAX_TTL),
  front_page_ttl: z.coerce.number().min(MIN_TTL).max(MAX_TTL),
  feed_ttl: z.coerce.number().min(MIN_TTL).max(MAX_TTL),
  rest_ttl: z.coerce.number().min(MIN_TTL).max(MAX_TTL),
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
      public_cache_ttl: initial.public_cache_ttl || MIN_TTL,
      private_cache_ttl: initial.private_cache_ttl || MIN_TTL,
      front_page_ttl: initial.front_page_ttl || MIN_TTL,
      feed_ttl: initial.feed_ttl || MIN_TTL,
      rest_ttl: initial.rest_ttl || MIN_TTL,
    },
  });

  React.useEffect(() => {
    form.reset({
      public_cache_ttl: initial.public_cache_ttl || MIN_TTL,
      private_cache_ttl: initial.private_cache_ttl || MIN_TTL,
      front_page_ttl: initial.front_page_ttl || MIN_TTL,
      feed_ttl: initial.feed_ttl || MIN_TTL,
      rest_ttl: initial.rest_ttl || MIN_TTL,
    });
  }, [initial, form.reset]);

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit(onSubmit)}
        className="grid grid-cols-1 md:grid-cols-2 gap-4"
      >
        <FormField
          control={form.control}
          name="public_cache_ttl"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default TTL for Public Cache (seconds)</FormLabel>
              <FormControl>
                <Input
                  {...field}
                  type="number"
                  min={MIN_TTL}
                  max={MAX_TTL}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="private_cache_ttl"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default TTL for Private Cache (seconds)</FormLabel>
              <FormControl>
                <Input
                  {...field}
                  type="number"
                  min={MIN_TTL}
                  max={MAX_TTL}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="front_page_ttl"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default TTL for Front Page (seconds)</FormLabel>
              <FormControl>
                <Input
                  {...field}
                  type="number"
                  min={MIN_TTL}
                  max={MAX_TTL}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="feed_ttl"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default TTL for Feeds (seconds)</FormLabel>
              <FormControl>
                <Input
                  {...field}
                  type="number"
                  min={MIN_TTL}
                  max={MAX_TTL}
                  disabled={isSaving}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="rest_ttl"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default TTL for REST API (seconds)</FormLabel>
              <FormControl>
                <Input
                  {...field}
                  type="number"
                  min={MIN_TTL}
                  max={MAX_TTL}
                  disabled={isSaving}
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
