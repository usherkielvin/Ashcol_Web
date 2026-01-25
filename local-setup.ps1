# Quick Setup Script for Local Development
# Run this script to set up your local development environment

Write-Host "====================================" -ForegroundColor Cyan
Write-Host "Ashcol ServiceHub - Local Setup" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""

# Check if .env exists
if (Test-Path .env) {
    Write-Host "[INFO] .env file already exists." -ForegroundColor Yellow
    $overwrite = Read-Host "Do you want to backup and recreate it? (y/N)"
    if ($overwrite -eq 'y' -or $overwrite -eq 'Y') {
        $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
        Copy-Item .env ".env.backup.$timestamp"
        Write-Host "[OK] Backed up existing .env to .env.backup.$timestamp" -ForegroundColor Green
        Copy-Item .env.example .env
        Write-Host "[OK] Created new .env from .env.example" -ForegroundColor Green
    } else {
        Write-Host "[SKIP] Keeping existing .env file" -ForegroundColor Yellow
    }
} else {
    Copy-Item .env.example .env
    Write-Host "[OK] Created .env from .env.example" -ForegroundColor Green
}

Write-Host ""
Write-Host "Generating application key..." -ForegroundColor Yellow
php artisan key:generate
Write-Host ""

Write-Host "====================================" -ForegroundColor Cyan
Write-Host "Configuration Required" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Please update the following in your .env file:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. DATABASE CONFIGURATION (Railway):" -ForegroundColor White
Write-Host "   - Get credentials from Railway dashboard" -ForegroundColor Gray
Write-Host "   - Update: DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD" -ForegroundColor Gray
Write-Host ""
Write-Host "2. MAIL CONFIGURATION:" -ForegroundColor White
Write-Host "   Option A - MailHog (Recommended):" -ForegroundColor Gray
Write-Host "     - Install: choco install mailhog" -ForegroundColor Gray
Write-Host "     - Run: mailhog" -ForegroundColor Gray
Write-Host "     - Web UI: http://localhost:8025" -ForegroundColor Gray
Write-Host "     - Already configured in .env (port 1025)" -ForegroundColor Gray
Write-Host ""
Write-Host "   Option B - Log Driver (Simple):" -ForegroundColor Gray
Write-Host "     - Change MAIL_MAILER=log in .env" -ForegroundColor Gray
Write-Host "     - Emails written to storage/logs/laravel.log" -ForegroundColor Gray
Write-Host ""
Write-Host "3. OAUTH (Optional):" -ForegroundColor White
Write-Host "   - Update Facebook/Google credentials if using social login" -ForegroundColor Gray
Write-Host ""

$continue = Read-Host "Press Enter to continue after updating .env file..."

Write-Host ""
Write-Host "====================================" -ForegroundColor Cyan
Write-Host "Installing Dependencies" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "Installing Composer dependencies..." -ForegroundColor Yellow
composer install --no-interaction
Write-Host ""

Write-Host "Installing NPM dependencies..." -ForegroundColor Yellow
npm install
Write-Host ""

Write-Host "====================================" -ForegroundColor Cyan
Write-Host "Database Migration" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""

$migrate = Read-Host "Do you want to run migrations now? (Y/n)"
if ($migrate -ne 'n' -and $migrate -ne 'N') {
    Write-Host "Running migrations..." -ForegroundColor Yellow
    php artisan migrate
    Write-Host ""
    
    $seed = Read-Host "Do you want to seed the database? (y/N)"
    if ($seed -eq 'y' -or $seed -eq 'Y') {
        Write-Host "Seeding database..." -ForegroundColor Yellow
        php artisan db:seed
    }
}

Write-Host ""
Write-Host "====================================" -ForegroundColor Cyan
Write-Host "Setup Complete!" -ForegroundColor Green
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Start Laravel server:" -ForegroundColor White
Write-Host "   php artisan serve" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Start Vite (in another terminal):" -ForegroundColor White
Write-Host "   npm run dev" -ForegroundColor Gray
Write-Host ""
Write-Host "3. Start MailHog (in another terminal, optional):" -ForegroundColor White
Write-Host "   mailhog" -ForegroundColor Gray
Write-Host ""
Write-Host "4. Open your browser:" -ForegroundColor White
Write-Host "   http://localhost:8000" -ForegroundColor Gray
Write-Host ""
Write-Host "For more information, see LOCAL_SETUP.md" -ForegroundColor Cyan
Write-Host ""
