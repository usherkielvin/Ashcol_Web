<?php

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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id'); // Reference to ticket_id (not foreign key to avoid issues)
            $table->unsignedBigInteger('ticket_table_id')->nullable(); // Reference to tickets.id
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('technician_id'); // Employee who received payment
            $table->unsignedBigInteger('manager_id')->nullable(); // Manager who received payment from technician
            $table->enum('payment_method', ['cash', 'online'])->default('cash');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'collected', 'submitted', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('collected_at')->nullable(); // When technician collected from customer
            $table->timestamp('submitted_at')->nullable(); // When technician gave to manager
            $table->timestamp('completed_at')->nullable(); // When manager confirmed receipt
            $table->timestamps();
            
            // Indexes for performance
            $table->index('ticket_id');
            $table->index('technician_id');
            $table->index('manager_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
