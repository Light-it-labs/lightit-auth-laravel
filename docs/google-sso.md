## Google Single Sign-On (SSO)

This module enables authentication via Google accounts using [googleapis/google-api-php-client](https://github.com/googleapis/google-api-php-client), allowing users to log in securely without managing passwords directly.

> [!NOTE]
> Google SSO integration relies on Google's Identity Platform. Your frontend must handle the OAuth flow and provide a valid ID token to your API.

> [!WARNING]
> Not offered by `auth:setup` yet. The generated controller still returns the
> `access_token` / `token_type` / `expires_in` shape of the removed Bearer driver;
> it is rewired to the boilerplate's session login before the feature is exposed.

### Setup

#### 1. Install the Google API Client

The [google/apiclient](https://github.com/googleapis/google-api-php-client) library is automatically installed when Google SSO is selected during `auth:setup`.

#### 2. Define the authentication route

```php
use Illuminate\Support\Facades\Route;
use Lightit\Authentication\App\Controllers\GoogleLoginController;

Route::prefix('auth')->group(static function () {
    Route::post('google', GoogleLoginController::class);
});
```

### How it works

1. The frontend initiates the Google login and obtains a valid ID token from Google's OAuth flow.
2. That token is sent to your backend's `POST /auth/google` endpoint.
3. The backend validates the token with Google's servers.
4. If valid, it retrieves the user's profile info (e.g., email).
5. The system locates or creates the corresponding user in the database.
6. Finally, it logs the user in. That step still returns a token-shaped body today - see the warning above.

---
