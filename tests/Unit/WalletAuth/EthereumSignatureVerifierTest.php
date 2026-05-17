<?php

namespace Tests\Unit\WalletAuth;

use AliNaderifar\LaravelWalletAuth\Services\EthereumSignatureVerifier;
use Tests\Support\LoadsSiweFixture;
use Tests\TestCase;

class EthereumSignatureVerifierTest extends TestCase
{
    use LoadsSiweFixture;

    public function test_recovers_hardhat_fixture_address(): void
    {
        if (! extension_loaded('gmp')) {
            $this->markTestSkipped('ext-gmp is required for signature recovery.');
        }

        $fixture = $this->siweFixture();
        $verifier = new EthereumSignatureVerifier;

        $recovered = $verifier->recoverAddress($fixture['message'], $fixture['signature']);

        $this->assertSame($fixture['address'], strtolower((string) $recovered));
    }

    public function test_verify_accepts_valid_fixture_signature(): void
    {
        if (! extension_loaded('gmp')) {
            $this->markTestSkipped('ext-gmp is required for signature recovery.');
        }

        $fixture = $this->siweFixture();
        $verifier = new EthereumSignatureVerifier;

        $this->assertTrue(
            $verifier->verify($fixture['message'], $fixture['signature'], $fixture['address'])
        );
    }

    public function test_verify_rejects_wrong_address(): void
    {
        if (! extension_loaded('gmp')) {
            $this->markTestSkipped('ext-gmp is required for signature recovery.');
        }

        $fixture = $this->siweFixture();
        $verifier = new EthereumSignatureVerifier;

        $this->assertFalse(
            $verifier->verify(
                $fixture['message'],
                $fixture['signature'],
                '0x0000000000000000000000000000000000000001'
            )
        );
    }
}
