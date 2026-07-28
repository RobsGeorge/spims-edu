<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('users');
            $table->foreignUlid('enrollment_id')->nullable()->unique()->constrained('enrollments')->nullOnDelete();
            $table->string('currency');
            $table->unsignedBigInteger('total_minor');
            $table->string('status')->default('OPEN');
            $table->timestamp('due_date')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->foreignUlid('offering_id')->nullable()->constrained('course_offerings')->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('users');
            $table->foreignUlid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('currency');
            $table->unsignedBigInteger('amount_minor');
            $table->string('method');
            $table->string('status')->default('PENDING');
            $table->string('gateway_ref')->nullable();
            $table->string('proof_url')->nullable();
            $table->foreignUlid('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('verified_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_serial')->nullable()->unique();
            $table->string('receipt_url')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('status');
        });

        Schema::create('wallet_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('egp_money_minor')->default(0);
            $table->unsignedBigInteger('usd_money_minor')->default(0);
            $table->unsignedBigInteger('egp_points_minor')->default(0);
            $table->unsignedBigInteger('usd_points_minor')->default(0);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('wallet_id')->constrained('wallet_accounts')->cascadeOnDelete();
            $table->string('currency');
            $table->string('kind');
            $table->string('direction');
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason');
            $table->foreignUlid('related_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignUlid('related_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'currency', 'kind']);
        });

        Schema::create('donations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency');
            $table->string('kind')->default('MONEY');
            $table->string('designation')->nullable();
            $table->foreignUlid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignUlid('enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->foreignUlid('student_id')->constrained('users');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency');
            $table->boolean('as_points')->default(false);
            $table->string('status')->default('REQUESTED');
            $table->text('reason')->nullable();
            $table->foreignUlid('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('donations');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_accounts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
