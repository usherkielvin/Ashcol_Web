# Email Verification Setup

This document explains how to configure outgoing email so the Ashcol email verification feature works correctly in all environments.

## Configuration

The email verification system is configured to use the existing Ashcol Service Desk email sender (`ashcol.servicedesk@gmail.com`).

### Required .env Settings

Make sure your `.env` file has the following email configuration:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=ashcol.servicedesk@gmail.com
MAIL_PASSWORD=your-app-password-here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=ashcol.servicedesk@gmail.com
MAIL_FROM_NAME="Ashcol Service Desk"
```

After updating `.env`, run:

```bash
php artisan config:clear
```

so Laravel picks up the new mail settings.

> **Note:** Never commit your `.env` file to git. It contains secrets like your Gmail app password.

### Gmail App Password Setup

Since Gmail requires app-specific passwords for SMTP:

1. Go to your Google Account: [https://myaccount.google.com/](https://myaccount.google.com/)
2. Navigate to **Security** → **2-Step Verification** (enable if not already enabled)
3. Go to **App passwords**: [https://myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords)
4. Generate a new app password for "Mail"
5. Copy the 16-character password
6. Paste it in your `.env` file as `MAIL_PASSWORD`

**Important:** Do **not** use your regular Gmail password. You must use an app-specific password.

### Testing

After configuring, test the email setup:

1. Register a new user through the Android app (or any client that hits the registration endpoint)
2. Check the email inbox of the registered email address
3. You should receive an email from `ashcol.servicedesk@gmail.com` with:
   - Subject: "Email Verification Code - Ashcol Service Desk"
   - 6-digit verification code
   - Expiration notice (10 minutes)

### Email Template

The verification email includes:
- Ashcol Service Desk branding
- Clear 6-digit code display
- Expiration notice (10 minutes)
- Professional formatting

### Troubleshooting

If emails are not being sent:

1. **Check .env configuration**: Ensure all `MAIL_*` variables are set correctly and there are no extra spaces or quotes.
2. **Clear config cache**: Run
   ```bash
   php artisan config:clear
   ```
3. **Check logs**: Look at `storage/logs/laravel.log` for any mail-related errors.
4. **Verify Gmail settings**: Ensure the app password is correct, 2FA is enabled, and the Google account is not blocking sign-in attempts.
5. **Test SMTP connection with Tinker**:
   - Start Tinker:
     ```bash
     php artisan tinker
     ```
   - Then run inside Tinker:
     ```php
     Mail::raw('Test email', function ($message) {
         $message->to('your-email@example.com')->subject('Test');
     });
     ```

### Development Mode

For local development without actually sending emails, you can use the `log` mailer instead of SMTP:

```env
MAIL_MAILER=log
```

This will write outgoing emails to `storage/logs/laravel.log` instead of sending them. This is useful when developing or testing without needing a real mailbox.