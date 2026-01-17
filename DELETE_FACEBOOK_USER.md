# How to Delete/Reset Facebook Login Account

## Method 1: Delete via Laravel Tinker (Recommended)

Open terminal in your Laravel project directory and run:

```bash
php artisan tinker
```

Then in tinker, you can:

### Delete by Facebook ID:
```php
$user = \App\Models\User::where('facebook_id', 'YOUR_FACEBOOK_ID')->first();
if ($user) {
    $user->delete();
    echo "User deleted successfully";
}
```

### Delete by Email:
```php
$user = \App\Models\User::where('email', 'user@example.com')->first();
if ($user) {
    $user->delete();
    echo "User deleted successfully";
}
```

### Delete ALL Facebook users:
```php
\App\Models\User::whereNotNull('facebook_id')->delete();
echo "All Facebook users deleted";
```

### Reset Facebook ID (unlink Facebook):
```php
$user = \App\Models\User::where('facebook_id', 'YOUR_FACEBOOK_ID')->first();
if ($user) {
    $user->facebook_id = null;
    $user->save();
    echo "Facebook ID unlinked";
}
```

## Method 2: Delete via MySQL/phpMyAdmin

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Select your database (e.g., `ashcol_portal`)
3. Go to `users` table
4. Find the user by Facebook ID or email
5. Click "Delete" or use SQL:

```sql
-- Delete by Facebook ID
DELETE FROM users WHERE facebook_id = 'YOUR_FACEBOOK_ID';

-- Delete by email
DELETE FROM users WHERE email = 'user@example.com';

-- Reset Facebook ID (unlink without deleting user)
UPDATE users SET facebook_id = NULL WHERE facebook_id = 'YOUR_FACEBOOK_ID';
```

## Method 3: Clear App Data (Android Testing)

On your Android device/emulator:

1. **Settings → Apps → [Your App Name] → Storage → Clear Data**
   - This will clear all local data including tokens
   - User will need to login again

2. **Uninstall and reinstall the app**
   - Removes all app data completely

## Method 4: View All Facebook Users

Check all Facebook users in the database:

### Via Tinker:
```php
$fbUsers = \App\Models\User::whereNotNull('facebook_id')->get();
foreach ($fbUsers as $user) {
    echo "ID: {$user->id}, FB ID: {$user->facebook_id}, Email: {$user->email}, Name: {$user->name}\n";
}
```

### Via SQL:
```sql
SELECT id, username, email, facebook_id, name, role FROM users WHERE facebook_id IS NOT NULL;
```

## Method 5: Create Delete Endpoint (Advanced)

If you want an API endpoint to delete users, add this to `AuthController.php`:

```php
public function deleteAccount(Request $request)
{
    try {
        $user = $request->user();
        $user->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete account: ' . $e->getMessage()
        ], 500);
    }
}
```

Then add route in `routes/api.php`:
```php
Route::middleware('auth:sanctum')->group(function () {
    // ... existing routes
    Route::delete('/user', [AuthController::class, 'deleteAccount']);
});
```

## Method 6: Delete ALL Accounts

### ⚠️ WARNING: This will delete ALL users from the database!

### Via Laravel Tinker:
```bash
php artisan tinker
```

Then:
```php
// Delete ALL users (including admin, manager, etc.)
\App\Models\User::truncate();
echo "All users deleted";

// OR if truncate doesn't work:
\App\Models\User::query()->delete();
echo "All users deleted";
```

### Via SQL (phpMyAdmin):
```sql
-- Delete ALL users
DELETE FROM users;

-- OR reset auto-increment and delete all
TRUNCATE TABLE users;
```

### Delete All Facebook Users Only:
```php
// In tinker:
\App\Models\User::whereNotNull('facebook_id')->delete();
echo "All Facebook users deleted";
```

### Delete All Regular Users (Keep Admin/Manager):
```php
// In tinker:
\App\Models\User::whereNotIn('role', ['admin', 'manager'])->delete();
echo "All regular users deleted";
```

## Quick Commands Summary

### Tinker - Quick Delete:
```bash
php artisan tinker
# Then:
User::where('facebook_id', '123456789')->delete();
```

### SQL - Quick Delete:
```sql
DELETE FROM users WHERE facebook_id = '123456789';
```

### Check if user exists:
```php
$user = User::where('facebook_id', 'YOUR_FACEBOOK_ID')->first();
if ($user) {
    echo "User found: {$user->email}\n";
} else {
    echo "User not found\n";
}
```
