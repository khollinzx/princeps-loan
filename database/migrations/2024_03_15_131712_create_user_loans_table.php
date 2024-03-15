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
        Schema::create('user_loans', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->double('income');
            $table->double('loan_amount');
            $table->string('status');
            $table->tinyInteger('is_fully_paid')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete(Constants::SET_NULL);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_loans');
    }
};
