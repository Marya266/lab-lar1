<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_requests', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10)->comment('HTTP метод (GET, POST, PUT, DELETE)');
            $table->text('url')->comment('Полный адрес вызванного метода');
            $table->string('route_name')->nullable()->comment('Имя маршрута');
            $table->string('controller_class')->nullable()->comment('Класс контроллера');
            $table->string('controller_method')->nullable()->comment('Метод контроллера');
            $table->longText('request_body')->nullable()->comment('Содержимое тела запроса');
            $table->json('request_headers')->comment('Заголовки запроса');
            $table->unsignedBigInteger('user_id')->nullable()->comment('ID пользователя');
            $table->string('ip_address', 45)->comment('IP адрес пользователя');
            $table->text('user_agent')->nullable()->comment('User-Agent пользователя');
            $table->integer('response_status')->comment('Код статуса ответа');
            $table->longText('response_body')->nullable()->comment('Содержимое тела ответа');
            $table->json('response_headers')->comment('Заголовки ответа');
            $table->decimal('execution_time', 8, 3)->nullable()->comment('Время выполнения запроса в секундах');
            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index(['method', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['response_status', 'created_at']);
            $table->index('created_at');

            // Внешний ключ на пользователя (может быть null для неавторизованных запросов)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_requests');
    }
};
