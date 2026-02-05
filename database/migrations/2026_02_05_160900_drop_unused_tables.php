<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration drops unused tables to clean up the database:
     * - facebook_accounts: Facebook login not implemented
     * - jobs, job_batches, failed_jobs: Queue system not used
     * - contact_messages: Not used in current app flows
     * - cache, cache_locks: Using file cache instead
     */
    public function up(): void
    {
        // Drop Facebook-related tables
        Schema::dropIfExists('facebook_accounts');
        
        // Drop queue-related tables (not using queues)
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        
        // Drop contact messages (not used)
        Schema::dropIfExists('contact_messages');
        
        // Drop cache tables (using file cache)
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This migration is destructive. Reversing will create empty tables.
     * Data cannot be recovered after running this migration.
     */
    public function down(): void
    {
        // Recreate cache tables
        Schema::create('cache', function ($table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function ($table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        // Recreate jobs tables
        Schema::create('jobs', function ($table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function ($table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function ($table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // Recreate contact_messages
        Schema::create('contact_messages', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->text('message');
            $table->timestamps();
        });

        // Recreate facebook_accounts
        Schema::create('facebook_accounts', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('facebook_id')->unique();
            $table->text('access_token')->nullable();
            $table->timestamp('linked_at')->useCurrent();
            $table->timestamps();
        });
    }
};
