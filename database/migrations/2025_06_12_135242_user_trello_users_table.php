<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trello_users', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 24)->unique(); // Trello использует 24-символьные ID
            $table->string('full_name', 100)->nullable();
            $table->string('username', 50)->nullable();
            $table->string('avatar_url', 255)->nullable();
            $table->text('trello_token')->nullable();
            $table->timestamps();

            // Добавляем внешний ключ к таблице telegram_users
            $table->foreign('user_id')
                ->references('trello_id')
                ->on('telegram_users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_users');
    }
};
