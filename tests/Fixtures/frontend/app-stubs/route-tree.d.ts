// In a real consuming project, TanStack Router's Vite plugin generates
// `routeTree.gen.ts` from every route file under `src/routes` and augments
// `FileRoutesByPath` with one entry per route, which is what makes
// `createFileRoute("/some/path/")` type-check at all (see fileRoute.d.ts in
// @tanstack/router-core - `FileRoutesByPath` ships empty). This fixture never
// runs that plugin, so it hand-declares only the entries the generated 2FA
// screens themselves call `createFileRoute` with - just enough for `tsc` to
// resolve them, not a stand-in for the plugin's real output.
declare module "@tanstack/router-core" {
  interface FileRoutesByPath {
    "/(public)/_guest/two-factor/": {
      parentRoute: any;
      id: "/(public)/_guest/two-factor/";
      path: "/two-factor";
      fullPath: "/two-factor";
    };
    "/(public)/_guest/two-factor/setup/": {
      parentRoute: any;
      id: "/(public)/_guest/two-factor/setup/";
      path: "/setup";
      fullPath: "/two-factor/setup";
    };
    "/(public)/_guest/two-factor/recovery-code/": {
      parentRoute: any;
      id: "/(public)/_guest/two-factor/recovery-code/";
      path: "/recovery-code";
      fullPath: "/two-factor/recovery-code";
    };
  }
}
