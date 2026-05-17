<?php

namespace App\WalletAuth\Data;

use Illuminate\Contracts\Auth\Authenticatable;

final class WalletLoginResult
{
    public function __construct(
        public Authenticatable $user,
        public bool $alreadyAuthenticated = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $csrfToken): array
    {
        $payload = [
            'message' => $this->alreadyAuthenticated ? 'Already signed in' : 'Logged in',
            'wallet_address' => $this->user->wallet_address,
            'csrf_token' => $csrfToken,
        ];

        if ($this->alreadyAuthenticated) {
            $payload['already_authenticated'] = true;
        }

        return $payload;
    }
}
