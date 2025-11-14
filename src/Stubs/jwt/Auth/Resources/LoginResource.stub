<?php

declare(strict_types=1);

namespace Lightit\Authentication\App\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lightit\Authentication\Domain\DataTransferObjects\LoginDto;

/**
 * @mixin LoginDto
 */
#[SchemaName('LoginDto')]
class LoginResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
        ];
    }
}
