export type BearerTokenResult = {
  accessToken: string;
  tokenType: "Bearer";
  expiresIn: number;
};

export type TwoFactorChallengeResult = {
  accessToken: string;
  tokenType: "setup_required" | "verification_required";
  expiresIn: number;
};

export type TwoFactorResetChallengeResult = {
  accessToken: string;
  tokenType: "reset_required";
  expiresIn: number;
};

export type LoginResult = BearerTokenResult | TwoFactorChallengeResult;

export const isTwoFactorChallenge = (result: LoginResult): result is TwoFactorChallengeResult => {
  return result.tokenType === "setup_required" || result.tokenType === "verification_required";
};

export const isSetupRequired = (result: LoginResult): boolean => {
  return result.tokenType === "setup_required";
};

export const isVerificationRequired = (result: LoginResult): boolean => {
  return result.tokenType === "verification_required";
};

export type TwoFactorSetupPayload = {
  qr: string;
  secret: string;
  recoveryCodes: string[];
};

export type OneTimePasswordPayload = {
  oneTimePassword: string;
};

export type RecoveryCodePayload = {
  recoveryCode: string;
};

export type PasswordPayload = {
  password: string;
};

export type VerifyRecoveryCodeResult = BearerTokenResult & {
  remainingRecoveryCodes: number;
};

export type RegenerateRecoveryCodesResult = {
  recoveryCodes: string[];
};

export type MessageResult = {
  message: string;
};
