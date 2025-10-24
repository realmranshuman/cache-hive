import * as React from "@wordpress/element";
import { Skeleton } from "@/components/ui/skeleton";

// Helper component for a single form field skeleton
const FormFieldSkeleton = ({
  labelWidth = "w-4/5",
}: {
  labelWidth?: string;
}) => (
  <div className="space-y-2">
    <Skeleton className={`h-5 ${labelWidth} rounded-md`} /> {/* Label */}
    <Skeleton className="h-10 w-full rounded-md" />{" "}
    {/* Input (shadcn Input default height) */}
  </div>
);

export function TtlSettingsSkeleton() {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
      {/* Skeleton for "Default TTL for Public Cache (seconds)" */}
      <FormFieldSkeleton labelWidth="w-5/6" />

      {/* Skeleton for "Default TTL for Private Cache (seconds)" */}
      <FormFieldSkeleton labelWidth="w-5/6" />

      {/* Skeleton for "Default TTL for Front Page (seconds)" */}
      <FormFieldSkeleton labelWidth="w-5/6" />

      {/* Skeleton for "Default TTL for Feeds (seconds)" */}
      <FormFieldSkeleton labelWidth="w-4/5" />

      {/* Skeleton for "Default TTL for REST API (seconds)" */}
      <FormFieldSkeleton labelWidth="w-4/5" />

      {/* Skeleton for "Save Changes" Button */}
      <div className="flex justify-end col-span-full pt-2">
        <Skeleton className="h-10 w-28 rounded-md" /> {/* Button */}
      </div>
    </div>
  );
}
