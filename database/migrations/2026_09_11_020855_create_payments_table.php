<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);

            $table->string('currency', 3)
                ->default('USD');

            $table->string('method')
                ->default('KHQR');

            $table->string('status')
                ->default('pending');

            // KHQR
            $table->text('qr_code')
                ->nullable();

            $table->text('qr_code_url')
                ->nullable();

            // Bakong verification
            $table->string('md5', 32)
                ->nullable()
                ->index();

            $table->string('bill_number')
                ->nullable()
                ->index();

            $table->string('merchant_name')
                ->nullable();

            // QR expiration
            $table->timestamp('expired_at')
                ->nullable();

            // Bakong transaction information
            $table->string('transaction_hash')
                ->nullable()
                ->index();

            $table->string('from_account_id')
                ->nullable();

            $table->string('to_account_id')
                ->nullable();

            $table->string('external_ref')
                ->nullable();

            // Payment completed time
            $table->timestamp('paid_at')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
