<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique()->comment('Наименование разрешения');
            $table->text('description')->nullable()->comment('Описание разрешения');
            $table->string('code', 100)->unique()->comment('Шифр разрешения');
            $table->timestamps();
            $table->softDeletes();
            
            // Отдельные индексы вместо составного
            $table->index('name');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }


};

