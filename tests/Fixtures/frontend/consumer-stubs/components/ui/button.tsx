import type { ButtonHTMLAttributes } from "react";

export type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: "default" | "outline" | "ghost" | "destructive";
};

export const Button = ({ variant: _variant, ...props }: ButtonProps) => {
  return <button {...props} />;
};
