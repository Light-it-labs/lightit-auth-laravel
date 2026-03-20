<?php

declare(strict_types=1);

namespace Lightit\Authentication\App\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Email;

final class ForgotPasswordRequest extends FormRequest
{
    public const string EMAIL = 'email';

    public function rules(): array
    {
        return [
            self::EMAIL => ['required', Email::default()],
        ];
    }

    public function getEmail(): string
    {
        return $this->string(self::EMAIL)->toString();
    }
}
