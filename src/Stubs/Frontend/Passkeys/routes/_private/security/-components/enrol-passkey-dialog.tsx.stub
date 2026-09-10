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
import { useEnrolPasskey } from "@/services/auth/passkeys/actions";
import { getPasskeyNameSchema } from "@/services/auth/passkeys/schemas";
import type { Passkey, PasskeyCeremonyError } from "@/services/auth/passkeys/types";

type EnrolPasskeyDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  token: string;
  onEnrolled: (passkey: Passkey) => void;
};

const isPasskeyCeremonyError = (error: unknown): error is PasskeyCeremonyError => {
  return typeof error === "object" && error !== null && "reason" in error;
};

// mapPasskeyCeremonyError() surfaces DOMException.name-derived reasons rather
// than one generic message, so a misconfigured RP ID (security_error) reads
// differently from a user simply cancelling the prompt.
const ceremonyErrorMessage = (error: PasskeyCeremonyError): string => {
  switch (error.reason) {
    case "already_registered":
      return "This authenticator already holds a passkey for this app.";
    case "cancelled_or_timed_out":
      return "That was cancelled, so nothing was added.";
    case "security_error":
      return "This device can't complete a passkey ceremony here. Check your passkey configuration.";
    default:
      return "That passkey could not be added. Try again.";
  }
};

export const EnrolPasskeyDialog = ({ open, onOpenChange, token, onEnrolled }: EnrolPasskeyDialogProps) => {
  const [name, setName] = useState("");
  const [validationError, setValidationError] = useState<string | null>(null);

  const { mutate, isPending, error, reset } = useEnrolPasskey({
    onSuccess: (passkey) => {
      setName("");
      onEnrolled(passkey);
      onOpenChange(false);
    },
  });

  const handleOpenChange = (nextOpen: boolean) => {
    if (!nextOpen) {
      setName("");
      setValidationError(null);
      reset();
    }

    onOpenChange(nextOpen);
  };

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const parsed = getPasskeyNameSchema().safeParse({ name });

    if (!parsed.success) {
      setValidationError(parsed.error.issues[0]?.message ?? "Invalid name");

      return;
    }

    setValidationError(null);
    mutate({ token, name });
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Add a passkey</DialogTitle>
          <DialogDescription>
            Name it after the device you&apos;re adding it from, so you can tell your passkeys apart later.
          </DialogDescription>
        </DialogHeader>
        <Form onSubmit={handleSubmit}>
          <Input
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="e.g. MacBook"
            autoFocus
          />
          {validationError ? (
            <Alert variant="destructive">
              <AlertDescription>{validationError}</AlertDescription>
            </Alert>
          ) : null}
          {error && isPasskeyCeremonyError(error) ? (
            <Alert variant="destructive">
              <AlertDescription>{ceremonyErrorMessage(error)}</AlertDescription>
            </Alert>
          ) : null}
          <DialogFooter>
            <Button type="submit" disabled={isPending}>
              Add passkey
            </Button>
          </DialogFooter>
        </Form>
      </DialogContent>
    </Dialog>
  );
};
