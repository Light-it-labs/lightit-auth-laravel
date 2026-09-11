import { useState, type FormEvent } from "react";
import { createFileRoute, useNavigate, useSearch } from "@tanstack/react-router";

import { Button } from "@/components/ui/button";
import { ErrorMessage } from "@/components/ui/error-message";
import { Input } from "@/components/ui/input";
import { persistSession } from "@/services/auth/session";
import { useVerifyRecoveryCode } from "@/services/auth/two-factor/actions";
import { getRecoveryCodeSchema } from "@/services/auth/two-factor/schemas";

type RecoveryCodeSearch = {
  token: string;
};

const TwoFactorRecoveryCodePage = () => {
  const { token } = useSearch({ strict: false }) as unknown as RecoveryCodeSearch;
  const navigate = useNavigate();

  const [recoveryCode, setRecoveryCode] = useState("");
  const [validationError, setValidationError] = useState<string | null>(null);
  const [remainingRecoveryCodes, setRemainingRecoveryCodes] = useState<number | null>(null);

  const { mutate, isPending, isError } = useVerifyRecoveryCode({
    onSuccess: (result) => {
      persistSession(result);
      setRemainingRecoveryCodes(result.remainingRecoveryCodes);
    },
  });

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const parsed = getRecoveryCodeSchema().safeParse({ recoveryCode });

    if (!parsed.success) {
      setValidationError(parsed.error.issues[0]?.message ?? "Invalid code");

      return;
    }

    setValidationError(null);
    mutate({ token, recoveryCode });
  };

  if (remainingRecoveryCodes !== null) {
    return (
      <div>
        {remainingRecoveryCodes <= 1 ? (
          <ErrorMessage
            errorMessage={`You have ${remainingRecoveryCodes} recovery code left. Generate new ones from your account settings.`}
          />
        ) : null}
        <Button onClick={() => void navigate({ to: "/" })}>Continue</Button>
      </div>
    );
  }

  return (
    <div>
      <h1>Use a recovery code</h1>
      <form onSubmit={handleSubmit}>
        <Input
          value={recoveryCode}
          onChange={(event) => setRecoveryCode(event.target.value)}
          aria-label="Recovery code"
        />
        <ErrorMessage errorMessage={validationError ?? undefined} />
        {isError ? <ErrorMessage errorMessage="That recovery code didn't work." /> : null}
        <Button type="submit" disabled={isPending}>
          Verify
        </Button>
      </form>
    </div>
  );
};

export const Route = createFileRoute("/(public)/_guest/two-factor/recovery-code/")({
  component: TwoFactorRecoveryCodePage,
});
