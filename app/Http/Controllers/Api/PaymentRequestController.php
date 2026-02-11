<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentRequestController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Handle payment request from technician
     * POST /api/payment-request
     */
    public function requestPayment(Request $request)
    {
        try {
            // Validate request
            $validator = $this->validatePaymentRequest($request->all());
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $ticketId = $request->input('ticket_id');
            $technicianId = $request->input('technician_id');

            // Find ticket
            $ticket = Ticket::where('ticket_id', $ticketId)->first();
            
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found'
                ], 404);
            }

            // Check technician authorization
            if (!$this->checkTechnicianAuthorization($ticket, $technicianId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Ticket not assigned to this technician'
                ], 403);
            }

            // Check if ticket can have payment requested
            if (!$ticket->canRequestPayment()) {
                $statusName = $ticket->status ? $ticket->status->name : 'Unknown';
                $hasPayment = $ticket->hasPayment() ? 'Yes' : 'No';
                
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket is not eligible for payment request.',
                    'debug' => [
                        'current_status' => $statusName,
                        'has_payment' => $hasPayment,
                        'allowed_statuses' => ['In Progress', 'Completed']
                    ]
                ], 400);
            }

            // Update ticket status to Pending Payment
            $pendingPaymentStatus = TicketStatus::where('name', 'Pending Payment')->first();
            
            if (!$pendingPaymentStatus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pending Payment status not found in system'
                ], 500);
            }

            $ticket->status_id = $pendingPaymentStatus->id;
            $ticket->save();

            // Send FCM notification to customer
            $this->notifyCustomer($ticket);

            return response()->json([
                'success' => true,
                'message' => 'Payment request sent successfully',
                'ticket_status' => 'Pending Payment',
                'ticket_id' => $ticket->ticket_id
            ], 200);

        } catch (\Exception $e) {
            Log::error('Payment request error: ' . $e->getMessage(), [
                'ticket_id' => $request->input('ticket_id'),
                'technician_id' => $request->input('technician_id'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing payment request'
            ], 500);
        }
    }

    /**
     * Validate payment request data
     */
    private function validatePaymentRequest($data)
    {
        return Validator::make($data, [
            'ticket_id' => 'required|string',
            'technician_id' => 'required|integer|exists:users,id'
        ]);
    }

    /**
     * Check if technician is authorized for the ticket
     */
    private function checkTechnicianAuthorization(Ticket $ticket, $technicianId)
    {
        return $ticket->assigned_staff_id == $technicianId;
    }

    /**
     * Send FCM notification to customer
     */
    private function notifyCustomer(Ticket $ticket)
    {
        try {
            $customer = $ticket->customer;
            
            if (!$customer || !$customer->fcm_token) {
                Log::warning('Customer FCM token not found', [
                    'ticket_id' => $ticket->ticket_id,
                    'customer_id' => $ticket->customer_id
                ]);
                return;
            }

            $notificationData = [
                'title' => 'Payment Request',
                'body' => 'Your technician has requested payment for ticket #' . $ticket->ticket_id,
                'data' => [
                    'type' => 'payment_request',
                    'ticket_id' => $ticket->ticket_id,
                    'amount' => $ticket->amount ?? 0,
                    'action' => 'pay_now'
                ]
            ];

            $this->firebaseService->sendNotification(
                $customer->fcm_token,
                $notificationData['title'],
                $notificationData['body'],
                $notificationData['data']
            );

            Log::info('Payment request notification sent', [
                'ticket_id' => $ticket->ticket_id,
                'customer_id' => $customer->id
            ]);

        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::error('Failed to send payment request notification: ' . $e->getMessage(), [
                'ticket_id' => $ticket->ticket_id,
                'customer_id' => $ticket->customer_id
            ]);
        }
    }
}
