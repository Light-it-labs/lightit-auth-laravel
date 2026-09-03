import { z } from "zod";

import i18n from "@/i18n";

export const getOneTimePasswordSchema = () => {
  return z.object({
    oneTimePassword: z.string().regex(/^\d{6}$/, {
      message: i18n.t("form.errors.invalidField", { field: i18n.t("form.otp") }),
    }),
  });
};

export const getRecoveryCodeSchema = () => {
  return z.object({
    recoveryCode: z.string().min(1, {
      message: i18n.t("form.errors.required", { field: i18n.t("form.recoveryCode") }),
    }),
  });
};

export const getPasswordConfirmationSchema = () => {
  return z.object({
    password: z.string().min(1, {
      message: i18n.t("form.errors.required", { field: i18n.t("form.password") }),
    }),
  });
};
