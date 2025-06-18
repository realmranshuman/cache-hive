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
  browserCache: z.boolean(),
  browserCacheTTL: z.string().optional(),
})

type BrowserCacheFormData = z.infer<typeof browserCacheSchema>

export function BrowserCacheTabForm({ initial, onSubmit }: { initial: BrowserCacheFormData, onSubmit: (data: BrowserCacheFormData) => void }) {
  const form = useForm<BrowserCacheFormData>({
    resolver: zodResolver(browserCacheSchema),
    defaultValues: {
      browserCache: initial.browserCache ?? false,
      browserCacheTTL: initial.browserCacheTTL ?? "",
    },
  })

  function handleSubmit(data: BrowserCacheFormData) {
    onSubmit(data)
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="browserCache"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Browser Cache</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
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
                <Input {...field} id="browser-cache-ttl" placeholder="31536000" />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="flex justify-end">
          <Button type="submit">Save Changes</Button>
        </div>
      </form>
    </Form>
  )
}
