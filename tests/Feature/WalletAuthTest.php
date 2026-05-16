<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Siwe\SiweMessageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_nonce_returns_siwe_message_with_chain_id(): void
    {
        $address = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0';

        $response = $this->postJson(route('auth.nonce'), [
            'address' => $address,
            'chain_id' => 1,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['nonce', 'message', 'chain_id', 'expires_at']);

        $message = $response->json('message');
        $this->assertStringContainsString('wants you to sign in with your Ethereum account:', $message);
        $this->assertStringContainsString('Chain ID: 1', $message);
        $this->assertStringContainsString('Nonce: '.$response->json('nonce'), $message);

        $this->assertDatabaseHas('wallet_nonces', [
            'address' => strtolower($address),
            'chain_id' => 1,
        ]);
    }

    public function test_nonce_requires_chain_id(): void
    {
        $this->postJson(route('auth.nonce'), [
            'address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
        ])->assertUnprocessable();
    }

    public function test_status_when_guest(): void
    {
        $this->getJson(route('auth.status'))
            ->assertOk()
            ->assertJson(['authenticated' => false, 'wallet_address' => null]);
    }

    public function test_status_when_authenticated(): void
    {
        $user = User::factory()->create([
            'wallet_address' => '0x742d35cc6634c0532925a3b844bc9e7595f0beb0',
            'email' => '0x742d35cc6634c0532925a3b844bc9e7595f0beb0@wallet.local',
        ]);

        $this->actingAs($user)
            ->getJson(route('auth.status'))
            ->assertOk()
            ->assertJson([
                'authenticated' => true,
                'wallet_address' => '0x742d35cc6634c0532925a3b844bc9e7595f0beb0',
            ]);
    }

    public function test_protected_route_requires_auth(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('sandbox'));
    }

    public function test_logout_returns_fresh_csrf_token(): void
    {
        $user = User::factory()->create([
            'wallet_address' => '0x742d35cc6634c0532925a3b844bc9e7595f0beb0',
            'email' => '0x742d35cc6634c0532925a3b844bc9e7595f0beb0@wallet.local',
        ]);

        $oldToken = csrf_token();

        $response = $this->actingAs($user)->postJson(route('auth.logout'));

        $response->assertOk()
            ->assertJsonStructure(['message', 'csrf_token']);

        $newToken = $response->json('csrf_token');
        $this->assertNotSame($oldToken, $newToken);
    }

    public function test_siwe_builder_matches_parser_round_trip(): void
    {
        $builder = app(SiweMessageBuilder::class);
        $issuedAt = now();

        $message = $builder->build([
            'domain' => 'localhost',
            'address' => '0x742d35cc6634c0532925a3b844bc9e7595f0beb0',
            'statement' => 'Sign in to the wallet auth sandbox.',
            'uri' => 'http://localhost',
            'version' => '1',
            'chainId' => 1,
            'nonce' => 'abc123nonce',
            'issuedAt' => $issuedAt,
            'expirationTime' => $issuedAt->copy()->addMinutes(5),
        ]);

        $parsed = app(\App\Services\Siwe\SiweMessageParser::class)->parse($message);

        $this->assertSame('localhost', $parsed['domain']);
        $this->assertSame(1, $parsed['chainId']);
        $this->assertSame('abc123nonce', $parsed['nonce']);
    }
}
