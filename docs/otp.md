## OTP (One-Time Password)

Email-based one-time password delivery for identity verification.


### Setup

#### 1. Install the feature

The OTP flow is automatically installed when selected during `auth:setup`.

#### 2. Run the migration

```bash
php artisan migrate
```

This creates the `otps` table with a generic `destination` column, so the same table works for any delivery channel.

#### 3. Define routes

```php
use Lightit\Authentication\App\Controllers\OtpSendController;
use Lightit\Authentication\App\Controllers\OtpVerifyController;

Route::prefix('otp')->group(static function () {
    Route::post('send', OtpSendController::class);
    Route::post('verify', OtpVerifyController::class);
});
```

---

### Flow

1. `POST /otp/send`
   - Body: `{ "email": "..." }`
   - Returns: `200` — generates a 6-digit code, stores it hashed in the `otps` table, and delivers it to the user via `OtpNotification`
   - Any previously expired or used codes for the same destination are cleaned up automatically

2. User receives the code (via email by default)

3. `POST /otp/verify`
   - Body: `{ "email": "...", "code": "123456" }`
   - Returns: `200` on success
   - Returns: `422` if the code is invalid, already used, or expired

```mermaid
flowchart TD
    Send[POST /otp/send] --> Generate[Generate 6-digit code]
    Generate --> Store[Store hashed in otps table]
    Store --> Deliver[Deliver to user via OtpNotification]
    Deliver --> UserReceives[User receives code]
    UserReceives --> Verify[POST /otp/verify]
    Verify --> Valid{Valid, unused and not expired?}
    Valid -- no --> E422[422 Unprocessable]
    Valid -- yes --> MarkUsed[Mark code as used]
    MarkUsed --> E200[200 OK]
```

---

### Adding a new delivery channel

Delivery is handled by `OtpNotification`, which uses Laravel's built-in Notifications system. To add a new channel, update `via()` and add the corresponding `to{Channel}()` method:

```php
public function via(): array
{
    return ['mail', 'vonage'];
}

public function toVonage(): VonageSmsMessage
{
    return (new VonageSmsMessage())
        ->content('Your OTP code is: ' . $this->code);
}
```

No migration or business logic changes needed — the `destination` column is channel-agnostic.
