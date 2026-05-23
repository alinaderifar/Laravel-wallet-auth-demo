# Wallet Auth Demo

**https://github.com/alinaderifar/Laravel-wallet-auth-demo**

Sandbox Laravel app for [Sign-In With Ethereum (SIWE)](https://eips.ethereum.org/EIPS/eip-4361) wallet login, powered by **[alinaderifar/laravel-wallet-auth](https://github.com/alinaderifar/Laravel-wallet-auth)**.

## Package

All wallet-auth logic (SIWE messages, signature verification, nonces, user resolution) lives in the package repository:

**https://github.com/alinaderifar/Laravel-wallet-auth**

Install / upgrade:

```bash
composer require alinaderifar/laravel-wallet-auth
```

Documentation, configuration, and migrations: see the [package README](https://github.com/alinaderifar/Laravel-wallet-auth#readme).

## What this repo contains

Thin demo layer only:

- `app/Http/Controllers/WalletAuthController.php` — HTTP routes + session login
- `config/wallet-auth.php` — `user_model`, SIWE statement, allowed chains
- `resources/views/sandbox.blade.php` — minimal MetaMask UI
- `routes/web.php` — sandbox + auth endpoints

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Requirements: PHP 8.1+, `ext-gmp`, [MetaMask](https://metamask.io/) (or compatible wallet).

Publish package assets on a fresh install if migrations/config are missing:

```bash
php artisan vendor:publish --tag=wallet-auth-config
php artisan vendor:publish --tag=wallet-auth-migrations
php artisan migrate
```

## Flow

1. Connect MetaMask  
2. Request SIWE nonce  
3. Sign message  
4. Verify signature → Laravel session  
5. Open `/dashboard` (protected route)

## Tests

```bash
php artisan test
```

## License

MIT
