<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('demo_users', function (Blueprint $t): void {
            $t->bigInteger('chat_id')->primary();
            $t->string('locale', 5);
            $t->string('locale_source', 10);
            $t->string('name', 60)->nullable();
            $t->boolean('awaiting_name')->default(false);
        });
        Schema::create('demo_updates', fn (Blueprint $t) => $t->bigInteger('update_id')->primary());
        Schema::create('demo_outbox', function (Blueprint $t): void {
            $t->id();
            $t->bigInteger('chat_id');
            $t->string('event_key')->unique();
            $t->text('payload');
            $t->string('kind');
            $t->unsignedInteger('revision')->default(0);
            $t->string('state');
            $t->unsignedInteger('attempts');
            $t->bigInteger('next_at');
            $t->bigInteger('message_id')->nullable();
            $t->integer('error_code')->nullable();
            $t->index(['state', 'next_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('demo_outbox');
        Schema::dropIfExists('demo_updates');
        Schema::dropIfExists('demo_users');
    }
};
