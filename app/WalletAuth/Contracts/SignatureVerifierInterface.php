<?php

namespace App\WalletAuth\Contracts;

interface SignatureVerifierInterface
{
    public function verify(string $message, string $signature, string $expectedAddress): bool;

    public function recoverAddress(string $message, string $signature): ?string;
}
