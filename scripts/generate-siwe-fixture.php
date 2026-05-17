<?php

/**
 * Generates tests/Fixtures/siwe-signature.json.
 * Uses a fixed test private key — never use on mainnet.
 */

require __DIR__.'/../vendor/autoload.php';

use AliNaderifar\LaravelWalletAuth\Services\EthereumSignatureVerifier;
use Elliptic\EC;
use kornrunner\Keccak;

$privateKey = 'ac0974bec39a39a671095e0c6d48022ad6b44f8c6e12b0c0d4e8b8c8c8c8c8c8c8c8c8c8c8c8c8c8c8c8c8c8c8c8';

$ec = new EC('secp256k1');
$key = $ec->keyFromPrivate($privateKey, 'hex');
$publicKeyHex = $key->getPublic(false, 'hex');
if (str_starts_with($publicKeyHex, '04')) {
    $publicKeyHex = substr($publicKeyHex, 2);
}
$addressHash = Keccak::hash(hex2bin($publicKeyHex), 256);
$address = strtolower('0x'.substr($addressHash, -40));

$nonce = 'fixture_nonce_deadbeef123456';
$issuedAt = '2020-01-01T00:00:00+00:00';
$expiration = '2099-01-01T00:00:00+00:00';

$message = implode("\n", [
    'localhost wants you to sign in with your Ethereum account:',
    $address,
    '',
    'Sign in to the wallet auth sandbox.',
    '',
    'URI: http://localhost',
    'Version: 1',
    'Chain ID: 1',
    "Nonce: {$nonce}",
    "Issued At: {$issuedAt}",
    "Expiration Time: {$expiration}",
]);

$hash = Keccak::hash("\x19Ethereum Signed Message:\n".strlen($message).$message, 256);
$sig = $key->sign($hash, ['canonical' => true]);

$r = str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT);
$s = str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT);

$verifier = new EthereumSignatureVerifier;
$signature = null;

for ($recoveryParam = 0; $recoveryParam <= 1; $recoveryParam++) {
    $candidate = '0x'.$r.$s.dechex($recoveryParam + 27);
    $recovered = strtolower((string) $verifier->recoverAddress($message, $candidate));

    if ($recovered === $address) {
        $signature = $candidate;
        break;
    }
}

if ($signature === null) {
    fwrite(STDERR, "Could not produce a recoverable signature for {$address}\n");
    exit(1);
}

$fixture = [
    'description' => 'Deterministic test keypair — CI only, never use on mainnet',
    'address' => $address,
    'chain_id' => 1,
    'nonce' => $nonce,
    'message' => $message,
    'signature' => $signature,
    'domain' => 'localhost',
    'uri' => 'http://localhost',
    'issued_at' => $issuedAt,
    'expiration_at' => $expiration,
];

$path = __DIR__.'/../tests/Fixtures/siwe-signature.json';
file_put_contents($path, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

echo "Wrote {$path}\n";
echo "Address: {$address}\n";
echo "Signature: {$signature}\n";
