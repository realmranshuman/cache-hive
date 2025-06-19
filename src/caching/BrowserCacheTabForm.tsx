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
import { verifyNginxBrowserCache, BrowserCacheStatus } from "@/api";
import { toast as sonnerToast } from "sonner";

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

type Props = {
  initial: BrowserCacheStatus["settings"];
  onSubmit: (data: BrowserCacheFormData) => Promise<void>;
  isSaving: boolean;
  status?: BrowserCacheStatus;
  error?: { message: string; rules: string } | null;
  manualRules?: string | null;
};

export function BrowserCacheTabForm({ initial, onSubmit, isSaving, status, error, manualRules }: Props) {
  const [verifyLoading, setVerifyLoading] = React.useState(false);
  const [verifyMessage, setVerifyMessage] = React.useState<string | null>(null);

  const form = useForm({
    resolver: zodResolver(browserCacheSchema),
    defaultValues: initial,
  });

  React.useEffect(() => {
    form.reset(initial);
  }, [initial]);

  async function handleSubmit(data: BrowserCacheFormData) {
    await onSubmit({
      ...data,
      browserCacheTTL: Number(data.browserCacheTTL),
    });
  }

  async function handleCopy(rules: string) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(rules);
    } else {
      // fallback for older browsers
      const textarea = document.createElement('textarea');
      textarea.value = rules;
      document.body.appendChild(textarea);
      textarea.select();
      try {
        document.execCommand('copy');
      } finally {
        document.body.removeChild(textarea);
      }
    }
    sonnerToast.success('Rules copied to clipboard!');
  }

  async function handleVerifyNginx() {
    setVerifyLoading(true);
    setVerifyMessage(null);
    try {
      const res = await fetch('/wp-includes/css/dashicons.min.css?_ch_verify=' + Date.now());
      const cacheControl = res.headers.get('cache-control');
      if (cacheControl && /max-age|public/.test(cacheControl)) {
        const verifyRes = await verifyNginxBrowserCache();
        setVerifyMessage(verifyRes.message || 'Verified!');
        // Optionally, you can trigger a refresh in parent after verification
      } else {
        setVerifyMessage('Could not verify caching headers.');
      }
    } catch (e) {
      setVerifyMessage('Verification failed.');
    } finally {
      setVerifyLoading(false);
    }
  }

  // Show error and rules to copy if .htaccess is not writable after save attempt (even if rules are present)
  if (error && manualRules) {
    return (
      <div className="space-y-4">
        <div className="bg-red-100 text-red-800 p-3 rounded">{error.message}</div>
        <textarea className="w-full font-mono text-xs" rows={8} readOnly value={manualRules} />
        <Button onClick={() => handleCopy(manualRules)}>Copy</Button>
      </div>
    );
  }

  // Apache/LiteSpeed: .htaccess not writable and rules not present (initial load, not after save)
  if (
    status &&
    (status.server === 'apache' || status.server === 'litespeed') &&
    status.htaccessWritable === false && status.rulesPresent === false
  ) {
    return (
      <div className="space-y-4">
        <div className="bg-red-100 text-red-800 p-3 rounded">
          .htaccess is not writable and does not contain browser cache rules. Please add the following rules manually:
        </div>
        <textarea className="w-full font-mono text-xs" rows={8} readOnly value={status.rules} />
        <Button onClick={() => handleCopy(status.rules)}>Copy</Button>
      </div>
    );
  }

  // Apache/LiteSpeed: rules present (writable or not)
  if (
    status &&
    (status.server === 'apache' || status.server === 'litespeed') &&
    status.rulesPresent === true
  ) {
    return (
      <div className="space-y-4">
        <div className="bg-green-100 text-green-800 p-3 rounded">
          Browser cache is <b>active</b> (rules detected in <code>.htaccess</code>).<br />
          TTL: <b>{status.settings.browserCacheTTL}</b> seconds.<br />
          {status.htaccessWritable === false ? (
            <>
              The toggle is disabled because <code>.htaccess</code> is not writable.<br />
              To disable browser cache, remove the rules manually from <code>.htaccess</code>.
            </>
          ) : (
            <>
              The toggle and TTL are now synced with the rules in <code>.htaccess</code>.<br />
              You can edit settings below, or edit/remove the rules directly in <code>.htaccess</code>.
            </>
          )}
        </div>
        <div>
          <label className="block text-xs font-semibold mb-1">Active .htaccess rules:</label>
          <pre className="bg-gray-100 text-xs p-2 rounded overflow-x-auto select-text whitespace-pre-wrap" style={{ userSelect: 'text' }}>{status.rules}</pre>
        </div>
        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit(handleSubmit)}>
            <FormField
              control={form.control}
              name="browserCacheEnabled"
              render={({ field }) => (
                <FormItem className="flex items-center justify-between">
                  <FormLabel>Browser Cache</FormLabel>
                  <FormControl>
                    <Switch checked={field.value} disabled={status.htaccessWritable === false} onCheckedChange={field.onChange} />
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
                      disabled={status.htaccessWritable === false}
                      value={typeof field.value === 'number' || typeof field.value === 'string' ? field.value : ''}
                      onChange={e => field.onChange(e.target.value === '' ? '' : Number(e.target.value))}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            {status.htaccessWritable !== false && (
              <div className="flex justify-end">
                <Button type="submit" disabled={isSaving}>
                  {isSaving ? "Saving..." : "Save Changes"}
                </Button>
              </div>
            )}
          </form>
        </Form>
      </div>
    );
  }

  // Nginx: not verified, rules not present (simulate read-only by always showing if rulesPresent is false)
  if (status && status.server === 'nginx' && !status.nginxVerified && status.rulesPresent === false) {
    return (
      <div className="space-y-4">
        <div className="bg-yellow-100 text-yellow-800 p-3 rounded">Nginx config is missing browser cache rules. Please add the following rules and reload Nginx, then click Verify.</div>
        <textarea className="w-full font-mono text-xs" rows={8} readOnly value={status.rules} />
        <div className="flex gap-2">
          <Button onClick={() => handleCopy(status.rules)}>Copy</Button>
          <Button onClick={handleVerifyNginx} disabled={verifyLoading}>{verifyLoading ? 'Verifying...' : 'Verify'}</Button>
        </div>
        {verifyMessage && <div className="text-sm text-gray-700">{verifyMessage}</div>}
      </div>
    );
  }

  // Nginx: verified
  if (status && status.server === 'nginx' && status.nginxVerified) {
    return <div className="bg-green-100 text-green-800 p-3 rounded">Nginx browser cache rules are verified and active.</div>;
  }

  // Default: show form
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
  );
}
