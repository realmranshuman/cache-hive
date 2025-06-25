import * as React from "react";
import { Skeleton } from "@/components/ui/skeleton"; // Adjust path if needed

export function AutoPurgeSettingsSkeleton() {
  const checkboxItemCount = 10; // Number of checkbox items in the "Auto Purge Rules" section

  return (
    <div className="space-y-4">
      {/* Skeleton for "Purge All Cache on Plugin/Theme/Core Upgrade" Switch */}
      <div className="flex items-center justify-between">
        <Skeleton className="h-5 w-3/4 rounded-md" /> {/* Label */}
        <Skeleton className="h-6 w-11 rounded-full" /> {/* Switch */}
      </div>

      {/* Skeleton for "Auto Purge Rules for Publish/Update Actions" Section */}
      <div className="space-y-3 pt-4">
        {/* Section Heading */}
        <Skeleton className="h-6 w-3/5 rounded-md border-b pb-2 mb-3" />

        {/* Grid for Checkboxes */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {Array.from({ length: checkboxItemCount }).map((_, index) => (
            <div
              key={`checkbox-skeleton-${index}`}
              className="flex items-center space-x-2 p-2 border rounded-md"
            >
              <Skeleton className="h-4 w-4 rounded-sm" /> {/* Checkbox */}
              <Skeleton className="h-4 w-5/6 rounded-md" /> {/* Label */}
            </div>
          ))}
        </div>
      </div>

      {/* Skeleton for "Custom Purge Hooks" Textarea Section */}
      <div className="pt-4 space-y-2">
        <Skeleton className="h-5 w-1/3 rounded-md" /> {/* Label */}
        <Skeleton className="w-full min-h-[80px] rounded-md" /> {/* Textarea */}
        {/* Description lines */}
        <Skeleton className="h-3 w-full rounded-md" />
        <Skeleton className="h-3 w-4/5 rounded-md" />
      </div>

      {/* Skeleton for "Serve Stale Cache While Regenerating" Switch */}
      <div className="flex items-center justify-between pt-4">
        <Skeleton className="h-5 w-3/5 rounded-md" /> {/* Label */}
        <Skeleton className="h-6 w-11 rounded-full" /> {/* Switch */}
      </div>

      {/* Skeleton for "Save Changes" Button */}
      <div className="flex justify-end pt-4">
        <Skeleton className="h-10 w-28 rounded-md" /> {/* Button */}
      </div>
    </div>
  );
}