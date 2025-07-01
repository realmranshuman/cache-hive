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
    .number({ invalid_type_error: "TTL must be a valid number." })
    .min(14400, "Minimum 4 hours (14400 seconds)")
    .max(63072000, "Maximum 2 years (63072000 seconds)"),
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

  const form = useForm<BrowserCacheFormData>({
    resolver: zodResolver(browserCacheSchema),
    values: {
      browserCacheEnabled: initial?.browserCacheEnabled || false,
      browserCacheTTL: initial?.browserCacheTTL || 31536000,
    },
  });

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
      } else {
        setVerifyMessage('Could not verify caching headers.');
      }
    } catch (e) {
      setVerifyMessage('Verification failed.');
    } finally {
      setVerifyLoading(false);
    }
  }

  // --- Conditional returns for views that DO NOT show the main form ---

  if (error && manualRules) {
    return (
      <div className="space-y-4">
        <div className="bg-red-100 text-red-800 p-3 rounded">{error.message}</div>
        <textarea className="w-full font-mono text-xs" rows={8} readOnly value={manualRules} />
        <Button onClick={() => handleCopy(manualRules)}>Copy</Button>
      </div>
    );
  }

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

  if (status && status.server === 'nginx' && status.nginxVerified) {
    return <div className="bg-green-100 text-green-800 p-3 rounded">Nginx browser cache rules are verified and active.</div>;
  }

  // --- Refactored Unified Form View ---

  const rulesArePresent = status?.rulesPresent === true;
  const isHtaccessReadOnly = status?.htaccessWritable === false;
  const fieldsAreDisabled = isSaving || (rulesArePresent && isHtaccessReadOnly);
  const showSaveButton = !(rulesArePresent && isHtaccessReadOnly);

  return (
    <div className="space-y-4">
      {rulesArePresent && (
        <>
          <div className="bg-green-100 text-green-800 p-3 rounded">
            Browser cache is <b>active</b> (rules detected in <code>.htaccess</code>).<br />
            TTL: <b>{status.settings.browserCacheTTL}</b> seconds.<br />
            {isHtaccessReadOnly ? (
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
            <pre className="bg-gray-100 text-xs p-2 rounded overflow-x-auto select-text whitespace-pre-wrap font-mono text-black dark:bg-gray-900 dark:text-white" style={{ userSelect: 'text' }}>{status.rules}</pre>
          </div>
        </>
      )}

      <Form {...form}>
        <form className="space-y-4" onSubmit={form.handleSubmit(handleSubmit)}>
          <FormField
            control={form.control}
            name="browserCacheEnabled"
            render={({ field }) => (
              <FormItem className="flex items-center justify-between">
                <FormLabel>Browser Cache</FormLabel>
                <FormControl>
                  <Switch
                    checked={field.value}
                    onCheckedChange={field.onChange}
                    disabled={fieldsAreDisabled}
                  />
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
                    disabled={fieldsAreDisabled}
                    value={field.value ?? ''}
                    onChange={e => field.onChange(Number(e.target.value))}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          {showSaveButton && (
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