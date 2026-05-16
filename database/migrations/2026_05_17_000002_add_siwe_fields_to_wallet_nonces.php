<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_nonces', function (Blueprint $table) {
            $table->text('message')->nullable()->after('nonce');
            $table->string('domain')->nullable()->after('message');
            $table->string('uri')->nullable()->after('domain');
            $table->unsignedBigInteger('chain_id')->nullable()->after('uri');
            $table->timestamp('issued_at')->nullable()->after('chain_id');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_nonces', function (Blueprint $table) {
            $table->dropColumn(['message', 'domain', 'uri', 'chain_id', 'issued_at']);
        });
    }
};
