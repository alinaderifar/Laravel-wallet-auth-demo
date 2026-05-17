<?php

namespace Tests\Unit\WalletAuth;

use App\Models\WalletNonce;
use App\WalletAuth\WalletAuthManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Support\LoadsSiweFixture;
use Tests\TestCase;

class WalletAuthManagerTest extends TestCase
{
    use LoadsSiweFixture;
    use RefreshDatabase;

    public function test_verify_and_login_with_fixture_signature(): void
    {
        if (! extension_loaded('gmp')) {
            $this->markTestSkipped('ext-gmp is required for signature recovery.');
        }

        $fixture = $this->siweFixture();

        WalletNonce::create([
            'address' => $fixture['address'],
            'nonce' => $fixture['nonce'],
            'message' => $fixture['message'],
            'domain' => $fixture['domain'],
            'uri' => $fixture['uri'],
            'chain_id' => $fixture['chain_id'],
            'issued_at' => $fixture['issued_at'],
            'expires_at' => $fixture['expiration_at'],
        ]);

        $manager = app(WalletAuthManager::class);

        $result = $manager->verifyAndLogin(
            address: $fixture['address'],
            signature: $fixture['signature'],
            chainId: $fixture['chain_id'],
        );

        $this->assertSame($fixture['address'], $result->user->wallet_address);
        $this->assertFalse($result->alreadyAuthenticated);
        $this->assertNotNull(WalletNonce::first()?->used_at);
    }

    public function test_full_challenge_flow_via_manager(): void
    {
        $manager = app(WalletAuthManager::class);

        $address = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0';

        $challenge = $manager->issueChallenge(
            address: $address,
            chainId: 1,
            domain: 'localhost',
            uri: 'http://localhost',
        );

        $this->assertStringContainsString('Nonce: '.$challenge->nonce, $challenge->message);
        $this->assertDatabaseHas('wallet_nonces', [
            'address' => strtolower($address),
            'nonce' => $challenge->nonce,
        ]);
    }

    public function test_verify_returns_existing_user_without_duplicate(): void
    {
        if (! extension_loaded('gmp')) {
            $this->markTestSkipped('ext-gmp is required for signature recovery.');
        }

        $fixture = $this->siweFixture();

        WalletNonce::create([
            'address' => $fixture['address'],
            'nonce' => $fixture['nonce'],
            'message' => $fixture['message'],
            'domain' => $fixture['domain'],
            'uri' => $fixture['uri'],
            'chain_id' => $fixture['chain_id'],
            'issued_at' => $fixture['issued_at'],
            'expires_at' => $fixture['expiration_at'],
        ]);

        $manager = app(WalletAuthManager::class);
        $first = $manager->verifyAndLogin($fixture['address'], $fixture['signature'], $fixture['chain_id']);

        WalletNonce::create([
            'address' => $fixture['address'],
            'nonce' => $fixture['nonce'].'2',
            'message' => $fixture['message'],
            'domain' => $fixture['domain'],
            'uri' => $fixture['uri'],
            'chain_id' => $fixture['chain_id'],
            'issued_at' => $fixture['issued_at'],
            'expires_at' => $fixture['expiration_at'],
        ]);

        Auth::login($first->user);

        $again = $manager->verifyAndLogin(
            address: $fixture['address'],
            signature: $fixture['signature'],
            chainId: $fixture['chain_id'],
            currentUser: $first->user,
        );

        $this->assertTrue($again->alreadyAuthenticated);
        $this->assertSame($first->user->id, $again->user->id);
    }
}
