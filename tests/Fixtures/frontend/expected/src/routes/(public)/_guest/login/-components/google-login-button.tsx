import { useNavigate } from "@tanstack/react-router";

import { useGoogleIdentityServices } from "@/hooks/use-google-identity-services";
import { persistSession } from "@/services/auth/session";
import { useGoogleLogin } from "@/services/auth/sso/google/actions";

export const GoogleLoginButton = () => {
  const navigate = useNavigate();

  const { mutate, isError: loginFailed } = useGoogleLogin({
    onSuccess: (result) => {
      persistSession(result);
      void navigate({ to: "/" });
    },
  });

  const { containerRef, isError: identityServicesFailed } = useGoogleIdentityServices((idToken) =>
    mutate({ idToken }),
  );

  return (
    <div>
      <div ref={containerRef} />
      {identityServicesFailed ? <p>Couldn&apos;t load Google sign-in. Try again later.</p> : null}
      {loginFailed ? <p>Google sign-in failed. Try again.</p> : null}
    </div>
  );
};
