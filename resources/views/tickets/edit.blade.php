<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Ticket') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('tickets.update', $ticket) }}">
                        @csrf
                        @method('PATCH')

                        <!-- Title -->
                        <div class="mb-4">
                            <x-input-label for="title" value="{{ __('Title') }}" />
                            <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" 
                                value="{{ old('title', $ticket->title) }}" required autofocus />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <x-input-label for="description" value="{{ __('Description') }}" />
                            <textarea id="description" name="description" rows="6" 
                                class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                required>{{ old('description', $ticket->description) }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <!-- Status -->
                        <div class="mb-4">
                            <x-input-label for="status_id" value="{{ __('Status') }}" />
                            <select id="status_id" name="status_id" 
                                class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" 
                                required>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->id }}" {{ old('status_id', $ticket->status_id) == $status->id ? 'selected' : '' }}>
                                        {{ $status->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status_id')" class="mt-2" />
                        </div>



                        <!-- Staff Assignment -->
                        <div class="mb-4">
                            <x-input-label for="assigned_staff_id" value="{{ __('Assign to Staff') }}" />
                            <select id="assigned_staff_id" name="assigned_staff_id" 
                                class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="">{{ __('Unassigned') }}</option>
                                @foreach($staff as $staffMember)
                                    <option value="{{ $staffMember->id }}" {{ old('assigned_staff_id', $ticket->assigned_staff_id) == $staffMember->id ? 'selected' : '' }}>
                                        {{ $staffMember->name }} ({{ $staffMember->email }})
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('assigned_staff_id')" class="mt-2" />
                        </div>

                        <!-- Ticket Info (Read-only) -->
                        <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                <strong>Customer:</strong> {{ $ticket->customer->name }} ({{ $ticket->customer->email }})
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                <strong>Created:</strong> {{ $ticket->created_at->format('M d, Y h:i A') }}
                            </p>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <a href="{{ route('tickets.show', $ticket) }}" class="mr-4 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                {{ __('Cancel') }}
                            </a>
                            <x-primary-button>
                                {{ __('Update Ticket') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

