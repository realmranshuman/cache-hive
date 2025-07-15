import * as React from "react";
import { Skeleton } from "@/components/ui/skeleton"; // Adjust path if needed
import { ExclusionsRolesSkeleton } from "@/components/skeletons/exclusions-roles-skeleton"; // Assuming this path

// Helper for Textarea field skeleton
const TextareaFieldSkeleton = ({ labelText = "Label", labelWidth = "w-1/2" }: { labelText?: string, labelWidth?: string }) => (
  <div className="space-y-2">
    <Skeleton className={`h-5 ${labelWidth} rounded-md`} /> {/* Label */}
    <Skeleton className="h-24 w-full rounded-md" /> {/* Textarea (rows={4} approx h-24) */}
  </div>
);

export function ExclusionsSettingsSkeleton() {
  return (
    <div className="space-y-4">
      {/* Skeleton for "URIs to Exclude from Caching" */}
      <TextareaFieldSkeleton labelText="URIs to Exclude from Caching" labelWidth="w-2/5" />

      {/* Skeleton for "Query Strings to Exclude from Caching" */}
      <TextareaFieldSkeleton labelText="Query Strings to Exclude from Caching" labelWidth="w-3/5" />

      {/* Skeleton for "Cookies to Exclude from Caching" */}
      <TextareaFieldSkeleton labelText="Cookies to Exclude from Caching" labelWidth="w-2/5" />

      {/* Placeholder for the Roles section, using the already defined ExclusionsRolesSkeleton */}
      {/* The actual ExclusionsTabForm uses Suspense which falls back to ExclusionsRolesSkeleton */}
      {/* So, for the overall form skeleton, we include ExclusionsRolesSkeleton directly. */}
      <div className="pt-2"> {/* Added slight padding to visually separate, optional */}
         <ExclusionsRolesSkeleton />
      </div>


      {/* Skeleton for "Save Changes" Button */}
      <div className="flex justify-end pt-2"> {/* Added pt-2 for consistency */}
        <Skeleton className="h-10 w-28 rounded-md" /> {/* Button */}
      </div>
    </div>
  );
}