<?php

namespace Tests\Feature\WalletAuth;

use AliNaderifar\LaravelWalletAuth\Models\WalletNonce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LoadsSiweFixture;
use Tests\TestCase;

class SiweVerifyFixtureTest extends TestCase
{
    use LoadsSiweFixture;
    use RefreshDatabase;

    public function test_verify_endpoint_accepts_fixture_signature(): void
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

        $response = $this->postJson(route('auth.verify'), [
            'address' => $fixture['address'],
            'signature' => $fixture['signature'],
            'chain_id' => $fixture['chain_id'],
        ]);

        $response->assertOk()
            ->assertJsonPath('wallet_address', $fixture['address'])
            ->assertJsonStructure(['csrf_token']);

        $this->assertAuthenticated();
    }
}
