import * as React from "react";
import { Skeleton } from "@/components/ui/skeleton"; // Adjust path if needed

export function CacheSettingsSkeleton() {
  return (
    <div className="space-y-4">
      {/* Skeleton for "Enable Full-Page Caching" */}
      <div className="flex items-center justify-between">
        <Skeleton className="h-5 w-2/5 rounded-md" /> {/* Label */}
        <Skeleton className="h-6 w-11 rounded-full" /> {/* Switch (shadcn Switch dimensions) */}
      </div>

      {/* Skeleton for "Cache for Logged-in Users" */}
      <div className="flex items-center justify-between">
        <Skeleton className="h-5 w-1/2 rounded-md" /> {/* Label */}
        <Skeleton className="h-6 w-11 rounded-full" /> {/* Switch */}
      </div>

      {/* Skeleton for "Cache for Commenters" */}
      <div className="flex items-center justify-between">
        <Skeleton className="h-5 w-2/5 rounded-md" /> {/* Label */}
        <Skeleton className="h-6 w-11 rounded-full" /> {/* Switch */}
      </div>

      {/* Skeleton for "Cache REST API Requests" */}
      <div className="flex items-center justify-between">
        <Skeleton className="h-5 w-1/2 rounded-md" /> {/* Label */}
        <Skeleton className="h-6 w-11 rounded-full" /> {/* Switch */}
      </div>

      {/* Skeleton for "Cache for Mobile Devices" */}
      <div className="flex items-center justify-between">
        <Skeleton className="h-5 w-2/5 rounded-md" /> {/* Label */}
        <Skeleton className="h-6 w-11 rounded-full" /> {/* Switch */}
      </div>

      {/* Skeleton for "Custom Mobile User Agent List" (conditionally shown) */}
      {/* For a skeleton, we usually show all potential elements */}
      <div className="space-y-2">
        <Skeleton className="h-5 w-1/3 rounded-md" /> {/* Label */}
        <Skeleton className="h-24 w-full rounded-md" /> {/* Textarea (rows={4} approx h-24) */}
      </div>

      {/* Skeleton for "Save Changes" Button */}
      <div className="flex justify-end pt-2"> {/* Added pt-2 for slight separation like buttons often have */}
        <Skeleton className="h-10 w-28 rounded-md" /> {/* Button */}
      </div>
    </div>
  );
}