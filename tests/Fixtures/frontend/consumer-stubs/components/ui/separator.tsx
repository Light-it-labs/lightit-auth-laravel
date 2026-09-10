import type { HTMLAttributes } from "react";

export type SeparatorProps = HTMLAttributes<HTMLHRElement>;

export const Separator = (props: SeparatorProps) => {
  return <hr {...props} />;
};
