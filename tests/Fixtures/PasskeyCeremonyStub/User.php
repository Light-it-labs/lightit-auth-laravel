<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * @property int    $id
 * @property string $email
 * @property string $name
 */
final class User extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    public static function createTable(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->string('name');
        });
    }

    public static function make(string $email, string $name): self
    {
        $user = new self();
        $user->email = $email;
        $user->name = $name;
        $user->saveOrFail();

        return $user;
    }
}
