<?php

namespace Tests\Feature;

use App\Services\EthereumSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_nonce_endpoint_returns_message(): void
    {
        $address = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0';

        $response = $this->postJson(route('auth.nonce'), ['address' => $address]);

        $response->assertOk()
            ->assertJsonStructure(['nonce', 'message']);

        $this->assertDatabaseHas('wallet_nonces', [
            'address' => strtolower($address),
        ]);
    }

    public function test_protected_route_requires_auth(): void
    {
        $this->get(route('dashboard'))->assertRedirect();
    }

}
