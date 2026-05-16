<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletAuthException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        ?string $message = null,
    ) {
        $config = config("wallet-auth.errors.{$errorCode}", []);
        parent::__construct($message ?? ($config['message'] ?? 'Authentication failed.'));
    }

    public function render(Request $request): JsonResponse
    {
        $config = config("wallet-auth.errors.{$this->errorCode}", []);

        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'retryable' => (bool) ($config['retryable'] ?? false),
        ], $this->statusCode());
    }

    private function statusCode(): int
    {
        return match ($this->errorCode) {
            'signature_invalid', 'address_mismatch', 'domain_mismatch', 'uri_mismatch' => 401,
            'already_authenticated' => 409,
            default => 422,
        };
    }
}
