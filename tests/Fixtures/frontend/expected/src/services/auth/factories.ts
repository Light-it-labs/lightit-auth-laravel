import { createQueryKeys } from "@lukemorales/query-key-factory";

import { getCurrentUser, login, logout } from "./api";

export const queries = createQueryKeys("auth", {
  currentUser: {
    queryKey: null,
    queryFn: () => {
      return getCurrentUser();
    },
  },
});

export const mutations = { login, logout };
