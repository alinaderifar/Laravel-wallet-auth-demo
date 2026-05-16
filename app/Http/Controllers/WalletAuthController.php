<?php

namespace App\Http\Controllers;

use App\Exceptions\WalletAuthException;
use App\Models\User;
use App\Models\WalletNonce;
use App\Services\EthereumSignatureVerifier;
use App\Services\Siwe\SiweMessageBuilder;
use App\Services\Siwe\SiweValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WalletAuthController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'authenticated' => false,
                'wallet_address' => null,
            ]);
        }

        return response()->json([
            'authenticated' => true,
            'wallet_address' => $user->wallet_address,
        ]);
    }

    public function nonce(Request $request, SiweMessageBuilder $builder): JsonResponse
    {
        if ($request->user()) {
            throw new WalletAuthException('already_authenticated');
        }

        $validated = $request->validate([
            'address' => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'chain_id' => ['required', 'integer', 'min:1'],
        ]);

        $address = strtolower($validated['address']);
        $chainId = (int) $validated['chain_id'];

        $allowed = config('wallet-auth.allowed_chain_ids', []);
        if ($allowed !== [] && ! in_array($chainId, $allowed, true)) {
            throw new WalletAuthException('chain_mismatch');
        }

        $nonce = bin2hex(random_bytes(16));
        $ttl = (int) config('wallet-auth.nonce_ttl_minutes', 5);
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addMinutes($ttl);

        $domain = $request->getHost();
        $uri = rtrim(config('app.url'), '/');

        $message = $builder->build([
            'domain' => $domain,
            'address' => $address,
            'statement' => config('wallet-auth.siwe.statement'),
            'uri' => $uri,
            'version' => config('wallet-auth.siwe.version', '1'),
            'chainId' => $chainId,
            'nonce' => $nonce,
            'issuedAt' => $issuedAt,
            'expirationTime' => $expiresAt,
        ]);

        WalletNonce::create([
            'address' => $address,
            'nonce' => $nonce,
            'message' => $message,
            'domain' => $domain,
            'uri' => $uri,
            'chain_id' => $chainId,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'nonce' => $nonce,
            'message' => $message,
            'chain_id' => $chainId,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function verify(
        Request $request,
        EthereumSignatureVerifier $verifier,
        SiweValidator $siweValidator,
    ): JsonResponse {
        $validated = $request->validate([
            'address' => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'signature' => ['required', 'string'],
            'chain_id' => ['required', 'integer', 'min:1'],
        ]);

        $address = strtolower($validated['address']);
        $chainId = (int) $validated['chain_id'];

        if ($request->user()) {
            if (strtolower((string) $request->user()->wallet_address) === $address) {
                return response()->json([
                    'message' => 'Already signed in',
                    'wallet_address' => $request->user()->wallet_address,
                    'already_authenticated' => true,
                ]);
            }

            throw new WalletAuthException('session_wallet_mismatch');
        }

        $walletNonce = WalletNonce::query()
            ->where('address', $address)
            ->where('chain_id', $chainId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $walletNonce || ! $walletNonce->message) {
            throw new WalletAuthException('nonce_missing');
        }

        $siweValidator->validate($walletNonce->message, $walletNonce, $address);

        if (! $verifier->verify($walletNonce->message, $validated['signature'], $address)) {
            throw new WalletAuthException('signature_invalid');
        }

        $walletNonce->update(['used_at' => now()]);

        $user = User::query()->firstOrCreate(
            ['wallet_address' => $address],
            [
                'name' => 'Wallet '.substr($address, -6),
                'email' => $address.'@wallet.local',
                'password' => bcrypt(Str::random(32)),
            ]
        );

        Auth::login($user, remember: true);

        return response()->json([
            'message' => 'Logged in',
            'wallet_address' => $user->wallet_address,
            'csrf_token' => csrf_token(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out',
            'csrf_token' => csrf_token(),
        ]);
    }
}
