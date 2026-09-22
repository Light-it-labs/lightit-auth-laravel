import type { FormHTMLAttributes } from "react";

export const Form = (props: FormHTMLAttributes<HTMLFormElement>) => {
  return <form {...props} />;
};
