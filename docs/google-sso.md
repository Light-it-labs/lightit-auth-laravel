## Google Single Sign-On (SSO)

> [!WARNING]
> Not offered by `auth:setup`. It is being replaced by Social login (PR #101), which adds the
> frontend and a provider registry.

The existing backend code (`GoogleLoginController`) verifies a Google ID token, finds or creates
the user by email and logs in through `LoginByUserAction::execute()`: it returns the
boilerplate's `UserResource` with a session cookie or, when 2FA applies to the user, the 2FA
challenge.
