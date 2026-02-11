<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentConfirmationController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Handle payment confirmation from customer
     * POST /api/payment-confirm
     */
    public function confirmPayment(Request $request)
    {
        try {
            // Validate request
            $validator = $this->validatePaymentConfirmation($request->all());
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $ticketId = $request->input('ticket_id');
            $customerId = $request->input('customer_id');
            $paymentMethod = $request->input('payment_method');
            $amount = $request->input('amount');

            // Find ticket
            $ticket = Ticket::where('ticket_id', $ticketId)->first();
            
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found'
                ], 404);
            }

            // Check customer authorization
            if (!$this->checkCustomerAuthorization($ticket, $customerId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Ticket does not belong to this customer'
                ], 403);
            }

            // Check if ticket is in pending payment status
            if (!$ticket->isPendingPayment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket is not in pending payment status'
                ], 400);
            }

            // Create Payment record
            $payment = $this->createPaymentRecord([
                'ticket_id' => $ticket->ticket_id,
                'ticket_table_id' => $ticket->id,
                'customer_id' => $customerId,
                'technician_id' => $ticket->assigned_staff_id,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'status' => 'collected',
                'confirmed_at' => now(),
                'collected_at' => now(),
            ]);

            // Update ticket status to Paid
            $paidStatus = TicketStatus::where('name', 'Paid')->first();
            
            if (!$paidStatus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paid status not found in system'
                ], 500);
            }

            $ticket->status_id = $paidStatus->id;
            $ticket->save();

            // Send FCM notification to technician
            $this->notifyTechnician($ticket, $payment);

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'payment' => [
                    'id' => $payment->id,
                    'ticket_id' => $payment->ticket_id,
                    'payment_method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'confirmed_at' => $payment->confirmed_at
                ],
                'ticket_status' => 'Paid'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Payment confirmation error: ' . $e->getMessage(), [
                'ticket_id' => $request->input('ticket_id'),
                'customer_id' => $request->input('customer_id'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing payment confirmation'
            ], 500);
        }
    }

    /**
     * Validate payment confirmation data
     */
    private function validatePaymentConfirmation($data)
    {
        return Validator::make($data, [
            'ticket_id' => 'required|string',
            'customer_id' => 'required|integer|exists:users,id',
            'payment_method' => 'required|string|in:cash,credit_card,gcash,bank_transfer,online',
            'amount' => 'required|numeric|min:0'
        ]);
    }

    /**
     * Check if customer is authorized for the ticket
     */
    private function checkCustomerAuthorization(Ticket $ticket, $customerId)
    {
        return $ticket->customer_id == $customerId;
    }

    /**
     * Create Payment record in database
     */
    private function createPaymentRecord($data)
    {
        return Payment::create($data);
    }

    /**
     * Send FCM notification to technician
     */
    private function notifyTechnician(Ticket $ticket, Payment $payment)
    {
        try {
            $technician = $ticket->assignedStaff;
            
            if (!$technician || !$technician->fcm_token) {
                Log::warning('Technician FCM token not found', [
                    'ticket_id' => $ticket->ticket_id,
                    'technician_id' => $ticket->assigned_staff_id
                ]);
                return;
            }

            $notificationData = [
                'title' => 'Payment Confirmed',
                'body' => 'Customer has confirmed payment for ticket #' . $ticket->ticket_id,
                'data' => [
                    'type' => 'payment_confirmed',
                    'ticket_id' => $ticket->ticket_id,
                    'payment_method' => $payment->payment_method,
                    'amount' => $payment->amount
                ]
            ];

            $this->firebaseService->sendNotification(
                $technician->fcm_token,
                $notificationData['title'],
                $notificationData['body'],
                $notificationData['data']
            );

            Log::info('Payment confirmation notification sent', [
                'ticket_id' => $ticket->ticket_id,
                'technician_id' => $technician->id
            ]);

        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::error('Failed to send payment confirmation notification: ' . $e->getMessage(), [
                'ticket_id' => $ticket->ticket_id,
                'technician_id' => $ticket->assigned_staff_id
            ]);
        }
    }
}
