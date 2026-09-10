import { useNavigate } from "@tanstack/react-router";

import { useGoogleIdentityServices } from "@/hooks/use-google-identity-services";
import { persistSession } from "@/services/auth/session";
import { useGoogleLogin } from "@/services/auth/sso/google/actions";

export const GoogleLoginButton = () => {
  const navigate = useNavigate();

  const { mutate, isError } = useGoogleLogin({
    onSuccess: (result) => {
      persistSession(result);
      void navigate({ to: "/" });
    },
  });

  const { containerRef } = useGoogleIdentityServices((idToken) => mutate({ idToken }));

  return (
    <div>
      <div ref={containerRef} />
      {isError ? <p>Google sign-in failed. Try again.</p> : null}
    </div>
  );
};
