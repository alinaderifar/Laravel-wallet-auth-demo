<?php

namespace App\Services\Siwe;

use Carbon\CarbonInterface;

class SiweMessageBuilder
{
    /**
     * @param  array{
     *     domain: string,
     *     address: string,
     *     statement?: string|null,
     *     uri: string,
     *     version?: string,
     *     chainId: int,
     *     nonce: string,
     *     issuedAt: CarbonInterface,
     *     expirationTime?: CarbonInterface|null,
     * }  $params
     */
    public function build(array $params): string
    {
        $domain = $params['domain'];
        $address = $params['address'];
        $uri = $params['uri'];
        $version = $params['version'] ?? '1';
        $chainId = $params['chainId'];
        $nonce = $params['nonce'];
        $issuedAt = $params['issuedAt']->toIso8601String();
        $statement = $params['statement'] ?? null;

        $lines = [
            "{$domain} wants you to sign in with your Ethereum account:",
            $address,
            '',
        ];

        if ($statement !== null && $statement !== '') {
            $lines[] = $statement;
            $lines[] = '';
        }

        $lines[] = "URI: {$uri}";
        $lines[] = "Version: {$version}";
        $lines[] = "Chain ID: {$chainId}";
        $lines[] = "Nonce: {$nonce}";
        $lines[] = "Issued At: {$issuedAt}";

        if (! empty($params['expirationTime'])) {
            $lines[] = 'Expiration Time: '.$params['expirationTime']->toIso8601String();
        }

        return implode("\n", $lines);
    }
}
