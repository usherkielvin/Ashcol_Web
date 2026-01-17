# Facebook Accounts Table Structure

## Overview

Facebook login accounts are now stored in a separate `facebook_accounts` table that links to the `users` table. This provides a cleaner architecture and better separation of concerns.

## Database Structure

### `users` Table
- Contains all user information (username, email, password, name, etc.)
- **No longer** contains `facebook_id` (removed)
- Email can be nullable (for pure Facebook users without email)

### `facebook_accounts` Table
- `id` - Primary key
- `user_id` - Foreign key to `users` table (cascade delete)
- `facebook_id` - Unique Facebook user ID
- `access_token` - Facebook access token (nullable)
- `linked_at` - Timestamp when account was linked
- `created_at`, `updated_at` - Timestamps

## Relationships

### User Model
```php
// Get Facebook account for a user
$user->facebookAccount

// Check if user has Facebook account
$user->hasFacebookAccount()
```

### FacebookAccount Model
```php
// Get the user that owns this Facebook account
$facebookAccount->user
```

## How It Works

### Registration Flow:
1. User signs in with Facebook → Gets Facebook ID
2. Backend checks `facebook_accounts` table for existing Facebook ID
3. If found → Login existing user
4. If not found → Create new user in `users` table + Create entry in `facebook_accounts` table

### Login Flow:
1. User signs in with Facebook → Gets Facebook ID
2. Backend looks up `facebook_accounts` by `facebook_id`
3. Gets associated `user_id` → Returns user data

## Benefits

1. **Cleaner Architecture**: Facebook data separated from user data
2. **Scalability**: Easy to add more social login providers (Google, Twitter, etc.)
3. **Flexibility**: Users can have multiple social accounts linked
4. **Data Integrity**: Foreign key constraints ensure data consistency

## Query Examples

### Find user by Facebook ID:
```php
$facebookAccount = FacebookAccount::where('facebook_id', '123456789')->first();
$user = $facebookAccount->user;
```

### Get all Facebook users:
```php
$fbUsers = User::whereHas('facebookAccount')->get();
```

### Delete all Facebook accounts:
```php
FacebookAccount::query()->delete();
```

### Delete Facebook account for specific user:
```php
$user->facebookAccount()->delete();
```

## Migration Status

✅ `facebook_accounts` table created
✅ `facebook_id` removed from `users` table
✅ `email` made nullable in `users` table
✅ Backend updated to use new structure
✅ Models and relationships configured
