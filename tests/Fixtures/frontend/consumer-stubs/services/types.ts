import type { UseMutationOptions, UseQueryOptions } from "@tanstack/react-query";

export type UseQueryProps<T extends { queryFn: (...args: any[]) => any }> = Omit<
  UseQueryOptions<Awaited<ReturnType<T["queryFn"]>>, Error, Awaited<ReturnType<T["queryFn"]>>, any>,
  "queryKey" | "queryFn"
>;

export type UseMutationProps<T extends (...args: any[]) => any> = Omit<
  UseMutationOptions<Awaited<ReturnType<T>>, Error, Parameters<T>[0]>,
  "mutationFn"
>;
