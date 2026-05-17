<?php

return [
    'user_model' => App\Models\User::class,

    'nonce_ttl_minutes' => 5,

    'siwe' => [
        'version' => '1',
        'statement' => 'Sign in to the wallet auth sandbox.',
    ],

    /*
    | Allowed chain IDs (decimal). Empty = allow any chain MetaMask reports.
    | 1 = Ethereum mainnet, 11155111 = Sepolia
    */
    'allowed_chain_ids' => [],

    'errors' => [
        'wallet_not_connected' => ['message' => 'Connect a wallet before signing in.', 'retryable' => false],
        'nonce_expired' => ['message' => 'Sign-in challenge expired. Request a new one.', 'retryable' => true],
        'nonce_missing' => ['message' => 'No active sign-in challenge for this wallet.', 'retryable' => true],
        'signature_invalid' => ['message' => 'Signature does not match this wallet.', 'retryable' => true],
        'chain_mismatch' => ['message' => 'Switch MetaMask to the network used when the challenge was created.', 'retryable' => true],
        'domain_mismatch' => ['message' => 'Sign-in domain does not match this application.', 'retryable' => false],
        'uri_mismatch' => ['message' => 'Sign-in URI does not match this application.', 'retryable' => false],
        'address_mismatch' => ['message' => 'Signed address does not match the connected wallet.', 'retryable' => false],
        'siwe_invalid' => ['message' => 'Sign-in message format is invalid.', 'retryable' => true],
        'already_authenticated' => ['message' => 'You are already signed in.', 'retryable' => false],
        'session_wallet_mismatch' => ['message' => 'Connected wallet does not match your session. Sign in again or log out.', 'retryable' => true],
    ],
];
