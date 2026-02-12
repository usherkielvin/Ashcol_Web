<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Services\FirestoreService;
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

            $payment = Payment::where('ticket_id', $ticket->ticket_id)
                ->orderByDesc('id')
                ->first();

            // Determine payment status based on payment method
            // Cash: pending (waiting for technician to confirm receipt)
            // Online: collected (auto-confirmed)
            $paymentStatus = ($paymentMethod === 'cash') ? 'pending' : 'collected';
            $collectedAt = ($paymentMethod === 'cash') ? null : now();

            if ($payment && $payment->status === 'pending') {
                $payment->update([
                    'customer_id' => $customerId,
                    'technician_id' => $ticket->assigned_staff_id,
                    'payment_method' => $paymentMethod,
                    'amount' => $amount,
                    'status' => $paymentStatus,
                    'confirmed_at' => now(),
                    'collected_at' => $collectedAt,
                ]);
            } else {
                $payment = $this->createPaymentRecord([
                    'ticket_id' => $ticket->ticket_id,
                    'ticket_table_id' => $ticket->id,
                    'customer_id' => $customerId,
                    'technician_id' => $ticket->assigned_staff_id,
                    'payment_method' => $paymentMethod,
                    'amount' => $amount,
                    'status' => $paymentStatus,
                    'confirmed_at' => now(),
                    'collected_at' => $collectedAt,
                ]);
            }

            // Only update ticket status to Completed if payment method is online
            // For cash, keep ticket in pending payment status until technician confirms
            $ticketCompleted = false;
            if ($paymentMethod !== 'cash') {
                $completedStatus = TicketStatus::where('name', 'Completed')->first();
                
                if (!$completedStatus) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Completed status not found in system'
                    ], 500);
                }

                $ticket->status_id = $completedStatus->id;
                $ticket->save();
                $ticketCompleted = true;
            }

            try {
                $ticket->load(['customer', 'assignedStaff', 'status', 'branch']);
                $payment->load(['technician', 'customer']);
                $firestoreService = new FirestoreService();
                if ($firestoreService->isAvailable()) {
                    // Update ticket in Firestore
                    $firestoreService->database()
                        ->collection('tickets')
                        ->document($ticket->ticket_id)
                        ->set([
                            'id' => $ticket->id,
                            'ticketId' => $ticket->ticket_id,
                            'customerId' => $ticket->customer_id,
                            'customerEmail' => $ticket->customer->email ?? null,
                            'assignedTo' => $ticket->assigned_staff_id,
                            'assigned_staff' => $ticket->assignedStaff
                                ? trim(($ticket->assignedStaff->firstName ?? '') . ' ' . ($ticket->assignedStaff->lastName ?? ''))
                                : null,
                            'assigned_staff_email' => $ticket->assignedStaff->email ?? null,
                            'status' => $ticket->status->name ?? 'Unknown',
                            'statusDetail' => $ticket->status_detail,
                            'statusColor' => $ticket->status->color ?? '#gray',
                            'serviceType' => $ticket->service_type,
                            'amount' => $ticket->amount,
                            'description' => $ticket->description,
                            'scheduledDate' => $ticket->scheduled_date,
                            'scheduledTime' => $ticket->scheduled_time,
                            'branch' => $ticket->branch->name ?? null,
                            'updatedAt' => new \DateTime(),
                        ], ['merge' => true]);

                    // Update payment in Firestore with correct status
                    $firestoreStatus = ($paymentMethod === 'cash') ? 'pending' : 'completed';
                    $firestoreService->database()
                        ->collection('payments')
                        ->document((string) $payment->id)
                        ->set([
                            'paymentId' => $payment->id,
                            'ticketId' => $ticket->ticket_id,
                            'customerEmail' => $ticket->customer->email ?? null,
                            'serviceName' => $ticket->service_type,
                            'technicianName' => $payment->technician
                                ? ($payment->technician->firstName . ' ' . $payment->technician->lastName)
                                : null,
                            'amount' => $payment->amount,
                            'paymentMethod' => $paymentMethod,
                            'status' => $firestoreStatus,
                            'createdAt' => new \DateTime(),
                            'updatedAt' => new \DateTime(),
                        ], ['merge' => true]);
                }
            } catch (\Exception $e) {
                Log::error('Firestore sync failed in payment confirmation: ' . $e->getMessage());
            }

            // Send FCM notification to technician
            $this->notifyTechnician($ticket, $payment, $paymentMethod);

            $responseMessage = ($paymentMethod === 'cash') 
                ? 'Payment method selected. Please pay the technician in cash.'
                : 'Payment confirmed successfully. Your ticket is now completed.';

            return response()->json([
                'success' => true,
                'message' => $responseMessage,
                'payment' => [
                    'id' => $payment->id,
                    'ticket_id' => $payment->ticket_id,
                    'payment_method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'confirmed_at' => $payment->confirmed_at
                ],
                'ticket_status' => $ticketCompleted ? 'Completed' : 'Pending Payment',
                'requires_cash_confirmation' => ($paymentMethod === 'cash')
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
    private function notifyTechnician(Ticket $ticket, Payment $payment, $paymentMethod)
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

            $title = ($paymentMethod === 'cash') 
                ? 'Customer Selected Cash Payment'
                : 'Payment Confirmed';
            
            $body = ($paymentMethod === 'cash')
                ? 'Customer will pay in cash for ticket #' . $ticket->ticket_id
                : 'Customer has completed online payment for ticket #' . $ticket->ticket_id;

            $notificationData = [
                'title' => $title,
                'body' => $body,
                'data' => [
                    'type' => 'payment_confirmed',
                    'ticket_id' => $ticket->ticket_id,
                    'payment_method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'requires_confirmation' => ($paymentMethod === 'cash')
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
                'technician_id' => $technician->id,
                'payment_method' => $paymentMethod
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
