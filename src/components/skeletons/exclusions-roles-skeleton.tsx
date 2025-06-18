import * as React from "react";

export function ExclusionsRolesSkeleton() {
  return (
    <div className="space-y-3">
      <div className="text-base font-medium mb-2">User Roles to Exclude from Caching</div>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        {Array.from({ length: 8 }).map((_, i) => (
          <div
            key={i}
            className="flex items-center space-x-2 p-2 border rounded-md bg-muted animate-pulse"
          >
            <div className="w-4 h-4 bg-gray-300 rounded" />
            <div className="h-4 w-32 bg-gray-300 rounded" />
          </div>
        ))}
      </div>
    </div>
  );
}
