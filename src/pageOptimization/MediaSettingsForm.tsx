import * as React from "react"
import { zodResolver } from "@hookform/resolvers/zod"
import { useForm } from "react-hook-form"
import { z } from "zod"
import { Button } from "@/components/ui/button"
import { Switch } from "@/components/ui/switch"
import { Input } from "@/components/ui/input"
import { Alert, AlertDescription } from "@/components/ui/alert"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"

const mediaSchema = z.object({
  lazyloadImages: z.boolean(),
  addMissingSizes: z.boolean(),
  responsivePlaceholder: z.boolean(),
  lazyloadIframes: z.boolean(),
  optimizeUploads: z.boolean(),
  optimizationQuality: z.string(),
  autoResizeUploads: z.boolean(),
  resizeWidth: z.string().optional(),
  resizeHeight: z.string().optional()
})

type MediaFormData = z.infer<typeof mediaSchema>

export function MediaSettingsForm({ initial, onSubmit }: { initial: MediaFormData, onSubmit: (data: MediaFormData) => void }) {
  const form = useForm<MediaFormData>({
    resolver: zodResolver(mediaSchema),
    defaultValues: initial,
  })

  function handleSubmit(data: MediaFormData) {
    onSubmit(data)
  }

  const autoResizeUploads = form.watch("autoResizeUploads")

  return (
    <Form {...form}>
      <form className="space-y-4" onSubmit={form.handleSubmit(handleSubmit)}>
        <Alert>
          <AlertDescription>
            If you are using any other plugins to optimze and/or deliver images, then do not enable image related settings here. We recommend using <a href="https://wordpress.org/plugins/ewww-image-optimizer/" className="underline text-blue-600" target="_blank" rel="noopener noreferrer">EWWW Image Optimizer</a>.
          </AlertDescription>
        </Alert>
        <FormField
          control={form.control}
          name="lazyloadImages"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Lazyload Images</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="addMissingSizes"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Add Missing Sizes</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="responsivePlaceholder"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Responsive Image Placeholder</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="lazyloadIframes"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Lazyload iframes</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="optimizeUploads"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Optimize Image Uploads</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="optimizationQuality"
          render={({ field }) => (
            <FormItem className="flex items-center gap-4">
              <FormLabel htmlFor="optimization-quality" className="mb-0">Optimization Quality</FormLabel>
              <FormControl>
                <Input {...field} id="optimization-quality" type="number" min="1" max="100" placeholder="82" className="w-24" />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="autoResizeUploads"
          render={({ field }) => (
            <FormItem className="flex items-center gap-4">
              <FormLabel htmlFor="auto-resize-uploads">Automatically Resize Uploads</FormLabel>
              <FormControl>
                <Switch id="auto-resize-uploads" checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        {autoResizeUploads && (
          <div className="pl-6">
            <div className="bg-muted rounded-md p-4 border">
              <div className="flex gap-6">
                <FormField
                  control={form.control}
                  name="resizeWidth"
                  render={({ field }) => (
                    <FormItem className="space-y-2 flex flex-col items-start">
                      <FormLabel htmlFor="resize-width">Width</FormLabel>
                      <FormControl>
                        <Input {...field} id="resize-width" type="number" placeholder="Width" className="w-24" />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="resizeHeight"
                  render={({ field }) => (
                    <FormItem className="space-y-2 flex flex-col items-start">
                      <FormLabel htmlFor="resize-height">Height</FormLabel>
                      <FormControl>
                        <Input {...field} id="resize-height" type="number" placeholder="Height" className="w-24" />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
            </div>
          </div>
        )}
        <div className="flex justify-end">
          <Button type="submit">Save Settings</Button>
        </div>
      </form>
    </Form>
  )
}
