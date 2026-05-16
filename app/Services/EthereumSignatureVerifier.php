<?php

namespace App\Services;

use Elliptic\EC;
use kornrunner\Keccak;

/**
 * Recovers the Ethereum address from a MetaMask personal_sign signature.
 */
class EthereumSignatureVerifier
{
    public function verify(string $message, string $signature, string $expectedAddress): bool
    {
        $recovered = $this->recoverAddress($message, $signature);

        if ($recovered === null) {
            return false;
        }

        return strtolower($recovered) === strtolower($expectedAddress);
    }

    public function recoverAddress(string $message, string $signature): ?string
    {
        $signature = $this->normalizeSignature($signature);
        if ($signature === null) {
            return null;
        }

        $hash = Keccak::hash(
            "\x19Ethereum Signed Message:\n".strlen($message).$message,
            256
        );

        $r = substr($signature, 0, 64);
        $s = substr($signature, 64, 64);
        $v = hexdec(substr($signature, 128, 2));

        if ($v < 27) {
            $v += 27;
        }

        $ec = new EC('secp256k1');
        $publicKey = $ec->recoverPubKey(
            $hash,
            ['r' => $r, 's' => $s],
            $v - 27
        );

        $publicKeyHex = $publicKey->encode('hex');
        if (str_starts_with($publicKeyHex, '04')) {
            $publicKeyHex = substr($publicKeyHex, 2);
        }

        $addressHash = Keccak::hash(hex2bin($publicKeyHex), 256);

        return '0x'.substr($addressHash, -40);
    }

    private function normalizeSignature(string $signature): ?string
    {
        $signature = str_starts_with($signature, '0x') ? substr($signature, 2) : $signature;

        if (! preg_match('/^[a-fA-F0-9]{130}$/', $signature)) {
            return null;
        }

        return $signature;
    }
}
