import { useEffect, useRef } from "react";

import { env } from "@/config/env";

const GOOGLE_IDENTITY_SERVICES_SRC = "https://accounts.google.com/gsi/client";

type GoogleCredentialResponse = {
  credential: string;
};

type GoogleIdentityServices = {
  accounts: {
    id: {
      initialize: (config: {
        client_id: string;
        callback: (response: GoogleCredentialResponse) => void;
      }) => void;
      renderButton: (
        parent: HTMLElement,
        options: { type: "standard"; theme: "outline"; size: "large"; width: string },
      ) => void;
    };
  };
};

declare global {
  interface Window {
    google?: GoogleIdentityServices;
  }
}

// The ID token can only come from Google's own client-side JS, and this package never
// installs frontend dependencies - so the Identity Services script is loaded from its
// CDN URL instead of an npm package such as @react-oauth/google.
const loadGoogleIdentityServices = (): Promise<GoogleIdentityServices> => {
  if (window.google) {
    return Promise.resolve(window.google);
  }

  const existing = document.querySelector<HTMLScriptElement>(`script[src="${GOOGLE_IDENTITY_SERVICES_SRC}"]`);

  return new Promise((resolve, reject) => {
    const script = existing ?? document.createElement("script");

    script.addEventListener("load", () => {
      if (window.google) {
        resolve(window.google);
      } else {
        reject(new Error("Google Identity Services script loaded without exposing window.google"));
      }
    });
    script.addEventListener("error", () => {
      reject(new Error("Failed to load the Google Identity Services script"));
    });

    if (!existing) {
      script.src = GOOGLE_IDENTITY_SERVICES_SRC;
      script.async = true;
      document.head.appendChild(script);
    }
  });
};

export const useGoogleIdentityServices = (onCredential: (idToken: string) => void) => {
  const containerRef = useRef<HTMLDivElement>(null);
  const onCredentialRef = useRef(onCredential);
  onCredentialRef.current = onCredential;

  useEffect(() => {
    let cancelled = false;

    void loadGoogleIdentityServices().then((google) => {
      if (cancelled || !containerRef.current) {
        return;
      }

      google.accounts.id.initialize({
        client_id: env.VITE_GOOGLE_CLIENT_ID,
        callback: (response) => onCredentialRef.current(response.credential),
      });

      google.accounts.id.renderButton(containerRef.current, {
        type: "standard",
        theme: "outline",
        size: "large",
        width: "100%",
      });
    });

    return () => {
      cancelled = true;
    };
  }, []);

  return { containerRef };
};
