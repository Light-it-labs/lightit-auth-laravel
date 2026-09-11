import type { UseMutationOptions, UseQueryOptions } from "@tanstack/react-query";

export type UseMutationProps<TFn extends (...args: never[]) => unknown> = Omit<
  UseMutationOptions<Awaited<ReturnType<TFn>>, unknown, Parameters<TFn>[0]>,
  "mutationFn"
>;

export type UseQueryProps<T extends { queryFn: (...args: never[]) => unknown }> = Omit<
  UseQueryOptions<Awaited<ReturnType<T["queryFn"]>>, unknown, Awaited<ReturnType<T["queryFn"]>>, readonly unknown[]>,
  "queryKey" | "queryFn"
>;
