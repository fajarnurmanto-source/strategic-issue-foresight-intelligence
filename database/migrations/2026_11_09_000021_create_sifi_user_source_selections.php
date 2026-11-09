<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sifi_user_source_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider_id', 64);
            $table->boolean('selected')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'provider_id']);
        });

        Schema::create('sifi_user_source_selection_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider_id', 64);
            $table->string('event_code', 32);
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sifi_user_source_selection_audits');
        Schema::dropIfExists('sifi_user_source_selections');
    }
};
