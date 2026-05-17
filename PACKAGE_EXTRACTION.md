# Package integration

Wallet auth logic lives in **`../wallet-auth`** (`alinaderifar/laravel-wallet-auth`).

This demo app only provides:

- `app/Http/Controllers/WalletAuthController.php` — HTTP + session
- `config/wallet-auth.php` — `user_model` and app-specific SIWE text
- `routes/web.php`, sandbox views, `users.wallet_address` migration
- Tests that exercise the package via the HTTP layer and fixtures

Install / update the path dependency:

```bash
composer update alinaderifar/laravel-wallet-auth
```
