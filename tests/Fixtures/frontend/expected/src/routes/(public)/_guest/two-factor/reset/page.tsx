import { type FormEvent } from "react";
import { useNavigate, useSearch } from "@tanstack/react-router";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { useResetTwoFactor } from "@/services/auth/two-factor/actions";

type ResetSearch = {
  token: string;
};

export default function TwoFactorResetPage() {
  const { token } = useSearch({ strict: false }) as unknown as ResetSearch;
  const navigate = useNavigate();

  const { mutate, isPending, isSuccess, isError } = useResetTwoFactor();

  const handleConfirm = (event: FormEvent<HTMLButtonElement>) => {
    event.preventDefault();

    mutate({ token });
  };

  if (isSuccess) {
    return (
      <div>
        <h1>Two-factor authentication reset</h1>
        <p>Your two-factor authentication has been cleared. Set it up again from your account settings.</p>
        <Button onClick={() => void navigate({ to: "/" })}>Continue</Button>
      </div>
    );
  }

  return (
    <div>
      <h1>Reset two-factor authentication</h1>
      <p>This clears your current two-factor setup. You&apos;ll need to set it up again afterward.</p>
      {isError ? (
        <Alert variant="destructive">
          <AlertDescription>
            That reset link didn&apos;t work. It may have expired - request a new one.
          </AlertDescription>
        </Alert>
      ) : null}
      <Button onClick={handleConfirm} disabled={isPending}>
        Confirm reset
      </Button>
    </div>
  );
}
