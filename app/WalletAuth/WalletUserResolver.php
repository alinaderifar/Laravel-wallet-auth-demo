<?php

namespace App\WalletAuth;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

class WalletUserResolver
{
    public function findOrCreate(string $address): Authenticatable
    {
        $model = config('wallet-auth.user_model', User::class);

        return $model::query()->firstOrCreate(
            ['wallet_address' => $address],
            [
                'name' => 'Wallet '.substr($address, -6),
                'email' => $address.'@wallet.local',
                'password' => bcrypt(Str::random(32)),
            ]
        );
    }
}
