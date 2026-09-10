import { useState, type FormEvent } from "react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Form } from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { useRegenerateRecoveryCodes } from "@/services/auth/two-factor/actions";
import { getPasswordConfirmationSchema } from "@/services/auth/two-factor/schemas";
// Reaches into the guest-side setup flow's -components folder rather than duplicating
// the display - this is the same recovery-codes list shown right after initial setup.
import { RecoveryCodes } from "../../../(public)/_guest/two-factor/-components/recovery-codes";

type RegenerateRecoveryCodesDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  token: string;
};

export const RegenerateRecoveryCodesDialog = ({
  open,
  onOpenChange,
  token,
}: RegenerateRecoveryCodesDialogProps) => {
  const [password, setPassword] = useState("");
  const [validationError, setValidationError] = useState<string | null>(null);

  const { mutate, isPending, isError, data, reset } = useRegenerateRecoveryCodes();

  const handleOpenChange = (nextOpen: boolean) => {
    if (!nextOpen) {
      setPassword("");
      setValidationError(null);
      reset();
    }

    onOpenChange(nextOpen);
  };

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const parsed = getPasswordConfirmationSchema().safeParse({ password });

    if (!parsed.success) {
      setValidationError(parsed.error.issues[0]?.message ?? "Invalid password");

      return;
    }

    setValidationError(null);
    mutate({ token, password });
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Regenerate recovery codes</DialogTitle>
          <DialogDescription>
            This invalidates your existing recovery codes and replaces them with a new set.
          </DialogDescription>
        </DialogHeader>
        {data ? (
          <RecoveryCodes recoveryCodes={data.recoveryCodes} />
        ) : (
          <Form onSubmit={handleSubmit}>
            <Input
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              placeholder="Password"
              autoFocus
            />
            {validationError ? (
              <Alert variant="destructive">
                <AlertDescription>{validationError}</AlertDescription>
              </Alert>
            ) : null}
            {isError ? (
              <Alert variant="destructive">
                <AlertDescription>That password didn&apos;t work.</AlertDescription>
              </Alert>
            ) : null}
            <DialogFooter>
              <Button type="submit" disabled={isPending}>
                Regenerate codes
              </Button>
            </DialogFooter>
          </Form>
        )}
      </DialogContent>
    </Dialog>
  );
};
