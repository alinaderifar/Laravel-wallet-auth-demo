<?php

namespace Tests\Support;

trait LoadsSiweFixture
{
    /**
     * @return array{
     *     description: string,
     *     address: string,
     *     chain_id: int,
     *     nonce: string,
     *     message: string,
     *     signature: string,
     *     domain: string,
     *     uri: string,
     *     issued_at: string,
     *     expiration_at: string,
     * }
     */
    protected function siweFixture(): array
    {
        $path = dirname(__DIR__).'/Fixtures/siwe-signature.json';
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }
}
