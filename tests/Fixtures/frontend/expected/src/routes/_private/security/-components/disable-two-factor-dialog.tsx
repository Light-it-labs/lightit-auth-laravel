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
import { useDisableTwoFactor } from "@/services/auth/two-factor/actions";
import { getPasswordConfirmationSchema } from "@/services/auth/two-factor/schemas";

type DisableTwoFactorDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  token: string;
};

export const DisableTwoFactorDialog = ({ open, onOpenChange, token }: DisableTwoFactorDialogProps) => {
  const [password, setPassword] = useState("");
  const [validationError, setValidationError] = useState<string | null>(null);

  const { mutate, isPending, isError, reset } = useDisableTwoFactor({
    onSuccess: () => {
      setPassword("");
      onOpenChange(false);
    },
  });

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
          <DialogTitle>Disable two-factor authentication</DialogTitle>
          <DialogDescription>
            Confirm your password to turn off two-factor authentication for your account.
          </DialogDescription>
        </DialogHeader>
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
              <AlertDescription>
                {/* The backend returns 403 when 2FA is mandatory for this app rather than a
                    distinct error code, so a wrong password and a blocked disable look the
                    same here - both need the same "nothing changed" message. */}
                That didn&apos;t work. Check your password, or two-factor may be required for this app.
              </AlertDescription>
            </Alert>
          ) : null}
          <DialogFooter>
            <Button type="submit" variant="destructive" disabled={isPending}>
              Disable two-factor authentication
            </Button>
          </DialogFooter>
        </Form>
      </DialogContent>
    </Dialog>
  );
};
