import { useEffect, type FormEvent } from "react";
import { useNavigate, useSearch } from "@tanstack/react-router";

import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { useSetupTwoFactor } from "@/services/auth/two-factor/actions";
import { RecoveryCodes } from "../-components/recovery-codes";

type SetupSearch = {
  token: string;
};

export default function TwoFactorSetupPage() {
  const { token } = useSearch({ strict: false }) as unknown as SetupSearch;
  const navigate = useNavigate();
  const { mutate, data, isPending, isError } = useSetupTwoFactor();

  useEffect(() => {
    mutate({ token });
    // Runs once per challenge token - re-triggering on every render would burn a
    // fresh secret/QR pair each time.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  const handleContinue = (event: FormEvent<HTMLButtonElement>) => {
    event.preventDefault();

    void navigate({ to: "/two-factor", search: { token, flow: "setup" } });
  };

  if (isError) {
    // The setup endpoint doesn't return an error code, so a network failure and an
    // expired/invalid challenge token look identical here - retrying is the only
    // generic recovery available without inventing an API contract.
    return (
      <div>
        <p role="alert">Something went wrong generating your two-factor setup.</p>
        <Button onClick={() => mutate({ token })}>Try again</Button>
      </div>
    );
  }

  if (isPending || !data) {
    return <p>Generating your two-factor setup...</p>;
  }

  return (
    <div>
      <h1>Set up two-factor authentication</h1>
      {/* The backend returns `qr` as a raw SVG string. Rendering it through `<img>`
          off a data URI - instead of `dangerouslySetInnerHTML` - keeps any script or
          event-handler content inert: an image resource doesn't execute embedded
          scripts the way inline SVG in the live DOM does. */}
      <img
        src={`data:image/svg+xml,${encodeURIComponent(data.qr)}`}
        alt="Scan this QR code with your authenticator app"
      />
      <p>Can&apos;t scan the code? Enter this manually: {data.secret}</p>
      <Separator />
      <RecoveryCodes recoveryCodes={data.recoveryCodes} />
      <Button onClick={handleContinue}>Continue</Button>
    </div>
  );
}
