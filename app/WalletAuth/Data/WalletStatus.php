<?php

namespace App\WalletAuth\Data;

final class WalletStatus
{
    public function __construct(
        public bool $authenticated,
        public ?string $walletAddress,
    ) {}

    /**
     * @return array{authenticated: bool, wallet_address: ?string}
     */
    public function toArray(): array
    {
        return [
            'authenticated' => $this->authenticated,
            'wallet_address' => $this->walletAddress,
        ];
    }
}
