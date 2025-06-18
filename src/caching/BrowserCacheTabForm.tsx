import * as React from "react"
import { zodResolver } from "@hookform/resolvers/zod"
import { useForm } from "react-hook-form"
import { z } from "zod"
import { Button } from "@/components/ui/button"
import { Switch } from "@/components/ui/switch"
import { Input } from "@/components/ui/input"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"

const browserCacheSchema = z.object({
  browserCacheEnabled: z.boolean(),
  browserCacheTTL: z
    .preprocess((val) => {
      if (typeof val === "string") return Number(val);
      return val;
    },
      z
        .number()
        .min(14400, "Minimum 4 hours (14400 seconds)")
        .max(63072000, "Maximum 2 years (63072000 seconds)")
    ),
})

type BrowserCacheFormData = z.infer<typeof browserCacheSchema>

// Fix: Explicitly type the form as useForm<any> to avoid zodResolver type mismatch
export function BrowserCacheTabForm({ initial, onSubmit, isSaving }: { initial: any, onSubmit: (data: BrowserCacheFormData) => Promise<void>, isSaving: boolean }) {
  const normalizedInitial: BrowserCacheFormData = {
    browserCacheEnabled: Boolean(initial.browserCacheEnabled),
    browserCacheTTL: typeof initial.browserCacheTTL === 'number' ? initial.browserCacheTTL : Number(initial.browserCacheTTL) || 31536000,
  };

  const form = useForm({
    resolver: zodResolver(browserCacheSchema),
    defaultValues: normalizedInitial,
  });

  React.useEffect(() => {
    form.reset(normalizedInitial);
  }, [initial, form.reset]);

  async function handleSubmit(data: BrowserCacheFormData) {
    await onSubmit({
      ...data,
      browserCacheTTL: Number(data.browserCacheTTL),
    });
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="browserCacheEnabled"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Browser Cache</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} disabled={isSaving} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="browserCacheTTL"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Browser Cache TTL (seconds)</FormLabel>
              <FormControl>
                <Input
                  {...field}
                  id="browser-cache-ttl"
                  type="number"
                  min={14400}
                  max={63072000}
                  step={1}
                  placeholder="31536000"
                  disabled={isSaving}
                  value={typeof field.value === 'number' || typeof field.value === 'string' ? field.value : ''}
                  onChange={e => field.onChange(e.target.value === '' ? '' : Number(e.target.value))}
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
  )
}
