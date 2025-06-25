<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::create('users_and_messengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ID пользователя');
            $table->foreignId('messenger_id')->constrained()->onDelete('cascade')->comment('ID мессенджера');
            $table->string('messenger_user_id')->comment('ID/имя пользователя в мессенджере');
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending')->comment('Статус подтверждения');
            $table->timestamp('confirmed_at')->nullable()->comment('Дата подтверждения');
            $table->boolean('notifications_enabled')->default(true)->comment('Разрешены ли уведомления');
            $table->string('verification_code')->nullable()->comment('Код верификации');
            $table->timestamp('verification_expires_at')->nullable()->comment('Время истечения кода');
            $table->timestamps();
            
            $table->unique(['user_id', 'messenger_id']);
            $table->index(['status', 'notifications_enabled']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('users_and_messengers');
    }

};
