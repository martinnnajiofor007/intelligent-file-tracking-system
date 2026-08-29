<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('dedup_key')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['related_type', 'related_id']);
            $table->unique('dedup_key');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};
