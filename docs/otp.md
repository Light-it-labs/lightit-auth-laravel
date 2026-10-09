## OTP (One-Time Password)

Passwordless login with a one-time code sent by email.

> [!WARNING]
> Not offered by `auth:setup` yet. The backend code exists, but the feature has no frontend,
> no route file and no `AUTH-*-TODO.md` checklist; it is offered once those land.

### API

| Controller (suggested route) | Body | Response |
| --- | --- | --- |
| `OtpSendController` (`POST /otp/send`) | `email` | `204`. If a user has that email, deletes the previous codes for it, stores a new hashed code in the `otps` table and emails it (`OtpNotification`). Same answer when no user matches. |
| `OtpVerifyController` (`POST /otp/verify`) | `email`, `code` | Marks the code as used and logs in through `LoginByUserAction::execute()`: `200` with the boilerplate's `UserResource` and a session cookie or, when 2FA applies to the user, the `200` challenge described in [google-2fa.md](google-2fa.md) and no session. `422` `invalid_otp` if the code is wrong, used or expired. |

Code length and lifetime come from `config/otp.php` (`OTP_LENGTH`, default 6;
`OTP_EXPIRES_IN_MINUTES`, default 5).
