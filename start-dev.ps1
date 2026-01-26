# Start Local Development Servers
# This script starts all required services in separate windows

# Check prerequisites
$errors = @()

# Check PHP
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    $errors += "PHP is not installed or not in PATH. Install XAMPP from https://www.apachefriends.org/"
}

# Check Composer
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    $errors += "Composer is not installed. Install from https://getcomposer.org/download/"
}

# Check npm
if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
    $errors += "npm is not installed. Install Node.js from https://nodejs.org/"
}

# Check if .env exists
if (-not (Test-Path .env)) {
    $errors += ".env file not found. Copy from .env.example: Copy-Item .env.example .env"
}

# Check if vendor directory exists (Composer dependencies)
if (-not (Test-Path "vendor")) {
    $errors += "Composer dependencies not installed. Run: composer install"
}

# Check if node_modules exists (npm dependencies)
if (-not (Test-Path "node_modules")) {
    $errors += "npm dependencies not installed. Run: npm install"
}

# Check if we're in the correct directory (Laravel project)
if (-not (Test-Path "artisan")) {
    $errors += "Not in Laravel project root. Make sure you're in: C:\xampp\htdocs\Ashcol_Web"
}

# Display errors if any
if ($errors.Count -gt 0) {
    Write-Host "========================================" -ForegroundColor Red
    Write-Host "[ERROR] Prerequisites not met!" -ForegroundColor Red
    Write-Host "========================================" -ForegroundColor Red
    Write-Host ""
    foreach ($error in $errors) {
        Write-Host "  [X] $error" -ForegroundColor Red
    }
    Write-Host ""
    Write-Host "[TIP] Run .\quick-setup.ps1 to check all prerequisites" -ForegroundColor Yellow
    Write-Host "[INFO] See COLLABORATOR_SETUP.md for detailed setup instructions" -ForegroundColor Yellow
    Write-Host ""
    exit 1
}

# Start Laravel Server
$currentDir = $PWD.Path
$laravelCmd = "cd '$currentDir'; php artisan serve"
Start-Process powershell -ArgumentList "-NoExit", "-Command", $laravelCmd

# Wait a bit
Start-Sleep -Seconds 2

# Start Vite
$viteCmd = "cd '$currentDir'; npm run dev"
Start-Process powershell -ArgumentList "-NoExit", "-Command", $viteCmd

# Wait a bit
Start-Sleep -Seconds 2

# Start Queue Worker (for async email sending)
$queueCmd = "cd '$currentDir'; php artisan queue:work"
Start-Process powershell -ArgumentList "-NoExit", "-Command", $queueCmd
