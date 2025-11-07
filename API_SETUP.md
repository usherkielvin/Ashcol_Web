# API Setup Instructions

## Step 1: Install Laravel Sanctum

Run the following command in your Laravel project directory:

```bash
composer require laravel/sanctum
```

## Step 2: Publish Sanctum Configuration

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

## Step 3: Run Migrations

```bash
php artisan migrate
```

This will create the `personal_access_tokens` table needed for API authentication.

## Step 4: Configure CORS (for Android app)

Update `config/cors.php` to allow requests from your Android app:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'], // Change this in production
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => false,
```

## Step 5: Start Laravel Server

```bash
php artisan serve
```

Your API will be available at: `http://localhost:8000`

## API Endpoints

### Login
- **POST** `/api/v1/login`
- Body: `{ "email": "user@example.com", "password": "password" }`
- Returns: User data and token

### Get User
- **GET** `/api/v1/user`
- Header: `Authorization: Bearer {token}`
- Returns: User data

### Logout
- **POST** `/api/v1/logout`
- Header: `Authorization: Bearer {token}`
- Returns: Success message

## Testing

You can test the API using curl or Postman:

```bash
# Login
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'

# Get User (replace TOKEN with actual token)
curl -X GET http://localhost:8000/api/v1/user \
  -H "Authorization: Bearer TOKEN"
```

## Android App Configuration

Update `ApiClient.java` in your Android project:

- For Android Emulator: Use `http://10.0.2.2:8000/`
- For Physical Device: Use `http://YOUR_COMPUTER_IP:8000/`
  - Find your IP: `ipconfig` (Windows) or `ifconfig` (Mac/Linux)

Make sure your computer and Android device are on the same network.

