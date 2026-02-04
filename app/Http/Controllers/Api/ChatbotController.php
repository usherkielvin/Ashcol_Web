<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Handle incoming chatbot messages with keyword matching, AI, and database queries
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        // Validate incoming request
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $message = strtolower(trim($validated['message']));
        $userId = $validated['user_id'] ?? null;
        $originalMessage = $validated['message'];

        // First try keyword-based responses for common queries
        $keywordResponse = $this->generateKeywordResponse($message, $userId);
        
        if ($keywordResponse) {
            return response()->json([
                'reply' => $keywordResponse,
                'timestamp' => now(),
                'method' => 'keyword',
            ]);
        }

        // Fall back to AI for complex queries, or provide helpful fallback
        try {
            $context = $this->buildContext($userId, $originalMessage);
            $aiResponse = $this->aiService->getSupportResponse($originalMessage, $context);

            return response()->json([
                'reply' => $aiResponse,
                'timestamp' => now(),
                'method' => 'ai',
            ]);
        } catch (\Exception $e) {
            \Log::error('Chatbot error: ' . $e->getMessage());
            
            // Provide helpful fallback response
            return response()->json([
                'reply' => "I understand you're asking about: \"" . $originalMessage . "\". For detailed assistance, please contact our support team at support@ashcol.com or visit our help center. You can also ask me about tickets, services, or account help!",
                'timestamp' => now(),
                'method' => 'fallback',
            ]);
        }
    }

    /**
     * Generate response based on keywords (faster, no API calls)
     *
     * @param string $message
     * @param int|null $userId
     * @return string|null
     */
    private function generateKeywordResponse(string $message, ?int $userId): ?string
    {
        // Greeting keywords
        if ($this->matchesKeywords($message, ['hi', 'hello', 'hey', 'greetings', 'good morning', 'good afternoon'])) {
            return "Hello! 👋 Welcome to Ashcol Support. How can I help you today?";
        }

        // Help/Support keywords
        if ($this->matchesKeywords($message, ['help', 'support', 'assist', 'how can you help'])) {
            return "I can help you with:\n• Creating a ticket\n• Checking ticket status\n• General information\n• Contacting our team\n\nWhat would you like to do?";
        }

        // Ticket creation keywords
        if ($this->matchesKeywords($message, ['create', 'new ticket', 'report issue', 'submit issue', 'open ticket'])) {
            return "To create a ticket, please:\n1. Visit our support page\n2. Click 'Create New Ticket'\n3. Fill in the details\n4. Submit\n\nOr contact our support team directly at support@ashcol.com";
        }

        // Check ticket status
        if ($this->matchesKeywords($message, ['ticket status', 'my tickets', 'check ticket', 'ticket update'])) {
            if ($userId) {
                return $this->getTicketStatus($userId);
            }
            return "Please log in to check your ticket status. Once logged in, visit your dashboard to view your tickets.";
        }

        // Services information
        if ($this->matchesKeywords($message, ['services', 'what do you offer', 'what services', 'products'])) {
            return "We offer various services including:\n• Technical Support\n• Consulting\n• System Integration\n• Maintenance & Updates\n\nFor more details, visit our Services page or contact us!";
        }

        // Contact/Email keywords
        if ($this->matchesKeywords($message, ['contact', 'email', 'phone', 'call us', 'reach out'])) {
            return "You can reach us at:\n📧 Email: support@ashcol.com\n📞 Phone: +1-800-ASHCOL-1\n💬 Chat with us here\n\nOur team is available Monday-Friday, 9 AM - 6 PM.";
        }

        // FAQ keywords
        if ($this->matchesKeywords($message, ['faq', 'frequently asked', 'common questions', 'how do i'])) {
            return "Here are some common questions:\n• How do I create a ticket?\n• What's the average response time?\n• How do I reset my password?\n• Can I track my ticket?\n\nAsk me any of these or tell me what you need!";
        }

        // Account/Login keywords
        if ($this->matchesKeywords($message, ['login', 'sign in', 'account', 'password', 'forgot password'])) {
            return "For account issues:\n• Visit the login page\n• Click 'Forgot Password' to reset\n• Create a new account if you don't have one\n\nStill having trouble? Contact support@ashcol.com";
        }

        // Thank you keywords
        if ($this->matchesKeywords($message, ['thanks', 'thank you', 'appreciate', 'thanks for help'])) {
            return "You're welcome! 😊 Is there anything else I can help you with?";
        }

        // Goodbye keywords
        if ($this->matchesKeywords($message, ['bye', 'goodbye', 'see you', 'thanks bye', 'farewell'])) {
            return "Thank you for contacting Ashcol Support! Have a great day! 👋";
        }

        // No keyword match - return null to trigger AI response
        return null;
    }

    /**
     * Check if message matches any of the provided keywords
     *
     * @param string $message
     * @param array $keywords
     * @return bool
     */
    private function matchesKeywords(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (strpos($message, strtolower($keyword)) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get ticket status for a user from the database
     *
     * @param int $userId
     * @return string
     */
    private function getTicketStatus(int $userId): string
    {
        // Get all tickets for the user
        $tickets = Ticket::where('customer_id', $userId)
            ->with(['status', 'assignedStaff'])
            ->latest()
            ->limit(5)
            ->get();

        if ($tickets->isEmpty()) {
            return "You don't have any tickets yet. Would you like to create one?";
        }

        $response = "Here are your recent tickets:\n\n";
        
        foreach ($tickets as $ticket) {
            $status = $ticket->status->name ?? 'Unknown';
            $response .= "📌 Ticket #{$ticket->id}: {$ticket->title}\n";
            $response .= "   Status: {$status}\n";

            $response .= "   Created: " . $ticket->created_at->format('M d, Y') . "\n\n";
        }

        return $response;
    }

    /**
     * Build context for AI responses with user and ticket information
     *
     * @param int|null $userId
     * @param string $message
     * @return string
     */
    private function buildContext(?int $userId, string $message): string
    {
        $context = "";

        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                $context .= "User: {$user->firstName} {$user->lastName}, Role: {$user->role}. ";
                
                // Add ticket count
                $ticketCount = Ticket::where('customer_id', $userId)->count();
                $context .= "They have {$ticketCount} tickets. ";
            }
        }

        // Check if message contains urgent keywords (only if AI service is available)
        try {
            if (method_exists($this->aiService, 'isUrgent')) {
                if ($this->aiService->isUrgent($message)) {
                    $context .= "The message appears to be urgent. Prioritize accordingly. ";
                }
            }
        } catch (\Exception $e) {
            // Ignore errors in urgency detection
        }

        return $context;
    }
}
