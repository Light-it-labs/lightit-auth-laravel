import { createRootRoute, Outlet } from "@tanstack/react-router";

// Every react-template project has a root route (see src/routes/__root.tsx there).
// This is the minimal shell a consuming app already provides - not part of this
// package's own output - so the real TanStack Router codegen has a tree to attach to.
const RootComponent = () => {
  return <Outlet />;
};

export const Route = createRootRoute({ component: RootComponent });
