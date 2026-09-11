import { useState, type FormEvent } from "react";
import { useNavigate, useSearch } from "@tanstack/react-router";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Form } from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { persistSession } from "@/services/auth/session";
import { useVerifyRecoveryCode } from "@/services/auth/two-factor/actions";
import { getRecoveryCodeSchema } from "@/services/auth/two-factor/schemas";

type RecoveryCodeSearch = {
  token: string;
};

export default function TwoFactorRecoveryCodePage() {
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
          <Alert variant="destructive">
            <AlertDescription>
              You have {remainingRecoveryCodes} recovery code left. Generate new ones from your account
              settings.
            </AlertDescription>
          </Alert>
        ) : null}
        <Button onClick={() => void navigate({ to: "/" })}>Continue</Button>
      </div>
    );
  }

  return (
    <div>
      <h1>Use a recovery code</h1>
      <Form onSubmit={handleSubmit}>
        <Input
          value={recoveryCode}
          onChange={(event) => setRecoveryCode(event.target.value)}
          aria-label="Recovery code"
        />
        {validationError ? (
          <Alert variant="destructive">
            <AlertDescription>{validationError}</AlertDescription>
          </Alert>
        ) : null}
        {isError ? (
          <Alert variant="destructive">
            <AlertDescription>That recovery code didn&apos;t work.</AlertDescription>
          </Alert>
        ) : null}
        <Button type="submit" disabled={isPending}>
          Verify
        </Button>
      </Form>
    </div>
  );
}
