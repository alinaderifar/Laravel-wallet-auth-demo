<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('wallet_address', 42)->nullable()->unique()->after('id');
        });

        Schema::create('wallet_nonces', function (Blueprint $table) {
            $table->id();
            $table->string('address', 42)->index();
            $table->string('nonce', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_nonces');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wallet_address');
        });
    }
};
