# Start Local Development Servers
# This script starts all required services in separate windows

Write-Host "Starting Ashcol ServiceHub Development Servers..." -ForegroundColor Cyan
Write-Host ""

# Check if .env exists
if (-not (Test-Path .env)) {
    Write-Host "[ERROR] .env file not found. Please run local-setup.ps1 first!" -ForegroundColor Red
    exit 1
}

# Start Laravel Server
Write-Host "Starting Laravel server..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$PWD'; Write-Host 'Laravel Development Server' -ForegroundColor Green; Write-Host 'Running on http://localhost:8000' -ForegroundColor Cyan; Write-Host ''; php artisan serve"

# Wait a bit
Start-Sleep -Seconds 2

# Start Vite
Write-Host "Starting Vite..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$PWD'; Write-Host 'Vite Development Server' -ForegroundColor Green; Write-Host ''; npm run dev"

# Wait a bit
Start-Sleep -Seconds 2

# Ask about MailHog
$mailhog = Read-Host "Do you want to start MailHog? (Y/n)"
if ($mailhog -ne 'n' -and $mailhog -ne 'N') {
    # Check if mailhog is installed
    $mailhogPath = Get-Command mailhog -ErrorAction SilentlyContinue
    if ($mailhogPath) {
        Write-Host "Starting MailHog..." -ForegroundColor Yellow
        Start-Process powershell -ArgumentList "-NoExit", "-Command", "Write-Host 'MailHog SMTP Server' -ForegroundColor Green; Write-Host 'SMTP: localhost:1025' -ForegroundColor Cyan; Write-Host 'Web UI: http://localhost:8025' -ForegroundColor Cyan; Write-Host ''; mailhog"
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
Write-Host "Laravel:  http://localhost:8000" -ForegroundColor White
Write-Host "Vite:     Check the Vite terminal window" -ForegroundColor White
Write-Host "MailHog:  http://localhost:8025 (if started)" -ForegroundColor White
Write-Host ""
Write-Host "Press Ctrl+C in each terminal window to stop the servers" -ForegroundColor Yellow
Write-Host ""
