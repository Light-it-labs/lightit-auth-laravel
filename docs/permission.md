## Roles and Permissions

Add role and permission-based authorization to your application using [spatie/laravel-permission](https://github.com/spatie/laravel-permission).

### Setup

When selected during `auth:setup`, this package will:

- Install and configure Spatie's package.
- Publish the permission config file and migration.

### Default Roles Created

- `user`
- `admin`
- `super_admin`

### Modify the User model

Add the `HasRoles` trait to your User model:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

### Granting Super Admin Privileges

To allow users with the `super_admin` role to bypass all authorization checks, add the following logic to your `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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

### Seeding Roles and Users (Optional)

You can create a database seeder to predefine roles and assign them to users:

```php
public function run(): void
{
    $this->call([RoleSeeder::class]);

    $user = User::factory()->create([
        'name' => 'Regular User',
        'email' => 'user@example.com',
    ]);
    $user->assignRole('user');

    $admin = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]);
    $admin->assignRole('admin');
}
```

### Protecting Routes with Permissions

Use middleware to protect routes by permission:

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

### Handling Permission Exceptions

To properly handle permission-related exceptions and return them as JSON responses, update the `convertDefaultExceptions` method in your `ExceptionHandler`:

```php
\Spatie\Permission\Exceptions\UnauthorizedException::class => function (\Spatie\Permission\Exceptions\UnauthorizedException $exception): void {
    throw new UnauthorizedException(message: $exception->getMessage());
},
```

This will catch Spatie's `UnauthorizedException` and rethrow it using your custom `UnauthorizedException`, ensuring a consistent JSON response format.

**Note:**
If you want to display detailed information about the missing permission in the exception message, you can set the `display_permission_in_exception` value to `true` in the `config/permission.php` file:

```php
'display_permission_in_exception' => true,
```
---
