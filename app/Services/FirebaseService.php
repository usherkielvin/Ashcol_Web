<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        // Initialize Firebase with service account
        // You'll need to download the service account JSON from Firebase Console
        $serviceAccountPath = storage_path('app/firebase-service-account.json');
        
        if (!file_exists($serviceAccountPath)) {
            Log::warning('Firebase service account file not found. FCM notifications disabled.');
            return;
        }

        try {
            $factory = (new Factory)->withServiceAccount($serviceAccountPath);
            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('Failed to initialize Firebase: ' . $e->getMessage());
        }
    }

    /**
     * Send push notification to a specific device token
     */
    public function sendNotification(string $fcmToken, string $title, string $body, array $data = [])
    {
        if (!$this->messaging) {
            Log::warning('Firebase messaging not initialized');
            return false;
        }

        try {
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            
            Log::info('FCM notification sent successfully', [
                'title' => $title,
                'data' => $data
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send FCM notification: ' . $e->getMessage(), [
                'fcmToken' => substr($fcmToken, 0, 20) . '...',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send notification to multiple device tokens
     */
    public function sendMulticast(array $fcmTokens, string $title, string $body, array $data = [])
    {
        if (!$this->messaging) {
            Log::warning('Firebase messaging not initialized');
            return false;
        }

        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $response = $this->messaging->sendMulticast($message, $fcmTokens);

            Log::info('FCM multicast sent', [
                'success_count' => $response->successes()->count(),
                'failure_count' => $response->failures()->count()
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send FCM multicast: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send ticket assignment notification
     */
    public function notifyTicketAssigned($ticketId, $assignedTo, $assignedBy)
    {
        if (!$assignedTo->fcm_token) {
            return false;
        }

        return $this->sendNotification(
            $assignedTo->fcm_token,
            'New Ticket Assigned',
            "Ticket {$ticketId} has been assigned to you",
            [
                'type' => 'ticket_assigned',
                'ticket_id' => $ticketId,
                'action' => 'refresh_tickets'
            ]
        );
    }

    /**
     * Send ticket status change notification
     */
    public function notifyTicketStatusChanged($ticket, $newStatus)
    {
        $notifications = [];

        // Notify customer
        if ($ticket->user && $ticket->user->fcm_token) {
            $notifications[] = $this->sendNotification(
                $ticket->user->fcm_token,
                'Ticket Status Updated',
                "Your ticket {$ticket->ticket_id} is now {$newStatus}",
                [
                    'type' => 'ticket_status_changed',
                    'ticket_id' => $ticket->ticket_id,
                    'status' => $newStatus,
                    'action' => 'refresh_tickets'
                ]
            );
        }

        // Notify assigned employee
        if ($ticket->assignedStaff && $ticket->assignedStaff->fcm_token) {
            $notifications[] = $this->sendNotification(
                $ticket->assignedStaff->fcm_token,
                'Ticket Status Updated',
                "Ticket {$ticket->ticket_id} is now {$newStatus}",
                [
                    'type' => 'ticket_status_changed',
                    'ticket_id' => $ticket->ticket_id,
                    'status' => $newStatus,
                    'action' => 'refresh_tickets'
                ]
            );
        }

        return in_array(true, $notifications);
    }
}
