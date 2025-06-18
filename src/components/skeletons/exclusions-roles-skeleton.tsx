import * as React from "react";
import { Skeleton } from "@/components/ui/skeleton"; // Adjust path if needed

export function ExclusionsRolesSkeleton({ checkboxCount = 8 }: { checkboxCount?: number }) {
  return (
    <div className="space-y-3">
      {/* Skeleton for the main FormLabel: "User Roles to Exclude from Caching" */}
      <Skeleton className="h-6 w-3/4 rounded-md" /> {/* text-base font-medium approx height */}

      {/* Skeleton for the grid of checkboxes */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        {Array.from({ length: checkboxCount }).map((_, index) => (
          <div
            key={`role-skeleton-${index}`}
            className="flex items-center space-x-2 p-2 border rounded-md" // Mimics FormItem styling
          >
            <Skeleton className="h-4 w-4 rounded-sm" /> {/* Checkbox itself */}
            <Skeleton className="h-4 w-5/6 rounded-md flex-grow" /> {/* Role name label */}
          </div>
        ))}
      </div>
      {/* Optional: Skeleton for FormMessage if it takes up space even when empty */}
      {/* <Skeleton className="h-4 w-1/3 rounded-md mt-1" /> */}
    </div>
  );
}