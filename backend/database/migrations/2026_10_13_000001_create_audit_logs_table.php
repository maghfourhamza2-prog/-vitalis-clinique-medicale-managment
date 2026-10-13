<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Deliberately not a foreign key: deleting a user must not rewrite
            // historical audit evidence or erase the actor identifier.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->unsignedSmallInteger('status_code');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->char('integrity_hash', 64);
            $table->timestamp('created_at')->index();

            $table->index(['user_id', 'created_at']);
            $table->index(['method', 'created_at']);
            $table->index(['status_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
