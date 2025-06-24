<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->comment('2FA включена');
            $table->string('two_factor_secret')->nullable()->comment('Секретный ключ для 2FA');
            $table->timestamp('two_factor_confirmed_at')->nullable()->comment('Дата подтверждения 2FA');
            $table->json('two_factor_recovery_codes')->nullable()->comment('Коды восстановления');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_enabled',
                'two_factor_secret', 
                'two_factor_confirmed_at',
                'two_factor_recovery_codes'
            ]);
        });
    }

};
