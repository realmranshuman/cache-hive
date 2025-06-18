import * as React from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";

export function CacheSettingsSkeleton() {
  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <Skeleton className="h-7 w-[220px]" />
        </CardTitle>
      </CardHeader>
      <CardContent>
        {/* Tab row mimic */}
        <div className="grid w-full grid-cols-4 sm:grid-cols-6 gap-2 mb-6">
          {["Cache", "TTL", "Auto Purge", "Exclusions"].map((tab) => (
            <Skeleton key={tab} className="h-10 rounded-md w-full" />
          ))}
        </div>
        <form className="space-y-4">
          {/* 5 switch fields */}
          {[...Array(5)].map((_, i) => (
            <div
              key={i}
              className="flex items-center justify-between"
            >
              <Skeleton className="h-5 w-[210px]" />
              <Skeleton className="h-6 w-[42px] rounded-full" />
            </div>
          ))}

          {/* Textarea field */}
          <div className="space-y-2">
            <Skeleton className="h-5 w-[180px]" />
            <Skeleton className="h-[96px] w-full" />
          </div>

          {/* Submit button */}
          <div className="flex justify-end">
            <Skeleton className="h-10 w-[120px]" />
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
