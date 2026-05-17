<?php

namespace App\WalletAuth;

use App\Models\WalletNonce;
use App\WalletAuth\Contracts\SignatureVerifierInterface;
use App\WalletAuth\Data\WalletChallenge;
use App\WalletAuth\Data\WalletLoginResult;
use App\WalletAuth\Data\WalletStatus;
use App\WalletAuth\Exceptions\WalletAuthException;
use App\WalletAuth\Services\Siwe\SiweMessageBuilder;
use App\WalletAuth\Services\Siwe\SiweValidator;
use Illuminate\Contracts\Auth\Authenticatable;

class WalletAuthManager
{
    public function __construct(
        private readonly SiweMessageBuilder $messageBuilder,
        private readonly SiweValidator $siweValidator,
        private readonly SignatureVerifierInterface $signatureVerifier,
        private readonly WalletUserResolver $userResolver,
    ) {}

    public function status(?Authenticatable $user): WalletStatus
    {
        if (! $user) {
            return new WalletStatus(authenticated: false, walletAddress: null);
        }

        return new WalletStatus(
            authenticated: true,
            walletAddress: $user->wallet_address,
        );
    }

    public function issueChallenge(
        string $address,
        int $chainId,
        string $domain,
        string $uri,
        ?Authenticatable $currentUser = null,
    ): WalletChallenge {
        if ($currentUser) {
            throw new WalletAuthException('already_authenticated');
        }

        $address = strtolower($address);
        $this->assertAllowedChainId($chainId);

        $nonce = bin2hex(random_bytes(16));
        $ttl = (int) config('wallet-auth.nonce_ttl_minutes', 5);
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addMinutes($ttl);

        $message = $this->messageBuilder->build([
            'domain' => $domain,
            'address' => $address,
            'statement' => config('wallet-auth.siwe.statement'),
            'uri' => $uri,
            'version' => config('wallet-auth.siwe.version', '1'),
            'chainId' => $chainId,
            'nonce' => $nonce,
            'issuedAt' => $issuedAt,
            'expirationTime' => $expiresAt,
        ]);

        WalletNonce::create([
            'address' => $address,
            'nonce' => $nonce,
            'message' => $message,
            'domain' => $domain,
            'uri' => $uri,
            'chain_id' => $chainId,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ]);

        return new WalletChallenge(
            nonce: $nonce,
            message: $message,
            chainId: $chainId,
            expiresAt: $expiresAt->toIso8601String(),
        );
    }

    public function verifyAndLogin(
        string $address,
        string $signature,
        int $chainId,
        ?Authenticatable $currentUser = null,
    ): WalletLoginResult {
        $address = strtolower($address);
        $this->assertAllowedChainId($chainId);

        if ($currentUser) {
            if (strtolower((string) $currentUser->wallet_address) === $address) {
                return new WalletLoginResult(
                    user: $currentUser,
                    alreadyAuthenticated: true,
                );
            }

            throw new WalletAuthException('session_wallet_mismatch');
        }

        $walletNonce = WalletNonce::query()
            ->where('address', $address)
            ->where('chain_id', $chainId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $walletNonce || ! $walletNonce->message) {
            throw new WalletAuthException('nonce_missing');
        }

        $this->siweValidator->validate($walletNonce->message, $walletNonce, $address);

        if (! $this->signatureVerifier->verify($walletNonce->message, $signature, $address)) {
            throw new WalletAuthException('signature_invalid');
        }

        $walletNonce->update(['used_at' => now()]);

        $user = $this->userResolver->findOrCreate($address);

        return new WalletLoginResult(user: $user);
    }

    private function assertAllowedChainId(int $chainId): void
    {
        $allowed = config('wallet-auth.allowed_chain_ids', []);

        if ($allowed !== [] && ! in_array($chainId, $allowed, true)) {
            throw new WalletAuthException('chain_mismatch');
        }
    }
}
