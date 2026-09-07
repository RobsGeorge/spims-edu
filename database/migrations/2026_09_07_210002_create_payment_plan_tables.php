<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedTinyInteger('installment_count');
            $table->string('interval')->default('MONTH');
            $table->date('start_on');
            $table->string('status')->default('OPEN');
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });

        Schema::create('payment_plan_installments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_plan_id')->constrained('payment_plans')->cascadeOnDelete();
            $table->date('due_on');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency');
            $table->string('status')->default('PENDING');
            $table->foreignUlid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamp('dunning_sent_at')->nullable();
            $table->timestamps();

            $table->index(['payment_plan_id', 'status']);
            $table->index(['due_on', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plan_installments');
        Schema::dropIfExists('payment_plans');
    }
};
