<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trello_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_user_id');
            $table->string('trello_id', 100)->nullable();
            $table->string('full_name', 100)->nullable();
            $table->string('username', 50)->nullable();
            $table->string('avatar_url', 255)->nullable();
            $table->text('trello_token')->nullable();
            $table->timestamps();

            $table->foreign('telegram_user_id')
                ->references('id')
                ->on('telegram_users')
                ->onDelete('cascade');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('trello_users');
    }
};
