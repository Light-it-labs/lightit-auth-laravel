type PersistableSession = {
  accessToken: string;
  tokenType: string;
  expiresIn: number | null;
};

// In-memory only, by design: persisting to localStorage/sessionStorage is a decision
// this file exists specifically to defer until the Bearer-vs-cookie session question
// is resolved (see the frontend TODO doc). Swap the storage here instead of in
// every screen once that decision lands.
let accessToken: string | null = null;

export const persistSession = (result: PersistableSession): void => {
  accessToken = result.accessToken;
};

export const clearSession = (): void => {
  accessToken = null;
};

export const getAccessToken = (): string | null => {
  return accessToken;
};
