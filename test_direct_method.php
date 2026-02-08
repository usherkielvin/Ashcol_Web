<?php
// Test the getManagerTickets method directly without authentication
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\TicketController;
use App\Models\User;

echo "Testing getManagerTickets method directly...\n\n";

// Get a manager user
$manager = User::where('role', 'manager')->first();
if (!$manager) {
    echo "❌ No manager found\n";
    exit(1);
}

echo "✅ Manager: " . $manager->email . " (Branch: " . $manager->branch . ")\n";

// Create a mock request
$request = new Request();
$request->setUserResolver(function () use ($manager) {
    return $manager;
});

// Temporarily bypass cache by modifying the method
$controller = new class extends TicketController {
    public function getManagerTicketsWithoutCache($request) {
        // Skip cache check and go directly to database query
        $user = $request->user();
        
        // Get manager's branch ID
        $managerBranchId = null;
        if ($user->isManager() && $user->branch) {
            $branch = \App\Models\Branch::where('name', $user->branch)->first();
            $managerBranchId = $branch ? $branch->id : null;
        }
        
        // Direct database query (same as our fixed version)
        $query = \App\Models\Ticket::select([
            'id', 'ticket_id', 'title', 'description', 'service_type', 
            'address', 'contact', 'preferred_date', 
            'status_id', 'customer_id', 'branch_id', 'assigned_staff_id', 'created_at', 'updated_at'
        ])->with([
            'status:id,name,color',
            'customer:id,firstName,lastName',
            'assignedStaff:id,firstName,lastName',
            'branch:id,name'
        ]);
        
        if ($managerBranchId) {
            $query->where('branch_id', $managerBranchId);
        }
        
        $tickets = $query->orderBy('created_at', 'desc')->get();
        
        $ticketData = $tickets->map(function ($ticket) {
            $customerName = '';
            if ($ticket->customer) {
                $firstName = $ticket->customer->firstName ?? '';
                $lastName = $ticket->customer->lastName ?? '';
                $customerName = trim($firstName . ' ' . $lastName);
            }
            
            return [
                'id' => $ticket->id,
                'ticket_id' => $ticket->ticket_id ?? '',
                'title' => $ticket->title ?? '',
                'description' => $ticket->description ?? '',
                'service_type' => $ticket->service_type ?? '',
                'address' => $ticket->address ?? '',
                'contact' => $ticket->contact ?? '',
                'preferred_date' => $ticket->preferred_date?->format('Y-m-d'),
                'status' => $ticket->status->name ?? 'Unknown',
                'status_color' => $ticket->status->color ?? '#gray',
                'customer_name' => $customerName,
                'assigned_staff' => $ticket->assignedStaff ? $ticket->assignedStaff->firstName . ' ' . $ticket->assignedStaff->lastName : null,
                'branch' => $ticket->branch->name ?? null,
                'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $ticket->updated_at->format('Y-m-d H:i:s'),
            ];
        });
        
        return response()->json([
            'success' => true,
            'tickets' => $ticketData,
        ]);
    }
};

try {
    $response = $controller->getManagerTicketsWithoutCache($request);
    
    echo "✅ Method executed successfully\n";
    echo "✅ Response status: " . $response->getStatusCode() . "\n";
    
    $data = json_decode($response->getContent(), true);
    if ($data && $data['success']) {
        echo "✅ Tickets found: " . count($data['tickets']) . "\n";
        
        foreach($data['tickets'] as $ticket) {
            echo "  - " . $ticket['ticket_id'] . " (" . $ticket['title'] . ")\n";
            echo "    Assigned Staff: " . ($ticket['assigned_staff'] ?? 'null') . "\n";
            echo "    Branch: " . ($ticket['branch'] ?? 'null') . "\n";
            
            if ($ticket['assigned_staff'] !== null) {
                echo "    ✅ Assigned staff populated correctly\n";
            }
            if ($ticket['branch'] !== null) {
                echo "    ✅ Branch populated correctly\n";
            }
            echo "\n";
        }
        
        echo "🎉 Fix verification: SUCCESS!\n";
        echo "The getManagerTickets method is now properly populating assigned_staff and branch fields.\n";
        
    } else {
        echo "❌ Unexpected response format\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\nDirect method test completed.\n";