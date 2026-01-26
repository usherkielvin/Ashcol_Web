# Start Local Development Servers
# This script starts all required services in separate windows

Write-Host "Starting Ashcol ServiceHub Development Servers..." -ForegroundColor Cyan
Write-Host ""

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
Write-Host "Starting Laravel server..." -ForegroundColor Yellow
$currentDir = $PWD.Path
$laravelCmd = "cd '$currentDir'; Write-Host 'Laravel Development Server' -ForegroundColor Green; Write-Host 'Running on http://localhost:8000' -ForegroundColor Cyan; Write-Host ''; php artisan serve"
Start-Process powershell -ArgumentList "-NoExit", "-Command", $laravelCmd

# Wait a bit
Start-Sleep -Seconds 2

# Start Vite
Write-Host "Starting Vite..." -ForegroundColor Yellow
$viteCmd = "cd '$currentDir'; Write-Host 'Vite Development Server' -ForegroundColor Green; Write-Host ''; npm run dev"
Start-Process powershell -ArgumentList "-NoExit", "-Command", $viteCmd

# Wait a bit
Start-Sleep -Seconds 2

# Start Queue Worker (for async email sending)
Write-Host "Starting Queue Worker..." -ForegroundColor Yellow
$queueCmd = "cd '$currentDir'; Write-Host 'Laravel Queue Worker' -ForegroundColor Green; Write-Host 'Processing queued jobs (emails, etc.)' -ForegroundColor Cyan; Write-Host ''; php artisan queue:work"
Start-Process powershell -ArgumentList "-NoExit", "-Command", $queueCmd

# Wait a bit
Start-Sleep -Seconds 2

# Ask about MailHog
$mailhog = Read-Host "Do you want to start MailHog? (Y/n)"
if ($mailhog -ne 'n' -and $mailhog -ne 'N') {
    # Check if mailhog is installed
    $mailhogPath = Get-Command mailhog -ErrorAction SilentlyContinue
    if ($mailhogPath) {
        Write-Host "Starting MailHog..." -ForegroundColor Yellow
        $mailhogCmd = "Write-Host 'MailHog SMTP Server' -ForegroundColor Green; Write-Host 'SMTP: localhost:1025' -ForegroundColor Cyan; Write-Host 'Web UI: http://localhost:8025' -ForegroundColor Cyan; Write-Host ''; mailhog"
        Start-Process powershell -ArgumentList "-NoExit", "-Command", $mailhogCmd
    } else {
        Write-Host "[WARNING] MailHog not found. Install with: choco install mailhog" -ForegroundColor Yellow
        Write-Host "[INFO] Or change MAIL_MAILER=log in .env to use log driver" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "====================================" -ForegroundColor Cyan
Write-Host "Development servers started!" -ForegroundColor Green
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Laravel:      http://localhost:8000" -ForegroundColor White
Write-Host "Vite:         Check the Vite terminal window" -ForegroundColor White
Write-Host "Queue Worker: Processing jobs in background" -ForegroundColor White
Write-Host "MailHog:      http://localhost:8025 (if started)" -ForegroundColor White
Write-Host ""
Write-Host "[OK] Emails will now send asynchronously (instant API response!)" -ForegroundColor Green
Write-Host ""
Write-Host "Press Ctrl+C in each terminal window to stop the servers" -ForegroundColor Yellow
Write-Host ""
