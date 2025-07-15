import * as React from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Input } from "@/components/ui/input";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import {
  verifyNginxBrowserCache,
  BrowserCacheStatus,
  getBrowserCacheSettings,
} from "@/api";
import { toast as sonnerToast } from "sonner";
import { wrapPromise } from "@/utils/wrapPromise";

const browserCacheSchema = z.object({
  browser_cache_enabled: z.boolean(),
  browser_cache_ttl: z.coerce.number().min(0, "TTL must be a positive number."),
});

type BrowserCacheFormData = z.infer<typeof browserCacheSchema>;

type Props = {
  initial: BrowserCacheStatus["settings"];
  onSubmit: (data: BrowserCacheFormData) => Promise<void>;
  isSaving: boolean;
  status: BrowserCacheStatus;
  error?: { message: string; rules: string } | null;
  manualRules?: string | null;
};

const nginxStatusCache = new Map<string, any>();
function getFinalNginxStatusResource(): { read: () => BrowserCacheStatus } {
  const cacheKey = "nginx-verification";
  if (!nginxStatusCache.has(cacheKey)) {
    const promise = (async () => {
      const verifyRes = await verifyNginxBrowserCache();
      const latestSettings = await getBrowserCacheSettings();
      return { ...latestSettings, nginx_verified: verifyRes.verified };
    })();
    nginxStatusCache.set(cacheKey, wrapPromise(promise));
  }
  return nginxStatusCache.get(cacheKey);
}

function NginxStatusView() {
  const finalStatus = getFinalNginxStatusResource().read();

  async function handleCopy(rules: string) {
    await navigator.clipboard.writeText(rules);
    sonnerToast.success("Rules copied to clipboard!");
  }

  if (finalStatus.nginx_verified) {
    return (
      <div className="space-y-4">
        <div className="bg-green-100 text-green-800 p-3 rounded">
          <p className="font-bold">Browser cache is active.</p>
          <p className="text-sm">
            Rules detected with a TTL of{" "}
            <b>{finalStatus.settings.browser_cache_ttl}</b> seconds.
          </p>
        </div>
        <div className="text-sm text-gray-600 dark:text-gray-400 p-4 border rounded-lg">
          Your server is running Nginx. Browser cache settings must be
          configured directly in your Nginx server configuration file.
        </div>
      </div>
    );
  } else {
    return (
      <div className="space-y-4">
        <div className="bg-yellow-100 text-yellow-800 p-3 rounded">
          Nginx config appears to be missing browser cache rules. Please add the
          following to your Nginx config, reload the service, and refresh this
          page.
        </div>
        <textarea
          className="w-full font-mono text-xs"
          rows={8}
          readOnly
          value={finalStatus.rules}
        />
        <div className="flex gap-2">
          <Button onClick={() => handleCopy(finalStatus.rules)}>
            Copy Rules
          </Button>
          <Button onClick={() => window.location.reload()}>Refresh</Button>
        </div>
      </div>
    );
  }
}

export function BrowserCacheTabForm({
  initial,
  onSubmit,
  isSaving,
  status: initialStatus,
  error,
  manualRules,
}: Props) {
  const form = useForm<BrowserCacheFormData>({
    resolver: zodResolver(browserCacheSchema),
    defaultValues: {
      browser_cache_enabled: initial?.browser_cache_enabled ?? false,
      browser_cache_ttl: initial?.browser_cache_ttl ?? 31536000,
    },
  });

  React.useEffect(() => {
    form.reset({
      browser_cache_enabled: initial?.browser_cache_enabled ?? false,
      browser_cache_ttl: initial?.browser_cache_ttl ?? 31536000,
    });
  }, [initial, form.reset]);

  async function handleCopy(rules: string) {
    await navigator.clipboard.writeText(rules);
    sonnerToast.success("Rules copied to clipboard!");
  }

  if (error && manualRules) {
    return (
      <div className="space-y-4">
        <div className="bg-red-100 text-red-800 p-3 rounded">
          {error.message}
        </div>
        <textarea
          className="w-full font-mono text-xs"
          rows={10}
          readOnly
          value={manualRules}
        />
        <Button onClick={() => handleCopy(manualRules)}>Copy Rules</Button>
      </div>
    );
  }

  if (initialStatus.server === "nginx") {
    return <NginxStatusView />;
  }

  if (
    (initialStatus.server === "apache" ||
      initialStatus.server === "litespeed") &&
    initialStatus.htaccess_writable === false &&
    !initialStatus.rules_present
  ) {
    return (
      <div className="space-y-4">
        <div className="bg-red-100 text-red-800 p-3 rounded">
          Your <code>.htaccess</code> file is not writable and browser cache
          rules are not present. Please add the following rules manually to your{" "}
          <code>.htaccess</code> file.
        </div>
        <textarea
          className="w-full font-mono text-xs"
          rows={10}
          readOnly
          value={initialStatus.rules}
        />
        <Button onClick={() => handleCopy(initialStatus.rules)}>
          Copy Rules
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {initialStatus.rules_present && (
        <div className="bg-green-100 text-green-800 p-3 rounded">
          <p className="font-bold">Browser cache is active.</p>
          <p className="text-sm">
            Rules detected with a TTL of{" "}
            <b>{initialStatus.settings.browser_cache_ttl}</b> seconds.
          </p>
        </div>
      )}
      <Form {...form}>
        <form className="space-y-4" onSubmit={form.handleSubmit(onSubmit)}>
          <FormField
            control={form.control}
            name="browser_cache_enabled"
            render={({ field }) => (
              <FormItem className="flex items-center justify-between rounded-lg border p-4">
                <FormLabel>Enable Browser Cache</FormLabel>
                <FormControl>
                  <Switch
                    checked={field.value}
                    onCheckedChange={field.onChange}
                    disabled={
                      isSaving || initialStatus.htaccess_writable === false
                    }
                  />
                </FormControl>
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="browser_cache_ttl"
            render={({ field }) => (
              <FormItem className="space-y-2">
                <FormLabel>Browser Cache TTL (seconds)</FormLabel>
                <FormControl>
                  <Input
                    {...field}
                    type="number"
                    min={0}
                    disabled={
                      isSaving || initialStatus.htaccess_writable === false
                    }
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <div className="flex justify-end">
            <Button
              type="submit"
              disabled={isSaving || initialStatus.htaccess_writable === false}
            >
              {isSaving ? "Saving..." : "Save Changes"}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  );
}
