## Roles and Permissions

This module integrates [spatie/laravel-permission](https://github.com/spatie/laravel-permission) to provide robust role and permission-based authorization for your Laravel application.

> [!INFO]
> A default set of roles (`user`, `admin`, `super_admin`) is seeded during setup. You can customize this or extend it with your own logic.

### Setup

#### 1. User model

Add the `HasRoles` trait to your `User` model:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

#### 2. Grant Super Admin bypass

Allow `super_admin` users to bypass all Gate checks in `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
```

#### 3. Optional: Seeding roles and users

```php
public function run(): void
{
    $this->call([RoleSeeder::class]);

    $user = UserFactory::new()->createOne([
        'name' => 'Regular User',
        'email' => 'user@example.com',
    ]);
    $user->assignRole('user');

    $admin = UserFactory::new()->createOne([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]);
    
    $admin->assignRole('admin');
}
```

#### 4. Protect routes using permissions

```php
use Illuminate\Support\Facades\Route;
use Lightit\Backoffice\Users\App\Controllers\StoreUserController;
use Lightit\Backoffice\Users\App\Controllers\DeleteUserController;
use Lightit\Shared\Permissions\UserPermissions;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::prefix('users')
    ->middleware(['auth'])
    ->group(static function (): void {
        Route::post('/', StoreUserController::class)
            ->middleware(PermissionMiddleware::using(UserPermissions::CREATE));

        Route::delete('/{user}', DeleteUserController::class)->whereNumber('user')
            ->middleware(PermissionMiddleware::using(UserPermissions::DELETE));
    });
```

#### 5. Handle permission exceptions

To convert Spatie's exceptions to JSON format consistently, update your `ExceptionHandler`:

```php
\Spatie\Permission\Exceptions\UnauthorizedException::class => function (\Spatie\Permission\Exceptions\UnauthorizedException $exception): void {
    throw new UnauthorizedException(message: $exception->getMessage());
},
```

Optional: To display the required permission in the error message, update the config:

```php
'display_permission_in_exception' => true,
```

---
