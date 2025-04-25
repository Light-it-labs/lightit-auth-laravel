## Roles and Permissions

This package supports optional integration with [spatie/laravel-permission](https://github.com/spatie/laravel-permission).

When enabled during `auth:setup`, it will:

- Install the package.
- Publish the config and migration.

### Default Roles

- `user`
- `admin`
- `super_admin`

### Add the trait to your User model

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

### Super Admin Shortcut

To allow a "super admin" to bypass all permission checks, follow the [official documentation](https://spatie.be/docs/laravel-permission/v6/basic-usage/super-admin) and add a global Gate check in the boot() method of **AppServiceProvider** like this:

```php
use Illuminate\Support\Facades\Gate;

public function boot()
{
    Gate::before(function ($user, $ability) {
        return $user->hasRole('Super Admin') ? true : null;
    });
}
```
### Seeding Roles and Creating Users

You can define a database seeder to create roles and assign them to users like this:

```php
public function run(): void
{
    $this->call([RoleSeeder::class]);

    $user = UserFactory::new()->createOne([
        'name' => 'user',
        'email' => 'user@mail.com',
    ]);
    $user->assignRole(RoleManagement::ROLE_USER);

    $admin = UserFactory::new()->createOne([
        'name' => 'admin',
        'email' => 'admin@mail.com',
    ]);
    
    $admin->assignRole(RoleManagement::ROLE_ADMIN);
}
```

### Example: Protecting Routes with Permissions

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lightit\Backoffice\Users\App\Controllers\{
    DeleteUserController, GetUserController, ListUserController, StoreUserController
};
use Lightit\Shared\Permissions\UserPermissions;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::prefix('users')
    ->middleware([])
    ->group(static function (): void {
        Route::post('/', StoreUserController::class)
            ->middleware(PermissionMiddleware::using(UserPermissions::CREATE));

        Route::delete('/{user}', DeleteUserController::class)->whereNumber('user')
            ->middleware(PermissionMiddleware::using(UserPermissions::DELETE));
    });
```
