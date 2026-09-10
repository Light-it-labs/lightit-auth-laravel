import { useState } from "react";

import { Button } from "@/components/ui/button";
import { getAccessToken } from "@/services/auth/session";
import { DisableTwoFactorDialog } from "./disable-two-factor-dialog";
import { RegenerateRecoveryCodesDialog } from "./regenerate-recovery-codes-dialog";
import { RequestTwoFactorResetDialog } from "./request-two-factor-reset-dialog";

export const TwoFactorSection = () => {
  const token = getAccessToken();
  const [disableOpen, setDisableOpen] = useState(false);
  const [resetOpen, setResetOpen] = useState(false);
  const [regenerateOpen, setRegenerateOpen] = useState(false);

  if (token === null) {
    return null;
  }

  return (
    <div>
      <h2>Two-factor authentication</h2>
      <p>Manage the second factor on your account, or recover access if you&apos;ve lost it.</p>
      <Button type="button" onClick={() => setRegenerateOpen(true)}>
        Regenerate recovery codes
      </Button>
      <Button type="button" variant="outline" onClick={() => setResetOpen(true)}>
        Reset two-factor authentication
      </Button>
      <Button type="button" variant="destructive" onClick={() => setDisableOpen(true)}>
        Disable two-factor authentication
      </Button>
      <RegenerateRecoveryCodesDialog open={regenerateOpen} onOpenChange={setRegenerateOpen} token={token} />
      <RequestTwoFactorResetDialog open={resetOpen} onOpenChange={setResetOpen} token={token} />
      <DisableTwoFactorDialog open={disableOpen} onOpenChange={setDisableOpen} token={token} />
    </div>
  );
};
