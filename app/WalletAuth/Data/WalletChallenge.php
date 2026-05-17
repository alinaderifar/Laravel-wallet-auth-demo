<?php

namespace App\WalletAuth\Data;

final class WalletChallenge
{
    public function __construct(
        public string $nonce,
        public string $message,
        public int $chainId,
        public string $expiresAt,
    ) {}

    /**
     * @return array{nonce: string, message: string, chain_id: int, expires_at: string}
     */
    public function toArray(): array
    {
        return [
            'nonce' => $this->nonce,
            'message' => $this->message,
            'chain_id' => $this->chainId,
            'expires_at' => $this->expiresAt,
        ];
    }
}
