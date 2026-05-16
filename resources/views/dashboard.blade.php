<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Protected — Wallet Auth Sandbox</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; }
        code { background: #f4f4f5; padding: 0.1rem 0.35rem; }
    </style>
</head>
<body>
    <h1>Protected route</h1>
    <p>You reached this page because Laravel's <code>auth</code> middleware found a valid session.</p>
    <ul>
        <li>User id: {{ $user->id }}</li>
        <li>Wallet: <code>{{ $user->wallet_address }}</code></li>
        <li>Name: {{ $user->name }}</li>
    </ul>
    <p><a href="{{ route('sandbox') }}">← Back to sandbox</a></p>
</body>
</html>
