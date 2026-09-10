import { useState } from "react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Icons } from "@/components/ui/icons";

type RecoveryCodesProps = {
  recoveryCodes: string[];
};

export const RecoveryCodes = ({ recoveryCodes }: RecoveryCodesProps) => {
  const [copyState, setCopyState] = useState<"idle" | "copied" | "failed">("idle");

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(recoveryCodes.join("\n"));
      setCopyState("copied");
    } catch {
      setCopyState("failed");
    }
  };

  return (
    <div>
      <h2>Save your recovery codes</h2>
      {/* Copy-to-clipboard only, no download: these codes never touch disk or
          browser storage, matching the reference screen this is ported from. */}
      <ul>
        {recoveryCodes.map((code) => (
          <li key={code}>{code}</li>
        ))}
      </ul>
      <Button type="button" onClick={() => void handleCopy()}>
        <Icons.copy aria-hidden="true" />
        {copyState === "copied" ? "Copied" : "Copy codes"}
      </Button>
      {copyState === "failed" && (
        <Alert variant="destructive">
          <AlertDescription>
            Couldn&apos;t copy the codes automatically. Select and copy them manually instead.
          </AlertDescription>
        </Alert>
      )}
    </div>
  );
};
