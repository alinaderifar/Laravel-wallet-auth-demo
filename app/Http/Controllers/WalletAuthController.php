<?php

namespace App\Http\Controllers;

use App\WalletAuth\WalletAuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletAuthController extends Controller
{
    public function __construct(
        private readonly WalletAuthManager $walletAuth,
    ) {}

    public function status(Request $request): JsonResponse
    {
        return response()->json(
            $this->walletAuth->status($request->user())->toArray()
        );
    }

    public function nonce(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'chain_id' => ['required', 'integer', 'min:1'],
        ]);

        $challenge = $this->walletAuth->issueChallenge(
            address: $validated['address'],
            chainId: (int) $validated['chain_id'],
            domain: $request->getHost(),
            uri: rtrim(config('app.url'), '/'),
            currentUser: $request->user(),
        );

        return response()->json($challenge->toArray());
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'signature' => ['required', 'string'],
            'chain_id' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->walletAuth->verifyAndLogin(
            address: $validated['address'],
            signature: $validated['signature'],
            chainId: (int) $validated['chain_id'],
            currentUser: $request->user(),
        );

        if (! $result->alreadyAuthenticated) {
            Auth::login($result->user, remember: true);
        }

        return response()->json(
            $result->toArray(csrf_token())
        );
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
