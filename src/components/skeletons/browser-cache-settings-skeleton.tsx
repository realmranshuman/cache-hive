import * as React from "@wordpress/element";
import { Skeleton } from "@/components/ui/skeleton";

export function BrowserCacheSettingsSkeleton() {
  return (
    <div className="space-y-4">
      {/* Skeleton for "Browser Cache" Switch */}
      <div className="flex items-center justify-between">
        <Skeleton className="h-5 w-1/4 rounded-md" />{" "}
        {/* Label: "Browser Cache" */}
        <Skeleton className="h-6 w-11 rounded-full" /> {/* Switch */}
      </div>

      {/* Skeleton for "Browser Cache TTL (seconds)" Input */}
      <div className="space-y-2">
        <Skeleton className="h-5 w-2/5 rounded-md" />{" "}
        {/* Label: "Browser Cache TTL (seconds)" */}
        <Skeleton className="h-10 w-full rounded-md" /> {/* Input */}
      </div>

      {/* Skeleton for "Save Changes" Button */}
      <div className="flex justify-end pt-2">
        {" "}
        {/* Added pt-2 for a bit of spacing like buttons often have */}
        <Skeleton className="h-10 w-28 rounded-md" /> {/* Button */}
      </div>
    </div>
  );
}
