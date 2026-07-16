<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_flow_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('area', 50)->index();
            $table->unsignedSmallInteger('step_order');
            $table->string('label', 120)->nullable();
            $table->json('codccu')->default('[]');
            $table->json('title_patterns')->default('[]');
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['area', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_flow_overrides');
    }
};
