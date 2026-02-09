<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        Schema::create('ticket_statuses_new', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('#6B7280');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('tickets_new', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id')->unique();
            $table->string('title')->nullable();
            $table->text('description');
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('status_id')->constrained('ticket_statuses_new')->onDelete('restrict');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->string('address')->nullable();
            $table->string('contact')->nullable();
            $table->string('service_type')->nullable();
            $table->string('unit_type')->nullable();
            $table->date('preferred_date')->nullable();
            $table->string('image_path')->nullable();
            $table->string('attachment_url')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->text('schedule_notes')->nullable();
            $table->timestamps();

            $table->index('status_id');
            $table->index('assigned_staff_id');
            $table->index('branch_id');
            $table->index('scheduled_date');
        });

        Schema::create('ticket_comments_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets_new')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('comment');
            $table->timestamps();
        });

        Schema::create('payments_new', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id');
            $table->unsignedBigInteger('ticket_table_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('technician_id');
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->enum('payment_method', ['cash', 'online'])->default('cash');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'collected', 'submitted', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('ticket_id');
            $table->index('technician_id');
            $table->index('manager_id');
            $table->index('status');
            $table->index('created_at');
        });

        $now = now();
        $statuses = [
            ['name' => 'Pending', 'color' => '#F59E0B', 'is_default' => true],
            ['name' => 'Scheduled', 'color' => '#6366F1', 'is_default' => false],
            ['name' => 'Ongoing', 'color' => '#3B82F6', 'is_default' => false],
            ['name' => 'Completed', 'color' => '#10B981', 'is_default' => false],
            ['name' => 'Cancelled', 'color' => '#EF4444', 'is_default' => false],
        ];

        foreach ($statuses as $status) {
            DB::table('ticket_statuses_new')->insert([
                'name' => $status['name'],
                'color' => $status['color'],
                'is_default' => $status['is_default'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $oldStatusById = DB::table('ticket_statuses')->pluck('name', 'id');
        $newStatusByName = DB::table('ticket_statuses_new')->pluck('id', 'name');

        $mapStatus = function (?string $oldName) {
            $name = strtolower(trim((string) $oldName));
            if ($name === 'scheduled') {
                return 'Scheduled';
            }
            if (in_array($name, ['ongoing', 'in progress', 'accepted'], true)) {
                return 'Ongoing';
            }
            if (in_array($name, ['completed', 'resolved', 'closed'], true)) {
                return 'Completed';
            }
            if (in_array($name, ['cancelled', 'canceled', 'rejected', 'failed'], true)) {
                return 'Cancelled';
            }
            return 'Pending';
        };

        DB::table('tickets')->orderBy('id')->chunkById(500, function ($tickets) use ($oldStatusById, $newStatusByName, $mapStatus) {
            $rows = [];
            foreach ($tickets as $ticket) {
                $oldName = $oldStatusById[$ticket->status_id] ?? null;
                $newName = $mapStatus($oldName);
                $rows[] = [
                    'id' => $ticket->id,
                    'ticket_id' => $ticket->ticket_id,
                    'title' => $ticket->title,
                    'description' => $ticket->description,
                    'customer_id' => $ticket->customer_id,
                    'assigned_staff_id' => $ticket->assigned_staff_id,
                    'status_id' => $newStatusByName[$newName] ?? $newStatusByName['Pending'],
                    'branch_id' => $ticket->branch_id,
                    'priority' => $ticket->priority,
                    'address' => $ticket->address,
                    'contact' => $ticket->contact,
                    'service_type' => $ticket->service_type,
                    'unit_type' => $ticket->unit_type,
                    'preferred_date' => $ticket->preferred_date,
                    'image_path' => $ticket->image_path,
                    'attachment_url' => $ticket->attachment_url,
                    'scheduled_date' => $ticket->scheduled_date,
                    'scheduled_time' => $ticket->scheduled_time,
                    'schedule_notes' => $ticket->schedule_notes,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->updated_at,
                ];
            }
            if (!empty($rows)) {
                DB::table('tickets_new')->insert($rows);
            }
        });

        if (Schema::hasTable('ticket_comments')) {
            DB::table('ticket_comments')->orderBy('id')->chunkById(500, function ($comments) {
                $rows = [];
                foreach ($comments as $comment) {
                    $rows[] = [
                        'id' => $comment->id,
                        'ticket_id' => $comment->ticket_id,
                        'user_id' => $comment->user_id,
                        'comment' => $comment->comment,
                        'created_at' => $comment->created_at,
                        'updated_at' => $comment->updated_at,
                    ];
                }
                if (!empty($rows)) {
                    DB::table('ticket_comments_new')->insert($rows);
                }
            });
        }

        if (Schema::hasTable('payments')) {
            DB::table('payments')->orderBy('id')->chunkById(500, function ($payments) {
                $rows = [];
                foreach ($payments as $payment) {
                    $rows[] = [
                        'id' => $payment->id,
                        'ticket_id' => $payment->ticket_id,
                        'ticket_table_id' => $payment->ticket_table_id,
                        'customer_id' => $payment->customer_id,
                        'technician_id' => $payment->technician_id,
                        'manager_id' => $payment->manager_id,
                        'payment_method' => $payment->payment_method,
                        'amount' => $payment->amount,
                        'status' => $payment->status,
                        'notes' => $payment->notes,
                        'collected_at' => $payment->collected_at,
                        'submitted_at' => $payment->submitted_at,
                        'completed_at' => $payment->completed_at,
                        'created_at' => $payment->created_at,
                        'updated_at' => $payment->updated_at,
                    ];
                }
                if (!empty($rows)) {
                    DB::table('payments_new')->insert($rows);
                }
            });
        }

        Schema::rename('ticket_comments', 'ticket_comments_old');
        Schema::rename('payments', 'payments_old');
        Schema::rename('tickets', 'tickets_old');
        Schema::rename('ticket_statuses', 'ticket_statuses_old');

        Schema::rename('ticket_statuses_new', 'ticket_statuses');
        Schema::rename('tickets_new', 'tickets');
        Schema::rename('ticket_comments_new', 'ticket_comments');
        Schema::rename('payments_new', 'payments');

        Schema::dropIfExists('ticket_comments_old');
        Schema::dropIfExists('payments_old');
        Schema::dropIfExists('tickets_old');
        Schema::dropIfExists('ticket_statuses_old');

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank to avoid destructive rollbacks.
    }
};
