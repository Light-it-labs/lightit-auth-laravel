## Google Single Sign-On (SSO)

Enable secure Single Sign-On (SSO) using Google accounts. This flow validates Google's ID tokens and issues your application's own access token for session management.

### Setup

1. **Install Google API Client**

This package automatically installs [`google/apiclient`](https://github.com/googleapis/google-api-php-client) when Google SSO is selected during `auth:setup`.

2. **Define the Google authentication route**

```php
use Illuminate\Support\Facades\Route;
use Lightit\Authentication\App\Controllers\GoogleLoginController;

Route::prefix('auth')->group(static function () {
    Route::post('google', GoogleLoginController::class);
});
```

### How it works

- The frontend obtains a Google ID token from Google's authentication process.
- The frontend sends the ID token to your backend at the `auth/google` endpoint.
- The backend validates the token using Google's API.
- If the token is valid, the backend locates or creates the user in the database.
- The user is then logged in by issuing an access token according to the authentication driver you have configured (e.g., JWT, Sanctum).
---
