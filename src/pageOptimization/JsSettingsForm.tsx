import * as React from "react"
import { zodResolver } from "@hookform/resolvers/zod"
import { useForm } from "react-hook-form"
import { z } from "zod"
import { Button } from "@/components/ui/button"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"

const jsSchema = z.object({
  minify: z.boolean(),
  combine: z.boolean(),
  combineExternalInline: z.boolean(),
  deferMode: z.string(),
  excludes: z.string().optional(),
  deferExcludes: z.string().optional()
})

type JsFormData = z.infer<typeof jsSchema>

export function JsSettingsForm({ initial, onSubmit }: { initial: JsFormData, onSubmit: (data: JsFormData) => void }) {
  const form = useForm<JsFormData>({
    resolver: zodResolver(jsSchema),
    defaultValues: initial,
  })

  function handleSubmit(data: JsFormData) {
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
              <FormLabel>Minify JS</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="combine"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Combine JS</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="combineExternalInline"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Combine External And Inline JS</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="deferMode"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>JS Deferred Loading</FormLabel>
              <FormControl>
                <Select value={field.value} onValueChange={field.onChange}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="off">Off</SelectItem>
                    <SelectItem value="deferred">Deferred</SelectItem>
                    <SelectItem value="delayed">Delayed</SelectItem>
                  </SelectContent>
                </Select>
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="excludes"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>JS Minify/Combine Excludes</FormLabel>
              <FormControl>
                <Textarea id="js-excludes" placeholder={"/wp-content/plugins/example-plugin/\n/wp-content/themes/example-theme/"} rows={3} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="deferExcludes"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>JS Deferred/Delayed Excludes</FormLabel>
              <FormControl>
                <Textarea id="js-defer-excludes" placeholder={"/wp-content/plugins/example-plugin/\n/wp-content/themes/example-theme/"} rows={3} {...field} />
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
