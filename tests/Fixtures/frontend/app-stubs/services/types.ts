import type { UseMutationOptions } from "@tanstack/react-query";

export type UseMutationProps<TFn extends (...args: never[]) => unknown> = Omit<
  UseMutationOptions<Awaited<ReturnType<TFn>>, unknown, Parameters<TFn>[0]>,
  "mutationFn"
>;
