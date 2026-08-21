import { useMutation, useQuery } from "@tanstack/react-query";

import { queryClient } from "@/config/query-client";
import type { UseMutationProps, UseQueryProps } from "@/services/types";
import { mutations, queries } from "./factories";

export const currentUserQuery = queries.currentUser;

export const useCurrentUser = (props?: UseQueryProps<typeof queries.currentUser>) => {
  return useQuery({ ...queries.currentUser, ...props });
};

export const useLogin = (props?: UseMutationProps<typeof mutations.login>) => {
  return useMutation({
    mutationFn: mutations.login,
    ...props,
    onSuccess: async (...args) => {
      await queryClient.refetchQueries({ queryKey: queries.currentUser.queryKey });
      await props?.onSuccess?.(...args);
    },
  });
};

export const useLogout = (props?: UseMutationProps<typeof mutations.logout>) => {
  return useMutation({
    mutationFn: mutations.logout,
    ...props,
  });
};
