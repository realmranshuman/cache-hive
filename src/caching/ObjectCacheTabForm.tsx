import * as React from "react"
import { zodResolver } from "@hookform/resolvers/zod"
import { useForm } from "react-hook-form"
import { z } from "zod"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Switch } from "@/components/ui/switch"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"

const objectCacheSchema = z.object({
  enabled: z.boolean(),
  method: z.string(),
  host: z.string(),
  port: z.string(),
  lifetime: z.string(),
  username: z.string().optional(),
  password: z.string().optional(),
  globalGroups: z.string().optional(),
  noCacheGroups: z.string().optional(),
  persistentConnection: z.boolean().optional(),
})

type ObjectCacheFormData = z.infer<typeof objectCacheSchema>

export function ObjectCacheTabForm({ initial, onSubmit, isSaving }: { initial: ObjectCacheFormData, onSubmit: (data: ObjectCacheFormData) => void, isSaving: boolean }) {
  const form = useForm<ObjectCacheFormData>({
    resolver: zodResolver(objectCacheSchema),
    defaultValues: {
      enabled: initial.enabled ?? false,
      method: initial.method ?? "memcached",
      host: initial.host ?? "localhost",
      port: initial.port ?? "11211",
      lifetime: initial.lifetime ?? "3600",
      username: initial.username ?? "",
      password: initial.password ?? "",
      globalGroups: initial.globalGroups ?? "",
      noCacheGroups: initial.noCacheGroups ?? "",
      persistentConnection: initial.persistentConnection ?? false,
    },
  })

  React.useEffect(() => {
    form.reset(initial);
  }, [initial, form.reset]);

  function handleSubmit(data: ObjectCacheFormData) {
    onSubmit(data)
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="enabled"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Object Cache</FormLabel>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} disabled={isSaving} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="method"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Method</FormLabel>
              <FormControl>
                <Select value={field.value} onValueChange={field.onChange} disabled={isSaving}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="memcached">Memcached</SelectItem>
                    <SelectItem value="redis">Redis</SelectItem>
                  </SelectContent>
                </Select>
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="grid grid-cols-2 gap-4">
          <FormField
            control={form.control}
            name="host"
            render={({ field }) => (
              <FormItem className="space-y-2">
                <FormLabel>Host</FormLabel>
                <FormControl>
                  <Input {...field} id="host" disabled={isSaving} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="port"
            render={({ field }) => (
              <FormItem className="space-y-2">
                <FormLabel>Port</FormLabel>
                <FormControl>
                  <Input {...field} id="port" disabled={isSaving} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>
        <FormField
          control={form.control}
          name="lifetime"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default Object Lifetime (seconds)</FormLabel>
              <FormControl>
                <Input {...field} id="object-lifetime" disabled={isSaving} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="grid grid-cols-2 gap-4">
          <FormField
            control={form.control}
            name="username"
            render={({ field }) => (
              <FormItem className="space-y-2">
                <FormLabel>Username</FormLabel>
                <FormControl>
                  <Input {...field} id="username" disabled={isSaving} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="password"
            render={({ field }) => (
              <FormItem className="space-y-2">
                <FormLabel>Password</FormLabel>
                <FormControl>
                  <Input {...field} id="password" type="password" disabled={isSaving} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>
        <FormField
          control={form.control}
          name="globalGroups"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Global Groups</FormLabel>
              <FormControl>
                <Textarea {...field} id="global-groups" placeholder={"users\nuserlogins\nusermeta\nsite-options"} rows={4} disabled={isSaving} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="noCacheGroups"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Do Not Cache Groups</FormLabel>
              <FormControl>
                <Textarea {...field} id="no-cache-groups" placeholder={"comment\ncounts\nplugins"} rows={4} disabled={isSaving} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="persistentConnection"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between">
              <FormLabel>Persistent Connection</FormLabel>
              <FormControl>
                <Switch checked={field.value || false} onCheckedChange={field.onChange} disabled={isSaving} />
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
