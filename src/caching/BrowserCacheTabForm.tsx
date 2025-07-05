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
import { verifyNginxBrowserCache, BrowserCacheStatus } from "@/api";
import { toast as sonnerToast } from "sonner";

const browserCacheSchema = z.object({
  browser_cache_enabled: z.boolean(),
  browser_cache_ttl: z.coerce.number().min(0, "TTL must be a positive number."),
});

type BrowserCacheFormData = z.infer<typeof browserCacheSchema>;

type Props = {
  initial: BrowserCacheStatus["settings"];
  onSubmit: (data: BrowserCacheFormData) => Promise<void>;
  isSaving: boolean;
  status?: BrowserCacheStatus;
  error?: { message: string; rules: string } | null;
  manualRules?: string | null;
};

export function BrowserCacheTabForm({
  initial,
  onSubmit,
  isSaving,
  status,
  error,
  manualRules,
}: Props) {
  const [verifyLoading, setVerifyLoading] = React.useState(false);
  const [verifyMessage, setVerifyMessage] = React.useState<string | null>(null);

  const form = useForm<BrowserCacheFormData>({
    resolver: zodResolver(browserCacheSchema),
    values: {
      browser_cache_enabled: initial?.browser_cache_enabled ?? false,
      browser_cache_ttl: initial?.browser_cache_ttl ?? 31536000,
    },
  });

  async function handleCopy(rules: string) {
    await navigator.clipboard.writeText(rules);
    sonnerToast.success("Rules copied to clipboard!");
  }

  async function handleVerifyNginx() {
    setVerifyLoading(true);
    setVerifyMessage(null);
    try {
      const verifyRes = await verifyNginxBrowserCache();
      setVerifyMessage(
        verifyRes.message ||
          (verifyRes.verified ? "Verified!" : "Could not verify.")
      );
    } catch (e) {
      setVerifyMessage("Verification failed.");
    } finally {
      setVerifyLoading(false);
    }
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

  if (status?.server === "nginx" && !status.nginx_verified) {
    return (
      <div className="space-y-4">
        <div className="bg-yellow-100 text-yellow-800 p-3 rounded">
          Nginx config appears to be missing browser cache rules. Please add the
          following to your Nginx config, reload the service, then click Verify.
        </div>
        <textarea
          className="w-full font-mono text-xs"
          rows={8}
          readOnly
          value={status.rules}
        />
        <div className="flex gap-2">
          <Button onClick={() => handleCopy(status.rules)}>Copy Rules</Button>
          <Button onClick={handleVerifyNginx} disabled={verifyLoading}>
            {verifyLoading ? "Verifying..." : "Verify"}
          </Button>
        </div>
        {verifyMessage && (
          <div className="text-sm text-gray-700 mt-2">{verifyMessage}</div>
        )}
      </div>
    );
  }

  if (
    (status?.server === "apache" || status?.server === "litespeed") &&
    status.htaccess_writable === false &&
    !status.rules_present
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
          value={status.rules}
        />
        <Button onClick={() => handleCopy(status.rules)}>Copy Rules</Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {(status?.rules_present || status?.nginx_verified) && (
        <div className="bg-green-100 text-green-800 p-3 rounded">
          <p className="font-bold">Browser cache is active.</p>
          <p className="text-sm">
            Rules detected with a TTL of{" "}
            <b>{status.settings.browser_cache_ttl}</b> seconds.
          </p>
          {status.server === "apache" && status.htaccess_writable === false && (
            <p className="text-sm mt-1">
              Settings are disabled because your <code>.htaccess</code> file is
              not writable.
            </p>
          )}
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
                      isSaving ||
                      (status?.server === "apache" &&
                        status.htaccess_writable === false)
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
                      isSaving ||
                      (status?.server === "apache" &&
                        status.htaccess_writable === false)
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
              disabled={
                isSaving ||
                (status?.server === "apache" &&
                  status.htaccess_writable === false)
              }
            >
              {isSaving ? "Saving..." : "Save Changes"}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  );
}
