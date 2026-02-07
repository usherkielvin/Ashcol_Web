<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Technician Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Assigned Tickets</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['total_assigned'] }}</p>
                        </div>
                        <div class="text-indigo-600 dark:text-indigo-400">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Updates</p>
                            <p class="text-3xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['pending'] }}</p>
                        </div>
                        <div class="text-orange-600 dark:text-orange-400">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>


            </div>

            <!-- Pending Updates -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Tickets Needing Attention</h3>
                        <a href="{{ route('tickets.index') }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm">
                            View All →
                        </a>
                    </div>
                    @if($pendingUpdates->count() > 0)
                        <div class="space-y-3">
                            @foreach($pendingUpdates as $ticket)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <a href="{{ route('tickets.show', $ticket) }}" class="text-lg font-semibold text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                                {{ $ticket->title }}
                                            </a>
                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                Customer: {{ $ticket->customer->name }}
                                            </p>
                                            <div class="mt-2 flex gap-2">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                                    style="background-color: {{ $ticket->status->color }}20; color: {{ $ticket->status->color }}">
                                                    {{ $ticket->status->name }}
                                                </span>

                                            </div>
                                        </div>
                                        <div class="ml-4 text-right text-sm text-gray-500 dark:text-gray-400">
                                            {{ $ticket->created_at->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400 text-center py-4">No tickets need attention at this time.</p>
                    @endif
                </div>
            </div>

            <!-- All Assigned Tickets -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">All Assigned Tickets</h3>
                        <a href="{{ route('tickets.index') }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm">
                            View All →
                        </a>
                    </div>
                    @if($assignedTickets->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Title</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Customer</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>

                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Created</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($assignedTickets as $ticket)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3">
                                                <a href="{{ route('tickets.show', $ticket) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                                    {{ $ticket->title }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $ticket->customer->name }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                                    style="background-color: {{ $ticket->status->color }}20; color: {{ $ticket->status->color }}">
                                                    {{ $ticket->status->name }}
                                                </span>
                                            </td>

                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $ticket->created_at->diffForHumans() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400 text-center py-4">No assigned tickets yet.</p>
                    @endif
                </div>
            </div>

            <!-- Unassigned Tickets (Available to Assign) -->
            @if($unassignedTickets->count() > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Unassigned Tickets</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">These tickets are available for assignment.</p>
                        <div class="space-y-3">
                            @foreach($unassignedTickets as $ticket)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <a href="{{ route('tickets.show', $ticket) }}" class="text-lg font-semibold text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                                {{ $ticket->title }}
                                            </a>
                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                Customer: {{ $ticket->customer->name }}
                                            </p>
                                        </div>
                                        <a href="{{ route('tickets.edit', $ticket) }}" class="ml-4 px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded">
                                            Assign to Me
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

