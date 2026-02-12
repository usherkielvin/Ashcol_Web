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
        // Extract user_id from authenticated user
        $userId = auth()->id();
        
        // Validate incoming request
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $message = strtolower(trim($validated['message']));
        $originalMessage = $validated['message'];

        // First try keyword-based responses for common queries
        $keywordResponse = $this->generateKeywordResponse($message, $userId);
        
        if ($keywordResponse) {
            $filteredResponse = $this->filterConfidentialData($keywordResponse);
            return response()->json([
                'reply' => $filteredResponse,
                'timestamp' => now(),
                'method' => 'keyword',
            ]);
        }

        // Fall back to AI for complex queries, or provide helpful fallback
        try {
            $context = $this->buildContext($userId, $originalMessage);
            $aiResponse = $this->aiService->getSupportResponse($originalMessage, $context);
            $filteredAiResponse = $this->filterConfidentialData($aiResponse);

            return response()->json([
                'reply' => $filteredAiResponse,
                'timestamp' => now(),
                'method' => 'ai',
            ]);
        } catch (\Exception $e) {
            \Log::error('Chatbot error: ' . $e->getMessage());
            
            // Provide helpful fallback response
            $fallbackResponse = "I understand you're asking about: \"" . $originalMessage . "\". For detailed assistance, please contact our support team at support@ashcol.com or visit our help center. You can also ask me about tickets, services, or account help!";
            $filteredFallback = $this->filterConfidentialData($fallbackResponse);
            
            return response()->json([
                'reply' => $filteredFallback,
                'timestamp' => now(),
                'method' => 'fallback',
            ]);
        }
    }

    /**
     * Filter out confidential data patterns from response text
     *
     * @param string $text
     * @return string
     */
    private function filterConfidentialData(string $text): string
    {
        // Pattern for credit card numbers (13-19 digits, with optional spaces or dashes)
        $creditCardPattern = '/\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{3,4}\b/';
        if (preg_match($creditCardPattern, $text)) {
            \Log::warning('Confidential data detected: Credit card number pattern found in chatbot response');
            $text = preg_replace($creditCardPattern, '[REDACTED]', $text);
        }

        // Pattern for potential passwords (common password-like strings)
        $passwordPattern = '/\b(password|pwd|pass)[\s:=]+[\w!@#$%^&*()]+/i';
        if (preg_match($passwordPattern, $text)) {
            \Log::warning('Confidential data detected: Password pattern found in chatbot response');
            $text = preg_replace($passwordPattern, '[REDACTED]', $text);
        }

        // Pattern for API keys (common formats like "api_key: xxxxx" or "apiKey=xxxxx")
        $apiKeyPattern = '/\b(api[_\-]?key|apikey|api[_\-]?secret)[\s:=]+[\w\-]+/i';
        if (preg_match($apiKeyPattern, $text)) {
            \Log::warning('Confidential data detected: API key pattern found in chatbot response');
            $text = preg_replace($apiKeyPattern, '[REDACTED]', $text);
        }

        return $text;
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
        // Get user name for personalization
        $userName = 'there';
        if ($userId) {
            $user = User::find($userId);
            $userName = $user ? ($user->firstName ?? 'there') : 'there';
        }
        
        // Greeting keywords
        if ($this->matchesKeywords($message, ['hi', 'hello', 'hey', 'greetings', 'good morning', 'good afternoon'])) {
            return "Hello {$userName}! 👋 Welcome to Ashcol Support. How can I help you today?";
        }

        // Help/Support keywords
        if ($this->matchesKeywords($message, ['help', 'support', 'assist', 'how can you help'])) {
            return "Hi {$userName}! I can help you with:\n• 📋 Checking your ticket status\n• 🎫 Creating a new service request\n• 💰 Payment information\n• 📞 Contacting our team\n• ℹ️ General information\n\nWhat would you like to do?";
        }

        // Ticket creation keywords
        if ($this->matchesKeywords($message, ['create', 'new ticket', 'report issue', 'submit issue', 'open ticket', 'book service', 'request service'])) {
            return "To book a service, {$userName}:\n1. Go to the Home tab\n2. Tap 'Book a Service'\n3. Select your service type\n4. Fill in the details\n5. Submit your request\n\nOr contact our support team at support@ashcol.com for assistance!";
        }

        // Check ticket status
        if ($this->matchesKeywords($message, ['ticket status', 'my tickets', 'check ticket', 'ticket update', 'show tickets', 'view tickets'])) {
            if ($userId) {
                // Check if message contains a ticket ID pattern (e.g., "ticket #123" or "ticket 123")
                if (preg_match('/ticket\s*#?(\w+)/i', $message, $matches)) {
                    $ticketId = $matches[1];
                    return $this->getSpecificTicketStatus($userId, $ticketId);
                }
                return $this->getTicketStatus($userId);
            }
            return "Please log in to check your ticket status, {$userName}. Once logged in, I can show you all your tickets!";
        }

        // Account information
        if ($this->matchesKeywords($message, ['my account', 'account info', 'profile', 'my details'])) {
            if ($userId) {
                $user = User::find($userId);
                if ($user) {
                    $response = "Hi {$userName}! Here's your account info:\n\n";
                    $response .= "📧 Email: {$user->email}\n";
                    $response .= "👤 Username: {$user->username}\n";
                    if ($user->branch) {
                        $response .= "🏢 Branch: {$user->branch}\n";
                    }
                    $response .= "\nNeed to update your info? Go to Profile > Personal Info!";
                    return $response;
                }
            }
            return "Please log in to view your account information!";
        }

        // Services information
        if ($this->matchesKeywords($message, ['services', 'what do you offer', 'what services', 'products', 'service types'])) {
            return "We offer various AC services, {$userName}:\n• 🧹 Cleaning Services\n• 🔧 Maintenance & Repair\n• ❄️ Installation\n• 🔍 Inspection\n• 🆘 Emergency Services\n\nTap 'Book Service' on the Home tab to get started!";
        }

        // Payment information
        if ($this->matchesKeywords($message, ['payment', 'pay', 'how to pay', 'payment methods', 'cost', 'price'])) {
            return "Payment information, {$userName}:\n\n💳 We accept:\n• Cash\n• GCash\n• PayMaya\n• Credit/Debit Cards\n\nYou can pay after service completion. Our technician will request payment when the work is done!";
        }

        // Contact/Email keywords
        if ($this->matchesKeywords($message, ['contact', 'email', 'phone', 'call us', 'reach out', 'support team'])) {
            return "You can reach us at:\n📧 Email: support@ashcol.com\n📞 Phone: +63-XXX-XXXX\n💬 Chat with me here anytime!\n\nOur team is available Monday-Saturday, 8 AM - 6 PM.";
        }

        // FAQ keywords
        if ($this->matchesKeywords($message, ['faq', 'frequently asked', 'common questions', 'how do i'])) {
            return "Here are some common questions, {$userName}:\n• How do I book a service?\n• How do I check my ticket status?\n• What payment methods do you accept?\n• How long does service take?\n• Can I reschedule my appointment?\n\nAsk me any of these or tell me what you need!";
        }

        // Account/Login keywords
        if ($this->matchesKeywords($message, ['login', 'sign in', 'account', 'password', 'forgot password', 'reset password'])) {
            return "For account issues:\n• Tap 'Forgot Password' on the login screen to reset\n• Create a new account if you don't have one\n• Make sure you're using the correct email\n\nStill having trouble? Contact support@ashcol.com";
        }

        // Thank you keywords
        if ($this->matchesKeywords($message, ['thanks', 'thank you', 'appreciate', 'thanks for help'])) {
            return "You're very welcome, {$userName}! 😊 Is there anything else I can help you with?";
        }

        // Goodbye keywords
        if ($this->matchesKeywords($message, ['bye', 'goodbye', 'see you', 'thanks bye', 'farewell'])) {
            return "Thank you for contacting Ashcol Support, {$userName}! Have a great day! 👋";
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
        // Get user information
        $user = User::find($userId);
        $userName = $user ? ($user->firstName ?? 'there') : 'there';
        
        // Get up to 5 most recent tickets for the user, ordered by most recent first
        $tickets = Ticket::where('customer_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($tickets->isEmpty()) {
            return "Hi {$userName}! 👋 You don't have any tickets yet. Would you like to create one? I can guide you through the process!";
        }

        // Count tickets by status
        $pendingCount = Ticket::where('customer_id', $userId)->where('status', 'pending')->count();
        $ongoingCount = Ticket::where('customer_id', $userId)->whereIn('status', ['ongoing', 'in progress', 'assigned'])->count();
        $completedCount = Ticket::where('customer_id', $userId)->whereIn('status', ['completed', 'resolved', 'closed'])->count();

        $response = "Hi {$userName}! Here's your ticket summary:\n\n";
        $response .= "📊 Overview:\n";
        $response .= "   • Pending: {$pendingCount}\n";
        $response .= "   • In Progress: {$ongoingCount}\n";
        $response .= "   • Completed: {$completedCount}\n\n";
        $response .= "📋 Recent Tickets:\n\n";
        
        foreach ($tickets as $ticket) {
            $status = ucfirst($ticket->status ?? 'Unknown');
            $ticketTitle = $ticket->service_type ?? 'Service Request';
            $statusEmoji = $this->getStatusEmoji($ticket->status);
            
            $response .= "{$statusEmoji} Ticket #{$ticket->ticket_id}\n";
            $response .= "   Service: {$ticketTitle}\n";
            $response .= "   Status: {$status}\n";
            $response .= "   Date: " . $ticket->created_at->format('M d, Y') . "\n\n";
        }

        if ($tickets->count() >= 5) {
            $response .= "Showing your 5 most recent tickets. Need help with any of them?";
        } else {
            $response .= "Need help with any of these tickets? Just ask!";
        }

        return $response;
    }

    /**
     * Get emoji for ticket status
     *
     * @param string|null $status
     * @return string
     */
    private function getStatusEmoji(?string $status): string
    {
        if (!$status) return '📌';
        
        $status = strtolower($status);
        
        if (in_array($status, ['completed', 'resolved', 'closed'])) {
            return '✅';
        } elseif (in_array($status, ['ongoing', 'in progress', 'assigned'])) {
            return '🔄';
        } elseif ($status === 'pending') {
            return '⏳';
        } elseif (in_array($status, ['cancelled', 'rejected'])) {
            return '❌';
        }
        
        return '📌';
    }

    /**
     * Get status for a specific ticket by ID
     *
     * @param int $userId
     * @param int $ticketId
     * @return string
     */
    private function getSpecificTicketStatus(int $userId, int $ticketId): string
    {
        // Get user information
        $user = User::find($userId);
        $userName = $user ? ($user->firstName ?? 'there') : 'there';
        
        // Find the ticket and verify it belongs to the user
        $ticket = Ticket::where('ticket_id', $ticketId)
            ->where('customer_id', $userId)
            ->first();

        if (!$ticket) {
            return "Hi {$userName}, I couldn't find ticket #{$ticketId} in your account. Please check the ticket ID and try again, or ask me to show all your tickets!";
        }

        $status = ucfirst($ticket->status ?? 'Unknown');
        $ticketTitle = $ticket->service_type ?? 'Service Request';
        $statusEmoji = $this->getStatusEmoji($ticket->status);
        
        $response = "Hi {$userName}! Here's the status of your ticket:\n\n";
        $response .= "{$statusEmoji} Ticket #{$ticket->ticket_id}\n";
        $response .= "   Service: {$ticketTitle}\n";
        $response .= "   Status: {$status}\n";
        $response .= "   Created: " . $ticket->created_at->format('M d, Y h:i A') . "\n";
        
        if ($ticket->assigned_to) {
            $technician = User::find($ticket->assigned_to);
            if ($technician) {
                $response .= "   Assigned to: {$technician->firstName} {$technician->lastName}\n";
            }
        }
        
        if ($ticket->scheduled_date) {
            $response .= "   Scheduled: " . \Carbon\Carbon::parse($ticket->scheduled_date)->format('M d, Y') . "\n";
        }
        
        if ($ticket->amount) {
            $response .= "   Amount: ₱" . number_format($ticket->amount, 2) . "\n";
        }

        // Add status-specific messages
        if (in_array(strtolower($ticket->status), ['completed', 'resolved', 'closed'])) {
            $response .= "\n✨ This ticket has been completed! Thank you for choosing Ashcol!";
        } elseif (in_array(strtolower($ticket->status), ['ongoing', 'in progress'])) {
            $response .= "\n🔧 Our technician is working on your request. Hang tight!";
        } elseif (strtolower($ticket->status) === 'pending') {
            $response .= "\n⏳ Your ticket is pending. We'll assign a technician soon!";
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
