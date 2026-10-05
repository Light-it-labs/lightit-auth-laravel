<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\SocialLoginStub;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stands in for the boilerplate's `Lightit\Users\Domain\Models\User`: the columns its
 * `users` table has, and its lower-casing email mutator.
 *
 * @property int                  $id
 * @property string               $name
 * @property string               $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string               $password
 */
final class User extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    public static function createTable(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
        });
    }

    public static function make(string $email, string $name = 'Demo User'): self
    {
        $user = new self();
        $user->name = $name;
        $user->email = $email;
        $user->password = 'not-a-real-hash';
        $user->saveOrFail();

        return $user;
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
        ];
    }
}
