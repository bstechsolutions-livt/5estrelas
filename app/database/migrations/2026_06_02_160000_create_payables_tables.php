<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payables', function (Blueprint $table) {
            $table->id();
            $table->string('title_number')->nullable(); // número do título (vem do Senior futuramente)
            $table->string('supplier_name'); // fornecedor
            $table->string('supplier_cnpj', 20)->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('due_date'); // vencimento
            $table->date('issue_date')->nullable(); // emissão
            $table->string('description')->nullable();
            $table->string('category')->nullable(); // tipo/natureza
            $table->enum('status', ['pendente', 'em_preparacao', 'aguardando_aprovacao', 'aprovado', 'reprovado', 'pago'])->default('pendente');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_for_approval_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('bordero_id')->nullable(); // será constrained quando criarmos a tabela
            $table->string('senior_id')->nullable(); // ID no Senior (integração futura)
            $table->timestamps();

            $table->index('status');
            $table->index('due_date');
            $table->index('supplier_name');
            $table->index('bordero_id');
        });

        Schema::create('payable_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name'); // nome do arquivo
            $table->string('path'); // caminho no storage
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->default(0); // bytes
            $table->timestamps();
        });

        Schema::create('payable_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('type')->default('comment'); // comment, status_change, approval, rejection
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_comments');
        Schema::dropIfExists('payable_documents');
        Schema::dropIfExists('payables');
    }
};
