<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'title',
        'description',
        'customer_id',
        'assigned_staff_id',
        'status_id',
        'branch_id',
        'priority',
        'address',
        'contact',
        'service_type',
        'image_path',
        'scheduled_date',
        'scheduled_time',
        'schedule_notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'scheduled_date' => 'date',
        'scheduled_time' => 'string',
    ];

    /**
     * Priority options
     */
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Get the customer who created the ticket
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the staff member assigned to the ticket
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    /**
     * Get the status of the ticket
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'status_id');
    }

    /**
     * Get all comments for this ticket
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * Get the branch assigned to this ticket
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Generate unique ticket ID
     */
    public static function generateTicketId()
    {
        do {
            $ticketId = 'TCKTId_' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('ticket_id', $ticketId)->exists());

        return $ticketId;
    }

    /**
     * Check if ticket is assigned
     */
    public function isAssigned(): bool
    {
        return !is_null($this->assigned_staff_id);
    }

    /**
     * Get priority badge color
     */
    public function getPriorityColor(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'gray',
            self::PRIORITY_MEDIUM => 'blue',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_URGENT => 'red',
            default => 'gray',
        };
    }
}

