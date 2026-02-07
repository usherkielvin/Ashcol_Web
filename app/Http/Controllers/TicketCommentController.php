<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketCommentController extends Controller
{
    /**
     * Store a newly created comment in storage.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        // Check if user can view this ticket
        $user = auth()->user();
        
        if ($user->isCustomer() && $ticket->customer_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'comment' => $request->comment,
        ]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Comment added successfully.');
    }

    /**
     * Remove the specified comment from storage.
     */
    public function destroy(TicketComment $ticketComment): RedirectResponse
    {
        $user = auth()->user();
        $ticket = $ticketComment->ticket;

        // Users can only delete their own comments, or admin/technician can delete any
        if ($ticketComment->user_id !== $user->id && !$user->isAdminOrTechnician()) {
            abort(403, 'Unauthorized action.');
        }

        $ticketComment->delete();

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Comment deleted successfully.');
    }
}

