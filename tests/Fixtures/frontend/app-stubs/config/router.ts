import { createRouter } from "@tanstack/react-router";

import { routeTree } from "@/routeTree.gen";

// Mirrors src/config/router.ts in react-template. Registering the router this way
// is what makes useNavigate()/Link/useSearch() type-check against the real route
// tree - without it, TanStack Router falls back to loose, unconstrained types and
// a route that forgets to declare its own search params would type-check anyway.
export const router = createRouter({ routeTree });

declare module "@tanstack/react-router" {
  // eslint-disable-next-line @typescript-eslint/consistent-type-definitions
  interface Register {
    router: typeof router;
  }
}
