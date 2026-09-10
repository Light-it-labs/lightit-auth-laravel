import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Icons } from "@/components/ui/icons";

type RecoveryCodesProps = {
  recoveryCodes: string[];
};

export const RecoveryCodes = ({ recoveryCodes }: RecoveryCodesProps) => {
  const [copied, setCopied] = useState(false);

  const handleCopy = () => {
    void navigator.clipboard.writeText(recoveryCodes.join("\n"));
    setCopied(true);
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
      <Button type="button" onClick={handleCopy}>
        <Icons.copy aria-hidden="true" />
        {copied ? "Copied" : "Copy codes"}
      </Button>
    </div>
  );
};
