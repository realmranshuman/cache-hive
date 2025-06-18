import * as React from "react";
import { Skeleton } from "@/components/ui/skeleton"; // Adjust path if needed

// Helper for a standard input field with a label
const InputFieldSkeleton = ({ labelWidth = "w-1/3", className = "" }: { labelWidth?: string, className?: string }) => (
  <div className={`space-y-2 ${className}`}>
    <Skeleton className={`h-5 ${labelWidth} rounded-md`} /> {/* Label */}
    <Skeleton className="h-10 w-full rounded-md" /> {/* Input */}
  </div>
);

// Helper for a textarea field with a label
const TextareaFieldSkeleton = ({ labelWidth = "w-1/3", className = "" }: { labelWidth?: string, className?: string }) => (
  <div className={`space-y-2 ${className}`}>
    <Skeleton className={`h-5 ${labelWidth} rounded-md`} /> {/* Label */}
    <Skeleton className="h-24 w-full rounded-md" /> {/* Textarea (rows={4} approx h-24) */}
  </div>
);

// Helper for a switch field with a label
const SwitchFieldSkeleton = ({ labelWidth = "w-2/5", className = "" }: { labelWidth?: string, className?: string }) => (
  <div className={`flex items-center justify-between ${className}`}>
    <Skeleton className={`h-5 ${labelWidth} rounded-md`} /> {/* Label */}
    <Skeleton className="h-6 w-11 rounded-full" /> {/* Switch */}
  </div>
);

export function ObjectCacheSettingsSkeleton() {
  return (
    <div className="space-y-4">
      {/* Skeleton for "Object Cache" Switch */}
      <SwitchFieldSkeleton labelWidth="w-1/4" />

      {/* Skeleton for "Method" Select */}
      <div className="space-y-2">
        <Skeleton className="h-5 w-1/5 rounded-md" /> {/* Label */}
        <Skeleton className="h-10 w-full rounded-md" /> {/* SelectTrigger */}
      </div>

      {/* Skeleton for Host and Port Grid */}
      <div className="grid grid-cols-2 gap-4">
        <InputFieldSkeleton labelWidth="w-1/3" /> {/* Host */}
        <InputFieldSkeleton labelWidth="w-1/3" /> {/* Port */}
      </div>

      {/* Skeleton for "Default Object Lifetime" Input */}
      <InputFieldSkeleton labelWidth="w-3/5" />

      {/* Skeleton for Username and Password Grid */}
      <div className="grid grid-cols-2 gap-4">
        <InputFieldSkeleton labelWidth="w-1/2" /> {/* Username */}
        <InputFieldSkeleton labelWidth="w-1/2" /> {/* Password */}
      </div>

      {/* Skeleton for "Global Groups" Textarea */}
      <TextareaFieldSkeleton labelWidth="w-1/3" />

      {/* Skeleton for "Do Not Cache Groups" Textarea */}
      <TextareaFieldSkeleton labelWidth="w-2/5" />

      {/* Skeleton for "Persistent Connection" Switch */}
      <SwitchFieldSkeleton labelWidth="w-2/5" />

      {/* Skeleton for "Save Changes" Button */}
      <div className="flex justify-end pt-2">
        <Skeleton className="h-10 w-28 rounded-md" /> {/* Button */}
      </div>
    </div>
  );
}