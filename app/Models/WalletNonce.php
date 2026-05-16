<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletNonce extends Model
{
    protected $fillable = [
        'address',
        'nonce',
        'message',
        'domain',
        'uri',
        'chain_id',
        'issued_at',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'chain_id' => 'integer',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
