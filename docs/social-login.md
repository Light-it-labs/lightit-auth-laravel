## Social Login

Let anyone sign in from the login screen with an account from another provider. Google is the
first provider; the backend is built so that adding Apple, Facebook or any other one is a new
class and one config line (see [Adding a provider](#adding-a-provider)).

The frontend gets the provider's signed token (for Google, the ID token from
[Google Identity Services](https://developers.google.com/identity/gsi/web)) and sends it to the
backend. The backend checks the token itself with
[firebase/php-jwt](https://github.com/firebase/php-jwt) against Google's published keys, links
the identity to a user and signs that user in with the cookie session, through the same 2FA gate
as the password login.

### Setup

Run `php artisan auth:setup` and pick **Social Login (Google)**. It requires
`firebase/php-jwt`, then writes into your app, skipping every file that already exists:

- the `SocialProvider` contract, `GoogleProvider`, `GoogleSigningKeys`,
  `SocialProviderRegistry`, `SocialLoginRateLimiter`, the `SocialAccount` model, the action,
  request, controller, DTOs and exceptions under `src/Authentication/`
- `LoginByUserAction` and the 2FA gate it calls, shared with 2FA and passkeys, so a backend
  without 2FA still compiles
- the migration that creates the `social_accounts` table
- `config/social-login.php`
- `routes/social-login.php`, required from `routes/api.php` (see [API](#api))
- the frontend service, the "Sign in with Google" button and its hook, when it finds the React
  project. Pass `--frontend-path=<path>` when it is not a sibling directory named `frontend`,
  `front` or `<app>-frontend`.
- the two checklists described in [After `auth:setup`](#after-authsetup)

No backend file of yours needs editing. These steps are yours; they are the same steps the two
checklists list.

#### 1. Run the migration

```bash
php artisan migrate
```

#### 2. Create the Google OAuth client

In the [Google Cloud console](https://console.cloud.google.com/apis/credentials):

1. Pick or create a project. If it asks for it, configure the OAuth consent screen (app name,
   support email; the `openid`, `email` and `profile` scopes are the defaults).
2. **Create credentials → OAuth client ID**, application type **Web application**.
3. Under **Authorized JavaScript origins**, add every frontend URL the button will be shown on,
   with scheme and port: `http://localhost:5173` for local development, your real domains for
   the other environments. Google refuses to render the button on any other origin.
4. Leave **Authorized redirect URIs** empty: the button uses a popup, not a redirect.
5. Copy the **Client ID** (`…apps.googleusercontent.com`). It is public; no client secret is
   used.

#### 3. Set the client id

`config/social-login.php` reads it from `.env`:

```dotenv
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
```

Every Google token must be issued for this client id (its `aud` claim). Until it is set, Google
sign-in answers `500` and logs an error that names `GOOGLE_CLIENT_ID`, instead of accepting
tokens issued for any other app.

#### 4. Frontend: install the dependencies

The button imports `@tanstack/react-query`, `@tanstack/react-router`, `axios` and `sonner`. The
generator never runs a package manager: it prints the add command for the missing ones, and the
checklist repeats it. Google Identity Services is a script, not a package: the button loads
`https://accounts.google.com/gsi/client` itself. If your app sends a Content Security Policy,
allow that script and `https://accounts.google.com` as a frame source.

#### 5. Frontend: set `VITE_GOOGLE_CLIENT_ID`

The same client id as the backend. The generator does not edit `src/config/env.ts`:

1. In `.env` (and `.env.example`, empty): `VITE_GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com`
2. In `src/config/env.ts`, inside `client`: `VITE_GOOGLE_CLIENT_ID: z.string().min(1),`
3. In every deploy workflow that builds the app, next to the other `VITE_*` variables.

The button reads `env.VITE_GOOGLE_CLIENT_ID`, so the frontend does not type-check until step 2
is done.

#### 6. Frontend: add the button to the login form

In `src/routes/(public)/_guest/login/-components/login-form.tsx`:

1. Add `import { GoogleLoginButton } from "./google-login-button";` as the last import, or right
   before `import { PasskeyLoginButton } from "./passkey-login-button";` if you have it.
2. Add `<GoogleLoginButton />` on the line after the submit button's closing `</Button>` (the
   one showing `{t("login.login")}`), below `<PasskeyLoginButton />` if you have it.

The button lives outside the form's fields and never submits it.

#### 7. Frontend: add the i18n keys

`src/i18n/locales/en.json` is not edited for you. Add the `socialLogin` block at the top level,
then translate it in `es.json`. The exact English strings are the JSON block in
`AUTH-SOCIAL-FRONTEND-TODO.md`; this repository keeps it in
`src/Stubs/Frontend/SocialLogin/AUTH-SOCIAL-FRONTEND-TODO.md.stub`. The button also reuses
`login.success` and `common.requestError`, which the template already has.

### After `auth:setup`

`auth:setup` writes the social login files and leaves two checklists in your projects:

| File | Where | What it lists |
| --- | --- | --- |
| `AUTH-SOCIAL-TODO.md` | backend root | Run `migrate`, create the Google OAuth client, set `GOOGLE_CLIENT_ID` |
| `AUTH-SOCIAL-FRONTEND-TODO.md` | frontend root | Install dependencies, set `VITE_GOOGLE_CLIENT_ID`, add the button, add the i18n keys |

The command also prints the same steps when it finishes. The frontend checklist is
generated for your package manager. Work through each checklist, tick the
boxes, and **delete the file when every box is ticked** — it is not read by the app
and nothing breaks without it. Running `auth:setup` again never overwrites it.

If you lose it, this page has every step in full (see Setup above).

---

### How it works

1. The button loads Google Identity Services once per page and renders Google's own button.
   When the script cannot load (offline, an ad blocker, a Content Security Policy), the button
   shows `socialLogin.googleUnavailable` instead; the rest of the login form keeps working.
2. The user picks an account in Google's popup and Google hands the button an ID token.
3. The frontend calls `ensureCsrf()` and then `POST auth/social/google` with `{ token }` through
   the shared `api` from `@/config/api`. There is no other HTTP client and no token is stored.
4. `GoogleProvider` checks the token: an `RS256` signature by one of
   [Google's keys](https://www.googleapis.com/oauth2/v3/certs) (cached for as long as Google's
   `Cache-Control` says), issuer `accounts.google.com`, audience equal to `GOOGLE_CLIENT_ID`,
   an expiry in the future, a subject and an email. Anything else is `invalid_social_token`.
   Up to 60 seconds of clock difference with Google is tolerated.
5. `SocialLoginAction` finds the user:
   - by the provider's account id (`social_accounts.provider_user_id`): a returning user, even
     if the Google email changed since;
   - otherwise, only if Google says the email is verified (`email_verified: true`): the user
     with that email, or a new user created with Google's name and email, `email_verified_at`
     set and a random password nobody knows (they can still set one through your password
     reset). A new user is linked right away. An existing user is linked only after the 2FA gate
     in step 6 lets them in: a sign-in that gets the 2FA challenge links nothing, and the next
     one matches the verified email again. An unverified email is `social_email_not_verified`
     and nothing is linked or created. If another request creates that link or registers that
     email first (a double click, or a normal sign-up at the same moment), the sign-in carries on
     with the user that now holds it, under the same rules: through the 2FA gate, then linked.

   Known trade-off: Google vouches for `email_verified` authoritatively only for `@gmail.com`
   addresses and for Google Workspace accounts (tokens with an `hd` claim). For any other
   domain it means the address was verified when the Google account was created, so whoever
   controls a lapsed custom domain later could verify it again and sign in as the user with
   that email. Social login trusts `email_verified` as Google sends it; if your users sign up
   with custom domains, decide whether that is acceptable for your app.
6. The user is signed in through
   `\Lightit\Authentication\Domain\Actions\LoginByUserAction::execute()`, the same gate the
   password login goes through with 2FA installed. A user without 2FA gets the cookie session
   and the boilerplate's `\Lightit\Users\App\Resources\UserResource`. A user with 2FA gets the
   same `200` challenge as the password login (`access_token`, `token_type`
   `verification_required` or `setup_required`, `expires_in`) and no session: social login
   never skips the second factor.
7. After a normal sign-in the hook fetches the current user again, like the template's
   `useLogin`, and the button navigates to the `redirect` search param (or `/`). If the 2FA
   frontend was already generated when the hook was, a challenge goes to the 2FA challenge store
   and the user lands on `/two-factor` or `/two-factor/setup`, keeping `redirect`. If you add 2FA
   later, delete `-hooks/use-sign-in-with-google.ts` and run `auth:setup` with Social Login
   again: it writes the 2FA-aware hook and skips every other file.

### API

`routes/social-login.php`, which `routes/api.php` requires, adds one public route with its own
limiter, `throttle:social-login` (10 a minute per IP). The limiter is registered by
`lightit-auth-laravel`'s own service provider when
`\Lightit\Authentication\Domain\SocialLoginRateLimiter` exists, so the package must stay a
runtime dependency (`composer require`, never `--dev`).

| Method | Path | Body | Answer |
| --- | --- | --- | --- |
| `POST` | `/auth/social/{provider}` | `{ token }` | `200`: the user (session created) or the 2FA challenge; `201` instead of `200` when this sign-in created the user |

Errors use the boilerplate's error body (`error.code`):

| Status | `error.code` | When |
| --- | --- | --- |
| `401` | - | The request has no session (not from a Sanctum stateful domain), like the boilerplate's login |
| `404` | `social_provider_unknown` | `{provider}` is not in `social-login.providers` |
| `422` | `invalid_social_token` | The token is malformed, expired, badly signed, or issued for another app or issuer |
| `422` | `social_email_not_verified` | First sign-in with an email the provider has not verified |
| `422` | - | `token` missing or not a string (validation error on `token`) |
| `429` | - | More than 10 attempts a minute from the same IP |
| `503` | `social_provider_unavailable` | Google's keys could not be fetched |
| `500` | - | `GOOGLE_CLIENT_ID` is not set (the message naming it is in the log) |

### Adding a provider

Everything after the token check is provider-agnostic: the route, the request, the linking,
the 2FA gate, the response and the frontend service. To add Apple, for example:

1. **Backend: write the provider.** Add
   `src/Authentication/Domain/SocialProviders/AppleProvider.php` implementing
   `\Lightit\Authentication\Domain\Contracts\SocialProvider`. Its `verify(string $token)` checks
   the token the way the provider documents it (signature, issuer, audience, expiry), throws
   `InvalidSocialTokenException` when any check fails, and returns a `SocialIdentityDto` with
   the provider name, the provider's stable account id, the email, whether the provider verified
   it, and the name (or `''`). `GoogleProvider` is the model to copy.
2. **Backend: register it** in `config/social-login.php`:
   `'apple' => \Lightit\Authentication\Domain\SocialProviders\AppleProvider::class,`. The key is
   the `{provider}` in the URL (lowercase letters, digits and dashes).
3. **Frontend: add it to the type** in `src/services/auth/social/types.ts`
   (`export type SocialProvider = "google" | "apple";`), then build its button next to
   `google-login-button.tsx`: get the provider's token with its SDK and call
   `signInWithSocialProvider("apple", token)` through a hook shaped like
   `use-sign-in-with-google.ts`.

No migration, route, action or installer change is needed: `social_accounts` already keys
accounts by provider.
