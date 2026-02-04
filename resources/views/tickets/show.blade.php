<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Ticket Details') }}
            </h2>
            @if(auth()->user()->isAdminOrStaff())
                <a href="{{ route('tickets.edit', $ticket) }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    {{ __('Edit Ticket') }}
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Ticket Details -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1">
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                                {{ $ticket->title }}
                            </h3>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                                    style="background-color: {{ $ticket->status->color }}20; color: {{ $ticket->status->color }}">
                                    {{ $ticket->status->name }}
                                </span>

                            </div>
                        </div>
                        <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                            <p>Created {{ $ticket->created_at->diffForHumans() }}</p>
                            <p>Updated {{ $ticket->updated_at->diffForHumans() }}</p>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mb-4">
                        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $ticket->description }}</p>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Customer:</p>
                            <p class="text-gray-900 dark:text-gray-100 font-medium">{{ $ticket->customer->name }}</p>
                            <p class="text-gray-500 dark:text-gray-400 text-xs">{{ $ticket->customer->email }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Assigned Staff:</p>
                            @if($ticket->assignedStaff)
                                <p class="text-gray-900 dark:text-gray-100 font-medium">{{ $ticket->assignedStaff->name }}</p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs">{{ $ticket->assignedStaff->email }}</p>
                            @else
                                <p class="text-gray-500 dark:text-gray-400 italic">Unassigned</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Comments ({{ $ticket->comments->count() }})
                    </h3>

                    <!-- Add Comment Form -->
                    <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" class="mb-6">
                        @csrf
                        <div>
                            <x-input-label for="comment" value="{{ __('Add Comment') }}" />
                            <textarea id="comment" name="comment" rows="3" 
                                class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                required placeholder="{{ __('Type your comment here...') }}">{{ old('comment') }}</textarea>
                            <x-input-error :messages="$errors->get('comment')" class="mt-2" />
                        </div>
                        <div class="mt-2">
                            <x-primary-button>
                                {{ __('Post Comment') }}
                            </x-primary-button>
                        </div>
                    </form>

                    <!-- Comments List -->
                    <div class="space-y-4">
                        @forelse($ticket->comments as $comment)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <p class="font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $comment->user->name }}
                                            </p>
                                            <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $comment->created_at->diffForHumans() }}
                                            </span>
                                        </div>
                                        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $comment->comment }}</p>
                                    </div>
                                    @if($comment->user_id === auth()->id() || auth()->user()->isAdminOrStaff())
                                        <form method="POST" action="{{ route('ticket-comments.destroy', $comment) }}" class="ml-4">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm"
                                                onclick="return confirm('Are you sure you want to delete this comment?')">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 dark:text-gray-400 text-center py-4">
                                {{ __('No comments yet. Be the first to comment!') }}
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Back Button -->
            <div class="text-center">
                <a href="{{ route('tickets.index') }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                    ← {{ __('Back to Tickets') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>

