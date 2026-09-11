import { useState } from "react";

import { Button } from "@/components/ui/button";
import { ErrorMessage } from "@/components/ui/error-message";

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
        {copyState === "copied" ? "Copied" : "Copy codes"}
      </Button>
      {copyState === "failed" && (
        <ErrorMessage errorMessage="Couldn't copy the codes automatically. Select and copy them manually instead." />
      )}
    </div>
  );
};
