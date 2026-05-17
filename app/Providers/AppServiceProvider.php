<?php

namespace App\Providers;

use App\WalletAuth\Contracts\SignatureVerifierInterface;
use App\WalletAuth\Services\EthereumSignatureVerifier;
use App\WalletAuth\WalletAuthManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SignatureVerifierInterface::class, EthereumSignatureVerifier::class);
        $this->app->singleton(WalletAuthManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
