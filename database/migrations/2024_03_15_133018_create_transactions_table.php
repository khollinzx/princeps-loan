<?php

use App\Utils\Constants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('loan_id')->nullable();
            $table->unsignedBigInteger('repayment_loan_id')->nullable();
            $table->string('card_details');
            $table->text('paystack_remark');
            $table->string('status');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete(Constants::SET_NULL);
            $table->foreign('loan_id')->references('id')->on('user_loans')->onDelete(Constants::SET_NULL);
            $table->foreign('repayment_loan_id')->references('id')->on('user_loan_repayments')->onDelete(Constants::SET_NULL);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
