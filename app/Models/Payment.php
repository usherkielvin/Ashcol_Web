<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'ticket_table_id',
        'customer_id',
        'technician_id',
        'manager_id',
        'payment_method',
        'amount',
        'status',
        'notes',
        'collected_at',
        'submitted_at',
        'completed_at',
        'confirmed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'collected_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the ticket this payment belongs to
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_table_id');
    }

    /**
     * Get the customer who made the payment
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the technician who collected the payment
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * Get the manager who received the payment
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
