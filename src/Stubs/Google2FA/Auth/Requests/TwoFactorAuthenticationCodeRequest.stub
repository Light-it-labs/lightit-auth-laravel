<?php

declare(strict_types=1);

namespace Lightit\Authentication\App\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TwoFactorAuthenticationCodeRequest extends FormRequest
{
    public const ONE_TIME_PASSWORD = 'one_time_password';

    public function rules(): array
    {
        return [
            self::ONE_TIME_PASSWORD => ['required', 'numeric', 'digits:6'],
        ];
    }

    public function getOtpCode(): string
    {
        return $this->string(self::ONE_TIME_PASSWORD)->toString();
    }
}
