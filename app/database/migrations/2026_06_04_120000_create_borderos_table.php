<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borderos', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique(); // BORD-0001
            $table->string('description')->nullable();
            $table->enum('status', ['rascunho', 'aguardando_aprovacao', 'aprovado', 'reprovado', 'pago'])->default('rascunho');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedInteger('items_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_for_approval_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // Agora adiciona a FK em payables.bordero_id (criada na migration de payables)
        Schema::table('payables', function (Blueprint $table) {
            $table->foreign('bordero_id')->references('id')->on('borderos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->dropForeign(['bordero_id']);
        });
        Schema::dropIfExists('borderos');
    }
};
