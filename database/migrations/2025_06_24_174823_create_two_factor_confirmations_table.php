<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
      public function up(): void
    {
        Schema::create('two_factor_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('code', 6)->comment('Код подтверждения');
            $table->string('type')->comment('Тип: enable, disable, login');
            $table->integer('attempts')->default(0)->comment('Количество попыток');
            $table->timestamp('expires_at')->comment('Время истечения');
            $table->boolean('used')->default(false)->comment('Использован ли код');
            $table->timestamps();

            $table->index(['user_id', 'code', 'type']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_confirmations');
    }

};
