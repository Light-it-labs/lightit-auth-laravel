import { queryOptions } from "@tanstack/react-query";

type CurrentUser = {
  permissions: string[];
  roles: string[];
};

export const currentUserQuery = () => {
  return queryOptions({
    queryKey: ["auth", "me"] as const,
    queryFn: async (): Promise<CurrentUser> => {
      return { permissions: [], roles: [] };
    },
  });
};
