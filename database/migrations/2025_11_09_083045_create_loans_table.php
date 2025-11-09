<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creditor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('balance', 12, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->decimal('minimum_payment', 12, 2);
            $table->date('start_date')->nullable();
            $table->integer('term_months')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
