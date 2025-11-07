<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Mail\ContactSubmitted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255'],
            'phone' => ['required','string','max:50'],
            'service' => ['required','string','max:50'],
            'message' => ['required','string','max:5000'],
        ]);

        ContactMessage::create($data);

        // Destination for contact notifications
        $to = env('CONTACT_TO', config('mail.from.address'));
        try {
            Mail::to($to)->send(new ContactSubmitted($data));
        } catch (\Throwable $e) {
            Log::error('Mail send failed: '.$e->getMessage());
        }

        return back()->with('status', 'Message sent successfully.');
    }
}


