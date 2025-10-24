import * as React from "@wordpress/element";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Info } from "lucide-react";

interface NetworkAlertProps {
  isNetworkAdmin?: boolean;
}

export function NetworkAlert({ isNetworkAdmin }: NetworkAlertProps) {
  if (isNetworkAdmin === undefined) {
    return null; // Don't render if the context is unknown
  }

  return (
    <Alert className="mb-6">
      <Info className="h-4 w-4" />
      <AlertTitle>
        {isNetworkAdmin ? "Network Settings" : "Site Settings"}
      </AlertTitle>
      <AlertDescription>
        {isNetworkAdmin
          ? "You are currently editing the network-wide default settings. Individual sites can override these options."
          : "These settings apply to this site only and will override any network defaults."}
      </AlertDescription>
    </Alert>
  );
}
