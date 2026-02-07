<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],


        ];

        // Admin and technician can assign customer and technician
        if ($user->isAdminOrTechnician()) {
            $rules['customer_id'] = ['nullable', 'exists:users,id'];
            $rules['assigned_staff_id'] = ['nullable', 'exists:users,id'];
            $rules['status_id'] = ['nullable', 'exists:ticket_statuses,id'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'The ticket title is required.',
            'description.required' => 'The ticket description is required.',
            'priority.required' => 'Please select a priority level.',
        ];
    }
}

