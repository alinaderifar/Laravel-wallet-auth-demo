<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WalletNonce;
use App\Services\EthereumSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WalletAuthController extends Controller
{
    public function nonce(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'],
        ]);

        $address = strtolower($validated['address']);
        $nonce = bin2hex(random_bytes(16));
        $ttl = (int) config('wallet-auth.nonce_ttl_minutes', 5);

        WalletNonce::create([
            'address' => $address,
            'nonce' => $nonce,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $message = $this->signMessage($address, $nonce);

        return response()->json([
            'nonce' => $nonce,
            'message' => $message,
        ]);
    }

    public function verify(Request $request, EthereumSignatureVerifier $verifier): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'signature' => ['required', 'string'],
        ]);

        $address = strtolower($validated['address']);

        $walletNonce = WalletNonce::query()
            ->where('address', $address)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $walletNonce) {
            return response()->json(['message' => 'No valid nonce for this wallet. Request a new one.'], 422);
        }

        $message = $this->signMessage($address, $walletNonce->nonce);

        if (! $verifier->verify($message, $validated['signature'], $address)) {
            return response()->json(['message' => 'Signature verification failed.'], 401);
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
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }

    private function signMessage(string $address, string $nonce): string
    {
        return str_replace(
            [':app', ':address', ':nonce'],
            [config('app.name'), $address, $nonce],
            config('wallet-auth.message')
        );
    }
}
