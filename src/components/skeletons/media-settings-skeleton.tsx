import * as React from "react"
import { Skeleton } from "@/components/ui/skeleton";

export function MediaSettingsSkeleton() {
  return (
    <div className="space-y-4">
      <Skeleton className="h-12 w-full" />
      <div className="flex items-center justify-between">
        <Skeleton className="h-5 w-32" />
        <Skeleton className="h-6 w-12 rounded-full" />
      </div>
       <div className="space-y-2">
        <Skeleton className="h-5 w-48" />
        <Skeleton className="h-24 w-full" />
      </div>
      <div className="flex items-center justify-between">
        <Skeleton className="h-5 w-32" />
        <Skeleton className="h-6 w-12 rounded-full" />
      </div>
       <div className="space-y-2">
        <Skeleton className="h-5 w-48" />
        <Skeleton className="h-24 w-full" />
      </div>
       <div className="flex items-center justify-between">
        <Skeleton className="h-5 w-40" />
        <Skeleton className="h-6 w-12 rounded-full" />
      </div>
      <div className="flex justify-end">
        <Skeleton className="h-10 w-32" />
      </div>
    </div>
  );
}