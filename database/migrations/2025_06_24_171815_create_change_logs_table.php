<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('change_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type')->comment('Тип сущности (User, Role, Permission)');
            $table->unsignedBigInteger('entity_id')->comment('ID мутирующей сущности');
            $table->unsignedBigInteger('user_id')->comment('ID пользователя, выполнившего операцию');
            $table->enum('action', ['created', 'updated', 'deleted'])->comment('Тип операции');
            $table->json('old_values')->nullable()->comment('Значения до мутации');
            $table->json('new_values')->nullable()->comment('Значения после мутации');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');

       
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_logs');
    }

};
