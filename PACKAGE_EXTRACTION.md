# Extracting `app/WalletAuth` into your Spatie package

Copy the entire `app/WalletAuth/` tree into `src/` and rename the namespace:

| Demo (now) | Package (target) |
|------------|------------------|
| `App\WalletAuth\` | `YourVendor\WalletAuth\` |
| `config/wallet-auth.php` | `config/wallet-auth.php` (mergeable) |
| `database/migrations/*wallet*` | `database/migrations/` |
| `tests/Fixtures/siwe-signature.json` | `tests/Fixtures/` |
| `tests/Unit/WalletAuth/*` | `tests/Unit/` (Testbench) |
| `tests/Support/LoadsSiweFixture.php` | `tests/Support/` |

## Stays in the demo app only

- `app/Http/Controllers/WalletAuthController.php` — thin HTTP + session layer
- `resources/views/sandbox.blade.php`
- `routes/web.php`

## Service provider bindings (from `AppServiceProvider`)

```php
$this->app->singleton(SignatureVerifierInterface::class, EthereumSignatureVerifier::class);
$this->app->singleton(WalletAuthManager::class);
```

## Regenerate the crypto fixture

```bash
php scripts/generate-siwe-fixture.php
```

Uses Hardhat account #0 (public test key). Never use that key on mainnet.
