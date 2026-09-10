import { z } from "zod";

import i18n from "@/i18n";

export const getGoogleLoginSchema = () => {
  return z.object({
    idToken: z.string().min(1, {
      message: i18n.t("form.errors.required", { field: i18n.t("form.googleIdToken") }),
    }),
  });
};
