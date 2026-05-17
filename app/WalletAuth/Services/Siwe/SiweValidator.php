<?php

namespace App\WalletAuth\Services\Siwe;

use App\Models\WalletNonce;
use App\WalletAuth\Exceptions\WalletAuthException;

class SiweValidator
{
    public function __construct(
        private readonly SiweMessageParser $parser,
    ) {}

    /**
     * @return array{domain: string, address: string, chainId: int, nonce: string}
     */
    public function validate(string $message, WalletNonce $walletNonce, string $expectedAddress): array
    {
        try {
            $parsed = $this->parser->parse($message);
        } catch (\InvalidArgumentException) {
            $this->fail('siwe_invalid');
        }

        if (strtolower($parsed['address']) !== strtolower($expectedAddress)) {
            $this->fail('address_mismatch');
        }

        if ($parsed['nonce'] !== $walletNonce->nonce) {
            $this->fail('nonce_missing');
        }

        if ($walletNonce->domain && strtolower($walletNonce->domain) !== strtolower($parsed['domain'])) {
            $this->fail('domain_mismatch');
        }

        if ($walletNonce->uri && rtrim($walletNonce->uri, '/') !== rtrim($parsed['uri'], '/')) {
            $this->fail('uri_mismatch');
        }

        if ($walletNonce->chain_id !== null && (int) $walletNonce->chain_id !== (int) $parsed['chainId']) {
            $this->fail('chain_mismatch');
        }

        $allowed = config('wallet-auth.allowed_chain_ids', []);
        if ($allowed !== [] && ! in_array($parsed['chainId'], $allowed, true)) {
            $this->fail('chain_mismatch');
        }

        if ($parsed['issuedAt']->isFuture()) {
            $this->fail('siwe_invalid');
        }

        if ($parsed['expirationTime']?->isPast()) {
            $this->fail('nonce_expired');
        }

        if ($walletNonce->expires_at->isPast()) {
            $this->fail('nonce_expired');
        }

        return $parsed;
    }

    private function fail(string $code): never
    {
        throw new WalletAuthException($code);
    }
}
