import { useNavigate } from "@tanstack/react-router";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { useSignInWithPasskey } from "@/services/auth/passkeys/actions";
import { isPasskeySupported } from "@/services/auth/passkeys/types";
import { persistSession } from "@/services/auth/session";

export const PasskeyLoginButton = () => {
  const navigate = useNavigate();

  const { mutate, isPending, isError } = useSignInWithPasskey({
    onSuccess: (result) => {
      persistSession(result);
      void navigate({ to: "/" });
    },
  });

  // A user who cannot use WebAuthn on this device or origin has nothing to do
  // here - the login page still offers password (and Google, if installed) -
  // so the button disappears rather than showing a disabled control with no
  // explanation.
  if (!isPasskeySupported()) {
    return null;
  }

  return (
    <div>
      <Button type="button" onClick={() => mutate()} disabled={isPending}>
        Sign in with a passkey
      </Button>
      {isError ? (
        <Alert variant="destructive">
          <AlertDescription>That passkey didn&apos;t work. Try another way to sign in.</AlertDescription>
        </Alert>
      ) : null}
    </div>
  );
};
