import { useState, type FormEvent } from "react";
import { Link, useNavigate, useSearch } from "@tanstack/react-router";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Form } from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { persistSession } from "@/services/auth/session";
import { useCompleteTwoFactor } from "@/services/auth/two-factor/actions";
import { getOneTimePasswordSchema } from "@/services/auth/two-factor/schemas";

type ChallengeSearch = {
  token: string;
  flow?: "setup" | "login";
};

export default function TwoFactorChallengePage() {
  const { token, flow } = useSearch({ strict: false }) as unknown as ChallengeSearch;
  const navigate = useNavigate();
  const isSetupFlow = flow === "setup";
  const recoveryCodeSearch = { token };

  const [oneTimePassword, setOneTimePassword] = useState("");
  const [validationError, setValidationError] = useState<string | null>(null);

  const { mutate, isPending, isError } = useCompleteTwoFactor({
    onSuccess: (result) => {
      persistSession(result);
      void navigate({ to: "/" });
    },
  });

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const parsed = getOneTimePasswordSchema().safeParse({ oneTimePassword });

    if (!parsed.success) {
      setValidationError(parsed.error.issues[0]?.message ?? "Invalid code");

      return;
    }

    setValidationError(null);
    mutate({ token, oneTimePassword });
  };

  return (
    <div>
      <h1>{isSetupFlow ? "Confirm two-factor setup" : "Enter your two-factor code"}</h1>
      <Form onSubmit={handleSubmit}>
        <Input
          value={oneTimePassword}
          onChange={(event) => setOneTimePassword(event.target.value)}
          inputMode="numeric"
          maxLength={6}
        />
        {validationError ? (
          <Alert variant="destructive">
            <AlertDescription>{validationError}</AlertDescription>
          </Alert>
        ) : null}
        {isError ? (
          <Alert variant="destructive">
            <AlertDescription>That code didn&apos;t work. Try again.</AlertDescription>
          </Alert>
        ) : null}
        <Button type="submit" disabled={isPending}>
          Confirm
        </Button>
      </Form>
      <Link to="/two-factor/recovery-code" search={recoveryCodeSearch}>
        Lost your device? Use a recovery code
      </Link>
    </div>
  );
}
