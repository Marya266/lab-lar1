<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
      public function up()
    {
        Schema::create('messengers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Наименование мессенджера');
            $table->text('description')->nullable()->comment('Описание мессенджера');
            $table->enum('environment', ['local', 'dev', 'prod'])->comment('Среда окружения');
            $table->string('token_env_variable')->comment('Название переменной окружения с токеном/ключом');
            $table->string('api_endpoint')->comment('API endpoint мессенджера');
            $table->boolean('is_active')->default(true)->comment('Активен ли мессенджер');
            $table->timestamps();
            
            $table->index(['environment', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('messengers');
    }

};
