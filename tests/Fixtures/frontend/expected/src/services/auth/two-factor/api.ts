import axios from "axios";
import { deepCamelKeys } from "string-ts";

import { env } from "@/config/env";
import type {
  BearerTokenResult,
  MessageResult,
  OneTimePasswordPayload,
  PasswordPayload,
  RecoveryCodePayload,
  RegenerateRecoveryCodesResult,
  TwoFactorResetChallengeResult,
  TwoFactorSetupPayload,
  VerifyRecoveryCodeResult,
} from "./types";

// The 2FA exchange endpoints authenticate via a short-lived, encrypted challenge
// token resent as a manual Authorization header - never through the app's normal
// authenticated client, since the holder isn't fully logged in yet.
const twoFactorApi = axios.create({
  baseURL: env.VITE_API_URL,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

twoFactorApi.interceptors.response.use((response) => {
  response.data = deepCamelKeys(response.data);

  return response;
});

const bearer = (token: string) => {
  return { headers: { Authorization: `Bearer ${token}` } };
};

export const setupTwoFactor = async ({ token }: { token: string }): Promise<TwoFactorSetupPayload> => {
  const response = await twoFactorApi.post("2fa/setup", undefined, bearer(token));

  return response.data.data;
};

export const completeTwoFactor = async ({
  token,
  oneTimePassword,
}: { token: string } & OneTimePasswordPayload): Promise<BearerTokenResult> => {
  const response = await twoFactorApi.post(
    "2fa/complete",
    { one_time_password: oneTimePassword },
    bearer(token),
  );

  return response.data.data;
};

export const verifyRecoveryCode = async ({
  token,
  recoveryCode,
}: { token: string } & RecoveryCodePayload): Promise<VerifyRecoveryCodeResult> => {
  const response = await twoFactorApi.post(
    "2fa/verify-recovery-code",
    { recovery_code: recoveryCode },
    bearer(token),
  );

  return response.data.data;
};

export const requestTwoFactorReset = async ({
  token,
  password,
}: { token: string } & PasswordPayload): Promise<TwoFactorResetChallengeResult> => {
  const response = await twoFactorApi.post(
    "2fa/request-reset",
    { password },
    bearer(token),
  );

  return response.data.data;
};

export const resetTwoFactor = async ({ token }: { token: string }): Promise<MessageResult> => {
  const response = await twoFactorApi.post("2fa/reset", undefined, bearer(token));

  return response.data.data;
};

export const disableTwoFactor = async ({
  token,
  password,
}: { token: string } & PasswordPayload): Promise<MessageResult> => {
  const response = await twoFactorApi.post("2fa/disable", { password }, bearer(token));

  return response.data.data;
};

export const regenerateRecoveryCodes = async ({
  token,
  password,
}: { token: string } & PasswordPayload): Promise<RegenerateRecoveryCodesResult> => {
  const response = await twoFactorApi.post(
    "2fa/regenerate-recovery-codes",
    { password },
    bearer(token),
  );

  return response.data.data;
};
