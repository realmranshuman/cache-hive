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

const cssSchema = z.object({
  minify: z.boolean(),
  combine: z.boolean(),
  combineExternalInline: z.boolean(),
  fontOptimization: z.string(),
  excludes: z.string().optional()
})

type CssFormData = z.infer<typeof cssSchema>

export function CssSettingsForm({ initial, onSubmit }: { initial: CssFormData, onSubmit: (data: CssFormData) => void }) {
  const form = useForm<CssFormData>({
    resolver: zodResolver(cssSchema),
    defaultValues: initial,
  })

  function handleSubmit(data: CssFormData) {
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
              <FormLabel>Minify CSS</FormLabel>
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
              <FormLabel>Combine CSS</FormLabel>
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
              <FormLabel>Combine External And Inline CSS</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="fontOptimization"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Font Optimization</FormLabel>
              <FormControl>
                <Select value={field.value} onValueChange={field.onChange}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="default">Default</SelectItem>
                    <SelectItem value="swap">Swap</SelectItem>
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
              <FormLabel>CSS Minify/Combine Excludes</FormLabel>
              <FormControl>
                <Textarea id="css-excludes" placeholder={"/wp-content/plugins/example-plugin/\n/wp-content/themes/example-theme/"} rows={3} {...field} />
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
