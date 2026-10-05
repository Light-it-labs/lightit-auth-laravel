## Passkeys

Let signed-in users register passkeys (WebAuthn) from their account, using
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
- the frontend services and the `/account/passkeys` page, when it finds the React project (see
  [Account page](#account-page)). Pass `--frontend-path=<path>` when it is not a sibling
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
port. `PASSKEYS_ALLOWED_ORIGINS` lists the full frontend origins, comma-separated.
`PASSKEYS_CHALLENGE_TTL_SECONDS` (default `300`) is how long a registration challenge lives.

#### 3. Optional: set the user handle secret

`PASSKEYS_USER_HANDLE_SECRET` falls back to `APP_KEY`. It keys the user handle stored on every
authenticator, so changing it (or rotating `APP_KEY` while it is the fallback) breaks every
passkey registered before the change.

#### 4. Use a shared cache store

The registration challenge lives in the cache, so use a store shared by every app server. The
boilerplate's `database` store works; `array` does not.

#### 5. Frontend: install the dependencies

The page imports `@hookform/resolvers`, `@simplewebauthn/browser`, `@tanstack/react-query`,
`@tanstack/react-router`, `axios`, `date-fns`, `react-hook-form`, `sonner` and `zod`. The
generator never runs a package manager: it prints the add command for the missing ones, and the
checklist repeats it.

#### 6. Frontend: add the i18n keys

`src/i18n/locales/en.json` is not edited for you. Add the `passkeys` block at the top level and
`navigation.links.passkeys` (the sidebar link's label), then translate them in `es.json`. The
exact English strings are the JSON blocks in `AUTH-PASSKEYS-FRONTEND-TODO.md`; this repository
keeps them in `src/Stubs/Frontend/Passkeys/AUTH-PASSKEYS-FRONTEND-TODO.md.stub`. The page also
reuses `form.password`, `form.errors.required`, `buttons.cancel` and `common.requestError`,
which the template already has.

#### 7. Frontend: link the page from the sidebar

The package does not edit your navigation. In `src/routes/_private/-components/sidebar/sidebar.tsx`,
add this entry at the end of the `links` array:

```tsx
{ path: "/account/passkeys", label: t("navigation.links.passkeys"), icon: <Icons.Lock /> },
```

Its label is the short `navigation.links.passkeys` key from step 6, like the other links.
`Lock` is the closest icon the template's `Icons` set ships; to use another one, add it to
`src/components/ui/icons/available-icons.ts` first.

### After `auth:setup`

`auth:setup` writes the passkey files and leaves two checklists in your projects:

| File | Where | What it lists |
| --- | --- | --- |
| `AUTH-PASSKEYS-TODO.md` | backend root | Run `migrate`, set the relying party in `.env`, optionally set the user handle secret, use a shared cache store |
| `AUTH-PASSKEYS-FRONTEND-TODO.md` | frontend root | Install dependencies, add the i18n keys, add the sidebar link |

The command also prints the same steps when it finishes. Each checklist is generated
for your install (your endpoints, your package manager). Work through it, tick the
boxes, and **delete the file when every box is ticked** — it is not read by the app
and nothing breaks without it. Running `auth:setup` again never overwrites it.

If you lose it, this page has every step in full (see Setup above).

---

### Endpoints

All under `auth:sanctum` in `routes/passkeys.php`, which `routes/api.php` requires. Every route
but the list uses `throttle:passkeys` (6 a minute per user, 30 per IP). That limiter is
registered by `lightit-auth-laravel`'s own service provider when
`\Lightit\Authentication\Domain\PasskeyRateLimiter` exists, so the package must stay a runtime
dependency (`composer require`, never `--dev`).

| Method | Path | Body | Answer |
| --- | --- | --- | --- |
| `GET` | `/passkeys` | - | `200`: `id`, `name`, `created_at`, `last_used_at` per passkey |
| `POST` | `/passkeys/registration-options` | `{ password }` | `200`: WebAuthn creation options |
| `POST` | `/passkeys` | `{ name, credential }` | `201`: the new passkey; `410` expired or replayed challenge, `422` failed verification, `409` already registered |
| `PATCH` | `/passkeys/{passkey}` | `{ name }` | `200`: the renamed passkey |
| `POST` | `/passkeys/{passkey}/delete` | `{ password }` | `204` |

Starting a registration and deleting a passkey ask for the current password, like turning 2FA
on and off; a wrong one is a `422` on `password`, not a `401`. Another user's passkey is a
`403`. `credential` is the browser's `RegistrationResponseJSON`, sent as is. The challenge is
bound to the user, lives `passkeys.challenge_ttl_seconds` and is spent by the first
`POST /passkeys` under a cache lock.

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
4. A cancelled or timed-out browser prompt, a device that can't create passkeys, a device that
   already holds a passkey for the account (the browser's `InvalidStateError` or the backend's
   `409`) and an expired challenge (`410`) get their own message; any other failure shows
   `common.requestError`.
5. Rename and delete refresh the list. Delete asks for the password again
   (`POST passkeys/{id}/delete`).
6. Without WebAuthn support in the browser the page says so instead of offering "Add passkey".
