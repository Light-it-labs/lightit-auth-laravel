import type { HTMLAttributes, ReactNode } from "react";

export type DialogProps = {
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  children?: ReactNode;
};

export const Dialog = ({ children }: DialogProps) => {
  return <div>{children}</div>;
};

export const DialogContent = (props: HTMLAttributes<HTMLDivElement>) => {
  return <div {...props} />;
};

export const DialogHeader = (props: HTMLAttributes<HTMLDivElement>) => {
  return <div {...props} />;
};

export const DialogFooter = (props: HTMLAttributes<HTMLDivElement>) => {
  return <div {...props} />;
};

export const DialogTitle = (props: HTMLAttributes<HTMLHeadingElement>) => {
  return <h2 {...props} />;
};

export const DialogDescription = (props: HTMLAttributes<HTMLParagraphElement>) => {
  return <p {...props} />;
};
