import { useState, type FormEvent } from "react";
import { useNavigate } from "@tanstack/react-router";

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
import { useRequestTwoFactorReset } from "@/services/auth/two-factor/actions";
import { getPasswordConfirmationSchema } from "@/services/auth/two-factor/schemas";

type RequestTwoFactorResetDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  token: string;
};

export const RequestTwoFactorResetDialog = ({
  open,
  onOpenChange,
  token,
}: RequestTwoFactorResetDialogProps) => {
  const navigate = useNavigate();
  const [password, setPassword] = useState("");
  const [validationError, setValidationError] = useState<string | null>(null);

  const { mutate, isPending, isError, data, reset } = useRequestTwoFactorReset();

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

  const handleContinue = () => {
    if (!data) {
      return;
    }

    // The reset endpoint issues its own short-lived challenge token, separate from
    // the real access token above - it travels through the URL to the guest-side
    // confirm screen, exactly like the setup/login challenge tokens do.
    void navigate({ to: "/two-factor/reset", search: { token: data.accessToken } });
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Reset two-factor authentication</DialogTitle>
          <DialogDescription>
            Confirm your password to start a reset. The next step clears your current
            two-factor setup, so you&apos;ll need to set it up again afterward.
          </DialogDescription>
        </DialogHeader>
        {data ? (
          <div>
            <Alert>
              <AlertDescription>
                Ready to reset. Continue to finish clearing your two-factor setup.
              </AlertDescription>
            </Alert>
            <DialogFooter>
              <Button type="button" onClick={handleContinue}>
                Continue
              </Button>
            </DialogFooter>
          </div>
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
                Request reset
              </Button>
            </DialogFooter>
          </Form>
        )}
      </DialogContent>
    </Dialog>
  );
};
