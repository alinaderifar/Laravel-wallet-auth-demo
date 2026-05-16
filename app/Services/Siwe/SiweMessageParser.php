<?php

namespace App\Services\Siwe;

use Carbon\Carbon;

class SiweMessageParser
{
    /**
     * @return array{
     *     domain: string,
     *     address: string,
     *     statement: ?string,
     *     uri: string,
     *     version: string,
     *     chainId: int,
     *     nonce: string,
     *     issuedAt: Carbon,
     *     expirationTime: ?Carbon,
     * }
     */
    public function parse(string $message): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $message) ?: [];

        if (count($lines) < 7) {
            throw new \InvalidArgumentException('SIWE message is too short.');
        }

        if (! preg_match('/^(.+) wants you to sign in with your Ethereum account:$/', $lines[0], $domainMatch)) {
            throw new \InvalidArgumentException('SIWE header line is invalid.');
        }

        $address = trim($lines[1] ?? '');
        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            throw new \InvalidArgumentException('SIWE address line is invalid.');
        }

        $cursor = 2;
        if (($lines[$cursor] ?? '') === '') {
            $cursor++;
        }

        $statement = null;
        $resourceLines = [];
        while ($cursor < count($lines)) {
            $line = $lines[$cursor];
            if (str_starts_with($line, 'URI: ')) {
                break;
            }
            $resourceLines[] = $line;
            $cursor++;
        }

        if ($resourceLines !== [] && ! ($resourceLines === [''] || (count($resourceLines) === 1 && $resourceLines[0] === ''))) {
            $statement = rtrim(implode("\n", $resourceLines), "\n");
        }

        $fields = [];
        for ($i = $cursor; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (! str_contains($line, ': ')) {
                continue;
            }
            [$key, $value] = explode(': ', $line, 2);
            $fields[$key] = $value;
        }

        foreach (['URI', 'Version', 'Chain ID', 'Nonce', 'Issued At'] as $required) {
            if (! isset($fields[$required])) {
                throw new \InvalidArgumentException("SIWE field [{$required}] is missing.");
            }
        }

        return [
            'domain' => $domainMatch[1],
            'address' => strtolower($address),
            'statement' => $statement,
            'uri' => $fields['URI'],
            'version' => $fields['Version'],
            'chainId' => (int) $fields['Chain ID'],
            'nonce' => $fields['Nonce'],
            'issuedAt' => Carbon::parse($fields['Issued At']),
            'expirationTime' => isset($fields['Expiration Time'])
                ? Carbon::parse($fields['Expiration Time'])
                : null,
        ];
    }
}
