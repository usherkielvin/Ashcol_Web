# User Registration Setup - Changes Summary

## Overview
Updated your Laravel backend to support the new user fields (`username`, `firstName`, `lastName`) required for your Android app while maintaining backward compatibility with the existing `name` field.

## Files Modified

### 1. **Database Migration** (`database/migrations/0001_01_01_000000_create_users_table.php`)
Added new fields to the users table:
- `username` (unique) - for Android app login
- `firstName` - user's first name
- `lastName` - user's last name
- `name` (nullable) - kept for backward compatibility
- `role` (default: 'customer') - user role field

### 2. **User Model** (`app/Models/User.php`)
Updated the `$fillable` array to include:
- `username`
- `firstName`
- `lastName`
- `name`
- `role`

### 3. **API Auth Controller** (`app/Http/Controllers/Api/AuthController.php`)

#### Updated Methods:

**a) `login()` method:**
- Now returns all user fields (username, firstName, lastName, name, email, role) in the response

**b) `register()` method:**
- Supports both old format (`name`) and new format (`username`, `firstName`, `lastName`)
- Validates optional `username`, `firstName`, `lastName` fields
- Requires either `name` OR both `firstName` and `lastName`
- Auto-generates `username` from email if not provided
- Generates full name from `firstName` and `lastName` for display purposes
- Returns all user fields in the response

**c) `verifyEmail()` method:**
- Updated to return all user fields in the response along with the token

### 4. **Migration Helper** (`database/migrations/2025_11_12_000000_add_username_and_name_fields_to_users_table.php`)
A new migration file that handles adding these fields to existing databases if needed.

## Next Steps

### Step 1: Run Migrations
```bash
php artisan migrate
```

This will:
- Create the updated `users` table with the new fields
- Apply the migration helper if needed

### Step 2: Test Registration via API

#### Android App Registration Request:
```json
POST /api/v1/register
{
    "username": "john_doe",
    "firstName": "John",
    "lastName": "Doe",
    "email": "john@example.com",
    "password": "SecurePassword123",
    "password_confirmation": "SecurePassword123"
}
```

#### Response (201 Created):
```json
{
    "success": true,
    "message": "Registration successful. Please verify your email.",
    "data": {
        "user": {
            "id": 1,
            "username": "john_doe",
            "firstName": "John",
            "lastName": "Doe",
            "name": "John Doe",
            "email": "john@example.com",
            "role": "customer"
        },
        "requires_verification": true
    }
}
```

#### Alternative: Using Old Format (name field)
```json
POST /api/v1/register
{
    "name": "Jane Smith",
    "email": "jane@example.com",
    "password": "SecurePassword123",
    "password_confirmation": "SecurePassword123"
}
```

### Step 3: Test Login via API

```json
POST /api/v1/login
{
    "email": "john@example.com",
    "password": "SecurePassword123"
}


#### Login Response (200 OK):
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "username": "john_doe",
            "firstName": "John",
            "lastName": "Doe",
            "name": "John Doe",
            "email": "john@example.com",
            "role": "customer"
        },
        "token": "your-access-token-here"
    }
}
```

### Step 4: Email Verification

After registration, the user needs to verify their email using the code sent to them:

```json
POST /api/v1/verify-email
{
    "email": "john@example.com",
    "code": "123456"
}
```

#### Verification Response (200 OK):
```json
{
    "success": true,
    "message": "Email verified successfully",
    "data": {
        "user": {
            "id": 1,
            "username": "john_doe",
            "firstName": "John",
            "lastName": "Doe",
            "name": "John Doe",
            "email": "john@example.com",
            "role": "customer"
        },
        "token": "your-access-token-here"
    }
}
```

## Key Features

✅ **Backward Compatibility** - Still supports the old `name` field format
✅ **Flexible Registration** - Accept either separate name fields or a full name field
✅ **Unique Username** - Username field has unique constraint
✅ **Email Verification** - Required before full access
✅ **Role-based Access** - Default role is 'customer'
✅ **Sanctum Authentication** - Secure token-based authentication

## Validation Rules

### Registration Field Validation:
- `username`: Optional, max 255 characters, must be unique
- `firstName`: Optional, max 255 characters
- `lastName`: Optional, max 255 characters
- `name`: Optional, max 255 characters
- `email`: Required, valid email, must be unique
- `password`: Required, minimum 8 characters, must be confirmed
- `role`: Optional, must be one of: `admin`, `staff`, or `customer`

**Important:** Either `name` OR both `firstName` and `lastName` must be provided.

## API Endpoints Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/register` | Register a new user |
| POST | `/api/v1/login` | Login user |
| POST | `/api/v1/verify-email` | Verify email with code |
| POST | `/api/v1/send-verification-code` | Send verification code |
| POST | `/api/v1/logout` | Logout user (requires auth) |
| GET | `/api/v1/user` | Get current user (requires auth) |
| GET | `/api/v1/profile` | Get user profile (requires auth) |

## Notes for Android App

Your Android app should now:
1. Send `username`, `firstName`, `lastName` during registration
2. Use the returned `token` for authenticated requests
3. Store the user data locally after successful registration
4. Handle the email verification step before allowing full app access
5. Use the token in the `Authorization` header for protected endpoints:
   ```
   Authorization: Bearer {token}
   ```

## Troubleshooting

If you encounter issues:

1. **Username already exists:** Make sure to generate unique usernames or handle the 422 validation error
2. **Email already registered:** Check if the user is already in the database
3. **Email verification fails:** Check the code expiration (10 minutes) and validity
4. **Password mismatch:** Ensure `password` and `password_confirmation` fields match

## Database Rollback

If needed, you can rollback the migrations:
```bash
php artisan migrate:rollback
```

This will revert the database schema to its previous state.
