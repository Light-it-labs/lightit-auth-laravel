# Consumer stubs

The generated auth TypeScript in `../expected/src` imports a handful of modules
this package never generates — `env`, `i18n`, `services/types`,
`services/users/schemas`, `services/users/types`, `services/auth/factories`,
and `config/query-client`. Every real consuming project already has these
(they come from the React starter template this package targets, or — for
`services/auth/factories` — from whichever current-user query the consumer's
own auth setup already provides), so the golden fixtures assume they exist and
don't ship them.

These files are minimal, hand-written stand-ins for that assumed contract —
just enough shape to let `tsc` resolve the imports and check the generated
code's own usage against them. They are **not** golden output and are not
asserted against by any PHP test.

Because they're hand-written, they can't catch a real mismatch between what
the generator assumes and what an actual consuming project provides — only a
project-level check (or an end-to-end fixture project) could do that. What
they do catch: syntax errors, wrong types, and broken references inside the
generated code itself, which is the gap this check exists to close.
