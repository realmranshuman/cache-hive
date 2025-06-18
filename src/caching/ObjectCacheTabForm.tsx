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
  objectCacheEnabled: z.boolean(),
  objectCacheMethod: z.string(),
  objectCacheHost: z.string(),
  objectCachePort: z.number().min(1),
  objectCacheLifetime: z.number().min(1),
  objectCacheUsername: z.string().optional(),
  objectCachePassword: z.string().optional(),
  objectCacheGlobalGroups: z.any(),
  objectCacheNoCacheGroups: z.any(),
  objectCachePersistentConnection: z.boolean().optional(),
})

type ObjectCacheFormData = z.infer<typeof objectCacheSchema>

export function ObjectCacheTabForm({ initial, onSubmit, isSaving }: { initial: Partial<ObjectCacheFormData>, onSubmit: (data: ObjectCacheFormData) => Promise<void>, isSaving: boolean }) {
  // Convert array to newline-separated string for textarea display
  function toTextarea(val?: string[] | string) {
    if (Array.isArray(val)) return val.join("\n");
    if (typeof val === "string") return val.replace(/ +/g, "\n").replace(/\n+/g, "\n").trim();
    return "";
  }
  // Convert textarea (newline-separated) to array for backend
  function fromTextarea(val: string) {
    return val.split(/\r?\n/).map(v => v.trim()).filter(Boolean);
  }

  const form = useForm<ObjectCacheFormData>({
    resolver: zodResolver(objectCacheSchema),
    defaultValues: {
      objectCacheEnabled: initial.objectCacheEnabled ?? false,
      objectCacheMethod: initial.objectCacheMethod ?? "memcached",
      objectCacheHost: initial.objectCacheHost ?? "localhost",
      objectCachePort: Number(initial.objectCachePort) || 11211,
      objectCacheLifetime: Number(initial.objectCacheLifetime) || 3600,
      objectCacheUsername: initial.objectCacheUsername ?? "",
      objectCachePassword: initial.objectCachePassword ?? "",
      objectCacheGlobalGroups: toTextarea(initial.objectCacheGlobalGroups),
      objectCacheNoCacheGroups: toTextarea(initial.objectCacheNoCacheGroups),
      objectCachePersistentConnection: Boolean(initial.objectCachePersistentConnection) ?? false,
    },
  })

  React.useEffect(() => {
    form.reset({
      objectCacheEnabled: initial.objectCacheEnabled ?? false,
      objectCacheMethod: initial.objectCacheMethod ?? "memcached",
      objectCacheHost: initial.objectCacheHost ?? "localhost",
      objectCachePort: Number(initial.objectCachePort) || 11211,
      objectCacheLifetime: Number(initial.objectCacheLifetime) || 3600,
      objectCacheUsername: initial.objectCacheUsername ?? "",
      objectCachePassword: initial.objectCachePassword ?? "",
      objectCacheGlobalGroups: toTextarea(initial.objectCacheGlobalGroups),
      objectCacheNoCacheGroups: toTextarea(initial.objectCacheNoCacheGroups),
      objectCachePersistentConnection: Boolean(initial.objectCachePersistentConnection) ?? false,
    });
  }, [initial, form.reset]);

  async function handleSubmit(data: ObjectCacheFormData) {
    // Convert textarea values to array for backend
    const payload = {
      ...data,
      objectCacheGlobalGroups: fromTextarea(data.objectCacheGlobalGroups as string),
      objectCacheNoCacheGroups: fromTextarea(data.objectCacheNoCacheGroups as string),
    };
    await onSubmit(payload as ObjectCacheFormData);
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="objectCacheEnabled"
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
          name="objectCacheMethod"
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
            name="objectCacheHost"
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
            name="objectCachePort"
            render={({ field }) => (
              <FormItem className="space-y-2">
                <FormLabel>Port</FormLabel>
                <FormControl>
                  <Input {...field} id="port" type="number" min={1} disabled={isSaving} onChange={e => field.onChange(Number(e.target.value))} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>
        <FormField
          control={form.control}
          name="objectCacheLifetime"
          render={({ field }) => (
            <FormItem className="space-y-2">
              <FormLabel>Default Object Lifetime (seconds)</FormLabel>
              <FormControl>
                <Input {...field} id="object-lifetime" type="number" min={1} disabled={isSaving} onChange={e => field.onChange(Number(e.target.value))} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="grid grid-cols-2 gap-4">
          <FormField
            control={form.control}
            name="objectCacheUsername"
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
            name="objectCachePassword"
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
          name="objectCacheGlobalGroups"
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
          name="objectCacheNoCacheGroups"
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
          name="objectCachePersistentConnection"
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
