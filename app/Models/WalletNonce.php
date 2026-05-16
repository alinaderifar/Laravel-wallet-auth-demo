<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletNonce extends Model
{
    protected $fillable = [
        'address',
        'nonce',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
