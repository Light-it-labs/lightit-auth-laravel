<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\RolesApiStub;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Stands in for the boilerplate's `Lightit\Users\Domain\Models\User` once the checklist's
 * `HasRoles` line is pasted.
 *
 * @property int    $id
 * @property string $name
 * @property string $email
 */
final class User extends Authenticatable
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];
}
