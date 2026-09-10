import { useState } from "react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { useDeletePasskey, usePasskeys } from "@/services/auth/passkeys/actions";
import { isPasskeySupported, type Passkey } from "@/services/auth/passkeys/types";
import { getAccessToken } from "@/services/auth/session";
import { EnrolPasskeyDialog } from "./enrol-passkey-dialog";

export const PasskeysSection = () => {
  const token = getAccessToken();
  const [enrolOpen, setEnrolOpen] = useState(false);

  // Hooks run unconditionally regardless of whether a token is present yet -
  // the query is disabled instead, and the missing-token case is handled by
  // the early return below, after both hooks have already been called.
  const {
    data: passkeys,
    isLoading,
    isError,
    refetch,
  } = usePasskeys({ token: token ?? "" }, { enabled: token !== null });
  const { mutate: deletePasskey, isPending: isDeleting } = useDeletePasskey({
    onSuccess: () => {
      void refetch();
    },
  });

  if (!isPasskeySupported()) {
    return (
      <div>
        <h2>Passkeys</h2>
        <Alert>
          <AlertDescription>
            This browser can&apos;t use passkeys. Open this app in a current browser to add one.
          </AlertDescription>
        </Alert>
      </div>
    );
  }

  if (token === null) {
    return null;
  }

  return (
    <div>
      <h2>Passkeys</h2>
      <p>One is enough - a password manager syncs it to every device you use.</p>
      {isLoading ? <p>Loading your passkeys…</p> : null}
      {isError ? (
        <Alert variant="destructive">
          <AlertDescription>Couldn&apos;t load your passkeys.</AlertDescription>
          <Button type="button" onClick={() => void refetch()}>
            Try again
          </Button>
        </Alert>
      ) : null}
      <ul>
        {passkeys?.map((passkey: Passkey) => (
          <li key={passkey.id}>
            <span>{passkey.name}</span>
            <Button
              type="button"
              variant="destructive"
              disabled={isDeleting}
              onClick={() => deletePasskey({ token, id: passkey.id })}
            >
              Remove
            </Button>
          </li>
        ))}
      </ul>
      {passkeys?.length === 0 ? <p>You haven&apos;t added a passkey yet.</p> : null}
      <Button type="button" onClick={() => setEnrolOpen(true)}>
        Add a passkey
      </Button>
      <EnrolPasskeyDialog
        open={enrolOpen}
        onOpenChange={setEnrolOpen}
        token={token}
        onEnrolled={() => void refetch()}
      />
    </div>
  );
};
