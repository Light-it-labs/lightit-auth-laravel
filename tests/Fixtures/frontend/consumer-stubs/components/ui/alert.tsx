import type { HTMLAttributes } from "react";

export type AlertProps = HTMLAttributes<HTMLDivElement> & {
  variant?: "default" | "destructive";
};

export const Alert = ({ variant: _variant, ...props }: AlertProps) => {
  return <div role="alert" {...props} />;
};

export const AlertTitle = (props: HTMLAttributes<HTMLParagraphElement>) => {
  return <p {...props} />;
};

export const AlertDescription = (props: HTMLAttributes<HTMLDivElement>) => {
  return <div {...props} />;
};
