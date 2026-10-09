## Passkeys

Let signed-in users register passkeys (WebAuthn) from their account, and anyone sign in with
one from the login screen, using
[web-auth/webauthn-lib](https://github.com/web-auth/webauthn-framework). Every call into the
library lives in `\Lightit\Authentication\Domain\Services\PasskeyCeremonyService`.

### Setup

Run `php artisan auth:setup` and pick **Passkeys**. It requires `web-auth/webauthn-lib:^5.3`,
then writes into your app, skipping every file that already exists:

- the passkey model, actions, requests, resources, controllers, `PasskeyChallengeStore`,
  `PasskeyCeremonyService` and `PasskeyRateLimiter` under `src/Authentication/`
- the migration that creates the `passkeys` table
- `config/passkeys.php`
- `routes/passkeys.php`, required from `routes/api.php` (see [Endpoints](#endpoints))
- the frontend services, the `/account/passkeys` page and a "Sign in with passkey" button for
  the login form, when it finds the React project (see [Account page](#account-page) and
  [Signing in with a passkey](#signing-in-with-a-passkey)). Pass `--frontend-path=<path>` when it is not a sibling
  directory named `frontend`, `front` or `<app>-frontend`.
- the two checklists described in [After `auth:setup`](#after-authsetup)

No backend file of yours needs editing. These steps are yours; they are the same steps the two
checklists list.

#### 1. Run the migration

```bash
php artisan migrate
```

#### 2. Set the relying party

`config/passkeys.php` reads these from `.env`:

```dotenv
PASSKEYS_RP_ID=localhost
PASSKEYS_RP_NAME="${APP_NAME}"
PASSKEYS_ALLOWED_ORIGINS=http://localhost:5173
```

`PASSKEYS_RP_ID` is the frontend's host (or a registrable parent domain), without scheme or
port. `PASSKEYS_ALLOWED_ORIGINS` lists the full frontend origins, comma-separated. Both fall
back to `localhost` / `http://localhost:5173` only when `APP_ENV` is `local` or `testing`;
anywhere else a missing value makes the first passkey request fail with a reported `500` naming
the variable, instead of a `422` that looks like a bad device.
`PASSKEYS_CHALLENGE_TTL_SECONDS` (default `300`) is how long a registration challenge lives.

#### 3. Set the user handle secret

`PASSKEYS_USER_HANDLE_SECRET` is required and has no fallback: without it every passkey request
fails with a reported `500`. Generate it once:

```bash
php -r 'echo bin2hex(random_bytes(32));'
```

It keys the user handle stored on every authenticator, so never rotate it: changing it
invalidates every passkey registered before the change. It is deliberately not `APP_KEY`, which
does get rotated.

#### 4. Use a shared cache store

The registration and sign-in challenges live in the cache, so use a store shared by every app
server. The
boilerplate's `database` store works; `array` does not.

#### 5. Frontend: install the dependencies

The page imports `@hookform/resolvers`, `@simplewebauthn/browser`, `@tanstack/react-query`,
`@tanstack/react-router`, `axios`, `date-fns`, `react-hook-form`, `sonner` and `zod`. The
generator never runs a package manager: it prints the add command for the missing ones, and the
checklist repeats it.

#### 6. Frontend: add the sign-in button to the login form

In `src/routes/(public)/_guest/login/-components/login-form.tsx`:

1. Add `import { PasskeyLoginButton } from "./passkey-login-button";` as the last import of the
   file.
2. Add `<PasskeyLoginButton />` on the line after the submit button's closing `</Button>` (the
   one showing `{t("login.login")}`).

The button is `type="button"`, so it never submits the password form. This step touches neither
the `useLogin` import nor the `loginMutation` line, so it applies the same way before or after
the 2FA login-hook step.

#### 7. Frontend: add the i18n keys

`src/i18n/locales/en.json` is not edited for you. Add the `passkeys` block at the top level and
`navigation.links.passkeys` (the sidebar link's label), then translate them in `es.json`. The
exact English strings are the JSON blocks in `AUTH-PASSKEYS-FRONTEND-TODO.md`; this repository
keeps them in `src/Stubs/Frontend/Passkeys/AUTH-PASSKEYS-FRONTEND-TODO.md.stub`. The screens
also reuse `form.password`, `form.errors.required`, `buttons.cancel`, `login.success` and
`common.requestError`, which the template already has.

#### 8. Frontend: link the page from the sidebar

The package does not edit your navigation. In `src/routes/_private/-components/sidebar/sidebar.tsx`,
add this entry at the end of the `links` array:

```tsx
{ path: "/account/passkeys", label: t("navigation.links.passkeys"), icon: <Icons.Lock /> },
```

Its label is the short `navigation.links.passkeys` key from step 7, like the other links.
`Lock` is the closest icon the template's `Icons` set ships; to use another one, add it to
`src/components/ui/icons/available-icons.ts` first.

### After `auth:setup`

`auth:setup` writes the passkey files and leaves two checklists in your projects:

| File | Where | What it lists |
| --- | --- | --- |
| `AUTH-PASSKEYS-TODO.md` | backend root | Run `migrate`, set the relying party in `.env`, set the user handle secret (required, no fallback), use a shared cache store |
| `AUTH-PASSKEYS-FRONTEND-TODO.md` | frontend root | Install dependencies, add the sign-in button, add the i18n keys, add the sidebar link |

The command also prints the same steps when it finishes. Each checklist is generated
for your install (your endpoints, your package manager). Work through it, tick the
boxes, and **delete the file when every box is ticked** — it is not read by the app
and nothing breaks without it. Running `auth:setup` again never overwrites it. When every
frontend file already exists, the command says the passkeys frontend is already installed and
does not print the manual steps again.

If you lose it, this page has every step in full (see Setup above).

---

### Endpoints

All in `routes/passkeys.php`, which `routes/api.php` requires. The two `auth/passkeys/*` sign-in
routes are public, each with its own per-IP bucket: `login-options` uses
`throttle:passkeys-sign-in-options` and `login` uses `throttle:passkeys-sign-in`, 20 a minute
each. The `passkeys/*` routes are under `auth:sanctum`, and every one but the list uses
`throttle:passkeys` (6 a minute per user, 30 per IP). All three limiters are registered by
`lightit-auth-laravel`'s own service provider when
`\Lightit\Authentication\Domain\PasskeyRateLimiter` exists, so the package must stay a runtime
dependency (`composer require`, never `--dev`).

**Behind a load balancer or reverse proxy, configure trusted proxies.** The sign-in limiters key on
`$request->ip()`. Without trusted proxies that is the proxy's address, so every visitor shares one
bucket and 20 sign-ins a minute, from anyone, lock everybody out with `429`. Trust your proxy in
`bootstrap/app.php` so Laravel reads the client IP from `X-Forwarded-For`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: ['10.0.0.0/8']); // your load balancer's addresses
})
```

| Method | Path | Body | Answer |
| --- | --- | --- | --- |
| `POST` | `/auth/passkeys/login-options` | - | `200`: `ceremony_id` and the WebAuthn request options |
| `POST` | `/auth/passkeys/login` | `{ ceremony_id, credential }` | `200`: the user (session created), never a 2FA challenge; `410` expired or replayed challenge, `422` unknown passkey (`passkey_not_recognised`) or failed verification (`passkey_login_failed`) |
| `GET` | `/passkeys` | - | `200`: `id`, `name`, `created_at`, `last_used_at` per passkey |
| `POST` | `/passkeys/registration-options` | `{ password }` | `200`: WebAuthn creation options |
| `POST` | `/passkeys` | `{ name, credential }` | `201`: the new passkey; `410` expired or replayed challenge, `422` failed verification, `409` already registered |
| `PATCH` | `/passkeys/{passkey}` | `{ name }` | `200`: the renamed passkey |
| `POST` | `/passkeys/{passkey}/delete` | `{ password }` | `204` |

Starting a registration and deleting a passkey ask for the current password, like turning 2FA
on and off; a wrong one is a `422` on `password`, not a `401`. Another user's passkey is a
`403`. `credential` is the browser's `RegistrationResponseJSON`, sent as is. The challenge is
bound to the user, lives `passkeys.challenge_ttl_seconds` and is spent by the first
`POST /passkeys` under a cache lock. There is one challenge per user: a second registration
start replaces the first, so only one ceremony can succeed, and only if the newest tab submits
first. Otherwise the older tab fails verification against the newer challenge (a `422`) and
spends it, so the newer tab then gets a `410`.
A rejected ceremony (the `422`) is logged at `warning` level as `passkey registration rejected`
(or `passkey sign-in rejected` for `passkey_login_failed`) with the exception class and message,
never the credential, so a wrong `PASSKEYS_RP_ID` or origin shows up in the logs.

### Account page

The frontend layer writes the passkey services (`src/services/auth/passkeys/*`) and the
`/account/passkeys` page (`src/routes/_private/account/passkeys/**`), where a signed-in user adds
a passkey, sees each one's name and creation date, renames it and deletes it. The TanStack
Router plugin adds the route to `src/routeTree.gen.ts` the next time `vite` runs (`dev` or
`build`). Type-check after that.

1. Every call uses the cookie session through the shared `api` from `@/config/api`; there is
   no token.
2. Add: the password goes to `POST passkeys/registration-options`, which returns the WebAuthn
   creation options. `startRegistration` from `@simplewebauthn/browser` (base64url JSON to the
   `ArrayBuffer`s the WebAuthn API wants and back, no dependencies of its own) runs
   `navigator.credentials.create`, and the result goes with the name to `POST passkeys`. The
   backend spends the challenge on that call, so a retry starts over from the password.
3. A wrong password is a `422` on the `password` field and shows under it.
4. A cancelled or timed-out browser prompt, a device that can't create passkeys, a browser
   `SecurityError` (the page's origin doesn't match `PASSKEYS_RP_ID`), a device that
   already holds a passkey for the account (the browser's `InvalidStateError` or the backend's
   `409`) and an expired challenge (`410`) get their own message; any other failure shows
   `common.requestError`.
5. Rename and delete refresh the list. Delete asks for the password again
   (`POST passkeys/{id}/delete`).
6. Without WebAuthn support in the browser the page says so instead of offering "Add passkey".

### Signing in with a passkey

The login options carry no `allowCredentials`, so the browser offers every passkey it holds for
the relying party. The challenge is stored under a random `ceremony_id`, lives
`passkeys.challenge_ttl_seconds` and is spent by the first `POST /auth/passkeys/login` under a
cache lock. The credential is looked up by the SHA-256 of its id; the assertion must carry a
user handle equal to the owner's (a missing one fails), user verification is required and a
signature counter that goes backwards fails the ceremony. A valid assertion stores the new
counter and `last_used_at`. The counter is checked and saved in one transaction with the
credential row locked; a deadlock (or a MySQL lock-wait timeout) there is retried, 3 attempts
in all, with the same challenge. The request caps the size of every credential field and hands
the ceremony only the fields WebAuthn reads.

The user is then signed in through
`\Lightit\Authentication\Domain\Actions\LoginByUserAction::executeAfterChallenge()`, with no
2FA challenge: a passkey verified with user verification is already multi-factor (the device
plus its biometric or PIN), stronger than a TOTP code. Every user, with or without 2FA, gets the
cookie session and the boilerplate's `\Lightit\Users\App\Resources\UserResource`. The
password login (and Google sign-in) still ask for the code, so a user with both configured signs
in either with password + code or with the passkey alone. With mandatory 2FA, a user who has not
enrolled TOTP yet also signs in with a passkey directly, because the passkey already satisfies
the second factor. Both ceremonies ask for `userVerification: required` and the server rejects
an assertion without the UV flag, which is what makes the passkey count as multi-factor. Like
the boilerplate's `LoginAction`, a request without a session (not from a Sanctum stateful
domain) is a `401`.

**The sign-in button.** The frontend writes
`src/routes/(public)/_guest/login/-components/passkey-login-button.tsx` and
`src/routes/(public)/_guest/login/-hooks/use-sign-in-with-passkey.ts`:

1. `authenticateWithPasskey` calls `ensureCsrf()`, then `POST auth/passkeys/login-options` for an
   anonymous, single-use challenge and its `ceremony_id`. `startAuthentication` lets the user
   pick any passkey the device holds for the site. The result goes with the `ceremony_id` to
   `POST auth/passkeys/login`.
2. The sign-in creates the cookie session. `useSignInWithPasskey` ignores the response
   body and fetches the current-user query again, like the template's `useLogin`, and the
   button navigates to the `redirect` search param (or `/`) through `redirectTarget`, like the
   login form.
3. A user with 2FA signs in the same way: the backend never answers a passkey sign-in with a
   2FA challenge, so the hook never routes to `/two-factor`.
4. A cancelled or timed-out prompt, a browser without WebAuthn, a passkey the backend doesn't
   know (`422` `passkey_not_recognised`, e.g. deleted from the account) and an expired
   challenge (`410`) get their own toast; anything else shows `common.requestError`.
