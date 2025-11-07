<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Message</title>
    <style>
        /* Minimal inline-friendly styles; many email clients strip external CSS */
        body { margin:0; padding:0; background:#f5f7fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; }
        .wrapper { width:100%; background:#f5f7fb; padding:24px 0; }
        .container { max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08); }
        .header { background:#39B54A; color:#ffffff; padding:20px 24px; }
        .header h1 { margin:0; font-size:20px; font-weight:700; }
        .content { padding:24px; color:#111827; }
        .row { margin-bottom:12px; }
        .label { color:#6B7280; font-size:12px; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
        .value { font-size:15px; color:#111827; }
        .message { white-space:pre-wrap; line-height:1.6; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:12px; }
        .footer { padding:16px 24px; color:#6B7280; font-size:12px; background:#f9fafb; }
        a { color:#39B54A; text-decoration:none; }
    </style>
    <!--[if mso]><style type="text/css">.value{font-family:Arial, sans-serif !important}</style><![endif]-->
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>New Inquiry Request</h1>
            </div>
            <div class="content">
                <div class="row">
                    <div class="label">Name</div>
                    <div class="value">{{ $name ?? '' }}</div>
                </div>
                <div class="row">
                    <div class="label">Email</div>
                    <div class="value"><a href="mailto:{{ $email ?? '' }}">{{ $email ?? '' }}</a></div>
                </div>
                <div class="row">
                    <div class="label">Phone</div>
                    <div class="value">{{ $phone ?? '' }}</div>
                </div>
                <div class="row">
                    <div class="label">Service</div>
                    <div class="value" style="text-transform:capitalize;">{{ $service ?? '' }}</div>
                </div>
                <div class="row">
                    <div class="label">Message</div>
                    <div class="message">{{ $body ?? '' }}</div>
                </div>
            </div>
            <div class="footer">
                You can reply directly to this email to respond to the customer.
            </div>
        </div>
    </div>
</body>
<!-- Preheader text for better inbox preview -->
<span style="display:none !important; visibility:hidden; opacity:0; height:0; width:0; overflow:hidden; mso-hide:all;">New contact message from {{ $name ?? 'a customer' }}.</span>
</html>


