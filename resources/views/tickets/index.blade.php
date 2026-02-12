<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Tickets') }}
            </h2>
            <a href="{{ route('tickets.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Create Ticket') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6 p-6">
                <form method="GET" action="{{ route('tickets.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <x-input-label for="search" value="{{ __('Search') }}" />
                        <x-text-input id="search" class="block mt-1 w-full" type="text" name="search" 
                            value="{{ request('search') }}" placeholder="{{ __('Title or description...') }}" />
                    </div>
                    
                    <div>
                        <x-input-label for="status_id" value="{{ __('Status') }}" />
                        <select id="status_id" name="status_id" 
                            class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                            <option value="">All Statuses</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->id }}" {{ request('status_id') == $status->id ? 'selected' : '' }}>
                                    {{ $status->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    

                    
                    <div class="flex items-end">
                        <x-primary-button class="w-full">
                            {{ __('Filter') }}
                        </x-primary-button>
                        <a href="{{ route('tickets.index') }}" class="ml-2 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded">
                            {{ __('Clear') }}
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tickets List -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" id="tickets-container">
                @if($tickets->count() > 0)
                    <div class="p-6">
                        <div class="space-y-4" id="tickets-list">
                            @foreach($tickets as $ticket)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition" data-ticket-id="{{ $ticket->id }}">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <a href="{{ route('tickets.show', $ticket) }}" class="text-lg font-semibold text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                                {{ $ticket->title }}
                                            </a>
                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                                {{ \Illuminate\Support\Str::limit($ticket->description, 150) }}
                                            </p>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ticket-status" 
                                                    style="background-color: {{ $ticket->status->color }}20; color: {{ $ticket->status->color }}">
                                                    {{ $ticket->status->name }}
                                                </span>

                                                @if($ticket->assignedStaff)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 ticket-assigned">
                                                        Assigned to {{ $ticket->assignedStaff->name }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 ticket-assigned">
                                                        Unassigned
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="ml-4 text-right">
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $ticket->created_at->diffForHumans() }}
                                            </p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                                by {{ $ticket->customer->name }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Pagination -->
                        <div class="mt-6">
                            {{ $tickets->links() }}
                        </div>
                    </div>
                @else
                    <div class="p-6 text-center">
                        <p class="text-gray-500 dark:text-gray-400">{{ __('No tickets found.') }}</p>
                        <a href="{{ route('tickets.create') }}" class="mt-4 inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                            {{ __('Create Your First Ticket') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Auto-refresh ticket list every 10 seconds for customers
        let refreshInterval;

        function refreshTicketList() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('ajax', '1');
            
            fetch(currentUrl.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTicketsList = doc.querySelector('#tickets-list');
                const currentTicketsList = document.querySelector('#tickets-list');
                
                if (newTicketsList && currentTicketsList) {
                    // Check if content actually changed
                    if (newTicketsList.innerHTML !== currentTicketsList.innerHTML) {
                        console.log('Ticket list updated, refreshing...');
                        currentTicketsList.innerHTML = newTicketsList.innerHTML;
                        showUpdateNotification();
                    }
                }
            })
            .catch(error => {
                console.error('Error refreshing ticket list:', error);
            });
        }

        function showUpdateNotification() {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 transition-opacity duration-300';
            notification.textContent = 'Tickets updated';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 2000);
        }

        // Start auto-refresh for customers only
        @if(auth()->user()->isCustomer())
            refreshInterval = setInterval(refreshTicketList, 10000);
            console.log('Auto-refresh enabled for customer ticket list');
        @endif

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
    @endpush
</x-app-layout>
