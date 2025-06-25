import * as React from "react"
import { zodResolver } from "@hookform/resolvers/zod"
import { useForm } from "react-hook-form"
import { z } from "zod"
import { Button } from "@/components/ui/button"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"

const htmlSchema = z.object({
  minify: z.boolean(),
  dnsPrefetch: z.string().optional(),
  dnsPreconnect: z.string().optional(),
  autoDnsPrefetch: z.boolean(),
  googleFontsAsync: z.boolean(),
  keepComments: z.boolean(),
  removeEmoji: z.boolean(),
  removeNoscript: z.boolean()
})

type HtmlFormData = z.infer<typeof htmlSchema>

export function HtmlSettingsForm({ initial, onSubmit }: { initial: HtmlFormData, onSubmit: (data: HtmlFormData) => void }) {
  const form = useForm<HtmlFormData>({
    resolver: zodResolver(htmlSchema),
    defaultValues: initial,
  })

  function handleSubmit(data: HtmlFormData) {
    onSubmit(data)
  }

  return (
    <Form {...form}>
      <form className="space-y-4" onSubmit={form.handleSubmit(handleSubmit)}>
        <FormField
          control={form.control}
          name="minify"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Minify HTML</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="dnsPrefetch"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>DNS Prefetch</FormLabel>
              <FormControl>
                <Textarea id="dns-prefetch" placeholder={"//fonts.googleapis.com\n//cdn.example.com"} rows={2} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="dnsPreconnect"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>DNS Preconnect</FormLabel>
              <FormControl>
                <Textarea id="dns-preconnect" placeholder={"//fonts.gstatic.com\n//cdn.example.com"} rows={2} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="autoDnsPrefetch"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <div>
                <FormLabel>Automatic DNS Prefetching</FormLabel>
                <p className="text-xs text-gray-500">Automatically add DNS prefetch for all external domains found in HTML output.</p>
              </div>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="googleFontsAsync"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Load Google Fonts Asynchronously</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="keepComments"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Keep HTML Comments</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="removeEmoji"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Remove WordPress Emoji</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="removeNoscript"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Remove noscript tag</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="flex justify-end">
          <Button type="submit">Save Settings</Button>
        </div>
      </form>
    </Form>
  )
}
