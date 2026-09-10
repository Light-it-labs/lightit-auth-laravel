import type { HTMLAttributes, PropsWithChildren } from "react";

export type DialogProps = PropsWithChildren<{
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
}>;

export const Dialog = ({ children }: DialogProps) => {
  return <>{children}</>;
};

export const DialogContent = (props: HTMLAttributes<HTMLDivElement>) => {
  return <div {...props} />;
};

export const DialogHeader = (props: HTMLAttributes<HTMLDivElement>) => {
  return <div {...props} />;
};

export const DialogTitle = (props: HTMLAttributes<HTMLHeadingElement>) => {
  return <h2 {...props} />;
};

export const DialogDescription = (props: HTMLAttributes<HTMLParagraphElement>) => {
  return <p {...props} />;
};

export const DialogFooter = (props: HTMLAttributes<HTMLDivElement>) => {
  return <div {...props} />;
};
