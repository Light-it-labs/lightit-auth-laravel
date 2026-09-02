import { privateApi } from "@/config/api";
import { useAuthStore } from "@/stores/use-auth-store";

export const LogoutButton = () => {
  const { user } = useAuthStore();

  return privateApi.post("auth/logout").then(() => {
    return user;
  });
};
