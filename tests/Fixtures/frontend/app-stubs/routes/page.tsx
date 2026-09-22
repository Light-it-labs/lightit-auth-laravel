import { createFileRoute } from "@tanstack/react-router";

// Stand-in for the consuming app's own home route ("/"), which every
// react-template project has (see src/routes/_private/page.tsx there) and which
// the generated 2FA screens navigate back to on success. Not part of this
// package's own output.
const HomePage = () => null;

export const Route = createFileRoute("/")({ component: HomePage });
