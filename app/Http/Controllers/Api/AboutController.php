<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AboutController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'title' => 'ASHCOL Service Hub',
                'description' => 'We provide fast, reliable appliance services for homes and businesses. Our technicians deliver safe, professional, and on-time service across branches.',
                'support_email' => 'support@ashcol.com',
                'support_phone' => '+63 900 000 0000',
                'support_hours' => 'Mon - Sat, 8:00 AM - 6:00 PM',
            ],
        ]);
    }
}
