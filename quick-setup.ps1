# Quick Setup Check Script
# Run this to verify all prerequisites are installed

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Prerequisites Check" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

$allGood = $true

# Check PHP
Write-Host "Checking PHP..." -ForegroundColor Yellow
if (Get-Command php -ErrorAction SilentlyContinue) {
    $phpVersion = php -v 2>&1 | Select-String "PHP" | Select-Object -First 1
    Write-Host "  ✅ PHP installed: $phpVersion" -ForegroundColor Green
} else {
    Write-Host "  ❌ PHP not found!" -ForegroundColor Red
    Write-Host "     Install XAMPP from: https://www.apachefriends.org/" -ForegroundColor Yellow
    Write-Host "     Or add PHP to PATH: C:\xampp\php" -ForegroundColor Yellow
    $allGood = $false
}

Write-Host ""

# Check Composer
Write-Host "Checking Composer..." -ForegroundColor Yellow
if (Get-Command composer -ErrorAction SilentlyContinue) {
    $composerVersion = composer --version 2>&1 | Select-Object -First 1
    Write-Host "  ✅ Composer installed: $composerVersion" -ForegroundColor Green
} else {
    Write-Host "  ❌ Composer not found!" -ForegroundColor Red
    Write-Host "     Install from: https://getcomposer.org/download/" -ForegroundColor Yellow
    $allGood = $false
}

Write-Host ""

# Check Node.js
Write-Host "Checking Node.js..." -ForegroundColor Yellow
if (Get-Command node -ErrorAction SilentlyContinue) {
    $nodeVersion = node -v
    Write-Host "  ✅ Node.js installed: v$nodeVersion" -ForegroundColor Green
} else {
    Write-Host "  ❌ Node.js not found!" -ForegroundColor Red
    Write-Host "     Install from: https://nodejs.org/ (LTS version)" -ForegroundColor Yellow
    $allGood = $false
}

Write-Host ""

# Check npm
Write-Host "Checking npm..." -ForegroundColor Yellow
if (Get-Command npm -ErrorAction SilentlyContinue) {
    $npmVersion = npm -v
    Write-Host "  ✅ npm installed: v$npmVersion" -ForegroundColor Green
} else {
    Write-Host "  ❌ npm not found!" -ForegroundColor Red
    Write-Host "     npm comes with Node.js. Reinstall Node.js if missing." -ForegroundColor Yellow
    $allGood = $false
}

Write-Host ""

# Check Git
Write-Host "Checking Git..." -ForegroundColor Yellow
if (Get-Command git -ErrorAction SilentlyContinue) {
    $gitVersion = git --version
    Write-Host "  ✅ Git installed: $gitVersion" -ForegroundColor Green
} else {
    Write-Host "  ❌ Git not found!" -ForegroundColor Red
    Write-Host "     Install from: https://git-scm.com/download/win" -ForegroundColor Yellow
    $allGood = $false
}

Write-Host ""

# Check .env file
Write-Host "Checking .env file..." -ForegroundColor Yellow
if (Test-Path .env) {
    Write-Host "  ✅ .env file exists" -ForegroundColor Green
} else {
    Write-Host "  ❌ .env file not found!" -ForegroundColor Red
    Write-Host "     Copy from .env.example: Copy-Item .env.example .env" -ForegroundColor Yellow
    Write-Host "     Then run: php artisan key:generate" -ForegroundColor Yellow
    $allGood = $false
}

Write-Host ""

# Check if in correct directory
Write-Host "Checking project structure..." -ForegroundColor Yellow
if (Test-Path "artisan" -and Test-Path "composer.json" -and Test-Path "package.json") {
    Write-Host "  ✅ Laravel project detected" -ForegroundColor Green
} else {
    Write-Host "  ⚠️  Not in Laravel project root?" -ForegroundColor Yellow
    Write-Host "     Make sure you're in: C:\xampp\htdocs\Ashcol_Web" -ForegroundColor Yellow
}

Write-Host ""

# Check vendor directory (Composer dependencies)
Write-Host "Checking Composer dependencies..." -ForegroundColor Yellow
if (Test-Path "vendor") {
    Write-Host "  ✅ Composer dependencies installed (vendor/ exists)" -ForegroundColor Green
} else {
    Write-Host "  ⚠️  Composer dependencies not installed" -ForegroundColor Yellow
    Write-Host "     Run: composer install" -ForegroundColor Yellow
}

Write-Host ""

# Check node_modules (npm dependencies)
Write-Host "Checking npm dependencies..." -ForegroundColor Yellow
if (Test-Path "node_modules") {
    Write-Host "  ✅ npm dependencies installed (node_modules/ exists)" -ForegroundColor Green
} else {
    Write-Host "  ⚠️  npm dependencies not installed" -ForegroundColor Yellow
    Write-Host "     Run: npm install" -ForegroundColor Yellow
}

Write-Host ""

# Summary
Write-Host "========================================" -ForegroundColor Cyan
if ($allGood) {
    Write-Host "  ✅ All prerequisites are installed!" -ForegroundColor Green
    Write-Host "  You can now run: .\start-dev.ps1" -ForegroundColor Cyan
} else {
    Write-Host "  ❌ Some prerequisites are missing" -ForegroundColor Red
    Write-Host "  Please install missing items above" -ForegroundColor Yellow
    Write-Host "  See COLLABORATOR_SETUP.md for details" -ForegroundColor Yellow
}
Write-Host "========================================`n" -ForegroundColor Cyan
